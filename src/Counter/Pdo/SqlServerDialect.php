<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;
use PDOException;

/**
 * SQL Server (sqlsrv / dblib) dialect for the counter table.
 *
 * Uses square-bracket identifier quoting, NVARCHAR(191) for the name column,
 * and UPDATE ... OUTPUT INSERTED.counter_value for an atomic increment-and-fetch.
 * CREATE TABLE is guarded with IF OBJECT_ID(...) IS NULL for idempotency.
 */
final class SqlServerDialect implements PdoDialectInterface
{
    use ExecutesIncrementStatement;

    /**
     * Wraps each identifier segment in square brackets.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            [$schema, $table] = explode('.', $identifier, 2);
            return '[' . $schema . '].[' . $table . ']';
        }

        return '[' . $identifier . ']';
    }

    /**
     * Returns a CREATE TABLE statement guarded with IF OBJECT_ID(...) IS NULL.
     *
     * Uses NVARCHAR(191) for the name column and BIGINT for the counter.
     */
    public function createTableSql(string $quotedTable): string
    {
        // Strip quoting to extract the raw name for OBJECT_ID().
        $rawName = str_replace(['[', ']'], '', $quotedTable);

        return "IF OBJECT_ID(N'{$rawName}', 'U') IS NULL "
            . "CREATE TABLE {$quotedTable} ("
            . "[name] NVARCHAR(191) NOT NULL, "
            . "[string_length] INT NOT NULL, "
            . "[counter_value] BIGINT NOT NULL DEFAULT 0, "
            . "PRIMARY KEY ([name], [string_length])"
            . ')';
    }

    /**
     * Returns a DROP TABLE statement guarded with IF OBJECT_ID(...) IS NOT NULL.
     */
    public function dropTableSql(string $quotedTable): string
    {
        $rawName = str_replace(['[', ']'], '', $quotedTable);

        return "IF OBJECT_ID(N'{$rawName}', 'U') IS NOT NULL DROP TABLE {$quotedTable}";
    }

    /**
     * Increments the counter using UPDATE ... OUTPUT INSERTED and returns the new value, or null on no match.
     *
     * @return int|null int (new value after increment) | null (row not found: fetchColumn returns false)
     *
     * @throws PDOException when prepare() or execute() fails; propagates unchanged so PdoCounterStore can wrap it
     */
    public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): int|null
    {
        $stmt = $this->executeIncrement(
            $pdo,
            "UPDATE {$quotedTable} SET [counter_value] = [counter_value] + 1 "
                . "OUTPUT INSERTED.[counter_value] "
                . "WHERE [name] = ? AND [string_length] = ?",
            [$counterName, $length],
        );

        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }
}
