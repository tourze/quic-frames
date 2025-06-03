# QUIC Frames Package

这是一个用于处理 QUIC 协议帧的 PHP 库，提供了各种 QUIC 帧类型的编解码和管理功能。

## 功能特性

- 支持标准 QUIC 帧类型（PING、PADDING、STREAM、ACK、CRYPTO、CONNECTION_CLOSE）
- 帧的编码和解码
- 优先级管理和调度
- 帧验证
- 高性能编解码器

## 安装

```bash
composer require tourze/quic-frames
```

## 基本用法

### 创建和编码帧

```php
use Tourze\QUIC\Frames\PingFrame;
use Tourze\QUIC\Frames\StreamFrame;
use Tourze\QUIC\Frames\FrameEncoder;

// 创建一个 PING 帧
$ping = new PingFrame();

// 创建一个 STREAM 帧
$stream = new StreamFrame(
    streamId: 4,
    data: 'Hello, QUIC!',
    offset: 0,
    fin: true
);

// 编码帧
$encoder = new FrameEncoder();
$encoded = $encoder->encodeFrame($ping);

// 编码多个帧
$frames = [$ping, $stream];
$encodedAll = $encoder->encodeFrames($frames);
```

### 解码帧

```php
use Tourze\QUIC\Frames\FrameDecoder;

$decoder = new FrameDecoder();

// 解码单个帧
[$frame, $consumed] = $decoder->decodeFrame($binaryData);

// 解码多个帧
$frames = $decoder->decodeFrames($binaryData);
```

### 帧优先级管理

```php
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();

// 添加帧到优先级队列
$manager->addFrame($ping);
$manager->addFrame($stream);

// 获取下一批要发送的帧
$nextFrames = $manager->getNextFrames(maxCount: 10, maxSize: 1200);

// 检查队列状态
$stats = $manager->getQueueStats();
```

## 支持的帧类型

### PADDING 帧
```php
use Tourze\QUIC\Frames\PaddingFrame;

$padding = new PaddingFrame(length: 10);
```

### PING 帧
```php
use Tourze\QUIC\Frames\PingFrame;

$ping = new PingFrame();
```

### STREAM 帧
```php
use Tourze\QUIC\Frames\StreamFrame;

$stream = new StreamFrame(
    streamId: 4,
    data: 'payload',
    offset: 0,
    fin: false,
    length: null // 可选：显式指定长度
);
```

### ACK 帧
```php
use Tourze\QUIC\Frames\AckFrame;

$ack = new AckFrame(
    largestAcked: 100,
    ackDelay: 1000,
    ackRanges: [[95, 100], [90, 92]],
    ecnCounts: null // 可选：ECN 计数
);
```

### CRYPTO 帧
```php
use Tourze\QUIC\Frames\CryptoFrame;

$crypto = new CryptoFrame(
    offset: 0,
    data: $tlsHandshakeData
);
```

### CONNECTION_CLOSE 帧
```php
use Tourze\QUIC\Frames\ConnectionCloseFrame;

$close = new ConnectionCloseFrame(
    errorCode: 0,
    frameType: 0,
    reasonPhrase: 'Normal closure'
);
```

## 帧优先级

帧具有内置的优先级系统：

- **紧急帧** (立即传输): ACK, CONNECTION_CLOSE
- **高优先级** (1-5): ACK, CONNECTION_CLOSE, CRYPTO
- **中优先级** (6-50): PING, STREAM
- **低优先级** (>50): PADDING

## 性能优化

- 使用 `FramePriorityManager` 进行高效的帧调度
- 支持按大小限制打包帧
- 提供帧验证以避免无效数据传输

## 错误处理

所有帧操作都会在出现错误时抛出 `InvalidArgumentException`：

```php
try {
    $frame = new StreamFrame(-1, 'data'); // 无效的流 ID
} catch (InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage();
}
```

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/quic-frames/tests/
```

## 许可证

MIT License

## 相关包

- `tourze/quic-core` - QUIC 协议核心功能
- `tourze/quic-packets` - QUIC 数据包处理
