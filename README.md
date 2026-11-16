# PHP Serial

<!-- 本ファイルは利用者向けの手引きのため「ですます調」で書く．句点は他の文書と同じく「。」で統一している． -->
<!-- textlint-disable ja-technical-writing/no-mix-dearu-desumasu, ja-technical-writing/ja-no-mixed-period -->

PHP でマイコンとシリアル通信するクロスプラットフォームライブラリ。

## 概要

PySerial や Ruby serialport のような使い勝手を提供。
Windows、Mac、Linux で動作し、Pure PHP 実装。

## 動作環境

- PHP 8.1 以上
- Windows、Linux、macOS

PHP 本体のセキュリティ修正が提供されているバージョン（[サポート対象](https://www.php.net/supported-versions.php)）で、最新のパッチリリースを使うことを推奨します。

## インストール

Composer を使用してインストール:

```bash
composer require tomio2480/php-serial
```

特定のバージョンを指定する場合:

```bash
composer require tomio2480/php-serial:^1.3
```

## 基本的な使い方

```php
<?php

require 'vendor/autoload.php';

use PhpSerial\Configuration;
use PhpSerial\SerialPort;

$config = new Configuration(
    baudRate: 9600,
    dataBits: 8,
    parity: Configuration::PARITY_NONE,
    stopBits: Configuration::STOP_BITS_1
);

$port = new SerialPort('/dev/ttyUSB0', $config);
$port->open();

// データ送信
$port->write("Hello, Arduino!\n");

// データ受信
$response = $port->readLine(timeout: 2000);
echo "Received: {$response}\n";

$port->close();
```

`write(string $data)` は実際に書き込めたバイト数を返します。
相手が受け取れないと、渡した長さより少ない値（0 を含む）が返ります。
Linux と macOS はポートを非ブロッキングで開くため、送信バッファの空きの分だけ書きます。
Windows では、書き込みの上限を「送信時間 + 1 秒」とし、超えたら打ち切ります。
戻り値を確かめ、書き残した分は送り直してください。

`read(int $length = 1024)` は最大 `$length` バイトを返します。
1 回で確保するバッファの上限は 64 KiB で、上限を超える指定は上限へ丸めます。
1 未満を指定すると `RuntimeException` を送出します。

## 設定例

### ボーレート

対応するボーレート（bps）は次のとおりです。

```text
110, 300, 600, 1200, 2400, 4800, 9600, 14400, 19200, 38400, 57600, 115200, 230400
```

```php
$config = new Configuration(baudRate: 115200);
```

### パリティ

```php
use PhpSerial\Configuration;

// パリティなし
$config = new Configuration(parity: Configuration::PARITY_NONE);

// 奇数パリティ
$config = new Configuration(parity: Configuration::PARITY_ODD);

// 偶数パリティ
$config = new Configuration(parity: Configuration::PARITY_EVEN);
```

### データビット

対応: 5, 6, 7, 8

```php
$config = new Configuration(dataBits: 8);
```

### ストップビット

```php
use PhpSerial\Configuration;

$config = new Configuration(stopBits: Configuration::STOP_BITS_1);
// または
$config = new Configuration(stopBits: Configuration::STOP_BITS_2);
```

## メソッドチェーン

```php
$config = new Configuration();
$config
    ->setBaudRate(115200)
    ->setDataBits(8)
    ->setParity(Configuration::PARITY_NONE)
    ->setStopBits(Configuration::STOP_BITS_1);
```

## デバイスパス

### Windows

```php
$port = new SerialPort('COM3');
```

**重要:** Windows 環境では PHP FFI 拡張の有効化を強く推奨します。

正確で安定したシリアル通信を実現するため、`php.ini` で FFI を有効にしてください：

```ini
extension=ffi
ffi.enable=true
```

FFI 無効時はフォールバック実装が使用されますが、**データ受信が動作せず送信のみ可能**となります。
詳細は [docs/WINDOWS_FFI_SETUP.md](docs/WINDOWS_FFI_SETUP.md) を参照してください。

### Linux

```php
$port = new SerialPort('/dev/ttyUSB0');
// または
$port = new SerialPort('/dev/ttyACM0');
```

### macOS

```php
$port = new SerialPort('/dev/tty.usbserial');
```

Linux と macOS では、`open()` が `stty` でポートを raw モードに設定します。
改行の変換や制御文字の解釈をせず、受け取ったバイトをそのまま扱います。

## テスト

環境変数でテスト用デバイスを指定:

```bash
# .env.exampleを.envにコピー
cp .env.example .env

# .envファイルでTEST_SERIAL_PORTを設定
# TEST_SERIAL_PORT=COM3

# テスト実行
composer test
```

実機なしで送受信を確かめる結合テストもあります。
serdevmock と仮想ポートの対を使います。
手順は [docs/INTEGRATION_TESTING.md](docs/INTEGRATION_TESTING.md) を参照してください。

## 使用例

基本的な使用例は [examples/basic.php](examples/basic.php) を参照。
複数コマンド送信の例は [examples/advanced.php](examples/advanced.php) を参照。

## 開発

### 品質確認

すべての品質チェックを一度に実行:

```bash
composer qa
```

個別チェック:

```bash
composer cs:check      # PSR-12準拠チェック
composer cs:fixer      # コードスタイルチェック
composer stan          # PHPStan静的解析 (レベル8)
composer test          # テスト実行
```

詳細は [CONTRIBUTING.md](CONTRIBUTING.md) を参照してください。

## ライセンス

MIT
