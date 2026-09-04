<?php

declare(strict_types=1);

namespace Chrismou\StringMint;

use Chrismou\StringMint\Exception\KeyspaceExhaustedException;

/**
 * Generates unique, non-incremental, URL-safe strings.
 *
 * Both methods default to the centrally configured (constructor-injected) length and accept an
 * optional trailing override. Implementations must never persist the returned string themselves;
 * persistence is the caller's responsibility.
 */
interface UniqueStringGeneratorInterface
{
    /**
     * Generates a unique string of exactly $length characters (default: the configured length).
     *
     * @throws KeyspaceExhaustedException when no unused string of that length remains
     */
    public function generate(?int $length = null): string;

    /**
     * Generates a unique string starting at $length characters (default: the configured length),
     * automatically moving to $length + 1, + 2, ... when a length is exhausted, up to the
     * configured maximum length (or the keyspace ceiling when no maximum is set).
     *
     * @throws KeyspaceExhaustedException when every length up to the ceiling is exhausted
     */
    public function generateWithAutoLengthIncrement(?int $length = null): string;
}
