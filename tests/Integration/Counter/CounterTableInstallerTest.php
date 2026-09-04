<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration\Counter;

use Chrismou\StringMint\Counter\CounterTableInstaller;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class CounterTableInstallerTest extends PdoTestCase
{
    #[Test]
    #[DataProvider('pdoDataset')]
    public function testInstallCreatesTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $this->assertFalse($installer->isInstalled());
        $installer->install();
        $this->assertTrue($installer->isInstalled());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testInstallIsIdempotentCallingTwiceDoesNotThrow(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $installer->install();
        $installer->install();
        $this->assertTrue($installer->isInstalled());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testIsInstalledReturnsFalseBeforeInstallAndTrueAfter(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $this->assertFalse($installer->isInstalled());
        $installer->install();
        $this->assertTrue($installer->isInstalled());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testUninstallDropsTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $installer->install();
        $installer->uninstall();
        $this->assertFalse($installer->isInstalled());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testUninstallIsIdempotent(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $installer->install();
        $installer->uninstall();
        $installer->uninstall();
        $this->assertFalse($installer->isInstalled());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testInstallSqlReturnsTheDialectSqlString(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $sql = $installer->installSql();
        $this->assertStringContainsString('stringmint_counters', $sql);
        $this->assertStringContainsString('string_length', $sql);
        $this->assertStringContainsString('counter_value', $sql);
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testInstalledTableAcceptsTwoRowsWithTheSameNameButDifferentLengths(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $installer = new CounterTableInstaller($pdo);
        $installer->install();
        $table = self::quoteIdentifier($pdo, 'stringmint_counters');
        $columns = implode(', ', [
            self::quoteIdentifier($pdo, 'name'),
            self::quoteIdentifier($pdo, 'string_length'),
            self::quoteIdentifier($pdo, 'counter_value'),
        ]);
        $pdo->exec("INSERT INTO {$table} ({$columns}) VALUES ('default', 3, 0)");
        $pdo->exec("INSERT INTO {$table} ({$columns}) VALUES ('default', 4, 0)");
        $count = self::queryOrFail($pdo, "SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $this->assertSame(2, (int) $count);
    }

    #[Test]
    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidTableNamesIncludingInjectionShapedStrings(string $badName): void
    {
        $this->expectException(InvalidTableNameException::class);
        new CounterTableInstaller(self::sqlitePdo(), $badName);
    }
}
