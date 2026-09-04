<?php

declare(strict_types=1);

namespace Chrismou\StringMint;

use Chrismou\StringMint\Alphabet\AlphabetInterface;
use Chrismou\StringMint\Exception\InvalidLengthException;
use RuntimeException;

/**
 * Per-length capacity maths for a given alphabet.
 *
 * This is the single shared ceiling for both generator strategies: a length whose capacity exceeds
 * MAXIMUM_CAPACITY (2^62) is rejected regardless of which strategy is used.
 */
final readonly class Keyspace
{
    /**
     * The maximum allowed keyspace capacity (2^62).
     *
     * Keeps Feistel network bit-widths within 62 bits and avoids integer overflow.
     * Equals 4,611,686,018,427,387,904 - more than enough for any practical pool.
     */
    public const MAXIMUM_CAPACITY = 2 ** 62;

    /**
     * Constructs a Keyspace for the given alphabet.
     *
     * @throws RuntimeException when running on 32-bit PHP (PHP_INT_SIZE !== 8)
     */
    public function __construct(private AlphabetInterface $alphabet)
    {
        // This package requires 64-bit PHP. On 32-bit PHP, 2**62 would silently become a float.
        if (PHP_INT_SIZE !== 8) {
            throw new RuntimeException('chrismou/stringmint requires 64-bit PHP (PHP_INT_SIZE must be 8).');
        }
    }

    /**
     * Returns |alphabet|^length (the number of distinct strings of exactly $length characters).
     *
     * @throws InvalidLengthException when $length < 1 or the capacity would exceed MAXIMUM_CAPACITY
     */
    public function capacityForLength(int $length): int
    {
        if ($length < 1) {
            throw new InvalidLengthException(
                "Length must be >= 1, got {$length}.",
            );
        }

        $base = $this->alphabet->size();
        $capacity = 1;

        for ($i = 0; $i < $length; $i++) {
            if ($capacity > intdiv(self::MAXIMUM_CAPACITY, $base)) {
                $largest = $this->largestSupportedLength();
                throw new InvalidLengthException(
                    "Length {$length} with alphabet size {$base} exceeds the maximum capacity (2^62). "
                        . "Largest supported length: {$largest}.",
                );
            }
            $capacity *= $base;
        }

        return $capacity;
    }

    /**
     * Returns true when capacityForLength($length) would succeed (no exception).
     */
    public function supportsLength(int $length): bool
    {
        if ($length < 1) {
            return false;
        }

        $base = $this->alphabet->size();
        $capacity = 1;

        for ($i = 0; $i < $length; $i++) {
            if ($capacity > intdiv(self::MAXIMUM_CAPACITY, $base)) {
                return false;
            }
            $capacity *= $base;
        }

        return true;
    }

    /**
     * Returns the largest length L such that |alphabet|^L <= MAXIMUM_CAPACITY.
     *
     * For the default 66-character alphabet this is 10; for a 2-character alphabet it is 62.
     */
    public function largestSupportedLength(): int
    {
        $base = $this->alphabet->size();
        $capacity = 1;
        $length = 0;

        while (true) {
            if ($capacity > intdiv(self::MAXIMUM_CAPACITY, $base)) {
                break;
            }
            $capacity *= $base;
            $length++;
        }

        return $length;
    }
}
