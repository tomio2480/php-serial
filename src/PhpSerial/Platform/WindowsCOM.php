<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use COM;
use PhpSerial\Configuration;
use RuntimeException;

/**
 * Windows COM implementation for serial port communication
 * Requires php_com_dotnet extension
 */
class WindowsCOM implements PlatformInterface
{
    private ?object $port = null;

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
        // COMオブジェクトではopen時に設定を適用
    }

    public function open(string $device): mixed
    {
        $this->validateDevice($device);

        if (!extension_loaded('com_dotnet')) {
            throw new RuntimeException(
                'COM extension is not loaded. Please enable php_com_dotnet.dll in php.ini'
            );
        }

        try {
            // WScript.Shellを使用してCOMポートを開く方法もあるが、
            // 直接ファイルハンドルとして開く方が確実
            throw new RuntimeException('COM implementation is not yet complete');
        } catch (\Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to open serial port %s: %s', $device, $e->getMessage())
            );
        }
    }

    public function close(mixed $handle): void
    {
        if ($this->port !== null) {
            $this->port = null;
        }
    }

    public function write(mixed $handle, string $data): int
    {
        throw new RuntimeException('Not implemented');
    }

    public function read(mixed $handle, int $length): string|false
    {
        throw new RuntimeException('Not implemented');
    }
}
