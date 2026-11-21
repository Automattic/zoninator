<?php
/**
 * Integration tests for which zone posts each user sees.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class Zoninator_Zone_Post_Status_Test extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var int[]
	 */
	private $post_ids;

	public function set_up(): void {
		parent::set_up();

		$zone          = Zoninator()->insert_zone( 'status-zone', 'Status zone' );
		$this->zone_id = (int) $zone['term_id'];

		$this->post_ids = array(
			'publish' => self::factory()->post->create(),
			'future'  => self::factory()->post->create(
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				)
			),
			'draft'   => self::factory()->post->create( array( 'post_status' => 'draft' ) ),
		);

		Zoninator()->add_zone_posts( $this->zone_id, array_values( $this->post_ids ) );

		// admin-ajax.php and admin-post.php run in the admin for every visitor.
		set_current_screen( 'dashboard' );
	}

	public function tear_down(): void {
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	public function test_logged_out_admin_request_gets_only_published_posts(): void {
		wp_set_current_user( 0 );

		$this->assertSame( array( $this->post_ids['publish'] ), $this->zone_post_ids() );
	}

	public function test_subscriber_admin_request_gets_only_published_posts(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( array( $this->post_ids['publish'] ), $this->zone_post_ids() );
	}

	public function test_zone_manager_in_the_admin_gets_every_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( array_values( $this->post_ids ), $this->zone_post_ids() );
	}

	/**
	 * @return int[]
	 */
	private function zone_post_ids() {
		return array_map( 'intval', wp_list_pluck( z_get_posts_in_zone( $this->zone_id ), 'ID' ) );
	}
}
