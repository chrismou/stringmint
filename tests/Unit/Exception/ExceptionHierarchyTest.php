<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Unit\Exception;

use Chrismou\StringMint\Exception\CounterStoreException;
use Chrismou\StringMint\Exception\InvalidAlphabetException;
use Chrismou\StringMint\Exception\InvalidLengthException;
use Chrismou\StringMint\Exception\InvalidTableNameException;
use Chrismou\StringMint\Exception\KeyspaceExhaustedException;
use Chrismou\StringMint\Exception\StringMintExceptionInterface;
use Chrismou\StringMint\Exception\UnableToGenerateUniqueStringException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function testInvalidAlphabetExceptionImplementsStringMintExceptionAndExtendsInvalidArgumentException(): void
    {
        $e = new InvalidAlphabetException('test');
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
    }

    #[Test]
    public function testInvalidLengthExceptionImplementsStringMintExceptionAndExtendsInvalidArgumentException(): void
    {
        $e = new InvalidLengthException('test');
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
    }

    #[Test]
    public function testInvalidTableNameExceptionImplementsStringMintExceptionAndExtendsInvalidArgumentException(): void
    {
        $e = new InvalidTableNameException('test');
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
    }

    #[Test]
    public function testUnableToGenerateUniqueStringExceptionImplementsStringMintExceptionAndExtendsRuntimeException(): void
    {
        $e = new UnableToGenerateUniqueStringException('test');
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    #[Test]
    public function testKeyspaceExhaustedExceptionIsAnUnableToGenerateUniqueStringException(): void
    {
        $e = KeyspaceExhaustedException::forLength(4, 100);
        $this->assertInstanceOf(UnableToGenerateUniqueStringException::class, $e);
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
    }

    #[Test]
    public function testCounterStoreExceptionImplementsStringMintExceptionAndExtendsRuntimeException(): void
    {
        $e = new CounterStoreException('test');
        $this->assertInstanceOf(StringMintExceptionInterface::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    // --- KeyspaceExhaustedException named constructors ---

    #[Test]
    public function testForLengthExposesTheLengthViaLengthMethod(): void
    {
        $e = KeyspaceExhaustedException::forLength(4, 1296);
        $this->assertSame(4, $e->length());
        $this->assertStringContainsString('4', $e->getMessage());
    }

    #[Test]
    public function testInferredForLengthExposesTheLengthViaLengthMethod(): void
    {
        $e = KeyspaceExhaustedException::inferredForLength(3, 10);
        $this->assertSame(3, $e->length());
        // Message should indicate this is an inferred/heuristic exhaustion.
        $message = $e->getMessage();
        $this->assertTrue(str_contains($message, 'appears') || str_contains($message, 'inferred'));
    }

    #[Test]
    public function testForRangeSetsLengthToNull(): void
    {
        $e = KeyspaceExhaustedException::forRange(3, 6);
        $this->assertNull($e->length());
        $this->assertStringContainsString('3', $e->getMessage());
        $this->assertStringContainsString('6', $e->getMessage());
    }
}
