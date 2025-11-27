<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\SerialPort;
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
