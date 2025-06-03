# QUIC 帧处理库

专门用于处理 QUIC 协议帧的 PHP 库，实现了 RFC 9000 标准中定义的各种帧类型。

## 核心特性

- 完整的 QUIC 帧类型支持
- 高效的编解码器
- 智能优先级管理
- 完善的错误处理
- 全面的单元测试覆盖

## 架构设计

```
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
```

## 技术规范

- PHP 8.1+
- 严格类型声明
- PSR-4 自动加载
- 完全测试覆盖

## 开发指南

请参考 `packages/quic-frames/dev20250103-005.md` 了解详细的开发规范和架构设计。

## 安装

```bash
composer require tourze/quic-frames
```

## 使用方法

待补充

## 配置

待补充

## 示例

待补充

## 参考文档

- [示例链接](https://example.com)
