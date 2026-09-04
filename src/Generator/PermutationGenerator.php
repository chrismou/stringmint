<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Generator;

use Chrismou\StringMint\Alphabet\AlphabetInterface;
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Counter\CounterStoreInterface;
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Exception\UnableToGenerateUniqueStringException;
use Chrismou\StringMint\Existence\ExistenceCheckerInterface;
use Chrismou\StringMint\Existence\NeverExistsChecker;
use Chrismou\StringMint\Keyspace;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;
use Chrismou\StringMint\Permutation\KeyspacePermutationInterface;
use Chrismou\StringMint\UniqueStringGeneratorInterface;
use InvalidArgumentException;
use PDO;
use SensitiveParameter;

/**
 * Generates guaranteed-unique strings via a keyed Feistel permutation and a PDO-backed counter.
 *
 * Zero retries in the normal case: every counter value maps to exactly one string of that length.
 * Retries occur only when an ExistenceCheckerInterface reports a collision with strings from outside the
 * generator (e.g. legacy data).
 *
 * The counter name is passed as the permutation tweak on every call, so two generators sharing
 * a secret and length but using different counter names produce entirely unrelated sequences.
 *
 * Do not change the secret, alphabet or counter name after strings have been issued: doing so
 * silently changes the bijection, potentially causing collisions with previously issued strings.
 * Changing only isReserved() is safe. Changing LengthPolicy is safe at any time.
 */
final readonly class PermutationGenerator implements UniqueStringGeneratorInterface
{
    private Keyspace $keyspace;

    /**
     * @throws InvalidArgumentException when maximumAttempts < 1
     * @throws InvalidLengthException when the default or maximum length exceeds the keyspace ceiling
     */
    public function __construct(
        private LengthPolicy $lengthPolicy,
        private CounterStoreInterface $counterStore,
        private KeyspacePermutationInterface $permutation,
        private AlphabetInterface $alphabet = new UrlSafe(),
        private ExistenceCheckerInterface $existenceChecker = new NeverExistsChecker(),
        private int $maximumAttempts = 100,
    ) {
        if ($maximumAttempts < 1) {
            throw new InvalidArgumentException(
                "maximumAttempts must be >= 1, got {$maximumAttempts}.",
            );
        }

        $this->keyspace = new Keyspace($alphabet);

        // Eagerly validate that the configured lengths are within the keyspace ceiling.
        $this->keyspace->capacityForLength($lengthPolicy->defaultLength());
        if ($lengthPolicy->maximumLength() !== null) {
            $this->keyspace->capacityForLength($lengthPolicy->maximumLength());
        }
    }

    /**
     * Builds a PDO-backed PermutationGenerator with no existence checker. Uses the URL-safe default alphabet
     * unless one is given.
     *
     * This is the one-liner for the common case. The explicit constructor remains the DI-friendly
     * path for anything custom. Note: the counter table must already be installed before calling
     * generate(); use CounterTableInstaller::install() as a separate step.
     *
     * @throws InvalidLengthException when length or maximumLength is invalid
     */
    public static function create(
        PDO $pdo,
        #[SensitiveParameter] string $secret,
        int $length,
        string $counterName = 'default',
        ?int $maximumLength = null,
        ?AlphabetInterface $alphabet = null,
        string $tableName = PdoCounterStore::DEFAULT_TABLE,
    ): self {
        $lengthPolicy = new LengthPolicy($length, $maximumLength);
        $counterStore = new PdoCounterStore($pdo, $counterName, $tableName);
        $permutation = new FeistelPermutation($secret);

        return new self($lengthPolicy, $counterStore, $permutation, $alphabet ?? new UrlSafe());
    }

    /**
     * Generates a unique string of exactly $length characters (default: the configured length).
     *
     * @throws KeyspaceExhaustedException when no unused string of that length remains
     * @throws UnableToGenerateUniqueStringException when maximumAttempts collisions occur
     * @throws InvalidLengthException for overrides beyond the keyspace ceiling or configured maximum
     */
    public function generate(?int $length = null): string
    {
        $resolvedLength = $this->lengthPolicy->resolveLength($length);
        $capacity = $this->keyspace->capacityForLength($resolvedLength);

        return $this->generateAtLength($resolvedLength, $capacity);
    }

    /**
     * Generates a unique string starting at $length characters, escalating to $length + 1, +2, ...
     * when each length is exhausted, up to the configured ceiling.
     *
     * @throws KeyspaceExhaustedException when every length up to the ceiling is exhausted
     * @throws UnableToGenerateUniqueStringException when maximumAttempts collisions occur at any length
     * @throws InvalidLengthException for overrides beyond the keyspace ceiling or configured maximum
     */
    public function generateWithAutoLengthIncrement(?int $length = null): string
    {
        $startLength = $this->lengthPolicy->resolveLength($length);
        $ceiling = $this->lengthPolicy->escalationCeiling($this->keyspace);
        $currentLength = $startLength;

        while ($currentLength <= $ceiling) {
            $capacity = $this->keyspace->capacityForLength($currentLength);

            try {
                return $this->generateAtLength($currentLength, $capacity);
            } catch (KeyspaceExhaustedException) {
                $currentLength++;
            }
        }

        throw KeyspaceExhaustedException::forRange($startLength, $ceiling);
    }

    /**
     * Generates a single candidate at the given length, retrying up to maximumAttempts times on collisions.
     *
     * @throws KeyspaceExhaustedException when the counter has reached or exceeded capacity
     * @throws UnableToGenerateUniqueStringException when maximumAttempts candidates all collide
     */
    private function generateAtLength(int $length, int $capacity): string
    {
        for ($attempt = 0; $attempt < $this->maximumAttempts; $attempt++) {
            $index = $this->counterStore->next($length);

            if ($index >= $capacity) {
                throw KeyspaceExhaustedException::forLength($length, $capacity);
            }

            // Pass the counter name as the tweak so counters sharing a secret and length still
            // produce independent bijections.
            $permuted = $this->permutation->permute($index, $capacity, $this->counterStore->name());
            $candidate = $this->encodeIndex($permuted, $length);

            if ($this->alphabet->isReserved($candidate)) {
                continue;
            }

            if ($this->existenceChecker->exists($candidate)) {
                continue;
            }

            return $candidate;
        }

        throw new UnableToGenerateUniqueStringException(
            "Could not generate a unique string of length {$length} after {$this->maximumAttempts} attempt(s). "
                . 'All candidates collided with existing data or were reserved by the alphabet.',
        );
    }

    /**
     * Encodes $index as exactly $length symbols in the alphabet's base, most-significant symbol first,
     * left-padded with characterAt(0).
     *
     * The permutation always yields an index below the capacity Keyspace validated, so the range checks here
     * are defensive; they exist so a misbehaving KeyspacePermutationInterface fails loudly rather than corrupting output.
     *
     * @throws InvalidArgumentException when $length < 1, $index < 0, or $index >= size^length
     */
    private function encodeIndex(int $index, int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException("Length must be >= 1, got {$length}.");
        }

        if ($index < 0) {
            throw new InvalidArgumentException("Index must be >= 0, got {$index}.");
        }

        $base = $this->alphabet->size();

        // Compute capacity = base^length, detecting overflow before it occurs.
        $capacity = 1;
        for ($i = 0; $i < $length; $i++) {
            if ($capacity > intdiv(PHP_INT_MAX, $base)) {
                throw new InvalidArgumentException(
                    "Index {$index} out of range for length {$length} (capacity exceeds PHP_INT_MAX).",
                );
            }
            $capacity *= $base;
        }

        if ($index >= $capacity) {
            throw new InvalidArgumentException(
                "Index {$index} out of range [0, {$capacity}) for length {$length}.",
            );
        }

        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result = $this->alphabet->characterAt($index % $base) . $result;
            $index = intdiv($index, $base);
        }

        return $result;
    }
}
