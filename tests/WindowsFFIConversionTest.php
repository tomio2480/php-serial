<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PhpSerial\Platform\WindowsFFI;
use PhpSerial\TimeoutException;
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

    public function testUnsupportedStopBitsAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported stop bits: 3');

        WindowsFFI::toDcbStopBits(3);
    }

    public function testFullWriteReturnsLength(): void
    {
        $this->assertSame(64, WindowsFFI::ensureFullyWritten(64, 64, 9600));
    }

    public function testPartialWriteIsReportedAsTimeout(): void
    {
        // COMMTIMEOUTS の上限に達すると、WriteFile は成功を返しつつ一部だけ書いて戻る
        try {
            WindowsFFI::ensureFullyWritten(10, 64, 9600);
            $this->fail('TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            $this->assertSame(10, $e->getBytesWritten());
            $this->assertStringContainsString('1128 ms', $e->getMessage());
        }
    }
}
