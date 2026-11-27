/**
 * Takes the WordPress.org screenshots from a wp-env site with the sample content.
 *
 * Use a released WordPress, not trunk, with debugging off, so nothing in the
 * shots is unreleased or a notice. Put this in .wp-env.override.json:
 *   { "core": "WordPress/WordPress#7.1", "config": { "WP_DEBUG": false, "SCRIPT_DEBUG": false } }
 *
 * Then run from the plugin root, on a fresh site (`npx --no wp-env clean all`):
 *   npx --no wp-env start
 *   npx --no wp-env run cli wp plugin deactivate query-monitor
 *   npx --no wp-env run cli --env-cwd=wp-content/plugins/<checkout> wp eval-file tools/wporg-assets/sample-data.php
 *   node tools/wporg-assets/screenshots.mjs
 *   oxipng -o max --strip safe .wordpress-org/screenshot-*.png
 *
 * Set WP_ENV_PORT if the site isn't on 8888.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const base = `http://localhost:${ process.env.WP_ENV_PORT || 8888 }`;
const out = ( n ) => `.wordpress-org/screenshot-${ n }.png`;
const wp = ( ...args ) => execFileSync( 'npx', [ '--no', 'wp-env', 'run', 'cli', 'wp', ...args ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();

// Update nags depend on when the shots are taken, not on the plugin.
const hideNags = '.update-nag, #wp-admin-bar-updates, #footer-upgrade { display: none !important; }';

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 } );
const admin = await context.newPage();
admin.on( 'load', () => admin.addStyleTag( { content: hideNags } ).catch( () => {} ) );

await admin.goto( `${ base }/wp-login.php` );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'password' );
await admin.click( '#wp-submit' );
await admin.waitForURL( /wp-admin/ );


// 1. The Zones screen, with the list of zones.
await admin.setViewportSize( { width: 1280, height: 560 } );
await admin.goto( `${ base }/wp-admin/admin.php?page=zoninator` );
await admin.addStyleTag( { content: hideNags } );
await admin.screenshot( { path: out( 1 ) } );

// 2. Editing a zone, with the Add content search open.
await admin.setViewportSize( { width: 1280, height: 800 } );
await admin.goto( `${ base }/wp-admin/admin.php?page=zoninator&action=edit&zone_id=${ wp( 'term', 'list', 'zoninator_zones', '--slug=homepage', '--field=term_id' ) }` );
await admin.addStyleTag( { content: hideNags } );
await admin.click( '#zone-post-search' );
await admin.keyboard.type( 'new', { delay: 50 } );
// Clicking shows the latest content first; wait for the search's own results.
await admin.locator( '.ui-autocomplete .ui-menu-item', { hasText: 'bold new staging' } ).waitFor();
await admin.waitForTimeout( 500 );
await admin.screenshot( { path: out( 2 ) } );

// 3. The Zone Posts block in the editor, selected, with its Zone panel.
const pageId = wp( 'post', 'list', '--post_type=page', '--name=today', '--field=ID' );
await admin.goto( `${ base }/wp-admin/post.php?post=${ pageId }&action=edit` );
await admin.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' )?.getBlocks().length > 0 );
await admin.evaluate( () => {
	const { dispatch, select } = window.wp.data;
	dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
	dispatch( 'core/preferences' ).set( 'core', 'fixedToolbar', false );
	const query = select( 'core/block-editor' ).getBlocks().find( ( b ) => b.name === 'core/query' );
	dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	dispatch( 'core/block-editor' ).selectBlock( query.clientId );
} );
await admin.getByRole( 'button', { name: 'Zone', exact: true } ).waitFor();
await admin.waitForTimeout( 1500 );
await admin.screenshot( { path: out( 3 ) } );

// 4 and 5 are what a visitor sees, so they're taken logged out.
const visitor = await browser.newPage( { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 } );

// 4. The block's output on the front end.
await visitor.goto( `${ base }/today/` );
await visitor.screenshot( { path: out( 4 ) } );

// 5. Zone Posts widgets in a classic theme's sidebar.
wp( 'theme', 'activate', 'twentyseventeen' );
try {
	await visitor.goto( `${ base }/late-penalty-seals-derby-win-in-front-of-a-sell-out-crowd/` );
	await visitor.locator( '#secondary' ).scrollIntoViewIfNeeded();
	await visitor.evaluate( () => window.scrollTo( 0, document.querySelector( '.site-content' ).offsetTop - 40 ) );
	await visitor.screenshot( { path: out( 5 ) } );
} finally {
	wp( 'theme', 'activate', 'twentytwentyfive' );
}

await browser.close();
