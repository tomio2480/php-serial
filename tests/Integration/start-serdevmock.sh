#!/usr/bin/env bash
# socat で仮想ポートの対を作り、片側でエコーモードの serdevmock を動かす（Linux / macOS 用）。
#
# 使い方: start-serdevmock.sh <php-serial 側のデバイス名> <作業ディレクトリ>
#   例:   start-serdevmock.sh /dev/ttyUSB90 "$RUNNER_TEMP/serdevmock"
#
# php-serial はデバイス名を検査するため、検査を通る名前の別名を sudo で張り、実体の pty を指す。
# 停止は stop-serdevmock.sh に同じ引数を渡して行う。
set -euo pipefail

device=${1:?php-serial 側のデバイス名（例: /dev/ttyUSB90）を指定する}
workdir=${2:?作業ディレクトリを指定する}

script_dir=$(cd "$(dirname "$0")" && pwd)
config="$script_dir/../fixtures/serdevmock/echo.json"
app_link="$workdir/app"
mock_link="$workdir/mock"

mkdir -p "$workdir"

socat -d -d "pty,raw,echo=0,link=$app_link" "pty,raw,echo=0,link=$mock_link" \
  >"$workdir/socat.log" 2>&1 &
echo $! >"$workdir/socat.pid"

for _ in $(seq 50); do
  [[ -e $app_link && -e $mock_link ]] && break
  sleep 0.1
done

if [[ ! -e $app_link || ! -e $mock_link ]]; then
  echo "socat が仮想ポートを作れなかった" >&2
  cat "$workdir/socat.log" >&2
  exit 1
fi

pty=$(readlink "$app_link")
sudo ln -sfn "$pty" "$device"
echo "php-serial 側: $device -> $pty"

PYTHONUNBUFFERED=1 serdevmock --protocol uart --port "$mock_link" --config "$config" \
  >"$workdir/serdevmock.log" 2>&1 &
echo $! >"$workdir/serdevmock.pid"

# serdevmock はポートを開くときに受信バッファを捨てる。
# 開き終える前に送ったデータは失われるため、エコーが返るまで確かめてから戻る。
exec 3<>"$app_link"
ready=false

for i in $(seq 30); do
  printf 'probe-%s\n' "$i" >&3

  while IFS= read -r -t 1 line <&3; do
    if [[ $line == "probe-$i" ]]; then
      ready=true
      break
    fi
  done

  $ready && break
done

# 遅れて届いた古い応答を読み捨て、テストへ持ち越さない
while IFS= read -r -t 1 _ <&3; do :; done
exec 3>&-

if ! $ready; then
  echo "serdevmock からエコーが返らなかった" >&2
  cat "$workdir/serdevmock.log" >&2
  exit 1
fi

echo "serdevmock の準備ができた"
