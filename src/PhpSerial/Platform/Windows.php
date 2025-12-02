<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\Configuration;
use RuntimeException;

class Windows implements PlatformInterface
{
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

        // セキュリティ: 数値パラメータの型と範囲を明示的に検証
        $baudRate = $config->getBaudRate();
        $dataBits = $config->getDataBits();
        $stopBits = $config->getStopBits();

        if (!is_int($baudRate) || $baudRate <= 0) {
            throw new RuntimeException('Invalid baud rate');
        }

        if (!is_int($dataBits) || $dataBits < 5 || $dataBits > 8) {
            throw new RuntimeException('Invalid data bits');
        }

        if (!is_int($stopBits) || ($stopBits !== 1 && $stopBits !== 2)) {
            throw new RuntimeException('Invalid stop bits');
        }

        // パリティは英字1文字のみ
        if (!preg_match('/^[noe]$/', $parity)) {
            throw new RuntimeException('Invalid parity');
        }

        // mode コマンドの形式: mode COM1: BAUD=9600 PARITY=n DATA=8 STOP=1
        // escapeshellarg() を使うとダブルクォーテーションが追加されてコマンドが失敗する
        // デバイス名とパラメータは上記で厳格にバリデーション済みなので直接使用
        $modeCommand = sprintf(
            'mode %s: BAUD=%d PARITY=%s DATA=%d STOP=%d',
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

        // Windows環境ではブロッキングモードで動作させる
        // ノンブロッキングモードでは受信がうまく機能しないことがある
        stream_set_blocking($handle, true);

        // タイムアウトを設定（読み取り時に無限待機を防ぐ）
        stream_set_timeout($handle, 0, 100000); // 100ms

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

        return fread($handle, $length);
    }
}
