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
        $columns = $this->_zone_gateway->get_admin_zone_post( $post, $zone);

        ?>
        <div id="zone-post-<?php echo $columns['post_id']; ?>" class="zone-post" data-post-id="<?php echo $columns['post_id']; ?>">
            <table>
                <tr>
                    <?php if ( array_key_exists( 'position', $columns ) ) : ?>
                        <td class="zone-post-col zone-post-<?php echo $columns['position']['key']; ?>">
                            <span title="<?php echo $columns['position']['current_position']; ?>">
			                    <?php echo $columns['position']['current_position']; ?>
		                    </span>
                        </td>
                    <?php endif; ?>
                    <?php if ( array_key_exists( 'info', $columns ) ) : ?>
                        <td class="zone-post-col zone-post-<?php echo $columns['info']['key']; ?>">
                            <?php
                            $info = $columns['info'];
                            $action_links = array_map(function ($data) {
                                return sprintf( '<a href="%s" class="%s" title="%s">%s</a>', $data['anchor'], $data['action'], $data['title'], $data['text']);
                            }, $info['action_link_data']);

                            ?>

                            <?php echo sprintf( '%s <span class="zone-post-status">(%s)</span>', $info['post']['post_title'], $info['post']['post_status'] ); ?>

                            <div class="row-actions">
                                <?php echo implode( ' | ', $action_links ); ?>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            </table>
            <input type="hidden" name="zone-post-id" value="<?php echo $columns['post_id']; ?>"/>
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