<?php

declare(strict_types=1);

namespace Tourze\QUIC\Frames\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Frames\{FrameDecoder, FrameEncoder, PaddingFrame, PingFrame, StreamFrame};

final class DebugTest extends TestCase
{
    public function testPaddingFrameEncoding(): void
    {
        $encoder = new FrameEncoder();
        $decoder = new FrameDecoder();
        
        $frames = [
            new PingFrame(),
            new PaddingFrame(3),
        ];
        
        $encoded = $encoder->encodeFrames($frames);
        echo "\nEncoded data: " . bin2hex($encoded) . "\n";
        echo "Encoded length: " . strlen($encoded) . "\n";
        
        $decoded = $decoder->decodeFrames($encoded);
        echo "Decoded count: " . count($decoded) . "\n";
        
        foreach ($decoded as $i => $frame) {
            echo "Frame $i: " . get_class($frame) . "\n";
        }
        
        $this->assertCount(2, $decoded);
    }

    public function testMultiFrameEncodingDebug(): void
    {
        $encoder = new FrameEncoder();
        $decoder = new FrameDecoder();
        
        $frames = [
            new PingFrame(),
            new StreamFrame(0, 'hello'),
            new PaddingFrame(3),
        ];
        
        $encoded = $encoder->encodeFrames($frames);
        echo "\nMulti-frame encoded data: " . bin2hex($encoded) . "\n";
        echo "Encoded length: " . strlen($encoded) . "\n";
        
        $decoded = $decoder->decodeFrames($encoded);
        echo "Decoded count: " . count($decoded) . "\n";
        
        foreach ($decoded as $i => $frame) {
            echo "Frame $i: " . get_class($frame) . " - " . $frame->getType()->name . "\n";
        }
        
        $this->assertCount(3, $decoded);
    }
} 