<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Permutation;

use InvalidArgumentException;

/**
 * A bijective mapping over a keyspace of size $size.
 *
 * Implementations MUST produce independent bijections for different tweaks: the same secret,
 * size and index with two different tweaks must map to different outputs. This independence
 * guarantee is what makes counters sharing a secret and length yield unrelated sequences.
 *
 * An implementation that ignores the tweak (such as IdentityPermutation) silently reintroduces
 * the shared-sequence problem across counter names - acceptable only in tests.
 */
interface KeyspacePermutationInterface
{
    /**
     * Bijectively maps $index in [0, $size) to another integer in [0, $size).
     *
     * $tweak namespaces the mapping: the same (secret, index, size) triple with two different tweaks
     * must produce different results. PermutationGenerator passes the counter name as the tweak.
     *
     * @throws InvalidArgumentException when $index < 0 or $index >= $size
     */
    public function permute(int $index, int $size, string $tweak): int;
}
