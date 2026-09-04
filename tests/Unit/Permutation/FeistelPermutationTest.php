<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Permutation;

use Chrismou\StringMint\Counter\InMemoryCounterStore;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeistelPermutationTest extends TestCase
{
    // --- bijection proofs ---

    /**
     * @return array<string, array{int}>
     */
    public static function bijectionSizes(): array
    {
        return [
            'size 1' => [1],
            'size 2' => [2],
            'size 7' => [7],
            'size 8' => [8],
            'size 27' => [27],
            'size 100' => [100],
            'size 1000' => [1000],
            'size 4096' => [4096],
        ];
    }

    #[Test]
    #[DataProvider('bijectionSizes')]
    public function testIsABijectionOnTheFullRange(int $size): void
    {
        $permutation = new FeistelPermutation('test-secret-16by');
        $outputs = [];
        for ($i = 0; $i < $size; $i++) {
            $out = $permutation->permute($i, $size, '');
            $this->assertGreaterThanOrEqual(0, $out);
            $this->assertLessThan($size, $out);
            $outputs[] = $out;
        }
        $this->assertSame($size, count(array_unique($outputs)));
    }

    #[Test]
    public function testIsABijectionForLargeSize17850625Sampled(): void
    {
        $size = 17850625;
        $permutation = new FeistelPermutation('test-secret-16by');
        $outputs = [];
        $step = intdiv($size, 1000);
        for ($i = 0; $i < 1000; $i++) {
            $index = $i * $step;
            $out = $permutation->permute($index, $size, '');
            $this->assertGreaterThanOrEqual(0, $out);
            $this->assertLessThan($size, $out);
            $outputs[] = $out;
        }
        $this->assertSame(1000, count(array_unique($outputs)));
    }

    // --- determinism ---

    #[Test]
    public function testProducesTheSameOutputForTheSameSecretSizeAndIndex(): void
    {
        $p1 = new FeistelPermutation('deterministic-key');
        $p2 = new FeistelPermutation('deterministic-key');
        $this->assertSame($p1->permute(42, 100, ''), $p2->permute(42, 100, ''));
    }

    #[Test]
    public function testProducesDifferentOutputsForDifferentSecrets(): void
    {
        $p1 = new FeistelPermutation('secret-one-xxxxx');
        $p2 = new FeistelPermutation('secret-two-xxxxx');
        $collisions = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($p1->permute($i, 1000, '') === $p2->permute($i, 1000, '')) {
                $collisions++;
            }
        }
        // Two independent permutations should only rarely coincide.
        $this->assertLessThan(10, $collisions);
    }

    #[Test]
    public function testProducesDifferentOutputsForDifferentSizesWithTheSameSecret(): void
    {
        $p = new FeistelPermutation('shared-secret-16');
        $out100 = $p->permute(5, 100, '');
        $out1000 = $p->permute(5, 1000, '');
        $this->assertLessThan(100, $out100);
        $this->assertLessThan(1000, $out1000);
    }

    #[Test]
    public function testDoesNotProduceConsecutiveOutputsForConsecutiveInputs(): void
    {
        $p = new FeistelPermutation('non-incremental-k');
        $consecutiveCount = 0;
        for ($i = 0; $i < 999; $i++) {
            $a = $p->permute($i, 1000, '');
            $b = $p->permute($i + 1, 1000, '');
            if ($b - $a === 1) {
                $consecutiveCount++;
            }
        }
        // Fewer than 5% of pairs should be consecutive.
        $this->assertLessThan(50, $consecutiveCount);
    }

    // --- tweak (A1) ---

    #[Test]
    public function testDifferentTweaksProduceDifferentOutputsStatistically(): void
    {
        $p = new FeistelPermutation('tweak-test-secret');
        $size = 4096;
        $coincidences = 0;
        for ($i = 0; $i < 1000; $i++) {
            $out1 = $p->permute($i, $size, 'links');
            $out2 = $p->permute($i, $size, 'invites');
            if ($out1 === $out2) {
                $coincidences++;
            }
        }
        // Expected coincidences: ~1000/4096 ≈ 0.24; well under 10.
        $this->assertLessThan(10, $coincidences);
    }

    #[Test]
    public function testEachTweakIsItsOwnFullBijectionOnTheRange(): void
    {
        $p = new FeistelPermutation('tweak-bijection-k');

        foreach ([7, 100, 1000] as $size) {
            foreach (['links', 'invites'] as $tweak) {
                $outputs = [];
                for ($i = 0; $i < $size; $i++) {
                    $out = $p->permute($i, $size, $tweak);
                    $this->assertGreaterThanOrEqual(0, $out);
                    $this->assertLessThan($size, $out);
                    $outputs[] = $out;
                }
                $this->assertSame($size, count(array_unique($outputs)));
            }
        }
    }

    #[Test]
    public function testTheSameTweakIsDeterministicAcrossInstancesWithTheSameSecret(): void
    {
        $p1 = new FeistelPermutation('same-secret-12345');
        $p2 = new FeistelPermutation('same-secret-12345');
        $this->assertSame($p1->permute(10, 100, 'links'), $p2->permute(10, 100, 'links'));
    }

    #[Test]
    public function testAcceptsAnEmptyTweak(): void
    {
        $p = new FeistelPermutation('empty-tweak-secrt');
        $out = $p->permute(5, 100, '');
        $this->assertGreaterThanOrEqual(0, $out);
        $this->assertLessThan(100, $out);
    }

    // --- __debugInfo (secret redaction) ---

    #[Test]
    public function testVarDumpOutputDoesNotContainTheSecret(): void
    {
        $secret = 'super-secret-key!';
        $p = new FeistelPermutation($secret);

        ob_start();
        var_dump($p);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString($secret, $output);
        $this->assertStringContainsString('[redacted]', $output);
    }

    #[Test]
    public function testVarDumpOfPermutationGeneratorWrappingThePermutationDoesNotContainTheSecret(): void
    {
        $secret = 'super-secret-key!';
        $generator = new PermutationGenerator(
            new LengthPolicy(4),
            new InMemoryCounterStore(),
            new FeistelPermutation($secret),
        );

        ob_start();
        var_dump($generator);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString($secret, $output);
    }

    // --- validation ---

    #[Test]
    public function testThrowsForAnEmptySecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FeistelPermutation('');
    }

    #[Test]
    public function testThrowsForRoundsLessThan3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FeistelPermutation('valid-secret-1234', 2);
    }

    #[Test]
    public function testThrowsForIndexLessThanZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FeistelPermutation('valid-secret-1234'))->permute(-1, 100, '');
    }

    #[Test]
    public function testThrowsForIndexAtOrAboveSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FeistelPermutation('valid-secret-1234'))->permute(100, 100, '');
    }
}
