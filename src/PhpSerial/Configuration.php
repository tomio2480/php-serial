<?php

declare(strict_types=1);

namespace PhpSerial;

class Configuration
{
    public const PARITY_NONE = 'none';
    public const PARITY_ODD = 'odd';
    public const PARITY_EVEN = 'even';

    public const STOP_BITS_1 = 1;
    public const STOP_BITS_2 = 2;

    private const VALID_BAUD_RATES = [
        110, 300, 600, 1200, 2400, 4800, 9600,
        14400, 19200, 38400, 57600, 115200, 230400,
    ];

    private const VALID_DATA_BITS = [5, 6, 7, 8];

    private const VALID_PARITIES = [
        self::PARITY_NONE,
        self::PARITY_ODD,
        self::PARITY_EVEN,
    ];

    private const VALID_STOP_BITS = [
        self::STOP_BITS_1,
        self::STOP_BITS_2,
    ];

    public function __construct(
        private int $baudRate = 9600,
        private int $dataBits = 8,
        private string $parity = self::PARITY_NONE,
        private int $stopBits = self::STOP_BITS_1
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!in_array($this->baudRate, self::VALID_BAUD_RATES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid baud rate: %d', $this->baudRate)
            );
        }

        if (!in_array($this->dataBits, self::VALID_DATA_BITS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid data bits: %d', $this->dataBits)
            );
        }

        if (!in_array($this->parity, self::VALID_PARITIES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid parity: %s', $this->parity)
            );
        }

        if (!in_array($this->stopBits, self::VALID_STOP_BITS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid stop bits: %d', $this->stopBits)
            );
        }
    }

    public function getBaudRate(): int
    {
        return $this->baudRate;
    }

    public function setBaudRate(int $baudRate): self
    {
        $this->baudRate = $baudRate;
        $this->validate();

        return $this;
    }

    public function getDataBits(): int
    {
        return $this->dataBits;
    }

    public function setDataBits(int $dataBits): self
    {
        $this->dataBits = $dataBits;
        $this->validate();

        return $this;
    }

    public function getParity(): string
    {
        return $this->parity;
    }

    public function setParity(string $parity): self
    {
        $this->parity = $parity;
        $this->validate();

        return $this;
    }

    public function getStopBits(): int
    {
        return $this->stopBits;
    }

    public function setStopBits(int $stopBits): self
    {
        $this->stopBits = $stopBits;
        $this->validate();

        return $this;
    }
}
