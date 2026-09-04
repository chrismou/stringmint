<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Generator;

use Chrismou\StringMint\Alphabet\AlphabetInterface;
use Chrismou\StringMint\Alphabet\Generic;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Existence\CallableExistenceChecker;
use Chrismou\StringMint\Existence\InMemoryExistenceChecker;
use Chrismou\StringMint\Existence\NeverExistsChecker;
use Chrismou\StringMint\Generator\RandomGenerator;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Tests\Support\ConfigurableAlphabet;
use Chrismou\StringMint\Tests\Support\HexAlphabet;
use Chrismou\StringMint\Tests\Support\ScriptedRandomEngine;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class RandomGeneratorTest extends TestCase
{
    /**
     * Builds a RandomGenerator with convenient defaults.
     */
    private static function makeRandom(
        int $defaultLength = 4,
        ?int $maxLength = null,
        ?InMemoryExistenceChecker $checker = null,
        ?AlphabetInterface $alphabet = null,
        int $maxAttempts = 10,
        ?Randomizer $randomizer = null,
    ): RandomGenerator {
        return new RandomGenerator(
            new LengthPolicy($defaultLength, $maxLength),
            $checker ?? new NeverExistsChecker(),
            $alphabet ?? new UrlSafe(),
            $maxAttempts,
            $randomizer,
        );
    }

    // --- basic generation ---

    #[Test]
    public function testGenerateReturnsAStringOfTheDefaultLength(): void
    {
        $gen = self::makeRandom(defaultLength: 4);
        $this->assertSame(4, strlen($gen->generate()));
    }

    #[Test]
    public function testGenerateWithOverrideUsesTheSpecifiedLength(): void
    {
        $gen = self::makeRandom(defaultLength: 4);
        $this->assertSame(6, strlen($gen->generate(6)));
    }

    #[Test]
    public function testOutputContainsOnlyAlphabetCharacters(): void
    {
        $alphabet = new Generic('abcdef');
        $gen = self::makeRandom(defaultLength: 4, alphabet: $alphabet);
        $result = $gen->generate();
        for ($i = 0; $i < strlen($result); $i++) {
            $this->assertNotFalse(strpos('abcdef', $result[$i]));
        }
    }

    #[Test]
    public function testUsesTheInjectedRandomizerDeterministicallyWithSeededMt19937(): void
    {
        $randomizer1 = new Randomizer(new Mt19937(42));
        $randomizer2 = new Randomizer(new Mt19937(42));
        $gen1 = self::makeRandom(defaultLength: 4, randomizer: $randomizer1);
        $gen2 = self::makeRandom(defaultLength: 4, randomizer: $randomizer2);
        $this->assertSame($gen1->generate(), $gen2->generate());
    }

    // --- retry path ---

    #[Test]
    public function testSkipsACandidateThatAlreadyExistsAndReturnsTheNextOne(): void
    {
        $randomizer1 = new Randomizer(new Mt19937(0));
        $gen1 = self::makeRandom(defaultLength: 4, randomizer: $randomizer1);
        $firstString = $gen1->generate(); // No checker; first candidate accepted.

        // Now block the first string.
        $checker = new InMemoryExistenceChecker([$firstString]);
        $randomizer2 = new Randomizer(new Mt19937(0));
        $gen2 = self::makeRandom(defaultLength: 4, checker: $checker, randomizer: $randomizer2);
        $secondString = $gen2->generate();

        $this->assertNotSame($firstString, $secondString);
        $this->assertSame(4, strlen($secondString));
    }

    // --- exhaustion ---

    #[Test]
    public function testGenerateThrowsInferredForLengthAfterMaximumAttemptsPerLengthCollisionsAndNeverChangesLength(): void
    {
        $alwaysExists = new CallableExistenceChecker(fn () => true);
        $gen = new RandomGenerator(
            new LengthPolicy(4),
            $alwaysExists,
            new UrlSafe(),
            3, // maximumAttemptsPerLength
        );

        $e = null;
        try {
            $gen->generate();
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertSame(4, $e->length());
        $this->assertStringContainsString('appears exhausted', $e->getMessage());
    }

    #[Test]
    public function testGenerateWithAutoLengthIncrementEscalatesAfterMaximumAttemptsPerLengthCollisions(): void
    {
        // Reject all 2-char candidates, accept 3-char ones.
        $twoCharChecker = new CallableExistenceChecker(
            fn (string $c) => strlen($c) === 2,
        );
        $gen = new RandomGenerator(
            new LengthPolicy(2, 3),
            $twoCharChecker,
            new UrlSafe(),
            5,
        );
        $result = $gen->generateWithAutoLengthIncrement();
        $this->assertSame(3, strlen($result));
    }

    #[Test]
    public function testGenerateWithAutoLengthIncrementThrowsForRangeWhenTheCeilingIsAlsoExhausted(): void
    {
        $alwaysExists = new CallableExistenceChecker(fn () => true);
        $alphabet = new Generic('ab');
        $gen = new RandomGenerator(
            new LengthPolicy(1, 2), // ceiling = 2
            $alwaysExists,
            $alphabet,
            2,
        );
        $e = null;
        try {
            $gen->generateWithAutoLengthIncrement();
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertNull($e->length()); // forRange
    }

    #[Test]
    public function testGenerateWithAutoLengthIncrementThrowsForRangeWhenKeyspaceCeilingIsReachedWithNoConfiguredMax(): void
    {
        // 2-char alphabet, largestSupportedLength = 62. Set starting length = 61.
        $alwaysExists = new CallableExistenceChecker(fn () => true);
        $alphabet = new Generic('ab');
        $gen = new RandomGenerator(
            new LengthPolicy(61), // no max -> ceiling = 62
            $alwaysExists,
            $alphabet,
            1,
        );
        $e = null;
        try {
            $gen->generateWithAutoLengthIncrement();
        } catch (KeyspaceExhaustedException $caught) {
            $e = $caught;
        }
        $this->assertNotNull($e);
        $this->assertNull($e->length());
    }

    // --- path hazards ---

    #[Test]
    public function testSkipsDotAtLength1UsingScriptedRandomEngineAndProvesTheFirstDrawWasDot(): void
    {
        // Mt19937 seed 46 byte layout (verified locally):
        //   generate() call 1 -> hex "bd3ca9c8" -> getInt(0, 65) = 63 -> '.' in the default alphabet
        //   generate() call 2 -> hex "455b0f42" -> getInt(0, 65) = 37 -> 'l' in the default alphabet
        $dotBytes = hex2bin('bd3ca9c8');
        $this->assertIsString($dotBytes, 'hex2bin(bd3ca9c8) must return a string');
        $nextBytes = hex2bin('455b0f42');
        $this->assertIsString($nextBytes, 'hex2bin(455b0f42) must return a string');

        // Prove the first queued byte string produces index 63 ('.').
        $probe = new Randomizer(new ScriptedRandomEngine([$dotBytes]));
        $this->assertSame(63, $probe->getInt(0, 65));

        // The generator must skip '.' (index 63) and return the next candidate ('l', index 37).
        $gen = new RandomGenerator(
            new LengthPolicy(1),
            new NeverExistsChecker(),
            new UrlSafe(),
            10,
            new Randomizer(new ScriptedRandomEngine([$dotBytes, $nextBytes])),
        );

        $result = $gen->generate();
        $this->assertNotSame('.', $result);
        $this->assertNotSame('..', $result);
        $this->assertSame('l', $result); // Index 37 in the default RFC 3986 unreserved alphabet.
        $this->assertSame(1, strlen($result));
    }

    // --- length validation ---

    #[Test]
    public function testGenerateWithOverrideAboveMaximumLengthThrowsInvalidLengthException(): void
    {
        $gen = self::makeRandom(defaultLength: 2, maxLength: 4);
        $this->expectException(InvalidLengthException::class);
        $gen->generate(5);
    }

    #[Test]
    public function testGenerate11With66CharAlphabetThrowsInvalidLengthExceptionBecauseItExceeds2To62(): void
    {
        $gen = self::makeRandom(defaultLength: 4);
        $this->expectException(InvalidLengthException::class);
        $gen->generate(11);
    }

    #[Test]
    public function testDefaultLengthBeyondKeyspaceCeilingThrowsInvalidLengthExceptionInConstructor(): void
    {
        $this->expectException(InvalidLengthException::class);
        self::makeRandom(defaultLength: 11);
    }

    #[Test]
    public function testMaximumLengthBeyondKeyspaceCeilingThrowsInvalidLengthExceptionInConstructor(): void
    {
        $this->expectException(InvalidLengthException::class);
        self::makeRandom(defaultLength: 4, maxLength: 11);
    }

    #[Test]
    public function testMaximumAttemptsPerLengthLessThan1ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RandomGenerator(
            new LengthPolicy(4),
            new NeverExistsChecker(),
            new UrlSafe(),
            0,
        );
    }

    // --- custom alphabets ---

    #[Test]
    public function testEmojiAlphabetProducesOutputMatchingPattern(): void
    {
        $gen = new RandomGenerator(
            new LengthPolicy(3),
            new NeverExistsChecker(),
            new ConfigurableAlphabet('🍎🍌🍒🍇'),
        );

        $result = $gen->generate();
        // Build a pattern from the alphabet characters so it does not depend on ext-mbstring.
        $pattern = '/^(?:' . implode('|', array_map('preg_quote', ['🍎', '🍌', '🍒', '🍇'])) . '){3}$/u';
        $this->assertMatchesRegularExpression($pattern, $result);
        // Length counts symbols, not bytes: 3 emoji = 12 bytes.
        $this->assertSame(12, strlen($result));
    }

    #[Test]
    public function testReservedCandidatesAreSkippedAndAllCallsReturnNonReservedCandidate(): void
    {
        // 'a' is reserved; every output must be 'b'.
        $gen = new RandomGenerator(
            new LengthPolicy(1),
            new NeverExistsChecker(),
            new ConfigurableAlphabet('ab', ['a']),
            maximumAttemptsPerLength: 64,
        );

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame('b', $gen->generate());
        }
    }

    #[Test]
    public function testHexAlphabetProducesOutputMatchingPattern(): void
    {
        $gen = new RandomGenerator(
            new LengthPolicy(6),
            new NeverExistsChecker(),
            new HexAlphabet(),
        );

        $result = $gen->generate();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $result);
    }
}
