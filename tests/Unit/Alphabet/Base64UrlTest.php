<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\Base64Url;
use Chrismou\StringMint\Tests\Support\ReadsAlphabetSymbols;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Base64UrlTest extends TestCase
{
    use ReadsAlphabetSymbols;

    #[Test]
    public function testHasTheExpectedCharacters(): void
    {
        $alphabet = new Base64Url();
        $this->assertSame(64, $alphabet->size());
        $this->assertSame(Base64Url::CHARACTERS, self::symbolsOf($alphabet));
    }

    #[Test]
    public function testContainsBase64UrlCharacters(): void
    {
        $symbols = self::symbolsOf(new Base64Url());
        $this->assertStringContainsString('A', $symbols);
        $this->assertStringContainsString('Z', $symbols);
        $this->assertStringContainsString('a', $symbols);
        $this->assertStringContainsString('z', $symbols);
        $this->assertStringContainsString('0', $symbols);
        $this->assertStringContainsString('9', $symbols);
        $this->assertStringContainsString('-', $symbols);
        $this->assertStringContainsString('_', $symbols);
    }

    #[Test]
    public function testDoesNotContainDotOrTilde(): void
    {
        $symbols = self::symbolsOf(new Base64Url());
        $this->assertStringNotContainsString('.', $symbols);
        $this->assertStringNotContainsString('~', $symbols);
    }

    #[Test]
    public function testCharactersAreASubsetOfTheUnreservedSet(): void
    {
        $this->assertSame(
            strlen(Base64Url::CHARACTERS),
            strspn(Base64Url::CHARACTERS, AbstractUrlSafeAlphabet::RFC3986_UNRESERVED),
        );
    }

    #[Test]
    public function testInheritsTheTrailingDotGuard(): void
    {
        $alphabet = new Base64Url();
        $this->assertTrue($alphabet->isReserved('a.'));
        $this->assertFalse($alphabet->isReserved('a'));
    }
}
