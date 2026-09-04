<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Chrismou\StringMint\Alphabet\AlphabetInterface;

/**
 * Shared helper for alphabet test classes.
 */
trait ReadsAlphabetSymbols
{
    /**
     * Concatenates every symbol in the alphabet into a single string.
     */
    private static function symbolsOf(AlphabetInterface $alphabet): string
    {
        $result = '';
        for ($i = 0; $i < $alphabet->size(); $i++) {
            $result .= $alphabet->characterAt($i);
        }

        return $result;
    }
}
