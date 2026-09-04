<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Existence;

/**
 * A set-backed ExistenceCheckerInterface that stores known strings in memory.
 *
 * Useful as a test double and for single-process applications where a database lookup would
 * be excessive. Not shared across processes or requests.
 */
final class InMemoryExistenceChecker implements ExistenceCheckerInterface
{
    /** @var array<string, true> */
    private array $existing = [];

    /**
     * @param iterable<string> $existing Strings already considered to exist.
     */
    public function __construct(iterable $existing = [])
    {
        foreach ($existing as $value) {
            $this->existing[$value] = true;
        }
    }

    /**
     * Returns true when $candidate is in the in-memory set.
     */
    public function exists(string $candidate): bool
    {
        return isset($this->existing[$candidate]);
    }

    /**
     * Adds a string to the in-memory set so future calls to exists() return true for it.
     */
    public function remember(string $candidate): void
    {
        $this->existing[$candidate] = true;
    }

    /**
     * Returns the number of strings currently in the set.
     */
    public function count(): int
    {
        return count($this->existing);
    }
}
