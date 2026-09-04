<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * The full RFC 3986 unreserved set (A-Z a-z 0-9 - . _ ~), 66 characters; the default alphabet for both generators.
 */
final class UrlSafe extends AbstractUrlSafeAlphabet
{
    public const CHARACTERS = self::RFC3986_UNRESERVED;

    public function __construct()
    {
        parent::__construct(self::CHARACTERS);
    }
}
