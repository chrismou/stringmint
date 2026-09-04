<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\LowercaseAlphanumeric;
use Chrismou\StringMint\Tests\Support\ReadsAlphabetSymbols;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LowercaseAlphanumericTest extends TestCase
{
    use ReadsAlphabetSymbols;

    #[Test]
    public function testHasTheExpectedCharacters(): void
    {
        $alphabet = new LowercaseAlphanumeric();
        $this->assertSame(36, $alphabet->size());
        $this->assertSame(LowercaseAlphanumeric::CHARACTERS, self::symbolsOf($alphabet));
    }

    #[Test]
    public function testContainsLowercaseLettersAndDigits(): void
    {
        $symbols = self::symbolsOf(new LowercaseAlphanumeric());
        $this->assertStringContainsString('a', $symbols);
        $this->assertStringContainsString('z', $symbols);
        $this->assertStringContainsString('0', $symbols);
        $this->assertStringContainsString('9', $symbols);
    }

    #[Test]
    public function testDoesNotContainUppercaseOrPunctuation(): void
    {
        $symbols = self::symbolsOf(new LowercaseAlphanumeric());
        $this->assertStringNotContainsString('A', $symbols);
        $this->assertStringNotContainsString('-', $symbols);
        $this->assertStringNotContainsString('.', $symbols);
        $this->assertStringNotContainsString('_', $symbols);
        $this->assertStringNotContainsString('~', $symbols);
    }

    #[Test]
    public function testCharactersAreASubsetOfTheUnreservedSet(): void
    {
        $this->assertSame(
            strlen(LowercaseAlphanumeric::CHARACTERS),
            strspn(LowercaseAlphanumeric::CHARACTERS, AbstractUrlSafeAlphabet::RFC3986_UNRESERVED),
        );
    }

    #[Test]
    public function testInheritsTheTrailingDotGuard(): void
    {
        $alphabet = new LowercaseAlphanumeric();
        $this->assertTrue($alphabet->isReserved('a.'));
        $this->assertFalse($alphabet->isReserved('a'));
    }
}
