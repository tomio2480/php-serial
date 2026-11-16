<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\TimeoutException;
use RuntimeException;

/**
 * 全量を書き終えるか期限を過ぎるまで、書き込みと待機を繰り返す。
 *
 * 非ブロッキングの書き込みは、送信バッファの空きの分しか書けない。
 * 書けなかった残りは、書き込める状態になるのを待って書き足す。
 * 書き込み・待機・時計を引数で受け取り、ポートなしで確かめられるようにしている。
 *
 * @internal Platform 実装の内部で使う。公開 API ではない。
 */
final class WriteLoop
{
    /**
     * @param callable(string): (int|false) $writeChunk 渡したデータの先頭から書き、書けたバイト数を返す
     * @param callable(float): void $waitWritable 書き込めるようになるまで、最大で引数の秒数だけ待つ
     * @param callable(): float $now 現在時刻（秒）
     *
     * @return int 書いたバイト数（常に $data の長さ）
     *
     * @throws TimeoutException 期限までに全量を書けなかった
     * @throws RuntimeException 書き込みそのものに失敗した
     */
    public static function run(
        string $data,
        int $budgetMs,
        callable $writeChunk,
        callable $waitWritable,
        callable $now
    ): int {
        $length = strlen($data);

        if ($length === 0) {
            return 0;
        }

        $deadline = $now() + $budgetMs / 1000;
        $total = 0;

        while (true) {
            $written = $writeChunk(substr($data, $total));

            if ($written === false) {
                throw new RuntimeException('Failed to write to serial port');
            }

            $total += $written;

            if ($total >= $length) {
                return $length;
            }

            $remaining = $deadline - $now();

            if ($remaining <= 0) {
                throw new TimeoutException(
                    sprintf('Write timed out after %d ms: wrote %d of %d bytes', $budgetMs, $total, $length),
                    $total
                );
            }

            $waitWritable($remaining);
        }
    }
}
