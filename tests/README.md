# Tests

This plugin has integration tests only — no separate unit suite. Tests run inside a `wp-env` container so they have access to a real WordPress install.

## Prerequisites

- Docker (required by `wp-env`).
- Node.js (any LTS version) for `wp-env`, which `npm ci` installs.
- Composer for PHP dependencies.

## One-time setup

```bash
composer install
npm ci
npx --no wp-env start
```

`wp-env start` builds the WordPress containers and mounts this plugin into them, on WordPress trunk and the latest PHP. To test another version, set `WP_ENV_CORE` and `WP_ENV_PHP_VERSION` when you start it (for example `WP_ENV_CORE=WordPress/WordPress#6.4 WP_ENV_PHP_VERSION=7.4 npx wp-env start`). The first run takes a minute or two.

## Running the tests

```bash
composer test:integration       # Single-site integration tests
composer test:integration-ms    # Same tests in multisite mode
```

Both commands run PHPUnit inside the wp-env `cli` container, so you don't need PHP locally to use them. The tests share the environment's database with the development site. `phpunit.xml.dist` gives the tests their own `wptests_` tables, so running them, through these scripts or by calling PHPUnit directly in the container, leaves your development content alone.

## Coverage

Coverage needs Xdebug, which `wp-env` turns off by default. Start the environment with coverage mode first:

```bash
npx --no wp-env start --xdebug=coverage
composer coverage               # Generates HTML coverage in build/coverage-html/
composer coverage-ci            # Prints a text coverage summary
```

## Stopping the environment

```bash
npx --no wp-env stop
```

## Where tests live

- `tests/bootstrap.php` — PHPUnit bootstrap (uses `yoast/wp-test-utils`).
- `tests/Integration/TestCase.php` — base class. Extend this for new tests.
- `tests/Integration/*Test.php` — the test files themselves.

## Continuous integration

The matrix in `.github/workflows/integration.yml` runs the lowest supported combination (WP 6.4 / PHP 7.4), the "Tested up to" version (WP 7.1 / latest PHP) and the next version (`master` / latest PHP). Both single-site and multisite paths run on every push.
