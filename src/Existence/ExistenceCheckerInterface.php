<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Existence;

/**
 * Determines whether a candidate string already exists in the application's store.
 *
 * Implementations are called after each candidate is generated. A return of true causes the
 * generator to discard the candidate and try another.
 */
interface ExistenceCheckerInterface
{
    /**
     * Returns true when $candidate is already in use and should not be issued again.
     */
    public function exists(string $candidate): bool;
}
