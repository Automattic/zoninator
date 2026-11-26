<?php
/**
 * The field that finds content to add to a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Prints the Zones screen's "Add content" field and the filters under it.
 *
 * The category and date filters are hooked on zoninator_advanced_search_fields,
 * as a site's own filters are, so a site can remove or reorder them. Sites
 * reach this object with zoninator_container()->get( SearchForm::class ).
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class SearchForm {

	/**
	 * Hooks the default filters. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'zoninator_advanced_search_fields', array( $this, 'render_category_filter' ) );
		add_action( 'zoninator_advanced_search_fields', array( $this, 'render_date_filter' ), 20 );
	}

	/**
	 * Prints the field and its filters.
	 */
	public function render(): void {
		?>
	<div class="zone-search-wrapper">
		<label for="zone-post-search"><?php esc_html_e( 'Add content', 'zoninator' ); ?></label>
		<input type="text" id="zone-post-search" name="search" class="large-text" aria-describedby="zone-post-search-description" />
		<p class="description" id="zone-post-search-description"><?php esc_html_e( 'Type a title or post ID to search, or choose from the latest content.', 'zoninator' ); ?></p>
		<?php $this->render_filters(); ?>
	</div>
		<?php
	}

	/**
	 * Prints the button that shows the filters, and the filters themselves.
	 */
	public function render_filters(): void {
		?>
	<div class="zone-advanced-search-filters-heading">
		<button type="button" class="button-link zone-toggle-advanced-search" aria-expanded="false" aria-controls="zone-advanced-search-filters" data-alt-label="<?php esc_attr_e( 'Hide filters', 'zoninator' ); ?>"><?php esc_html_e( 'Show filters', 'zoninator' ); ?></button>
	</div>
	<div class="zone-advanced-search-filters-wrapper" id="zone-advanced-search-filters">
		<?php
		/**
		 * Fires inside the Zones screen's search filters, to print more of them.
		 *
		 * Zoninator prints its category filter here, and its date filter at priority 20.
		 *
		 * @since 0.6
		 */
		do_action( 'zoninator_advanced_search_fields' );
		?>
	</div>
		<?php
	}

	/**
	 * Prints the category filter.
	 */
	public function render_category_filter(): void {
		?>
	<label for="zone_advanced_filter_taxonomy" class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'zoninator' ); ?></label>
		<?php
		wp_dropdown_categories(
			/**
			 * Filters the wp_dropdown_categories() arguments for the search's category filter.
			 *
			 * @since 0.5
			 *
			 * @param array $args wp_dropdown_categories() arguments.
			 */
			apply_filters(
				'zoninator_advanced_filter_category',
				array(
					'show_option_all' => __( 'All categories', 'zoninator' ),
					'selected'        => 0,
					'name'            => 'zone_advanced_filter_taxonomy',
					'id'              => 'zone_advanced_filter_taxonomy',
					'hide_if_empty'   => true,
				)
			)
		);
	}

	/**
	 * Prints the date filter.
	 */
	public function render_date_filter(): void {
		/**
		 * Filters which date the search's date filter starts on.
		 *
		 * @since 0.7
		 *
		 * @param string $default One of the options' values: a Y-m-d date, or '' for all dates.
		 */
		$current_date = apply_filters( 'zoninator_advanced_filter_date_default', '' );
		/**
		 * Filters the options in the search's date filter.
		 *
		 * A string is passed through strtotime(). An array has a value, which must
		 * be a Y-m-d date or the search ignores it, and a label.
		 *
		 * @since 0.5
		 *
		 * @param array $dates The options. Default array( 'all', 'today', 'yesterday' ).
		 */
		$date_filters = apply_filters( 'zoninator_advanced_filter_date', array( 'all', 'today', 'yesterday' ) );
		$date_labels  = array(
			'all'       => __( 'All dates', 'zoninator' ),
			'today'     => __( 'Today', 'zoninator' ),
			'yesterday' => __( 'Yesterday', 'zoninator' ),
		);
		?>
	<label for="zone_advanced_filter_date" class="screen-reader-text"><?php esc_html_e( 'Filter by date', 'zoninator' ); ?></label>
	<select name="zone_advanced_filter_date" id="zone_advanced_filter_date">
		<?php
		// Convert string dates into actual dates
		foreach ( $date_filters as $date ) :
			if ( is_array( $date ) ) {
				$output = array_key_exists( 'value', $date ) ? $date['value'] : 0;
				$label  = array_key_exists( 'label', $date ) ? $date['label'] : $output;
			} else {
				$timestamp = strtotime( (string) $date );
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$output = ( $timestamp ) ? date( 'Y-m-d', $timestamp ) : 0;
				$label  = $date_labels[ $date ] ?? $date;
			}

			printf( '<option value="%s" %s>%s</option>', esc_attr( $output ), selected( $output, $current_date, false ), esc_html( $label ) );
			endforeach;
		?>
	</select>
		<?php
	}
}
