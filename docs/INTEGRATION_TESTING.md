# 🔌 serdevmock を使った結合テスト

<!-- 本ファイルは開発者向けの手順書のため「ですます調」で書く．句点は他の文書と同じく「。」で統一している． -->
<!-- textlint-disable ja-technical-writing/no-mix-dearu-desumasu, ja-technical-writing/ja-no-mixed-period -->

実機を使わずに、ポートを開いて送受信するまでを確かめる手順をまとめます。
仮想ポートの対を作り、片側で serdevmock を動かします。
もう片側を php-serial で開き、送ったデータがそのまま返るかを確かめます。
Linux は CI で毎回、macOS は CI で週 1 回、Windows は手元で確かめます。

## 📑 目次

- 🧭 仕組み
- 🐧 Linux と macOS
- 🪟 Windows
- ⚙ CI での扱い
- ⚠ 既知の問題

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
| Windows | com0com | 手元 |

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
serdevmock を起動する前にテストを流さないでください。
相手が読まないと、`write()` が戻らなくなります（既知の問題 1）。

## ⚙ CI での扱い

Linux は `.github/workflows/integration.yml` で、push と PR のたびに実行します。
macOS は `.github/workflows/integration-macos.yml` で実行します。
macOS ランナーは空き待ちが 10 分前後になることがあるため、実行の機会を絞っています。

- 手動実行（`workflow_dispatch`）
- 毎週月曜 03:00（日本時間）
- 結合テストの仕組みか `Unix.php` を変えた PR

どちらも `--fail-on-skipped` と `--fail-on-empty-test-suite` を付けて実行します。
環境変数の設定漏れで全件が skip されたまま、緑になるのを防ぐためです。

## ⚠ 既知の問題

Windows の FFI 実装では、次の問題を確認しています。
直すまでは、テストを 1 件ずつ流すと結果が安定します（`--filter` を使います）。

1. 書き込みのタイムアウトが無いため、相手が読まないと `write()` が戻りません。
2. 同じプロセスの 2 件目のテストでポートを開くと、`FFI\Exception` の出る場合があります。
   1 件ずつ流すと通り、PHPUnit の外で開閉を繰り返しても通ります。
3. 2 の例外が出ると、COM ポートのハンドルは閉じられずに残ります。以降のテストはポートを開けません。
