<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\PingFrame;

final class PingFrameTest extends TestCase
{
    public function testGetType(): void
    {
        $frame = new PingFrame();
        $this->assertSame(FrameType::PING, $frame->getType());
    }

    public function testEncode(): void
    {
        $frame = new PingFrame();
        $encoded = $frame->encode();
        
        $this->assertSame("\x01", $encoded);
        $this->assertSame(1, strlen($encoded));
    }

    public function testDecode(): void
    {
        $data = "\x01";
        [$frame, $consumed] = PingFrame::decode($data);
        
        $this->assertInstanceOf(PingFrame::class, $frame);
        $this->assertSame(FrameType::PING, $frame->getType());
        $this->assertSame(1, $consumed);
    }

    public function testDecodeWithInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的PING帧格式');
        
        PingFrame::decode("\x02");
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的PING帧格式');
        
        PingFrame::decode("", 0);
    }

    public function testValidate(): void
    {
        $frame = new PingFrame();
        $this->assertTrue($frame->validate());
    }

    public function testGetPriority(): void
    {
        $frame = new PingFrame();
        $this->assertSame(50, $frame->getPriority());
    }

    public function testRequiresImmediateTransmission(): void
    {
        $frame = new PingFrame();
        $this->assertFalse($frame->requiresImmediateTransmission());
    }
} 