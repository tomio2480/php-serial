<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PhpSerial\SerialPort;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SerialPortTest extends TestCase
{
    private string $device;

    protected function setUp(): void
    {
        $this->device = getenv('TEST_SERIAL_PORT') ?: 'TEST_DEVICE';
    }

    public function testConstructorSetsDeviceAndConfiguration(): void
    {
        $port = new SerialPort($this->device);

        $this->assertSame($this->device, $port->getDevice());
        $this->assertInstanceOf(Configuration::class, $port->getConfiguration());
        $this->assertFalse($port->isOpen());
    }

    public function testConstructorWithCustomConfiguration(): void
    {
        $config = new Configuration(baudRate: 115200);
        $port = new SerialPort($this->device, $config);

        $this->assertSame($config, $port->getConfiguration());
        $this->assertSame(115200, $port->getConfiguration()->getBaudRate());
    }

    public function testWriteWithoutOpenThrowsException(): void
    {
        $port = new SerialPort($this->device);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serial port is not open');

        $port->write('test');
    }

    public function testReadWithoutOpenThrowsException(): void
    {
        $port = new SerialPort($this->device);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serial port is not open');

        $port->read();
    }

    public function testReadLineWithoutOpenThrowsException(): void
    {
        $port = new SerialPort($this->device);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serial port is not open');

        $port->readLine();
    }
}
