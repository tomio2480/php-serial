<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Platform\WriteLoop;
use PhpSerial\TimeoutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 期限まで書き続ける処理を、書き込み・待機・時計を差し替えて確かめる。
 * 実際のポートを使わないため、どの OS でも実行する。
 */
class WriteLoopTest extends TestCase
{
    private const BUDGET_MS = 1000;

    private float $clock = 0.0;

    /** @var list<float> */
    private array $waits = [];

    public function testReturnsLengthWhenEverythingIsWrittenAtOnce(): void
    {
        $written = $this->writeWith('hello', [5]);

        $this->assertSame(5, $written);
        $this->assertSame([], $this->waits);
    }

    public function testKeepsWritingTheRestAfterWaiting(): void
    {
        $sent = '';
        $chunks = [2, 0, 3];
        $writer = function (string $chunk) use (&$sent, &$chunks): int {
            $n = array_shift($chunks) ?? 0;
            $sent .= substr($chunk, 0, $n);

            return $n;
        };

        $written = WriteLoop::run('hello', self::BUDGET_MS, $writer, $this->waiter(0.1), $this->now(...));

        $this->assertSame(5, $written);
        $this->assertSame('hello', $sent);
        $this->assertCount(2, $this->waits);
    }

    public function testThrowsTimeoutWithBytesWrittenWhenDeadlinePasses(): void
    {
        try {
            $this->writeWith('hello', [2], waitSeconds: 0.3);
            $this->fail('TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            $this->assertSame(2, $e->getBytesWritten());
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        // 期限を過ぎてから待ち続けない
        $this->assertGreaterThanOrEqual(1.0, $this->clock);
        $this->assertLessThan(1.3 + 0.001, $this->clock);
    }

    public function testWaitIsLimitedToTheRemainingTime(): void
    {
        try {
            $this->writeWith('hello', [0], waitSeconds: 0.7);
        } catch (TimeoutException) {
        }

        foreach ($this->waits as $i => $requested) {
            $this->assertLessThanOrEqual(self::BUDGET_MS / 1000 + 0.001, $requested, "wait #{$i}");
        }

        $this->assertEqualsWithDelta(0.3, $this->waits[1], 0.001);
    }

    public function testWriteFailureIsNotReportedAsTimeout(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write to serial port');

        $failingWriter = fn (string $chunk): false => false;

        try {
            WriteLoop::run('hello', self::BUDGET_MS, $failingWriter, $this->waiter(0.1), $this->now(...));
        } catch (TimeoutException $e) {
            $this->fail('a write failure must not be a TimeoutException');
        }
    }

    public function testEmptyDataReturnsZeroWithoutWriting(): void
    {
        $called = false;
        $writer = function (string $chunk) use (&$called): int {
            $called = true;

            return 0;
        };

        $this->assertSame(0, WriteLoop::run('', self::BUDGET_MS, $writer, $this->waiter(0.1), $this->now(...)));
        $this->assertFalse($called);
    }

    /**
     * 書き込みごとに $chunks の先頭のバイト数を書けたことにし、尽きたら 0 を返す。
     *
     * @param list<int> $chunks
     */
    private function writeWith(string $data, array $chunks, float $waitSeconds = 0.1): int
    {
        $writer = function (string $chunk) use (&$chunks): int {
            return array_shift($chunks) ?? 0;
        };

        return WriteLoop::run($data, self::BUDGET_MS, $writer, $this->waiter($waitSeconds), $this->now(...));
    }

    /**
     * 求められた秒数と $step の短い方だけ時計を進める待機。
     */
    private function waiter(float $step): callable
    {
        return function (float $seconds) use ($step): void {
            $this->waits[] = $seconds;
            $this->clock += min($seconds, $step);
        };
    }

    private function now(): float
    {
        return $this->clock;
    }
}
