<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Exception\InvalidAlphabetException;
use Chrismou\StringMint\Tests\Support\ReadsAlphabetSymbols;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractUrlSafeAlphabetTest extends TestCase
{
    use ReadsAlphabetSymbols;

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
            'space' => ['ab '],
            'slash' => ['a/b'],
            'plus' => ['a+b'],
            'equals' => ['a=b'],
            'percent' => ['a%b'],
            'multibyte' => ["ab\xc3\xa9"],
            'invalid-utf8' => ["ab\xff"],
        ];
    }

    #[Test]
    #[DataProvider('invalidCharacterSets')]
    public function testRejectsInvalidCharacterSets(string $characters): void
    {
        $this->expectException(InvalidAlphabetException::class);
        new class ($characters) extends AbstractUrlSafeAlphabet {};
    }

    #[Test]
    public function testAcceptsAnyStrictSubsetOfTheUnreservedSet(): void
    {
        $alphabet = new class ('abcd') extends AbstractUrlSafeAlphabet {};
        $this->assertSame(4, $alphabet->size());
        $this->assertSame('d', $alphabet->characterAt(3));
    }

    #[Test]
    public function testAcceptsTheFullUnreservedSet(): void
    {
        $alphabet = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {};
        $this->assertSame(66, $alphabet->size());
    }

    // --- isReserved ---

    #[Test]
    public function testIsReservedReturnsTrueForSingleDot(): void
    {
        $alphabet = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {};
        $this->assertTrue($alphabet->isReserved('.'));
    }

    #[Test]
    public function testIsReservedReturnsTrueForDoubleDot(): void
    {
        $alphabet = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {};
        $this->assertTrue($alphabet->isReserved('..'));
    }

    #[Test]
    public function testIsReservedReturnsTrueForAnyTrailingDot(): void
    {
        $alphabet = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {};
        $this->assertTrue($alphabet->isReserved('...'));
        $this->assertTrue($alphabet->isReserved('a.'));
        $this->assertTrue($alphabet->isReserved('abc-1.'));
    }

    #[Test]
    public function testIsReservedReturnsFalseForOtherStrings(): void
    {
        $alphabet = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {};
        $this->assertFalse($alphabet->isReserved('a'));
        $this->assertFalse($alphabet->isReserved('.a'));
        $this->assertFalse($alphabet->isReserved('a.b'));
        $this->assertFalse($alphabet->isReserved(''));
    }

    // --- extension ---

    #[Test]
    public function testSubclassCanOverrideIsReservedWhileKeepingUnreservedValidation(): void
    {
        $extended = new class (AbstractUrlSafeAlphabet::RFC3986_UNRESERVED) extends AbstractUrlSafeAlphabet {
            public function isReserved(string $candidate): bool
            {
                return parent::isReserved($candidate) || str_starts_with($candidate, '-');
            }
        };

        $this->assertSame(66, $extended->size());
        $this->assertTrue($extended->isReserved('.'));
        $this->assertTrue($extended->isReserved('-a'));
        $this->assertFalse($extended->isReserved('a-'));
    }

    #[Test]
    public function testSubclassWithInvalidCharactersStillThrowsInvalidAlphabetException(): void
    {
        $this->expectException(InvalidAlphabetException::class);
        new class ('a/b') extends AbstractUrlSafeAlphabet {};
    }
}
