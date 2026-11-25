<?php
/**
 * The Abilities API abilities that change the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use WP_Error;

/**
 * Registers the abilities that curate a zone: add posts to it, set its
 * posts and their order, and take posts out.
 *
 * They change a zone for the current user through GuardedZoneCurator, so
 * they pass the same checks as the Zones screen and the REST API, the
 * zone's lock included.
 *
 * The bootstrapper hooks it, and the container builds it the first time the hook fires.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class CurationAbilities {

	/**
	 * Changes the posts in a zone for the current user.
	 *
	 * @var GuardedZoneCurator
	 */
	private $curator;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param GuardedZoneCurator $curator      Changes the posts in a zone for the current user.
	 * @param Capabilities       $capabilities The capability checks.
	 */
	public function __construct( GuardedZoneCurator $curator, Capabilities $capabilities ) {
		$this->curator      = $curator;
		$this->capabilities = $capabilities;
	}

	/**
	 * Register the abilities.
	 */
	public function register() {
		$this->register_ability(
			'zoninator/add-zone-posts',
			__( 'Add posts to a zone', 'zoninator' ),
			__( 'Adds posts to the end of a zone, in the order given. A post already in the zone keeps its place.', 'zoninator' ),
			'add_posts',
			array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			)
		);

		$this->register_ability(
			'zoninator/set-zone-posts',
			__( 'Set the posts in a zone', 'zoninator' ),
			__( 'Makes a zone hold these posts and no others, in the order given. Use it to reorder a zone: send every post that should stay. An empty list empties the zone.', 'zoninator' ),
			'set_posts',
			array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			)
		);

		$this->register_ability(
			'zoninator/remove-zone-posts',
			__( 'Remove posts from a zone', 'zoninator' ),
			__( 'Takes posts out of a zone. The posts themselves are not deleted.', 'zoninator' ),
			'remove_posts',
			array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			)
		);
	}

	/**
	 * Whether the current user may use the abilities: the same capability the
	 * Zones screen needs.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return $this->capabilities->current_user_can_manage_zones();
	}

	/**
	 * Add posts to the end of a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function add_posts( $input ) {
		return $this->change( 'add_posts', $input );
	}

	/**
	 * Set the posts in a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function set_posts( $input ) {
		return $this->change( 'set_posts', $input );
	}

	/**
	 * Take posts out of a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function remove_posts( $input ) {
		return $this->change( 'remove_posts', $input );
	}

	/**
	 * Registers one ability. They all take a zone ID and a list of post IDs.
	 *
	 * @param string $name        Ability name.
	 * @param string $label       Its label.
	 * @param string $description What it does.
	 * @param string $method      The method on this class that does it.
	 * @param array  $annotations Whether it's read-only, destructive and idempotent.
	 */
	private function register_ability( $name, $label, $description, $method, array $annotations ) {
		wp_register_ability(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => Abilities::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'zone_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The zone ID.', 'zoninator' ),
							'minimum'     => 1,
						),
						'post_ids' => array(
							'type'        => 'array',
							'description' => __( 'Post IDs, in order.', 'zoninator' ),
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
					),
					'required'             => array( 'zone_id', 'post_ids' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, $method ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'meta'                => array(
					'annotations'  => $annotations,
					'show_in_rest' => true,
					// Unlike the read-only abilities, these aren't offered on the MCP adapter's
					// default server. A site that wants agents to change zones opts in.
					'mcp'          => array( 'public' => false ),
				),
			)
		);
	}

	/**
	 * Makes a change for the current user, and turns a refusal into an error.
	 *
	 * @param string $method GuardedZoneCurator method.
	 * @param array  $input  Ability input.
	 * @return array|WP_Error
	 */
	private function change( $method, $input ) {
		$input = (array) $input;

		try {
			$this->curator->$method( get_current_user_id(), (int) $input['zone_id'], array_map( 'intval', (array) $input['post_ids'] ) );
		} catch ( ZoneNotFoundException $exception ) {
			return new WP_Error( 'zone-not-found', __( 'Zone not found.', 'zoninator' ), array( 'status' => 404 ) );
		} catch ( PostNotAllowedException $exception ) {
			return new WP_Error( 'invalid-post-id', __( 'Invalid post ID.', 'zoninator' ), array( 'status' => 400 ) );
		} catch ( ZoneLockedException $exception ) {
			$holder = get_userdata( $exception->get_holder_id() );

			return new WP_Error(
				'zone-locked',
				sprintf(
					/* translators: %s: the locking user's display name, or "another user". */
					__( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
					$holder ? $holder->display_name : __( 'another user', 'zoninator' )
				),
				array( 'status' => 400 )
			);
		} catch ( AccessDeniedException $exception ) {
			return new WP_Error( 'permission-denied', __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => 403 ) );
		}

		return array( 'success' => true );
	}
}
