<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Frames\FramePriorityManager;
use Tourze\QUIC\Frames\PingFrame;
use Tourze\QUIC\Frames\PaddingFrame;
use Tourze\QUIC\Frames\StreamFrame;

class FramePriorityManagerTest extends TestCase
{
    private FramePriorityManager $manager;

    protected function setUp(): void
    {
        $this->manager = new FramePriorityManager();
    }

    public function testAddFrameAndGetFrames(): void
    {
        $streamFrame = new StreamFrame(1, 'test');
        $pingFrame = new PingFrame();
        $paddingFrame = new PaddingFrame(5);

        $this->manager->addFrame($streamFrame);
        $this->manager->addFrame($pingFrame);
        $this->manager->addFrame($paddingFrame);

        $this->assertTrue($this->manager->hasFrames());
        $this->assertSame(3, $this->manager->getFrameCount());
    }

    public function testAddFrames(): void
    {
        $frames = [
            new PaddingFrame(5),
            new PingFrame(),
            new StreamFrame(1, 'test'),
        ];

        $this->manager->addFrames($frames);
        $this->assertSame(3, $this->manager->getFrameCount());
    }

    public function testGetNextFrames(): void
    {
        $streamFrame = new StreamFrame(1, 'test');
        $pingFrame = new PingFrame();
        $paddingFrame = new PaddingFrame(5);

        $this->manager->addFrames([$streamFrame, $pingFrame, $paddingFrame]);

        $nextFrames = $this->manager->getNextFrames(2);
        
        $this->assertCount(2, $nextFrames);
        $this->assertTrue($this->manager->hasFrames()); // 还有1个帧剩余
    }

    public function testGetQueueStats(): void
    {
        $frames = [
            new StreamFrame(1, 'test1'),
            new PingFrame(),
            new StreamFrame(2, 'test2'),
            new PaddingFrame(5),
        ];

        $this->manager->addFrames($frames);
        $stats = $this->manager->getQueueStats();

        $this->assertArrayHasKey('urgent', $stats);
        $this->assertArrayHasKey('high', $stats);
        $this->assertArrayHasKey('medium', $stats);
        $this->assertArrayHasKey('low', $stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertSame(4, $stats['total']);
    }

    public function testClearAll(): void
    {
        $this->manager->addFrame(new PingFrame());
        $this->assertTrue($this->manager->hasFrames());
        
        $this->manager->clearAll();
        $this->assertFalse($this->manager->hasFrames());
        $this->assertSame(0, $this->manager->getFrameCount());
    }
}
