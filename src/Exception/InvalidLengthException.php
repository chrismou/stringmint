<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use InvalidArgumentException;

/**
 * Thrown for any invalid-length condition: policy construction, per-call overrides,
 * or lengths whose capacity exceeds Keyspace::MAXIMUM_CAPACITY.
 */
final class InvalidLengthException extends InvalidArgumentException implements StringMintExceptionInterface
{
}
