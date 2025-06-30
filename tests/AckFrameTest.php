<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\AckFrame;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

class AckFrameTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $frame = new AckFrame(100, 50, [[10, 20], [30, 40]], [1, 2, 3]);
        
        $this->assertSame(100, $frame->getLargestAcked());
        $this->assertSame(50, $frame->getAckDelay());
        $this->assertSame([[10, 20], [30, 40]], $frame->getAckRanges());
        $this->assertSame([1, 2, 3], $frame->getEcnCounts());
        $this->assertSame(FrameType::ACK_ECN, $frame->getType());
    }

    public function testConstructorWithoutEcnCounts(): void
    {
        $frame = new AckFrame(100, 50);
        
        $this->assertSame(100, $frame->getLargestAcked());
        $this->assertSame(50, $frame->getAckDelay());
        $this->assertSame([], $frame->getAckRanges());
        $this->assertNull($frame->getEcnCounts());
        $this->assertSame(FrameType::ACK, $frame->getType());
    }

    public function testConstructorWithNegativeLargestAcked(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('最大确认包序号不能为负数');
        
        new AckFrame(-1, 50);
    }

    public function testConstructorWithNegativeAckDelay(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('确认延迟不能为负数');
        
        new AckFrame(100, -1);
    }

    public function testConstructorWithInvalidEcnCounts(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('ECN计数必须包含3个元素');
        
        /** @phpstan-ignore-next-line 故意传入错误的ECN计数来测试验证 */
        new AckFrame(100, 50, [], [1, 2]);
    }

    public function testConstructorWithInvalidAckRangeFormat(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('确认范围格式无效');
        
        /** @phpstan-ignore-next-line 故意传入格式错误的range来测试验证 */
        new AckFrame(100, 50, [[10]]);
    }

    public function testConstructorWithInvalidAckRangeValues(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('确认范围值无效');
        
        new AckFrame(100, 50, [[20, 10]]);
    }

    public function testEncode(): void
    {
        $frame = new AckFrame(100, 50);
        $encoded = $frame->encode();
        
        $this->assertNotEmpty($encoded);
    }

    public function testEncodeWithEcnCounts(): void
    {
        $frame = new AckFrame(100, 50, [], [1, 2, 3]);
        $encoded = $frame->encode();
        
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new AckFrame(100, 50);
        $encoded = $frame->encode();
        
        [$decodedFrame, $consumed] = AckFrame::decode($encoded);
        
        $this->assertInstanceOf(AckFrame::class, $decodedFrame);
        $this->assertSame(100, $decodedFrame->getLargestAcked());
        $this->assertSame(50, $decodedFrame->getAckDelay());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码ACK帧');
        
        AckFrame::decode('', 10);
    }

    public function testDecodeWithInvalidFrameType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('无效的ACK帧类型');
        
        $data = chr(0xFF); // 无效的帧类型
        AckFrame::decode($data);
    }
}
