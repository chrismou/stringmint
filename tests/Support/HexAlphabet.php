<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Chrismou\StringMint\Alphabet\AbstractAlphabet;

/**
 * A 16-symbol hex alphabet (0-9 a-f), matching the README custom-alphabet example.
 */
final class HexAlphabet extends AbstractAlphabet
{
    public function __construct()
    {
        parent::__construct('0123456789abcdef');
    }
}
