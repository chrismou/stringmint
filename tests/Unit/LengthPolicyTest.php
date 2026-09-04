<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit;

use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Keyspace;
use Chrismou\StringMint\LengthPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LengthPolicyTest extends TestCase
{
    // --- construction ---

    #[Test]
    public function testAcceptsADefaultLengthOnly(): void
    {
        $policy = new LengthPolicy(4);
        $this->assertSame(4, $policy->defaultLength());
        $this->assertNull($policy->maximumLength());
    }

    #[Test]
    public function testAcceptsADefaultAndMaximumLength(): void
    {
        $policy = new LengthPolicy(4, 8);
        $this->assertSame(4, $policy->defaultLength());
        $this->assertSame(8, $policy->maximumLength());
    }

    #[Test]
    public function testAcceptsEqualDefaultAndMaximum(): void
    {
        $policy = new LengthPolicy(4, 4);
        $this->assertSame(4, $policy->defaultLength());
        $this->assertSame(4, $policy->maximumLength());
    }

    #[Test]
    public function testThrowsWhenDefaultLengthIsLessThanOne(): void
    {
        $this->expectException(InvalidLengthException::class);
        new LengthPolicy(0);
    }

    #[Test]
    public function testThrowsWhenMaximumLengthIsLessThanDefaultLength(): void
    {
        $this->expectException(InvalidLengthException::class);
        new LengthPolicy(5, 3);
    }

    // --- resolveLength ---

    #[Test]
    public function testResolveLengthReturnsTheDefaultWhenOverrideIsNull(): void
    {
        $this->assertSame(4, (new LengthPolicy(4))->resolveLength(null));
    }

    #[Test]
    public function testResolveLengthReturnsTheOverrideWhenWithinBounds(): void
    {
        $this->assertSame(6, (new LengthPolicy(4, 8))->resolveLength(6));
    }

    #[Test]
    public function testResolveLengthAcceptsAnOverrideEqualToTheMaximum(): void
    {
        $this->assertSame(8, (new LengthPolicy(4, 8))->resolveLength(8));
    }

    #[Test]
    public function testResolveLengthAcceptsALargeOverrideWhenNoMaximumIsConfigured(): void
    {
        // LengthPolicy should not reject large values; Keyspace will catch them later.
        $this->assertSame(100, (new LengthPolicy(4))->resolveLength(100));
    }

    #[Test]
    public function testResolveLengthThrowsForOverrideLessThanOne(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new LengthPolicy(4))->resolveLength(0);
    }

    #[Test]
    public function testResolveLengthThrowsForOverrideAboveMaximumLength(): void
    {
        $this->expectException(InvalidLengthException::class);
        (new LengthPolicy(4, 8))->resolveLength(9);
    }

    // --- escalationCeiling ---

    #[Test]
    public function testEscalationCeilingReturnsTheConfiguredMaximumWhenSet(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $this->assertSame(8, (new LengthPolicy(4, 8))->escalationCeiling($keyspace));
    }

    #[Test]
    public function testEscalationCeilingReturnsLargestSupportedLengthWhenNoMaximumIsConfigured(): void
    {
        $keyspace = new Keyspace(new UrlSafe());
        $ceiling = (new LengthPolicy(4))->escalationCeiling($keyspace);
        $this->assertSame($keyspace->largestSupportedLength(), $ceiling);
    }
}
