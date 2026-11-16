<#
start-serdevmock.ps1 で起動した serdevmock を止め、ログを出す（Windows 用）。

使い方: pwsh tests/Integration/stop-serdevmock.ps1 -WorkDir <作業ディレクトリ>
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$WorkDir
)

$pidFile = Join-Path $WorkDir 'serdevmock.pid'

if (Test-Path $pidFile) {
    $id = [int](Get-Content $pidFile -TotalCount 1)
    $proc = Get-Process -Id $id -ErrorAction SilentlyContinue

    if ($null -eq $proc) {
        # 試験の途中で落ちていた場合は、失敗の原因として目に付くように残す
        Write-Warning 'serdevmock は停止の前に終了していた'
    } else {
        # serdevmock.exe は python.exe を子として起動するため、子まで含めて止める
        & taskkill.exe /PID $id /T /F | Out-Null
        # ポートが閉じ切る前に次の試験が開くと、使用中で失敗するため終了を待つ
        $null = $proc.WaitForExit(5000)
    }

    Remove-Item $pidFile -Force
}

foreach ($name in 'serdevmock.log', 'serdevmock.err.log') {
    $path = Join-Path $WorkDir $name
    if (Test-Path $path) {
        Write-Host "----- $name"
        Get-Content $path | Write-Host
    }
}
