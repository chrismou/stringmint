<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Counter\Pdo;

use Chrismou\StringMint\Counter\Pdo\MySqlDialect;
use Chrismou\StringMint\Counter\Pdo\PdoDialectInterface;
use Chrismou\StringMint\Counter\Pdo\PostgreSqlDialect;
use Chrismou\StringMint\Counter\Pdo\SqliteDialect;
use Chrismou\StringMint\Counter\Pdo\SqlServerDialect;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DialectSqlTest extends TestCase
{
    // --- MySqlDialect ---

    #[Test]
    public function testMysqlQuoteIdentifierWrapsASimpleNameInBackticks(): void
    {
        $dialect = new MySqlDialect();
        $this->assertSame('`table_name`', $dialect->quoteIdentifier('table_name'));
    }

    #[Test]
    public function testMysqlQuoteIdentifierWrapsASchemaDotTablePairInBackticks(): void
    {
        $dialect = new MySqlDialect();
        $this->assertSame('`myschema`.`table_name`', $dialect->quoteIdentifier('myschema.table_name'));
    }

    #[Test]
    public function testMysqlCreateTableSqlContainsIfNotExistsAndBacktickQuotedIdentifiers(): void
    {
        $dialect = new MySqlDialect();
        $sql = $dialect->createTableSql('`t`');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `t`', $sql);
        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringContainsString('`string_length`', $sql);
        $this->assertStringContainsString('`counter_value`', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`name`, `string_length`)', $sql);
        $this->assertStringContainsString('VARCHAR(191)', $sql);
        $this->assertStringContainsString('BIGINT', $sql);
    }

    #[Test]
    public function testMysqlDropTableSqlContainsDropTableIfExists(): void
    {
        $dialect = new MySqlDialect();
        $this->assertSame('DROP TABLE IF EXISTS `t`', $dialect->dropTableSql('`t`'));
    }

    #[Test]
    public function testMysqlIncrementAndFetchThrowsPdoExceptionOnADatabaseError(): void
    {
        // LAST_INSERT_ID() is not a function in SQLite; the statement execution fails.
        $dialect = new MySqlDialect();
        $pdo = new PDO('sqlite::memory:');
        $this->expectException(PDOException::class);
        $dialect->incrementAndFetch($pdo, '`t`', 'default', 4);
    }

    // --- PostgreSqlDialect ---

    #[Test]
    public function testPostgresQuoteIdentifierWrapsASimpleNameInDoubleQuotes(): void
    {
        $dialect = new PostgreSqlDialect();
        $this->assertSame('"table_name"', $dialect->quoteIdentifier('table_name'));
    }

    #[Test]
    public function testPostgresQuoteIdentifierWrapsASchemaDotTablePairInDoubleQuotes(): void
    {
        $dialect = new PostgreSqlDialect();
        $this->assertSame('"myschema"."table_name"', $dialect->quoteIdentifier('myschema.table_name'));
    }

    #[Test]
    public function testPostgresCreateTableSqlContainsIfNotExistsAndDoubleQuotedIdentifiers(): void
    {
        $dialect = new PostgreSqlDialect();
        $sql = $dialect->createTableSql('"t"');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS "t"', $sql);
        $this->assertStringContainsString('"name"', $sql);
        $this->assertStringContainsString('"string_length"', $sql);
        $this->assertStringContainsString('"counter_value"', $sql);
        $this->assertStringContainsString('PRIMARY KEY ("name", "string_length")', $sql);
    }

    #[Test]
    public function testPostgresDropTableSqlContainsDropTableIfExists(): void
    {
        $dialect = new PostgreSqlDialect();
        $this->assertSame('DROP TABLE IF EXISTS "t"', $dialect->dropTableSql('"t"'));
    }

    #[Test]
    public function testPostgresIncrementAndFetchThrowsPdoExceptionWhenTableDoesNotExist(): void
    {
        // Table "t" is not created; the UPDATE ... RETURNING fails with "no such table".
        $dialect = new PostgreSqlDialect();
        $pdo = new PDO('sqlite::memory:');
        $this->expectException(PDOException::class);
        $dialect->incrementAndFetch($pdo, '"t"', 'default', 4);
    }

    // --- SqliteDialect ---

    #[Test]
    public function testSqliteQuoteIdentifierWrapsASimpleNameInDoubleQuotes(): void
    {
        $dialect = new SqliteDialect();
        $this->assertSame('"table_name"', $dialect->quoteIdentifier('table_name'));
    }

    #[Test]
    public function testSqliteQuoteIdentifierWrapsASchemaDotTablePairInDoubleQuotes(): void
    {
        $dialect = new SqliteDialect();
        $this->assertSame('"myschema"."table_name"', $dialect->quoteIdentifier('myschema.table_name'));
    }

    #[Test]
    public function testSqliteCreateTableSqlUsesCreateTableIfNotExists(): void
    {
        $dialect = new SqliteDialect();
        $sql = $dialect->createTableSql('"t"');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS "t"', $sql);
        $this->assertStringContainsString('"name" TEXT', $sql);
        $this->assertStringContainsString('"string_length" INTEGER', $sql);
        $this->assertStringContainsString('"counter_value" INTEGER', $sql);
        $this->assertStringContainsString('PRIMARY KEY ("name", "string_length")', $sql);
    }

    #[Test]
    public function testSqliteDropTableSqlUsesDropTableIfExists(): void
    {
        $dialect = new SqliteDialect();
        $this->assertSame('DROP TABLE IF EXISTS "t"', $dialect->dropTableSql('"t"'));
    }

    #[Test]
    public function testSqliteIncrementAndFetchAlwaysReturnsUseCompareAndSwap(): void
    {
        $dialect = new SqliteDialect();
        $pdo = new PDO('sqlite::memory:');
        $result = $dialect->incrementAndFetch($pdo, '"t"', 'default', 4);
        $this->assertSame(PdoDialectInterface::USE_COMPARE_AND_SWAP, $result);
    }

    // --- SqlServerDialect ---

    #[Test]
    public function testSqlServerQuoteIdentifierWrapsASimpleNameInSquareBrackets(): void
    {
        $dialect = new SqlServerDialect();
        $this->assertSame('[table_name]', $dialect->quoteIdentifier('table_name'));
    }

    #[Test]
    public function testSqlServerQuoteIdentifierWrapsASchemaDotTablePairInSquareBrackets(): void
    {
        $dialect = new SqlServerDialect();
        $this->assertSame('[myschema].[table_name]', $dialect->quoteIdentifier('myschema.table_name'));
    }

    #[Test]
    public function testSqlServerCreateTableSqlUsesIfObjectIdGuardAndSquareBracketIdentifiers(): void
    {
        $dialect = new SqlServerDialect();
        $sql = $dialect->createTableSql('[t]');
        $this->assertStringContainsString('IF OBJECT_ID', $sql);
        $this->assertStringContainsString('CREATE TABLE [t]', $sql);
        $this->assertStringContainsString('[name]', $sql);
        $this->assertStringContainsString('[string_length]', $sql);
        $this->assertStringContainsString('[counter_value]', $sql);
        $this->assertStringContainsString('PRIMARY KEY ([name], [string_length])', $sql);
        $this->assertStringContainsString('NVARCHAR(191)', $sql);
    }

    #[Test]
    public function testSqlServerDropTableSqlUsesIfObjectIdGuard(): void
    {
        $dialect = new SqlServerDialect();
        $sql = $dialect->dropTableSql('[t]');
        $this->assertStringContainsString('IF OBJECT_ID', $sql);
        $this->assertStringContainsString('DROP TABLE [t]', $sql);
    }

    #[Test]
    public function testSqlServerIncrementAndFetchThrowsPdoExceptionOnASqlSyntaxError(): void
    {
        // OUTPUT INSERTED is not supported in SQLite; the statement fails.
        $dialect = new SqlServerDialect();
        $pdo = new PDO('sqlite::memory:');
        $this->expectException(PDOException::class);
        $dialect->incrementAndFetch($pdo, '[t]', 'default', 4);
    }
}
