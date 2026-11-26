# Replacing the `Zoninator` class

1.0.0 removes the `Zoninator` class, the `Zoninator()` function, the `$zoninator` global and `z_get_zoninator()`. Code that calls any of them stops with a fatal error. This page gives the replacement for each method and property. It's part of [Upgrading to 1.0](upgrading-to-1.0.md).

A search for `Zoninator()`, `$zoninator` and `z_get_zoninator` across your theme and plugins finds every call.

In short: to read zones, use the [template tags](theme-developers.md). To change them, use the classes in [Changing zones from PHP](php-api.md), which take IDs where the old methods also took slugs, terms and `WP_Post` objects, and throw exceptions where the old methods returned a `WP_Error`.

## Zones and their posts

| `Zoninator()->…` | Use instead |
|---|---|
| `insert_zone( $slug, $name, $details )` | `ZoneManager::create( $slug, $name, $details )` |
| `update_zone( $zone, $data )` | `ZoneManager::update( $zone_id, $data )` |
| `delete_zone( $zone )` | `ZoneManager::remove( $zone_id )` |
| `add_zone_posts( $zone, $posts, true )` | `ZoneCurator::add_posts( $zone_id, $post_ids )` |
| `add_zone_posts( $zone, $posts )` | `ZoneCurator::set_posts( $zone_id, $post_ids )` |
| `remove_zone_posts( $zone, $posts )` | `ZoneCurator::remove_posts( $zone_id, $post_ids )` |
| `remove_zone_posts( $zone )`, with no posts or an empty array | `ZoneCurator::clear_posts( $zone_id )`. `remove_posts()` with an empty array removes nothing |
| `lock_zone( $zone, $user_id )` | `ZoneLocker::lock( $zone_id, $user_id )`. It no longer replaces a lock another user holds: it throws `ZoneLockedException`. To take a zone over, call `ZoneLocker::unlock()` first |
| `unlock_zone( $zone )` | `ZoneLocker::unlock( $zone_id )` |
| `is_zone_locked( $zone )` | `ZoneLocker::get_blocker_id( $zone_id, get_current_user_id() )`, which gives the lock holder's ID, or `null` |
| `get_zone()`, `get_zones()`, `get_zone_posts()` and `get_zone_query()` | `zoninator_get_zone()`, `zoninator_get_zones()`, `zoninator_get_posts_in_zone()` and `zoninator_get_zone_query()` |
| `get_next_post_in_zone()`, `get_prev_post_in_zone()` and `get_zones_for_post()` | `zoninator_get_next_post_in_zone()`, `zoninator_get_prev_post_in_zone()` and `zoninator_get_post_zones()` |
| `get_first_post_in_zone()`, `get_last_post_in_zone()` and `get_single_post_in_zone()` | The first item of `zoninator_get_posts_in_zone( $zone, array( 'posts_per_page' => 1 ) )`, which returns an array. Add `'order' => 'DESC'` for the last post |
| `zone_exists( $zone )` | `(bool) zoninator_get_zone( $zone )` |
| `get_zone_id( $zone )` and `get_zone_slug( $zone )` | `zoninator_get_zone( $zone )->term_id` and `->slug` |
| `get_zone_meta_key( $zone )` | `'_zoninator_order_' . $zone_id` |
| `get_post_order( $post, $zone )` | `get_post_meta( $post_id, '_zoninator_order_' . $zone_id, true )` |
| `get_post_id( $post )` | `$post->ID` |
| `get_post_status_label( $post )` and `get_post_type_label( $post )` | `get_post_status_object( $post->post_status )->label` and `get_post_type_object( $post->post_type )->labels->singular_name` |
| `get_zone_feed( $zone )` | `GET /zoninator/v1/zones/{zone_id}/posts`, or `zoninator_get_posts_in_zone()` for the whole posts |
| `get_admin_zone_post()` and `get_admin_zone_posts()` | `zoninator_get_posts_in_zone()` for the posts. Nothing in Zoninator used the arrays these built |
| `ajax_move_zone_post()` | `ZoneCurator::remove_posts()` on the old zone, then `add_posts()` on the new one |

`ZoneManager` and `ZoneCurator` make no capability or lock checks, as the old methods didn't. To make a change for a user with the checks the Zones screen makes, use `GuardedZoneManager` and `GuardedZoneCurator`; see [Changing zones from PHP](php-api.md#the-classes).

Two things to watch when you switch:

- **Pass IDs.** The old methods took a zone's ID, slug or term, and posts as IDs, `WP_Post` objects or `array( 'ID' => … )`. The new ones take IDs only, and throw an `InvalidArgumentException` for a post that isn't one, so pass `wp_list_pluck( $posts, 'ID' )`.
- **Pass plain text.** `ZoneManager` encodes a zone's name and details for storage. Don't call `esc_html()` or `htmlspecialchars()` on them first, or they're encoded twice.

## Settings, post types and capabilities

| `Zoninator()->…` | Use instead |
|---|---|
| `$zone_lock_period` and `get_zone_lock_period()` | `apply_filters( 'zoninator_zone_lock_period', 30 )`. To change it, use the [filter](hooks.md#zoninator_zone_lock_period-filter) |
| `$zone_max_lock_period` and `get_zone_max_lock_period()` | `apply_filters( 'zoninator_zone_max_lock_period', 600 )`, or the [filter](hooks.md#zoninator_zone_max_lock_period-filter) |
| `$posts_per_page` and `get_posts_per_page()` | `apply_filters( 'zoninator_posts_per_page', 10 )`, or the [filter](hooks.md#zoninator_posts_per_page-filter) |
| `$zone_detail_defaults` and `get_zone_detail_defaults()` | `apply_filters( 'zoninator_zone_detail_defaults', array( 'description' => '' ) )`, or the [filter](hooks.md#zoninator_zone_detail_defaults-filter) |
| `$post_types` and `get_supported_post_types()` | `get_post_types_by_support( 'zoninator_zones' )` |
| `register_zone_post_type( $post_type )` | `add_post_type_support( $post_type, 'zoninator_zones' )` |
| `$default_post_types` | Posts support zones from the start. To take zones away from them, `remove_post_type_support( 'post', 'zoninator_zones' )` |
| `$zone_taxonomy` and `Zoninator::ZONE_TAXONOMY` | `'zoninator_zones'` |
| `$zone_meta_prefix` and `Zoninator::ZONE_META_PREFIX` | `'_zoninator_order_'` |
| `$key` | `'zoninator'`, the Zones screen's page slug |
| `current_user_can_add_post_to_zone( $post )` | `post_type_supports( get_post_type( $post ), 'zoninator_zones' ) && current_user_can( 'read_post', $post_id )` |
| `_get_add_zones_cap()`, `_get_edit_zones_cap()` and `_get_manage_zones_cap()` | `apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' )`, and the same for `zoninator_edit_zone_cap` and `zoninator_manage_zone_cap` |
| `_current_user_can_add_zones()` and `_current_user_can_manage_zones()` | `current_user_can()` with the capability above |
| `_current_user_can_edit_zones( $zone_id )` | `apply_filters( 'zoninator_current_user_can_edit_zone', current_user_can( apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' ) ), $zone_id, get_current_user_id() )` |
| `check( $action, $zone_id )` | The matching check above: `'insert'` is the add check, `'update'` and `'delete'` are the edit check for `$zone_id`, and anything else is the manage check |

The stored zones depend on `'zoninator_zones'` and `'_zoninator_order_'`, so neither can change.

## Removed with nothing to replace them

These served the Zones screen, or did nothing a site needs. If your code calls one, remove the call.

| Removed | Notes |
|---|---|
| `$rest_api` and `maybe_add_rest_api()` | To change a route, use core's REST hooks, such as `rest_endpoints` |
| `$zone_messages`, `$zone_nonce_prefix`, `$zone_ajax_nonce_action` and `$zone_term_prefix` | |
| `_get_message()`, `_get_nonce_key()`, `verify_nonce()` and `verify_access()` | The zone form's nonce actions are `'zone-nonce-' . $action`, followed by `-` and the zone ID to change or delete a zone, and its nonce is in the `zoninator_nonce` field |
| `ajax_add_post()`, `ajax_remove_post()`, `ajax_reorder_posts()`, `ajax_search_posts()`, `ajax_recent_posts()`, `ajax_update_lock()`, `ajax_return()`, `admin_ajax_init()` and `zone_admin_recent_posts_dropdown()` | See [The admin-ajax actions are gone](upgrading-to-1.0.md#the-admin-ajax-actions-are-gone) |
| `admin_page_zone_tabs()`, `admin_page_zone_edit()`, `admin_page_zone_details()`, `admin_page_zone_content()`, `admin_page_zone_post()`, `zone_advanced_search_filters()` and `zone_admin_search_form()` | To add to the Zones screen, use the [zone field hooks](hooks.md#zoninator_pre_zone_fields--zoninator_post_zone_fields-actions), [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) and [`zoninator_advanced_search_fields`](hooks.md#zoninator_advanced_search_fields-action) |
| `is_zoninator_page()` | `'toplevel_page_zoninator' === get_current_screen()->id` |
| `_empty_zone_posts_cache()`, `get_zone_cache_key()`, `get_zone_posts_from_cache()` and `add_zone_posts_to_cache()` | They did nothing in 0.12.x |
| `get_formatted_zone_slug()` and `get_unformatted_zone_slug()` | Both returned the slug unchanged |
| `_fill_zone_details( $zone )` | `zoninator_get_zone( $zone )` returns the zone with its details filled in |
| `_get_zone_page_url( $args )` | `add_query_arg( $args, menu_page_url( 'zoninator', false ) )` |
| `_get_request_var()`, `_get_get_var()` and `_get_post_var()` | Core's functions on `$_REQUEST`, `$_GET` or `$_POST`, such as `sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) )` |
| `_get_value_or_default( $key, $source, $default )` | `! empty( $source[ $key ] ) ? $source[ $key ] : $default` |
| `_sanitize_value( $value )` | `htmlspecialchars( stripslashes( $value ) )`, which is what it did |
| `_validate_date_filter( $date )` and `_validate_category_filter( $cat )` | `preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date )` and `get_term_by( 'id', $cat, 'category' )` |
| `_unauthorized_access()` | `wp_die()` |

The methods that were hook callbacks, such as `init()` and `do_zoninator_feeds()`, are listed in [Hooks are registered on the objects that handle them](upgrading-to-1.0.md#hooks-are-registered-on-the-objects-that-handle-them).

## The classes 0.12.0 added

0.12.0 split `Zoninator`'s work into classes under `Automattic\Zoninator\`. They were internal, but if your code names one, for a constant or a type, it has a new name, and a new file under `src/` that loads itself when the class is first used. If your code loads one with `require`, remove that line.

| 0.12.x | 1.0.0 |
|---|---|
| `Automattic\Zoninator\Locks` | `Automattic\Zoninator\Application\ZoneLocker`, whose `lock()` and `get_blocker_id()` take a zone ID and a user ID |
| `Automattic\Zoninator\Admin\Screen` | Several classes under `Automattic\Zoninator\Infrastructure\WordPress\Admin\`. Its `PAGE_SLUG` constant is now the string `'zoninator'` |
| `Automattic\Zoninator\Admin\Ajax` and its `NONCE_ACTION` constant | Nothing; the REST API checks the `wp_rest` nonce |
| `Automattic\Zoninator\REST\Zones_Controller` | Four controllers under `Automattic\Zoninator\Infrastructure\WordPress\Rest\`: `ZonesController`, `ZonePostsController`, `SearchController` and `LockController`. Its error code constants are on `ErrorCodes` |
| `Automattic\Zoninator\Request` | Nothing. Its `NONCE_PREFIX` constant is now the string `'zone-nonce'` |
| `Automattic\Zoninator\Settings`, `Taxonomy`, `Capabilities`, `Zones` and `Feed` | The same name under `Automattic\Zoninator\Infrastructure\WordPress\` |

Apart from `ZoneLocker` and `ErrorCodes`, these are still internal, and can change in any release. Use the template tags, the classes in [Changing zones from PHP](php-api.md) and the hooks instead.
