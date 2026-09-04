<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Existence;

use Chrismou\StringMint\Counter\Pdo\DialectResolver;
use Chrismou\StringMint\Counter\Pdo\PdoDialectInterface;
use Chrismou\StringMint\Exception\ExistenceCheckException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use PDO;
use PDOException;

/**
 * An ExistenceCheckerInterface that queries a single column in a PDO-connected table.
 *
 * Uses SELECT 1 FROM {table} WHERE {column} = ? for maximum portability across drivers.
 * Table and column names are validated against a safe identifier regex (they cannot be bound
 * as query parameters and are inlined into the SQL).
 *
 * Database failures are never swallowed: a checker that silently answered "does not exist" on a
 * misnamed table or a dropped connection would let the generator issue colliding strings.
 */
final readonly class PdoColumnExistenceChecker implements ExistenceCheckerInterface
{
    private string $sql;

    /** The safe identifier pattern: optional schema prefix, alphanumeric + underscore only. */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /**
     * @throws InvalidTableNameException when the table or column name fails validation
     */
    public function __construct(
        private PDO $pdo,
        string $tableName,
        string $columnName,
        ?PdoDialectInterface $dialect = null,
    ) {
        if (!preg_match(self::IDENTIFIER_PATTERN, $tableName)) {
            throw new InvalidTableNameException(
                "Invalid table name '{$tableName}'. Use only letters, digits and underscores, with an optional schema prefix.",
            );
        }

        if (!preg_match(self::IDENTIFIER_PATTERN, $columnName)) {
            throw new InvalidTableNameException(
                "Invalid column name '{$columnName}'. Use only letters, digits and underscores.",
            );
        }

        $resolvedDialect = $dialect ?? DialectResolver::resolve($pdo);
        $quotedTable = $resolvedDialect->quoteIdentifier($tableName);
        $quotedColumn = $resolvedDialect->quoteIdentifier($columnName);

        $this->sql = "SELECT 1 FROM {$quotedTable} WHERE {$quotedColumn} = ?";
    }

    /**
     * Returns true when a row with $candidate in the configured column exists.
     *
     * Works with any PDO error mode: prepare() / execute() returning false is treated the same as a
     * thrown PDOException.
     *
     * @throws ExistenceCheckException when the query cannot be prepared or executed
     */
    public function exists(string $candidate): bool
    {
        try {
            $stmt = $this->pdo->prepare($this->sql);

            if ($stmt === false) {
                throw $this->failure('prepare', $this->pdo->errorInfo());
            }

            if ($stmt->execute([$candidate]) === false) {
                throw $this->failure('execute', $stmt->errorInfo());
            }

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            throw new ExistenceCheckException(
                "Existence check failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * Builds the exception for a false return from prepare() or execute() (ERRMODE_SILENT / ERRMODE_WARNING).
     *
     * @param array{0?: string|null, 1?: int|string|null, 2?: string|null} $errorInfo
     */
    private function failure(string $step, array $errorInfo): ExistenceCheckException
    {
        $message = $errorInfo[2] ?? 'unknown error';

        return new ExistenceCheckException(
            "Existence check failed to {$step} the query: {$message}",
            (int) ($errorInfo[0] ?? 0),
            new PDOException($message, (int) ($errorInfo[0] ?? 0)),
        );
    }
}
