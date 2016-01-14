<?php

class Zoninator_Zone_Gateway
{
    var $post_types = null;

    var $zone_detail_defaults = array(
        'description' => ''
        // Add additional properties here!
    );

    var $zone_term_prefix = 'zone-';

    var $zone_meta_prefix = '_zoninator_order_';

    var $zone_lock_period = 30; // number of seconds a lock is valid for

    var $zone_max_lock_period = 600; // max number of seconds for all locks in a session

    var $posts_per_page = 10;

    function __construct() {
        $this->zone_lock_period = apply_filters( 'zoninator_zone_lock_period', $this->zone_lock_period );
        $this->zone_max_lock_period = apply_filters( 'zoninator_zone_max_lock_period', $this->zone_max_lock_period );
        $this->posts_per_page = apply_filters( 'zoninator_posts_per_page', $this->posts_per_page );

        add_action( 'split_shared_term', array( $this, 'split_shared_term' ), 10, 4 );
        $this->default_post_types = array( 'post' );
    }

    function init() {
        // Default post type support
        foreach( $this->default_post_types as $post_type )
            add_post_type_support( $post_type, Zoninator_Constants::ZONE_TAXONOMY );

        // Register taxonomy
        if( ! taxonomy_exists( Zoninator_Constants::ZONE_TAXONOMY ) ) {
            register_taxonomy( Zoninator_Constants::ZONE_TAXONOMY, $this->get_supported_post_types(), array(
                'label' => __( 'Zones', 'zoninator' ),
                'hierarchical' => false,
                'query_var' => false,
                'rewrite' => false,
                'public' => false,

            ) );
        }
    }

    function insert_zone( $slug, $name = '', $details = array() ) {

        // slug cannot be empty
        if( empty( $slug ) ) {
            return new WP_Error( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
        }

        $slug = $this->get_formatted_zone_slug( $slug );

        if ( empty( $name ) ) {
            $name = $slug;
        }

        $details = wp_parse_args( $details, $this->zone_detail_defaults );
        $details = maybe_serialize( stripslashes_deep( $details ) );

        $args = array(
            'slug' => $slug,
            'description' => $details,
        );

        // Filterize to allow other inputs
        $args = apply_filters( 'zoninator_insert_zone', $args );

        return wp_insert_term( $name, Zoninator_Constants::ZONE_TAXONOMY, $args );
    }

    function update_zone( $zone, $data = array() ) {
        $zone_id = $this->get_zone_id( $zone );

        if( $this->zone_exists( $zone_id ) ) {
            $zone = $this->get_zone( $zone );

            $name = $this->_get_value_or_default( 'name', $data, $zone->name );
            $slug = $this->_get_value_or_default( 'slug', $data, $zone->slug, array( $this, 'get_formatted_zone_slug' ) );
            $details = $this->_get_value_or_default( 'details', $data, array() );

            // TODO: Back-fill current zone details
            //$details = wp_parse_args( $details, $this->zone_detail_defaults );
            $details = wp_parse_args( $details, $this->zone_detail_defaults );
            $details = maybe_serialize( stripslashes_deep( $details ) );

            $args = array(
                'name' => $name,
                'slug' => $slug,
                'description' => $details
            );

            // Filterize to allow other inputs
            $args = apply_filters( 'zoninator_update_zone', $args, $zone_id, $zone );

            return wp_update_term( $zone_id, Zoninator_Constants::ZONE_TAXONOMY, $args );
        }
        return new WP_Error( 'invalid-zone', __( 'Sorry, that zone doesn\'t exist.', 'zoninator' ) );
    }

    function get_zone_feed( $zone_slug_or_id ) {
        $zone_id = $this->get_zone( $zone_slug_or_id );

        if ( empty( $zone_id ) ) {
            return new WP_Error( 'invalid-zone-supplied', __( 'Invalid zone supplied', 'zoninator' ) );
        }

        $results = $this->get_zone_posts( $zone_id, apply_filters( 'zoninator_json_feed_fields', array(), $zone_slug_or_id ) );

        if ( empty( $results ) ) {
            return new WP_Error( 'no-zone-posts-found',  __( 'No zone posts found', 'zoninator' ) );
        }

        $filtered_results = $this->filter_zone_feed_fields( $results );

        return apply_filters( 'zoninator_json_feed_results', $filtered_results, $zone_slug_or_id );
    }

    private function filter_zone_feed_fields( $results ) {
        $white_listed_fields = array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' );
        $filtered_results = array();

        $i = 0;
        foreach ( $results as $result ) {
            foreach( $white_listed_fields as $field ) {
                if ( ! array_key_exists( $i, $filtered_results ) ) {
                    $filtered_results[$i] = new stdClass();
                }
                $filtered_results[$i]->$field = $result->$field;
            }
            $i++;
        }

        return $filtered_results;
    }

    function get_zones( $args = array() ) {

        $args = wp_parse_args( $args, array(
            'orderby' => 'id',
            'order' => 'ASC',
            'hide_empty' => 0,
        ) );

        $zones = get_terms( Zoninator_Constants::ZONE_TAXONOMY, $args );

        // Add extra fields in description as properties
        foreach( $zones as $zone ) {
            $zone = $this->_fill_zone_details( $zone );
        }

        return $zones;
    }

    function get_post_order( $post, $zone ) {
        $post_id = $this->get_post_id( $post );
        $meta_key = $this->get_zone_meta_key( $zone );

        return get_metadata( 'post', $post_id, $meta_key, true );
    }

    function get_zone( $zone ) {
        if( is_int( $zone ) ) {
            $field = 'id';
        } elseif( is_string( $zone ) ) {
            $field = 'slug';
            $zone = $this->get_zone_slug( $zone );
        } elseif( is_object( $zone ) ) {
            return $zone;
        } else {
            return false;
        }

        $zone = get_term_by( $field, $zone, Zoninator_Constants::ZONE_TAXONOMY );

        if( ! $zone )
            return false;

        return $this->_fill_zone_details( $zone );
    }

    function get_zone_posts( $zone, $args = array() ) {
        // Check cache first
        if( $posts = $this->get_zone_posts_from_cache( $zone, $args ) )
            return $posts;

        $query = $this->get_zone_query( $zone, $args );
        $posts = $query->posts;

        // Add posts to cache
        $this->add_zone_posts_to_cache( $posts, $zone, $args );

        return $posts;
    }

    function add_zone_posts($zone, $posts, $append = false ) {
        $zone = $this->get_zone( $zone );
        $meta_key = $this->get_zone_meta_key( $zone );

        $this->_empty_zone_posts_cache( $meta_key );

        if( $append ) {
            // Order should be the highest post order
            $last_post = $this->get_last_post_in_zone( $zone );
            if( $last_post )
                $order = $this->get_post_order( $last_post, $zone );
            else
                $order = 0;
        } else {
            $order = 0;
            $this->remove_zone_posts( $zone );
        }

        foreach( (array) $posts as $post ) {
            $post_id = $this->get_post_id( $post );
            if( $post_id ) {
                $order++;
                update_metadata( 'post', $post_id, $meta_key, $order, true );
            }
            // TODO: remove_object_terms -- but need remove object terms function :(
        }

        clean_term_cache( $this->get_zone_id( $zone ), Zoninator_Constants::ZONE_TAXONOMY ); // flush cache for our zone term and related APC caches

        do_action( 'zoninator_add_zone_posts', $posts, $zone );
        return null;
    }

    function lock_zone( $zone, $user_id = 0 ) {
        $zone_id = $this->get_zone_id( $zone );

        if( ! $zone_id )
            return false;

        if( ! $user_id ) {
            $user = wp_get_current_user();
            $user_id = $user->ID;
        }

        $lock_key = $this->get_zone_meta_key( $zone );
        $expiry = $this->zone_lock_period + 1; // Add a one to avoid most race condition issues between lock expiry and ajax call
        set_transient( $lock_key, $user->ID, $expiry );

        // Possible alternative: set zone lock as property with time and user
    }

    // Not really needed with transients...
    function unlock_zone( $zone ) {
        $zone_id = $this->get_zone_id( $zone );

        if( ! $zone_id )
            return false;

        $lock_key = $this->get_zone_meta_key( $zone );

        delete_transient( $lock_key );
    }

    function is_zone_locked( $zone ) {
        $zone_id = $this->get_zone_id( $zone );
        if( ! $zone_id )
            return false;

        $user = wp_get_current_user();
        $lock_key = $this->get_zone_meta_key( $zone );

        $lock = get_transient( $lock_key );

        // If lock doesn't exist, or check if current user same as lock user
        if( ! $lock || absint( $lock ) === absint( $user->ID ) )
            return false;
        else
            // return user_id of locking user
            return absint( $lock );
    }

    function zone_exists( $zone ) {
        $zone_id = $this->get_zone_id( $zone );

        if( term_exists( $zone_id, Zoninator_Constants::ZONE_TAXONOMY ) )
            return true;

        return false;
    }

    function delete_zone( $zone ) {
        $zone_id = $this->get_zone_id( $zone );
        $meta_key = $this->get_zone_meta_key( $zone );

        $this->_empty_zone_posts_cache( $meta_key );

        if( $this->zone_exists( $zone_id ) ) {
            // Delete all post associations for the zone
            $this->remove_zone_posts( $zone_id );

            // Delete the term
            $delete = wp_delete_term( $zone_id, Zoninator_Constants::ZONE_TAXONOMY );

            if( ! $delete ) {
                return new WP_Error( 'delete-zone', __( 'Sorry, we couldn\'t delete the zone.', 'zoninator' ) );
            } else {
                do_action( 'zoninator_delete_zone', $zone_id );
                return $delete;
            }
        }
        return new WP_Error( 'invalid-zone', __( 'Sorry, that zone doesn\'t exist.', 'zoninator' ) );
    }

    function remove_zone_posts( $zone, $posts = null ) {
        $zone = $this->get_zone( $zone );
        $meta_key = $this->get_zone_meta_key( $zone );

        $this->_empty_zone_posts_cache( $meta_key );

        // if null, delete all
        if( ! $posts )
            $posts = $this->get_zone_posts( $zone );

        foreach( (array) $posts as $post ) {
            $post_id = $this->get_post_id( $post );
            if( $post_id )
                delete_metadata( 'post', $post_id, $meta_key );
        }

        clean_term_cache( $this->get_zone_id( $zone ), Zoninator_Constants::ZONE_TAXONOMY ); // flush cache for our zone term and related APC caches

        do_action( 'zoninator_remove_zone_posts', $posts, $zone );
        return null;
    }

    function get_post_id( $post ) {
        if( is_int( $post ) )
            return $post;
        elseif( is_array( $post ) )
            return absint( $post['ID'] );
        elseif( is_object( $post ) )
            return $post->ID;

        return false;
    }

    function get_zone_meta_key( $zone ) {
        $zone_id = $this->get_zone_id( $zone );
        return $this->zone_meta_prefix . $zone_id;
    }

    function get_zone_id( $zone ) {
        if( is_int( $zone ) )
            return $zone;

        $zone = $this->get_zone( $zone );
        if( is_object( $zone ) )
            $zone = $zone->term_id;

        return (int)$zone;
    }

    function get_first_post_in_zone( $zone ) {
        return $this->get_single_post_in_zone( $zone );
    }

    function get_prev_post_in_zone( $zone, $post_id ) {
        // TODO: test this works
        $order = $this->get_post_order_in_zone( $zone, $post_id );

        return $this->get_single_post_in_zone( $zone, array(
            'meta_value' => $order,
            'meta_compare' => '<='
        ) );
    }

    function get_next_post_in_zone( $zone, $post_id ) {
        // TODO: test this works
        $order = $this->get_post_order_in_zone( $zone, $post_id );

        return $this->get_single_post_in_zone( $zone, array(
            'meta_value' => $order,
            'meta_compare' => '>='
        ) );

    }

    // Handle 4.2 term-splitting
    function split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
        if ( Zoninator_Constants::ZONE_TAXONOMY === $taxonomy ) {
            do_action( 'zoninator_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );

            // Quick, easy switcheroo; add posts to new zone id and remove from the old one.
            $posts = $this->get_zone_posts( $old_term_id );
            if ( ! empty( $posts ) ) {
                $this->add_zone_posts( $new_term_id, $posts );
                $this->remove_zone_posts( $old_term_id );
            }

            do_action( 'zoninator_did_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );
        }
    }

    function get_post_order_in_zone( $zone, $post_id ) {
        // TODO: implement
        return 0;
    }

    function get_zone_posts_from_cache( $zone, $args = array() ) {
        return false; // TODO: implement

//        $meta_key = $this->get_zone_meta_key( $zone );
//        $cache_key = $this->get_zone_cache_key( $zone, $args );
//        if( $posts = wp_cache_get( $cache_key, $meta_key ) )
//            return $posts;
//        return false;
    }

    function add_zone_posts_to_cache( $posts, $zone, $args = array() ) {
        return; // TODO: implement

//        $meta_key = $this->get_zone_meta_key( $zone );
//        $cache_key = $this->get_zone_cache_key( $zone, $args );
//        wp_cache_set( $cache_key, $posts, $meta_key );
    }

    public function get_admin_zone_post($post, $zone) {
        return apply_filters('zoninator_zone_post_columns', array(
            'post_id' => $post->ID,
            'position' => array(
                'current_position' => intval( $this->get_post_order( $post->ID, $zone ) ),
                'change_position_message' => esc_attr__( 'Click and drag to change the position of this item.', Zoninator_Constants::TEXT_DOMAIN ),
                'key' => 'position'
            ),
            'info' => array(
                'key' => 'info',
                'post' => array(
                    'post_title'  => esc_html( $post->post_title ),
                    'post_status' => esc_html( $post->post_status )
                ),
                'action_link_data' => array(
                    array(
                        'action' => 'edit',
                        'anchor'     => get_edit_post_link( $post->ID ),
                        'title' => __( 'Opens in new window', Zoninator_Constants::TEXT_DOMAIN ),
                        'text'  => __( 'Edit', Zoninator_Constants::TEXT_DOMAIN )
                    ),
                    array(
                        'action' => 'delete',
                        'anchor'     => '#',
                        'title' => __( 'Opens in new window', Zoninator_Constants::TEXT_DOMAIN ),
                        'text'  => __( 'Remove', Zoninator_Constants::TEXT_DOMAIN )
                    ),
                    array(
                        'action' => 'view',
                        'anchor'     => get_permalink( $post->ID ),
                        'title' => __( 'Opens in new window', Zoninator_Constants::TEXT_DOMAIN ),
                        'text'  => __( 'View', 'zoninator' )
                    )
                )
            )
        ), $post, $zone);
    }

    public function get_admin_zone_posts( $zone_or_id ) {
        $zone = $this->get_zone( $zone_or_id );
        $posts = $this->get_zone_posts( $zone );
        $admin_zone_posts = array();

        foreach ( $posts as $post ) {
            $admin_zone_posts[] = $this->get_admin_zone_post( $post, $zone );
        }

        return $admin_zone_posts;
    }

    function get_supported_post_types() {
        if( isset( $this->post_types ) )
            return $this->post_types;

        $this->post_types = array();

        foreach( get_post_types() as $post_type ) {
            if( post_type_supports( $post_type, Zoninator_Constants::ZONE_TAXONOMY ) )
                array_push( $this->post_types, $post_type );
        }

        return $this->post_types;
    }

    function get_zone_query( $zone, $args = array() ) {
        $meta_key = $this->get_zone_meta_key( $zone );

        $defaults = array(
            'order' => 'ASC',
            'posts_per_page' => -1,
            'post_type' => $this->get_supported_post_types(),
            'ignore_sticky_posts' => '1', // don't want sticky posts messing up our order
        );

        // Default to published posts on the front-end
        if ( ! is_admin() )
            $defaults['post_status'] = array( 'publish' );

        if ( is_admin() ) // skip APC in the admin
            $defaults['suppress_filters'] = true;

        $args = wp_parse_args( $args, $defaults );

        // Un-overridable args
        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = $meta_key;

        /* // 3.1-friendly, though missing sort support which is why we're using the old way
        if( function_exists( 'get_post_format' ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => $meta_key,
                    'type' => 'NUMERIC'
                )
            );
        } else {
            $args['meta_key'] = $meta_key;
        }
        */
        return new WP_Query( $args );
    }

    function get_zone_slug( $zone ) {
        if( is_int( $zone ) )
            $zone = $this->get_zone( $zone );

        if( is_object( $zone ) )
            $zone = $zone->slug;

        return $this->get_formatted_zone_slug( $zone );
    }

    function get_formatted_zone_slug( $slug ) {
        return $slug; // legacy function -- slugs can no longer be changed
    }

    function get_last_post_in_zone( $zone ) {
        return $this->get_single_post_in_zone( $zone, array(
            'order' => 'DESC',
        ) );
    }

    // TODO: Caching needs to be testing properly before being implemented!
    function get_zone_cache_key( $zone, $args = array() ) {
        return '';
//
//        $meta_key = $this->get_zone_meta_key( $zone );
//        $hash = md5( serialize( $args ) );
//        return $meta_key . $hash;
    }

    function get_single_post_in_zone( $zone, $args = array() ) {

        $args = wp_parse_args( $args, array(
            'posts_per_page' => 1,
            'showposts' => 1,
        ) );

        $post = $this->get_zone_posts( $zone, $args );

        if( is_array( $post ) && ! empty( $post ) )
            return array_pop( $post );

        return false;
    }

    private function _fill_zone_details( $zone ) {
        if( ! empty( $zone->zoninator_parsed ) && $zone->zoninator_parsed )
            return $zone;

        $details = array();

        if( ! empty( $zone->description ) )
            $details = maybe_unserialize( $zone->description );

        $details = wp_parse_args( $details, $this->zone_detail_defaults );

        foreach( $details as $detail_key => $detail_value ) {
            $zone->$detail_key = $detail_value;
        }

        $zone->zoninator_parsed = true;

        return $zone;
    }

    private function _empty_zone_posts_cache( $meta_key ) {
        return; // TODO: implement
    }

    function get_zones_for_post( $post_id ) {
        // TODO: build this out

        // get_object_terms
        // get_terms

        // OR

        // get all meta_keys that match the prefix
        // strip the prefix

        // OR

        // get all zones and see if there's a matching meta entry
        // strip the prefix from keys

        // array_map( 'absint', $zone_ids )
        // $zones = array();
        // foreach( $zone_ids as $zone_id ) {
        // $zones[] = get_zone( $zone_id );
        //}
        //return $zones;
    }

    function get_unformatted_zone_slug( $slug ) {
        return $slug; // legacy function -- slugs can no longer be changed
    }

    private function _get_value_or_default( $var, $object, $default = '', $sanitize_callback = '' ) {
        if( is_object( $object ) )
            $value = ! empty( $object->$var ) ? $object->$var : $default;
        elseif( is_array( $object ) )
            $value = ! empty( $object[$var] ) ? $object[$var] : $default;
        else
            $value = $default;

        if( is_callable( $sanitize_callback ) ) {
            if( is_array( $value ) )
                $value = array_map( $sanitize_callback, $value );
            else
                $value = call_user_func( $sanitize_callback, $value );
        }

        return $value;
    }
}
