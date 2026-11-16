<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\Configuration;
use RuntimeException;

class Unix implements PlatformInterface
{
    // write() の期限の計算に使う。configure() の前に書いた場合は既定の設定の値を使う
    private ?int $baudRate = null;

    private static function validateDevice(string $device): void
    {
        // D 修飾子: $ を末尾の改行の手前で一致させない
        if (!preg_match('#^/dev/(tty(USB|ACM|S|AMA)?\d+|tty\.[a-zA-Z0-9_\-]+)$#D', $device)) {
            throw new RuntimeException(
                sprintf(
                    'Invalid Unix device path: %s (expected format: /dev/ttyUSB0, /dev/tty.usbserial, etc.)',
                    $device
                )
            );
        }
    }

    public function configure(string $device, Configuration $config): void
    {
        exec(self::buildConfigureCommand($device, $config, PHP_OS_FAMILY), $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                sprintf('Failed to configure serial port: %s', implode("\n", $output))
            );
        }

        $this->baudRate = $config->getBaudRate();
    }

    /**
     * ポートを設定する stty のコマンドを組み立てる。
     *
     * デバイスを渡すオプションは GNU（Linux）が -F、BSD 系（macOS）が -f である。
     * macOS で -F を渡すと、stty はデバイスではなく標準入力を設定しようとして失敗する。
     *
     * @internal テストから OS ごとの組み立てを確かめるために公開している。
     */
    public static function buildConfigureCommand(string $device, Configuration $config, string $osFamily): string
    {
        self::validateDevice($device);
        $parityMap = [
            Configuration::PARITY_NONE => '-parenb',
            Configuration::PARITY_ODD => 'parenb parodd',
            Configuration::PARITY_EVEN => 'parenb -parodd',
        ];

        $stopBitsMap = [
            Configuration::STOP_BITS_1 => '-cstopb',
            Configuration::STOP_BITS_2 => 'cstopb',
        ];

        $parity = $parityMap[$config->getParity()];
        $stopBits = $stopBitsMap[$config->getStopBits()];

        $deviceOption = $osFamily === 'Darwin' ? '-f' : '-F';

        // raw -echo -iexten: 行編集・改行の変換・特殊文字（0x0F・0x16 など）の処理を止め、
        // 受け取ったバイトをそのまま扱う。既定の端末設定ではバイナリが欠ける。
        // macOS の raw はデータビットとパリティも上書きするため、回線の設定より前に置く。
        // セキュリティ: 数値パラメータも明示的にエスケープ
        // 失敗の理由を例外メッセージへ残すため、標準エラー出力も受け取る
        return sprintf(
            'stty %s %s raw -echo -iexten %s cs%s %s %s 2>&1',
            $deviceOption,
            escapeshellarg($device),
            escapeshellarg((string) $config->getBaudRate()),
            escapeshellarg((string) $config->getDataBits()),
            $parity,
            $stopBits
        );
    }

    public function open(string $device): mixed
    {
        $handle = @fopen($device, 'r+b');

        if ($handle === false) {
            throw new RuntimeException(
                sprintf('Failed to open serial port: %s', $device)
            );
        }

        stream_set_blocking($handle, false);

        return $handle;
    }

    public function close(mixed $handle): void
    {
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    public function write(mixed $handle, string $data): int
    {
        if (!is_resource($handle)) {
            throw new RuntimeException('Invalid handle');
        }

        // ポートは非ブロッキングで開いているため、送信バッファの空きの分しか書けない。
        // 残りは書き込めるようになるのを待って書き足し、期限を過ぎたら TimeoutException を投げる
        return WriteLoop::run(
            $data,
            WriteDeadline::budgetMs(strlen($data), $this->baudRate ?? (new Configuration())->getBaudRate()),
            static fn (string $chunk): int|false => fwrite($handle, $chunk),
            static function (float $seconds) use ($handle): void {
                $read = null;
                $write = [$handle];
                $except = null;
                $wholeSeconds = (int) $seconds;
                $microseconds = (int) (($seconds - $wholeSeconds) * 1_000_000);

                // シグナルで中断されても、呼び出し元が期限を確かめて書き直す
                stream_select($read, $write, $except, $wholeSeconds, $microseconds);
            },
            static fn (): float => microtime(true)
        );
    }

    public function read(mixed $handle, int $length): string|false
    {
        if (!is_resource($handle)) {
            throw new RuntimeException('Invalid handle');
        }

        return fread($handle, ReadLength::normalize($length));
    }
}
