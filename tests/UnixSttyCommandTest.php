<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Configuration;
use PhpSerial\Platform\Unix;
use PHPUnit\Framework\TestCase;

/**
 * Linux / macOS でポートを設定する stty のコマンドの組み立てを確かめる。
 * コマンドは実行しないため、どの OS でも実行する。
 */
class UnixSttyCommandTest extends TestCase
{
    public function testLinuxUsesUpperCaseDeviceOption(): void
    {
        $command = Unix::buildConfigureCommand('/dev/ttyUSB0', new Configuration(), 'Linux');

        $this->assertStringStartsWith('stty -F ' . escapeshellarg('/dev/ttyUSB0') . ' ', $command);
    }

    public function testMacOsUsesLowerCaseDeviceOption(): void
    {
        // BSD 系の stty は -f でデバイスを受け取る。-F は受け付けず、標準入力を設定しようとして失敗する
        $command = Unix::buildConfigureCommand('/dev/tty.usbserial', new Configuration(), 'Darwin');

        $this->assertStringStartsWith('stty -f ' . escapeshellarg('/dev/tty.usbserial') . ' ', $command);
    }

    public function testCommandCapturesErrorOutput(): void
    {
        // 失敗の理由を例外メッセージへ残すため、標準エラー出力も受け取る
        $command = Unix::buildConfigureCommand('/dev/ttyUSB0', new Configuration(), 'Linux');

        $this->assertStringEndsWith(' 2>&1', $command);
    }

    public function testRawModeComesBeforeLineSettings(): void
    {
        // 端末の行編集や特殊文字（0x0F・0x16 など）の処理を止め、受け取ったバイトをそのまま扱う。
        // macOS の raw はデータビットとパリティも上書きするため、回線の設定より前に置く
        $command = Unix::buildConfigureCommand('/dev/tty.usbserial', new Configuration(), 'Darwin');

        $rawPosition = strpos($command, ' raw -echo -iexten ');
        $dataBitsPosition = strpos($command, ' cs');

        $this->assertNotFalse($rawPosition);
        $this->assertNotFalse($dataBitsPosition);
        $this->assertLessThan($dataBitsPosition, $rawPosition);
    }

    public function testCommandContainsLineSettings(): void
    {
        $config = new Configuration(
            baudRate: 115200,
            dataBits: 7,
            parity: Configuration::PARITY_EVEN,
            stopBits: Configuration::STOP_BITS_2
        );

        $command = Unix::buildConfigureCommand('/dev/ttyUSB0', $config, 'Linux');

        // escapeshellarg() の引用符は実行中の OS で変わるため、期待値も同じ関数で作る
        $expected = sprintf(
            'stty -F %s raw -echo -iexten %s cs%s parenb -parodd cstopb 2>&1',
            escapeshellarg('/dev/ttyUSB0'),
            escapeshellarg('115200'),
            escapeshellarg('7')
        );

        $this->assertSame($expected, $command);
    }
}
