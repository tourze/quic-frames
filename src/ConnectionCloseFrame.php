<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Exception\InvalidFrameException;

/**
 * CONNECTION_CLOSE帧
 *
 * 用于通知对端连接即将关闭
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.19
 */
final class ConnectionCloseFrame extends Frame
{
    public function __construct(
        private readonly int $errorCode,
        private readonly int $frameType = 0,
        private readonly string $reasonPhrase = ''
    ) {
        if ($errorCode < 0) {
            throw new InvalidFrameException('错误码不能为负数');
        }
        
        if ($frameType < 0) {
            throw new InvalidFrameException('帧类型不能为负数');
        }
    }

    public function getType(): FrameType
    {
        return FrameType::CONNECTION_CLOSE;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getFrameType(): int
    {
        return $this->frameType;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function encode(): string
    {
        $result = chr($this->getType()->value);
        $result .= VariableInteger::encode($this->errorCode);
        $result .= VariableInteger::encode($this->frameType);
        $result .= VariableInteger::encode(strlen($this->reasonPhrase));
        $result .= $this->reasonPhrase;
        
        return $result;
    }

    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new InvalidFrameException('数据不足，无法解码CONNECTION_CLOSE帧');
        }

        $position = $offset;
        $frameType = ord($data[$position++]);
        
        if ($frameType !== FrameType::CONNECTION_CLOSE->value) {
            throw new InvalidFrameException('无效的CONNECTION_CLOSE帧类型');
        }

        // 解码错误码
        [$errorCode, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码触发关闭的帧类型
        [$triggerFrameType, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 解码原因短语长度
        [$reasonLength, $consumed] = VariableInteger::decode($data, $position);
        $position += $consumed;

        // 验证原因短语长度
        if ($position + $reasonLength > strlen($data)) {
            throw new InvalidFrameException('数据不足，无法读取完整的原因短语');
        }

        // 读取原因短语
        $reasonPhrase = '';
        if ($reasonLength > 0) {
            $reasonPhrase = substr($data, $position, $reasonLength);
            $position += $reasonLength;
        }

        $totalConsumed = $position - $offset;
        
        return [new self($errorCode, $triggerFrameType, $reasonPhrase), $totalConsumed];
    }

    public function validate(): bool
    {
        return $this->errorCode >= 0 && $this->frameType >= 0;
    }
} 