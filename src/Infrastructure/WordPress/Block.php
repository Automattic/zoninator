<?php
/**
 * The Zone Posts variation of the Query Loop block.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use WP_Block;
use WP_REST_Request;

/**
 * Lets core's Query Loop block show a zone's posts.
 *
 * The editor script registers a Query Loop variation with a zone picker,
 * which saves the zone ID as zoninatorZone in the block's query. These
 * callbacks turn that into the zone's query, on the front end and in the
 * editor preview. It registers its own hooks in register_hooks(), and sites
 * reach it with zoninator_container()->get( Block::class ) to unhook them.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Block {

	/**
	 * The variation's name, saved as the Query Loop block's namespace.
	 */
	public const VARIATION = 'zoninator/zone-posts';

	/**
	 * Key in the block's query, and REST request param, that holds the zone ID.
	 */
	public const ZONE_KEY = 'zoninatorZone';

	/**
	 * Script handle of the editor script.
	 */
	public const SCRIPT_HANDLE = 'zoninator-block';

	/**
	 * Query args the block keeps, so its page size and pagination still work.
	 */
	private const KEPT_ARGS = array( 'posts_per_page', 'offset', 'paged' );

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 */
	public function __construct( private readonly Zones $zones ) {
	}

	/**
	 * Hooks the zone query into the Query Loop block and its editor. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_vars' ), 10, 2 );
		// The Zone Posts block's editor preview always reads /wp/v2/posts.
		add_filter( 'rest_post_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Turns a zone Query Loop's query into the zone's query on the front end.
	 *
	 * @param array    $query Query args core built from the block.
	 * @param WP_Block $block The Post Template or Query Pagination block inside the Query Loop.
	 * @return array
	 */
	public function filter_query_vars( $query, $block ) {
		// A Query Loop without the key isn't ours. With no zone picked yet, the key is 0 and nothing shows.
		if ( ! isset( $block->context['query'][ self::ZONE_KEY ] ) ) {
			return $query;
		}

		return $this->zones->get_query_args( absint( $block->context['query'][ self::ZONE_KEY ] ), array_intersect_key( $query, array_flip( self::KEPT_ARGS ) ) );
	}

	/**
	 * Turns the editor preview's REST request into the zone's query.
	 *
	 * The REST route lists one post type, so the preview only shows the zone's
	 * posts of that type.
	 *
	 * @param array           $args    Query args for the REST request.
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function filter_rest_query( $args, $request ) {
		if ( null === $request->get_param( self::ZONE_KEY ) ) {
			return $args;
		}

		$kept              = array_intersect_key( $args, array_flip( self::KEPT_ARGS ) );
		$kept['post_type'] = $args['post_type'];

		return $this->zones->get_query_args( absint( $request->get_param( self::ZONE_KEY ) ), $kept );
	}

	/**
	 * Loads the script that adds the variation and its zone picker to the editor.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugin_dir_url( ZONINATOR_FILE ) . 'js/zoninator-block.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			ZONINATOR_VERSION,
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'zoninator', dirname( ZONINATOR_FILE ) . '/languages' );
	}
}
