# 開発ガイド

## 目次

- [開発環境のセットアップ](#開発環境のセットアップ)
- [品質確認手順](#品質確認手順)
- [テストの実行](#テストの実行)
- [コントリビューション](#コントリビューション)

## 開発環境のセットアップ

### 必要な環境

- PHP 8.1以上
- Composer
- Git

### 依存関係のインストール

```bash
composer install
```

## 品質確認手順

コードの品質を保証するため、以下のツールを使用しています。

### 一括チェック

すべての品質チェックを一度に実行:

```bash
composer qa
```

このコマンドは以下を順番に実行します。

1. PHP_CodeSniffer (PSR-12準拠チェック)
2. PHP-CS-Fixer (コードスタイルチェック)
3. PHPStan (静的解析)
4. PHPUnit (テスト)

### 個別チェック

#### 1. PSR-12準拠チェック (PHP_CodeSniffer)

コーディング規約の確認:

```bash
composer cs:check
```

自動修正:

```bash
composer cs:fix
```

#### 2. コードスタイルチェック (PHP-CS-Fixer)

より詳細なスタイルチェック:

```bash
composer cs:fixer
```

自動修正:

```bash
composer cs:fixer:fix
```

#### 3. 静的解析 (PHPStan)

型安全性とバグのチェック (レベル8):

```bash
composer stan
```

#### 4. テスト実行

```bash
composer test
```

カバレッジ付き:

```bash
composer test:coverage
```

## PSR準拠

このプロジェクトは以下のPSRに準拠しています。

### PSR-4 (オートロード)

ネームスペースとディレクトリ構造の確認:

```bash
composer validate --strict
```

### PSR-12 (コーディング規約)

上記の`composer cs:check`で確認できます。

## コミット前の確認

コミット前に必ず以下を実行してください。

```bash
composer qa
```

すべてのチェックがパスすることを確認してからコミットしてください。

## GitHub Actions

プルリクエストやpush時に自動で以下が実行されます。

- PHP 8.1, 8.2, 8.3, 8.4, 8.5での動作確認
- Windows, Linux, macOSでの動作確認
- コード品質チェック (PSR-12, PHPStan)
- テスト実行

詳細は[.github/workflows/ci.yml](.github/workflows/ci.yml)を参照してください。

## コントリビューション

1. このリポジトリをフォーク
2. フィーチャーブランチを作成 (`git checkout -b feature/amazing-feature`)
3. 変更をコミット (`git commit -m 'Add amazing feature'`)
4. ブランチにプッシュ (`git push origin feature/amazing-feature`)
5. プルリクエストを作成

### プルリクエストの要件

- すべての品質チェックがパス (`composer qa`)
- 新機能にはテストを追加
- PSR-12, PSR-4に準拠
- PHPStan レベル8でエラーなし

## トラブルシューティング

### PHPStanでFFI関連のエラーが出る

FFI関連のエラーは`phpstan.neon`で適切に無視設定されています。
新たなエラーが出た場合は、以下を確認してください。

- FFIのメソッドは動的に追加されるため、静的解析では検出できません
- FFI\CDataのプロパティも実行時に決定されます

これらは正常な動作です。

### PHP-CS-Fixerでバージョン警告が出る

開発環境のPHPバージョンが8.1より新しい場合、警告が表示されることがあります。
これは情報提供のみで、エラーではありません。
