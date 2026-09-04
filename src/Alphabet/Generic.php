<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Alphabet;

/**
 * An alphabet built from any characters you pass in. One symbol per UTF-8 character; reserves nothing.
 *
 * Not checked for URL safety: use a preset such as UrlSafe, or extend AbstractUrlSafeAlphabet, when the output
 * must be safe in a URL path segment.
 */
final class Generic extends AbstractAlphabet
{
}
