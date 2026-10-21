<?php

declare(strict_types=1);

// 内蔵 Web サーバー（cli-server SAPI）から実行する fixture．
// Web 系 SAPI では STDERR 定数が定義されないため，その環境で警告経路を通す．

use PhpSerial\Platform\Windows;

require __DIR__ . '/../../../vendor/autoload.php';

$platform = new Windows();

$handle = fopen('php://memory', 'r+b');
$platform->read($handle, 1);

echo 'ok';
