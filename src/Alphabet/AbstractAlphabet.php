<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

use Chrismou\StringMint\Exception\InvalidAlphabetException;
use InvalidArgumentException;

/**
 * Base class for alphabets: splits a string into one symbol per UTF-8 character, validates the set once,
 * and serves it by position.
 *
 * Multibyte characters (accented letters, Greek, emoji) are single symbols. Override isReserved() to
 * exclude specific outputs.
 */
abstract class AbstractAlphabet implements AlphabetInterface
{
    /** @var list<string> */
    private readonly array $symbols;

    private readonly int $size;

    /**
     * @throws InvalidAlphabetException when the string is not valid UTF-8, has fewer than two characters,
     *                                  or contains a duplicate character
     */
    public function __construct(string $characters)
    {
        $this->symbols = self::splitCharacters($characters);
        self::validateSymbols($this->symbols);
        $this->size = count($this->symbols);
    }

    /**
     * Returns the number of symbols.
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Returns the symbol at the given zero-based position.
     *
     * @throws InvalidArgumentException when $position is outside [0, size())
     */
    public function characterAt(int $position): string
    {
        if ($position < 0 || $position >= $this->size) {
            throw new InvalidArgumentException("Position {$position} is out of range [0, {$this->size}).");
        }

        return $this->symbols[$position];
    }

    /**
     * Reserves nothing by default; subclasses override this to exclude specific outputs.
     */
    public function isReserved(string $candidate): bool
    {
        return false;
    }

    /**
     * Splits a string into one symbol per UTF-8 character.
     *
     * @return list<string>
     *
     * @throws InvalidAlphabetException when the string is not valid UTF-8
     */
    private static function splitCharacters(string $characters): array
    {
        $split = preg_split('//u', $characters, -1, PREG_SPLIT_NO_EMPTY);
        if ($split === false) {
            throw new InvalidAlphabetException('The alphabet string is not valid UTF-8.');
        }

        return $split;
    }

    /**
     * Validates the symbol list, throwing InvalidAlphabetException on any violation.
     *
     * @param list<string> $symbols
     */
    private static function validateSymbols(array $symbols): void
    {
        if (count($symbols) < 2) {
            throw new InvalidAlphabetException('An alphabet must contain at least 2 characters.');
        }

        if (count(array_unique($symbols)) !== count($symbols)) {
            throw new InvalidAlphabetException('The alphabet contains duplicate characters.');
        }
    }
}
