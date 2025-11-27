<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\Configuration;
use RuntimeException;

class Unix implements PlatformInterface
{
    private function validateDevice(string $device): void
    {
        if (!preg_match('#^/dev/(tty(USB|ACM|S|AMA)?\d+|tty\.[a-zA-Z0-9_\-]+)$#', $device)) {
            throw new RuntimeException(
                sprintf('Invalid Unix device path: %s (expected format: /dev/ttyUSB0, /dev/tty.usbserial, etc.)', $device)
            );
        }
    }

    public function configure(string $device, Configuration $config): void
    {
        $this->validateDevice($device);
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

        $command = sprintf(
            'stty -F %s %d cs%d %s %s',
            escapeshellarg($device),
            $config->getBaudRate(),
            $config->getDataBits(),
            $parity,
            $stopBits
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                sprintf('Failed to configure serial port: %s', implode("\n", $output))
            );
        }
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
