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
     * @var Frame[]
     */
    private array $highPriorityFrames = [];

    /**
     * 中优先级帧队列
     * @var Frame[]
     */
    private array $mediumPriorityFrames = [];

    /**
     * 低优先级帧队列
     * @var Frame[]
     */
    private array $lowPriorityFrames = [];

    /**
     * 需要立即发送的帧队列
     * @var Frame[]
     */
    private array $urgentFrames = [];

    public function __construct(
        private readonly int $highPriorityThreshold = 5,
        private readonly int $lowPriorityThreshold = 50
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
     * @param int $maxSize 最大总大小（字节）
     * @return Frame[] 要发送的帧数组
     */
    public function getNextFrames(int $maxCount = 10, int $maxSize = 1200): array
    {
        $frames = [];
        $totalSize = 0;
        $count = 0;

        // 优先发送紧急帧
        while (!empty($this->urgentFrames) && $count < $maxCount) {
            $frame = array_shift($this->urgentFrames);
            $frameSize = strlen($frame->encode());
            
            if ($totalSize + $frameSize <= $maxSize) {
                $frames[] = $frame;
                $totalSize += $frameSize;
                $count++;
            } else {
                // 如果加入这个帧会超出大小限制，放回队列头部
                array_unshift($this->urgentFrames, $frame);
                break;
            }
        }

        // 然后发送高优先级帧
        while (!empty($this->highPriorityFrames) && $count < $maxCount) {
            $frame = array_shift($this->highPriorityFrames);
            $frameSize = strlen($frame->encode());
            
            if ($totalSize + $frameSize <= $maxSize) {
                $frames[] = $frame;
                $totalSize += $frameSize;
                $count++;
            } else {
                array_unshift($this->highPriorityFrames, $frame);
                break;
            }
        }

        // 再发送中优先级帧
        while (!empty($this->mediumPriorityFrames) && $count < $maxCount) {
            $frame = array_shift($this->mediumPriorityFrames);
            $frameSize = strlen($frame->encode());
            
            if ($totalSize + $frameSize <= $maxSize) {
                $frames[] = $frame;
                $totalSize += $frameSize;
                $count++;
            } else {
                array_unshift($this->mediumPriorityFrames, $frame);
                break;
            }
        }

        // 最后发送低优先级帧
        while (!empty($this->lowPriorityFrames) && $count < $maxCount) {
            $frame = array_shift($this->lowPriorityFrames);
            $frameSize = strlen($frame->encode());
            
            if ($totalSize + $frameSize <= $maxSize) {
                $frames[] = $frame;
                $totalSize += $frameSize;
                $count++;
            } else {
                array_unshift($this->lowPriorityFrames, $frame);
                break;
            }
        }

        return $frames;
    }

    /**
     * 检查是否有待发送的帧
     */
    public function hasFrames(): bool
    {
        return !empty($this->urgentFrames) 
            || !empty($this->highPriorityFrames)
            || !empty($this->mediumPriorityFrames)
            || !empty($this->lowPriorityFrames);
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
        return !empty($this->urgentFrames);
    }

    /**
     * 获取各优先级队列的统计信息
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

        $removed += $this->removeFromQueue($this->urgentFrames, $frameType);
        $removed += $this->removeFromQueue($this->highPriorityFrames, $frameType);
        $removed += $this->removeFromQueue($this->mediumPriorityFrames, $frameType);
        $removed += $this->removeFromQueue($this->lowPriorityFrames, $frameType);

        return $removed;
    }

    /**
     * 从指定队列中移除特定类型的帧
     *
     * @param Frame[] $queue
     * @param FrameType $frameType
     * @return int 移除的帧数量
     */
    private function removeFromQueue(array &$queue, FrameType $frameType): int
    {
        $originalCount = count($queue);
        
        $queue = array_filter($queue, static function (Frame $frame) use ($frameType): bool {
            return $frame->getType() !== $frameType;
        });

        return $originalCount - count($queue);
    }
} 