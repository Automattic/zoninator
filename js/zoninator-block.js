/**
 * Adds the Zone Posts variation of the Query Loop block, with a zone picker.
 *
 * The zone ID is saved as zoninatorZone in the block's query. The server turns
 * it into the zone's query, so posts show in the zone's order.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var VARIATION = 'zoninator/zone-posts';

	wp.blocks.registerBlockVariation( 'core/query', {
		name: VARIATION,
		title: __( 'Zone Posts', 'zoninator' ),
		description: __( 'Show the posts in a zone, in the zone\'s order.', 'zoninator' ),
		icon: 'list-view',
		scope: [ 'inserter' ],
		isActive: [ 'namespace' ],
		attributes: {
			namespace: VARIATION,
			query: {
				perPage: 10,
				pages: 0,
				offset: 0,
				postType: 'post',
				// The REST API maps some orderBy values, such as id, over the zone order in the preview.
				orderBy: 'date',
				inherit: false,
				zoninatorZone: 0,
			},
		},
		// The zone sets the posts and their order, so only paging controls make sense.
		allowedControls: [ 'postCount', 'offset', 'pages' ],
		// A zone is a list of posts to read, so each title links to its post, as in the Zone Posts
		// widget. The title starts as a heading, as in core's Query Loop, and can be a paragraph.
		innerBlocks: [
			[ 'core/post-template', {}, [ [ 'core/post-title', { isLink: true, levelOptions: [ 0, 1, 2, 3, 4, 5, 6 ] } ] ] ],
		],
	} );

	function ZonePicker( props ) {
		var state = wp.element.useState( null );
		var zones = state[ 0 ];
		var setZones = state[ 1 ];
		var errorState = wp.element.useState( false );
		var failed = errorState[ 0 ];
		var setFailed = errorState[ 1 ];
		var query = props.attributes.query || {};

		wp.element.useEffect( function () {
			wp.apiFetch( { path: '/zoninator/v1/zones' } )
				.then( setZones )
				.catch( function () {
					setFailed( true );
				} );
		}, [] );

		if ( failed ) {
			return el( wp.components.Notice, { status: 'error', isDismissible: false }, __( 'Zones could not be loaded.', 'zoninator' ) );
		}

		if ( ! zones ) {
			return el(
				wp.element.Fragment,
				null,
				el( wp.components.Spinner ),
				el( wp.components.VisuallyHidden, null, __( 'Loading zones…', 'zoninator' ) )
			);
		}

		return el( wp.components.SelectControl, {
			label: __( 'Zone', 'zoninator' ),
			value: query.zoninatorZone || 0,
			options: [ { label: __( 'Select a zone', 'zoninator' ), value: 0 } ].concat(
				zones.map( function ( zone ) {
					return { label: zone.name, value: zone.term_id };
				} )
			),
			onChange: function ( value ) {
				props.setAttributes( {
					query: Object.assign( {}, query, { zoninatorZone: parseInt( value, 10 ) || 0 } ),
				} );
			},
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} );
	}

	var withZonePicker = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( 'core/query' !== props.name || VARIATION !== props.attributes.namespace ) {
				return el( BlockEdit, props );
			}

			return el(
				wp.element.Fragment,
				null,
				el(
					wp.blockEditor.InspectorControls,
					null,
					el( wp.components.PanelBody, { title: __( 'Zone', 'zoninator' ) }, el( ZonePicker, props ) )
				),
				el( BlockEdit, props )
			);
		};
	}, 'withZonePicker' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'zoninator/zone-picker', withZonePicker );
} )( window.wp );
