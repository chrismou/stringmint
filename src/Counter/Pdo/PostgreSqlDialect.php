<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;
use PDOException;

/**
 * PostgreSQL dialect for the counter table.
 *
 * Uses double-quoted identifier quoting and UPDATE ... RETURNING for an atomic increment-and-fetch.
 */
final class PostgreSqlDialect implements PdoDialectInterface
{
    use ExecutesIncrementStatement;

    /**
     * Wraps each identifier segment in double quotes.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            [$schema, $table] = explode('.', $identifier, 2);
            return '"' . $schema . '"."' . $table . '"';
        }

        return '"' . $identifier . '"';
    }

    /**
     * Returns a CREATE TABLE IF NOT EXISTS statement using PostgreSQL column types.
     */
    public function createTableSql(string $quotedTable): string
    {
        return "CREATE TABLE IF NOT EXISTS {$quotedTable} ("
            . '"name" VARCHAR(191) NOT NULL, '
            . '"string_length" INT NOT NULL, '
            . '"counter_value" BIGINT NOT NULL DEFAULT 0, '
            . 'PRIMARY KEY ("name", "string_length")'
            . ')';
    }

    /**
     * Returns a DROP TABLE IF EXISTS statement.
     */
    public function dropTableSql(string $quotedTable): string
    {
        return "DROP TABLE IF EXISTS {$quotedTable}";
    }

    /**
     * Increments the counter using UPDATE ... RETURNING and returns the new value, or null on no match.
     *
     * @return int|null int (new value after increment) | null (row not found: fetchColumn returns false)
     *
     * @throws PDOException when prepare() or execute() fails; propagates unchanged so PdoCounterStore can wrap it
     */
    public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): int|null
    {
        $stmt = $this->executeIncrement(
            $pdo,
            "UPDATE {$quotedTable} SET \"counter_value\" = \"counter_value\" + 1 "
                . "WHERE \"name\" = ? AND \"string_length\" = ? RETURNING \"counter_value\"",
            [$counterName, $length],
        );

        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }
}
