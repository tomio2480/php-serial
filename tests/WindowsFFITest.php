<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use FFI;
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
    private const WRITE_SIZE = 1024 * 1024;
    private const WRITE_REPEAT = 8;

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

    public function testWriteReleasesBufferAfterEachCall(): void
    {
        $platform = $this->createInitializedPlatform();
        // cast() の結果は型情報を FFI インスタンスに頼るため，インスタンスを変数で保持する
        $handleTypes = FFI::cdef('typedef void* HANDLE;');
        $invalidHandle = $handleTypes->cast('HANDLE', -1);
        $data = str_repeat('a', self::WRITE_SIZE);

        $before = memory_get_usage();

        for ($i = 0; $i < self::WRITE_REPEAT; $i++) {
            try {
                $platform->write($invalidHandle, $data);
                $this->fail('WriteFile with an invalid handle should fail');
            } catch (RuntimeException $e) {
                $this->assertSame('Failed to write to serial port', $e->getMessage());
            }
        }

        // 解放されていれば，増分は 1 回分の書き込みサイズにも届かない
        $this->assertLessThan(self::WRITE_SIZE, memory_get_usage() - $before);
    }

    /**
     * FFI を初期化した状態の WindowsFFI を返す．
     * open() は存在しないポートで失敗するが，その前に FFI を初期化する．
     */
    private function createInitializedPlatform(): WindowsFFI
    {
        $platform = new WindowsFFI();

        try {
            $platform->open(self::ABSENT_PORT);
            $this->fail(self::ABSENT_PORT . ' should not exist');
        } catch (RuntimeException $e) {
            $this->assertStringStartsWith('Failed to open serial port', $e->getMessage());
        }

        return $platform;
    }
}
