<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * The symbol set a generator draws from.
 *
 * Implementations MUST return at least two distinct symbols, or two different indices can encode to the
 * same output and uniqueness breaks. Extend AbstractAlphabet, which enforces this; implementing this
 * interface directly bypasses all validation.
 */
interface AlphabetInterface
{
    /**
     * Returns the number of symbols (the numeric base used for encoding).
     */
    public function size(): int;

    /**
     * Returns the symbol at the given zero-based position, which must be in [0, size()).
     */
    public function characterAt(int $position): string;

    /**
     * Returns true when a generated candidate must be skipped and never issued.
     *
     * Called once per candidate inside the generation loop, so keep it cheap and deterministic.
     * A skipped candidate burns its counter index / attempt like an existence collision.
     */
    public function isReserved(string $candidate): bool;
}
