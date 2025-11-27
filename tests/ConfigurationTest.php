<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new Configuration();

        $this->assertSame(9600, $config->getBaudRate());
        $this->assertSame(8, $config->getDataBits());
        $this->assertSame(Configuration::PARITY_NONE, $config->getParity());
        $this->assertSame(Configuration::STOP_BITS_1, $config->getStopBits());
    }

    public function testCustomConfiguration(): void
    {
        $config = new Configuration(
            baudRate: 115200,
            dataBits: 7,
            parity: Configuration::PARITY_EVEN,
            stopBits: Configuration::STOP_BITS_2
        );

        $this->assertSame(115200, $config->getBaudRate());
        $this->assertSame(7, $config->getDataBits());
        $this->assertSame(Configuration::PARITY_EVEN, $config->getParity());
        $this->assertSame(Configuration::STOP_BITS_2, $config->getStopBits());
    }

    public function testInvalidBaudRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid baud rate');

        new Configuration(baudRate: 99999);
    }

    public function testInvalidDataBits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data bits');

        new Configuration(dataBits: 9);
    }

    public function testInvalidParity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parity');

        new Configuration(parity: 'invalid');
    }

    public function testInvalidStopBits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid stop bits');

        new Configuration(stopBits: 3);
    }

    public function testSetBaudRate(): void
    {
        $config = new Configuration();
        $result = $config->setBaudRate(115200);

        $this->assertSame($config, $result);
        $this->assertSame(115200, $config->getBaudRate());
    }

    public function testSetDataBits(): void
    {
        $config = new Configuration();
        $result = $config->setDataBits(7);

        $this->assertSame($config, $result);
        $this->assertSame(7, $config->getDataBits());
    }

    public function testSetParity(): void
    {
        $config = new Configuration();
        $result = $config->setParity(Configuration::PARITY_ODD);

        $this->assertSame($config, $result);
        $this->assertSame(Configuration::PARITY_ODD, $config->getParity());
    }

    public function testSetStopBits(): void
    {
        $config = new Configuration();
        $result = $config->setStopBits(Configuration::STOP_BITS_2);

        $this->assertSame($config, $result);
        $this->assertSame(Configuration::STOP_BITS_2, $config->getStopBits());
    }

    public function testMethodChaining(): void
    {
        $config = new Configuration();
        $result = $config
            ->setBaudRate(115200)
            ->setDataBits(7)
            ->setParity(Configuration::PARITY_EVEN)
            ->setStopBits(Configuration::STOP_BITS_2);

        $this->assertSame($config, $result);
        $this->assertSame(115200, $config->getBaudRate());
        $this->assertSame(7, $config->getDataBits());
        $this->assertSame(Configuration::PARITY_EVEN, $config->getParity());
        $this->assertSame(Configuration::STOP_BITS_2, $config->getStopBits());
    }
}
