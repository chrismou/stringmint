<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * Letters and digits only (A-Z a-z 0-9), no punctuation. 62 characters.
 */
final class Alphanumeric extends AbstractUrlSafeAlphabet
{
    public const CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct()
    {
        parent::__construct(self::CHARACTERS);
    }
}
