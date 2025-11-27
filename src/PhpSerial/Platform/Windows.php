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

        // mode コマンドの形式: mode COM1: BAUD=9600 PARITY=n DATA=8 STOP=1
        // Windows環境でcmd.exeを明示的に使用
        $modeCommand = sprintf(
            'mode %s: BAUD=%d PARITY=%s DATA=%d STOP=%d',
            $device,
            $config->getBaudRate(),
            $parity,
            $config->getDataBits(),
            $config->getStopBits()
        );

        $command = 'cmd.exe /c ' . escapeshellarg($modeCommand);

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                sprintf('Failed to configure serial port %s: %s', $device, implode("\n", $output))
            );
        }

        // 設定が反映されるまで少し待機
        usleep(100000); // 100ms
    }

    private mixed $winHandle = null;

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

        stream_set_blocking($handle, false);

        return $handle;
    }

    private function applyConfiguration(mixed $handle, Configuration $config): void
    {
        // PHPのfopen後、Windows APIを使ってボーレートを設定
        // stream_get_meta_dataからハンドルを取得することはできないため、
        // PHPレベルでの設定は限定的

        // 代替案: fopenではなく、Windows APIで直接ポートを開く
        // 現時点ではPHP標準機能の制約により、mode コマンドに頼るしかない
        // 実用的な解決策は別途実装が必要
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

        return fread($handle, $length);
    }
}
