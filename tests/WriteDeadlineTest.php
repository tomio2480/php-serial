<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Platform\WriteDeadline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * write() の期限「1 文字の最大送信時間 × バイト数 + 余裕」の計算を確かめる。
 * どの OS でも同じ規則を使うため、OS を問わず実行する。
 */
class WriteDeadlineTest extends TestCase
{
    /**
     * 1 文字を最大 12 ビット（スタート・データ 8・パリティ・ストップ 2）と見て、切り上げた値を期待する。
     *
     * @return array<string, array{int, int}>
     */
    public static function perCharacterProvider(): array
    {
        return [
            '300 bps' => [300, 40],
            '9600 bps' => [9600, 2],
            '115200 bps' => [115200, 1],
            '921600 bps' => [921600, 1],
        ];
    }

    #[DataProvider('perCharacterProvider')]
    public function testPerCharacterCoversOneCharacterTime(int $baudRate, int $expectedMs): void
    {
        $this->assertSame($expectedMs, WriteDeadline::perCharacterMs($baudRate));
    }

    public function testBudgetAddsMarginToTransmissionTime(): void
    {
        // 64 バイト × 2 ms + 1000 ms
        $this->assertSame(1128, WriteDeadline::budgetMs(64, 9600));
    }

    public function testNonPositiveBaudRateIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Baud rate must be positive: 0');

        WriteDeadline::perCharacterMs(0);
    }
}
