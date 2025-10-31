<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\ConnectionCloseFrame;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * @internal
 */
#[CoversClass(ConnectionCloseFrame::class)]
final class ConnectionCloseFrameTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $frame = new ConnectionCloseFrame(0x100, 0x02, 'Connection timeout');

        $this->assertSame(0x100, $frame->getErrorCode());
        $this->assertSame(0x02, $frame->getFrameType());
        $this->assertSame('Connection timeout', $frame->getReasonPhrase());
        $this->assertSame(FrameType::CONNECTION_CLOSE, $frame->getType());
    }

    public function testConstructorWithDefaults(): void
    {
        $frame = new ConnectionCloseFrame(0x100);

        $this->assertSame(0x100, $frame->getErrorCode());
        $this->assertSame(0, $frame->getFrameType());
        $this->assertSame('', $frame->getReasonPhrase());
    }

    public function testConstructorWithNegativeErrorCode(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('错误码不能为负数');

        new ConnectionCloseFrame(-1);
    }

    public function testConstructorWithNegativeFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('帧类型不能为负数');

        new ConnectionCloseFrame(0x100, -1);
    }

    public function testEncode(): void
    {
        $frame = new ConnectionCloseFrame(0x100, 0x02, 'test');
        $encoded = $frame->encode();

        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new ConnectionCloseFrame(0x100, 0x02, 'test message');
        $encoded = $frame->encode();

        [$decodedFrame, $consumed] = ConnectionCloseFrame::decode($encoded);

        $this->assertInstanceOf(ConnectionCloseFrame::class, $decodedFrame);
        $this->assertSame(0x100, $decodedFrame->getErrorCode());
        $this->assertSame(0x02, $decodedFrame->getFrameType());
        $this->assertSame('test message', $decodedFrame->getReasonPhrase());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码CONNECTION_CLOSE帧');

        ConnectionCloseFrame::decode('', 10);
    }

    public function testDecodeWithInvalidFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('无效的CONNECTION_CLOSE帧类型');

        $data = chr(0xFF); // 无效的帧类型
        ConnectionCloseFrame::decode($data);
    }

    public function testValidate(): void
    {
        $frame = new ConnectionCloseFrame(0x100);
        $this->assertTrue($frame->validate());

        $frameWithReason = new ConnectionCloseFrame(0x100, 0x02, 'Connection timeout');
        $this->assertTrue($frameWithReason->validate());
    }
}
