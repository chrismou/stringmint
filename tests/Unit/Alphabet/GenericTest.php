<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractAlphabet;
use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\Generic;
use Chrismou\StringMint\Exception\InvalidAlphabetException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GenericTest extends TestCase
{
    // --- splitting ---

    #[Test]
    public function testAsciiInputSplitsIntoOneSymbolPerCharacter(): void
    {
        $alphabet = new Generic('abcd');
        $this->assertSame(4, $alphabet->size());
        $this->assertSame('a', $alphabet->characterAt(0));
    }

    #[Test]
    public function testMultibyteInputSplitsIntoSymbols(): void
    {
        $alphabet = new Generic('αβγ');
        $this->assertSame(3, $alphabet->size());
    }

    // --- accepts non-URL-safe characters ---

    #[Test]
    public function testAcceptsCharactersThatUrlSafeAlphabetRejects(): void
    {
        $alphabet = new Generic('a/b+c=');
        $this->assertSame(6, $alphabet->size());
    }

    // --- validation ---

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCharacterSets(): array
    {
        return [
            'empty-string' => [''],
            'single-character' => ['a'],
            'duplicate-characters' => ['aba'],
            'invalid-utf8' => ["ab\xff"],
        ];
    }

    #[Test]
    #[DataProvider('invalidCharacterSets')]
    public function testRejectsInvalidCharacterSets(string $characters): void
    {
        $this->expectException(InvalidAlphabetException::class);
        new Generic($characters);
    }

    // --- isReserved ---

    #[Test]
    public function testIsReservedReturnsFalseForAllInputs(): void
    {
        $alphabet = new Generic('ab');
        $this->assertFalse($alphabet->isReserved(''));
        $this->assertFalse($alphabet->isReserved('.'));
        $this->assertFalse($alphabet->isReserved('..'));
        $this->assertFalse($alphabet->isReserved('a.'));
    }

    // --- class hierarchy ---

    #[Test]
    public function testIsFinal(): void
    {
        $reflection = new ReflectionClass(Generic::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function testExtendsAbstractAlphabetDirectly(): void
    {
        $this->assertInstanceOf(AbstractAlphabet::class, new Generic('ab'));
        $this->assertNotInstanceOf(AbstractUrlSafeAlphabet::class, new Generic('ab'));
    }
}
