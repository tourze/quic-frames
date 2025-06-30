<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * ACK帧
 *
 * 用于确认收到的数据包
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.3
 */
final class AckFrame extends Frame
{
    /**
     * @param int $largestAcked 最大确认包序号
     * @param int $ackDelay 确认延迟（微秒）
     * @param array<array{int, int}> $ackRanges 确认范围 [[start, end], ...]
     * @param array{int, int, int}|null $ecnCounts ECN计数 [ect0, ect1, ecnCe]
     */
    public function __construct(
        private readonly int $largestAcked,
        private readonly int $ackDelay,
        private readonly array $ackRanges = [],
        private readonly ?array $ecnCounts = null
    ) {
        if ($largestAcked < 0) {
            throw new InvalidFrameException('最大确认包序号不能为负数');
        }
        
        if ($ackDelay < 0) {
            throw new InvalidFrameException('确认延迟不能为负数');
        }

        if ($ecnCounts !== null && count($ecnCounts) !== 3) {
            throw new InvalidFrameException('ECN计数必须包含3个元素');
        }

        // 验证确认范围的有效性
        foreach ($ackRanges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new InvalidFrameException('确认范围格式无效');
            }
            [$start, $end] = $range;
            if ($start < 0 || $end < 0 || $start > $end) {
                throw new InvalidFrameException('确认范围值无效');
            }
        }
    }

    public function getType(): FrameType
    {
        return $this->ecnCounts !== null ? FrameType::ACK_ECN : FrameType::ACK;
    }

    public function getLargestAcked(): int
    {
        return $this->largestAcked;
    }

    public function getAckDelay(): int
    {
        return $this->ackDelay;
    }

    public function getAckRanges(): array
    {
        return $this->ackRanges;
    }

    public function getEcnCounts(): ?array
    {
        return $this->ecnCounts;
    }

    public function encode(): string
    {
        $result = chr($this->getType()->value);
        
        // 编码最大确认包序号
        $result .= VariableInteger::encode($this->largestAcked);
        
        // 编码确认延迟
        $result .= VariableInteger::encode($this->ackDelay);
        
        // 编码确认范围数量
        $result .= VariableInteger::encode(count($this->ackRanges));
        
        // 编码第一个确认范围（从 largestAcked 开始的连续范围长度）
        $firstRangeLength = 0;
        if (!empty($this->ackRanges)) {
            $firstRange = $this->ackRanges[0];
            $firstRangeLength = $firstRange[1] - $firstRange[0];
        }
        $result .= VariableInteger::encode($firstRangeLength);
        
        // 编码其他确认范围
        for ($i = 1; $i < count($this->ackRanges); $i++) {
            $currentRange = $this->ackRanges[$i];
            $previousRange = $this->ackRanges[$i - 1];
            
            // 间隔大小（两个范围之间的间隙）
            $gap = $previousRange[0] - $currentRange[1] - 2;
            $result .= VariableInteger::encode($gap);
            
            // 确认范围长度
            $rangeLength = $currentRange[1] - $currentRange[0];
            $result .= VariableInteger::encode($rangeLength);
        }
        
        // 如果是ECN确认帧，编码ECN计数
        if ($this->ecnCounts !== null) {
            $result .= VariableInteger::encode($this->ecnCounts[0]); // ECT(0)
            $result .= VariableInteger::encode($this->ecnCounts[1]); // ECT(1)
            $result .= VariableInteger::encode($this->ecnCounts[2]); // ECN-CE
        }
        
        return $result;
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new InvalidFrameException('数据不足，无法解码ACK帧');
        }

        $position = $offset;
        $frameType = ord($data[$position++]);
        
        $isEcnAck = $frameType === FrameType::ACK_ECN->value;
        
        if ($frameType !== FrameType::ACK->value && $frameType !== FrameType::ACK_ECN->value) {
            throw new InvalidFrameException('无效的ACK帧类型');
        }

        // 解码最大确认包序号
        [$largestAcked, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码确认延迟
        [$ackDelay, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码确认范围数量
        [$ackRangeCount, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码第一个确认范围长度
        [$firstRangeLength, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        $ackRanges = [];
        
        // 添加第一个确认范围
        if ($firstRangeLength >= 0) {
            $ackRanges[] = [$largestAcked - $firstRangeLength, $largestAcked];
        }

        // 解码其他确认范围
        $currentPacketNumber = $largestAcked - $firstRangeLength;
        
        for ($i = 0; $i < $ackRangeCount; $i++) {
            // 解码间隔
            [$gap, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            // 解码范围长度
            [$rangeLength, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            $currentPacketNumber -= $gap + 2;
            $rangeEnd = $currentPacketNumber;
            $rangeStart = $currentPacketNumber - $rangeLength;
            
            $ackRanges[] = [$rangeStart, $rangeEnd];
            $currentPacketNumber = $rangeStart;
        }

        // 如果是ECN确认帧，解码ECN计数
        $ecnCounts = null;
        if ($isEcnAck) {
            [$ect0, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            [$ect1, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            [$ecnCe, $consumed] = VariableInteger::decode($data, $position);
            $position += $consumed;
            
            $ecnCounts = [$ect0, $ect1, $ecnCe];
        }

        $totalConsumed = $position - $offset;
        
        return [
            new self($largestAcked, $ackDelay, $ackRanges, $ecnCounts),
            $totalConsumed
        ];
    }

    public function validate(): bool
    {
        if ($this->largestAcked < 0 || $this->ackDelay < 0) {
            return false;
        }

        foreach ($this->ackRanges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                return false;
            }
            [$start, $end] = $range;
            if ($start < 0 || $end < 0 || $start > $end) {
                return false;
            }
        }

        if ($this->ecnCounts !== null) {
            if (count($this->ecnCounts) !== 3) {
                return false;
            }
            foreach ($this->ecnCounts as $count) {
                if ($count < 0) {
                    return false;
                }
            }
        }

        return true;
    }
} 