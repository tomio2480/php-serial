# Arduino テストコード

<!-- 本ファイルは利用者向けの手引きのため「ですます調」で書く．句点は他の文書と同じく「。」で統一している． -->
<!-- textlint-disable ja-technical-writing/no-mix-dearu-desumasu, ja-technical-writing/ja-no-mixed-period -->

PHP シリアル通信ライブラリのテスト用 Arduino スケッチ。

## ファイル

### echo.ino

受信した文字列をそのまま返すエコープログラム。

**対応するPHPコード**: `examples/basic.php`

**動作**:

1. シリアル通信を 9600bps で開始
2. 受信した文字列に"Echo: "を付けて返信

**使い方**:

1. Arduino IDE で `echo.ino` を開く
2. Arduino に書き込む
3. `.env` ファイルで接続ポートを設定
4. `php examples/basic.php` を実行

### led_control.ino

LED を制御するコマンドプログラム。

**対応するPHPコード**: `examples/advanced.php`

**ボーレート**: 115200

**コマンド**:

- `LED_ON`: 内蔵 LED（ピン 13）を点灯
- `LED_OFF`: 内蔵 LED を消灯
- `GET_STATUS`: LED 状態を取得
- `GET_TEMP`: 疑似温度データを取得

**使い方**:

1. Arduino IDE で `led_control.ino` を開く
2. Arduino に書き込む
3. `.env` ファイルで接続ポートを設定
4. `php examples/advanced.php` を実行

## 必要な機材

- Arduino Uno、Nano、または Mega
- USB ケーブル

## 注意事項

### ポート番号の確認

**Windows**:

- デバイスマネージャーで確認
- 例: COM3, COM4

**Linux**:

```bash
ls /dev/ttyUSB* /dev/ttyACM*
```

**macOS**:

```bash
ls /dev/tty.usb*
```

### シリアルモニタとの競合

Arduino IDE のシリアルモニタを開いているとポートが使用中になります。
PHP スクリプト実行前にシリアルモニタを閉じてください。

### ボーレートの確認

- `echo.ino`: 9600bps
- `led_control.ino`: 115200bps

PHP コードと Arduino コードのボーレートが一致していることを確認してください。
