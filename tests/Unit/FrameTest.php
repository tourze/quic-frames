<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\Frame;

class FrameTest extends TestCase
{
    private Frame $frame;

    protected function setUp(): void
    {
        $this->frame = new class() extends Frame {
            public function getType(): FrameType
            {
                return FrameType::STREAM;
            }

            public function encode(): string
            {
                return 'test-data';
            }

            public static function decode(string $data, int $offset = 0): array
            {
                return [new static(), strlen($data)];
            }

            public function validate(): bool
            {
                return true;
            }
        };
    }

    public function testGetPriorityForAckFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::ACK; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(1, $frame->getPriority());
    }

    public function testGetPriorityForAckEcnFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::ACK_ECN; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(1, $frame->getPriority());
    }

    public function testGetPriorityForConnectionCloseFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::CONNECTION_CLOSE; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(2, $frame->getPriority());
    }

    public function testGetPriorityForConnectionCloseAppFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::CONNECTION_CLOSE_APP; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(2, $frame->getPriority());
    }

    public function testGetPriorityForCryptoFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::CRYPTO; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(3, $frame->getPriority());
    }

    public function testGetPriorityForHandshakeDoneFrame(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::HANDSHAKE_DONE; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertSame(4, $frame->getPriority());
    }

    public function testGetPriorityForDefaultFrame(): void
    {
        $this->assertSame(10, $this->frame->getPriority());
    }

    public function testRequiresImmediateTransmissionForConnectionClose(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::CONNECTION_CLOSE; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testRequiresImmediateTransmissionForConnectionCloseApp(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::CONNECTION_CLOSE_APP; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testRequiresImmediateTransmissionForAck(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::ACK; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testRequiresImmediateTransmissionForAckEcn(): void
    {
        $frame = new class() extends Frame {
            public function getType(): FrameType { return FrameType::ACK_ECN; }
            public function encode(): string { return ''; }
            public static function decode(string $data, int $offset = 0): array { return [new static(), 0]; }
            public function validate(): bool { return true; }
        };

        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testRequiresImmediateTransmissionForOtherFrame(): void
    {
        $this->assertFalse($this->frame->requiresImmediateTransmission());
    }
}