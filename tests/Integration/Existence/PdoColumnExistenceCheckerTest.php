<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration\Existence;

use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Existence\PdoColumnExistenceChecker;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PdoColumnExistenceCheckerTest extends PdoTestCase
{
    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsFalseWhenTheValueIsNotInTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $pdo->exec('CREATE TABLE IF NOT EXISTS "links" ("slug" TEXT NOT NULL PRIMARY KEY)');
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertFalse($checker->exists('abc123'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsTrueWhenTheValueIsInTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $pdo->exec('CREATE TABLE IF NOT EXISTS "links" ("slug" TEXT NOT NULL PRIMARY KEY)');
        $pdo->exec("INSERT INTO \"links\" (\"slug\") VALUES ('abc123')");
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertTrue($checker->exists('abc123'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsFalseForADifferentValue(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $pdo->exec('CREATE TABLE IF NOT EXISTS "links" ("slug" TEXT NOT NULL PRIMARY KEY)');
        $pdo->exec("INSERT INTO \"links\" (\"slug\") VALUES ('abc123')");
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertFalse($checker->exists('xyz789'));
    }

    #[Test]
    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidTableNamesIncludingInjectionShapedStrings(string $badName): void
    {
        $this->expectException(InvalidTableNameException::class);
        new PdoColumnExistenceChecker(self::sqlitePdo(), $badName, 'slug');
    }

    #[Test]
    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidColumnNamesIncludingInjectionShapedStrings(string $badColumn): void
    {
        $this->expectException(InvalidTableNameException::class);
        new PdoColumnExistenceChecker(self::sqlitePdo(), 'links', $badColumn);
    }
}
