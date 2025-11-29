<?php

declare(strict_types=1);

namespace PhpSerial\Platform;

use FFI;
use PhpSerial\Configuration;
use RuntimeException;

/**
 * Windows implementation using FFI to call Windows API directly
 * This allows proper baud rate configuration
 */
class WindowsFFI implements PlatformInterface
{
    private ?FFI $ffi = null;
    private mixed $handle = null;
    private ?Configuration $config = null;

    private function validateDevice(string $device): void
    {
        if (!preg_match('/^COM\d+$/i', $device)) {
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

        $this->ffi = FFI::cdef($code, 'kernel32.dll');
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

        if ($handle === $this->ffi->cast('HANDLE', -1)) {
            throw new RuntimeException(
                sprintf('Failed to open serial port: %s', $device)
            );
        }

        $this->handle = $handle;

        // Apply configuration immediately after opening
        if ($this->config !== null) {
            $this->applyConfiguration($this->config);
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

        // Stop bits (0 = 1 bit, 2 = 2 bits)
        $dcb->StopBits = $config->getStopBits() - 1;

        // Apply settings
        if (!$this->ffi->SetCommState($this->handle, FFI::addr($dcb))) {
            throw new RuntimeException('Failed to set COM state');
        }

        // Set timeouts for non-blocking reads
        $timeouts = $this->ffi->new('COMMTIMEOUTS');
        $timeouts->ReadIntervalTimeout = 1;
        $timeouts->ReadTotalTimeoutMultiplier = 0;
        $timeouts->ReadTotalTimeoutConstant = 1;
        $timeouts->WriteTotalTimeoutMultiplier = 0;
        $timeouts->WriteTotalTimeoutConstant = 0;

        $this->ffi->SetCommTimeouts($this->handle, FFI::addr($timeouts));
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

        $buffer = FFI::new('char[' . strlen($data) . ']', false);
        FFI::memcpy($buffer, $data, strlen($data));

        $bytesWritten = $this->ffi->new('DWORD');

        if (!$this->ffi->WriteFile($handle, $buffer, strlen($data), FFI::addr($bytesWritten), null)) {
            throw new RuntimeException('Failed to write to serial port');
        }

        return $bytesWritten->cdata;
    }

    public function read(mixed $handle, int $length): string|false
    {
        if ($handle === null) {
            throw new RuntimeException('Invalid handle');
        }

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
