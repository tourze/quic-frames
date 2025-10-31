<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\AckFrame;
use Tourze\QUIC\Frames\ConnectionCloseFrame;
use Tourze\QUIC\Frames\CryptoFrame;
use Tourze\QUIC\Frames\FrameDecoder;
use Tourze\QUIC\Frames\FrameEncoder;
use Tourze\QUIC\Frames\FramePriorityManager;
use Tourze\QUIC\Frames\PaddingFrame;
use Tourze\QUIC\Frames\PingFrame;
use Tourze\QUIC\Frames\StreamFrame;

/**
 * @internal
 */
#[CoversClass(FrameEncoder::class)]
final class FrameIntegrationTest extends TestCase
{
    private FrameEncoder $encoder;

    private FrameDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new FrameEncoder();
        $this->decoder = new FrameDecoder();
    }

    public function testFrameRoundTrip(): void
    {
        $frames = [
            new PingFrame(),
            new PaddingFrame(5),
            new StreamFrame(4, 'test data', 0, false),
            new AckFrame(100, 1000),
            new CryptoFrame(0, 'crypto data'),
            new ConnectionCloseFrame(0, 0, 'test'),
        ];

        foreach ($frames as $originalFrame) {
            // 编码
            $encoded = $this->encoder->encodeFrame($originalFrame);

            // 解码
            [$decodedFrame, $consumed] = $this->decoder->decodeFrame($encoded);

            // 验证
            $this->assertSame($originalFrame->getType(), $decodedFrame->getType());
            $this->assertSame(strlen($encoded), $consumed);
            $this->assertTrue($decodedFrame->validate());
        }
    }

    public function testMultiFrameEncoding(): void
    {
        $frames = [
            new PingFrame(),
            new StreamFrame(0, 'hello'),
            new PaddingFrame(3),
        ];

        $encoded = $this->encoder->encodeFrames($frames);
        $decoded = $this->decoder->decodeFrames($encoded);

        $this->assertCount(3, $decoded);
        $this->assertInstanceOf(PingFrame::class, $decoded[0]);
        $this->assertInstanceOf(StreamFrame::class, $decoded[1]);
        $this->assertInstanceOf(PaddingFrame::class, $decoded[2]);
    }

    public function testPriorityManagerIntegration(): void
    {
        $manager = new FramePriorityManager();

        $ping = new PingFrame();
        $stream = new StreamFrame(0, 'data');
        $ack = new AckFrame(10, 100);
        $padding = new PaddingFrame(10);

        $manager->addFrames([$ping, $stream, $ack, $padding]);

        // 检查统计信息
        $stats = $manager->getQueueStats();
        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['urgent']); // ACK 是紧急帧

        // 获取下一批帧
        $nextFrames = $manager->getNextFrames(maxCount: 2);
        $this->assertCount(2, $nextFrames);

        // 第一个应该是 ACK（紧急）
        $this->assertInstanceOf(AckFrame::class, $nextFrames[0]);
    }

    public function testEncodeFramesWithPadding(): void
    {
        $frames = [new PingFrame()];
        $targetSize = 10;

        $encoded = $this->encoder->encodeFramesWithPadding($frames, $targetSize);

        $this->assertSame($targetSize, strlen($encoded));

        $decoded = $this->decoder->decodeFrames($encoded);
        $this->assertCount(2, $decoded); // PING + PADDING
        $this->assertInstanceOf(PingFrame::class, $decoded[0]);
        $this->assertInstanceOf(PaddingFrame::class, $decoded[1]);
    }

    public function testFrameFiltering(): void
    {
        $frames = [
            new PingFrame(),
            new StreamFrame(0, 'data'),
            new PingFrame(),
            new PaddingFrame(5),
        ];

        $encoded = $this->encoder->encodeFrames($frames);
        $decoded = $this->decoder->decodeFrames($encoded);

        $pingFrames = $this->decoder->filterFramesByType($decoded, FrameType::PING);
        $this->assertCount(2, $pingFrames);

        // 使用实际的StreamFrame类型（STREAM_LEN）而不是基础的STREAM类型
        $streamFrame = new StreamFrame(0, 'data');
        $streamFrames = $this->decoder->filterFramesByType($decoded, $streamFrame->getType());
        $this->assertCount(1, $streamFrames);
    }

    public function testFrameValidation(): void
    {
        $frames = [
            new PingFrame(),
            new StreamFrame(0, 'test'),
            new AckFrame(100, 1000),
        ];

        $this->assertTrue($this->decoder->validateFrames($frames));

        // 测试包含无效帧的情况（通过强制修改创建无效状态比较困难，所以这里主要测试接口）
        $this->assertTrue($this->decoder->validateFrames([])); // 空数组应该有效
    }

    public function testComplexFrameScenario(): void
    {
        // 模拟一个复杂的 QUIC 通信场景
        $manager = new FramePriorityManager();

        // 添加握手数据
        $manager->addFrame(new CryptoFrame(0, 'TLS handshake data'));

        // 添加应用数据流
        $manager->addFrame(new StreamFrame(4, 'HTTP request data', 0, false));
        $manager->addFrame(new StreamFrame(4, ' more data', 17, true)); // 带 FIN

        // 添加确认
        $manager->addFrame(new AckFrame(50, 500)); // 简化，不包含复杂范围

        // 添加连接关闭
        $manager->addFrame(new ConnectionCloseFrame(0, 0, 'Normal close'));

        // 获取所有帧并编码
        $allFrames = [];
        while ($manager->hasFrames()) {
            $batch = $manager->getNextFrames(maxCount: 10, maxSize: 1200);
            $allFrames = array_merge($allFrames, $batch);
        }

        $this->assertGreaterThan(0, count($allFrames));

        // 编码和解码验证
        $encoded = $this->encoder->encodeFrames($allFrames);
        $decoded = $this->decoder->decodeFrames($encoded);

        $this->assertSameSize($allFrames, $decoded);
        $this->assertTrue($this->decoder->validateFrames($decoded));
    }

    public function testCanFitInPacket(): void
    {
        $smallFrame = new PingFrame();
        $largeFrame = new StreamFrame(0, str_repeat('x', 1000));

        // 测试可以放入的情况
        $this->assertTrue($this->encoder->canFitInPacket([$smallFrame], 1200));

        // 测试无法放入的情况
        $this->assertFalse($this->encoder->canFitInPacket([$largeFrame], 100));

        // 测试多个帧的组合
        $frames = [new PingFrame(), new PaddingFrame(10)];
        $this->assertTrue($this->encoder->canFitInPacket($frames, 50));
        $this->assertFalse($this->encoder->canFitInPacket($frames, 5));
    }

    public function testEncodeFramesByPriority(): void
    {
        $ping = new PingFrame();
        $stream = new StreamFrame(0, 'data');
        $ack = new AckFrame(10, 100);
        $padding = new PaddingFrame(10);

        $frames = [$ping, $stream, $ack, $padding];

        // 按优先级编码
        $encoded = $this->encoder->encodeFramesByPriority($frames);
        $decoded = $this->decoder->decodeFrames($encoded);

        $this->assertGreaterThan(0, count($decoded));
        $this->assertSameSize($frames, $decoded);

        // 验证第一个帧应该是高优先级的 ACK 帧
        $this->assertInstanceOf(AckFrame::class, $decoded[0]);
    }
}
