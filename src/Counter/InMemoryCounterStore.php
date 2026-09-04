<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter;

/**
 * An in-memory CounterStoreInterface for tests, CLI scripts and single-process applications.
 *
 * Not shared across processes or requests. Supports seeding individual lengths at specific
 * starting values so tests can assert exhaustion behaviour without generating thousands of strings.
 *
 * The $startAt parameter is first so existing positional usage (new InMemoryCounterStore([3 => 64]))
 * is unchanged; pass $name as a named argument when needed (new InMemoryCounterStore(name: 'links')).
 */
final class InMemoryCounterStore implements CounterStoreInterface
{
    /** @var array<int, int> Current counter values keyed by length. */
    private array $counters;

    /**
     * @param array<int, int> $startAt Initial counter values keyed by length. Defaults to 0 for any unset length.
     */
    public function __construct(
        array $startAt = [],
        private readonly string $name = 'default',
    ) {
        $this->counters = $startAt;
    }

    /**
     * Returns and increments the counter for the given length, starting at the seeded value (default 0).
     */
    public function next(int $length): int
    {
        if (!isset($this->counters[$length])) {
            $this->counters[$length] = 0;
        }

        return $this->counters[$length]++;
    }

    /**
     * Returns the counter namespace name.
     */
    public function name(): string
    {
        return $this->name;
    }
}
