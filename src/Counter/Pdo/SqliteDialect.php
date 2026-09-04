<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;

/**
 * SQLite dialect for the counter table (also the fallback for unknown PDO drivers).
 *
 * Uses double-quoted identifier quoting (standard SQL) and returns USE_COMPARE_AND_SWAP from
 * incrementAndFetch() to delegate to PdoCounterStore's portable compare-and-swap loop. This avoids
 * relying on RETURNING (SQLite >= 3.35 only) and keeps the SQL dialect-agnostic enough to serve
 * as a fallback for drivers not explicitly supported.
 *
 * CREATE TABLE IF NOT EXISTS and DROP TABLE IF EXISTS are standard SQLite syntax. Unknown drivers
 * receiving this dialect on a first install may need installSql() pasted into their own tool or a
 * custom PdoDialectInterface injected; the isInstalled() guard in CounterTableInstaller covers re-installs.
 */
final class SqliteDialect implements PdoDialectInterface
{
    /**
     * Wraps each identifier segment in double quotes (standard SQL).
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
     * Returns a CREATE TABLE IF NOT EXISTS statement using SQLite column types.
     *
     * TEXT / INTEGER / INTEGER map to SQLite's type affinity rules. The INTEGER primary key
     * pair is compatible with both SQLite and ISO SQL.
     */
    public function createTableSql(string $quotedTable): string
    {
        return "CREATE TABLE IF NOT EXISTS {$quotedTable} ("
            . '"name" TEXT NOT NULL, '
            . '"string_length" INTEGER NOT NULL, '
            . '"counter_value" INTEGER NOT NULL DEFAULT 0, '
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
     * Signals that PdoCounterStore should use its compare-and-swap loop.
     *
     * SQLite serialises writers at the database level, so the CAS loop rarely retries.
     *
     * @return PdoDialectInterface::USE_COMPARE_AND_SWAP
     */
    public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): string
    {
        return self::USE_COMPARE_AND_SWAP;
    }
}
