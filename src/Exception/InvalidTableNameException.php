<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use InvalidArgumentException;

/**
 * Thrown when a table or column name fails the identifier validation regex.
 */
final class InvalidTableNameException extends InvalidArgumentException implements StringMintExceptionInterface
{
}
