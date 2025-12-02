<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\Configuration;
use RuntimeException;

class Windows implements PlatformInterface
{
    private static bool $warningShown = false;
    private static bool $readWarningShown = false;

    public function __construct()
    {
        // FFI が無効な場合は警告を表示（初回のみ）
        if (!self::$warningShown) {
            $this->showFFIWarning();
            self::$warningShown = true;
        }
    }

    private function showFFIWarning(): void
    {
        $message = <<<'EOT'

Warning: PHP FFI extension is not enabled.
警告: PHP FFI 拡張が無効です。

For optimal serial communication on Windows, enable FFI in php.ini:
Windowsで最適なシリアル通信を行うには、php.iniでFFIを有効にしてください：

  extension=ffi
  ffi.enable=true

Current limitations with FFI disabled:
FFI無効時の制限事項：
  - Lower communication accuracy and stability
  - 通信精度と安定性が低下します
  - Dependency on Windows 'mode' command
  - Windows 'mode'コマンドへの依存

For details, see: docs/WINDOWS_FFI_SETUP.md
詳細は docs/WINDOWS_FFI_SETUP.md を参照してください

EOT;
        // stderr に出力（通常の出力と区別するため）
        fwrite(STDERR, $message);
    }

    private function validateDevice(string $device): void
    {
        if (!preg_match('/^COM\d+$/i', $device)) {
            throw new RuntimeException(
                sprintf('Invalid Windows COM port name: %s (expected format: COM1, COM2, etc.)', $device)
            );
        }
    }

    public function configure(string $device, Configuration $config): void
    {
        $this->validateDevice($device);

        // Windowsではポートを開く前にmode コマンドで設定する
        $parityMap = [
            Configuration::PARITY_NONE => 'n',
            Configuration::PARITY_ODD => 'o',
            Configuration::PARITY_EVEN => 'e',
        ];

        $parity = $parityMap[$config->getParity()] ?? 'n';

        // セキュリティ: 数値パラメータの範囲を検証
        // Configuration クラスで型は保証されているため is_int() チェックは不要
        $baudRate = $config->getBaudRate();
        $dataBits = $config->getDataBits();
        $stopBits = $config->getStopBits();

        if ($baudRate <= 0) {
            throw new RuntimeException('Invalid baud rate');
        }

        if ($dataBits < 5 || $dataBits > 8) {
            throw new RuntimeException('Invalid data bits');
        }

        if ($stopBits !== 1 && $stopBits !== 2) {
            throw new RuntimeException('Invalid stop bits');
        }

        // パリティは英字1文字のみ
        if (!preg_match('/^[noe]$/', $parity)) {
            throw new RuntimeException('Invalid parity');
        }

        // mode コマンドの形式: mode COM1: BAUD=9600 PARITY=n DATA=8 STOP=1 to=on xon=off
        // escapeshellarg() を使うとダブルクォーテーションが追加されてコマンドが失敗する
        // デバイス名とパラメータは上記で厳格にバリデーション済みなので直接使用
        // to=on: タイムアウトを有効にして読み取り時のハングを防止
        // xon=off: XON/XOFFフロー制御を無効化（Arduinoでは通常不要）
        $modeCommand = sprintf(
            'mode %s: BAUD=%d PARITY=%s DATA=%d STOP=%d to=on xon=off',
            $device,
            $baudRate,
            $parity,
            $dataBits,
            $stopBits
        );

        $command = 'cmd.exe /c ' . $modeCommand;

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                sprintf('Failed to configure serial port %s: %s', $device, implode("\n", $output))
            );
        }

        // 設定が反映されるまで少し待機
        usleep(100000); // 100ms
    }

    public function open(string $device): mixed
    {
        // Windowsでは \\.\COMx 形式を使用
        $devicePath = "\\\\.\\{$device}";

        $handle = @fopen($devicePath, 'r+b');

        if ($handle === false) {
            throw new RuntimeException(
                sprintf('Failed to open serial port: %s', $device)
            );
        }

        // Windows環境ではノンブロッキングモードで動作させる
        // ブロッキングモードだと fread() が無限待機する問題がある
        stream_set_blocking($handle, false);

        // 読み取りタイムアウトを設定（秒, マイクロ秒）
        // ノンブロッキングモードでは無視されるが、念のため設定
        stream_set_timeout($handle, 2, 0);

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

        $written = fwrite($handle, $data);

        if ($written === false) {
            throw new RuntimeException('Failed to write to serial port');
        }

        return $written;
    }

    public function read(mixed $handle, int $length): string|false
    {
        if (!is_resource($handle)) {
            throw new RuntimeException('Invalid handle');
        }

        if ($length < 1) {
            throw new RuntimeException('Length must be at least 1');
        }

        // FFI OFF 環境での受信不可の警告（初回のみ）
        if (!self::$readWarningShown) {
            fwrite(
                STDERR,
                "Warning: Data reception does not work in FFI OFF environment. " .
                "Enable FFI for bidirectional communication.\n"
            );
            self::$readWarningShown = true;
        }

        // Windows環境では fread() がハングする問題があるため、
        // 1バイトずつ読み取って指定された長さまで蓄積する
        $buffer = '';
        $startTime = microtime(true);
        $timeoutMs = 100; // 100ms timeout

        for ($i = 0; $i < $length; $i++) {
            // 1バイト読み取り
            $char = @fgetc($handle);

            if ($char === false) {
                // データなし、またはエラー
                // タイムアウトチェック
                if ((microtime(true) - $startTime) * 1000 > $timeoutMs) {
                    break;
                }
                // データがない場合は次のバイトへ
                break;
            }

            $buffer .= $char;

            // データが来たらタイマーをリセット
            $startTime = microtime(true);
        }

        // バッファが空の場合は空文字列を返す
        return $buffer;
    }
}
