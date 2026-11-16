# 🔌 serdevmock を使った結合テスト

<!-- 本ファイルは開発者向けの手順書のため「ですます調」で書く．句点は他の文書と同じく「。」で統一している． -->
<!-- textlint-disable ja-technical-writing/no-mix-dearu-desumasu, ja-technical-writing/ja-no-mixed-period -->

実機を使わずに、ポートを開いて送受信するまでを確かめる手順をまとめます。
仮想ポートの対を作り、片側で serdevmock を動かします。
もう片側を php-serial で開き、送ったデータがそのまま返るかを確かめます。
Linux は CI で毎回、macOS と Windows は CI で週 1 回確かめます。
Windows は手元でも確かめられます。

## 📑 目次

- 🧭 仕組み
- 🐧 Linux と macOS
- 🪟 Windows
- ⚙ CI での扱い
- 🔍 この試験で見つけた問題

## 🧭 仕組み

[serdevmock](https://github.com/tomio2480/serdevmock) は Python 製のツールです。
シリアル機器の側を演じます。
仮想ポートを作る機能は持たないため、OS ごとの道具で対を作ります。

```text
php-serial → ポート A ⇔（仮想ポートの対）⇔ ポート B ← serdevmock
```

図 1. 仮想ポートの対の両端につながる php-serial と serdevmock

serdevmock には `tests/fixtures/serdevmock/echo.json` を読ませます。
受け取ったデータをそのまま送り返すエコーモードの設定です。

テストは `tests/Integration/` にあり、PHPUnit の `integration` グループに属します。
環境変数 `SERDEVMOCK_PORT` に php-serial 側のポートを指定したときだけ実行されます。
未設定なら skip されるため、`composer test` の結果は変わりません。

表 1. OS ごとの仮想ポートの道具と確かめる場所

| OS | 仮想ポートの道具 | 確かめる場所 |
|---|---|---|
| Linux | socat | CI（push と PR ごと） |
| macOS | socat | CI（手動、週 1 回、関係する PR） |
| Windows | com0com | CI（手動、週 1 回、関係する PR）と手元 |

## 🐧 Linux と macOS

socat と Python 3.11 以上を用意します。
serdevmock は、版とハッシュを固定した `tests/Integration/requirements.txt` から入れます。

```bash
python -m pip install --require-hashes -r tests/Integration/requirements.txt
```

php-serial はデバイス名を検査するため、socat が作る pty の名前をそのまま渡せません。
起動スクリプトが、検査を通る名前の別名を `sudo` で張ります。
別名は Linux なら `/dev/ttyUSB90`、macOS なら `/dev/tty.serdevmock` を使います。

```bash
bash tests/Integration/start-serdevmock.sh /dev/ttyUSB90 /path/to/workdir
```

```bash
SERDEVMOCK_PORT=/dev/ttyUSB90 vendor/bin/phpunit --group integration --testdox
```

```bash
bash tests/Integration/stop-serdevmock.sh /dev/ttyUSB90 /path/to/workdir
```

起動スクリプトは、エコーが返るのを確かめてから戻ります。
serdevmock はポートを開くときに受信バッファを捨てるため、先に送ると失われるからです。

## 🪟 Windows

com0com で COM10 と COM11 の対を作っておきます。
作り方は serdevmock の手順書を参照してください。

- [VIRTUAL_PORTS.md](https://github.com/tomio2480/serdevmock/blob/main/docs/VIRTUAL_PORTS.md)

PHP では FFI を有効にしておきます（[WINDOWS_FFI_SETUP.md](WINDOWS_FFI_SETUP.md)）。

serdevmock は venv へ入れると、ほかの Python 環境を汚しません。

```powershell
python -m venv .venv-serdevmock
.\.venv-serdevmock\Scripts\python.exe -m pip install --require-hashes -r tests\Integration\requirements.txt
```

serdevmock を COM11 で起動したまま、別のターミナルでテストを流します。

```powershell
.\.venv-serdevmock\Scripts\serdevmock.exe --protocol uart --port COM11 --config tests\fixtures\serdevmock\echo.json
```

```powershell
$env:SERDEVMOCK_PORT = 'COM10'
vendor\bin\phpunit --group integration --testdox
```

`.env` に `SERDEVMOCK_PORT=COM10` と書いても指定できます。

serdevmock を別のターミナルで動かす代わりに、起動スクリプトも使えます。
CI と同じく、エコーが返るのを確かめてから戻ります。

```powershell
pwsh tests\Integration\start-serdevmock.ps1 -AppPort COM10 -MockPort COM11 -WorkDir $env:TEMP\serdevmock -Serdevmock .\.venv-serdevmock\Scripts\serdevmock.exe
```

```powershell
pwsh tests\Integration\stop-serdevmock.ps1 -WorkDir $env:TEMP\serdevmock
```

### 相手が読まないポートでの試験

serdevmock を止めると、COM10 は「相手が読まないポート」になります。
この状態で、書き込みの時間の上限と、失敗後の後始末を確かめます。

```powershell
$env:WINDOWS_IDLE_PORT = 'COM10'
vendor\bin\phpunit --group windows-idle-port --testdox
```

`.env` に `WINDOWS_IDLE_PORT=COM10` と書いても指定できます。
serdevmock の試験と同時には指定しないでください。
片方は相手が読む前提、もう片方は読まない前提のためです。

## ⚙ CI での扱い

表 2. OS ごとのワークフローと実行の機会

| OS | ワークフロー | 実行の機会 |
|---|---|---|
| Linux | `integration.yml` | push と PR のたび |
| macOS | `integration-macos.yml` | 手動、週 1 回、関係する PR |
| Windows | `integration-windows.yml` | 手動、週 1 回、関係する PR |

ワークフローは `.github/workflows/` にあります。
macOS ランナーは空き待ちが 10 分前後になることがあるため、実行の機会を絞っています。
Windows のジョブは 1 分ほどで終わります。
ただし FFI 実装に関係しない変更まで走らせないよう、macOS に合わせています。
どちらも次の機会に実行します。

- 手動実行（`workflow_dispatch`）
- 毎週月曜 03:00（日本時間）
- 結合テストの仕組みか、その OS のプラットフォーム実装を変えた PR

関係する PR の判定には、macOS は `Unix.php`、Windows は `Windows.php` と `WindowsFFI.php` を使います。

いずれも `--fail-on-skipped` と `--fail-on-empty-test-suite` を付けて実行します。
環境変数の設定漏れで全件が skip されたまま、緑になるのを防ぐためです。

### 🪟 Windows ランナーでの com0com

Windows のジョブは、com0com 3.0.0.0 の署名版を SourceForge から取得します。
SHA256 を照合してから、インストーラーを `/S` で非対話のまま実行します。
続けて `setupc.exe` で COM10 と COM11 の対を作ります。

インストーラーの前に、署名者の証明書を「信頼された発行元」へ登録します。
登録しないと、ドライバー導入の確認ダイアログで止まるためです。
この操作は使い捨てのランナーに限り、手元では行いません。

このドライバーは 2016 年の証明書で署名されています。
Secure Boot が有効な Windows 10 以降では、読み込みを拒まれることがあります。
GitHub のホストランナーは Secure Boot が無効のため、そのまま読み込めます。
ランナーの構成が変わって導入に失敗したときは、この点を最初に疑ってください。

ジョブは 2 段で試験します。
まず serdevmock を COM11 で動かし、COM10 で `integration` グループを流します。
次に serdevmock を止め、COM10 で `windows-idle-port` グループを流します。

Windows のランナーは、ステップ内で起動したプロセスをステップの終わりに止めます。
このため、serdevmock の起動・試験・停止を 1 つのステップにまとめています。
分けると、試験の時点で serdevmock が止まっており、エコーが返りません。

FFI が無効だと、php-serial は fopen 版の実装へ切り替わります。
FFI 実装を試験しないまま通るのを防ぐため、最初に `php --ri ffi` で有効化を確かめます。

## 🔍 この試験で見つけた問題

Windows の FFI 実装で、次の問題を見つけて直しました。
同じ問題が戻ってきたときは、ここに挙げたテストが失敗します。

表 3. 見つけた問題と、それを確かめるテスト

| 問題 | 確かめるテスト |
|---|---|
| 相手が読まないと `write()` が戻らない（現在は期限を過ぎると `TimeoutException`） | `WindowsIdlePortTest` |
| 同じプロセスで 2 回目に開くと `FFI\Exception` が出る | `SerdevmockEchoTest`（3 件をまとめて流す） |
| 設定に失敗すると COM ポートのハンドルが残る | `WindowsIdlePortTest` |
| ストップビット 2 が 1.5 として設定される | `WindowsFFIConversionTest` |
