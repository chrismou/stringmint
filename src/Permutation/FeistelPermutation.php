<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Permutation;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * A keyed, tweaked Feistel-network permutation (FFX-style format-preserving encryption).
 *
 * Maps any index in [0, size) to a unique index in [0, size) using a balanced Feistel network
 * with cycle-walking to support arbitrary (non-power-of-two) keyspace sizes. Requires only
 * hash_hmac (always available from ext-hash, bundled with PHP).
 *
 * The same (secret, size, index, tweak) always produces the same output; different secrets,
 * sizes or tweaks produce independent bijections. Cost: ~8 HMAC calls per string (~10 us total),
 * negligible compared to the counter round-trip.
 */
final readonly class FeistelPermutation implements KeyspacePermutationInterface
{
    /**
     * @throws InvalidArgumentException when the secret is empty or rounds < 3
     */
    public function __construct(
        #[SensitiveParameter] private string $secret,
        private int $rounds = 4,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Feistel secret must not be empty. Use at least 16 random bytes.');
        }

        if ($rounds < 3) {
            throw new InvalidArgumentException("Feistel rounds must be >= 3, got {$rounds}.");
        }
    }

    /**
     * Prevents the HMAC secret from appearing in var_dump() and framework debug panels.
     *
     * @return array{secret: string, rounds: int}
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]', 'rounds' => $this->rounds];
    }

    /**
     * Bijectively maps $index in [0, $size) to another integer in [0, $size).
     *
     * The $tweak (typically the counter name) namespaces the permutation: two different tweaks
     * with the same secret and size produce independent bijections.
     *
     * @throws InvalidArgumentException when $index < 0 or $index >= $size
     */
    public function permute(int $index, int $size, string $tweak): int
    {
        if ($index < 0 || $index >= $size) {
            throw new InvalidArgumentException(
                "Index {$index} must be in [0, {$size}).",
            );
        }

        // A keyspace of one has only one possible output.
        if ($size === 1) {
            return 0;
        }

        // Find the smallest k such that 2^k >= size.
        $bits = 0;
        $power = 1;
        while ($power < $size) {
            $power <<= 1;
            $bits++;
        }

        // Split into two halves (balanced, rounded up for the left half when bits is odd).
        $halfBits = intdiv($bits + 1, 2);
        $mask = (1 << $halfBits) - 1;

        $value = $index;

        // Cycle-walk: keep permuting until the result falls within [0, size).
        // Expected iterations < 4 because domain < 4 * size.
        do {
            $left = $value >> $halfBits;
            $right = $value & $mask;

            for ($round = 0; $round < $this->rounds; $round++) {
                // Round function F: derive a pseudorandom half-width value from the right half,
                // the round number, the keyspace size, and the tweak (counter name).
                // Including size makes each length's permutation independent; including tweak
                // makes each counter's permutation independent even under the same secret.
                // The tweak is the last, variable-length component so preceding fixed-width fields
                // remain unambiguous.
                $hmacInput = pack('J', $right) . pack('N', $round) . pack('J', $size) . $tweak;
                $hmacOutput = hash_hmac('sha256', $hmacInput, $this->secret, true);

                // unpack('J') may return a negative value for large 64-bit values; the mask makes
                // it non-negative since mask < 2^31 <= 2^62.
                /** @var array{1: int} $unpacked */
                $unpacked = unpack('J', substr($hmacOutput, 0, 8));
                $f = $unpacked[1] & $mask;

                [$left, $right] = [$right, $left ^ $f];
            }

            $value = ($left << $halfBits) | $right;
        } while ($value >= $size);

        return $value;
    }
}
