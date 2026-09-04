<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration;

use Chrismou\StringMint\Alphabet\Generic;
use Chrismou\StringMint\Counter\CounterTableInstaller;
use Chrismou\StringMint\Counter\InMemoryCounterStore;
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Existence\NeverExistsChecker;
use Chrismou\StringMint\Existence\PdoColumnExistenceChecker;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\Generator\RandomGenerator;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;
use Chrismou\StringMint\Tests\Support\HexAlphabet;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end integration test:
 * - 6-char alphabet: 'abcdef'
 * - LengthPolicy(3, 5): starts at length 3, ceiling at 5
 * - Capacities: length 3 = 6^3 = 216, length 4 = 6^4 = 1296, length 5 = 6^5 = 7776
 * - PermutationGenerator + PdoCounterStore + PdoColumnExistenceChecker
 * - Generates 5,000 strings total with generateWithAutoLengthIncrement()
 */
final class EndToEndTest extends PdoTestCase
{
    #[Test]
    #[DataProvider('pdoDataset')]
    public function testGenerates5000UniqueStringsWithCorrectLengthEscalation(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        (new CounterTableInstaller($pdo))->install();
        self::createLinksTable($pdo);

        $alphabet = new Generic('abcdef');
        $policy = new LengthPolicy(3, 5);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $generator = new PermutationGenerator(
            $policy,
            new PdoCounterStore($pdo, 'links'),
            new FeistelPermutation('e2e-test-secret-k'),
            $alphabet,
            $checker,
        );

        $generated = [];
        $lengthCounts = [3 => 0, 4 => 0, 5 => 0];

        for ($i = 0; $i < 5000; $i++) {
            $slug = $generator->generateWithAutoLengthIncrement();
            self::insertLink($pdo, $slug);
            $len = strlen($slug);
            $lengthCounts[$len] = ($lengthCounts[$len] ?? 0) + 1;
            $generated[] = $slug;
        }

        $this->assertSame(5000, count(array_unique($generated)));
        $this->assertSame(216, $lengthCounts[3]);
        $this->assertSame(1296, $lengthCounts[4]);
        $this->assertSame(5000 - 216 - 1296, $lengthCounts[5]);
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testGenerateFixedLength3ThrowsKeyspaceExhaustedExceptionAfterAll216AreConsumed(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        (new CounterTableInstaller($pdo))->install();
        self::createLinksTable($pdo);

        $alphabet = new Generic('abcdef');
        $policy = new LengthPolicy(3, 5);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $generator = new PermutationGenerator(
            $policy,
            new PdoCounterStore($pdo, 'links'),
            new FeistelPermutation('e2e-test-secret-k'),
            $alphabet,
            $checker,
        );

        for ($i = 0; $i < 216; $i++) {
            $slug = $generator->generateWithAutoLengthIncrement();
            self::insertLink($pdo, $slug);
        }

        $this->expectException(KeyspaceExhaustedException::class);
        $generator->generate();
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testGenerate5StillSucceedsAfterLength3And4AreExhausted(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        (new CounterTableInstaller($pdo))->install();

        $alphabet = new Generic('abcdef');
        $policy = new LengthPolicy(3, 5);
        $seededStore = new InMemoryCounterStore([3 => 216, 4 => 1296], 'links');
        $gen = new PermutationGenerator(
            $policy,
            $seededStore,
            new FeistelPermutation('e2e-test-secret-k'),
            $alphabet,
            new NeverExistsChecker(),
        );

        $result = $gen->generate(5);
        $this->assertSame(5, strlen($result));
        $this->assertSame(5, strspn($result, 'abcdef'));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testRandomGeneratorOnTheSameTableProducesStringsThatDoNotCollideWithExistingRows(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        (new CounterTableInstaller($pdo))->install();
        self::createLinksTable($pdo);

        $alphabet = new Generic('abcdef');
        $policy = new LengthPolicy(3, 5);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $generator = new PermutationGenerator(
            $policy,
            new PdoCounterStore($pdo, 'links'),
            new FeistelPermutation('e2e-test-secret-k'),
            $alphabet,
            $checker,
        );

        $generated = [];
        for ($i = 0; $i < 50; $i++) {
            $slug = $generator->generateWithAutoLengthIncrement();
            self::insertLink($pdo, $slug);
            $generated[] = $slug;
        }

        $randomChecker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $randomGen = new RandomGenerator(
            new LengthPolicy(3, 5),
            $randomChecker,
            $alphabet,
            50,
        );

        $randomSlug = $randomGen->generateWithAutoLengthIncrement();
        $this->assertFalse(in_array($randomSlug, $generated, true));
        $this->assertGreaterThanOrEqual(3, strlen($randomSlug));
    }

    #[Test]
    #[DataProvider('pdoDataset')]
    public function testHexAlphabetProduces256TwoCharStringsThen44ThreeCharStringsAllUnique(Closure $pdoFactory): void
    {
        $pdo = $pdoFactory();
        (new CounterTableInstaller($pdo))->install();
        self::createLinksTable($pdo);

        $alphabet = new HexAlphabet();
        $policy = new LengthPolicy(2, 3);
        $checker = new PdoColumnExistenceChecker($pdo, 'links', 'slug');
        $generator = new PermutationGenerator(
            $policy,
            new PdoCounterStore($pdo, 'links'),
            new FeistelPermutation('hex-e2e-secret-xxx'),
            $alphabet,
            $checker,
        );

        $generated = [];
        for ($i = 0; $i < 300; $i++) {
            $slug = $generator->generateWithAutoLengthIncrement();
            self::insertLink($pdo, $slug);
            $generated[] = $slug;
        }

        $this->assertSame(300, count(array_unique($generated)));
        $twoChar = array_filter($generated, fn ($s) => strlen($s) === 2);
        $threeChar = array_filter($generated, fn ($s) => strlen($s) === 3);
        $this->assertSame(256, count($twoChar));
        $this->assertSame(44, count($threeChar));

        foreach ($generated as $slug) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $slug);
        }
    }
}
