# Windows での FFI セットアップ

PHP の FFI（Foreign Function Interface）を有効化することで、Windows API を直接呼び出して正確なボーレート設定が可能になります。

## 必要な設定

### 1. php.ini の編集

php.ini の場所を確認：
```bash
php --ini
```

出力例：
```
Configuration File (php.ini) Path: C:\php8.5
Loaded Configuration File:         C:\php8.5\php.ini
```

### 2. FFI 拡張の有効化

`C:\php8.5\php.ini` をテキストエディタで開き、以下の行を探します。

```ini
;extension=ffi
```

`;`（セミコロン）を削除してコメントを解除：

```ini
extension=ffi
```

### 3. FFI の有効化

php.ini 内で以下の行を探します。

```ini
;ffi.enable=false
```

以下のように変更：

```ini
ffi.enable=true
```

見つからない場合は、ファイルの末尾に追加：

```ini
[ffi]
ffi.enable=true
```

### 4. PowerShell/コマンドプロンプトを再起動

設定を反映するため、ターミナルを再起動します。

### 5. 確認

```bash
php examples/test_ffi.php
```

以下のように表示されれば成功：

```
PHP Version: 8.5.0
FFI extension loaded: Yes
FFI is available!
FFI test successful!
```

## Windows API によるシリアル通信

FFI が有効な環境では、以下の利点があります。

- 正確なボーレート設定（9600, 115200 など）
- パリティ、ストップビット、データビットの完全な制御
- Windows の `mode` コマンドに依存しない安定した通信

FFI が無効な場合は、従来の `fopen()` 実装にフォールバックしますが、ボーレート設定に制限があります。

## トラブルシューティング

### FFI が有効にならない

- php.ini の場所が正しいか確認
- `extension_dir` の設定を確認
- `php_ffi.dll` が存在するか確認（`C:\php8.5\ext\php_ffi.dll`）

### dll が見つからない

Windows 版 PHP には FFI が含まれているはずですが、見つからない場合は PHP を再インストールしてください。