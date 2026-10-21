<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Platform\WindowsFFI;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Windows API を FFI で実際に呼び出して確かめる．
 * 実在するシリアルポートは使わず，存在しないポートと無効なハンドルで失敗経路を通す．
 */
class WindowsFFITest extends TestCase
{
    // 通常の環境に存在しない番号を選ぶ
    private const ABSENT_PORT = 'COM255';

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !extension_loaded('ffi')) {
            $this->markTestSkipped('Windows API を FFI で呼べる環境でのみ実行する');
        }
    }

    public function testOpenThrowsWhenPortDoesNotExist(): void
    {
        $platform = new WindowsFFI();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to open serial port: ' . self::ABSENT_PORT);

        $platform->open(self::ABSENT_PORT);
    }
}
