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

### Hooks are registered on the objects that handle them

Up to 0.12.x, every Zoninator hook was registered on the `Zoninator` object, with a one-line method that handed the work to the class that does it. In 1.0.0 each hook is registered on that class's object, and the one-line methods are gone.

**Check your `remove_action()` and `remove_filter()` calls.** One that names an old callback, such as `remove_action( 'template_redirect', array( Zoninator(), 'do_zoninator_feeds' ) )`, now matches nothing. PHP gives no error and WordPress gives no notice, so the hook quietly keeps running. Replace it with the call in the last column. The new `zoninator_container()` function returns the container that builds Zoninator's classes, and its `get()` takes a class name and returns that class's object, the one that handles the hook. Use those objects only to unhook, as their other methods are internal.

| Hook | Old callback | How to unhook it in 1.0.0 |
|---|---|---|
| `init` | `array( Zoninator(), 'init' )`, priority 99, which registered the zone taxonomy | `remove_action( 'init', array( zoninator_container()->get( \Automattic\Zoninator\Taxonomy::class ), 'register' ) )`, now at the default priority |
| `init` | `array( Zoninator(), 'add_zone_feed' )` | `remove_action( 'init', array( zoninator_container()->get( \Automattic\Zoninator\Feed::class ), 'add_rewrite_rule' ) )` |
| `template_redirect` | `array( Zoninator(), 'do_zoninator_feeds' )` | `remove_action( 'template_redirect', array( zoninator_container()->get( \Automattic\Zoninator\Feed::class ), 'serve' ) )` |
| `rest_api_init` | `array( Zoninator(), 'register_rest_routes' )` | `remove_action( 'rest_api_init', array( zoninator_container()->get( \Automattic\Zoninator\Bootstrapper::class ), 'register_rest_routes' ) )` |
| `widgets_init` | `array( Zoninator(), 'widgets_init' )` | `remove_action( 'widgets_init', array( 'Zoninator_ZonePosts_Widget', 'register' ) )` |
| `split_shared_term` | `array( Zoninator(), 'split_shared_term' )` | `remove_action( 'split_shared_term', array( zoninator_container()->get( \Automattic\Zoninator\Zones::class ), 'split_shared_term' ) )` |
| `query_loop_block_query_vars` | `array( Zoninator(), 'zone_block_query_vars' )` | `remove_filter( 'query_loop_block_query_vars', array( zoninator_container()->get( \Automattic\Zoninator\Block::class ), 'filter_query_vars' ) )` |
| `rest_post_query` | `array( Zoninator(), 'zone_block_rest_query' )` | `remove_filter( 'rest_post_query', array( zoninator_container()->get( \Automattic\Zoninator\Block::class ), 'filter_rest_query' ) )` |
| `enqueue_block_editor_assets` | `array( Zoninator(), 'zone_block_editor_assets' )` | `remove_action( 'enqueue_block_editor_assets', array( zoninator_container()->get( \Automattic\Zoninator\Block::class ), 'enqueue_editor_assets' ) )` |
| `admin_init` | `array( Zoninator(), 'admin_controller' )` | `remove_action( 'admin_init', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'handle_request' ) )` |
| `admin_init` | `array( Zoninator(), 'admin_init' )`, which added the two `admin_enqueue_scripts` callbacks | Nothing to unhook: the two callbacks below are now added directly |
| `admin_enqueue_scripts` | `array( Zoninator(), 'admin_enqueue_scripts' )` | `remove_action( 'admin_enqueue_scripts', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'enqueue_scripts' ) )` |
| `admin_enqueue_scripts` | `array( Zoninator(), 'admin_enqueue_styles' )` | `remove_action( 'admin_enqueue_scripts', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'enqueue_styles' ) )` |
| `admin_menu` | `array( Zoninator(), 'admin_page_init' )` | `remove_action( 'admin_menu', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'register_menu_page' ) )` |
| `toplevel_page_zoninator` | `array( Zoninator(), 'admin_page' )` | `remove_action( 'toplevel_page_zoninator', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'render' ) )` |
| `zoninator_advanced_search_fields` | `array( Zoninator(), 'zone_advanced_search_cat_filter' )` | `remove_action( 'zoninator_advanced_search_fields', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'render_category_filter' ) )` |
| `zoninator_advanced_search_fields` | `array( Zoninator(), 'zone_advanced_search_date_filter' )`, priority 20 | `remove_action( 'zoninator_advanced_search_fields', array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'render_date_filter' ), 20 )` |

Zoninator adds all of these when it boots, on `plugins_loaded` at priority 10 (see [Zoninator boots on `plugins_loaded`](#zoninator-boots-on-plugins_loaded)). The admin ones used to wait for `init` at priority 99. Remove them on `plugins_loaded` at priority 20, which works for every hook in the table. The exception is `toplevel_page_zoninator`, which is added on `admin_menu`, so remove it on `admin_menu` at priority 11 or later.

The default callbacks in [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) have moved the same way: `array( Zoninator(), 'admin_page_zone_post_col_position' )` and `array( Zoninator(), 'admin_page_zone_post_col_info' )` are now `array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'render_position_column' )` and `array( zoninator_container()->get( \Automattic\Zoninator\Admin\Screen::class ), 'render_info_column' )`. If your filter compares them or calls them directly, update it.

### Zoninator boots on `plugins_loaded`

Zoninator used to build itself as soon as WordPress loaded its file. It now builds its classes and adds its hooks on `plugins_loaded`, at priority 10, so every plugin has loaded by the time it starts. Code that runs later, such as theme code and anything on `init`, sees no difference.

- Calling `Zoninator()` before `plugins_loaded`, from a mu-plugin or another plugin's main file, still works: it boots Zoninator there and then. `zoninator_container()` does the same.
- If a theme loads Zoninator from its `functions.php`, after `plugins_loaded` has fired, Zoninator boots straight away.
- The `$zoninator` global is set when Zoninator boots, so it's empty until then. Use `Zoninator()` instead, which never is.
- `Zoninator` is now `final`, and its constructor takes the parts it delegates to. Don't extend it or build it with `new Zoninator()`; use `Zoninator()`. The classes under `Automattic\Zoninator\`, apart from `Container`, are marked `@internal`.
- `Zoninator::maybe_add_rest_api()` and `Zoninator::register_rest_routes()` are gone. The REST routes are always registered, by `Automattic\Zoninator\Bootstrapper::register_rest_routes()` on `rest_api_init`. `Zoninator()->rest_api` still holds the REST controller once `rest_api_init` has fired.

`Zoninator::init()`, which ran on `init` at priority 99, is gone too, and so is the setup it did there:

- The settings filters, such as `zoninator_zone_lock_period`, apply each time Zoninator reads the value, so a filter you add at any point before then counts. They used to apply once, on `init`.
- The `zoninator_pre_init` and `zoninator_post_init` actions are removed. Hook `init` instead, at whatever priority you need.
- `Zoninator()->default_post_types` is removed. Posts support zones from the moment Zoninator boots. To take zones away from posts, call `remove_post_type_support( 'post', 'zoninator_zones' )`. To add a post type, call `add_post_type_support( 'page', 'zoninator_zones' )`, add `zoninator_zones` to the post type's `supports`, or call `Zoninator()->register_zone_post_type()`. Any of these works at any point.
- The `zoninator_zones` taxonomy registers on `init` at the default priority, with no post types attached. A post's place in a zone has always been post meta, not a term relationship, so nothing else changes, but `get_object_taxonomies( 'post' )` no longer lists it.
- `remove_action( 'init', array( Zoninator(), 'init' ), 99 )` now matches nothing; the table above gives the call that stops the taxonomy registering. To hide the Zones screen from some users, filter [`zoninator_manage_zone_cap`](hooks.md#zoninator_manage_zone_cap-filter).

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

The work behind `Zoninator` now lives in classes under `Automattic\Zoninator\`. They're `final` and internal, so keep using `Zoninator()`, the template tags and the hooks. Zoninator's hooks are still registered on the `Zoninator` object, so `remove_action( 'init', array( Zoninator(), 'init' ), 99 )` and the like keep working. 1.0.0 [moves most of them](#hooks-are-registered-on-the-objects-that-handle-them).

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
