<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;
use Tourze\QUIC\Frames\PaddingFrame;

/**
 * @internal
 */
#[CoversClass(PaddingFrame::class)]
final class PaddingFrameTest extends TestCase
{
    public function testConstructorWithValidLength(): void
    {
        $frame = new PaddingFrame(10);

        $this->assertSame(10, $frame->getLength());
        $this->assertSame(FrameType::PADDING, $frame->getType());
    }

    public function testConstructorWithDefaultLength(): void
    {
        $frame = new PaddingFrame();

        $this->assertSame(1, $frame->getLength());
    }

    public function testConstructorWithNegativeLength(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('PADDING帧长度必须至少为1字节');

        new PaddingFrame(-1);
    }

    public function testConstructorWithZeroLength(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('PADDING帧长度必须至少为1字节');

        new PaddingFrame(0);
    }

    public function testEncode(): void
    {
        $frame = new PaddingFrame(5);
        $encoded = $frame->encode();

        $this->assertSame(5, strlen($encoded));
        $this->assertSame(str_repeat("\x00", 5), $encoded);
    }

    public function testDecode(): void
    {
        $frame = new PaddingFrame(10);
        $encoded = $frame->encode();

        [$decodedFrame, $consumed] = PaddingFrame::decode($encoded);

        $this->assertInstanceOf(PaddingFrame::class, $decodedFrame);
        $this->assertInstanceOf(PaddingFrame::class, $decodedFrame);
        $this->assertSame(10, $decodedFrame->getLength());
        $this->assertSame(10, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码PADDING帧');

        PaddingFrame::decode('', 10);
    }

    public function testDecodeWithInvalidFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('无效的PADDING帧格式');

        $data = chr(0xFF); // 无效的帧类型
        PaddingFrame::decode($data);
    }

    public function testDecodeCountingPaddingBytes(): void
    {
        $data = "\x00\x00\x00\x00\x00"; // 5个padding字节
        [$frame, $consumed] = PaddingFrame::decode($data);

        $this->assertInstanceOf(PaddingFrame::class, $frame);
        $this->assertSame(5, $frame->getLength());
        $this->assertSame(5, $consumed);
    }

    public function testDecodeMixedData(): void
    {
        $data = "\x00\x00\x00\x01"; // 3个padding字节 + 1个非padding字节
        [$frame, $consumed] = PaddingFrame::decode($data);

        $this->assertInstanceOf(PaddingFrame::class, $frame);
        $this->assertSame(3, $frame->getLength());
        $this->assertSame(3, $consumed);
    }

    public function testValidate(): void
    {
        $frame = new PaddingFrame(5);

        $this->assertTrue($frame->validate());
    }
}
