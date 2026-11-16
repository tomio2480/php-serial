<?php

declare(strict_types=1);

// WindowsIdlePortTest が別プロセスで起動する。
// 書き込みが戻らない場合でも、テスト側のプロセスを止めずに済ませるためである。
// 使い方: php write-to-idle-port.php <COM ポート> <書き込むバイト数>

require __DIR__ . '/../../../vendor/autoload.php';

use PhpSerial\Configuration;
use PhpSerial\SerialPort;

$args = $_SERVER['argv'] ?? [];
$device = $args[1] ?? null;
$length = filter_var($args[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!is_string($device) || !is_int($length)) {
    fwrite(STDERR, "usage: php write-to-idle-port.php <COM port> <bytes>\n");
    exit(2);
}

$port = new SerialPort($device, new Configuration(baudRate: 9600));
$port->open();

try {
    $written = $port->write(str_repeat('x', $length));
    echo "written:{$written}";
} catch (RuntimeException $e) {
    echo "exception:{$e->getMessage()}";
} finally {
    $port->close();
}
