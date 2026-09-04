<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use RuntimeException;

/**
 * Thrown when a PDO-backed counter store encounters a database error.
 *
 * The original PDOException (when available) is set as the previous exception.
 */
final class CounterStoreException extends RuntimeException implements StringMintExceptionInterface
{
}
