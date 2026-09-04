<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Generator;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\AlphabetInterface;
use Chrismou\StringMint\Alphabet\Generic;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Counter\CounterStoreInterface;
use Chrismou\StringMint\Counter\InMemoryCounterStore;
use Chrismou\StringMint\Exception\CounterStoreException;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Exception\UnableToGenerateUniqueStringException;
use Chrismou\StringMint\Existence\InMemoryExistenceChecker;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\Keyspace;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;
use Chrismou\StringMint\Permutation\KeyspacePermutationInterface;
use Chrismou\StringMint\Tests\Support\ConfigurableAlphabet;
use Chrismou\StringMint\Tests\Support\HexAlphabet;
use Chrismou\StringMint\Tests\Support\IdentityPermutation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermutationGeneratorTest extends TestCase
{
    private const TEST_ALPHABET = 'abcd';

    /**
     * Builds a PermutationGenerator with a 4-char alphabet and IdentityPermutation by default.
     */
    private static function makeGenerator(
        int $defaultLength = 2,
        ?int $maxLength = null,
        ?InMemoryCounterStore $store = null,
        ?AlphabetInterface $alphabet = null,
        ?InMemoryExistenceChecker $checker = null,
        int $maximumAttempts = 100,
        string $counterName = 'default',
    ): PermutationGenerator {
        $store ??= new InMemoryCounterStore(name: $counterName);
        $alphabet ??= new Generic(self::TEST_ALPHABET);

        return new PermutationGenerator(
            new LengthPolicy($defaultLength, $maxLength),
            $store,
            new IdentityPermutation(),
            $alphabet,
            $checker ?? new InMemoryExistenceChecker(),
            $maximumAttempts,
        );
    }

    // --- basic generation ---

    #[Test]
    public function testGenerateUsesTheDefaultLength(): void
    {
        $gen = self::makeGenerator(defaultLength: 3);
        $this->assertSame(3, strlen($gen->generate()));
    }

    #[Test]
    public function testGenerateWithOverrideUsesTheSpecifiedLength(): void
    {
        $gen = self::makeGenerator(defaultLength: 2);
        $this->assertSame(3, strlen($gen->generate(3)));
    }

    #[Test]
    public function testOutputContainsOnlyAlphabetCharacters(): void
    {
        $gen = self::makeGenerator(defaultLength: 2);
        $result = $gen->generate();
        for ($i = 0; $i < strlen($result); $i++) {
            $this->assertNotFalse(strpos(self::TEST_ALPHABET, $result[$i]));
        }
    }

    // --- exhaustion ---

    #[Test]
    public function testGenerate2Returns16DistinctStringsBeforeExhaustion(): void
    {
        // 4-char alphabet, length 2: 4^2 = 16 possible strings.
        $gen = self::makeGenerator(defaultLength: 2);
        $strings = [];
        for ($i = 0; $i < 16; $i++) {
            $strings[] = $gen->generate();
        }
        $this->assertSame(16, count(array_unique($strings)));
    }

    #[Test]
    public function testThe17thGenerateThrowsKeyspaceExhaustedExceptionWithLengthEquals2(): void
    {
        $store = new InMemoryCounterStore([2 => 16]); // Seeded at capacity
        $gen = self::makeGenerator(defaultLength: 2, store: $store);
        $e = null;
        try {
            $gen->generate();
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertSame(2, $e->length());
    }

    #[Test]
    public function testThe18thGenerateAlsoThrowsBecauseCounterKeepsBurning(): void
    {
        $store = new InMemoryCounterStore([2 => 16]);
        $gen = self::makeGenerator(defaultLength: 2, store: $store);
        for ($i = 0; $i < 2; $i++) {
            try {
                $gen->generate();
            } catch (KeyspaceExhaustedException) {
            }
        }
        // Counter should now be at 18; still exhausted.
        $this->expectException(KeyspaceExhaustedException::class);
        $gen->generate();
    }

    // --- auto-length increment ---

    #[Test]
    public function testGenerateWithAutoLengthIncrementReturns16TwoCharStringsThenEscalatesToThree(): void
    {
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3);
        $strings = [];
        for ($i = 0; $i < 17; $i++) {
            $strings[] = $gen->generateWithAutoLengthIncrement(2);
        }
        $twoChar = array_filter($strings, fn ($s) => strlen($s) === 2);
        $threeChar = array_filter($strings, fn ($s) => strlen($s) === 3);
        $this->assertSame(16, count($twoChar));
        $this->assertSame(1, count($threeChar));
    }

    #[Test]
    public function testGenerateWithAutoLengthIncrementThrowsForRangeWhenMaximumLengthIsExhausted(): void
    {
        // 4-char alphabet, length 2 = 16 strings; with max 2 there is nowhere to escalate.
        $store = new InMemoryCounterStore([2 => 16]);
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 2, store: $store);
        $e = null;
        try {
            $gen->generateWithAutoLengthIncrement(2);
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertNull($e->length()); // forRange sets length to null
    }

    // --- no exhaustion memo: every call at an exhausted length burns one counter index ---

    #[Test]
    public function testASecondGenerateWithAutoLengthIncrementAfterExhaustionStillReturnsAThreeCharString(): void
    {
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3);
        // Exhaust length 2.
        for ($i = 0; $i < 16; $i++) {
            $gen->generateWithAutoLengthIncrement(2);
        }
        // 17th and 18th calls both burn a length-2 increment then escalate to 3.
        $s1 = $gen->generateWithAutoLengthIncrement(2);
        $s2 = $gen->generateWithAutoLengthIncrement(2);
        $this->assertSame(3, strlen($s1));
        $this->assertSame(3, strlen($s2));
    }

    #[Test]
    public function testTheBurntLength2IncrementIsTrackedAndCounterAdvancesByOnePerExhaustedCall(): void
    {
        $store = new InMemoryCounterStore([2 => 16]);
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3, store: $store);
        // Length-2 counter starts at 16 (exhausted). Each call burns one more.
        $gen->generateWithAutoLengthIncrement(2); // burns index 16 on length 2
        $gen->generateWithAutoLengthIncrement(2); // burns index 17 on length 2
        // The length-2 counter should now be at 18.
        $this->assertSame(18, $store->next(2));
    }

    // --- per-length counters are independent ---

    #[Test]
    public function testAfterExhaustingLength2GenerateAtLength3StartsAtIndex0(): void
    {
        // IdentityPermutation + 4-char alphabet: index 0 at length 3 = "aaa".
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3);
        for ($i = 0; $i < 16; $i++) {
            $gen->generate(2);
        }
        $this->assertSame('aaa', $gen->generate(3));
    }

    // --- seeded exhaustion ---

    #[Test]
    public function testInMemoryCounterStoreSeededAtCapacityMakesGenerateWithAutoLengthIncrementReturn4CharString(): void
    {
        // 4-char alphabet, length 3 capacity = 64; seed at 64.
        $store = new InMemoryCounterStore([3 => 64]);
        $gen = self::makeGenerator(defaultLength: 3, maxLength: 4, store: $store);
        $result = $gen->generateWithAutoLengthIncrement(3);
        $this->assertSame(4, strlen($result));
    }

    // --- null ceiling stops at largest supported length ---

    #[Test]
    public function testGenerateWithAutoLengthIncrementThrowsForRangeWithCeiling62OnA2CharAlphabet(): void
    {
        $alphabet = new Generic('ab');
        $keyspace = new Keyspace($alphabet);
        // Seed lengths 61 and 62 (the largest supported on a 2-char alphabet) at their capacities.
        $cap61 = $keyspace->capacityForLength(61);
        $cap62 = $keyspace->capacityForLength(62);
        $store = new InMemoryCounterStore([61 => $cap61, 62 => $cap62]);

        $gen = new PermutationGenerator(
            new LengthPolicy(61), // no maximum - ceiling is largestSupportedLength
            $store,
            new IdentityPermutation(),
            $alphabet,
        );

        $e = null;
        try {
            $gen->generateWithAutoLengthIncrement(61);
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertNull($e->length()); // forRange
    }

    // --- length validation ---

    #[Test]
    public function testOverrideAboveMaximumLengthThrowsInvalidLengthExceptionFromGenerate(): void
    {
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3);
        $this->expectException(InvalidLengthException::class);
        $gen->generate(4);
    }

    #[Test]
    public function testOverrideAboveMaximumLengthThrowsInvalidLengthExceptionFromGenerateWithAutoLengthIncrement(): void
    {
        $gen = self::makeGenerator(defaultLength: 2, maxLength: 3);
        $this->expectException(InvalidLengthException::class);
        $gen->generateWithAutoLengthIncrement(4);
    }

    #[Test]
    public function testGenerate11With66CharAlphabetThrowsInvalidLengthExceptionBecauseItExceeds2To62(): void
    {
        $gen = new PermutationGenerator(
            new LengthPolicy(4),
            new InMemoryCounterStore(),
            new IdentityPermutation(),
            new UrlSafe(),
        );
        $this->expectException(InvalidLengthException::class);
        $gen->generate(11);
    }

    #[Test]
    public function testDefaultLengthBeyondKeyspaceCeilingThrowsInvalidLengthExceptionInConstructor(): void
    {
        $this->expectException(InvalidLengthException::class);
        new PermutationGenerator(
            new LengthPolicy(11),
            new InMemoryCounterStore(),
            new IdentityPermutation(),
            new UrlSafe(),
        );
    }

    #[Test]
    public function testMaximumLengthBeyondKeyspaceCeilingThrowsInvalidLengthExceptionInConstructor(): void
    {
        $this->expectException(InvalidLengthException::class);
        new PermutationGenerator(
            new LengthPolicy(4, 11),
            new InMemoryCounterStore(),
            new IdentityPermutation(),
            new UrlSafe(),
        );
    }

    // --- existence collision and path hazards ---

    #[Test]
    public function testExistenceCollisionSkipsToTheNextCounterValueBurningTheIndex(): void
    {
        // With IdentityPermutation and 'abcd' alphabet:
        //   index 0 at length 2 = "aa"; if "aa" is in the checker, we should get "ab".
        $checker = new InMemoryExistenceChecker(['aa']);
        $gen = self::makeGenerator(defaultLength: 2, checker: $checker);
        $this->assertSame('ab', $gen->generate());
    }

    #[Test]
    public function testPathSegmentHazardDotIsSkippedAtLength1WithDefaultAlphabet(): void
    {
        $alphabet = new UrlSafe();
        $dotIndex = strpos(AbstractUrlSafeAlphabet::RFC3986_UNRESERVED, '.');
        $this->assertIsInt($dotIndex, '"." must be present in the urlSafe alphabet');
        $store = new InMemoryCounterStore([1 => $dotIndex]);
        $gen = new PermutationGenerator(
            new LengthPolicy(1),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );
        $result = $gen->generate();
        $this->assertNotSame('.', $result);
        $this->assertSame(1, strlen($result));
    }

    #[Test]
    public function testPathSegmentHazardDoubleDotIsSkippedAtLength2WithDefaultAlphabet(): void
    {
        $alphabet = new UrlSafe();
        // ".." encodes as: dotIndex * 66 + dotIndex
        $dotIndex = strpos(AbstractUrlSafeAlphabet::RFC3986_UNRESERVED, '.');
        $this->assertIsInt($dotIndex);
        $doubleDotIndex = $dotIndex * $alphabet->size() + $dotIndex;
        $store = new InMemoryCounterStore([2 => $doubleDotIndex]);
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );
        $result = $gen->generate();
        $this->assertNotSame('..', $result);
        $this->assertSame(2, strlen($result));
    }

    #[Test]
    public function testExceedingMaximumAttemptsThrowsUnableToGenerateUniqueStringExceptionAndDoesNotEscalate(): void
    {
        // Block all possible 2-char strings (16 total) so every candidate collides.
        $chars = str_split(self::TEST_ALPHABET);
        $allStrings = [];
        foreach ($chars as $c1) {
            foreach ($chars as $c2) {
                $allStrings[] = $c1 . $c2;
            }
        }
        $checker = new InMemoryExistenceChecker($allStrings);
        $gen = self::makeGenerator(defaultLength: 2, checker: $checker, maximumAttempts: 5);
        $this->expectException(UnableToGenerateUniqueStringException::class);
        $gen->generate();
    }

    // --- counter name namespaces the permutation ---

    #[Test]
    public function testTwoGeneratorsWithDifferentCounterNamesProduceDifferentSequences(): void
    {
        $secret = 'namespacing-test-s';
        $alphabet = new UrlSafe();
        $policy = new LengthPolicy(3);
        $permutation = new FeistelPermutation($secret);

        $genLinks = new PermutationGenerator(
            $policy,
            new InMemoryCounterStore(name: 'links'),
            $permutation,
            $alphabet,
        );
        $genInvites = new PermutationGenerator(
            $policy,
            new InMemoryCounterStore(name: 'invites'),
            $permutation,
            $alphabet,
        );

        $linksStrings = [];
        $invitesStrings = [];
        for ($i = 0; $i < 50; $i++) {
            $linksStrings[] = $genLinks->generate();
            $invitesStrings[] = $genInvites->generate();
        }

        $positionalMatches = count(array_filter(
            array_map(null, $linksStrings, $invitesStrings),
            fn ($pair) => $pair[0] === $pair[1],
        ));
        // Fewer than 5 positional matches out of 50.
        $this->assertLessThan(5, $positionalMatches);
    }

    #[Test]
    public function testTwoGeneratorsWithTheSameCounterNameAndSecretProduceIdenticalSequences(): void
    {
        $secret = 'same-name-same-seq';
        $alphabet = new UrlSafe();
        $policy = new LengthPolicy(3);

        $gen1 = new PermutationGenerator(
            $policy,
            new InMemoryCounterStore(name: 'links'),
            new FeistelPermutation($secret),
            $alphabet,
        );
        $gen2 = new PermutationGenerator(
            $policy,
            new InMemoryCounterStore(name: 'links'),
            new FeistelPermutation($secret),
            $alphabet,
        );

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($gen1->generate(), $gen2->generate());
        }
    }

    // --- CounterStoreException propagates unchanged ---

    #[Test]
    public function testDoesNotCatchCounterStoreExceptionFromTheStore(): void
    {
        $store = new class () implements CounterStoreInterface {
            public function next(int $length): int
            {
                throw new CounterStoreException('simulated DB error');
            }

            public function name(): string
            {
                return 'test';
            }
        };

        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            new Generic(self::TEST_ALPHABET),
        );

        $this->expectException(CounterStoreException::class);
        $gen->generate();
    }

    // --- encoding behaviour (moved from AlphabetTest, driven through IdentityPermutation) ---

    #[Test]
    public function testEncodeIndexPadsWithTheFirstCharacter(): void
    {
        // IdentityPermutation returns index unchanged; index 0 at length 3 = 'aaa'.
        $store = new InMemoryCounterStore([3 => 0]);
        $gen = self::makeGenerator(defaultLength: 3, store: $store);
        $this->assertSame('aaa', $gen->generate());
    }

    #[Test]
    public function testEncodeIndexEncodesCapacityMinusOneAsLastCharRepeated(): void
    {
        // 4^3 - 1 = 63 -> 'ddd'.
        $store = new InMemoryCounterStore([3 => 63]);
        $gen = self::makeGenerator(defaultLength: 3, store: $store);
        $this->assertSame('ddd', $gen->generate());
    }

    #[Test]
    public function testEncodeIndexEncodesIndex1AtLength3Correctly(): void
    {
        $store = new InMemoryCounterStore([3 => 1]);
        $gen = self::makeGenerator(defaultLength: 3, store: $store);
        $this->assertSame('aab', $gen->generate());
    }

    #[Test]
    public function testEncodeIndexEncodesIndex4AtLength2(): void
    {
        // 4 in base 4 = 10 -> "ba"
        $store = new InMemoryCounterStore([2 => 4]);
        $gen = self::makeGenerator(defaultLength: 2, store: $store);
        $this->assertSame('ba', $gen->generate());
    }

    #[Test]
    public function testRoundTripOf64ConsecutiveCallsMatchesNaiveBase4Encoding(): void
    {
        $gen = self::makeGenerator(defaultLength: 3);
        $alphabetChars = str_split(self::TEST_ALPHABET);

        for ($i = 0; $i < 64; $i++) {
            $result = $gen->generate();
            // Naive base-4 encoding of $i into 3 digits.
            $d0 = intdiv($i, 16);
            $d1 = intdiv($i % 16, 4);
            $d2 = $i % 4;
            $expected = $alphabetChars[$d0] . $alphabetChars[$d1] . $alphabetChars[$d2];
            $this->assertSame($expected, $result, "Mismatch at index {$i}");
        }
    }

    #[Test]
    public function testMisbehavingPermutationReturningIndexEqualToSizeThrowsInvalidArgumentException(): void
    {
        $misbehaving = new class () implements KeyspacePermutationInterface {
            public function permute(int $index, int $size, string $tweak): int
            {
                return $size; // out of range: equal to capacity
            }
        };

        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            new InMemoryCounterStore(),
            $misbehaving,
            new Generic(self::TEST_ALPHABET),
        );

        $this->expectException(InvalidArgumentException::class);
        $gen->generate();
    }

    #[Test]
    public function testMisbehavingPermutationReturningNegativeIndexThrowsInvalidArgumentException(): void
    {
        $misbehaving = new class () implements KeyspacePermutationInterface {
            public function permute(int $index, int $size, string $tweak): int
            {
                return -1;
            }
        };

        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            new InMemoryCounterStore(),
            $misbehaving,
            new Generic(self::TEST_ALPHABET),
        );

        $this->expectException(InvalidArgumentException::class);
        $gen->generate();
    }

    // --- custom alphabets end to end ---

    #[Test]
    public function testEmojiAlphabetProducesCorrectOutputAtLength2(): void
    {
        $alphabet = new ConfigurableAlphabet('🍎🍌🍒🍇');
        $store = new InMemoryCounterStore([2 => 0]);
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );

        // Index 0 -> first symbol repeated = '🍎🍎'; each emoji is 4 bytes.
        $result = $gen->generate();
        $this->assertSame('🍎🍎', $result);
        // Length counts symbols, not bytes (side effect S1).
        $this->assertSame(8, strlen('🍎🍎'));
    }

    #[Test]
    public function testEmojiAlphabetIndex5ProducesCorrectOutput(): void
    {
        $alphabet = new ConfigurableAlphabet('🍎🍌🍒🍇');
        // 5 = 1*4 + 1 -> '🍌🍌'
        $store = new InMemoryCounterStore([2 => 5]);
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );

        $this->assertSame('🍌🍌', $gen->generate());
    }

    #[Test]
    public function testEmojiAlphabet16ConsecutiveGenerationsAreDistinct(): void
    {
        $alphabet = new ConfigurableAlphabet('🍎🍌🍒🍇');
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            new InMemoryCounterStore(),
            new IdentityPermutation(),
            $alphabet,
        );

        $results = [];
        for ($i = 0; $i < 16; $i++) {
            $results[] = $gen->generate();
        }
        $this->assertSame(16, count(array_unique($results)));
    }

    #[Test]
    public function testEmojiAlphabet17thCallThrowsKeyspaceExhaustedException(): void
    {
        $alphabet = new ConfigurableAlphabet('🍎🍌🍒🍇');
        $store = new InMemoryCounterStore([2 => 16]);
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );

        $this->expectException(KeyspaceExhaustedException::class);
        $gen->generate();
    }

    #[Test]
    public function testReservedOutputBurnsCounterIndexAndIsSkipped(): void
    {
        // 'aa' is reserved; first call should return 'ab' and afterwards the counter is at 2.
        $alphabet = new ConfigurableAlphabet('abcd', ['aa']);
        $store = new InMemoryCounterStore();
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            $store,
            new IdentityPermutation(),
            $alphabet,
        );

        $this->assertSame('ab', $gen->generate());
        // Counter for length 2 is now at 2 (index 0 was reserved, index 1 was used).
        $this->assertSame(2, $store->next(2));
    }

    #[Test]
    public function testAllReservedOutputsCauseUnableToGenerateAfterMaxAttempts(): void
    {
        // Reserve all 16 two-character strings from the 'abcd' alphabet.
        $chars = str_split('abcd');
        $allStrings = [];
        foreach ($chars as $c1) {
            foreach ($chars as $c2) {
                $allStrings[] = $c1 . $c2;
            }
        }
        $alphabet = new ConfigurableAlphabet('abcd', $allStrings);
        $gen = new PermutationGenerator(
            new LengthPolicy(2),
            new InMemoryCounterStore(),
            new IdentityPermutation(),
            $alphabet,
            maximumAttempts: 5,
        );

        $this->expectException(UnableToGenerateUniqueStringException::class);
        $gen->generate();
    }

    #[Test]
    public function testHexAlphabetWith100GenerationsAllMatchPattern(): void
    {
        $gen = new PermutationGenerator(
            new LengthPolicy(3),
            new InMemoryCounterStore(),
            new FeistelPermutation('hex-secret-xxxxxxx'),
            new HexAlphabet(),
        );

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $result = $gen->generate();
            $this->assertMatchesRegularExpression('/^[0-9a-f]{3}$/', $result);
            $results[] = $result;
        }

        $this->assertSame(100, count(array_unique($results)));
    }
}
