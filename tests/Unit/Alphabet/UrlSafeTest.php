<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Alphabet;

use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Tests\Support\ReadsAlphabetSymbols;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlSafeTest extends TestCase
{
    use ReadsAlphabetSymbols;

    #[Test]
    public function testDefaultConstructorReturnsTheFull66CharRfc3986UnreservedSet(): void
    {
        $alphabet = new UrlSafe();
        $this->assertSame(66, $alphabet->size());
        $symbols = self::symbolsOf($alphabet);
        $this->assertSame(UrlSafe::CHARACTERS, $symbols);
        $this->assertSame(AbstractUrlSafeAlphabet::RFC3986_UNRESERVED, UrlSafe::CHARACTERS);
        $this->assertStringContainsString('A', $symbols);
        $this->assertStringContainsString('Z', $symbols);
        $this->assertStringContainsString('a', $symbols);
        $this->assertStringContainsString('z', $symbols);
        $this->assertStringContainsString('0', $symbols);
        $this->assertStringContainsString('9', $symbols);
        $this->assertStringContainsString('-', $symbols);
        $this->assertStringContainsString('.', $symbols);
        $this->assertStringContainsString('_', $symbols);
        $this->assertStringContainsString('~', $symbols);
    }

    #[Test]
    public function testHasTheExpectedCharacters(): void
    {
        $alphabet = new UrlSafe();
        $this->assertSame(66, $alphabet->size());
        $this->assertSame(UrlSafe::CHARACTERS, self::symbolsOf($alphabet));
    }

    #[Test]
    public function testCharactersAreASubsetOfTheUnreservedSet(): void
    {
        $this->assertSame(
            strlen(UrlSafe::CHARACTERS),
            strspn(UrlSafe::CHARACTERS, AbstractUrlSafeAlphabet::RFC3986_UNRESERVED),
        );
    }

    #[Test]
    public function testInheritsTheTrailingDotGuard(): void
    {
        $alphabet = new UrlSafe();
        $this->assertTrue($alphabet->isReserved('a.'));
        $this->assertFalse($alphabet->isReserved('a'));
    }
}
