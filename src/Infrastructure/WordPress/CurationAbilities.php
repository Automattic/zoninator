<?php
/**
 * The Abilities API abilities that change the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use WP_Error;

/**
 * Registers the abilities that curate a zone: add posts to it, set its
 * posts and their order, and take posts out.
 *
 * They change a zone for the current user through GuardedZoneCurator, so
 * they pass the same checks as the Zones screen and the REST API, the
 * zone's lock included.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( CurationAbilities::class ) to unhook it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class CurationAbilities {

	/**
	 * Constructor.
	 *
	 * @param GuardedZoneCurator $curator      Changes the posts in a zone for the current user.
	 * @param Capabilities       $capabilities The capability checks.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly GuardedZoneCurator $curator,
		private readonly Capabilities $capabilities,
		private readonly Errors $errors,
	) {
	}

	/**
	 * Hooks the abilities. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register the abilities.
	 */
	public function register(): void {
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
	public function permissions_check(): bool {
		return $this->capabilities->can_curate_zones( get_current_user_id() );
	}

	/**
	 * Add posts to the end of a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function add_posts( $input ): array|WP_Error {
		return $this->change( 'add_posts', $input );
	}

	/**
	 * Set the posts in a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function set_posts( $input ): array|WP_Error {
		return $this->change( 'set_posts', $input );
	}

	/**
	 * Take posts out of a zone.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function remove_posts( $input ): array|WP_Error {
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
	private function register_ability( $name, $label, $description, $method, array $annotations ): void {
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
	private function change( $method, $input ): array|WP_Error {
		$input = (array) $input;

		try {
			$this->curator->$method( get_current_user_id(), (int) $input['zone_id'], array_map( 'intval', (array) $input['post_ids'] ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, (int) $input['zone_id'] );
		}

		return array( 'success' => true );
	}
}
