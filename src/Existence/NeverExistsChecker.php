<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Existence;

/**
 * Null-object ExistenceCheckerInterface that always returns false.
 *
 * Used as the default for PermutationGenerator when no external existence check is needed.
 * The permutation strategy guarantees uniqueness by construction, so a checker is only required
 * when legacy data outside the generator may collide.
 */
final class NeverExistsChecker implements ExistenceCheckerInterface
{
    /**
     * Always returns false (nothing is considered to already exist).
     */
    public function exists(string $candidate): bool
    {
        return false;
    }
}
