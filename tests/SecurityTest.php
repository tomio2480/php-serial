<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PhpSerial\Platform\PlatformInterface;
use PhpSerial\Platform\Unix;
use PhpSerial\Platform\Windows;
use PhpSerial\Platform\WindowsCOM;
use PhpSerial\Platform\WindowsFFI;
use PhpSerial\SerialPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecurityTest extends TestCase
{
    public function testCommandInjectionPrevention(): void
    {
        $maliciousDevices = [
            'COM3 & del test.txt',
            'COM3; rm -rf /',
            'COM3 | echo hacked',
            '/dev/ttyUSB0; echo hacked',
            '/dev/ttyUSB0 && cat /etc/passwd',
            '../../../etc/passwd',
            'COM3$(whoami)',
        ];

        foreach ($maliciousDevices as $device) {
            try {
                $port = new SerialPort($device);
                $port->open();
                $this->fail("Expected RuntimeException for malicious device: {$device}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Invalid', $e->getMessage());
            }
        }
    }

    /**
     * PCRE の $ は末尾の改行の手前でも一致するため，改行付きの名前を個別に確かめる．
     *
     * @return array<string, array{PlatformInterface, string}>
     */
    public static function trailingNewlineDeviceProvider(): array
    {
        return [
            'Unix' => [new Unix(), "/dev/ttyUSB0\n"],
            'Unix (tty.*)' => [new Unix(), "/dev/tty.usbserial\n"],
            'Windows' => [new Windows(), "COM1\n"],
            'WindowsFFI' => [new WindowsFFI(), "COM1\n"],
        ];
    }

    #[DataProvider('trailingNewlineDeviceProvider')]
    public function testDeviceWithTrailingNewlineIsRejected(PlatformInterface $platform, string $device): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid');

        $platform->configure($device, new Configuration());
    }

    public function testWindowsComRejectsDeviceWithTrailingNewline(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid');

        (new WindowsCOM())->open("COM1\n");
    }

    public function testValidDeviceNames(): void
    {
        $validDevices = [
            'COM1',
            'COM3',
            '/dev/ttyUSB0',
            '/dev/ttyACM0',
            '/dev/tty.usbserial',
        ];

        foreach ($validDevices as $device) {
            $port = new SerialPort($device);
            $this->assertSame($device, $port->getDevice());
        }
    }
}
