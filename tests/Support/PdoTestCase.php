<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Chrismou\StringMint\Counter\CounterTableInstaller;
use Chrismou\StringMint\Counter\Pdo\DialectResolver;
use Closure;
use PDO;
use PDOStatement;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Abstract base class for tests that require PDO connections.
 *
 * Provides static DataProvider methods (pdoDataset and installedPdoDataset) that supply SQLite
 * unconditionally and optional MySQL/PostgreSQL when the corresponding env DSNs are set.
 * Unit tests that only need sqlitePdo() directly may also extend this class.
 */
abstract class PdoTestCase extends TestCase
{
    /**
     * Returns a fresh in-memory SQLite PDO connection.
     *
     * Marks the calling test as skipped when pdo_sqlite is not available.
     */
    protected static function sqlitePdo(): PDO
    {
        return PdoFactory::sqlite();
    }

    /**
     * DataProvider: one PDO factory closure per available database driver.
     *
     * SQLite is always included. MySQL and PostgreSQL are added only when the corresponding
     * env DSNs are set, so CI without a database server runs cleanly with one entry.
     * Each entry is a Closure so the test receives a fresh connection, guaranteeing isolation.
     *
     * @return array<string, list<Closure(): PDO>>
     */
    public static function pdoDataset(): array
    {
        $entries = [
            'SQLite' => [fn (): PDO => PdoFactory::sqlite()],
        ];

        if (PdoFactory::fromEnv('STRINGMINT_MYSQL_DSN') !== null) {
            $entries['MySQL'] = [fn (): PDO => self::freshConnectionFromEnv('STRINGMINT_MYSQL_DSN')];
        }

        if (PdoFactory::fromEnv('STRINGMINT_PGSQL_DSN') !== null) {
            $entries['PostgreSQL'] = [fn (): PDO => self::freshConnectionFromEnv('STRINGMINT_PGSQL_DSN')];
        }

        return $entries;
    }

    /**
     * Connects to a server-backed database from its env DSN and drops the tables the integration
     * tests create, so every test starts from the same empty state SQLite's in-memory database gives.
     */
    private static function freshConnectionFromEnv(string $envVariable): PDO
    {
        $pdo = PdoFactory::fromEnv($envVariable)
            ?? throw new RuntimeException("{$envVariable} became unavailable.");

        (new CounterTableInstaller($pdo))->uninstall();
        $pdo->exec('DROP TABLE IF EXISTS ' . self::quoteIdentifier($pdo, 'links'));

        return $pdo;
    }

    /**
     * Quotes an identifier the way the connection's dialect expects (backticks on MySQL, double quotes elsewhere).
     */
    protected static function quoteIdentifier(PDO $pdo, string $identifier): string
    {
        return DialectResolver::resolve($pdo)->quoteIdentifier($identifier);
    }

    /**
     * Creates the "links" table (single "slug" column, primary key) that the existence-checker tests use.
     */
    protected static function createLinksTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::quoteIdentifier($pdo, 'links')
                . ' (' . self::quoteIdentifier($pdo, 'slug') . ' VARCHAR(191) NOT NULL PRIMARY KEY)',
        );
    }

    /**
     * Inserts one slug into the "links" table.
     */
    protected static function insertLink(PDO $pdo, string $slug): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::quoteIdentifier($pdo, 'links')
                . ' (' . self::quoteIdentifier($pdo, 'slug') . ') VALUES (?)',
        );
        if ($stmt === false || $stmt->execute([$slug]) === false) {
            throw new AssertionFailedError("Failed to insert slug '{$slug}' into the links table.");
        }
    }

    /**
     * Executes a SQL query and returns the PDOStatement, failing the test if the query returns false.
     *
     * Use in place of direct $pdo->query() chains so that callers receive a guaranteed PDOStatement.
     */
    protected static function queryOrFail(PDO $pdo, string $sql): PDOStatement
    {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $error = $pdo->errorInfo();
            throw new AssertionFailedError(
                'PDO::query() failed: ' . ($error[2] ?? 'unknown error'),
            );
        }

        return $stmt;
    }

    /**
     * DataProvider: SQL identifier strings that must be rejected by the identifier validator.
     *
     * Each entry is a single injection-shaped or otherwise invalid string. Tests that
     * validate identifier rejection should use this provider so every failure shape is
     * exercised as a distinct, descriptively-named case.
     *
     * @return array<string, array{string}>
     */
    public static function invalidIdentifiers(): array
    {
        return [
            'hyphenated-identifier' => ['invalid-name'],
            'sql-injection-semicolon' => ['foo; DROP TABLE x'],
            'embedded-double-quote' => ['foo"bar'],
            'embedded-backtick' => ["foo`bar"],
            'empty-string' => [''],
        ];
    }

    /**
     * DataProvider: PDO factory closures with the counter table pre-installed.
     *
     * Same driver coverage as pdoDataset(); each factory installs the counter table before
     * returning the connection.
     *
     * @return array<string, list<Closure(): PDO>>
     */
    public static function installedPdoDataset(): array
    {
        $entries = [];

        foreach (static::pdoDataset() as $label => $factories) {
            $factory = $factories[0];
            $entries[$label] = [
                function () use ($factory): PDO {
                    $pdo = $factory();
                    (new CounterTableInstaller($pdo))->install();
                    return $pdo;
                },
            ];
        }

        return $entries;
    }
}
