# Tests

This plugin has unit tests, integration tests and end-to-end (E2E) tests. The unit tests cover the code that doesn't call WordPress, and run on your own PHP. The integration tests run PHPUnit inside a `wp-env` container, so they have access to a real WordPress install. The E2E tests drive the Zones screen in a real browser with [Playwright](https://playwright.dev/).

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

`wp-env start` builds the WordPress containers and mounts this plugin into them, on WordPress trunk and the latest PHP. To test another version, set `WP_ENV_CORE` and `WP_ENV_PHP_VERSION` when you start it (for example `WP_ENV_CORE=WordPress/WordPress#6.9 WP_ENV_PHP_VERSION=8.2 npx --no wp-env start`). The first run takes a minute or two.

## Running the tests

```bash
composer test:unit              # Unit tests; needs PHP 8.2 or later, but not Docker or wp-env
composer test:integration       # Single-site integration tests
composer test:integration-ms    # Same tests in multisite mode
```

The two integration commands run PHPUnit inside the wp-env `cli` container, so you don't need PHP locally to use them. The tests share the environment's database with the development site. `phpunit.xml.dist` gives the tests their own `wptests_` tables, so running them, through these scripts or by calling PHPUnit directly in the container, leaves your development content alone.

## End-to-end tests

The E2E tests in `tests/e2e/` cover what only a browser shows on the Zones screen: the zone form, adding content and the search spinner, ordering by keyboard and by dragging, focus after removing a post, the zone lock, and [axe](https://github.com/dequelabs/axe-core) accessibility checks. They don't re-test the REST API, permissions or template tags, which PHPUnit covers.

```bash
npx --no playwright install chromium   # Once, to download the browser
npm run test:e2e                        # Run every E2E test
npm run test:e2e -- --headed            # Watch them run
npm run test:e2e -- --debug             # Step through them in the Playwright inspector
```

They run against the development site at http://localhost:8888, so start `wp-env` first. If you started it on another port with `WP_ENV_PORT`, set the same `WP_ENV_PORT` when you run the tests, or set `WP_BASE_URL` to the site's URL.

The tests share the development site's database. Each test creates the zones, posts and users it needs through the REST API, and deletes only those when it ends, so your own content stays as it was. Don't add a test that empties the site, such as one calling `deleteAllPosts()`.

When a test fails, Playwright saves a screenshot and a trace in `artifacts/test-results/`. Open a trace with `npx playwright show-trace <path to trace.zip>`.

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
- `tests/e2e/fixtures.js` — the `zoninator` fixture, which creates test data and deletes it afterwards, and a helper to log in as a second user.
- `tests/e2e/*.spec.js` — the E2E tests. `playwright.config.js` extends the `@wordpress/scripts` config.

## Continuous integration

The matrix in `.github/workflows/integration.yml` runs the lowest supported combination (WP 6.9 / PHP 8.2), the "Tested up to" version (WP 7.1 / latest PHP) and the next version (`master` / latest PHP). Both single-site and multisite paths run on every push.

`.github/workflows/e2e.yml` runs the E2E tests on the "Tested up to" version (WP 7.1) with the latest PHP. If a test fails, the Playwright report, screenshots and traces are uploaded as the `playwright-report` artifact.
