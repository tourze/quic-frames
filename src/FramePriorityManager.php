<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;

/**
 * 帧优先级管理器
 *
 * 负责帧的优先级调度和传输管理
 */
final class FramePriorityManager
{
    /**
     * 高优先级帧队列
     *
     * @var Frame[]
     */
    private array $highPriorityFrames = [];

    /**
     * 中优先级帧队列
     *
     * @var Frame[]
     */
    private array $mediumPriorityFrames = [];

    /**
     * 低优先级帧队列
     *
     * @var Frame[]
     */
    private array $lowPriorityFrames = [];

    /**
     * 需要立即发送的帧队列
     *
     * @var Frame[]
     */
    private array $urgentFrames = [];

    public function __construct(
        private readonly int $highPriorityThreshold = 5,
        private readonly int $lowPriorityThreshold = 50,
    ) {
    }

    /**
     * 添加帧到相应的优先级队列
     */
    public function addFrame(Frame $frame): void
    {
        if ($frame->requiresImmediateTransmission()) {
            $this->urgentFrames[] = $frame;

            return;
        }

        $priority = $frame->getPriority();

        if ($priority <= $this->highPriorityThreshold) {
            $this->highPriorityFrames[] = $frame;
        } elseif ($priority <= $this->lowPriorityThreshold) {
            $this->mediumPriorityFrames[] = $frame;
        } else {
            $this->lowPriorityFrames[] = $frame;
        }
    }

    /**
     * 批量添加帧
     *
     * @param Frame[] $frames
     */
    public function addFrames(array $frames): void
    {
        foreach ($frames as $frame) {
            $this->addFrame($frame);
        }
    }

    /**
     * 获取下一批要发送的帧
     *
     * @param int $maxCount 最大帧数量
     * @param int $maxSize  最大总大小（字节）
     *
     * @return Frame[] 要发送的帧数组
     */
    public function getNextFrames(int $maxCount = 10, int $maxSize = 1200): array
    {
        $context = [
            'frames' => [],
            'totalSize' => 0,
            'count' => 0,
            'maxCount' => $maxCount,
            'maxSize' => $maxSize,
        ];

        $result = $this->processFrameQueue($this->urgentFrames, $context);
        $this->urgentFrames = $result['frameQueue'];
        $context = $result['context'];

        $result = $this->processFrameQueue($this->highPriorityFrames, $context);
        $this->highPriorityFrames = $result['frameQueue'];
        $context = $result['context'];

        $result = $this->processFrameQueue($this->mediumPriorityFrames, $context);
        $this->mediumPriorityFrames = $result['frameQueue'];
        $context = $result['context'];

        $result = $this->processFrameQueue($this->lowPriorityFrames, $context);
        $this->lowPriorityFrames = $result['frameQueue'];
        $context = $result['context'];

        return $context['frames'];
    }

    /**
     * @param Frame[] $frameQueue
     * @param array{frames: Frame[], totalSize: int, count: int, maxCount: int, maxSize: int} $context
     * @return array{frameQueue: Frame[], context: array{frames: Frame[], totalSize: int, count: int, maxCount: int, maxSize: int}}
     */
    private function processFrameQueue(array $frameQueue, array $context): array
    {
        while ([] !== $frameQueue && $context['count'] < $context['maxCount']) {
            $frame = array_shift($frameQueue);

            $result = $this->tryAddFrame($frame, $context);
            if (!$result['success']) {
                array_unshift($frameQueue, $frame);
                break;
            }
            $context = $result['context'];
        }

        return ['frameQueue' => $frameQueue, 'context' => $context];
    }

    /**
     * @param array{frames: Frame[], totalSize: int, count: int, maxCount: int, maxSize: int} $context
     * @return array{success: bool, context: array{frames: Frame[], totalSize: int, count: int, maxCount: int, maxSize: int}}
     */
    private function tryAddFrame(Frame $frame, array $context): array
    {
        $frameSize = strlen($frame->encode());

        if ($context['totalSize'] + $frameSize <= $context['maxSize']) {
            $context['frames'][] = $frame;
            $context['totalSize'] += $frameSize;
            ++$context['count'];

            return ['success' => true, 'context' => $context];
        }

        return ['success' => false, 'context' => $context];
    }

    /**
     * 检查是否有待发送的帧
     */
    public function hasFrames(): bool
    {
        return [] !== $this->urgentFrames
            || [] !== $this->highPriorityFrames
            || [] !== $this->mediumPriorityFrames
            || [] !== $this->lowPriorityFrames;
    }

    /**
     * 获取待发送帧的总数
     */
    public function getFrameCount(): int
    {
        return count($this->urgentFrames)
            + count($this->highPriorityFrames)
            + count($this->mediumPriorityFrames)
            + count($this->lowPriorityFrames);
    }

    /**
     * 检查是否有紧急帧需要立即发送
     */
    public function hasUrgentFrames(): bool
    {
        return [] !== $this->urgentFrames;
    }

    /**
     * 获取各优先级队列的统计信息
     */
    /**
     * @return array{urgent: int, high: int, medium: int, low: int, total: int}
     */
    public function getQueueStats(): array
    {
        return [
            'urgent' => count($this->urgentFrames),
            'high' => count($this->highPriorityFrames),
            'medium' => count($this->mediumPriorityFrames),
            'low' => count($this->lowPriorityFrames),
            'total' => $this->getFrameCount(),
        ];
    }

    /**
     * 清空所有队列
     */
    public function clearAll(): void
    {
        $this->urgentFrames = [];
        $this->highPriorityFrames = [];
        $this->mediumPriorityFrames = [];
        $this->lowPriorityFrames = [];
    }

    /**
     * 移除指定类型的帧
     */
    public function removeFramesByType(FrameType $frameType): int
    {
        $removed = 0;

        $result = $this->removeFromQueue($this->urgentFrames, $frameType);
        $this->urgentFrames = $result['queue'];
        $removed += $result['removedCount'];

        $result = $this->removeFromQueue($this->highPriorityFrames, $frameType);
        $this->highPriorityFrames = $result['queue'];
        $removed += $result['removedCount'];

        $result = $this->removeFromQueue($this->mediumPriorityFrames, $frameType);
        $this->mediumPriorityFrames = $result['queue'];
        $removed += $result['removedCount'];

        $result = $this->removeFromQueue($this->lowPriorityFrames, $frameType);
        $this->lowPriorityFrames = $result['queue'];
        $removed += $result['removedCount'];

        return $removed;
    }

    /**
     * 从指定队列中移除特定类型的帧
     *
     * @param Frame[] $queue
     * @return array{queue: Frame[], removedCount: int}
     */
    private function removeFromQueue(array $queue, FrameType $frameType): array
    {
        $originalCount = count($queue);

        $queue = array_filter($queue, static function (Frame $frame) use ($frameType): bool {
            return $frame->getType() !== $frameType;
        });

        return [
            'queue' => $queue,
            'removedCount' => $originalCount - count($queue),
        ];
    }
}
