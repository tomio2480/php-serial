<?php

declare(strict_types=1);

namespace PhpSerial;

use RuntimeException;
use Throwable;

/**
 * 期限までに送信を終えられなかったことを知らせる例外。
 *
 * 相手が受け取らない（読まない・フロー制御で止めている）と送信バッファが空かず、期限を過ぎる。
 * 期限までに送れたバイト数を getBytesWritten() で取り出せるため、残りを送り直せる。
 * RuntimeException を継承するため、従来どおり RuntimeException でも捕まえられる。
 */
final class TimeoutException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $bytesWritten,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * 期限までに送れたバイト数（0 を含む）。
     */
    public function getBytesWritten(): int
    {
        return $this->bytesWritten;
    }
}
