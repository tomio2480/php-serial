<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenvPath = __DIR__ . '/../.env';

if (file_exists($dotenvPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}
