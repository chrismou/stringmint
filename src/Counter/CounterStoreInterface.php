<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter;

/**
 * Provides atomic, per-length counters for PermutationGenerator.
 *
 * Each length is an independent, monotonically increasing sequence. Consecutive calls for the
 * same length return strictly increasing values starting at 0.
 *
 * name() is part of the uniqueness contract (not merely a label): PermutationGenerator passes it
 * as the permutation tweak, so two stores with the same name and secret produce identical string
 * sequences. name() MUST be constant for the store's lifetime and MUST be distinct per counter
 * namespace.
 */
interface CounterStoreInterface
{
    /**
     * Atomically reserves and returns the next unused zero-based index for strings of $length characters.
     *
     * Each length is an independent sequence; consecutive calls for the same length return strictly
     * increasing values starting at 0.
     */
    public function next(int $length): int;

    /**
     * Returns the counter namespace this store serves (e.g. "links", "invites").
     *
     * Used by PermutationGenerator as the permutation tweak. Must be stable for the store's lifetime.
     */
    public function name(): string;
}
