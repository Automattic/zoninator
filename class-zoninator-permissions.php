<?php


class Zoninator_Permissions
{
    public function check( $action = '', $zone_id = null ) {
        // TODO: should check if zone locked
        if ( 'insert' == $action ) {
            return $this->current_user_can_add_zones();
        }

        if ( 'update' == $action || 'delete' == $action ) {
            return $this->current_user_can_edit_zones( $zone_id );
        }

        return $this->current_user_can_manage_zones();
    }

    public function current_user_can_add_zones() {
        return current_user_can( $this->_get_add_zones_cap() );
    }

    public function current_user_can_edit_zones( $zone_id ) {
        $has_cap = current_user_can( $this->_get_edit_zones_cap() );
        return apply_filters( 'zoninator_current_user_can_edit_zone', $has_cap, $zone_id );
    }

    public function current_user_can_manage_zones() {
        return current_user_can( $this->get_manage_zones_cap() );
    }

    public function get_manage_zones_cap() {
        return apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' );
    }

    private function _get_add_zones_cap() {
        return apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' );
    }

    private function _get_edit_zones_cap() {
        return apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' );
    }
}
