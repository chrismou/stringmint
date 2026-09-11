<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use RuntimeException;

/**
 * Thrown when a PDO-backed existence checker cannot run its query.
 *
 * The check fails closed: a database error is never reported as "does not exist", because that
 * would let the generator issue a string that may already be in use. The original PDOException
 * (when available) is set as the previous exception.
 */
final class ExistenceCheckException extends RuntimeException implements StringMintExceptionInterface
{
}
