<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Counter\Pdo;

use Chrismou\StringMint\Counter\Pdo\DialectResolver;
use Chrismou\StringMint\Counter\Pdo\MySqlDialect;
use Chrismou\StringMint\Counter\Pdo\PostgreSqlDialect;
use Chrismou\StringMint\Counter\Pdo\SqliteDialect;
use Chrismou\StringMint\Counter\Pdo\SqlServerDialect;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A PDO subclass that overrides getAttribute() to return a scripted driver name.
 *
 * Lets DialectResolver be driven through every branch without needing a live
 * MySQL, PostgreSQL or SQL Server connection in the test suite.
 */
final class FakeDriverPdo extends PDO
{
    private string $fakeDriverName;

    /** Opens a real in-memory SQLite connection and then overrides the driver name. */
    public function __construct(string $fakeDriverName)
    {
        parent::__construct('sqlite::memory:');
        $this->fakeDriverName = $fakeDriverName;
    }

    /** Returns the scripted driver name for ATTR_DRIVER_NAME; delegates to the parent for every other attribute. */
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return $this->fakeDriverName;
        }

        return parent::getAttribute($attribute);
    }
}

final class DialectResolverTest extends TestCase
{
    #[Test]
    public function testResolvesMysqlDriverToMySqlDialect(): void
    {
        $pdo = new FakeDriverPdo('mysql');
        $this->assertInstanceOf(MySqlDialect::class, DialectResolver::resolve($pdo));
    }

    #[Test]
    public function testResolvesPgsqlDriverToPostgreSqlDialect(): void
    {
        $pdo = new FakeDriverPdo('pgsql');
        $this->assertInstanceOf(PostgreSqlDialect::class, DialectResolver::resolve($pdo));
    }

    #[Test]
    public function testResolvesSqlsrvDriverToSqlServerDialect(): void
    {
        $pdo = new FakeDriverPdo('sqlsrv');
        $this->assertInstanceOf(SqlServerDialect::class, DialectResolver::resolve($pdo));
    }

    #[Test]
    public function testResolvesDbLibDriverToSqlServerDialect(): void
    {
        $pdo = new FakeDriverPdo('dblib');
        $this->assertInstanceOf(SqlServerDialect::class, DialectResolver::resolve($pdo));
    }

    #[Test]
    public function testResolvesSqliteDriverToSqliteDialect(): void
    {
        $pdo = new FakeDriverPdo('sqlite');
        $this->assertInstanceOf(SqliteDialect::class, DialectResolver::resolve($pdo));
    }

    #[Test]
    public function testFallsBackToSqliteDialectForUnknownDrivers(): void
    {
        foreach (['oci', 'odbc', 'firebird', 'nonsense'] as $unknownDriver) {
            $pdo = new FakeDriverPdo($unknownDriver);
            $this->assertInstanceOf(
                SqliteDialect::class,
                DialectResolver::resolve($pdo),
                "Expected SqliteDialect fallback for driver '{$unknownDriver}'",
            );
        }
    }
}
