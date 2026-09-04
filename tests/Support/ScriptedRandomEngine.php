<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Random\Engine;
use UnderflowException;

/**
 * A test double Random\Engine that returns queued byte strings on successive generate() calls.
 *
 * Allows tests to prove the retry path in RandomGenerator: pre-populate the queue with bytes
 * that produce a known first candidate (which the existence checker will reject), then the
 * second candidate that will be accepted.
 *
 * Queue entries must be valid Random\Engine byte strings (1-8 bytes on 64-bit PHP).
 * Throws UnderflowException when the queue is exhausted.
 */
final class ScriptedRandomEngine implements Engine
{
    /** @var list<string> */
    private array $queue;

    /**
     * @param list<string> $queue Byte strings to return in order, one per generate() call.
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Returns the next byte string from the queue.
     *
     * @throws UnderflowException when all queued values have been consumed
     */
    public function generate(): string
    {
        if (empty($this->queue)) {
            throw new UnderflowException('ScriptedRandomEngine queue is exhausted.');
        }

        return array_shift($this->queue);
    }
}
