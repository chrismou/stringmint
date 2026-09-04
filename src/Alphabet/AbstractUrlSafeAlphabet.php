<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

use Chrismou\StringMint\Exception\InvalidAlphabetException;

/**
 * Base class for alphabets restricted to the RFC 3986 unreserved set (A-Z a-z 0-9 - . _ ~).
 *
 * Validates every character against the unreserved set on top of AbstractAlphabet's checks, and reserves any
 * output ending in ".": that covers "." and "..", which every URL parser normalises away, and strings like
 * "abc.", where the trailing dot is dropped by chat clients and email linkifiers. Nothing else is reserved.
 *
 * Extend this class to build a URL-safe alphabet from your own subset of the unreserved characters, or to
 * exclude more outputs by overriding isReserved() (call parent::isReserved() to keep the trailing-dot guard).
 */
abstract class AbstractUrlSafeAlphabet extends AbstractAlphabet
{
    /** The full RFC 3986 unreserved character set (66 characters). */
    public const RFC3986_UNRESERVED = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';

    /**
     * @throws InvalidAlphabetException when a character is outside the unreserved set, or the base validation fails
     */
    public function __construct(string $characters)
    {
        parent::__construct($characters);
        $this->assertUnreserved();
    }

    /**
     * Returns true for any candidate ending in ".", which covers the "." and ".." path-segment hazards and
     * strings that auto-linkers would truncate.
     *
     * Subclasses that extend this method should call parent::isReserved() to preserve the trailing-dot guard.
     */
    public function isReserved(string $candidate): bool
    {
        return str_ends_with($candidate, '.');
    }

    /**
     * Rejects any symbol that is not a single byte from the RFC 3986 unreserved set.
     */
    private function assertUnreserved(): void
    {
        for ($position = 0; $position < $this->size(); $position++) {
            $symbol = $this->characterAt($position);
            if (strlen($symbol) !== 1 || !str_contains(self::RFC3986_UNRESERVED, $symbol)) {
                throw new InvalidAlphabetException(
                    "Character '{$symbol}' is not in the RFC 3986 unreserved set.",
                );
            }
        }
    }
}
