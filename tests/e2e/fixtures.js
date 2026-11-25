/**
 * Test fixtures for the Zones screen.
 *
 * The tests share the development site's database, so they never empty it.
 * The `zoninator` fixture creates zones, posts and users through the REST API,
 * and deletes only those once the test ends, whether it passed or not.
 */
const { randomUUID } = require( 'node:crypto' );
const base = require( '@wordpress/e2e-test-utils-playwright' );

const PASSWORD = 'zoninator-e2e-password';

// Unique per run, so a test never trips over a leftover with the same name.
const unique = () => randomUUID().slice( 0, 8 );

const test = base.test.extend( {
	zoninator: async ( { requestUtils }, use ) => {
		const created = { zones: [], posts: [], users: [] };

		// Playwright's use(), not a React hook.
		// eslint-disable-next-line react-hooks/rules-of-hooks
		await use( {
			unique,

			async createZone(
				name = `E2E zone ${ unique() }`,
				description = ''
			) {
				const zone = await requestUtils.rest( {
					method: 'POST',
					path: '/zoninator/v1/zones',
					data: { name, description },
				} );
				created.zones.push( zone.term_id );
				return zone;
			},

			// For a zone the test made through the screen.
			trackZone( zoneId ) {
				created.zones.push( Number( zoneId ) );
			},

			// Creates published posts, newest last, so the latest content lists them first.
			async createPosts( titles ) {
				const posts = [];
				for ( const title of titles ) {
					const post = await requestUtils.createPost( {
						title,
						status: 'publish',
					} );
					created.posts.push( post.id );
					posts.push( post );
				}
				return posts;
			},

			async setZonePosts( zoneId, postIds ) {
				await requestUtils.rest( {
					method: 'PUT',
					path: `/zoninator/v1/zones/${ zoneId }/posts`,
					data: { post_ids: postIds },
				} );
			},

			async createEditor() {
				const username = `e2e-editor-${ unique() }`;
				const user = await requestUtils.createUser( {
					username,
					email: `${ username }@example.com`,
					password: PASSWORD,
					roles: [ 'editor' ],
				} );
				created.users.push( user.id );
				return { ...user, username, password: PASSWORD };
			},
		} );

		// Clean up everything, even if one deletion fails, then report the first failure.
		// The test may already have deleted its zone, so a zone that's gone is fine.
		const failures = [];
		const remove = ( path ) =>
			requestUtils
				.rest( {
					method: 'DELETE',
					path,
					params: { force: true, reassign: 1 },
				} )
				.catch( ( error ) => failures.push( error ) );
		for ( const zoneId of created.zones ) {
			await requestUtils
				.rest( {
					method: 'DELETE',
					path: `/zoninator/v1/zones/${ zoneId }`,
				} )
				.catch( () => {} );
		}
		for ( const postId of created.posts ) {
			await remove( `/wp/v2/posts/${ postId }` );
		}
		for ( const userId of created.users ) {
			await remove( `/wp/v2/users/${ userId }` );
		}
		if ( failures.length ) {
			throw failures[ 0 ];
		}
	},
} );

/**
 * Opens a page logged in as another user, in a browser context of its own.
 *
 * @param {import('@playwright/test').Browser}   browser
 * @param {string}                               baseURL
 * @param {{username: string, password: string}} user
 * @return {Promise<import('@playwright/test').Page>} The logged-in page; close its context when done.
 */
async function loginAs( browser, baseURL, { username, password } ) {
	const context = await browser.newContext( { baseURL } );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( username );
	await page.getByLabel( 'Password', { exact: true } ).fill( password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForURL( /wp-admin/ );
	return page;
}

const zoneUrl = ( zoneId ) =>
	`/wp-admin/admin.php?page=zoninator&action=edit&zone_id=${ zoneId }`;

module.exports = { test, expect: base.expect, loginAs, zoneUrl };
