<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Frames\FrameEncoder;
use Tourze\QUIC\Frames\PaddingFrame;
use Tourze\QUIC\Frames\PingFrame;
use Tourze\QUIC\Frames\StreamFrame;

final class FrameEncoderTest extends TestCase
{
    private FrameEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new FrameEncoder();
    }

    public function testEncodeFrame(): void
    {
        $frame = new PingFrame();
        $encoded = $this->encoder->encodeFrame($frame);
        
        $this->assertSame("\x01", $encoded);
    }

    public function testEncodeFrames(): void
    {
        $frames = [
            new PingFrame(),
            new PaddingFrame(2),
        ];
        
        $encoded = $this->encoder->encodeFrames($frames);
        $expected = "\x01\x00\x00";
        
        $this->assertSame($expected, $encoded);
    }

    public function testEncodeFramesWithInvalidFrame(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('只能编码Frame实例');
        
        $this->encoder->encodeFrames(['not a frame']);
    }

    public function testEncodeFramesByPriority(): void
    {
        $frames = [
            new PaddingFrame(1), // 低优先级 (100)
            new PingFrame(),     // 中优先级 (50)
            new StreamFrame(0, 'test'), // 高优先级 (20)
        ];
        
        $encoded = $this->encoder->encodeFramesByPriority($frames);
        
        // 验证流帧排在最前面 (STREAM_LEN类型，因为总是包含长度字段)
        $this->assertSame(0x0A, ord($encoded[0])); // STREAM_LEN帧类型
    }

    public function testEncodeFramesWithPadding(): void
    {
        $frames = [new PingFrame()]; // 1字节
        $targetSize = 5;
        
        $encoded = $this->encoder->encodeFramesWithPadding($frames, $targetSize);
        
        $this->assertSame($targetSize, strlen($encoded));
        $this->assertSame("\x01", substr($encoded, 0, 1)); // PING帧
        $this->assertStringEndsWith("\x00\x00\x00\x00", $encoded); // 填充
    }

    public function testEncodeFramesWithPaddingExceedsTargetSize(): void
    {
        $frames = [new StreamFrame(0, 'long data that exceeds target')];
        $targetSize = 5;
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/编码数据大小.*超过目标大小/');
        
        $this->encoder->encodeFramesWithPadding($frames, $targetSize);
    }

    public function testGetEncodedSize(): void
    {
        $frame = new PingFrame();
        $size = $this->encoder->getEncodedSize($frame);
        
        $this->assertSame(1, $size);
    }

    public function testGetTotalEncodedSize(): void
    {
        $frames = [
            new PingFrame(),      // 1字节
            new PaddingFrame(3),  // 3字节
        ];
        
        $totalSize = $this->encoder->getTotalEncodedSize($frames);
        $this->assertSame(4, $totalSize);
    }

    public function testCanFitInPacket(): void
    {
        $frames = [
            new PingFrame(),      // 1字节
            new PaddingFrame(3),  // 3字节
        ];
        
        $this->assertTrue($this->encoder->canFitInPacket($frames, 10));
        $this->assertTrue($this->encoder->canFitInPacket($frames, 4));
        $this->assertFalse($this->encoder->canFitInPacket($frames, 3));
    }
} 