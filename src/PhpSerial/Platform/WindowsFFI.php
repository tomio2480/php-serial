<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use FFI;
use PhpSerial\Configuration;
use PhpSerial\TimeoutException;
use RuntimeException;
use Throwable;

/**
 * Windows implementation using FFI to call Windows API directly
 * This allows proper baud rate configuration
 */
class WindowsFFI implements PlatformInterface
{
    // CreateFileA が失敗時に返す値（(HANDLE)-1）
    private const INVALID_HANDLE_VALUE = -1;

    // DCB.StopBits の値（winbase.h の ONESTOPBIT と TWOSTOPBITS）
    private const DCB_ONESTOPBIT = 0;
    private const DCB_TWOSTOPBITS = 2;

    /**
     * プロセス内で共有する FFI インスタンス。
     *
     * インスタンスごとに FFI::cdef() していた頃、PHPUnit で同じプロセスの 2 件目のテストが
     * ポートを開くと、DCB のフィールドへの代入で FFI\Exception が出た。
     * 原因は特定できていない（PHPUnit の外では再現しなかった）。
     * 回避策として一度だけ作って保持し続け、破棄させない。
     */
    private static ?FFI $sharedFfi = null;

    private ?FFI $ffi = null;
    private mixed $handle = null;
    private ?Configuration $config = null;

    private function validateDevice(string $device): void
    {
        // D 修飾子: $ を末尾の改行の手前で一致させない
        if (!preg_match('/^COM\d+$/iD', $device)) {
            throw new RuntimeException(
                sprintf('Invalid Windows COM port name: %s (expected format: COM1, COM2, etc.)', $device)
            );
        }
    }

    private function initFFI(): void
    {
        if ($this->ffi !== null) {
            return;
        }

        if (self::$sharedFfi !== null) {
            $this->ffi = self::$sharedFfi;

            return;
        }

        if (!extension_loaded('ffi')) {
            throw new RuntimeException(
                'FFI extension is not loaded. Please enable ffi.enable=true in php.ini'
            );
        }

        // Windows API definitions
        $code = <<<'CDEF'
        typedef void* HANDLE;
        typedef unsigned long DWORD;
        typedef unsigned short WORD;
        typedef unsigned char BYTE;

        // CreateFile constants
        #define GENERIC_READ  0x80000000
        #define GENERIC_WRITE 0x40000000
        #define OPEN_EXISTING 3
        #define FILE_ATTRIBUTE_NORMAL 0x80
        #define INVALID_HANDLE_VALUE ((HANDLE)-1)

        // Serial port structures
        typedef struct {
            DWORD DCBlength;
            DWORD BaudRate;
            DWORD fBinary  :1;
            DWORD fParity  :1;
            DWORD fOutxCtsFlow  :1;
            DWORD fOutxDsrFlow  :1;
            DWORD fDtrControl  :2;
            DWORD fDsrSensitivity  :1;
            DWORD fTXContinueOnXoff  :1;
            DWORD fOutX  :1;
            DWORD fInX  :1;
            DWORD fErrorChar  :1;
            DWORD fNull  :1;
            DWORD fRtsControl  :2;
            DWORD fAbortOnError  :1;
            DWORD fDummy2  :17;
            WORD wReserved;
            WORD XonLim;
            WORD XoffLim;
            BYTE ByteSize;
            BYTE Parity;
            BYTE StopBits;
            char XonChar;
            char XoffChar;
            char ErrorChar;
            char EofChar;
            char EvtChar;
            WORD wReserved1;
        } DCB;

        typedef struct {
            DWORD ReadIntervalTimeout;
            DWORD ReadTotalTimeoutMultiplier;
            DWORD ReadTotalTimeoutConstant;
            DWORD WriteTotalTimeoutMultiplier;
            DWORD WriteTotalTimeoutConstant;
        } COMMTIMEOUTS;

        // Windows API functions
        HANDLE CreateFileA(const char* lpFileName, DWORD dwDesiredAccess, DWORD dwShareMode,
                          void* lpSecurityAttributes, DWORD dwCreationDisposition,
                          DWORD dwFlagsAndAttributes, HANDLE hTemplateFile);
        int CloseHandle(HANDLE hObject);
        int GetCommState(HANDLE hFile, DCB* lpDCB);
        int SetCommState(HANDLE hFile, DCB* lpDCB);
        int SetCommTimeouts(HANDLE hFile, COMMTIMEOUTS* lpCommTimeouts);
        int ReadFile(HANDLE hFile, void* lpBuffer, DWORD nNumberOfBytesToRead,
                    DWORD* lpNumberOfBytesRead, void* lpOverlapped);
        int WriteFile(HANDLE hFile, const void* lpBuffer, DWORD nNumberOfBytesToWrite,
                     DWORD* lpNumberOfBytesWritten, void* lpOverlapped);
        CDEF;

        self::$sharedFfi = FFI::cdef($code, 'kernel32.dll');
        $this->ffi = self::$sharedFfi;
    }

    public function configure(string $device, Configuration $config): void
    {
        $this->validateDevice($device);
        $this->config = $config;
    }

    public function open(string $device): mixed
    {
        $this->validateDevice($device);
        $this->initFFI();

        if ($this->ffi === null) {
            throw new RuntimeException('FFI initialization failed');
        }

        // Open COM port using Windows API
        $devicePath = "\\\\.\\{$device}";

        $handle = $this->ffi->CreateFileA(
            $devicePath,
            0x80000000 | 0x40000000, // GENERIC_READ | GENERIC_WRITE
            0,                        // dwShareMode (no sharing)
            null,                     // lpSecurityAttributes
            3,                        // OPEN_EXISTING
            0x80,                     // FILE_ATTRIBUTE_NORMAL
            null                      // hTemplateFile
        );

        // CData 同士の === はオブジェクトの同一性を比べ，常に偽になる．
        // == はポインタ値を比べるため，INVALID_HANDLE_VALUE の判定にはこちらを使う．
        if ($handle == $this->ffi->cast('HANDLE', self::INVALID_HANDLE_VALUE)) {
            throw new RuntimeException(
                sprintf('Failed to open serial port: %s', $device)
            );
        }

        $this->handle = $handle;

        // Apply configuration immediately after opening
        if ($this->config !== null) {
            try {
                $this->applyConfiguration($this->config);
            } catch (Throwable $e) {
                // 排他で開いたハンドルを残すと、同じポートを二度と開けなくなる
                $this->ffi->CloseHandle($handle);
                $this->handle = null;

                throw $e;
            }
        }

        return $handle;
    }

    public function applyConfiguration(Configuration $config): void
    {
        if ($this->handle === null) {
            throw new RuntimeException('Port is not open');
        }

        if ($this->ffi === null) {
            throw new RuntimeException('FFI is not initialized');
        }

        // Get current DCB
        $dcb = $this->ffi->new('DCB');
        $dcb->DCBlength = FFI::sizeof($dcb);

        if (!$this->ffi->GetCommState($this->handle, FFI::addr($dcb))) {
            throw new RuntimeException('Failed to get COM state');
        }

        // Set baud rate and other parameters
        $dcb->BaudRate = $config->getBaudRate();
        $dcb->ByteSize = $config->getDataBits();

        // Parity
        $parityMap = [
            Configuration::PARITY_NONE => 0,
            Configuration::PARITY_ODD => 1,
            Configuration::PARITY_EVEN => 2,
        ];
        $dcb->Parity = $parityMap[$config->getParity()] ?? 0;

        $dcb->StopBits = self::toDcbStopBits($config->getStopBits());

        // Apply settings
        if (!$this->ffi->SetCommState($this->handle, FFI::addr($dcb))) {
            throw new RuntimeException('Failed to set COM state');
        }

        // 読み込みはすぐ戻す。書き込みは送信にかかる時間に余裕を足した上限を設ける
        // （0 にすると上限が無くなり、相手が読まないと WriteFile が戻らない）
        $timeouts = $this->ffi->new('COMMTIMEOUTS');
        $timeouts->ReadIntervalTimeout = 1;
        $timeouts->ReadTotalTimeoutMultiplier = 0;
        $timeouts->ReadTotalTimeoutConstant = 1;
        // 上限は WriteDeadline::budgetMs() と同じ「1 文字の最大送信時間 × バイト数 + 余裕」になる
        $timeouts->WriteTotalTimeoutMultiplier = WriteDeadline::perCharacterMs($config->getBaudRate());
        $timeouts->WriteTotalTimeoutConstant = WriteDeadline::MARGIN_MS;

        // 失敗を見逃すと書き込みの上限が無いままになるため、例外にする
        if (!$this->ffi->SetCommTimeouts($this->handle, FFI::addr($timeouts))) {
            throw new RuntimeException('Failed to set COM timeouts');
        }
    }

    /**
     * ストップビット数を DCB.StopBits の値へ変換する。
     *
     * DCB は 0 = 1 ビット、1 = 1.5 ビット、2 = 2 ビットで表す（winbase.h）。
     * ビット数から 1 を引くと、2 ビットが 1.5 ビットになってしまう。
     *
     * @internal テストから変換規則を確かめるために公開している。
     */
    public static function toDcbStopBits(int $stopBits): int
    {
        return match ($stopBits) {
            Configuration::STOP_BITS_1 => self::DCB_ONESTOPBIT,
            Configuration::STOP_BITS_2 => self::DCB_TWOSTOPBITS,
            default => throw new RuntimeException(sprintf('Unsupported stop bits: %d', $stopBits)),
        };
    }

    public function close(mixed $handle): void
    {
        if ($handle !== null && $this->ffi !== null) {
            $this->ffi->CloseHandle($handle);
            $this->handle = null;
        }
    }

    public function write(mixed $handle, string $data): int
    {
        if ($handle === null) {
            throw new RuntimeException('Invalid handle');
        }

        if ($this->ffi === null) {
            throw new RuntimeException('FFI is not initialized');
        }

        $length = strlen($data);

        // char[0] は確保できないため、空のデータは書かずに返す
        if ($length === 0) {
            return 0;
        }

        // 所有権付きで確保し，$buffer の破棄と同時に解放させる（WriteFile は同期呼び出し）
        $buffer = $this->ffi->new('char[' . $length . ']');
        FFI::memcpy($buffer, $data, $length);

        $bytesWritten = $this->ffi->new('DWORD');

        if (!$this->ffi->WriteFile($handle, $buffer, $length, FFI::addr($bytesWritten), null)) {
            throw new RuntimeException('Failed to write to serial port');
        }

        return self::ensureFullyWritten(
            (int) $bytesWritten->cdata,
            $length,
            ($this->config ?? new Configuration())->getBaudRate()
        );
    }

    /**
     * WriteFile が全量を書けたかを確かめ、書けていなければ TimeoutException を投げる。
     *
     * COMMTIMEOUTS の上限に達すると、WriteFile は成功を返しつつ一部だけ書いて戻る。
     *
     * @internal テストから判定を確かめるために公開している。
     */
    public static function ensureFullyWritten(int $written, int $length, int $baudRate): int
    {
        if ($written < $length) {
            throw new TimeoutException(
                sprintf(
                    'Write timed out after %d ms: wrote %d of %d bytes',
                    WriteDeadline::budgetMs($length, $baudRate),
                    $written,
                    $length
                ),
                $written
            );
        }

        return $written;
    }

    public function read(mixed $handle, int $length): string|false
    {
        if ($handle === null) {
            throw new RuntimeException('Invalid handle');
        }

        $length = ReadLength::normalize($length);

        if ($this->ffi === null) {
            throw new RuntimeException('FFI is not initialized');
        }

        $buffer = $this->ffi->new('char[' . $length . ']');
        $bytesRead = $this->ffi->new('DWORD');

        if (!$this->ffi->ReadFile($handle, $buffer, $length, FFI::addr($bytesRead), null)) {
            return false;
        }

        if ($bytesRead->cdata === 0) {
            return '';
        }

        return FFI::string($buffer, $bytesRead->cdata);
    }
}
