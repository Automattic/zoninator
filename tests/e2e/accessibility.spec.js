/**
 * axe checks on the Zones screen, in each state an editor sees it.
 *
 * Only the screen itself is scanned (#wpbody-content): the admin menu and
 * toolbar are core's, and other plugins add to them. Core's "update WordPress"
 * nag is left out too: it shows only when a newer release is out, and its link
 * fails label-content-name-mismatch, which is core's to fix.
 */
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { test, expect, loginAs, zoneUrl } = require( './fixtures' );

async function expectNoSeriousViolations( page ) {
	const results = await new AxeBuilder( { page } )
		.include( '#wpbody-content' )
		.exclude( '.update-nag' )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
		.analyze();

	const serious = results.violations.filter( ( violation ) =>
		[ 'serious', 'critical' ].includes( violation.impact )
	);
	// Report the rule, and where it failed, rather than the whole result.
	expect(
		serious.map( ( { id, nodes } ) => ( {
			id,
			targets: nodes.map( ( node ) => node.target.join( ' ' ) ),
		} ) )
	).toEqual( [] );
}

test.describe( 'Zones screen accessibility', () => {
	test( 'has no serious violations with no zone open', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=zoninator' );
		await expectNoSeriousViolations( page );
	} );

	test( 'has no serious violations with a zone open for editing', async ( {
		page,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone(
			undefined,
			'A zone to check.'
		);
		const posts = await zoninator.createPosts( [
			`First ${ zoninator.unique() }`,
			`Second ${ zoninator.unique() }`,
		] );
		await zoninator.setZonePosts(
			zone.term_id,
			posts.map( ( post ) => post.id )
		);

		await page.goto( zoneUrl( zone.term_id ) );
		await expectNoSeriousViolations( page );
	} );

	test( 'has no serious violations with a zone locked by someone else', async ( {
		page,
		browser,
		baseURL,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone();
		const [ post ] = await zoninator.createPosts( [
			`Locked ${ zoninator.unique() }`,
		] );
		await zoninator.setZonePosts( zone.term_id, [ post.id ] );
		const editor = await zoninator.createEditor();

		// The admin's screen holds the lock.
		const locked = page.waitForResponse(
			( response ) =>
				decodeURIComponent( response.url() ).includes(
					`zones/${ zone.term_id }/lock`
				) && response.ok()
		);
		await page.goto( zoneUrl( zone.term_id ) );
		await locked;

		const editorPage = await loginAs( browser, baseURL, editor );
		try {
			await editorPage.goto( zoneUrl( zone.term_id ) );
			await expect(
				editorPage.locator( '.zone-posts-wrapper.readonly' )
			).toBeVisible();
			await expectNoSeriousViolations( editorPage );
		} finally {
			await editorPage.context().close();
		}
	} );
} );
