<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use InvalidArgumentException;

/**
 * Thrown when an alphabet is constructed with an invalid character set.
 */
final class InvalidAlphabetException extends InvalidArgumentException implements StringMintExceptionInterface
{
}
