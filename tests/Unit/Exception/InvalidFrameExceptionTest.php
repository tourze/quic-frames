<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

class InvalidFrameExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidFrameException('Test message');
        
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidFrameException('Test message', 123);
        
        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous exception');
        $exception = new InvalidFrameException('Test message', 0, $previous);
        
        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}