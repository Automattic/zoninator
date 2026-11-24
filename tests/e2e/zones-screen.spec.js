/**
 * The Zones screen in a real browser: what an editor does with it, each flow once.
 *
 * PHPUnit already pins the REST routes, permissions and template tags. These
 * tests cover what only a browser shows: the form, the search and its spinner,
 * keyboard and drag ordering, focus, announcements and the zone lock.
 */
const { test, expect, loginAs, zoneUrl } = require( './fixtures' );

// The IDs of the posts in the zone, in the order the screen shows them.
const postIdsInZone = ( page ) =>
	page
		.locator( '.zone-posts-list .zone-post' )
		.evaluateAll( ( rows ) =>
			rows.map( ( row ) => Number( row.dataset.postId ) )
		);

// Resolves once the zone's posts have been saved, which is how a new order is stored.
// apiFetch sends the PUT as a POST with an X-HTTP-Method-Override header.
const zonePostsSaved = ( page, zoneId ) =>
	page.waitForResponse(
		( response ) =>
			'PUT' ===
				response.request().headers()[ 'x-http-method-override' ] &&
			new RegExp( `zoninator/v1/zones/${ zoneId }/posts(\\?|$)` ).test(
				decodeURIComponent( response.url() )
			) &&
			response.ok()
	);

const searchField = ( page ) =>
	page.getByRole( 'textbox', { name: 'Add content' } );
const suggestion = ( page, title ) =>
	page.locator( '.ui-autocomplete li', { hasText: title } );

test.describe( 'Zones screen', () => {
	test( 'creates a zone, then edits its name and description', async ( {
		page,
		zoninator,
	} ) => {
		const name = `E2E zone ${ zoninator.unique() }`;
		const newName = `${ name } renamed`;

		await page.goto( '/wp-admin/admin.php?page=zoninator' );
		await page.getByLabel( 'Name', { exact: true } ).fill( name );
		await page
			.getByLabel( 'Description' )
			.fill( 'Stories for the front page.' );
		await page.getByRole( 'button', { name: 'Add New Zone' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Edit Zone' } )
		).toBeVisible();
		zoninator.trackZone(
			new URL( page.url() ).searchParams.get( 'zone_id' )
		);
		await expect( page.locator( '#zone-message' ) ).toHaveText(
			'The zone was successfully created.'
		);
		await expect(
			page.locator( '.zone-tabs' ).getByRole( 'link', { name } )
		).toHaveAttribute( 'aria-current', 'page' );

		await page.getByLabel( 'Name', { exact: true } ).fill( newName );
		await page
			.getByLabel( 'Description' )
			.fill( 'Stories for the home page.' );
		await page.getByRole( 'button', { name: 'Update' } ).click();

		await expect( page.locator( '#zone-message' ) ).toHaveText(
			'The zone was successfully updated.'
		);
		await expect(
			page.locator( '.zone-tabs' ).getByRole( 'link', { name: newName } )
		).toHaveAttribute( 'aria-current', 'page' );
		await expect(
			page
				.locator( '.zone-tabs' )
				.getByRole( 'link', { name, exact: true } )
		).toHaveCount( 0 );
		await expect( page.getByLabel( 'Name', { exact: true } ) ).toHaveValue(
			newName
		);
		await expect( page.getByLabel( 'Description' ) ).toHaveValue(
			'Stories for the home page.'
		);
	} );

	test( 'adds content from the latest content, and the spinner clears', async ( {
		page,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone();
		const title = `Latest ${ zoninator.unique() }`;
		const [ post ] = await zoninator.createPosts( [ title ] );

		await page.goto( zoneUrl( zone.term_id ) );
		// A click focuses the field too, so it starts two searches. Each must be answered,
		// or jQuery UI keeps the loading spinner on (fixed in #234).
		await searchField( page ).click();

		await expect( suggestion( page, title ) ).toBeVisible();
		await expect( searchField( page ) ).not.toHaveClass(
			/ui-autocomplete-loading/
		);
		await expect(
			page.locator( '.zone-posts-save-input .spinner' )
		).not.toHaveClass( /is-active/ );

		const saved = zonePostsSaved( page, zone.term_id );
		await suggestion( page, title ).click();
		await saved;

		await expect(
			page.locator( '.zone-posts-list .zone-post', { hasText: title } )
		).toBeVisible();
		await expect( searchField( page ) ).toHaveValue( '' );

		await page.reload();
		expect( await postIdsInZone( page ) ).toEqual( [ post.id ] );
	} );

	test( 'adds content found by title and by post ID', async ( {
		page,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone();
		const suffix = zoninator.unique();
		const [ alpha, bravo ] = await zoninator.createPosts( [
			`Alpha ${ suffix }`,
			`Bravo ${ suffix }`,
		] );

		await page.goto( zoneUrl( zone.term_id ) );

		// Focusing the field shows the latest content, which has both posts, and the search
		// answers after it. Wait until only the post searched for is offered.
		const suggestions = page.locator( '.ui-autocomplete li:visible' );
		await searchField( page ).fill( `Alpha ${ suffix }` );
		await expect( suggestions ).toHaveText( [
			new RegExp( `^Alpha ${ suffix }` ),
		] );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );
		await expect
			.poll( () => postIdsInZone( page ) )
			.toEqual( [ alpha.id ] );

		await searchField( page ).fill( String( bravo.id ) );
		await expect( suggestions ).toHaveText( [
			new RegExp( `^Bravo ${ suffix }` ),
		] );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );

		await expect
			.poll( () => postIdsInZone( page ) )
			.toEqual( [ alpha.id, bravo.id ] );
	} );

	test( 'reorders with Move up and Move down from the keyboard, and announces each move', async ( {
		page,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone();
		const suffix = zoninator.unique();
		const [ a, b, c ] = await zoninator.createPosts( [
			`A ${ suffix }`,
			`B ${ suffix }`,
			`C ${ suffix }`,
		] );
		await zoninator.setZonePosts( zone.term_id, [ a.id, b.id, c.id ] );

		await page.goto( zoneUrl( zone.term_id ) );
		const status = page.locator( '#zone-posts-save-info[role="status"]' );
		const announcement = page.locator( '#a11y-speak-polite' );
		const moveDownA = page.getByRole( 'button', {
			name: `Move down “A ${ suffix }”`,
		} );

		await moveDownA.focus();
		let saved = zonePostsSaved( page, zone.term_id );
		await page.keyboard.press( 'Enter' );
		await saved;
		expect( await postIdsInZone( page ) ).toEqual( [ b.id, a.id, c.id ] );
		await expect( status ).toHaveText( /^Saved at / );
		await expect( announcement ).toHaveText(
			'Item moved to position 2 of 3.'
		);

		// Focus stays on the button, so pressing again carries on moving the same post.
		await expect( moveDownA ).toBeFocused();
		await status.evaluate( ( element ) => ( element.textContent = '' ) );
		saved = zonePostsSaved( page, zone.term_id );
		await page.keyboard.press( 'Enter' );
		await saved;
		expect( await postIdsInZone( page ) ).toEqual( [ b.id, c.id, a.id ] );
		await expect( status ).toHaveText( /^Saved at / );
		await expect( announcement ).toHaveText(
			'Item moved to position 3 of 3.'
		);

		await page
			.getByRole( 'button', { name: `Move up “C ${ suffix }”` } )
			.focus();
		await status.evaluate( ( element ) => ( element.textContent = '' ) );
		saved = zonePostsSaved( page, zone.term_id );
		await page.keyboard.press( 'Enter' );
		await saved;
		expect( await postIdsInZone( page ) ).toEqual( [ c.id, b.id, a.id ] );
		await expect( status ).toHaveText( /^Saved at / );
		await expect( announcement ).toHaveText(
			'Item moved to position 1 of 3.'
		);

		await page.reload();
		expect( await postIdsInZone( page ) ).toEqual( [ c.id, b.id, a.id ] );
	} );

	test( 'reorders by dragging', async ( { page, zoninator } ) => {
		const zone = await zoninator.createZone();
		const suffix = zoninator.unique();
		const [ a, b, c ] = await zoninator.createPosts( [
			`A ${ suffix }`,
			`B ${ suffix }`,
			`C ${ suffix }`,
		] );
		await zoninator.setZonePosts( zone.term_id, [ a.id, b.id, c.id ] );

		await page.goto( zoneUrl( zone.term_id ) );
		// Grab the position number: jQuery UI won't start a drag on a button.
		const from = await page
			.locator( `#zone-post-${ c.id } .zone-post-position` )
			.boundingBox();
		const to = await page.locator( `#zone-post-${ a.id }` ).boundingBox();

		const saved = zonePostsSaved( page, zone.term_id );
		await page.mouse.move(
			from.x + from.width / 2,
			from.y + from.height / 2
		);
		await page.mouse.down();
		await page.mouse.move( from.x + from.width / 2, from.y - 10, {
			steps: 5,
		} );
		await page.mouse.move( to.x + to.width / 2, to.y + 2, { steps: 20 } );
		await page.mouse.up();
		await saved;

		expect( await postIdsInZone( page ) ).toEqual( [ c.id, a.id, b.id ] );
		await page.reload();
		expect( await postIdsInZone( page ) ).toEqual( [ c.id, a.id, b.id ] );
	} );

	test( 'removes posts, moving focus to the next Remove button, then to the search field', async ( {
		page,
		zoninator,
	} ) => {
		const zone = await zoninator.createZone();
		const suffix = zoninator.unique();
		const [ a, b ] = await zoninator.createPosts( [
			`A ${ suffix }`,
			`B ${ suffix }`,
		] );
		await zoninator.setZonePosts( zone.term_id, [ a.id, b.id ] );

		await page.goto( zoneUrl( zone.term_id ) );

		await page
			.getByRole( 'button', { name: `Remove “A ${ suffix }”` } )
			.focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( `#zone-post-${ a.id }` ) ).toHaveCount( 0 );
		await expect(
			page.getByRole( 'button', { name: `Remove “B ${ suffix }”` } )
		).toBeFocused();
		await expect( page.locator( '#a11y-speak-polite' ) ).toHaveText(
			'Item removed from the zone.'
		);

		await page.keyboard.press( 'Enter' );
		await expect( page.locator( `#zone-post-${ b.id }` ) ).toHaveCount( 0 );
		await expect( searchField( page ) ).toBeFocused();

		await page.reload();
		expect( await postIdsInZone( page ) ).toEqual( [] );
	} );

	test( 'shows a zone open elsewhere as locked and read only', async ( {
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

		// The admin opens the zone, and the screen takes the lock.
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
				editorPage.getByText(
					'Sorry, this zone is in use by admin and is currently locked.'
				)
			).toBeVisible();
			await expect(
				editorPage.locator( '.zone-posts-wrapper' )
			).toHaveClass( /readonly/ );
			await expect(
				editorPage.locator( `#zone-post-${ post.id }` )
			).toBeVisible();
			await expect(
				editorPage.locator( '.zone-posts-list' )
			).not.toHaveClass( /ui-sortable/ );
			await expect( searchField( editorPage ) ).toBeHidden();
			await expect(
				editorPage.locator( `#zone-post-${ post.id } .row-actions` )
			).toBeHidden();
			await expect( editorPage.locator( '#zone-info' ) ).toHaveCount( 0 );
			await expect(
				editorPage.locator( '#zone-info-readonly' )
			).toContainText( zone.name );
		} finally {
			await editorPage.context().close();
		}
	} );

	test( 'deletes a zone', async ( { page, zoninator } ) => {
		const zone = await zoninator.createZone();

		await page.goto( zoneUrl( zone.term_id ) );
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page.getByRole( 'link', { name: 'Delete' } ).click();

		await expect( page.locator( '#zone-message' ) ).toHaveText(
			'The zone was successfully deleted.'
		);
		await expect(
			page
				.locator( '.zone-tabs' )
				.getByRole( 'link', { name: zone.name } )
		).toHaveCount( 0 );
	} );
} );
