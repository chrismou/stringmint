<?php

declare(strict_types=1);

namespace Chrismou\StringMint;

use Chrismou\StringMint\Exception\InvalidLengthException;

/**
 * Centralised length configuration: a default length and an optional maximum.
 *
 * The maximum doubles as a ceiling for generateWithAutoLengthIncrement() and as a guard against
 * per-call overrides that exceed the destination column width.
 */
final readonly class LengthPolicy
{
    /**
     * @throws InvalidLengthException when defaultLength < 1 or (maximumLength is set and maximumLength < defaultLength)
     */
    public function __construct(
        private int $defaultLength,
        private ?int $maximumLength = null,
    ) {
        if ($defaultLength < 1) {
            throw new InvalidLengthException(
                "Default length must be >= 1, got {$defaultLength}.",
            );
        }

        if ($maximumLength !== null && $maximumLength < $defaultLength) {
            throw new InvalidLengthException(
                "Maximum length ({$maximumLength}) must be >= default length ({$defaultLength}).",
            );
        }
    }

    /**
     * Returns the centrally configured default length.
     */
    public function defaultLength(): int
    {
        return $this->defaultLength;
    }

    /**
     * Returns the configured maximum length, or null when none is set.
     */
    public function maximumLength(): ?int
    {
        return $this->maximumLength;
    }

    /**
     * Validates and returns the effective length for a generation call.
     *
     * Returns the default when $override is null. Rejects $override < 1 and,
     * when a maximum is configured, $override > maximumLength.
     * A large override with no configured maximum is accepted here; the keyspace ceiling
     * (enforced by Keyspace::capacityForLength()) applies instead.
     *
     * @throws InvalidLengthException when the override is out of range
     */
    public function resolveLength(?int $override): int
    {
        if ($override === null) {
            return $this->defaultLength;
        }

        if ($override < 1) {
            throw new InvalidLengthException(
                "Length override must be >= 1, got {$override}.",
            );
        }

        if ($this->maximumLength !== null && $override > $this->maximumLength) {
            throw new InvalidLengthException(
                "Length override {$override} exceeds the configured maximum length {$this->maximumLength}.",
            );
        }

        return $override;
    }

    /**
     * Returns the ceiling for generateWithAutoLengthIncrement(): the configured maximum, or the
     * keyspace's largest supported length when no maximum is configured.
     *
     * The keyspace is passed as an argument (rather than injected) so LengthPolicy stays a
     * plain two-integer value object that containers can construct without knowing the alphabet.
     */
    public function escalationCeiling(Keyspace $keyspace): int
    {
        return $this->maximumLength ?? $keyspace->largestSupportedLength();
    }
}
