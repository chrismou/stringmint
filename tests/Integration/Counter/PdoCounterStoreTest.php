<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration\Counter;

use Chrismou\StringMint\Counter\CounterTableInstaller;
use Chrismou\StringMint\Counter\Pdo\PdoDialectInterface;
use Chrismou\StringMint\Counter\Pdo\SqliteDialect;
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Exception\CounterStoreException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use Closure;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A mutable holder for the CasFailOnceStatement fail-next flag.
 *
 * Passed as a constructor argument so the statement class carries no static state.
 */
final class CasFailNextHolder
{
    public function __construct(public bool $failNext = false)
    {
    }
}

/**
 * A PDOStatement subclass that lies about rowCount() once for a CAS UPDATE, then behaves normally.
 *
 * Used with PDO::ATTR_STATEMENT_CLASS to simulate a concurrent writer beating us to the UPDATE.
 * Consult and flip the injected CasFailNextHolder before each test that needs one simulated contention.
 *
 */
final class CasFailOnceStatement extends PDOStatement
{
    protected function __construct(private readonly CasFailNextHolder $holder)
    {
    }

    public function rowCount(): int
    {
        $isCasUpdate = str_contains($this->queryString, 'UPDATE')
            && str_contains($this->queryString, '"counter_value" = ?');

        if ($this->holder->failNext && $isCasUpdate) {
            $this->holder->failNext = false;

            return 0; // Pretend zero rows were updated to force one CAS retry.
        }

        return parent::rowCount();
    }
}

/**
 * A PDOStatement subclass that always returns rowCount() = 0 for CAS UPDATEs.
 *
 * Used to exhaust maximumCompareAndSwapAttempts and force CounterStoreException.
 *
 */
final class CasAlwaysFailStatement extends PDOStatement
{
    public function rowCount(): int
    {
        if (str_contains($this->queryString, 'UPDATE') && str_contains($this->queryString, '"counter_value" = ?')) {
            return 0;
        }

        return parent::rowCount();
    }
}

final class PdoCounterStoreTest extends PdoTestCase
{
    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testFirstNext4Returns0(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $store = new PdoCounterStore($pdo);
        $this->assertSame(0, $store->next(4));
    }

    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testSequentialCallsForTheSameLengthReturnIncreasingValues(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $store = new PdoCounterStore($pdo);
        $this->assertSame(0, $store->next(4));
        $this->assertSame(1, $store->next(4));
        $this->assertSame(2, $store->next(4));
    }

    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testNext4AndNext5AreIndependentSequences(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $store = new PdoCounterStore($pdo);
        $this->assertSame(0, $store->next(4));
        $this->assertSame(0, $store->next(5));
        $this->assertSame(1, $store->next(4));
        $this->assertSame(1, $store->next(5));
    }

    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testTwoStoreInstancesSharingATableAndNameInterleaveCorrectly(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $store1 = new PdoCounterStore($pdo);
        $store2 = new PdoCounterStore($pdo);
        $values = [];
        $values[] = $store1->next(4);
        $values[] = $store2->next(4);
        $values[] = $store1->next(4);
        $values[] = $store2->next(4);
        sort($values);
        $this->assertSame([0, 1, 2, 3], $values);
    }

    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testDifferentCounterNamesAreIndependent(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $links = new PdoCounterStore($pdo, 'links');
        $invites = new PdoCounterStore($pdo, 'invites');
        $this->assertSame(0, $links->next(4));
        $this->assertSame(0, $invites->next(4));
        $this->assertSame(1, $links->next(4));
        $this->assertSame(1, $invites->next(4));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testNameReturnsTheInjectedCounterName(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $store = new PdoCounterStore($pdo, 'links');
        $this->assertSame('links', $store->name());
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testMissingTableThrowsCounterStoreExceptionWithAPdoExceptionPrevious(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory(); // No table installed.
        $store = new PdoCounterStore($pdo);
        $caught = null;
        try {
            $store->next(4);
        } catch (CounterStoreException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught);
        $this->assertInstanceOf(PDOException::class, $caught->getPrevious());
    }

    #[Test]
    #[DataProvider('installedPdoDataset')]
    public function testWorksWhenThePdoIsInErrorModeSilent(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $store = new PdoCounterStore($pdo);
        $this->assertSame(0, $store->next(4));
        $this->assertSame(1, $store->next(4));
    }

    #[Test]
    #[DataProvider('invalidIdentifiers')]
    public function testRejectsAnInvalidTableName(string $badName): void
    {
        $this->expectException(InvalidTableNameException::class);
        new PdoCounterStore(self::sqlitePdo(), 'default', $badName);
    }

    #[Test]
    public function testRejectsACounterNameThatIsTooLong(): void
    {
        $longName = str_repeat('a', 192);
        $this->expectException(InvalidArgumentException::class);
        new PdoCounterStore(self::sqlitePdo(), $longName);
    }

    // --- CAS retry path ---

    #[Test]
    public function testCasRetryPathRowCount0OnceBeforeSucceedingStillYieldsAValidIndex(): void
    {
        $pdo = self::sqlitePdo();
        (new CounterTableInstaller($pdo))->install();

        // Override the statement class so the first CAS UPDATE pretends to affect 0 rows.
        $holder = new CasFailNextHolder(true);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CasFailOnceStatement::class, [$holder]]);

        $store = new PdoCounterStore($pdo, 'default', PdoCounterStore::DEFAULT_TABLE, new SqliteDialect(), 5);

        $result = $store->next(4);
        $this->assertGreaterThanOrEqual(0, $result);

        // Second call (no more lies) advances the counter normally.
        $this->assertGreaterThan($result, $store->next(4));
    }

    #[Test]
    public function testCasExhaustionThrowsCounterStoreExceptionWhenMaximumCompareAndSwapAttemptsIsExceeded(): void
    {
        $pdo = self::sqlitePdo();
        (new CounterTableInstaller($pdo))->install();

        // Override the statement class so CAS UPDATEs always return 0 rows.
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CasAlwaysFailStatement::class]);

        // Small attempt budget so the test is fast.
        $store = new PdoCounterStore($pdo, 'default', PdoCounterStore::DEFAULT_TABLE, new SqliteDialect(), 3);

        $this->expectException(CounterStoreException::class);
        $store->next(4);
    }

    // --- SQLSTATE 23000 on the lazy seed INSERT ---

    #[Test]
    public function testSqlState23000OnSeedInsertIsSwallowedAndNextReturnsTheCorrectIndex(): void
    {
        $pdo = self::sqlitePdo();
        (new CounterTableInstaller($pdo))->install();

        $inner = new SqliteDialect();
        $hasRaced = false;

        $dialect = new class ($inner, $hasRaced) implements PdoDialectInterface {
            private bool $hasRaced;

            public function __construct(
                private readonly SqliteDialect $inner,
                bool $hasRaced,
            ) {
                $this->hasRaced = $hasRaced;
            }

            /** @return null|PdoDialectInterface::USE_COMPARE_AND_SWAP */
            public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): null|string
            {
                if (!$this->hasRaced) {
                    $this->hasRaced = true;
                    // Pre-insert so the subsequent seed INSERT fails with SQLSTATE 23000.
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . $quotedTable . ' ("name", "string_length", "counter_value") VALUES (?, ?, 0)',
                    );
                    $stmt->execute([$counterName, $length]);

                    return null; // Signal "no row" to trigger the seed path.
                }

                return $this->inner->incrementAndFetch($pdo, $quotedTable, $counterName, $length);
            }

            public function quoteIdentifier(string $identifier): string
            {
                return $this->inner->quoteIdentifier($identifier);
            }

            public function createTableSql(string $quotedTable): string
            {
                return $this->inner->createTableSql($quotedTable);
            }

            public function dropTableSql(string $quotedTable): string
            {
                return $this->inner->dropTableSql($quotedTable);
            }
        };

        $store = new PdoCounterStore($pdo, 'default', PdoCounterStore::DEFAULT_TABLE, $dialect);

        // The race: dialect pre-inserts (counter=0), returns null; seed INSERT fails 23000 (swallowed);
        // retry CAS: SELECT->0, UPDATE->1; next() returns 1-1=0.
        $this->assertSame(0, $store->next(4));
    }

    #[Test]
    public function testSqlState23000OnSeedInsertIsSwallowedUnderErrorModeSilentAndNextReturns0(): void
    {
        $pdo = self::sqlitePdo();
        (new CounterTableInstaller($pdo))->install();

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $inner = new SqliteDialect();
        $hasRaced = false;

        $dialect = new class ($inner, $hasRaced) implements PdoDialectInterface {
            private bool $hasRaced;

            public function __construct(
                private readonly SqliteDialect $inner,
                bool $hasRaced,
            ) {
                $this->hasRaced = $hasRaced;
            }

            /** @return null|PdoDialectInterface::USE_COMPARE_AND_SWAP */
            public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): null|string
            {
                if (!$this->hasRaced) {
                    $this->hasRaced = true;
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . $quotedTable . ' ("name", "string_length", "counter_value") VALUES (?, ?, 0)',
                    );
                    $stmt->execute([$counterName, $length]);

                    return null;
                }

                return $this->inner->incrementAndFetch($pdo, $quotedTable, $counterName, $length);
            }

            public function quoteIdentifier(string $identifier): string
            {
                return $this->inner->quoteIdentifier($identifier);
            }

            public function createTableSql(string $quotedTable): string
            {
                return $this->inner->createTableSql($quotedTable);
            }

            public function dropTableSql(string $quotedTable): string
            {
                return $this->inner->dropTableSql($quotedTable);
            }
        };

        $store = new PdoCounterStore($pdo, 'default', PdoCounterStore::DEFAULT_TABLE, $dialect);

        $this->assertSame(0, $store->next(4));
    }
}
