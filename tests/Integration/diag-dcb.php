<?php

// TEMP: 原因調査用。PR 作成前に削除する。
// 使い方: php diag-dcb.php COM10

declare(strict_types=1);

$device = $argv[1] ?? 'COM10';

$ffi = FFI::cdef(<<<'CDEF'
typedef void* HANDLE;
typedef unsigned long DWORD;
typedef unsigned short WORD;
typedef unsigned char BYTE;
typedef struct {
    DWORD DCBlength; DWORD BaudRate;
    DWORD fBinary:1; DWORD fParity:1; DWORD fOutxCtsFlow:1; DWORD fOutxDsrFlow:1;
    DWORD fDtrControl:2; DWORD fDsrSensitivity:1; DWORD fTXContinueOnXoff:1;
    DWORD fOutX:1; DWORD fInX:1; DWORD fErrorChar:1; DWORD fNull:1;
    DWORD fRtsControl:2; DWORD fAbortOnError:1; DWORD fDummy2:17;
    WORD wReserved; WORD XonLim; WORD XoffLim;
    BYTE ByteSize; BYTE Parity; BYTE StopBits;
    char XonChar; char XoffChar; char ErrorChar; char EofChar; char EvtChar;
    WORD wReserved1;
} DCB;
typedef struct { DWORD fFlags; DWORD cbInQue; DWORD cbOutQue; } COMSTAT;
HANDLE CreateFileA(const char*, DWORD, DWORD, void*, DWORD, DWORD, HANDLE);
int CloseHandle(HANDLE);
int GetCommState(HANDLE, DCB*);
int GetCommModemStatus(HANDLE, DWORD*);
int ClearCommError(HANDLE, DWORD*, COMSTAT*);
int WriteFile(HANDLE, const void*, DWORD, DWORD*, void*);
DWORD GetLastError(void);
CDEF, 'kernel32.dll');

$h = $ffi->CreateFileA("\\\\.\\{$device}", 0xC0000000, 0, null, 3, 0x80, null);
$dcb = $ffi->new('DCB');
$dcb->DCBlength = FFI::sizeof($dcb);
$ffi->GetCommState($h, FFI::addr($dcb));

foreach (['BaudRate', 'fBinary', 'fParity', 'fOutxCtsFlow', 'fOutxDsrFlow', 'fDtrControl', 'fDsrSensitivity',
    'fTXContinueOnXoff', 'fOutX', 'fInX', 'fErrorChar', 'fNull', 'fRtsControl', 'fAbortOnError',
    'XonLim', 'XoffLim', 'ByteSize', 'Parity', 'StopBits'] as $f) {
    echo "{$f}={$dcb->$f} ";
}
echo "\n";

$ms = $ffi->new('DWORD');
$ffi->GetCommModemStatus($h, FFI::addr($ms));
printf("modem=0x%02x (CTS=%d DSR=%d RING=%d RLSD=%d)\n", $ms->cdata, ($ms->cdata >> 4) & 1, ($ms->cdata >> 5) & 1, ($ms->cdata >> 6) & 1, ($ms->cdata >> 7) & 1);

$data = "diag\n";
$n = $ffi->new('DWORD');
$ok = $ffi->WriteFile($h, $data, strlen($data), FFI::addr($n), null);
echo "write ok={$ok} n={$n->cdata}\n";
usleep(800000);

$err = $ffi->new('DWORD');
$st = $ffi->new('COMSTAT');
$ffi->ClearCommError($h, FFI::addr($err), FFI::addr($st));
echo "errors={$err->cdata} inQue={$st->cbInQue} outQue={$st->cbOutQue} fFlags={$st->fFlags}\n";
$ffi->CloseHandle($h);
