<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PhpSerial\Platform\WindowsFFI;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Configuration の値を Windows API の構造体の値へ写す規則を確かめる。
 * FFI を使わない純粋な変換のため、どの OS でも実行する。
 */
class WindowsFFIConversionTest extends TestCase
{
    // DCB.StopBits の値（winbase.h）
    private const ONESTOPBIT = 0;
    private const TWOSTOPBITS = 2;

    /**
     * @return array<string, array{int, int}>
     */
    public static function stopBitsProvider(): array
    {
        return [
            '1 stop bit' => [Configuration::STOP_BITS_1, self::ONESTOPBIT],
            '2 stop bits' => [Configuration::STOP_BITS_2, self::TWOSTOPBITS],
        ];
    }

    #[DataProvider('stopBitsProvider')]
    public function testStopBitsMapToDcbValues(int $stopBits, int $expected): void
    {
        $this->assertSame($expected, WindowsFFI::toDcbStopBits($stopBits));
    }

    /**
     * 1 文字を最大 12 ビット（スタート・データ 8・パリティ・ストップ 2）と見て、切り上げた値を期待する。
     *
     * @return array<string, array{int, int}>
     */
    public static function writeTimeoutMultiplierProvider(): array
    {
        return [
            '300 bps' => [300, 40],
            '9600 bps' => [9600, 2],
            '115200 bps' => [115200, 1],
            '921600 bps' => [921600, 1],
        ];
    }

    #[DataProvider('writeTimeoutMultiplierProvider')]
    public function testWriteTimeoutMultiplierCoversOneCharacterTime(int $baudRate, int $expectedMs): void
    {
        $this->assertSame($expectedMs, WindowsFFI::writeTimeoutMultiplierMs($baudRate));
    }

    public function testUnsupportedStopBitsAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported stop bits: 3');

        WindowsFFI::toDcbStopBits(3);
    }

    public function testNonPositiveBaudRateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Baud rate must be positive: 0');

        WindowsFFI::writeTimeoutMultiplierMs(0);
    }
}
