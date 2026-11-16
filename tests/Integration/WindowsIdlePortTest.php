<?php

declare(strict_types=1);

namespace PhpSerial\Tests\Integration;

use PhpSerial\Configuration;
use PhpSerial\Platform\WindowsFFI;
use PhpSerial\SerialPort;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 相手側が読まない COM ポートを使い、Windows の FFI 実装の後始末と時間の上限を確かめる。
 *
 * WINDOWS_IDLE_PORT に、対になるポートを誰も開いていない com0com のポートを指定したときだけ実行する。
 * serdevmock を止めた状態の COM10 が当たる。手順は docs/INTEGRATION_TESTING.md を参照する。
 */
#[Group('integration')]
final class WindowsIdlePortTest extends TestCase
{
    private const ENV_PORT = 'WINDOWS_IDLE_PORT';

    private const FIXTURE = __DIR__ . '/../fixtures/windows/write-to-idle-port.php';

    private const WRITE_LENGTH = 64;

    // 書き込みのタイムアウトに、子プロセスの起動時間を足した余裕を見込む
    private const WRITE_DEADLINE_SECONDS = 15.0;

    private const POLL_INTERVAL_US = 50000;

    private string $device = '';

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !extension_loaded('ffi')) {
            $this->markTestSkipped('Windows API を FFI で呼べる環境でのみ実行する');
        }

        // .env の値は phpdotenv が $_ENV へ入れるため、プロセスの環境変数より先に見る
        $device = $_ENV[self::ENV_PORT] ?? getenv(self::ENV_PORT);

        if (!is_string($device) || $device === '') {
            $this->markTestSkipped(self::ENV_PORT . ' が未設定のため、相手が読まないポートでの試験を飛ばす');
        }

        $this->device = $device;
    }

    public function testWriteReturnsWithinDeadlineWhenPeerIsNotReading(): void
    {
        $process = proc_open(
            [PHP_BINARY, self::FIXTURE, $this->device, (string) self::WRITE_LENGTH],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);

        $deadline = microtime(true) + self::WRITE_DEADLINE_SECONDS;

        while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
            usleep(self::POLL_INTERVAL_US);
        }

        $returned = !proc_get_status($process)['running'];

        if (!$returned) {
            proc_terminate($process);
        }

        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $this->assertTrue($returned, 'write() did not return within ' . self::WRITE_DEADLINE_SECONDS . ' seconds');
        $this->assertMatchesRegularExpression('/^(written:\d+|exception:.+)$/', $output, $errors);
        $this->assertNotSame('written:' . self::WRITE_LENGTH, $output, '相手が読まないのに全量を書けたと報告した');
    }

    public function testPortCanBeReopenedAfterConfigurationFails(): void
    {
        $failing = new class () extends WindowsFFI {
            public function applyConfiguration(Configuration $config): void
            {
                throw new RuntimeException('injected configuration failure');
            }
        };
        $failing->configure($this->device, new Configuration());

        try {
            $failing->open($this->device);
            $this->fail('open() should propagate the configuration failure');
        } catch (RuntimeException $e) {
            $this->assertSame('injected configuration failure', $e->getMessage());
        }

        // ハンドルが残っていれば、排他で開いたままのため次の open() が失敗する
        $port = new SerialPort($this->device);
        $port->open();
        $this->assertTrue($port->isOpen());
        $port->close();
    }
}
