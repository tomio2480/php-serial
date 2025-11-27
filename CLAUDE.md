# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要

PHPでマイコンとのシリアル通信を行うクロスプラットフォームライブラリ。
PySerialやRuby serialportのような使い勝手を目指す。
Windows、Mac、Linuxで動作し、PHP 8.5に対応。

## 技術スタック

- **言語**: PHP 8.5
- **パッケージ管理**: Composer
- **テストフレームワーク**: PHPUnit
- **コーディング規約**: PSR-4, PSR-12

## プロジェクト構造

```
php-serial/
├── src/PhpSerial/          # メインライブラリコード
│   ├── SerialPort.php      # シリアルポート通信のメインクラス
│   ├── Configuration.php   # ボーレート、パリティ等の設定管理
│   └── Platform/           # プラットフォーム固有の実装
│       ├── Unix.php        # Linux/macOS向け (sttyコマンド)
│       ├── Windows.php     # Windows向け (fopenベース、制限あり)
│       └── WindowsFFI.php  # Windows向け (FFI + Windows API、推奨)
├── tests/                  # PHPUnitテストコード
├── examples/               # 使用例
├── docs/                   # ドキュメント
│   └── WINDOWS_FFI_SETUP.md # Windows FFI セットアップ手順
└── composer.json
```

## アーキテクチャ設計

### 実装方針
1. **Pure PHP実装**: PHP標準機能とFFIでシリアルポート制御
2. **プラットフォーム抽象化**: Platform配下で各OS固有の処理を分離
3. **Windows FFI**: Windows APIを直接呼び出して正確なボーレート制御

### クラス設計
- `SerialPort`: ユーザー向けAPI。open/close/read/writeを提供
- `Configuration`: ボーレート、データビット、パリティ、ストップビットを管理
- `Platform\Unix`: Linux/macOS向け、sttyコマンドを使用
- `Platform\Windows`: Windows向け、fopenベース（FFI無効時のフォールバック）
- `Platform\WindowsFFI`: Windows向け、FFI + Windows API（推奨）

## 開発コマンド

### 依存関係のインストール
```bash
composer install
```

### テスト実行
```bash
# 全テスト実行
composer test

# 単一テストファイル実行
./vendor/bin/phpunit tests/SerialPortTest.php

# カバレッジ付き実行
composer test:coverage
```

### コーディングスタイルチェック
```bash
composer cs:check
composer cs:fix
```

## テスト戦略

### ユニットテスト
- 設定クラスのバリデーション
- プラットフォーム検出ロジック
- コマンド生成ロジック

### モックテスト
- 実際のシリアルポートなしでテスト可能にする
- システムコマンド実行部分をモック化

### 統合テスト (任意)
- 実際のシリアルデバイスを使用したテスト
- CIでは実行不可のためローカル環境のみ

## 重要な実装上の注意

### 環境
- PHP 8.5とComposerがインストール済み
- 初期セットアップ時は依存関係が未インストールの状態を想定

### ファイルハンドル管理
- `fopen()`でデバイスファイルを開く
- ブロッキング/ノンブロッキングモードの制御
- 確実な`fclose()`実行（デストラクタでも保証）

### プラットフォーム判定
- `PHP_OS_FAMILY`定数を使用（Windows/Linux/Darwin）
- 各プラットフォームで異なるデバイスパス
  - Linux/Mac: `/dev/ttyUSB0`, `/dev/tty.usbserial-*`
  - Windows: `COM1`, `COM2`等

### エラーハンドリング
- デバイスが見つからない場合は例外をスロー
- 設定値の範囲外チェック
- ポートが開いていない状態での操作を防ぐ

### PHP 8.5固有機能の活用
- Named Arguments
- Constructor Property Promotion
- Match式
- Union Types、Nullsafe演算子
- PHP 8.5の新機能も積極的に活用
- FFI（Foreign Function Interface）: Windows APIの直接呼び出し

### Windows FFI の重要性
- Windows環境でボーレートを正確に設定するには FFI が必須
- `php.ini` で `extension=ffi` と `ffi.enable=true` を設定
- FFI 無効時は従来の Windows クラスにフォールバック（機能制限あり）
- 詳細は `docs/WINDOWS_FFI_SETUP.md` を参照

## 参考にした既存実装

- **Xowap/PHP-Serial**: 元祖PHP Serial、設計思想を参考
- **sanchescom/php-serial**: PHP 7リファクタ版、モダンな実装
- **PySerial**: Python実装、APIデザインの参考

## 開発の進め方

1. 最小限の機能で動作させる（open/close/read/write）
2. Windows環境で先行実装・テスト（開発環境）
3. Linux、macOSへの対応を追加
4. エラーハンドリングの充実
5. パフォーマンス改善（必要に応じてC拡張検討）
