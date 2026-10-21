<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use RuntimeException;

/**
 * read() に渡す読み取り長を検証し，上限へ丸める．
 *
 * fread() や FFI::new() は指定長のバッファを先に確保する．
 * 呼び出し側が外部入力をそのまま渡すと，巨大な確保で memory_limit を超え，
 * プロセスが fatal error で止まる．read() は「最大 n バイト」を返す API のため，
 * 上限へ丸めても呼び出し側の期待は崩れない．
 *
 * @internal Platform 実装の内部で使う．公開 API ではない．
 */
final class ReadLength
{
    /**
     * 1 回の read() で確保するバッファの上限（64 KiB）．
     * 230400 bps でも約 2.8 秒分に当たり，シリアル通信の 1 回分として十分に大きい．
     */
    public const MAX = 65536;

    /**
     * @return int<1, max>
     */
    public static function normalize(int $length): int
    {
        if ($length < 1) {
            throw new RuntimeException('Length must be at least 1');
        }

        return min($length, self::MAX);
    }
}
