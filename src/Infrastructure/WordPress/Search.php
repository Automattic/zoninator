<?php
/**
 * The search for posts to add to a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use WP_Post;

/**
 * Finds posts to offer for a zone: a text search, or the latest content when
 * nothing has been typed. The REST search route and the zoninator/search-posts
 * ability both use it.
 *
 * @since 1.0.0
 * @internal Use the REST API or the Abilities API instead.
 */
final class Search {

	/**
	 * The zone taxonomy, for the post types that support zones.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Zoninator's settings, for the number of results.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The capability checks, for the posts the user may add.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param Taxonomy     $taxonomy     The zone taxonomy.
	 * @param Settings     $settings     Zoninator's settings.
	 * @param Capabilities $capabilities The capability checks.
	 */
	public function __construct( Taxonomy $taxonomy, Settings $settings, Capabilities $capabilities ) {
		$this->taxonomy     = $taxonomy;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
	}

	/**
	 * Finds posts to offer for a zone.
	 *
	 * @param string $term    Search term. Empty for the latest content.
	 * @param int    $cat     Category ID to filter by, or 0.
	 * @param string $date    Date to filter by, as Y-m-d, or ''.
	 * @param int[]  $exclude Post IDs to leave out, usually those already in the zone.
	 * @param int    $limit   Number of search results, up to 100. 0 for the default. The latest content ignores it.
	 * @param int    $zone_id Zone being edited, or 0. Passed to the query args filters.
	 * @return array[] Results, each passed through the zoninator_search_results_post filter.
	 */
	public function find_posts( $term, $cat, $date, $exclude, $limit = 0, $zone_id = 0 ) {
		$post_types = $this->taxonomy->get_post_types();

		if ( '' === $term ) {
			// With nothing typed yet, offer the latest content.
			$args = apply_filters(
				'zoninator_recent_posts_args',
				array(
					'posts_per_page'      => $this->settings->get_posts_per_page(),
					'order'               => 'DESC',
					'orderby'             => 'post_date',
					'post_type'           => $post_types,
					'ignore_sticky_posts' => true,
					'post_status'         => array( 'publish', 'future' ),
					'suppress_filters'    => false,
				),
				$zone_id
			);
		} else {
			$limit = $limit > 0 ? min( $limit, 100 ) : $this->settings->get_posts_per_page();

			$args = apply_filters(
				'zoninator_search_args',
				array(
					's'                => $term,
					'posts_per_page'   => $limit,
					'post_type'        => $post_types,
					'post_status'      => array( 'publish', 'future' ),
					'order'            => 'DESC',
					'orderby'          => 'post_date',
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
					'suppress_filters' => true,
					'no_found_rows'    => true,
				),
				$zone_id
			);
		}

		if ( $this->is_valid_category_filter( $cat ) ) {
			$args['cat'] = $cat;
		}

		if ( $this->is_valid_date_filter( $date ) ) {
			$date_parts       = explode( '-', $date );
			$args['year']     = $date_parts[0];
			$args['monthnum'] = $date_parts[1];
			$args['day']      = $date_parts[2];
		}

		// A post ID finds that post directly, which skips the slow LIKE search. It
		// uses the filtered search args, so it only finds a post the text search
		// could offer. If it finds nothing, search the text as usual, so a number
		// in a title, such as "1984", still matches.
		if ( ctype_digit( $term ) ) {
			$by_id = $args;
			unset( $by_id['s'] );
			// post__in, not p: p makes a singular query, which ignores taxonomy args.
			$by_id['post__in']       = array( (int) $term );
			$by_id['posts_per_page'] = 1;

			$posts = $this->get_posts_excluding( $by_id, $exclude );
			if ( $posts ) {
				return array_map( array( $this, 'format_search_result' ), $posts );
			}
		}

		return array_map( array( $this, 'format_search_result' ), $this->get_posts_excluding( $args, $exclude ) );
	}

	/**
	 * Formats a post as a search result.
	 *
	 * @param WP_Post $post Post.
	 * @return array The result, passed through the zoninator_search_results_post filter.
	 */
	private function format_search_result( $post ) {
		return apply_filters(
			'zoninator_search_results_post',
			array(
				'title'             => empty( $post->post_title ) ? __( '(no title)', 'zoninator' ) : $post->post_title,
				'post_id'           => $post->ID,
				'date'              => get_the_time( get_option( 'date_format' ), $post ),
				'post_type'         => $post->post_type,
				'post_type_label'   => $this->get_post_type_label( $post ),
				'post_status'       => $post->post_status,
				'post_status_label' => $this->get_post_status_label( $post ),
			),
			$post
		);
	}

	/**
	 * Get posts but leave out the given IDs, without a NOT IN clause.
	 *
	 * NOT IN gets slow on big posts tables, so we fetch a few more rows
	 * and drop the excluded ones in PHP.
	 *
	 * The search sends the IDs to exclude in the request, so at most 100 extra
	 * rows are fetched, whatever the request holds. Zones are hand-curated and
	 * small, so this only shortens the list when over 100 excluded posts are
	 * among the newest matches.
	 *
	 * @param array $args    WP_Query args.
	 * @param array $exclude Post IDs to leave out.
	 * @return WP_Post[]
	 */
	private function get_posts_excluding( $args, $exclude ) {
		$exclude = array_unique( array_map( 'absint', (array) $exclude ) );
		$limit   = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;

		if ( $limit > 0 ) {
			$args['posts_per_page'] = $limit + min( count( $exclude ), 100 );
		}

		// The query only knows post types and statuses, so leave out posts the user may not read,
		// such as ones a membership plugin restricts. This can return fewer than the limit.
		$posts = array_values(
			array_filter(
				get_posts( $args ),
				function ( $post ) use ( $exclude ) {
					return ! in_array( $post->ID, $exclude, true ) && $this->capabilities->current_user_can_add_post_to_zone( $post );
				}
			)
		);

		return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string The label for the post's status, such as "Published", or the status itself if it has none.
	 */
	private function get_post_status_label( $post ) {
		$status = get_post_status_object( $post->post_status );

		return $status ? $status->label : $post->post_status;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string The singular label for the post's type, such as "Post", or the type itself if it has none.
	 */
	private function get_post_type_label( $post ) {
		$type = get_post_type_object( $post->post_type );

		return $type ? $type->labels->singular_name : $post->post_type;
	}

	/**
	 * @param string $date Date filter from the search form.
	 * @return bool Whether it's a date, as Y-m-d.
	 */
	private function is_valid_date_filter( $date ) {
		return (bool) preg_match( '/(\d{4})-(\d{2})-(\d{2})/', $date );
	}

	/**
	 * @param int $cat Category filter from the search form.
	 * @return bool Whether it's a category.
	 */
	private function is_valid_category_filter( $cat ) {
		return $cat && get_term_by( 'id', $cat, 'category' );
	}
}
