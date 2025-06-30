<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * STREAM帧
 *
 * 用于传输应用数据流
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.8
 */
final class StreamFrame extends Frame
{
    public function __construct(
        private readonly int $streamId,
        private readonly string $data,
        private readonly int $offset = 0,
        private readonly bool $fin = false,
        private readonly ?int $length = null
    ) {
        if ($streamId < 0) {
            throw new InvalidFrameException('流ID不能为负数');
        }
        
        if ($offset < 0) {
            throw new InvalidFrameException('偏移量不能为负数');
        }

        if ($length !== null && $length < 0) {
            throw new InvalidFrameException('长度不能为负数');
        }

        if ($length !== null && $length !== strlen($data)) {
            throw new InvalidFrameException('指定长度与数据长度不匹配');
        }
    }

    public function getType(): FrameType
    {
        // 根据标志位确定具体的流帧类型
        $type = 0x08;
        
        if ($this->offset > 0) {
            $type |= 0x04; // OFF位
        }
        
        // 在多帧编码的情况下，总是包含长度字段
        $type |= 0x02; // LEN位
        
        if ($this->fin) {
            $type |= 0x01; // FIN位
        }

        return FrameType::from($type);
    }

    public function getStreamId(): int
    {
        return $this->streamId;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function hasFin(): bool
    {
        return $this->fin;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function encode(): string
    {
        $result = chr($this->getType()->value);
        $result .= VariableInteger::encode($this->streamId);
        
        if ($this->offset > 0) {
            $result .= VariableInteger::encode($this->offset);
        }
        
        // 总是包含长度字段
        $dataLength = $this->length ?? strlen($this->data);
        $result .= VariableInteger::encode($dataLength);
        
        $result .= $this->data;
        
        return $result;
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new InvalidFrameException('数据不足，无法解码STREAM帧');
        }

        $position = $offset;
        $frameType = ord($data[$position++]);
        
        if (($frameType & 0xF8) !== 0x08) {
            throw new InvalidFrameException('无效的STREAM帧类型');
        }

        $hasOffset = ($frameType & 0x04) !== 0;
        $hasLength = ($frameType & 0x02) !== 0;
        $hasFin = ($frameType & 0x01) !== 0;

        // 解码流ID
        [$streamId, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码偏移量
        $streamOffset = 0;
        if ($hasOffset) {
            [$streamOffset, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
        }

        // 解码长度和数据
        $streamData = '';
        $dataLength = null;
        
        if ($hasLength) {
            [$dataLength, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            if ($position + $dataLength > strlen($data)) {
                throw new InvalidFrameException('数据不足，无法读取完整的流数据');
            }
            
            $streamData = substr($data, $position, $dataLength);
            $position += $dataLength;
        } else {
            // 如果没有长度字段，读取剩余所有数据
            $streamData = substr($data, $position);
            $position = strlen($data);
        }

        $totalConsumed = $position - $offset;
        
        return [
            new self($streamId, $streamData, $streamOffset, $hasFin, $dataLength),
            $totalConsumed
        ];
    }

    public function validate(): bool
    {
        if ($this->streamId < 0) {
            return false;
        }

        if ($this->offset < 0) {
            return false;
        }

        if ($this->length !== null) {
            if ($this->length < 0 || $this->length !== strlen($this->data)) {
                return false;
            }
        }

        return true;
    }

    public function getPriority(): int
    {
        return 20; // 较高优先级，仅次于控制帧
    }
} 