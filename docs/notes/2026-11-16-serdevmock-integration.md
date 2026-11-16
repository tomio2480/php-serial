# 🔌 serdevmock による実機なしの結合テスト

<!-- 句点は他の文書と同じく「。」で統一している． -->
<!-- textlint-disable ja-technical-writing/ja-no-mixed-period -->

## 📝 要約

serdevmock と仮想ポートの対を使い、実機なしでポートの送受信を確かめる結合テストを加えた。
CI の Linux と macOS で流したところ、macOS でライブラリの不具合が 2 件見つかった。
本ノートは、仮想ポートの組み立て方と、macOS の `stty` の想定外の挙動を残す。

## 🗺 目次

- 🧭 背景
- ✅ 判断
- 🔀 代替案と棄却理由
- ⚠ 想定外だった挙動
- 📏 実測
- 🔗 参照

## 🧭 背景

これまでのテストは、設定の検査とコマンドの組み立てまでを確かめていた。
ポートを開いて実際に送受信する経路は、実機を持つ手元でしか確かめられなかった。
serdevmock はデバイス側を演じる Python 製のエミュレータで、エコーモードを持つ。
仮想ポートの対の片側で serdevmock を動かせば、もう片側を php-serial で開ける。

## ✅ 判断

- 仮想ポートの対は、Linux と macOS では socat、Windows では com0com で作る。
- socat が作る PTY の名前は、`Unix` のデバイス名検査を通らない。
  検査は変えず、検査を通る別名を `sudo ln -sfn` で `/dev` に張る。
  Linux は `/dev/ttyUSB90`、macOS は `/dev/tty.serdevmock` とした。
- 試験は `SERDEVMOCK_PORT` を指定したときだけ動かし、未指定なら skip する。
  CI では `--fail-on-skipped` と `--fail-on-empty-test-suite` を付け、全件 skip の緑を防ぐ。
- 全バイト値 0〜255 の往復を試験に含める。文字列だけでは制御文字の扱いの誤りを見逃す。
- serdevmock と pyserial は版とハッシュを固定し、`pip --require-hashes` で入れる。
- Linux は push と PR ごとに流す。macOS は手動・週 1 回・関係する PR に絞った。
- Windows は CI に載せず、手元で確かめる手順を `docs/INTEGRATION_TESTING.md` にまとめた。

## 🔀 代替案と棄却理由

- デバイス名の検査を緩めて PTY（`/dev/pts/N`・`/dev/ttys00N`）を通す案は採らなかった。
  試験の都合で、利用者に向けた入力検査を広げることになる。
- serdevmock を使わず、PHP のテストから PTY の反対側を直接読み書きする案は採らなかった。
  serdevmock の応答規則を後で使えるよう、デバイス側の役は 1 つに寄せた。
- macOS を PR ごとに流す案は採らなかった。ランナーの空き待ちが 10 分前後になることがある。

## ⚠ 想定外だった挙動

### macOS の stty はデバイスを -f で受け取る

GNU の `stty` は `-F` でデバイスを受け取り、BSD 系の `stty` は `-f` で受け取る。
macOS で `-F` を渡すと、標準入力を設定しようとして `stdin isn't a terminal` で失敗した。
それまでの `Unix.php` は `-F` 固定で、macOS の実機でも同じく失敗していたはずである。
加えて、`stty` の標準エラー出力を捨てていたため、例外に失敗の理由が残らなかった。
OS ごとに `-F` と `-f` を切り替え、標準エラー出力も例外メッセージへ含めるよう直した。

### raw にしないと 0x0F と 0x16 が消える

`stty` を直すと、macOS では全バイト値の往復だけが失敗し、0x0F と 0x16 が消えた。
端末の既定の設定では、これらを VDISCARD と VLNEXT の制御文字として解釈する。
Linux では socat が PTY を raw にしていたため、表に出なかった。

`stty` に `raw -echo -iexten` を加え、受け取ったバイトをそのまま扱うようにした。
macOS の `raw` は `cfmakeraw` 相当で、データビットとパリティも上書きする。
そのため `raw` を回線の設定（`cs8`・`parenb` など）より前に置く。
実機でも改行の変換や制御文字の解釈が止まるため、振る舞いの変化として README に記した。

### macOS の /dev へも別名を張れる

macOS の `/dev` は devfs で、別名を張れるか事前には分からなかった。
GitHub Actions の macOS ランナーでは、`sudo ln -sfn` で張れた。

### ジョブの env では runner コンテキストを使えない

`jobs.<id>.env` で `${{ runner.temp }}` を参照すると、ワークフロー定義の誤りとなった。
ジョブは 1 つも起動せず、ログも残らない。
ステップ内でシェル変数 `$RUNNER_TEMP` を参照するよう直した。

### Windows で見つかった不具合

手元の Windows で試験を流すと、相手が読まないポートへの `write()` が戻らなかった。
ほかに FFI 実装の不具合 3 件が見つかり、#24 で直す。

### squash マージ後の積み重ね PR の衝突

PR #24 は #23 の上に積み、base を `main` にして #23 の変更をマージで取り込んでいた。
前回のノートの教訓どおり、#23 のブランチを消しても #24 は自動クローズされなかった。
一方、#23 を squash でマージすると、#24 は 2 ファイルで main と衝突した。
同じ内容でも、squash 後のコミットと元のコミットは別物として扱われるためである。

PR #24 は #23 の内容をすべて含むため、main をマージして衝突は #24 側を採った。
マージ後のツリーがマージ前の #24 と同一であることを、ツリーのハッシュで確かめた。
force-push は要らなかった。

## 📏 実測

表 1. macOS の結合テストジョブの所要時間

| 区間 | 時間 |
|---|---|
| ジョブ全体 | 32 秒 |
| `brew install socat` | 8 秒 |

ジョブ自体は短く、費用より空き待ちが問題になる。
公開リポジトリのため、標準ランナーは無料で使える。

## 🔗 参照

- [serdevmock](https://github.com/tomio2480/serdevmock)
- [GitHub Docs: Contexts の利用可能範囲](https://docs.github.com/en/actions/reference/workflows-and-actions/contexts#context-availability)
- tomio2480/php-serial#23（結合テスト基盤と macOS の修正）
- tomio2480/php-serial#24（Windows FFI の修正）
