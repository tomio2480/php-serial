#!/usr/bin/env bash
# start-serdevmock.sh で起動した serdevmock と socat を止め、ログを出す（Linux / macOS 用）。
#
# 使い方: stop-serdevmock.sh <php-serial 側のデバイス名> <作業ディレクトリ>
set -uo pipefail

device=${1:?php-serial 側のデバイス名を指定する}
workdir=${2:?作業ディレクトリを指定する}

for name in serdevmock socat; do
  pid_file="$workdir/$name.pid"

  if [[ -f $pid_file ]]; then
    pid=$(cat "$pid_file")
    kill "$pid" 2>/dev/null || true

    # 別のシェルが起動したプロセスは wait できないため、終了を短く待ってからログを読む
    for _ in $(seq 20); do
      kill -0 "$pid" 2>/dev/null || break
      sleep 0.1
    done
  fi

  if [[ -f $workdir/$name.log ]]; then
    echo "----- $name.log"
    cat "$workdir/$name.log"
  fi
done

if [[ -L $device ]]; then
  sudo rm -f "$device"
fi
