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
    echo "Please copy .env.example to .env and set your serial port device.\n";
    exit(1);
}

try {
    $config = new Configuration(
        baudRate: 9600,
        dataBits: 8,
        parity: Configuration::PARITY_NONE,
        stopBits: Configuration::STOP_BITS_1
    );

    $port = new SerialPort($device, $config);

    echo "Opening serial port: {$device}\n";
    $port->open();

    echo "Waiting for Arduino to initialize...\n";
    sleep(2); // Arduinoのリセット待機

    echo "Writing data...\n";
    $port->write("Hello, Arduino!\n");

    echo "Reading response (timeout: 2 seconds)...\n";
    $response = $port->readLine(timeout: 2000);

    if ($response) {
        echo "Received: {$response}\n";
    } else {
        echo "No response received.\n";
    }

    echo "Closing port...\n";
    $port->close();

    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
