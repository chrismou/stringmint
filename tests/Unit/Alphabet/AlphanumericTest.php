<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\Alphanumeric;
use Chrismou\StringMint\Tests\Support\ReadsAlphabetSymbols;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlphanumericTest extends TestCase
{
    use ReadsAlphabetSymbols;

    #[Test]
    public function testHasTheExpectedCharacters(): void
    {
        $alphabet = new Alphanumeric();
        $this->assertSame(62, $alphabet->size());
        $this->assertSame(Alphanumeric::CHARACTERS, self::symbolsOf($alphabet));
    }

    #[Test]
    public function testContainsLettersAndDigits(): void
    {
        $symbols = self::symbolsOf(new Alphanumeric());
        $this->assertStringContainsString('A', $symbols);
        $this->assertStringContainsString('Z', $symbols);
        $this->assertStringContainsString('a', $symbols);
        $this->assertStringContainsString('z', $symbols);
        $this->assertStringContainsString('0', $symbols);
        $this->assertStringContainsString('9', $symbols);
    }

    #[Test]
    public function testDoesNotContainPunctuation(): void
    {
        $symbols = self::symbolsOf(new Alphanumeric());
        $this->assertStringNotContainsString('-', $symbols);
        $this->assertStringNotContainsString('.', $symbols);
        $this->assertStringNotContainsString('_', $symbols);
        $this->assertStringNotContainsString('~', $symbols);
    }

    #[Test]
    public function testCharactersAreASubsetOfTheUnreservedSet(): void
    {
        $this->assertSame(
            strlen(Alphanumeric::CHARACTERS),
            strspn(Alphanumeric::CHARACTERS, AbstractUrlSafeAlphabet::RFC3986_UNRESERVED),
        );
    }

    #[Test]
    public function testInheritsTheTrailingDotGuard(): void
    {
        $alphabet = new Alphanumeric();
        $this->assertTrue($alphabet->isReserved('a.'));
        $this->assertFalse($alphabet->isReserved('a'));
    }
}
