<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Exception;

use Throwable;

/**
 * Marker interface for all exceptions thrown by this package.
 *
 * Consumers may catch this to handle any package exception in one place.
 */
interface StringMintExceptionInterface extends Throwable
{
}
