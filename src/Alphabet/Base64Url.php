<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * The base64url character set (A-Z a-z 0-9 - _), RFC 4648 section 5. 64 characters.
 */
final class Base64Url extends AbstractUrlSafeAlphabet
{
    public const CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    public function __construct()
    {
        parent::__construct(self::CHARACTERS);
    }
}
