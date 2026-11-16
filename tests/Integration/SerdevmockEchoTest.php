<?php

declare(strict_types=1);

namespace PhpSerial\Tests\Integration;

use PhpSerial\Configuration;
use PhpSerial\SerialPort;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * serdevmock をエコーモードで動かした仮想ポート対に対する結合テスト。
 *
 * SERDEVMOCK_PORT に php-serial 側のポートを指定したときだけ実行する。
 * 対になるポートでは tests/fixtures/serdevmock/echo.json を読ませた serdevmock を動かしておく。
 * 準備の手順は docs/INTEGRATION_TESTING.md を参照する。
 */
#[Group('integration')]
final class SerdevmockEchoTest extends TestCase
{
    private const ENV_PORT = 'SERDEVMOCK_PORT';

    // tests/fixtures/serdevmock/echo.json の baudrate と合わせる
    private const BAUD_RATE = 9600;

    private const DRAIN_MS = 100;

    private const TIMEOUT_MS = 2000;

    private const POLL_INTERVAL_US = 10000;

    private ?SerialPort $port = null;

    protected function setUp(): void
    {
        // .env の値は phpdotenv が $_ENV へ入れるため、プロセスの環境変数より先に見る
        $device = $_ENV[self::ENV_PORT] ?? getenv(self::ENV_PORT);

        if (!is_string($device) || $device === '') {
            $this->markTestSkipped(self::ENV_PORT . ' が未設定のため、serdevmock との結合テストを飛ばす');
        }

        $this->port = new SerialPort($device, new Configuration(baudRate: self::BAUD_RATE));
        $this->port->open();
        $this->drain();
    }

    protected function tearDown(): void
    {
        $this->port?->close();
        $this->port = null;
    }

    public function testWrittenTextIsEchoedBack(): void
    {
        $payload = "hello serdevmock\n";

        $this->assertSame(strlen($payload), $this->openPort()->write($payload));
        $this->assertSame($payload, $this->readExactly(strlen($payload)));
    }

    public function testAllByteValuesSurviveRoundTrip(): void
    {
        $payload = implode('', array_map('chr', range(0, 255)));

        $this->assertSame(strlen($payload), $this->openPort()->write($payload));
        $this->assertSame(bin2hex($payload), bin2hex($this->readExactly(strlen($payload))));
    }

    public function testReadLineReturnsEchoedLineWithoutTerminator(): void
    {
        $this->openPort()->write("PING\r\n");

        $this->assertSame('PING', $this->openPort()->readLine(self::TIMEOUT_MS));
    }

    private function openPort(): SerialPort
    {
        $this->assertNotNull($this->port);

        return $this->port;
    }

    /**
     * 前のテストが読み残したエコーを捨て、テスト同士が干渉しないようにする。
     */
    private function drain(): void
    {
        $deadline = microtime(true) + self::DRAIN_MS / 1000;

        while (microtime(true) < $deadline) {
            if ($this->openPort()->read() === '') {
                usleep(self::POLL_INTERVAL_US);
            }
        }
    }

    /**
     * 指定バイト数がそろうか、タイムアウトするまで読み続ける。
     */
    private function readExactly(int $length): string
    {
        $buffer = '';
        $deadline = microtime(true) + self::TIMEOUT_MS / 1000;

        while (strlen($buffer) < $length && microtime(true) < $deadline) {
            $chunk = $this->openPort()->read($length - strlen($buffer));

            if ($chunk === '') {
                usleep(self::POLL_INTERVAL_US);
                continue;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
