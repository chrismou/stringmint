<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Existence;

use Chrismou\StringMint\Existence\CallableExistenceChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CallableExistenceCheckerTest extends TestCase
{
    #[Test]
    public function testDelegatesToTheCallback(): void
    {
        $checker = new CallableExistenceChecker(fn (string $c) => $c === 'exists');
        $this->assertTrue($checker->exists('exists'));
        $this->assertFalse($checker->exists('other'));
    }

    #[Test]
    public function testCallsTheCallbackWithTheExactCandidateString(): void
    {
        $received = null;
        $checker = new CallableExistenceChecker(function (string $c) use (&$received): bool {
            $received = $c;
            return false;
        });
        $checker->exists('test-candidate');
        $this->assertSame('test-candidate', $received);
    }
}
