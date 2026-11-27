<?php
/**
 * Base test case for integration tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenDataLoader;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenView;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Base test case for integration tests, with the plugin's own objects to hand.
 */
abstract class TestCase extends WPIntegrationTestCase {

	/**
	 * Core keeps the scripts and styles a test enqueues in globals that
	 * WP_UnitTestCase doesn't reset. Drop them, and the next call to
	 * wp_scripts() or wp_styles() builds fresh ones.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
		parent::tearDown();
	}

	/**
	 * Creates a zone, for a test to work on.
	 *
	 * @param string $slug    The zone's slug.
	 * @param string $name    The zone's name, or '' to use the slug.
	 * @param array  $details Details to set, by name.
	 * @return int The new zone's ID.
	 */
	protected static function insert_zone( string $slug, string $name = '', array $details = array() ): int {
		return (int) zoninator_container()->get( ZoneManager::class )->create( $slug, $name, $details )->get_id();
	}

	/**
	 * Changes a zone.
	 *
	 * @param int|string|\WP_Term $zone Zone ID, slug or term.
	 * @param array               $data The name, slug and details to change.
	 */
	protected static function update_zone( $zone, array $data = array() ): void {
		zoninator_container()->get( ZoneManager::class )->update( self::zones()->get_id( $zone ), $data );
	}

	/**
	 * Deletes a zone.
	 *
	 * @param int|string|\WP_Term $zone Zone ID, slug or term.
	 */
	protected static function delete_zone( $zone ): void {
		zoninator_container()->get( ZoneManager::class )->remove( self::zones()->get_id( $zone ) );
	}

	/**
	 * Sets a zone's posts, or adds posts to the end of it.
	 *
	 * @param int|string|\WP_Term $zone   Zone ID, slug or term.
	 * @param mixed               $posts  A post or posts, as IDs or WP_Post objects.
	 * @param bool                $append Whether to add the posts to the end. Otherwise they replace the zone's posts.
	 */
	protected static function add_zone_posts( $zone, $posts, bool $append = false ): void {
		$curator = zoninator_container()->get( ZoneCurator::class );
		$zone_id = self::zones()->get_id( $zone );

		if ( $append ) {
			$curator->add_posts( $zone_id, self::post_ids( $posts ) );
		} else {
			$curator->set_posts( $zone_id, self::post_ids( $posts ) );
		}
	}

	/**
	 * Takes posts out of a zone, or empties it.
	 *
	 * @param int|string|\WP_Term $zone  Zone ID, slug or term.
	 * @param mixed               $posts A post or posts, as IDs or WP_Post objects. Leave out to empty the zone.
	 */
	protected static function remove_zone_posts( $zone, $posts = null ): void {
		$curator = zoninator_container()->get( ZoneCurator::class );
		$zone_id = self::zones()->get_id( $zone );

		if ( null === $posts ) {
			$curator->clear_posts( $zone_id );
		} else {
			$curator->remove_posts( $zone_id, self::post_ids( $posts ) );
		}
	}

	/**
	 * @param mixed $posts A post or posts, as IDs or WP_Post objects.
	 * @return int[] Their IDs.
	 */
	private static function post_ids( $posts ): array {
		return array_map(
			static function ( $post ) {
				return is_object( $post ) ? (int) $post->ID : (int) $post;
			},
			is_array( $posts ) ? $posts : array( $posts )
		);
	}

	/**
	 * Renders the Zones screen for the current user.
	 *
	 * @param int $zone_id Zone to open, or 0 for the form that adds one.
	 * @return string HTML.
	 */
	protected static function render_zone_editor( int $zone_id = 0 ): string {
		ob_start();
		zoninator_container()->get( ScreenView::class )->render( zoninator_container()->get( ScreenDataLoader::class )->load( $zone_id ) );

		return (string) ob_get_clean();
	}

	protected static function zones(): Zones {
		return zoninator_container()->get( Zones::class );
	}

	protected static function locker(): ZoneLocker {
		return zoninator_container()->get( ZoneLocker::class );
	}

	protected static function taxonomy(): Taxonomy {
		return zoninator_container()->get( Taxonomy::class );
	}

	protected static function settings(): Settings {
		return zoninator_container()->get( Settings::class );
	}

	protected static function feed(): Feed {
		return zoninator_container()->get( Feed::class );
	}
}
