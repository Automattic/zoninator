# Upgrading

What to check when you update Zoninator, release by release. Stored zones and their posts carry over unchanged: no release here needs a data migration.

For the full list of changes in each release, see the [CHANGELOG](../CHANGELOG.md).

## To 1.0.0

### WordPress 6.9 and PHP 8.2 are now the minimum

Zoninator 1.0.0 requires WordPress 6.9 or later and PHP 8.2 or later, up from WordPress 6.4 and PHP 7.4. WordPress won't offer 1.0.0 to a site that doesn't meet both, so that site stays on 0.12.x. Update WordPress and PHP first, then update Zoninator.

### The Zones screen's admin-ajax actions are gone

The six admin-ajax actions deprecated in 0.12.0 (`zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts`, `zoninator_update_recent` and `zoninator_update_lock`) are removed. A request to one of them now gets admin-ajax's `0` response, with a `400` status. Use the REST routes listed under [The Zones screen uses the REST API](#the-zones-screen-uses-the-rest-api) instead.

The code behind them is gone too:

| Removed | Use instead |
|---|---|
| `Zoninator::ajax_add_post()`, `ajax_remove_post()`, `ajax_reorder_posts()`, `ajax_search_posts()`, `ajax_recent_posts()` and `ajax_update_lock()` | the REST routes in the table under "To 0.12.0" |
| `Zoninator::ajax_return()` | nothing; the REST routes send their own responses |
| `Zoninator::ajax_move_zone_post()` | `Zoninator()->remove_zone_posts()`, then `Zoninator()->add_zone_posts()` |
| `Zoninator::zone_admin_recent_posts_dropdown()` | `GET /zoninator/v1/search` with no `term` |
| `Zoninator::admin_ajax_init()` | nothing; it only registered the actions |
| `Automattic\Zoninator\Admin\Ajax`, its `NONCE_ACTION` constant, and the `Zoninator()->zone_ajax_nonce_action` property | nothing; the REST API checks the `wp_rest` nonce |

If your code unhooked an action with `remove_action( 'wp_ajax_zoninator_add_post', array( Zoninator(), 'ajax_add_post' ) )` or similar, delete that line: there's nothing left to unhook.

On the Zones screen, the hidden `zone-nonce-ajax-action` nonce field and the `ajaxNonceAction` entry in `zoninatorOptions` are gone, and so are the `zoninator.ajax()`, `zoninator.getAjaxNonce()`, `zoninator.getAjaxNonceField()` and `zoninator.updateAjaxNonce()` JavaScript functions. The screen still fires the `zoninator.ajax` jQuery event on `#zone-post-search` before each REST request, with the action name (such as `add_post`) and the request data, so listeners on it keep working.

### `Zoninator`'s old properties are gone

The `__get()`, `__set()` and `__isset()` methods that kept `Zoninator()`'s old public properties working, with a notice, are removed. Reading one now gives PHP's "Undefined property" warning and `null`. Writing one creates a dynamic property, which PHP 8.2 deprecates and Zoninator never reads, so the write no longer changes anything. Use the replacements in the table under [`Zoninator`'s properties are no longer public](#zoninators-properties-are-no-longer-public), except for `$zone_messages` and `$zone_nonce_prefix`, whose `_get_message()` and `_get_nonce_key()` replacements are removed too (see below), and `$zone_ajax_nonce_action`, which went with the admin-ajax actions.

### `Zoninator` keeps only its public API

`Zoninator` now holds the zone, zone post, lock and post type methods, the settings getters (such as `get_zone_lock_period()`), the `ZONE_TAXONOMY` and `ZONE_META_PREFIX` constants, and the callbacks its hooks use. These methods are removed:

| Removed | Use instead |
|---|---|
| `_get_request_var()`, `_get_get_var()` and `_get_post_var()` | Core's functions on `$_REQUEST`, `$_GET` or `$_POST`, such as `sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) )` |
| `_get_value_or_default( $key, $source, $default )` | `! empty( $source[ $key ] ) ? $source[ $key ] : $default` |
| `_sanitize_value( $value )` | `htmlspecialchars( stripslashes( $value ) )`, which is what it did |
| `_validate_date_filter( $date )` | `preg_match( '/\d{4}-\d{2}-\d{2}/', $date )` |
| `_validate_category_filter( $cat )` | `get_term_by( 'id', $cat, 'category' )` |
| `_get_message()` | Nothing; the messages belong to the Zones screen |
| `_get_nonce_key( $action )` | Nothing; the Zones screen's nonce actions are `'zone-nonce-' . $action` |
| `_get_zone_page_url( $args )` | `add_query_arg( $args, menu_page_url( 'zoninator', false ) )` |
| `_fill_zone_details( $zone )` | `Zoninator()->get_zone( $zone )`, which returns the zone with its details filled in |
| `_unauthorized_access()` | `wp_die()` |
| `_empty_zone_posts_cache()`, `get_zone_cache_key()`, `get_zone_posts_from_cache()` and `add_zone_posts_to_cache()` | Nothing; they did nothing since 0.12.0, when core's query cache took over |
| `_get_add_zones_cap()` | `apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' )` |
| `_get_edit_zones_cap()` | `apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' )` |
| `_get_manage_zones_cap()` | `apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' )` |
| `_current_user_can_add_zones()` | `current_user_can( apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' ) )` |
| `_current_user_can_edit_zones( $zone_id )` | `apply_filters( 'zoninator_current_user_can_edit_zone', current_user_can( apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' ) ), $zone_id )` |
| `_current_user_can_manage_zones()` | `current_user_can( apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' ) )` |
| `check( $action, $zone_id )` | The matching check above: `'insert'` is the add check, `'update'` and `'delete'` are the edit check for `$zone_id`, and anything else is the manage check |
| `get_formatted_zone_slug()` and `get_unformatted_zone_slug()` | The slug itself; both returned it unchanged |
| `get_admin_zone_post()` and `get_admin_zone_posts()` | `Zoninator()->get_zone_posts()` for the posts. Nothing in Zoninator used the arrays these built, and `zoninator_zone_post_columns` no longer fires for them |
| `admin_page_zone_tabs()`, `admin_page_zone_edit()`, `admin_page_zone_details()`, `admin_page_zone_content()`, `admin_page_zone_post()`, `zone_advanced_search_filters()` and `zone_admin_search_form()` | Nothing; the Zones screen renders these parts itself. To add to it, use the [zone field hooks](hooks.md#zoninator_pre_zone_fields--zoninator_post_zone_fields-actions), [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) and [`zoninator_advanced_search_fields`](hooks.md#zoninator_advanced_search_fields-action) |
| `is_zoninator_page()` | `'toplevel_page_zoninator' === get_current_screen()->id` |

### The widget's cache filter has a new name

`zone_posts_widget_block_save_cache_seconds` is now [`zoninator_zone_posts_widget_block_save_cache_seconds`](hooks.md#zoninator_zone_posts_widget_block_save_cache_seconds-filter), with the prefix every other Zoninator hook uses. The old name no longer fires, so rename it in your `add_filter()` call.

## To 0.12.0

Most sites need do nothing. Check this section if your code reads or writes `Zoninator()`'s properties, calls the REST API, uses the REST classes Zoninator used to bundle, or customizes the Zones screen.

### `Zoninator`'s properties are no longer public

The values sites tune now come from filters, and the fixed values are class constants. Reading an old property still works, with a `_doing_it_wrong()` notice that names its replacement. Writing to one of the tunable values still takes effect, with a notice, until 1.0.0. Writing to a fixed value is ignored.

| Property | Read it with | Change it with |
|---|---|---|
| `$zone_lock_period` | `Zoninator()->get_zone_lock_period()` | the [`zoninator_zone_lock_period`](hooks.md#zoninator_zone_lock_period-filter) filter |
| `$zone_max_lock_period` | `Zoninator()->get_zone_max_lock_period()` | the [`zoninator_zone_max_lock_period`](hooks.md#zoninator_zone_max_lock_period-filter) filter |
| `$posts_per_page` | `Zoninator()->get_posts_per_page()` | the [`zoninator_posts_per_page`](hooks.md#zoninator_posts_per_page-filter) filter |
| `$zone_detail_defaults` | `Zoninator()->get_zone_detail_defaults()` | the [`zoninator_zone_detail_defaults`](hooks.md#zoninator_zone_detail_defaults-filter) filter |
| `$post_types` | `Zoninator()->get_supported_post_types()` | `Zoninator()->register_zone_post_type()` |
| `$zone_messages` | `Zoninator()->_get_message()` | can't be changed |
| `$zone_taxonomy` | `Zoninator::ZONE_TAXONOMY` | can't be changed |
| `$zone_meta_prefix` | `Zoninator::ZONE_META_PREFIX` | can't be changed |
| `$key` | `Automattic\Zoninator\Admin\Screen::PAGE_SLUG` | can't be changed |
| `$zone_nonce_prefix` | `Zoninator()->_get_nonce_key()` | can't be changed |
| `$zone_ajax_nonce_action` | `Automattic\Zoninator\Admin\Ajax::NONCE_ACTION` | can't be changed |
| `$zone_term_prefix` | Zoninator never used it; use your own value | — |

The work behind `Zoninator` now lives in classes under `Automattic\Zoninator\`. They're `final` and internal, so keep using `Zoninator()`, the template tags and the hooks. Zoninator's hooks are still registered on the `Zoninator` object, so `remove_action( 'init', array( Zoninator(), 'init' ), 99 )` and the like keep working.

### The REST API runs on core's `WP_REST_Controller`

Zoninator no longer bundles the Mixtape REST library. The routes, their arguments and their response bodies are the same, except for the changes below. Code that used the library directly needs updating:

- The `Zoninator_Api`, `Zoninator_Api_Controller`, `Zoninator_Api_Filter_Search`, `Zoninator_Api_Schema_Converter` and `Zoninator_REST_*` classes are gone.
- The `mixtape_*`, `mt_*` and `rest_api_schema_properties` hooks are gone. To change a route, use core's REST hooks, such as `rest_endpoints` or `rest_pre_dispatch`.
- `Zoninator()->rest_api` is now an `Automattic\Zoninator\REST\Zones_Controller`. It's set on `rest_api_init`, so it's `null` before then.

What changes for REST clients:

- A request refused by a capability check gets `401` when logged out and `403` when logged in, with the code `permission-denied`. It used to get `500`.
- Every error has a `code` alongside its `message`, in core's REST error shape. The `400` and `404` responses used to have only a `message`. A zone locked by someone else gives `zone-locked`, and its response keeps the `zone_id` and `blocked` it had before. See [Errors](rest-api.md#errors) for the codes.
- Three error messages are reworded: "Invalid post id" is now "Invalid post ID.", "zone id required" is now "Zone ID is required.", and "Zone not found" is now "Zone not found.". If your client compares the message text, match on the `code` instead: `invalid-post-id`, `zone-id-required` and `zone-not-found`. Messages are translated, so they were never reliable to match.
- `PUT /zones/{zone_id}` with an empty `description` now clears the zone's description. It used to ignore an empty value. If your client sends `"description": ""` to mean "leave it alone", leave the field out instead. An empty `name` or `slug` is still ignored.
- The search (`GET /search`, and the deprecated `zoninator_search_posts` and `zoninator_update_recent` actions) only lists posts the user can read, so a page of results can hold fewer posts than asked for when an access plugin hides some.
- `PUT /zones/{zone_id}/posts` and `PUT /zones/{zone_id}/lock` check `zoninator_manage_zone_cap`, the capability the Zones screen has always needed to curate a zone. They used to check `zoninator_edit_zone_cap` and the `zoninator_current_user_can_edit_zone` filter. If you used that filter to stop some users changing a zone's posts over REST, it no longer does; the Zones screen never honored it for that either.
- Malformed input, such as a non-numeric post ID, gets a `400` instead of a PHP fatal error.

See the [REST API reference](rest-api.md) for the new routes the Zones screen uses.

### Zones can only be changed through Zoninator

The `zoninator_zones` taxonomy no longer grants any of core's term capabilities (`manage_terms`, `edit_terms`, `delete_terms` and `assign_terms` all map to `do_not_allow`). Core's own term handling therefore refuses zones: `tax_input` on post saves, the term admin-ajax actions and the XML-RPC term methods. Zoninator never used term relationships, since a post's place in a zone is post meta, so nothing in the plugin changes.

If your code assigned zones through any of those, use Zoninator instead: `Zoninator()->add_zone_posts()`, `Zoninator()->remove_zone_posts()`, or the [REST API](rest-api.md). Who may change zones is decided only by `zoninator_add_zone_cap`, `zoninator_edit_zone_cap`, `zoninator_manage_zone_cap` and the `zoninator_current_user_can_edit_zone` filter.

### The Zones screen uses the REST API

The Zones screen now adds, removes, reorders and searches for posts, and takes the zone lock, through the REST API with `wp.apiFetch()`. These admin-ajax actions, and the `Zoninator` methods behind them, are deprecated, and [1.0.0 removes them](#the-zones-screens-admin-ajax-actions-are-gone):

| admin-ajax action | Use instead |
|---|---|
| `zoninator_add_post` | `POST /zoninator/v1/zones/{zone_id}/posts/{post_id}` |
| `zoninator_remove_post` | `DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}` |
| `zoninator_reorder_posts` | `PUT /zoninator/v1/zones/{zone_id}/posts` |
| `zoninator_search_posts` | `GET /zoninator/v1/search` |
| `zoninator_update_recent` | `GET /zoninator/v1/search` with no `term` |
| `zoninator_update_lock` | `PUT /zoninator/v1/zones/{zone_id}/lock` |

`Zoninator::ajax_return()`, `Zoninator::ajax_move_zone_post()` and `Zoninator::zone_admin_recent_posts_dropdown()` are deprecated too. To move a post between zones, call `Zoninator()->remove_zone_posts()` and `Zoninator()->add_zone_posts()`.

The screen itself has changed:

- It's built from core's admin styles, without the jQuery UI theme. Custom CSS or JavaScript aimed at the old markup may need updating.
- The recent posts dropdown is gone. The search field offers the latest content before anything is typed, and `zoninator_recent_posts_args` still filters it.
- Changes save as they're made, and the zone's posts can be reordered with the keyboard.
- When the lock's idle time runs out, the screen asks whether you're still working instead of leaving straight away.

The zone field hooks (`zoninator_pre_zone_fields` and the rest), `zoninator_zone_post_columns` and the search filters all still apply.

### Template tags that now work

`z_get_next_post_in_zone()` and `z_get_prev_post_in_zone()` caused a fatal error in every earlier version, and `z_get_post_zones()` always returned `null`. They now work as the [theme developer guide](theme-developers.md) describes. If your theme avoided them or worked around them, you can use them now.
