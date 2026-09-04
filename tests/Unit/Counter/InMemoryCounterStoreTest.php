<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Counter;

use Chrismou\StringMint\Counter\InMemoryCounterStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryCounterStoreTest extends TestCase
{
    #[Test]
    public function testReturnsSequentialValuesStartingAt0ForEachLength(): void
    {
        $store = new InMemoryCounterStore();
        $this->assertSame(0, $store->next(4));
        $this->assertSame(1, $store->next(4));
        $this->assertSame(2, $store->next(4));
    }

    #[Test]
    public function testTracksLengthsIndependently(): void
    {
        $store = new InMemoryCounterStore();
        $this->assertSame(0, $store->next(4));
        $this->assertSame(0, $store->next(5));
        $this->assertSame(1, $store->next(4));
        $this->assertSame(1, $store->next(5));
    }

    #[Test]
    public function testRespectsSeededStartValues(): void
    {
        $store = new InMemoryCounterStore([3 => 64, 4 => 1000]);
        $this->assertSame(64, $store->next(3));
        $this->assertSame(65, $store->next(3));
        $this->assertSame(1000, $store->next(4));
    }

    #[Test]
    public function testNameReturnsDefaultWhenOmitted(): void
    {
        $this->assertSame('default', (new InMemoryCounterStore())->name());
    }

    #[Test]
    public function testNameReturnsTheConstructorArgumentWhenGiven(): void
    {
        $this->assertSame('links', (new InMemoryCounterStore(name: 'links'))->name());
        $this->assertSame('invites', (new InMemoryCounterStore(name: 'invites'))->name());
    }

    #[Test]
    public function testExistingStartAtUsageWithPositionalArgumentStillWorks(): void
    {
        $store = new InMemoryCounterStore([3 => 64]);
        $this->assertSame(64, $store->next(3));
        $this->assertSame('default', $store->name());
    }
}
