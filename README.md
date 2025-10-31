# QUIC Frames Package

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/packagist/php-v/tourze/quic-frames.svg?style=flat-square)]
(https://packagist.org/packages/tourze/quic-frames)
[![License](https://img.shields.io/packagist/l/tourze/quic-frames.svg?style=flat-square)]
(https://packagist.org/packages/tourze/quic-frames)
[![Latest Version](https://img.shields.io/packagist/v/tourze/quic-frames.svg?style=flat-square)]
(https://packagist.org/packages/tourze/quic-frames)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/quic-frames.svg?style=flat-square)]
(https://packagist.org/packages/tourze/quic-frames)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/quic-frames?style=flat-square)]
(https://codecov.io/gh/tourze/quic-frames)
[![Build Status]
(https://img.shields.io/github/actions/workflow/status/tourze/quic-frames/tests.yml?branch=master&style=flat-square)]
(https://github.com/tourze/quic-frames/actions)

A PHP library for handling QUIC protocol frames, providing encoding, 
decoding, and management of various QUIC frame types.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [Dependencies](#dependencies)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- Support for standard QUIC frame types  
  (PING, PADDING, STREAM, ACK, CRYPTO, CONNECTION_CLOSE)
- Frame encoding and decoding
- Priority management and scheduling
- Frame validation
- High-performance codec

## Installation

```bash
composer require tourze/quic-frames
```

## Quick Start

```php
<?php

use Tourze\QUIC\Frames\StreamFrame;
use Tourze\QUIC\Frames\FrameEncoder;
use Tourze\QUIC\Frames\FrameDecoder;

// Create a STREAM frame
$frame = new StreamFrame(4, 'Hello QUIC!', 0, true);

// Encode the frame
$encoder = new FrameEncoder();
$binary = $encoder->encodeFrame($frame);

// Decode the frame
$decoder = new FrameDecoder();
[$decoded, $consumed] = $decoder->decodeFrame($binary);

// Use priority manager
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();
$manager->addFrame($frame);
$nextFrames = $manager->getNextFrames(maxCount: 10, maxSize: 1200);
```

## Basic Usage

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

## Advanced Usage

### Batch Frame Processing

For better performance when handling multiple frames:

```php
use Tourze\QUIC\Frames\FrameDecoder;

$decoder = new FrameDecoder();
$frames = $decoder->decodeFrames($binaryData);

foreach ($frames as $frame) {
    // Process each frame
    echo "Frame type: " . $frame->getType() . "\n";
}
```

### Custom Priority Management

```php
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();

// Add urgent frames (highest priority)
$manager->addUrgentFrame($ackFrame);

// Add frames with different priorities
$manager->addFrame($streamFrame, priority: 10);
$manager->addFrame($paddingFrame, priority: 100);

// Get frames respecting priority and size limits
$nextFrames = $manager->getNextFrames(maxCount: 5, maxSize: 800);
```

### Frame Validation

All frames support validation to ensure data integrity:

```php
$frame = new StreamFrame(4, 'data', 0, true);

if ($frame->validate()) {
    $encoded = $frame->encode();
} else {
    throw new InvalidArgumentException('Invalid frame data');
}
```

## Dependencies

This package requires:

- **PHP 8.1+** - Uses modern PHP features for better performance
- **tourze/quic-core** - Core QUIC protocol definitions and utilities
- **tourze/quic-packets** - QUIC packet handling and structure definitions

For development:
- **phpunit/phpunit ^10.0** - Unit testing framework
- **phpstan/phpstan ^2.1** - Static analysis tool

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

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/quic-frames/tests/
```

## Contributing

We welcome contributions! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes and add tests
4. Ensure all tests pass: `./vendor/bin/phpunit packages/quic-frames/tests/`
5. Run static analysis: `./vendor/bin/phpstan analyse packages/quic-frames`
6. Submit a pull request

For bug reports and feature requests, please create an issue on GitHub.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Related Packages

- `tourze/quic-core` - QUIC protocol core functionality
- `tourze/quic-packets` - QUIC packet processing
