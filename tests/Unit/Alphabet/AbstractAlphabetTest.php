<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractAlphabet;
use Chrismou\StringMint\Exception\InvalidAlphabetException;
use Chrismou\StringMint\Tests\Support\ConfigurableAlphabet;
use Chrismou\StringMint\Tests\Support\HexAlphabet;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractAlphabetTest extends TestCase
{
    // --- splitting ---

    #[Test]
    public function testAsciiStringSplitsIntoOneSymbolPerCharacter(): void
    {
        $alphabet = new class ('abc') extends AbstractAlphabet {};
        $this->assertSame(3, $alphabet->size());
        $this->assertSame('a', $alphabet->characterAt(0));
        $this->assertSame('c', $alphabet->characterAt(2));
    }

    #[Test]
    public function testGreekStringSplitsIntoMultibyteSymbols(): void
    {
        $alphabet = new class ('αβγ') extends AbstractAlphabet {};
        $this->assertSame(3, $alphabet->size());
        $this->assertSame('β', $alphabet->characterAt(1));
        // β is two bytes in UTF-8, but one symbol.
        $this->assertSame(2, strlen($alphabet->characterAt(1)));
    }

    #[Test]
    public function testEmojiStringSplitsIntoFourSymbols(): void
    {
        $alphabet = new class ('🍎🍌🍒🍇') extends AbstractAlphabet {};
        $this->assertSame(4, $alphabet->size());
        $this->assertSame('🍎', $alphabet->characterAt(0));
        // Each emoji is four bytes in UTF-8.
        $this->assertSame(4, strlen($alphabet->characterAt(0)));
    }

    #[Test]
    public function testMixedSingleAndMultibyteStringSplitsPerCharacter(): void
    {
        $alphabet = new class ('aé') extends AbstractAlphabet {};
        $this->assertSame(2, $alphabet->size());
    }

    // --- validation rejection ---

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCharacterSets(): array
    {
        return [
            'empty-string' => [''],
            'single-character' => ['a'],
            'single-multibyte-character' => ['α'],
            'duplicate-characters' => ['aba'],
            'duplicate-multibyte-characters' => ['éé'],
            'invalid-utf8' => ["\xff\xfe"],
            'invalid-utf8-with-ascii' => ["ab\xff"],
        ];
    }

    #[Test]
    #[DataProvider('invalidCharacterSets')]
    public function testRejectsInvalidCharacterSets(string $characters): void
    {
        $this->expectException(InvalidAlphabetException::class);
        new class ($characters) extends AbstractAlphabet {};
    }

    // --- characterAt bounds ---

    #[Test]
    public function testCharacterAtThrowsForNegativePosition(): void
    {
        $alphabet = new class ('ab') extends AbstractAlphabet {};
        $this->expectException(InvalidArgumentException::class);
        $alphabet->characterAt(-1);
    }

    #[Test]
    public function testCharacterAtThrowsForPositionEqualToSize(): void
    {
        $alphabet = new class ('ab') extends AbstractAlphabet {};
        $this->expectException(InvalidArgumentException::class);
        $alphabet->characterAt($alphabet->size());
    }

    // --- isReserved ---

    #[Test]
    public function testDefaultIsReservedReturnsFalseForAllInputs(): void
    {
        $alphabet = new class ('ab') extends AbstractAlphabet {};
        $this->assertFalse($alphabet->isReserved(''));
        $this->assertFalse($alphabet->isReserved('.'));
        $this->assertFalse($alphabet->isReserved('..'));
        $this->assertFalse($alphabet->isReserved('a'));
    }

    #[Test]
    public function testConfigurableAlphabetReturnsReservedForConfiguredCandidates(): void
    {
        $alphabet = new ConfigurableAlphabet('ab', ['a']);
        $this->assertTrue($alphabet->isReserved('a'));
        $this->assertFalse($alphabet->isReserved('b'));
    }

    // --- README example: HexAlphabet ---

    #[Test]
    public function testHexAlphabetHas16SymbolsAndCorrectLastSymbol(): void
    {
        $alphabet = new HexAlphabet();
        $this->assertSame(16, $alphabet->size());
        $this->assertSame('f', $alphabet->characterAt(15));
        $this->assertFalse($alphabet->isReserved('0'));
    }
}
