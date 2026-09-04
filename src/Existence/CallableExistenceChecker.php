<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Existence;

use Closure;

/**
 * An ExistenceCheckerInterface backed by a user-supplied closure.
 *
 * Useful for quick wiring without implementing a dedicated class, and for blocklists:
 * return true for banned words or reserved slugs in addition to existing database rows.
 */
final readonly class CallableExistenceChecker implements ExistenceCheckerInterface
{
    /** @param Closure(string): bool $callback */
    public function __construct(private Closure $callback)
    {
    }

    /**
     * Delegates to the injected callback.
     */
    public function exists(string $candidate): bool
    {
        return ($this->callback)($candidate);
    }
}
