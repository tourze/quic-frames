<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;
use Tourze\QUIC\Frames\FrameDecoder;
use Tourze\QUIC\Frames\PaddingFrame;
use Tourze\QUIC\Frames\PingFrame;

/**
 * @internal
 */
#[CoversClass(FrameDecoder::class)]
final class FrameDecoderTest extends TestCase
{
    private FrameDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new FrameDecoder();
    }

    public function testDecodeFrames(): void
    {
        // 创建一些帧并编码
        $pingFrame = new PingFrame();
        $paddingFrame = new PaddingFrame(5);

        $data = $pingFrame->encode() . $paddingFrame->encode();

        $frames = $this->decoder->decodeFrames($data);

        $this->assertCount(2, $frames);
        $this->assertInstanceOf(PingFrame::class, $frames[0]);
        $this->assertInstanceOf(PaddingFrame::class, $frames[1]);
    }

    public function testDecodeFramesWithEmptyData(): void
    {
        $frames = $this->decoder->decodeFrames('');

        $this->assertSame([], $frames);
    }

    public function testDecodeFrameWithInvalidData(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('数据不足，无法解码帧');

        $this->decoder->decodeFrame('', 10);
    }

    public function testDecodeFrameWithUnsupportedType(): void
    {
        $this->expectException(InvalidFrameException::class);
        $this->expectExceptionMessage('未知的帧类型: 0xFF');

        $data = chr(0xFF); // 不支持的帧类型
        $this->decoder->decodeFrame($data);
    }

    public function testFilterFramesByType(): void
    {
        $pingFrame = new PingFrame();
        $paddingFrame = new PaddingFrame(5);

        $frames = [$pingFrame, $paddingFrame];

        $filteredFrames = $this->decoder->filterFramesByType($frames, $pingFrame->getType());

        $this->assertCount(1, $filteredFrames);
        $this->assertInstanceOf(PingFrame::class, $filteredFrames[0]);
    }

    public function testDecodeWithOffset(): void
    {
        $pingFrame = new PingFrame();
        $data = 'prefix' . $pingFrame->encode();

        [$frame, $consumed] = $this->decoder->decodeFrame($data, 6);

        $this->assertInstanceOf(PingFrame::class, $frame);
        $this->assertGreaterThan(0, $consumed);
    }

    public function testPeekFrameType(): void
    {
        $pingFrame = new PingFrame();
        $data = $pingFrame->encode();

        $frameType = $this->decoder->peekFrameType($data);

        $this->assertEquals($pingFrame->getType(), $frameType);
    }

    public function testValidateFrames(): void
    {
        $pingFrame = new PingFrame();
        $paddingFrame = new PaddingFrame(5);
        $frames = [$pingFrame, $paddingFrame];

        $this->assertTrue($this->decoder->validateFrames($frames));
        $this->assertTrue($this->decoder->validateFrames([])); // 空数组应该有效
    }
}
