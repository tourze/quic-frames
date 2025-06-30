<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * CRYPTO帧
 *
 * 用于传输TLS握手消息和其他加密握手数据
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.6
 */
final class CryptoFrame extends Frame
{
    public function __construct(
        private readonly int $offset,
        private readonly string $data
    ) {
        if ($offset < 0) {
            throw new InvalidFrameException('偏移量不能为负数');
        }
    }

    public function getType(): FrameType
    {
        return FrameType::CRYPTO;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getLength(): int
    {
        return strlen($this->data);
    }

    public function encode(): string
    {
        $result = chr($this->getType()->value);
        $result .= VariableInteger::encode($this->offset);
        $result .= VariableInteger::encode($this->getLength());
        $result .= $this->data;
        
        return $result;
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new InvalidFrameException('数据不足，无法解码CRYPTO帧');
        }

        $position = $offset;
        $frameType = ord($data[$position++]);
        
        if ($frameType !== FrameType::CRYPTO->value) {
            throw new InvalidFrameException('无效的CRYPTO帧类型');
        }

        // 解码偏移量
        [$cryptoOffset, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码数据长度
        [$length, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 验证数据长度
        if ($position + $length > strlen($data)) {
            throw new InvalidFrameException('数据不足，无法读取完整的CRYPTO数据');
        }

        // 读取CRYPTO数据
        $cryptoData = substr($data, $position, $length);
        $position += $length;

        $totalConsumed = $position - $offset;
        
        return [new self($cryptoOffset, $cryptoData), $totalConsumed];
    }

    public function validate(): bool
    {
        return $this->offset >= 0;
    }
} 