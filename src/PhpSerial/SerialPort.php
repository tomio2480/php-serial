<?php

declare(strict_types=1);

namespace PhpSerial;

use PhpSerial\Platform\PlatformInterface;
use PhpSerial\Platform\Windows;
use PhpSerial\Platform\WindowsFFI;
use PhpSerial\Platform\Unix;
use RuntimeException;

class SerialPort
{
    private PlatformInterface $platform;
    private mixed $handle = null;
    private bool $isOpen = false;

    public function __construct(
        private string $device,
        private ?Configuration $config = null
    ) {
        $this->config ??= new Configuration();
        $this->platform = $this->detectPlatform();
    }

    private function detectPlatform(): PlatformInterface
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => $this->createWindowsPlatform(),
            'Linux', 'Darwin' => new Unix(),
            default => throw new RuntimeException(
                sprintf('Unsupported platform: %s', PHP_OS_FAMILY)
            ),
        };
    }

    private function createWindowsPlatform(): PlatformInterface
    {
        // FFI が利用可能であれば WindowsFFI を使用
        if (extension_loaded('ffi')) {
            try {
                return new WindowsFFI();
            } catch (\Exception $e) {
                // FFI が使えない場合は従来の Windows 実装にフォールバック
                error_log('WindowsFFI initialization failed: ' . $e->getMessage());
            }
        }

        // FFI が使えない場合は従来の実装
        return new Windows();
    }

    public function open(): void
    {
        if ($this->isOpen) {
            throw new RuntimeException('Serial port is already open');
        }

        // Windowsではポートを開く前に設定を適用
        $this->platform->configure($this->device, $this->config);

        $this->handle = $this->platform->open($this->device);
        $this->isOpen = true;
    }

    public function close(): void
    {
        if (!$this->isOpen) {
            return;
        }

        $this->platform->close($this->handle);
        $this->handle = null;
        $this->isOpen = false;
    }

    public function write(string $data): int
    {
        $this->ensureOpen();
        return $this->platform->write($this->handle, $data);
    }

    public function read(int $length = 1024): string
    {
        $this->ensureOpen();

        $data = $this->platform->read($this->handle, $length);

        return $data === false ? '' : $data;
    }

    public function readLine(int $timeout = 1000): string
    {
        $this->ensureOpen();

        $startTime = microtime(true);
        $buffer = '';
        $foundNewline = false;

        while (true) {
            // 複数バイト読み込んで効率化
            $data = $this->platform->read($this->handle, 128);

            if ($data !== false && $data !== '') {
                $buffer .= $data;

                // 改行が含まれているかチェック
                if (strpos($buffer, "\n") !== false) {
                    $foundNewline = true;
                    break;
                }
            }

            // タイムアウトチェック
            if ((microtime(true) - $startTime) * 1000 > $timeout) {
                break;
            }

            // データがない場合は少し待機
            if ($data === false || $data === '') {
                usleep(10000); // 10ms待機
            }
        }

        // 改行で分割して最初の行を返す
        if ($foundNewline) {
            $lines = explode("\n", $buffer, 2);
            return rtrim($lines[0], "\r");
        }

        return rtrim($buffer, "\r\n");
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function getDevice(): string
    {
        return $this->device;
    }

    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    private function ensureOpen(): void
    {
        if (!$this->isOpen) {
            throw new RuntimeException('Serial port is not open');
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
