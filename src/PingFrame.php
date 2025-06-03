<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;

/**
 * PING帧
 * 
 * 用于测试连接可达性和保持连接活跃
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.2
 */
final class PingFrame extends Frame
{
    public function getType(): FrameType
    {
        return FrameType::PING;
    }

    public function encode(): string
    {
        return "\x01";
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data) || ord($data[$offset]) !== 0x01) {
            throw new \InvalidArgumentException('无效的PING帧格式');
        }

        return [new self(), 1];
    }

    public function validate(): bool
    {
        return true; // PING帧没有额外的验证要求
    }

    public function getPriority(): int
    {
        return 50; // 中等优先级
    }
} 