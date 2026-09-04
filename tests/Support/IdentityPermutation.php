<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Chrismou\StringMint\Permutation\KeyspacePermutationInterface;

/**
 * A test double that maps every index to itself (i -> i).
 *
 * Makes the relationship between counter values and encoded strings deterministic, so tests can
 * assert exact exhaustion boundaries without knowing the Feistel output. Intentionally ignores
 * the tweak, which is acceptable only in tests.
 */
final class IdentityPermutation implements KeyspacePermutationInterface
{
    /**
     * Returns $index unchanged.
     */
    public function permute(int $index, int $size, string $tweak): int
    {
        return $index;
    }
}
