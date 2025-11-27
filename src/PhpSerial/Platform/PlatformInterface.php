<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use PhpSerial\Configuration;

interface PlatformInterface
{
    public function configure(string $device, Configuration $config): void;

    public function open(string $device): mixed;

    public function close(mixed $handle): void;

    public function write(mixed $handle, string $data): int;

    public function read(mixed $handle, int $length): string|false;
}
