<?php

class Zoninator_View_Renderer
{
    /**
     * @var Zoninator_Zone_Gateway
     */
    private $_zone_gateway = null;

    public function __construct( $zone_gateway )
    {
        $this->_zone_gateway = $zone_gateway;
    }

    public function admin_page_zone_post($post, $zone)
    {
        $columns = apply_filters('zoninator_zone_post_columns', array(
            'position' => array($this, 'admin_page_zone_post_col_position'),
            'info' => array($this, 'admin_page_zone_post_col_info')
        ), $post, $zone);
        ?>
        <div id="zone-post-<?php echo $post->ID; ?>" class="zone-post" data-post-id="<?php echo $post->ID; ?>">
            <table>
                <tr>
                    <?php foreach ($columns as $column_key => $column_callback) : ?>
                        <?php if (is_callable($column_callback)) : ?>
                            <td class="zone-post-col zone-post-<?php echo $column_key; ?>">
                                <?php call_user_func($column_callback, $post, $zone); ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            </table>
            <input type="hidden" name="zone-post-id" value="<?php echo $post->ID; ?>"/>
        </div>
        <?php
    }

    function admin_page_zone_post_col_position( $post, $zone ) {
        $current_position = $this->_zone_gateway->get_post_order( $post->ID, $zone );
        ?>
        <span title="<?php esc_attr_e( 'Click and drag to change the position of this item.', 'zoninator' ); ?>">
			<?php echo esc_html( $current_position ); ?>
		</span>
        <?php
    }

    function admin_page_zone_post_col_info( $post, $zone ) {
        $action_links = array(
            sprintf( '<a href="%s" class="edit" target="_blank" title="%s">%s</a>', get_edit_post_link( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'Edit', 'zoninator' ) ),
            sprintf( '<a href="#" class="delete" title="%s">%s</a>', __( 'Remove this item from the zone', 'zoninator' ), __( 'Remove', 'zoninator' ) ),
            sprintf( '<a href="%s" class="view" target="_blank" title="%s">%s</a>', get_permalink( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'View', 'zoninator' ) ),
            // Move To
            // Copy To
        );
        ?>
        <?php echo sprintf( '%s <span class="zone-post-status">(%s)</span>', esc_html( $post->post_title ), esc_html( $post->post_status ) ); ?>

        <div class="row-actions">
            <?php echo implode( ' | ', $action_links ); ?>
        </div>
        <?php
    }

    function zone_admin_search_form() {
        ?>
        <div class="zone-search-wrapper">
            <label for="zone-post-search"><?php esc_html_e( 'Search for content', 'zoninator' );?></label>
            <input type="text" id="zone-post-search" name="search" />
            <p class="description"><?php esc_html_e( 'Enter a term or phrase in the text box above to search for and add content to this zone.', 'zoninator' ); ?></p>
        </div>
        <?php
    }

    function zone_advanced_search_filters() {
        ?>
        <div class="zone-advanced-search-filters-heading">
            <span class="zone-toggle-advanced-search" data-alt-label="<?php esc_attr_e( 'Hide', 'zoninator' ); ?>"><?php esc_html_e( 'Show Filters', 'zoninator' ); ?></span>
        </div>
        <div class="zone-advanced-search-filters-wrapper">
            <?php do_action( 'zoninator_advanced_search_fields' ); ?>
        </div>
        <?php
    }
}