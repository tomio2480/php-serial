<?php

// TEMP: 原因調査用。PR 作成前に削除する。
// 使い方: php diag-dcb.php COM10 [write]
// PHP FFI はビットフィールドを先頭に重ねて扱うため、フラグ語はバイト列から読む。

declare(strict_types=1);

$device = $argv[1] ?? 'COM10';
$doWrite = ($argv[2] ?? '') === 'write';

$ffi = FFI::cdef(<<<'CDEF'
typedef void* HANDLE;
typedef unsigned long DWORD;
typedef struct { unsigned char b[28]; } DCB;
typedef struct { DWORD a, b, c, d, e; } COMMTIMEOUTS;
typedef struct { DWORD fFlags; DWORD cbInQue; DWORD cbOutQue; } COMSTAT;
HANDLE CreateFileA(const char*, DWORD, DWORD, void*, DWORD, DWORD, HANDLE);
int CloseHandle(HANDLE);
int GetCommState(HANDLE, DCB*);
int GetCommModemStatus(HANDLE, DWORD*);
int ClearCommError(HANDLE, DWORD*, COMSTAT*);
int SetCommTimeouts(HANDLE, COMMTIMEOUTS*);
int WriteFile(HANDLE, const void*, DWORD, DWORD*, void*);
DWORD GetLastError(void);
CDEF, 'kernel32.dll');

$h = $ffi->CreateFileA("\\\\.\\{$device}", 0xC0000000, 0, null, 3, 0x80, null);
if ($h == $ffi->cast('HANDLE', -1)) {
    echo "open failed: error=" . $ffi->GetLastError() . "\n";
    exit(0);
}

$dcb = $ffi->new('DCB');
FFI::memcpy($dcb, pack('V', 28), 4);
$ok = $ffi->GetCommState($h, FFI::addr($dcb));
$v = unpack('Vlen/Vbaud/Vflags/vres/vxon/vxoff/CbyteSize/Cparity/CstopBits', FFI::string(FFI::addr($dcb), 21));
$f = $v['flags'];
printf(
    "get=%d baud=%d %d%s%d flags=0x%04x fBinary=%d fParity=%d CtsFlow=%d DsrFlow=%d DtrCtl=%d DsrSens=%d TXCont=%d OutX=%d InX=%d Null=%d RtsCtl=%d Abort=%d\n",
    $ok, $v['baud'], $v['byteSize'], ['N', 'O', 'E', 'M', 'S'][$v['parity']] ?? '?', $v['stopBits'], $f,
    $f & 1, ($f >> 1) & 1, ($f >> 2) & 1, ($f >> 3) & 1, ($f >> 4) & 3, ($f >> 6) & 1, ($f >> 7) & 1,
    ($f >> 8) & 1, ($f >> 9) & 1, ($f >> 11) & 1, ($f >> 12) & 3, ($f >> 14) & 1
);

$ms = $ffi->new('DWORD');
$ffi->GetCommModemStatus($h, FFI::addr($ms));
printf("modem CTS=%d DSR=%d RING=%d RLSD=%d\n", ($ms->cdata >> 4) & 1, ($ms->cdata >> 5) & 1, ($ms->cdata >> 6) & 1, ($ms->cdata >> 7) & 1);

if ($doWrite) {
    $t = $ffi->new('COMMTIMEOUTS');
    $t->a = 1; $t->b = 0; $t->c = 1; $t->d = 0; $t->e = 1000;
    $ffi->SetCommTimeouts($h, FFI::addr($t));
    $data = "diag\n";
    $n = $ffi->new('DWORD');
    $wok = $ffi->WriteFile($h, $data, strlen($data), FFI::addr($n), null);
    echo "write ok={$wok} n={$n->cdata}\n";
    usleep(800000);
    $err = $ffi->new('DWORD');
    $st = $ffi->new('COMSTAT');
    $ffi->ClearCommError($h, FFI::addr($err), FFI::addr($st));
    echo "errors={$err->cdata} inQue={$st->cbInQue} outQue={$st->cbOutQue} holdFlags=0x" . dechex($st->fFlags) . "\n";
}

$ffi->CloseHandle($h);
