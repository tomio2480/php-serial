<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpSerial\Configuration;
use PhpSerial\SerialPort;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$device = $_ENV['SERIAL_PORT'] ?? $_SERVER['SERIAL_PORT'] ?? false;

if (!$device) {
    echo "Error: SERIAL_PORT environment variable not set.\n";
    exit(1);
}

try {
    $config = new Configuration(
        baudRate: 115200,
        dataBits: 8,
        parity: Configuration::PARITY_NONE,
        stopBits: Configuration::STOP_BITS_1
    );

    $port = new SerialPort($device, $config);

    echo "Opening serial port: {$device} at 115200 baud\n";
    $port->open();

    echo "Waiting for Arduino to initialize...\n";
    sleep(2); // Arduinoのリセット待機

    // Arduino の起動メッセージを読み捨て
    $startupMessage = $port->readLine(timeout: 1000);
    if ($startupMessage) {
        echo "Arduino startup: {$startupMessage}\n\n";
    }

    echo "Sending multiple commands...\n\n";

    $commands = [
        "LED_ON\n",
        "GET_TEMP\n",
        "GET_STATUS\n",
    ];

    foreach ($commands as $cmd) {
        echo "Sending: " . trim($cmd) . "\n";
        $port->write($cmd);

        // Arduinoの処理とレスポンス待機
        usleep(200000); // 200ms待機

        $response = $port->readLine(timeout: 2000);
        echo "Response: " . ($response ?: "(none)") . "\n\n";

        // 次のコマンド前に少し待機
        usleep(100000); // 100ms待機
    }

    $port->close();
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
