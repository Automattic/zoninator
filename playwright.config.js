/**
 * Playwright configuration for Zoninator's end-to-end tests.
 *
 * Extends the `@wordpress/scripts` config, whose global setup logs in as the
 * admin, and points it at tests/e2e.
 *
 * wp-env runs one environment, so the tests use the development site on
 * WP_ENV_PORT (8888 by default). Start it first; the config doesn't. Set
 * WP_BASE_URL to test another site.
 */
process.env.WP_BASE_URL ??= `http://localhost:${ process.env.WP_ENV_PORT || 8888 }`;

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = {
	...baseConfig,
	testDir: './tests/e2e',
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never' } ] ]
		: [ [ 'list' ] ],
	webServer: undefined,
};
