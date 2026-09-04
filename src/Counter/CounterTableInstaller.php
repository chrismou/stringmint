<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter;

use Chrismou\StringMint\Counter\Pdo\DialectResolver;
use Chrismou\StringMint\Counter\Pdo\PdoDialectInterface;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use PDO;
use PDOException;

/**
 * Installs and removes the counter table that PdoCounterStore requires.
 *
 * Acts as a lightweight migration: install() is idempotent (CREATE TABLE IF NOT EXISTS or
 * equivalent), uninstall() drops the table, and installSql() / uninstallSql() expose the raw
 * SQL for consumers who prefer to paste it into their own migration tool.
 *
 * Does not install the table automatically; call install() (or run installSql()) as an explicit
 * step before first use.
 */
final readonly class CounterTableInstaller
{
    /** The safe identifier pattern (optional schema prefix). */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    private PdoDialectInterface $dialect;
    private string $quotedTable;

    /**
     * @throws InvalidTableNameException when the table name fails the identifier regex
     */
    public function __construct(
        private PDO $pdo,
        string $tableName = PdoCounterStore::DEFAULT_TABLE,
        ?PdoDialectInterface $dialect = null,
    ) {
        if (!preg_match(self::IDENTIFIER_PATTERN, $tableName)) {
            throw new InvalidTableNameException(
                "Invalid table name '{$tableName}'. Use only letters, digits and underscores, with an optional schema prefix.",
            );
        }

        $this->dialect = $dialect ?? DialectResolver::resolve($pdo);
        $this->quotedTable = $this->dialect->quoteIdentifier($tableName);
    }

    /**
     * Creates the counter table if it does not already exist (idempotent).
     *
     * Checks isInstalled() first so drivers that reject IF NOT EXISTS still get an idempotent
     * install when the table already exists.
     */
    public function install(): void
    {
        if ($this->isInstalled()) {
            return;
        }

        $this->pdo->exec($this->installSql());
    }

    /**
     * Drops the counter table if it exists (idempotent).
     */
    public function uninstall(): void
    {
        $this->pdo->exec($this->uninstallSql());
    }

    /**
     * Returns true when the counter table exists and is accessible.
     */
    public function isInstalled(): bool
    {
        try {
            $result = $this->pdo->query(
                'SELECT 1 FROM ' . $this->quotedTable . ' WHERE 1 = 0',
            );
            return $result !== false;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Returns the CREATE TABLE SQL for the counter table.
     *
     * Useful when you want to integrate the migration into your own tooling.
     */
    public function installSql(): string
    {
        return $this->dialect->createTableSql($this->quotedTable);
    }

    /**
     * Returns the DROP TABLE SQL for the counter table.
     */
    public function uninstallSql(): string
    {
        return $this->dialect->dropTableSql($this->quotedTable);
    }
}
