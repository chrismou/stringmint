<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter;

use Chrismou\StringMint\Counter\Pdo\DialectResolver;
use Chrismou\StringMint\Counter\Pdo\PdoDialectInterface;
use Chrismou\StringMint\Exception\CounterStoreException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * A PDO-backed CounterStoreInterface with atomic per-length counters.
 *
 * Stores one row per (name, string_length) in the counter table. Any length works immediately:
 * the first next() call for an unseen length inserts the row lazily.
 *
 * Does not open or close transactions. Works inside a caller's transaction, with the caveat
 * that MySQL / PostgreSQL / SQL Server hold a row lock until the transaction commits.
 *
 * Does not assume ERRMODE_EXCEPTION; checks execute() return values and wraps both PDOException
 * and false results in CounterStoreException.
 */
final readonly class PdoCounterStore implements CounterStoreInterface
{
    public const DEFAULT_TABLE = 'stringmint_counters';

    /** The safe identifier pattern (optional schema prefix). */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    private PdoDialectInterface $dialect;
    private string $quotedTable;
    private string $quotedNameColumn;
    private string $quotedLengthColumn;
    private string $quotedValueColumn;

    /**
     * @throws InvalidTableNameException when the table name fails the identifier regex
     * @throws InvalidArgumentException when the counter name is not 1-191 bytes
     */
    public function __construct(
        private PDO $pdo,
        private string $counterName = 'default',
        string $tableName = self::DEFAULT_TABLE,
        ?PdoDialectInterface $dialect = null,
        private int $maximumCompareAndSwapAttempts = 50,
    ) {
        $nameLength = strlen($counterName);
        if ($nameLength < 1 || $nameLength > 191) {
            throw new InvalidArgumentException(
                "Counter name must be between 1 and 191 bytes, got {$nameLength}.",
            );
        }

        if (!preg_match(self::IDENTIFIER_PATTERN, $tableName)) {
            throw new InvalidTableNameException(
                "Invalid table name '{$tableName}'. Use only letters, digits and underscores, with an optional schema prefix.",
            );
        }

        $this->dialect = $dialect ?? DialectResolver::resolve($pdo);
        $this->quotedTable = $this->dialect->quoteIdentifier($tableName);

        // Column names go through the dialect too: MySQL treats double-quoted names as string literals.
        $this->quotedNameColumn = $this->dialect->quoteIdentifier('name');
        $this->quotedLengthColumn = $this->dialect->quoteIdentifier('string_length');
        $this->quotedValueColumn = $this->dialect->quoteIdentifier('counter_value');
    }

    /**
     * Returns the counter namespace name.
     */
    public function name(): string
    {
        return $this->counterName;
    }

    /**
     * Atomically reserves and returns the next zero-based index for the given length.
     *
     * On the first call for a given length, inserts a seed row (counter_value = 0).
     *
     * @throws CounterStoreException on database errors
     */
    public function next(int $length): int
    {
        $newValue = $this->callIncrementAndFetch($length);

        if ($newValue === null) {
            // No row exists yet for this (name, length) pair - insert the seed row.
            $this->insertSeedRow($length);
            // Retry; a second null means the insert+update cycle failed.
            $newValue = $this->callIncrementAndFetch($length);
            if ($newValue === null) {
                throw new CounterStoreException(
                    "Failed to increment counter for name='{$this->counterName}', length={$length} after seeding.",
                );
            }
        }

        // The stored counter_value represents "how many indexes have been reserved".
        // After incrementing from old to new, the just-reserved index is old = new - 1.
        return $newValue - 1;
    }

    /**
     * Calls the dialect's incrementAndFetch, falling back to the CAS loop when the sentinel is returned.
     *
     * Wraps any PDOException thrown by the dialect in CounterStoreException so callers always see
     * CounterStoreException for persistence-layer failures (the dialect is not required to wrap).
     *
     * @throws CounterStoreException on any persistence failure
     */
    private function callIncrementAndFetch(int $length): ?int
    {
        try {
            $raw = $this->dialect->incrementAndFetch($this->pdo, $this->quotedTable, $this->counterName, $length);
        } catch (PDOException $e) {
            throw new CounterStoreException(
                "Failed to increment counter for name='{$this->counterName}', length={$length}: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        if ($raw === PdoDialectInterface::USE_COMPARE_AND_SWAP) {
            return $this->compareAndSwap($length);
        }

        return $raw;
    }

    /**
     * Portable compare-and-swap increment loop (used by SqliteDialect and the unknown-driver fallback).
     *
     * Returns null when no row exists for (counterName, length), triggering the seed path.
     * Retries up to maximumCompareAndSwapAttempts times on concurrent writers; throws CounterStoreException
     * if all attempts fail.
     */
    private function compareAndSwap(int $length): ?int
    {
        for ($attempt = 0; $attempt < $this->maximumCompareAndSwapAttempts; $attempt++) {
            // Read the current value.
            $stmt = $this->prepareStatement(
                "SELECT {$this->quotedValueColumn} FROM {$this->quotedTable}"
                    . " WHERE {$this->quotedNameColumn} = ? AND {$this->quotedLengthColumn} = ?",
            );
            $this->executeStatement($stmt, [$this->counterName, $length]);
            $current = $stmt->fetchColumn();

            // No row - signal the seed path.
            if ($current === false) {
                return null;
            }

            $oldValue = (int) $current;
            $newValue = $oldValue + 1;

            // Conditionally update.
            $updateStmt = $this->prepareStatement(
                "UPDATE {$this->quotedTable} SET {$this->quotedValueColumn} = ?"
                    . " WHERE {$this->quotedNameColumn} = ? AND {$this->quotedLengthColumn} = ?"
                    . " AND {$this->quotedValueColumn} = ?",
            );
            $this->executeStatement($updateStmt, [$newValue, $this->counterName, $length, $oldValue]);

            if ($updateStmt->rowCount() === 1) {
                return $newValue;
            }

            // Another writer modified the row concurrently; re-read and retry.
        }

        throw new CounterStoreException(
            "Compare-and-swap failed after {$this->maximumCompareAndSwapAttempts} attempts "
                . "for name='{$this->counterName}', length={$length}.",
        );
    }

    /**
     * Inserts a seed row with counter_value = 0, swallowing duplicate-key errors (SQLSTATE 23xxx).
     *
     * A duplicate-key failure means another process raced the insert, which is fine.
     */
    private function insertSeedRow(int $length): void
    {
        try {
            $stmt = $this->prepareStatement(
                "INSERT INTO {$this->quotedTable}"
                    . " ({$this->quotedNameColumn}, {$this->quotedLengthColumn}, {$this->quotedValueColumn})"
                    . ' VALUES (?, ?, 0)',
            );
            $this->executeStatement($stmt, [$this->counterName, $length]);
        } catch (CounterStoreException $e) {
            // Swallow duplicate-key violations (SQLSTATE 23000, 23505).
            $sqlState = $e->getPrevious() instanceof PDOException
                ? (string) $e->getPrevious()->getCode()
                : '';

            if (!str_starts_with($sqlState, '23')) {
                throw $e;
            }
        }
    }

    /**
     * Prepares a statement, wrapping errors in CounterStoreException.
     *
     * @throws CounterStoreException
     */
    private function prepareStatement(string $sql): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw new CounterStoreException(
                "Failed to prepare statement: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        if ($stmt === false) {
            $error = $this->pdo->errorInfo();
            throw new CounterStoreException(
                'Failed to prepare statement: ' . ($error[2] ?? 'unknown error'),
            );
        }

        return $stmt;
    }

    /**
     * Executes a statement, wrapping errors in CounterStoreException.
     *
     * @param list<mixed> $params
     * @throws CounterStoreException
     */
    private function executeStatement(PDOStatement $stmt, array $params = []): void
    {
        try {
            $result = $stmt->execute($params);
        } catch (PDOException $e) {
            throw new CounterStoreException(
                "Statement execution failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        if ($result === false) {
            $error = $stmt->errorInfo();
            // Pass the SQLSTATE as the code so insertSeedRow()'s duplicate-key detection
            // works correctly when the connection is in ERRMODE_SILENT.
            // $error[0] is the SQLSTATE string (e.g. '23000'); casting to int gives 23000,
            // which str_starts_with((string) ..., '23') matches the same way as the real string.
            $errorCode = (int) ($error[0] ?? 0);
            $pdoError = new PDOException($error[2] ?? 'unknown error', $errorCode);
            throw new CounterStoreException(
                'Statement execution failed: ' . ($error[2] ?? 'unknown error'),
                $errorCode,
                $pdoError,
            );
        }
    }
}
