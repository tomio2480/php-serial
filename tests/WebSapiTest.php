<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Web 系 SAPI での実行を，PHP 内蔵 Web サーバーの実プロセスで確かめる．
 */
class WebSapiTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/web-sapi';
    private const HOST = '127.0.0.1';
    private const READY_TIMEOUT_SECONDS = 5.0;
    private const POLL_INTERVAL_MICROSECONDS = 50000;

    public function testWindowsPlatformWarningsDoNotCauseFatalErrorOnWebSapi(): void
    {
        $address = self::HOST . ':' . $this->findFreePort();
        $logFile = tempnam(sys_get_temp_dir(), 'php-serial-web-sapi-');
        $this->assertNotFalse($logFile);

        // -n で php.ini を読まず，display_errors などを既定値に固定する
        $process = proc_open(
            [PHP_BINARY, '-n', '-S', $address, '-t', self::FIXTURE_DIR],
            [1 => ['file', $logFile, 'w'], 2 => ['file', $logFile, 'a']],
            $pipes
        );
        $this->assertIsResource($process);

        try {
            $this->waitUntilListening($address);
            $body = $this->fetch("http://{$address}/windows-warnings.php");
        } finally {
            proc_terminate($process);
            proc_close($process);
        }

        $log = (string) file_get_contents($logFile);
        unlink($logFile);

        $this->assertSame('ok', $body, "Server log:\n{$log}");
        $this->assertStringContainsString('PHP FFI extension is not enabled', $log);
        $this->assertStringContainsString('Data reception does not work in FFI OFF environment', $log);
    }

    private function findFreePort(): int
    {
        $server = stream_socket_server('tcp://' . self::HOST . ':0');
        $this->assertNotFalse($server);
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitUntilListening(string $address): void
    {
        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            // 起動前の接続拒否は想定内のため，警告を抑止して再試行する
            $socket = @stream_socket_client("tcp://{$address}");

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        $this->fail("Built-in web server did not start on {$address}");
    }

    private function fetch(string $url): string
    {
        // 500 応答でも本文を受け取り，fatal error の内容を失敗メッセージへ残す
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $body = file_get_contents($url, false, $context);
        $this->assertNotFalse($body);

        return $body;
    }
}
