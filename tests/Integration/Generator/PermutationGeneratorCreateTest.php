<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Integration\Generator;

use Chrismou\StringMint\Alphabet\LowercaseAlphanumeric;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Counter\CounterTableInstaller;
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\Tests\Support\HexAlphabet;
use Chrismou\StringMint\Tests\Support\PdoTestCase;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

final class PermutationGeneratorCreateTest extends PdoTestCase
{
    /**
     * Returns an in-memory SQLite PDO with the counter table installed, and optionally a custom table too.
     */
    private static function pdoWithTable(?string $customTable = null): PDO
    {
        $pdo = self::sqlitePdo();
        $table = $customTable ?? PdoCounterStore::DEFAULT_TABLE;
        (new CounterTableInstaller($pdo, $table))->install();
        return $pdo;
    }

    #[Test]
    public function testCreateReturnsAWorkingGeneratorThatProducesA4CharString(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'test-secret-xxxxx', 4);
        $result = $gen->generate();
        $this->assertSame(4, strlen($result));
        $this->assertSame(4, strspn($result, UrlSafe::CHARACTERS));
    }

    #[Test]
    public function testCreateHonoursALengthOverrideGenerate5ReturnsA5CharString(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'test-secret-xxxxx', 4);
        $result = $gen->generate(5);
        $this->assertSame(5, strlen($result));
    }

    #[Test]
    public function testCreateEscalatesFromLength3To4WhenTheCounterTableIsPreSeededAtCapacity(): void
    {
        // 66-char default alphabet: capacity at length 3 = 66^3 = 287,496.
        $pdo = self::pdoWithTable();
        $capacity3 = 66 ** 3; // 287496
        $pdo->exec(
            'INSERT INTO "stringmint_counters" ("name", "string_length", "counter_value") VALUES (\'default\', 3, '
                . $capacity3 . ')',
        );

        $gen = PermutationGenerator::create($pdo, 'escalation-secret', length: 3, maximumLength: 4);
        $result = $gen->generateWithAutoLengthIncrement();

        // Length 3 is exhausted; the generator must escalate to length 4.
        $this->assertSame(4, strlen($result));
    }

    #[Test]
    public function testCreateWithMaximumLength3ThrowsForRangeWhenCounterIsAtCapacity(): void
    {
        $pdo = self::pdoWithTable();
        $capacity3 = 66 ** 3;
        $pdo->exec(
            'INSERT INTO "stringmint_counters" ("name", "string_length", "counter_value") VALUES (\'default\', 3, '
                . $capacity3 . ')',
        );

        $gen = PermutationGenerator::create($pdo, 'exhaustion-secret', length: 3, maximumLength: 3);

        $caught = null;
        try {
            $gen->generateWithAutoLengthIncrement();
        } catch (KeyspaceExhaustedException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        // forRange() sets length() to null and includes both bounds in the message.
        $this->assertNull($caught->length());
        $this->assertStringContainsString('3', $caught->getMessage());
    }

    #[Test]
    public function testCreateWithLength3AndMaximumLength4RejectsGenerate5WithInvalidLengthException(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'test-secret-xxxxx', length: 3, maximumLength: 4);
        $this->expectException(InvalidLengthException::class);
        $gen->generate(5);
    }

    #[Test]
    public function testCounterNameIsHonouredLinksAndInvitesProduceDifferentSequencesAndSeparateRows(): void
    {
        $pdo = self::pdoWithTable();
        $genLinks = PermutationGenerator::create($pdo, 'counter-name-test', 4, counterName: 'links');
        $genInvites = PermutationGenerator::create($pdo, 'counter-name-test', 4, counterName: 'invites');

        $linksStrings = [];
        $invitesStrings = [];
        for ($i = 0; $i < 10; $i++) {
            $linksStrings[] = $genLinks->generate();
            $invitesStrings[] = $genInvites->generate();
        }

        // Different sequences (independent permutations due to different tweaks).
        $this->assertFalse($linksStrings === $invitesStrings);

        // Two separate rows exist in the table: one per (name, length).
        $count = self::queryOrFail($pdo, 'SELECT COUNT(*) FROM "stringmint_counters" WHERE "string_length" = 4')
            ->fetchColumn();
        $this->assertSame(2, (int) $count);
    }

    #[Test]
    public function testTableNameIsHonouredRowsGoToTheCustomTableAndDefaultTableStaysEmpty(): void
    {
        $pdo = self::sqlitePdo();
        (new CounterTableInstaller($pdo))->install();
        (new CounterTableInstaller($pdo, 'custom_counters'))->install();

        $gen = PermutationGenerator::create(
            $pdo,
            'table-name-secret',
            4,
            tableName: 'custom_counters',
        );
        $gen->generate();

        $defaultCount = self::queryOrFail($pdo, 'SELECT COUNT(*) FROM "stringmint_counters"')
            ->fetchColumn();
        $customCount = self::queryOrFail($pdo, 'SELECT COUNT(*) FROM "custom_counters"')
            ->fetchColumn();

        $this->assertSame(0, (int) $defaultCount);
        $this->assertSame(1, (int) $customCount);
    }

    #[Test]
    public function testEmptySecretThrowsFromFeistelPermutationComposedConstructor(): void
    {
        $pdo = self::pdoWithTable();
        $this->expectException(InvalidArgumentException::class);
        PermutationGenerator::create($pdo, '', 4);
    }

    #[Test]
    public function testInvalidTableNameThrowsInvalidTableNameExceptionFromComposedConstructor(): void
    {
        $pdo = self::pdoWithTable();
        $this->expectException(InvalidTableNameException::class);
        PermutationGenerator::create($pdo, 'secret', 4, tableName: 'invalid-name');
    }

    #[Test]
    public function testTheClassExposesNoPublicMethodOrPropertyNamedLikeSecret(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'my-secret-value-x', 4);
        $reflection = new ReflectionObject($gen);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertStringNotContainsString('secret', strtolower($method->getName()));
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->assertStringNotContainsString('secret', strtolower($property->getName()));
        }
    }

    // --- alphabet parameter ---

    #[Test]
    public function testAlphabetParameterLowercaseAlphanumericProducesCorrectCharacters(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'secret', 4, alphabet: new LowercaseAlphanumeric());
        $result = $gen->generate();
        $this->assertSame(4, strspn($result, LowercaseAlphanumeric::CHARACTERS));
    }

    #[Test]
    public function testAlphabetParameterHexAlphabetProducesHexOutput(): void
    {
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'secret', 4, alphabet: new HexAlphabet());
        $result = $gen->generate();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{4}$/', $result);
    }

    #[Test]
    public function testHexAlphabetAtLength16ThrowsInvalidLengthException(): void
    {
        // 16^16 = 2^64 > 2^62; must be rejected.
        $pdo = self::pdoWithTable();
        $this->expectException(InvalidLengthException::class);
        PermutationGenerator::create($pdo, 'secret', 16, alphabet: new HexAlphabet());
    }

    #[Test]
    public function testHexAlphabetAtLength15Constructs(): void
    {
        // 16^15 = 2^60 <= 2^62; must succeed.
        $pdo = self::pdoWithTable();
        $gen = PermutationGenerator::create($pdo, 'secret', 15, alphabet: new HexAlphabet());
        $this->assertInstanceOf(PermutationGenerator::class, $gen);
    }
}
