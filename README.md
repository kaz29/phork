# phork

[![Tests](https://github.com/kaz29/phork/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/phork/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

[日本語版 README はこちら](README.ja.md)

**phork** is a runtime-based parallel test distribution tool for PHPUnit. It intelligently distributes your test files across multiple workers using previous execution time data, minimising total CI time through load-balanced parallel runs.

---

## Features

- **Runtime-aware distribution** – parses JUnit XML logs to balance test load by actual execution time
- **Graceful fallback** – uses round-robin distribution when no prior runtime data is available
- **Merged JUnit output** – combines per-worker JUnit XML reports into a single file
- **Auto CPU detection** – automatically picks the number of workers based on available CPU cores
- **PSR-4 aware** – reads simple PSR-4 mappings in `composer.json` (one directory per namespace) to map file paths to PHP class names

---

## Requirements

- PHP 8.1 or later
- [Composer](https://getcomposer.org/)
- [Paratest](https://github.com/paratestphp/paratest) 7.0+ (installed automatically as a dependency)

---

## Installation

```bash
composer require --dev kaz29/phork
```

---

## Usage

### Basic parallel run

```bash
# Run tests in parallel across all available CPU cores
./vendor/bin/phork --test-dir=tests/
```

### Runtime-balanced run

First, generate a JUnit baseline with a regular PHPUnit run:

```bash
vendor/bin/phpunit --log-junit junit.xml
```

Then use that baseline to balance the next run:

```bash
./vendor/bin/phork --workers=4 --log=junit.xml --test-dir=tests/ --output=results.xml
```

---

## CLI Options

| Option | Default | Description |
|---|---|---|
| `--workers=N` | auto (CPU cores) | Number of parallel worker processes |
| `--log=PATH` | *(none)* | Path to a previous JUnit XML file used for runtime-based distribution |
| `--test-dir=PATH` | `tests/` | Directory to scan for `*Test.php` files |
| `--output=PATH` | same as `--log` | Path to write the merged JUnit XML result |

> **Note:** If you specify `--log` but omit `--output`, the merged result will overwrite your baseline JUnit file. It is recommended to always set `--output` explicitly.

---

## How It Works

1. **Scan** – recursively discovers all `*Test.php` files under `--test-dir`
2. **Parse** – reads `--log` (if provided) to build a class → execution-time map
3. **Split** – distributes test files into `--workers` buckets using a greedy load-balancing algorithm (falls back to round-robin with no prior data)
4. **Run** – spawns one `paratest` process per bucket in parallel
5. **Merge** – combines JUnit XML results from all workers into `--output`

---

## Docker

The repository ships Dockerfiles for PHP 8.3 and 8.4 and a `compose.yml` for local development:

```bash
# Start services (PHP 8.4 + PostgreSQL)
docker compose up -d

# Run tests inside the container
docker compose exec phork-app vendor/bin/phpunit
```

---

## Development

```bash
# Install dependencies
composer install

# Run unit tests
vendor/bin/phpunit --testsuite Unit

# Run integration tests
vendor/bin/phpunit --testsuite Integration
```

---

## Database Isolation

When running tests in parallel, each worker needs its own database. Use the `TEST_TOKEN` environment variable provided by paratest in your `bootstrap.php`:

```php
// tests/bootstrap.php
$token = getenv('TEST_TOKEN') ?: '1';
putenv("DB_DATABASE=testdb_{$token}");
```

---

## License

This project is licensed under the [MIT License](LICENSE).

