<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * Lowercase letters and digits (a-z 0-9); safe for case-insensitive database collations. 36 characters.
 */
final class LowercaseAlphanumeric extends AbstractUrlSafeAlphabet
{
    public const CHARACTERS = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct()
    {
        parent::__construct(self::CHARACTERS);
    }
}
