# QUIC 帧处理库

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

专门用于处理 QUIC 协议帧的 PHP 库，实现了 RFC 9000 标准中
定义的各种帧类型。

## 目录

- [核心特性](#核心特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [使用方法](#使用方法)
- [高级用法](#高级用法)
- [依赖项](#依赖项)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)

## 核心特性

- 完整的 QUIC 帧类型支持
- 高效的编解码器
- 智能优先级管理
- 完善的错误处理
- 全面的单元测试覆盖

## 架构设计

```text
Tourze\QUIC\Frames\
├── Frame                    # 抽象基类
├── PingFrame               # PING 帧
├── PaddingFrame           # PADDING 帧
├── StreamFrame            # STREAM 帧
├── AckFrame               # ACK 帧
├── CryptoFrame            # CRYPTO 帧
├── ConnectionCloseFrame   # CONNECTION_CLOSE 帧
├── FrameEncoder           # 帧编码器
├── FrameDecoder           # 帧解码器
└── FramePriorityManager   # 优先级管理器
```

## 安装

```bash
composer require tourze/quic-frames
```

## 快速开始

```php
<?php

use Tourze\QUIC\Frames\StreamFrame;
use Tourze\QUIC\Frames\FrameEncoder;
use Tourze\QUIC\Frames\FrameDecoder;

// 创建流帧
$frame = new StreamFrame(4, 'Hello QUIC', 0, true);

// 编码
$encoder = new FrameEncoder();
$binary = $encoder->encodeFrame($frame);

// 解码
$decoder = new FrameDecoder();
[$decoded, $consumed] = $decoder->decodeFrame($binary);

// 使用优先级管理器
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();
$manager->addFrame($frame);
$nextFrames = $manager->getNextFrames(maxCount: 10, maxSize: 1200);
```

## 技术规范

- PHP 8.1+
- 严格类型声明
- PSR-4 自动加载
- 完全测试覆盖

## 使用方法

### 创建和编码帧

```php
use Tourze\QUIC\Frames\PingFrame;
use Tourze\QUIC\Frames\StreamFrame;
use Tourze\QUIC\Frames\FrameEncoder;

// 创建 PING 帧
$ping = new PingFrame();

// 创建 STREAM 帧
$stream = new StreamFrame(4, 'Hello, QUIC!', 0, true);

// 编码帧
$encoder = new FrameEncoder();
$encoded = $encoder->encodeFrame($ping);
```

### 解码帧

```php
use Tourze\QUIC\Frames\FrameDecoder;

$decoder = new FrameDecoder();
[$frame, $consumed] = $decoder->decodeFrame($binaryData);
```

### 帧优先级管理

```php
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();
$manager->addFrame($ping);
$manager->addFrame($stream);

$nextFrames = $manager->getNextFrames(maxCount: 10, maxSize: 1200);
```

## 高级用法

### 批量帧处理

处理多个帧时的性能优化：

```php
use Tourze\QUIC\Frames\FrameDecoder;

$decoder = new FrameDecoder();
$frames = $decoder->decodeFrames($binaryData);

foreach ($frames as $frame) {
    // 处理每个帧
    echo "帧类型: " . $frame->getType() . "\n";
}
```

### 自定义优先级管理

```php
use Tourze\QUIC\Frames\FramePriorityManager;

$manager = new FramePriorityManager();

// 添加紧急帧（最高优先级）
$manager->addUrgentFrame($ackFrame);

// 添加不同优先级的帧
$manager->addFrame($streamFrame, priority: 10);
$manager->addFrame($paddingFrame, priority: 100);

// 按优先级和大小限制获取帧
$nextFrames = $manager->getNextFrames(maxCount: 5, maxSize: 800);
```

### 帧验证

所有帧都支持验证以确保数据完整性：

```php
$frame = new StreamFrame(4, 'data', 0, true);

if ($frame->validate()) {
    $encoded = $frame->encode();
} else {
    throw new InvalidArgumentException('无效的帧数据');
}
```

## 依赖项

此包需要：

- **PHP 8.1+** - 使用现代 PHP 特性以获得更好的性能
- **tourze/quic-core** - QUIC 协议核心定义和工具
- **tourze/quic-packets** - QUIC 数据包处理和结构定义

开发环境依赖：
- **phpunit/phpunit ^10.0** - 单元测试框架
- **phpstan/phpstan ^2.1** - 静态分析工具

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/quic-frames/tests/
```

## 贡献

我们欢迎贡献！请遵循以下指导原则：

1. Fork 本仓库
2. 创建功能分支：`git checkout -b feature/new-feature`
3. 进行更改并添加测试
4. 确保所有测试通过：`./vendor/bin/phpunit packages/quic-frames/tests/`
5. 运行静态分析：`./vendor/bin/phpstan analyse packages/quic-frames`
6. 提交 Pull Request

如需报告 Bug 或请求功能，请在 GitHub 上创建 Issue。

## 许可证

MIT 许可证。更多信息请参考 [License File](LICENSE)。

## 相关包

- `tourze/quic-core` - QUIC 协议核心功能
- `tourze/quic-packets` - QUIC 数据包处理
