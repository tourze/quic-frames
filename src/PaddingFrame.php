<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * PADDING帧
 *
 * 用于填充数据包，增加数据包大小以防止流量分析
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.1
 */
final class PaddingFrame extends Frame
{
    public function __construct(
        private readonly int $length = 1
    ) {
        if ($length < 1) {
            throw new InvalidFrameException('PADDING帧长度必须至少为1字节');
        }
    }

    public function getType(): FrameType
    {
        return FrameType::PADDING;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function encode(): string
    {
        return str_repeat("\x00", $this->length);
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new InvalidFrameException('数据不足，无法解码PADDING帧');
        }

        $length = 0;
        $position = $offset;
        
        // 计算连续的PADDING帧长度
        while ($position < strlen($data) && ord($data[$position]) === 0x00) {
            $length++;
            $position++;
        }

        if ($length === 0) {
            throw new InvalidFrameException('无效的PADDING帧格式');
        }

        return [new self($length), $length];
    }

    public function validate(): bool
    {
        return $this->length >= 1;
    }

    public function getPriority(): int
    {
        return 100; // 最低优先级
    }
} 