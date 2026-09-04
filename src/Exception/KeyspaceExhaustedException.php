<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

/**
 * Thrown when a length (or every length up to the configured ceiling) has no unused strings left.
 *
 * Exact exhaustion for the permutation strategy; inferred from consecutive collisions for the random strategy.
 */
final class KeyspaceExhaustedException extends UnableToGenerateUniqueStringException
{
    private ?int $exhaustedLength;

    /**
     * @param string   $message Human-readable description of the exhaustion event.
     * @param int|null $length  The specific length that was exhausted, or null for a range exhaustion.
     */
    private function __construct(string $message, ?int $length = null)
    {
        parent::__construct($message);
        $this->exhaustedLength = $length;
    }

    /**
     * Constructs an exception for exact exhaustion of a single length (permutation strategy).
     */
    public static function forLength(int $length, int $capacity): self
    {
        return new self(
            "Keyspace for length {$length} is exhausted (capacity: {$capacity}).",
            $length,
        );
    }

    /**
     * Constructs an exception for inferred exhaustion of a single length (random strategy).
     *
     * Exhaustion is inferred from consecutive collisions and may fire on a merely crowded pool;
     * raising maximumAttemptsPerLength or switching to the permutation strategy avoids false positives.
     */
    public static function inferredForLength(int $length, int $attempts): self
    {
        return new self(
            "Keyspace for length {$length} appears exhausted after {$attempts} consecutive collision(s). "
                . "The pool may be full or extremely crowded. Consider raising maximumAttemptsPerLength "
                . "or switching to PermutationGenerator.",
            $length,
        );
    }

    /**
     * Constructs an exception for when every length in a range has been exhausted.
     */
    public static function forRange(int $startLength, int $ceiling): self
    {
        return new self(
            "Keyspace exhausted for every length from {$startLength} to {$ceiling} (the configured ceiling).",
        );
    }

    /**
     * The exhausted length, or null when a range was exhausted (forRange).
     */
    public function length(): ?int
    {
        return $this->exhaustedLength;
    }
}
