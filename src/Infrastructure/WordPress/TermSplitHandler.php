<?php
/**
 * Keeps a zone's posts when WordPress splits a shared term.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\ZoneCurator;

/**
 * Moves a zone's posts to its new ID when WordPress splits a term the zone
 * shared with another taxonomy. Shared terms date from before WordPress 4.2.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( TermSplitHandler::class ) to unhook it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class TermSplitHandler {

	/**
	 * Changes the posts in a zone.
	 *
	 * @var ZoneCurator
	 */
	private $curator;

	/**
	 * Constructor.
	 *
	 * @param ZoneCurator $curator Changes the posts in a zone.
	 */
	public function __construct( ZoneCurator $curator ) {
		$this->curator = $curator;
	}

	/**
	 * Hooks the handler. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks() {
		add_action( 'split_shared_term', array( $this, 'move_zone_posts' ), 10, 4 );
	}

	/**
	 * Moves the zone's posts from its old term ID to its new one.
	 *
	 * @param int    $old_term_id      ID of the term that was shared.
	 * @param int    $new_term_id      ID of the term the split made.
	 * @param int    $term_taxonomy_id ID of the term's place in its taxonomy.
	 * @param string $taxonomy         The taxonomy the new term belongs to.
	 */
	public function move_zone_posts( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
		if ( Taxonomy::NAME !== $taxonomy ) {
			return;
		}

		do_action( 'zoninator_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );

		$this->curator->move_posts( (int) $old_term_id, (int) $new_term_id );

		do_action( 'zoninator_did_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );
	}
}
