<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;
use PDOException;

/**
 * MySQL (and MariaDB) dialect for the counter table.
 *
 * Uses backtick identifier quoting and the LAST_INSERT_ID() trick to perform an atomic
 * increment-and-fetch in a single statement without RETURNING support.
 */
final class MySqlDialect implements PdoDialectInterface
{
    use ExecutesIncrementStatement;

    /**
     * Wraps each identifier segment in backticks.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            [$schema, $table] = explode('.', $identifier, 2);
            return '`' . $schema . '`.`' . $table . '`';
        }

        return '`' . $identifier . '`';
    }

    /**
     * Returns a CREATE TABLE IF NOT EXISTS statement using MySQL column types.
     *
     * VARCHAR(191) keeps the primary-key index within the 767-byte limit on older MySQL with utf8mb4.
     */
    public function createTableSql(string $quotedTable): string
    {
        return "CREATE TABLE IF NOT EXISTS {$quotedTable} ("
            . '`name` VARCHAR(191) NOT NULL, '
            . '`string_length` INT NOT NULL, '
            . '`counter_value` BIGINT NOT NULL DEFAULT 0, '
            . 'PRIMARY KEY (`name`, `string_length`)'
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
     * Increments the counter using LAST_INSERT_ID() and returns the new value, or null on no match.
     *
     * lastInsertId() returns a string on PDO/MySQL; we cast to int as documented in side effect 11.
     *
     * @return int|null int (new value after increment) | null (row not found: rowCount === 0)
     *
     * @throws PDOException when prepare() or execute() fails; propagates unchanged so PdoCounterStore can wrap it
     */
    public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): int|null
    {
        $stmt = $this->executeIncrement(
            $pdo,
            "UPDATE {$quotedTable} SET `counter_value` = LAST_INSERT_ID(`counter_value` + 1) "
                . "WHERE `name` = ? AND `string_length` = ?",
            [$counterName, $length],
        );

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) $pdo->lastInsertId();
    }
}
