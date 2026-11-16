<#
com0com の対の片側で、エコーモードの serdevmock を動かす（Windows 用）。

使い方: pwsh tests/Integration/start-serdevmock.ps1 -AppPort COM10 -MockPort COM11 -WorkDir <作業ディレクトリ>

-AppPort は php-serial 側、-MockPort は serdevmock 側のポートである。
serdevmock が PATH に無いときは、-Serdevmock に実行ファイルのパスを渡す。
停止は stop-serdevmock.ps1 に同じ -WorkDir を渡して行う。
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$AppPort,
    [Parameter(Mandatory)][string]$MockPort,
    [Parameter(Mandatory)][string]$WorkDir,
    [string]$Serdevmock = 'serdevmock'
)

$ErrorActionPreference = 'Stop'

$fixtureDir = Join-Path $PSScriptRoot '..\fixtures\serdevmock'
$exe = (Get-Command $Serdevmock -CommandType Application | Select-Object -First 1).Source

New-Item -ItemType Directory -Force $WorkDir | Out-Null
$WorkDir = (Resolve-Path $WorkDir).Path

$env:PYTHONUNBUFFERED = '1'
# 出力先がファイルだと既定の文字コードになり、serdevmock の日本語のメッセージで落ちる
$env:PYTHONIOENCODING = 'utf-8'
# 設定ファイルは作業ディレクトリからの相対名で渡し、パスの空白で引数が割れるのを避ける
$proc = Start-Process -FilePath $exe -PassThru -WindowStyle Hidden `
    -WorkingDirectory $fixtureDir `
    -ArgumentList '--protocol', 'uart', '--port', $MockPort, '--config', 'echo.json' `
    -RedirectStandardOutput (Join-Path $WorkDir 'serdevmock.log') `
    -RedirectStandardError (Join-Path $WorkDir 'serdevmock.err.log')
Set-Content -Path (Join-Path $WorkDir 'serdevmock.pid') -Value $proc.Id

function Write-SerdevmockLog {
    foreach ($name in 'serdevmock.log', 'serdevmock.err.log') {
        $path = Join-Path $WorkDir $name
        if (Test-Path $path) {
            Write-Host "----- $name"
            Get-Content $path | Write-Host
        }
    }
}

# serdevmock はポートを開くときに受信バッファを捨てる。
# 開き終える前に送ったデータは失われるため、エコーが返るまで確かめてから戻る。
$port = [System.IO.Ports.SerialPort]::new($AppPort, 9600)
$port.NewLine = "`n"
$port.ReadTimeout = 1000
# 相手が読まないと書き込みが戻らないため、上限を設ける
$port.WriteTimeout = 1000
$ready = $false

$port.Open()
try {
    for ($i = 1; $i -le 30 -and -not $ready; $i++) {
        # 起動直後に落ちた場合は、待ち続けずに打ち切る
        if ($proc.HasExited) {
            break
        }

        $probe = "probe-$i"
        try {
            $port.WriteLine($probe)
        } catch [System.TimeoutException] {
            continue
        }
        $deadline = [DateTime]::UtcNow.AddSeconds(1)

        while (-not $ready -and [DateTime]::UtcNow -lt $deadline) {
            try {
                $ready = $port.ReadLine().TrimEnd("`r") -eq $probe
            } catch [System.TimeoutException] {
                break
            }
        }
    }

    # 遅れて届いた古い応答を読み捨て、テストへ持ち越さない
    try {
        while ($true) {
            [void]$port.ReadLine()
        }
    } catch [System.TimeoutException] {
    }
} finally {
    $port.Close()
}

if (-not $ready) {
    Write-SerdevmockLog
    # 呼び出し元が後続の試験へ進まないよう、終了コードでなく例外で知らせる
    throw 'serdevmock からエコーが返らなかった'
}

Write-Host "php-serial 側: $AppPort / serdevmock 側: $MockPort"
Write-Host 'serdevmock の準備ができた'
