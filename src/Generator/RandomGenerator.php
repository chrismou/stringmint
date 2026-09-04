<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Generator;

use Chrismou\StringMint\Alphabet\AlphabetInterface;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Existence\ExistenceCheckerInterface;
use Chrismou\StringMint\Keyspace;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\UniqueStringGeneratorInterface;
use InvalidArgumentException;
use Random\Randomizer;

/**
 * Generates probabilistically unique strings via CSPRNG with existence-check retries.
 *
 * Does not require a counter or persistent state. Exhaustion detection is heuristic:
 * KeyspaceExhaustedException::inferredForLength() fires after maximumAttemptsPerLength consecutive
 * collisions, which may happen on a merely crowded pool. Raising maximumAttemptsPerLength,
 * using generateWithAutoLengthIncrement(), or switching to PermutationGenerator are the mitigations.
 *
 * The same keyspace ceiling (Keyspace::MAXIMUM_CAPACITY = 2^62) applies to this strategy for
 * consistency; a pool of 2^62 strings is a practical bound that never needs escalation past it.
 */
final readonly class RandomGenerator implements UniqueStringGeneratorInterface
{
    private Keyspace $keyspace;
    private Randomizer $resolvedRandomizer;

    /**
     * @throws InvalidArgumentException when maximumAttemptsPerLength < 1
     * @throws InvalidLengthException when the default or maximum length exceeds the keyspace ceiling
     */
    public function __construct(
        private LengthPolicy $lengthPolicy,
        private ExistenceCheckerInterface $existenceChecker,
        private AlphabetInterface $alphabet = new UrlSafe(),
        private int $maximumAttemptsPerLength = 10,
        ?Randomizer $randomizer = null,
    ) {
        if ($maximumAttemptsPerLength < 1) {
            throw new InvalidArgumentException(
                "maximumAttemptsPerLength must be >= 1, got {$maximumAttemptsPerLength}.",
            );
        }

        $this->keyspace = new Keyspace($alphabet);

        // Eagerly validate that the configured lengths are within the keyspace ceiling.
        $this->keyspace->capacityForLength($lengthPolicy->defaultLength());
        if ($lengthPolicy->maximumLength() !== null) {
            $this->keyspace->capacityForLength($lengthPolicy->maximumLength());
        }

        $this->resolvedRandomizer = $randomizer ?? new Randomizer();
    }

    /**
     * Generates a unique string of exactly $length characters (default: the configured length).
     *
     * Retries up to maximumAttemptsPerLength times if a candidate already exists. Never escalates
     * to a longer length.
     *
     * @throws KeyspaceExhaustedException (inferred) after maximumAttemptsPerLength consecutive collisions
     * @throws InvalidLengthException for overrides beyond the keyspace ceiling or configured maximum
     */
    public function generate(?int $length = null): string
    {
        $resolvedLength = $this->lengthPolicy->resolveLength($length);

        // Validate against the keyspace ceiling (may throw InvalidLengthException).
        $this->keyspace->capacityForLength($resolvedLength);

        return $this->tryAtLength($resolvedLength);
    }

    /**
     * Generates a unique string starting at $length characters, escalating to $length + 1, +2, ...
     * when maximumAttemptsPerLength consecutive collisions occur.
     *
     * @throws KeyspaceExhaustedException when every length up to the ceiling is exhausted (inferred)
     * @throws InvalidLengthException for overrides beyond the keyspace ceiling or configured maximum
     */
    public function generateWithAutoLengthIncrement(?int $length = null): string
    {
        $startLength = $this->lengthPolicy->resolveLength($length);
        $ceiling = $this->lengthPolicy->escalationCeiling($this->keyspace);
        $currentLength = $startLength;

        while ($currentLength <= $ceiling) {
            // Validate ceiling at each step.
            $this->keyspace->capacityForLength($currentLength);

            try {
                return $this->tryAtLength($currentLength);
            } catch (KeyspaceExhaustedException) {
                $currentLength++;
            }
        }

        throw KeyspaceExhaustedException::forRange($startLength, $ceiling);
    }

    /**
     * Attempts to generate a unique string of $length characters, retrying up to maximumAttemptsPerLength times.
     *
     * Returns the first candidate that is neither reserved by the alphabet nor reported by the existence checker.
     * Uses Randomizer::getInt() per character position (not getBytesFromString, which is PHP 8.3+).
     *
     * @throws KeyspaceExhaustedException (inferred) after maximumAttemptsPerLength consecutive collisions
     */
    private function tryAtLength(int $length): string
    {
        $alphabetSize = $this->alphabet->size();

        for ($attempt = 0; $attempt < $this->maximumAttemptsPerLength; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < $length; $i++) {
                $candidate .= $this->alphabet->characterAt(
                    $this->resolvedRandomizer->getInt(0, $alphabetSize - 1),
                );
            }

            if ($this->alphabet->isReserved($candidate)) {
                continue;
            }

            if (!$this->existenceChecker->exists($candidate)) {
                return $candidate;
            }
        }

        throw KeyspaceExhaustedException::inferredForLength($length, $this->maximumAttemptsPerLength);
    }
}
