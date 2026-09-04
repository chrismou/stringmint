<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration\Existence;

use Chrismou\StringMint\Exception\ExistenceCheckException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Existence\PdoColumnExistenceChecker;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use Closure;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PdoColumnExistenceCheckerTest extends PdoTestCase
{
    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsFalseWhenTheValueIsNotInTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        self::createLinksTable($pdo);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertFalse($checker->exists('abc123'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsTrueWhenTheValueIsInTheTable(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        self::createLinksTable($pdo);
        self::insertLink($pdo, 'abc123');
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertTrue($checker->exists('abc123'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testReturnsFalseForADifferentValue(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        self::createLinksTable($pdo);
        self::insertLink($pdo, 'abc123');
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $this->assertFalse($checker->exists('xyz789'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testMissingTableThrowsExistenceCheckExceptionInsteadOfReportingNotFound(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory(); // No links table.
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');

        $caught = null;
        try {
            $checker->exists('abc123');
        } catch (ExistenceCheckException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertInstanceOf(PDOException::class, $caught->getPrevious());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testMissingTableThrowsExistenceCheckExceptionUnderErrorModeSilent(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory(); // No links table.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');

        $this->expectException(ExistenceCheckException::class);
        $checker->exists('abc123');
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testWorksWhenThePdoIsInErrorModeSilent(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        self::createLinksTable($pdo);
        self::insertLink($pdo, 'abc123');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');

        $this->assertTrue($checker->exists('abc123'));
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
