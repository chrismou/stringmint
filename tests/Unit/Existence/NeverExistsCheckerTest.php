<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Existence;

use Chrismou\StringMint\Existence\NeverExistsChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NeverExistsCheckerTest extends TestCase
{
    #[Test]
    public function testAlwaysReturnsFalse(): void
    {
        $checker = new NeverExistsChecker();
        $this->assertFalse($checker->exists('anything'));
        $this->assertFalse($checker->exists(''));
        $this->assertFalse($checker->exists('abc123'));
    }
}
