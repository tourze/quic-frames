<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\CryptoFrame;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * @internal
 */
#[CoversClass(CryptoFrame::class)]
final class CryptoFrameTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $frame = new CryptoFrame(100, 'test crypto data');

        $this->assertSame(100, $frame->getOffset());
        $this->assertSame('test crypto data', $frame->getData());
        $this->assertSame(16, $frame->getLength());
        $this->assertSame(FrameType::CRYPTO, $frame->getType());
    }

    public function testConstructorWithNegativeOffset(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('偏移量不能为负数');

        new CryptoFrame(-1, 'test');
    }

    public function testEncode(): void
    {
        $frame = new CryptoFrame(100, 'test data');
        $encoded = $frame->encode();

        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new CryptoFrame(100, 'test crypto data');
        $encoded = $frame->encode();

        [$decodedFrame, $consumed] = CryptoFrame::decode($encoded);

        $this->assertInstanceOf(CryptoFrame::class, $decodedFrame);
        $this->assertSame(100, $decodedFrame->getOffset());
        $this->assertSame('test crypto data', $decodedFrame->getData());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码CRYPTO帧');

        CryptoFrame::decode('', 10);
    }

    public function testDecodeWithInvalidFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('无效的CRYPTO帧类型');

        $data = chr(0xFF); // 无效的帧类型
        CryptoFrame::decode($data);
    }

    public function testValidate(): void
    {
        $frame = new CryptoFrame(100, 'test crypto data');

        $this->assertTrue($frame->validate());
    }
}
