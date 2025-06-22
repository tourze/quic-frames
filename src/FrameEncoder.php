<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

/**
 * 帧编码器
 * 
 * 负责将多个帧编码为二进制数据包
 */
final class FrameEncoder
{
    /**
     * 编码单个帧
     */
    public function encodeFrame(Frame $frame): string
    {
        return $frame->encode();
    }

    /**
     * 编码多个帧
     *
     * @param Frame[] $frames 要编码的帧数组
     * @return string 编码后的二进制数据
     */
    public function encodeFrames(array $frames): string
    {
        $result = '';
        
        foreach ($frames as $frame) {
            if (!$frame instanceof Frame) {
                throw new \InvalidArgumentException('只能编码Frame实例');
            }
            
            $result .= $this->encodeFrame($frame);
        }
        
        return $result;
    }

    /**
     * 按优先级排序帧后编码
     *
     * @param Frame[] $frames 要编码的帧数组
     * @return string 编码后的二进制数据
     */
    public function encodeFramesByPriority(array $frames): string
    {
        // 按优先级排序（数值越小优先级越高）
        usort($frames, static function (Frame $a, Frame $b): int {
            $priorityA = $a->getPriority();
            $priorityB = $b->getPriority();
            
            if ($priorityA === $priorityB) {
                // 优先级相同时，需要立即发送的帧排在前面
                $urgentA = $a->requiresImmediateTransmission() ? 1 : 0;
                $urgentB = $b->requiresImmediateTransmission() ? 1 : 0;
                return $urgentB - $urgentA;
            }
            
            return $priorityA - $priorityB;
        });
        
        return $this->encodeFrames($frames);
    }

    /**
     * 编码帧并填充到指定大小
     *
     * @param Frame[] $frames 要编码的帧数组
     * @param int $targetSize 目标数据包大小
     * @return string 编码后的二进制数据
     */
    public function encodeFramesWithPadding(array $frames, int $targetSize): string
    {
        if ($targetSize <= 0) {
            throw new \InvalidArgumentException('目标大小必须大于0');
        }

        $encoded = $this->encodeFramesByPriority($frames);
        $currentSize = strlen($encoded);
        
        if ($currentSize > $targetSize) {
            throw new \InvalidArgumentException("编码数据大小({$currentSize})超过目标大小({$targetSize})");
        }
        
        // 如果需要填充
        if ($currentSize < $targetSize) {
            $paddingSize = $targetSize - $currentSize;
            $paddingFrame = new PaddingFrame($paddingSize);
            $encoded .= $paddingFrame->encode();
        }
        
        return $encoded;
    }

    /**
     * 计算帧编码后的大小
     */
    public function getEncodedSize(Frame $frame): int
    {
        return strlen($frame->encode());
    }

    /**
     * 计算多个帧编码后的总大小
     *
     * @param Frame[] $frames
     */
    public function getTotalEncodedSize(array $frames): int
    {
        $totalSize = 0;
        
        foreach ($frames as $frame) {
            if (!$frame instanceof Frame) {
                throw new \InvalidArgumentException('只能计算Frame实例的大小');
            }
            
            $totalSize += $this->getEncodedSize($frame);
        }
        
        return $totalSize;
    }

    /**
     * 检查帧是否可以放入指定大小的数据包
     *
     * @param Frame[] $frames
     * @param int $maxSize
     */
    public function canFitInPacket(array $frames, int $maxSize): bool
    {
        return $this->getTotalEncodedSize($frames) <= $maxSize;
    }
} 