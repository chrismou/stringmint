<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Existence;

use Chrismou\StringMint\Existence\InMemoryExistenceChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryExistenceCheckerTest extends TestCase
{
    #[Test]
    public function testReturnsFalseForUnknownStrings(): void
    {
        $checker = new InMemoryExistenceChecker();
        $this->assertFalse($checker->exists('abc'));
    }

    #[Test]
    public function testReturnsTrueForStringsPassedToTheConstructor(): void
    {
        $checker = new InMemoryExistenceChecker(['abc', 'def']);
        $this->assertTrue($checker->exists('abc'));
        $this->assertTrue($checker->exists('def'));
        $this->assertFalse($checker->exists('ghi'));
    }

    #[Test]
    public function testReturnsTrueAfterRememberIsCalled(): void
    {
        $checker = new InMemoryExistenceChecker();
        $checker->remember('xyz');
        $this->assertTrue($checker->exists('xyz'));
    }

    #[Test]
    public function testCountReflectsTheNumberOfRememberedStrings(): void
    {
        $checker = new InMemoryExistenceChecker(['a', 'b']);
        $this->assertSame(2, $checker->count());
        $checker->remember('c');
        $this->assertSame(3, $checker->count());
    }

    #[Test]
    public function testAcceptsAnIterableGeneratorInTheConstructor(): void
    {
        $gen = (function () {
            yield 'one';
            yield 'two';
        })();
        $checker = new InMemoryExistenceChecker($gen);
        $this->assertTrue($checker->exists('one'));
        $this->assertTrue($checker->exists('two'));
        $this->assertFalse($checker->exists('three'));
    }
}
