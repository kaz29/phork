# phork

[![Tests](https://github.com/kaz29/phork/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/phork/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

[English README is here](README.md)

**phork** は PHPUnit 向けの実行時間ベース並列テスト分散ツールです。過去のテスト実行時間データを利用してテストファイルを複数のワーカーに効率よく振り分け、ロードバランシングによる並列実行で CI の総実行時間を最小化します。

---

## 特徴

- **実行時間ベースの分散** – JUnit XML ログを解析し、実際の実行時間を考慮してテストの負荷を均等に分割
- **フォールバック対応** – 過去の実行データがない場合は自動的にラウンドロビン方式で分散
- **JUnit 出力のマージ** – 各ワーカーの JUnit XML レポートをひとつのファイルにまとめて出力
- **CPU コア数の自動検出** – 利用可能な CPU コア数に基づいてワーカー数を自動設定
- **PSR-4 対応** – `composer.json` を読み込み、ファイルパスと PHP クラス名を正しくマッピング

---

## 動作要件

- PHP 8.1 以上
- [Composer](https://getcomposer.org/)
- [Paratest](https://github.com/paratestphp/paratest) 7.0 以上（依存関係として自動インストール）

---

## インストール

```bash
composer require --dev kaz29/phork
```

---

## 使い方

### 基本的な並列実行

```bash
# 利用可能なすべての CPU コアを使ってテストを並列実行
./vendor/bin/phork --test-dir=tests/
```

### 実行時間ベースの分散

まず通常の PHPUnit 実行で JUnit のベースラインを生成します:

```bash
vendor/bin/phpunit --log-junit junit.xml
```

そのベースラインを使って次回の実行を負荷分散します:

```bash
./vendor/bin/phork --workers=4 --log=junit.xml --test-dir=tests/ --output=results.xml
```

---

## CLI オプション

| オプション | デフォルト | 説明 |
|---|---|---|
| `--workers=N` | 自動（CPU コア数） | 並列ワーカープロセスの数 |
| `--log=PATH` | *(なし)* | 実行時間ベース分散に使用する過去の JUnit XML ファイルのパス |
| `--test-dir=PATH` | `tests/` | `*Test.php` ファイルをスキャンするディレクトリ |
| `--output=PATH` | `--log` と同じ | マージした JUnit XML 結果の出力先パス |

---

## 動作の仕組み

1. **スキャン** – `--test-dir` 配下の `*Test.php` ファイルを再帰的に検出
2. **解析** – `--log` が指定されている場合、クラス名 → 実行時間のマップを構築
3. **分割** – 貪欲法によるロードバランシングアルゴリズムで `--workers` 個のバケツにテストを分散（過去データなしの場合はラウンドロビン）
4. **実行** – 各バケツに対して `paratest` プロセスを並列に起動
5. **マージ** – 全ワーカーの JUnit XML 結果を `--output` に統合

---

## Docker

リポジトリには PHP 8.3・8.4 用の Dockerfile とローカル開発用の `compose.yml` が含まれています:

```bash
# サービスを起動（PHP 8.4 + PostgreSQL）
docker compose up -d

# コンテナ内でテストを実行
docker compose exec phork-app vendor/bin/phpunit
```

---

## 開発

```bash
# 依存関係のインストール
composer install

# ユニットテストの実行
vendor/bin/phpunit --testsuite Unit

# インテグレーションテストの実行
vendor/bin/phpunit --testsuite Integration
```

---

## データベースの分離

並列テスト実行時は、各ワーカーごとに個別のデータベースが必要です。paratest が提供する `TEST_TOKEN` 環境変数を `bootstrap.php` で使用してください:

```php
// tests/bootstrap.php
$token = getenv('TEST_TOKEN') ?: '1';
putenv("DB_DATABASE=testdb_{$token}");
```

---

## ライセンス

このプロジェクトは [MIT ライセンス](LICENSE) のもとで公開されています。
