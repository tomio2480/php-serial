<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use RuntimeException;

/**
 * write() の期限を計算する。
 *
 * 期限は「1 文字の最大送信時間 × バイト数 + 余裕」とし、どの OS でも同じ規則を使う。
 * 相手が受け取っていれば送信時間の内に終わり、受け取らなければ余裕の分だけ待って諦める。
 *
 * @internal Platform 実装の内部で使う。公開 API ではない。
 */
final class WriteDeadline
{
    // 1 文字の最大ビット数（スタート 1・データ 8・パリティ 1・ストップ 2）
    private const MAX_BITS_PER_CHARACTER = 12;

    // 送信時間に足す固定の余裕。相手が読まないときは、この程度で諦める
    public const MARGIN_MS = 1000;

    /**
     * 1 文字の送信にかかる最大時間（ミリ秒、切り上げ）を返す。
     */
    public static function perCharacterMs(int $baudRate): int
    {
        if ($baudRate < 1) {
            throw new RuntimeException(sprintf('Baud rate must be positive: %d', $baudRate));
        }

        return max(1, intdiv(self::MAX_BITS_PER_CHARACTER * 1000 + $baudRate - 1, $baudRate));
    }

    /**
     * $length バイトを送るときの期限（ミリ秒）を返す。
     */
    public static function budgetMs(int $length, int $baudRate): int
    {
        return self::perCharacterMs($baudRate) * $length + self::MARGIN_MS;
    }
}
