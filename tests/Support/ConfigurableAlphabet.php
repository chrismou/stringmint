<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use Chrismou\StringMint\Alphabet\AbstractAlphabet;

/**
 * An AbstractAlphabet whose characters and reserved outputs are supplied per instance, for generator tests.
 */
final class ConfigurableAlphabet extends AbstractAlphabet
{
    /**
     * @param list<string> $reserved Candidates isReserved() reports as reserved.
     */
    public function __construct(string $characters, private readonly array $reserved = [])
    {
        parent::__construct($characters);
    }

    public function isReserved(string $candidate): bool
    {
        return in_array($candidate, $this->reserved, true);
    }
}
