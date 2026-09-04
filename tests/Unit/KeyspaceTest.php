<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit;

use Chrismou\StringMint\Alphabet\Generic;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Keyspace;
use Chrismou\StringMint\Tests\Support\ConfigurableAlphabet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KeyspaceTest extends TestCase
{
    // --- capacityForLength ---

    #[Test]
    public function testCapacityForLengthReturns2ForA2CharAlphabetAtLength1(): void
    {
        $keyspace = new Keyspace(new Generic('ab'));
        $this->assertSame(2, $keyspace->capacityForLength(1));
    }

    #[Test]
    public function testCapacityForLengthReturns4ForA2CharAlphabetAtLength2(): void
    {
        $keyspace = new Keyspace(new Generic('ab'));
        $this->assertSame(4, $keyspace->capacityForLength(2));
    }

    #[Test]
    public function testCapacityForLengthReturns66ToThe4thForDefaultAlphabetAtLength4(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $this->assertSame(66 ** 4, $keyspace->capacityForLength(4));
    }

    #[Test]
    public function testCapacityForLengthReturns66ToThe10thForDefaultAlphabetAtLength10(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $expected = 66 ** 10;
        $this->assertSame($expected, $keyspace->capacityForLength(10));
        $this->assertLessThanOrEqual(Keyspace::MAXIMUM_CAPACITY, $expected);
    }

    #[Test]
    public function testCapacityForLengthThrowsInvalidLengthExceptionForLength0(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new Keyspace(new Generic('ab')))->capacityForLength(0);
    }

    #[Test]
    public function testCapacityForLengthThrowsInvalidLengthExceptionForNegativeLength(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new Keyspace(new Generic('ab')))->capacityForLength(-1);
    }

    #[Test]
    public function testCapacityForLengthThrowsInvalidLengthExceptionFor66CharAlphabetAtLength11(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new Keyspace(new UrlSafe()))->capacityForLength(11);
    }

    #[Test]
    public function testCapacityForLengthThrowsInvalidLengthExceptionFor2CharAlphabetAtLength63(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new Keyspace(new Generic('ab')))->capacityForLength(63);
    }

    // --- supportsLength ---

    #[Test]
    public function testSupportsLengthReturnsTrueForSupportedLengths(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $this->assertTrue($keyspace->supportsLength(10));
        $this->assertTrue($keyspace->supportsLength(1));
    }

    #[Test]
    public function testSupportsLengthReturnsFalseForUnsupportedLengths(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $this->assertFalse($keyspace->supportsLength(11));
        $this->assertFalse($keyspace->supportsLength(0));
    }

    #[Test]
    public function testSupportsLengthReturnsTrueFor2CharAlphabetAtLength62(): void
    {
        $this->assertTrue((new Keyspace(new Generic('ab')))->supportsLength(62));
    }

    #[Test]
    public function testSupportsLengthReturnsFalseFor2CharAlphabetAtLength63(): void
    {
        $this->assertFalse((new Keyspace(new Generic('ab')))->supportsLength(63));
    }

    // --- largestSupportedLength ---

    #[Test]
    public function testLargestSupportedLengthReturns10ForThe66CharDefaultAlphabet(): void
    {
        $this->assertSame(10, (new Keyspace(new UrlSafe()))->largestSupportedLength());
    }

    #[Test]
    public function testLargestSupportedLengthReturns62ForA2CharAlphabet(): void
    {
        $this->assertSame(62, (new Keyspace(new Generic('ab')))->largestSupportedLength());
    }

    #[Test]
    public function testLargestSupportedLengthIsConsistentWithSupportsLength(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $largest = $keyspace->largestSupportedLength();
        $this->assertTrue($keyspace->supportsLength($largest));
        $this->assertFalse($keyspace->supportsLength($largest + 1));
    }

    // --- non-UrlSafe alphabet via interface ---

    #[Test]
    public function testKeyspaceAcceptsNonUrlSafeAlphabetAndCountsCharactersNotBytes(): void
    {
        $keyspace = new Keyspace(new ConfigurableAlphabet('αβγ'));
        // 3-symbol alphabet, capacity for length 3 = 3^3 = 27.
        $this->assertSame(27, $keyspace->capacityForLength(3));
    }
}
