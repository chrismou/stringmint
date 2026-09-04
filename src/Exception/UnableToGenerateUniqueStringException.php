<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use RuntimeException;

/**
 * Base exception for "could not produce a unique string" failures.
 *
 * Consumers who only care whether generation succeeded can catch this base class.
 * Consumers who want to react specifically to keyspace exhaustion should catch
 * KeyspaceExhaustedException.
 */
class UnableToGenerateUniqueStringException extends RuntimeException implements StringMintExceptionInterface
{
}
