<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;
use Tourze\QUIC\Frames\StreamFrame;

/**
 * @internal
 */
#[CoversClass(StreamFrame::class)]
final class StreamFrameTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $frame = new StreamFrame(1, 'test data', 100, true);

        $this->assertSame(1, $frame->getStreamId());
        $this->assertSame('test data', $frame->getData());
        $this->assertSame(100, $frame->getOffset());
        $this->assertTrue($frame->hasFin());
        $this->assertNull($frame->getLength()); // length参数没有传入，所以是null
        // 带有offset和fin标志的STREAM帧类型是STREAM_OFF_LEN_FIN
        $this->assertSame(FrameType::STREAM_OFF_LEN_FIN, $frame->getType());
    }

    public function testConstructorWithDefaults(): void
    {
        $frame = new StreamFrame(1, 'test');

        $this->assertSame(1, $frame->getStreamId());
        $this->assertSame('test', $frame->getData());
        $this->assertSame(0, $frame->getOffset());
        $this->assertFalse($frame->hasFin());
    }

    public function testConstructorWithNegativeStreamId(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('流ID不能为负数');

        new StreamFrame(-1, 'test');
    }

    public function testConstructorWithNegativeOffset(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('偏移量不能为负数');

        new StreamFrame(1, 'test', -1);
    }

    public function testConstructorWithEmptyData(): void
    {
        // 根据QUIC协议，空数据的STREAM帧是允许的，用于发送FIN标志
        $frame = new StreamFrame(1, '', 0, true);

        $this->assertSame('', $frame->getData());
        $this->assertTrue($frame->hasFin());
    }

    public function testEncode(): void
    {
        $frame = new StreamFrame(1, 'test data');
        $encoded = $frame->encode();

        $this->assertNotEmpty($encoded);
    }

    public function testEncodeWithOffset(): void
    {
        $frame = new StreamFrame(1, 'test', 100);
        $encoded = $frame->encode();

        $this->assertNotEmpty($encoded);
    }

    public function testEncodeWithFin(): void
    {
        $frame = new StreamFrame(1, 'test', 0, true);
        $encoded = $frame->encode();

        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new StreamFrame(1, 'test data', 100, true);
        $encoded = $frame->encode();

        [$decodedFrame, $consumed] = StreamFrame::decode($encoded);

        $this->assertInstanceOf(StreamFrame::class, $decodedFrame);
        $this->assertSame(1, $decodedFrame->getStreamId());
        $this->assertSame('test data', $decodedFrame->getData());
        $this->assertSame(100, $decodedFrame->getOffset());
        $this->assertTrue($decodedFrame->hasFin());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码STREAM帧');

        StreamFrame::decode('', 10);
    }

    public function testDecodeWithInvalidFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('无效的STREAM帧类型');

        $data = chr(0xFF); // 无效的帧类型
        StreamFrame::decode($data);
    }

    public function testFramePriority(): void
    {
        $frame = new StreamFrame(1, 'test');
        $priority = $frame->getPriority();

        $this->assertGreaterThan(0, $priority);
    }

    public function testValidate(): void
    {
        $frame = new StreamFrame(1, 'test data', 100);

        $this->assertTrue($frame->validate());
    }
}
