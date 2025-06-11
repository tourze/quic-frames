<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames;

use Tourze\QUIC\Core\Enum\FrameType;

/**
 * 帧解码器
 * 
 * 负责将二进制数据解码为帧对象
 */
final class FrameDecoder
{
    /**
     * 帧类型到具体类的映射
     */
    private const FRAME_CLASS_MAP = [
        0x00 => PaddingFrame::class,     // PADDING
        0x01 => PingFrame::class,        // PING
        0x02 => AckFrame::class,         // ACK
        0x03 => AckFrame::class,         // ACK_ECN
        0x06 => CryptoFrame::class,      // CRYPTO
        0x1C => ConnectionCloseFrame::class, // CONNECTION_CLOSE
        // 流帧类型共享同一个类
        0x08 => StreamFrame::class,      // STREAM
        0x09 => StreamFrame::class,      // STREAM_FIN
        0x0A => StreamFrame::class,      // STREAM_LEN
        0x0B => StreamFrame::class,      // STREAM_LEN_FIN
        0x0C => StreamFrame::class,      // STREAM_OFF
        0x0D => StreamFrame::class,      // STREAM_OFF_FIN
        0x0E => StreamFrame::class,      // STREAM_OFF_LEN
        0x0F => StreamFrame::class,      // STREAM_OFF_LEN_FIN
    ];

    /**
     * 解码单个帧
     * 
     * @param string $data 二进制数据
     * @param int $offset 解码起始偏移量
     * @return array{0: Frame, 1: int} [解码的帧对象, 消耗的字节数]
     */
    public function decodeFrame(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new \InvalidArgumentException('数据不足，无法解码帧');
        }

        $frameTypeByte = ord($data[$offset]);
        
        // 尝试解析帧类型
        try {
            $frameType = FrameType::from($frameTypeByte);
        } catch (\ValueError) {
            throw new \InvalidArgumentException("未知的帧类型: 0x" . sprintf('%02X', $frameTypeByte));
        }

        // 获取对应的帧类
        $frameClass = self::FRAME_CLASS_MAP[$frameTypeByte] ?? null;
        
        if ($frameClass === null) {
            throw new \InvalidArgumentException("不支持的帧类型: {$frameType->name}");
        }

        // 调用具体帧类的解码方法
        return $frameClass::decode($data, $offset);
    }

    /**
     * 解码多个帧
     * 
     * @param string $data 二进制数据
     * @return Frame[] 解码的帧数组
     */
    public function decodeFrames(string $data): array
    {
        $frames = [];
        $offset = 0;
        $dataLength = strlen($data);

        while ($offset < $dataLength) {
            // 特殊处理 PADDING 帧（连续的 0x00 字节）
            if (ord($data[$offset]) === 0x00) {
                $paddingStart = $offset;
                while ($offset < $dataLength && ord($data[$offset]) === 0x00) {
                    $offset++;
                }
                $paddingLength = $offset - $paddingStart;
                if ($paddingLength > 0) {
                    $frames[] = new PaddingFrame($paddingLength);
                }
                continue;
            }

            try {
                [$frame, $consumed] = $this->decodeFrame($data, $offset);
                $frames[] = $frame;
                $offset += $consumed;
            } catch  (\Throwable $e) {
                throw new \InvalidArgumentException(
                    "在偏移量 {$offset} 处解码帧失败: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $frames;
    }

    /**
     * 检查数据中是否有完整的帧
     * 
     * @param string $data 二进制数据
     * @param int $offset 检查起始偏移量
     * @return bool 是否有完整的帧
     */
    public function hasCompleteFrame(string $data, int $offset = 0): bool
    {
        try {
            $this->decodeFrame($data, $offset);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * 获取下一个帧的类型（不解码整个帧）
     * 
     * @param string $data 二进制数据
     * @param int $offset 偏移量
     * @return FrameType 帧类型
     */
    public function peekFrameType(string $data, int $offset = 0): FrameType
    {
        if ($offset >= strlen($data)) {
            throw new \InvalidArgumentException('数据不足，无法获取帧类型');
        }

        $frameTypeByte = ord($data[$offset]);
        
        try {
            return FrameType::from($frameTypeByte);
        } catch (\ValueError) {
            throw new \InvalidArgumentException("未知的帧类型: 0x" . sprintf('%02X', $frameTypeByte));
        }
    }

    /**
     * 验证所有解码的帧
     * 
     * @param Frame[] $frames 要验证的帧数组
     * @return bool 所有帧是否有效
     */
    public function validateFrames(array $frames): bool
    {
        foreach ($frames as $frame) {
            if (!$frame instanceof Frame) {
                return false;
            }
            
            if (!$frame->validate()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 按类型过滤帧
     * 
     * @param Frame[] $frames 帧数组
     * @param FrameType $frameType 要过滤的帧类型
     * @return Frame[] 过滤后的帧数组
     */
    public function filterFramesByType(array $frames, FrameType $frameType): array
    {
        return array_filter($frames, static function (Frame $frame) use ($frameType): bool {
            return $frame->getType() === $frameType;
        });
    }

    /**
     * 获取支持的帧类型列表
     * 
     * @return FrameType[] 支持的帧类型数组
     */
    public function getSupportedFrameTypes(): array
    {
        $types = [];
        foreach (array_keys(self::FRAME_CLASS_MAP) as $typeValue) {
            try {
                $types[] = FrameType::from($typeValue);
            } catch (\ValueError) {
                // 忽略无效的帧类型值
            }
        }
        return $types;
    }
} 