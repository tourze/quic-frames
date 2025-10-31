<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;

/**
 * QUIC帧抽象基类
 *
 * 定义所有QUIC帧的通用接口和基本实现
 * 参考：https://tools.ietf.org/html/rfc9000#section-12.4
 */
abstract class Frame
{
    /**
     * 获取帧类型
     */
    abstract public function getType(): FrameType;

    /**
     * 编码帧为二进制数据
     */
    abstract public function encode(): string;

    /**
     * 从二进制数据解码帧
     *
     * @param string $data   二进制数据
     * @param int    $offset 解码起始偏移量
     *
     * @return array{0: Frame, 1: int} [解码的帧对象, 消耗的字节数]
     */
    abstract public static function decode(string $data, int $offset = 0): array;

    /**
     * 验证帧数据的有效性
     */
    abstract public function validate(): bool;

    /**
     * 获取帧的优先级（用于传输调度）
     *
     * @return int 优先级数值，数值越小优先级越高
     */
    public function getPriority(): int
    {
        return match ($this->getType()) {
            FrameType::ACK, FrameType::ACK_ECN => 1,
            FrameType::CONNECTION_CLOSE, FrameType::CONNECTION_CLOSE_APP => 2,
            FrameType::CRYPTO => 3,
            FrameType::HANDSHAKE_DONE => 4,
            default => 10,
        };
    }

    /**
     * 判断是否需要立即发送
     */
    public function requiresImmediateTransmission(): bool
    {
        return in_array($this->getType(), [
            FrameType::CONNECTION_CLOSE,
            FrameType::CONNECTION_CLOSE_APP,
            FrameType::ACK,
            FrameType::ACK_ECN,
        ], true);
    }
}
