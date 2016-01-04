<?php


class Zoninator_Permissions
{
    public function check( $action = '', $zone_id = null ) {
        // TODO: should check if zone locked
        switch( $action ) {
            case 'insert':
                $verify_function = 'current_user_can_add_zones';
                break;
            case 'update':
            case 'delete':
                $verify_function = 'current_user_can_edit_zones';
                break;
            default:
                $verify_function = 'current_user_can_manage_zones';
                break;
        }

        if( ! call_user_func( array( $this, $verify_function ), $zone_id ) ) {
            return false;
        }
        return true;
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
