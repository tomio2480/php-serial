# Arduino テストコード

PHPシリアル通信ライブラリのテスト用Arduinoスケッチ。

## ファイル

### echo.ino

受信した文字列をそのまま返すエコープログラム。

**対応するPHPコード**: `examples/basic.php`

**動作**:
1. シリアル通信を9600bpsで開始
2. 受信した文字列に"Echo: "を付けて返信

**使い方**:
1. Arduino IDEで`echo.ino`を開く
2. Arduinoに書き込む
3. `.env`ファイルで接続ポートを設定
4. `php examples/basic.php`を実行

### led_control.ino

LEDを制御するコマンドプログラム。

**対応するPHPコード**: `examples/advanced.php`

**ボーレート**: 115200

**コマンド**:
- `LED_ON`: 内蔵LED（ピン13）を点灯
- `LED_OFF`: 内蔵LEDを消灯
- `GET_STATUS`: LED状態を取得
- `GET_TEMP`: 疑似温度データを取得

**使い方**:
1. Arduino IDEで`led_control.ino`を開く
2. Arduinoに書き込む
3. `.env`ファイルで接続ポートを設定
4. `php examples/advanced.php`を実行

## 必要な機材

- Arduino Uno、Nano、またはMega
- USBケーブル

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

Arduino IDEのシリアルモニタを開いているとポートが使用中になります。
PHPスクリプト実行前にシリアルモニタを閉じてください。

### ボーレートの確認

- `echo.ino`: 9600bps
- `led_control.ino`: 115200bps

PHPコードとArduinoコードのボーレートが一致していることを確認してください。
