<?php

declare(strict_types=1);

namespace PhpSerial\Tests;

use PhpSerial\Platform\PlatformInterface;
use PhpSerial\Platform\ReadLength;
use PhpSerial\Platform\Unix;
use PhpSerial\Platform\Windows;
use PhpSerial\Platform\WindowsFFI;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReadLengthTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function acceptedLengthProvider(): array
    {
        return [
            'minimum' => [1, 1],
            'maximum' => [ReadLength::MAX, ReadLength::MAX],
            'just above maximum' => [ReadLength::MAX + 1, ReadLength::MAX],
            'PHP_INT_MAX' => [PHP_INT_MAX, ReadLength::MAX],
        ];
    }

    #[DataProvider('acceptedLengthProvider')]
    public function testNormalizeClampsLengthToMaximum(int $length, int $expected): void
    {
        $this->assertSame($expected, ReadLength::normalize($length));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function rejectedLengthProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'PHP_INT_MIN' => [PHP_INT_MIN],
        ];
    }

    #[DataProvider('rejectedLengthProvider')]
    public function testNormalizeRejectsLengthBelowOne(int $length): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Length must be at least 1');

        ReadLength::normalize($length);
    }

    /**
     * @return array<string, array{PlatformInterface}>
     */
    public static function streamPlatformProvider(): array
    {
        return [
            'Unix' => [new Unix()],
            'Windows' => [new Windows()],
        ];
    }

    #[DataProvider('streamPlatformProvider')]
    public function testStreamPlatformReadsAtMostMaximumLength(PlatformInterface $platform): void
    {
        $handle = fopen('php://memory', 'r+b');
        $this->assertIsResource($handle);
        fwrite($handle, str_repeat('a', ReadLength::MAX + 10));
        rewind($handle);

        $data = $platform->read($handle, ReadLength::MAX + 10);

        $this->assertIsString($data);
        $this->assertSame(ReadLength::MAX, strlen($data));
    }

    public function testWindowsFfiRejectsLengthBelowOneBeforeAllocating(): void
    {
        $platform = new WindowsFFI();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Length must be at least 1');

        $platform->read('handle', 0);
    }
}
