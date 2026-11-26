# Upgrading

What to check when you update Zoninator, release by release. Stored zones and their posts carry over unchanged: no release here needs a data migration.

For the full list of changes in each release, see the [CHANGELOG](../CHANGELOG.md).

## To 1.0.0

1.0.0 is a breaking release. Old names are removed outright, so code that still uses one stops with a fatal error instead of a notice. Test the update on a staging site first.

What to check depends on what your site does:

| If your site… | Read |
|---|---|
| has a theme or plugin that calls a `z_` function, such as `z_get_posts_in_zone()` | [The template tags have the `zoninator_` prefix](#the-template-tags-have-the-zoninator_-prefix). Most sites that show zones in a theme need this one |
| calls `Zoninator()`, or uses the `$zoninator` global | [The `Zoninator` class and `Zoninator()` are gone](#the-zoninator-class-and-zoninator-are-gone) |
| removes one of Zoninator's hooks with `remove_action()` or `remove_filter()` | [Hooks are registered on the objects that handle them](#hooks-are-registered-on-the-objects-that-handle-them) |
| listens on `zoninator_add_zone_posts` or `zoninator_remove_zone_posts` | [The zone post actions always pass post IDs](#the-zone-post-actions-always-pass-post-ids) |
| changes zones over the REST API | [Zone locks now stop every change](#zone-locks-now-stop-every-change-not-only-the-zones-screen), [The locked zone error keeps its details under `data`](#the-locked-zone-error-keeps-its-details-under-data) and [Adding a post over REST no longer returns its row](#adding-a-post-over-rest-no-longer-returns-its-row) |
| filters the Zone Posts widget's cache, or extends the widget | [The widget's cache filter has a new name](#the-widgets-cache-filter-has-a-new-name) |
| listens on `zoninator_split_shared_term` or `zoninator_did_split_shared_term` | [Zoninator no longer handles shared term splits](#zoninator-no-longer-handles-shared-term-splits) |
| still calls the old admin-ajax actions | [The Zones screen's admin-ajax actions are gone](#the-zones-screens-admin-ajax-actions-are-gone) |

A site that only uses the Zones screen, the Zone Posts widget and the Zone Posts block needs none of these, only the minimum versions below.

### WordPress 6.9 and PHP 8.2 are now the minimum

Zoninator 1.0.0 requires WordPress 6.9 or later and PHP 8.2 or later, up from WordPress 6.4 and PHP 7.4. WordPress won't offer 1.0.0 to a site that doesn't meet both, so that site stays on 0.12.x. Update WordPress and PHP first, then update Zoninator.

### The Zones screen's admin-ajax actions are gone

The six admin-ajax actions deprecated in 0.12.0 (`zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts`, `zoninator_update_recent` and `zoninator_update_lock`) are removed. A request to one of them now gets admin-ajax's `0` response, with a `400` status. Use the REST routes listed under [The Zones screen uses the REST API](#the-zones-screen-uses-the-rest-api) instead.

The code behind them is gone too:

| Removed | Use instead |
|---|---|
| `Zoninator::ajax_add_post()`, `ajax_remove_post()`, `ajax_reorder_posts()`, `ajax_search_posts()`, `ajax_recent_posts()` and `ajax_update_lock()` | the REST routes in the table under "To 0.12.0" |
| `Zoninator::ajax_return()` | nothing; the REST routes send their own responses |
| `Zoninator::ajax_move_zone_post()` | `ZoneCurator::remove_posts()` on the old zone, then `add_posts()` on the new one; see [The `Zoninator` class and `Zoninator()` are gone](#the-zoninator-class-and-zoninator-are-gone) |
| `Zoninator::zone_admin_recent_posts_dropdown()` | `GET /zoninator/v1/search` with no `term` |
| `Zoninator::admin_ajax_init()` | nothing; it only registered the actions |
| `Automattic\Zoninator\Admin\Ajax`, its `NONCE_ACTION` constant, and the `Zoninator()->zone_ajax_nonce_action` property | nothing; the REST API checks the `wp_rest` nonce |

If your code unhooked an action with `remove_action( 'wp_ajax_zoninator_add_post', array( Zoninator(), 'ajax_add_post' ) )` or similar, delete that line: there's nothing left to unhook.

On the Zones screen, the hidden `zone-nonce-ajax-action` nonce field and the `ajaxNonceAction` entry in `zoninatorOptions` are gone, and so are the `zoninator.ajax()`, `zoninator.getAjaxNonce()`, `zoninator.getAjaxNonceField()` and `zoninator.updateAjaxNonce()` JavaScript functions. The screen still fires the `zoninator.ajax` jQuery event on `#zone-post-search` before each REST request, with the action name (such as `add_post`) and the request data, so listeners on it keep working.

### The template tags have the `zoninator_` prefix

The template tags began with `z_`, which was too short a prefix to be safe from clashes. They now begin with `zoninator_`, like every Zoninator hook. They take the same arguments and return the same values.

**Update your theme before, or together with, the plugin.** The old names no longer exist, so a template that still calls one stops with a fatal error.

| 0.12.x | 1.0.0 |
|---|---|
| `z_get_zones()` | `zoninator_get_zones()` |
| `z_get_zone()` | `zoninator_get_zone()` |
| `z_get_posts_in_zone()` | `zoninator_get_posts_in_zone()` |
| `z_get_zone_query()` | `zoninator_get_zone_query()` |
| `z_get_next_post_in_zone()` | `zoninator_get_next_post_in_zone()` |
| `z_get_prev_post_in_zone()` | `zoninator_get_prev_post_in_zone()` |
| `z_get_post_zones()` | `zoninator_get_post_zones()` |
| `z_disable_zoninator_locks()` | `zoninator_disable_locks()` |
| `z_get_zoninator()` | Nothing; see the next section |
| `z_get_loop_post_id_or_default()` | `get_the_ID()` |

A search for `z_get_` and `z_disable_` across your theme and plugins finds every call.

### The `Zoninator` class and `Zoninator()` are gone

`Zoninator` did a little of everything, so nothing said which of its methods were the plugin's public API. It's removed, along with the `Zoninator()` function, the `$zoninator` global and `z_get_zoninator()`. Code that calls any of them stops with a fatal error.

To read zones, use the template tags. To change them, use the classes under `Automattic\Zoninator\Application\`, which you get from the container:

```php
use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneManager;

$zone = zoninator_container()->get( ZoneManager::class )->create( 'homepage', 'Home page' );

zoninator_container()->get( ZoneCurator::class )->set_posts( $zone->get_id(), array( 12, 34, 56 ) );
```

These classes take a zone's ID, not its slug or term; `zoninator_get_zone( 'homepage' )->term_id` gets the ID. They return an `Automattic\Zoninator\Domain\Zone`, and where the old methods returned a `WP_Error` they throw an exception from `Automattic\Zoninator\Domain\`:

- `InvalidZoneException`: a new zone has no slug.
- `ZoneNotFoundException`: there's no zone with that ID.
- `ZoneNotSavedException`: WordPress refused to save or delete the zone, for example because another zone has its name.
- `ZoneFullException`: the zone would hold more posts than its [limit](hooks.md#zoninator_post_count_limit-filter).
- `ZoneLockedException`: someone else holds the zone's lock.

The `Guarded` classes, described under [Zone locks now stop every change](#zone-locks-now-stop-every-change-not-only-the-zones-screen), also throw `AccessDeniedException` and `PostNotAllowedException`.

| `Zoninator()->…` | Use instead |
|---|---|
| `insert_zone( $slug, $name, $details )` | `ZoneManager::create( $slug, $name, $details )` |
| `update_zone( $zone, $data )` | `ZoneManager::update( $zone_id, $data )` |
| `delete_zone( $zone )` | `ZoneRemover::remove( $zone_id )` |
| `add_zone_posts( $zone, $posts, true )` | `ZoneCurator::add_posts( $zone_id, $post_ids )` |
| `add_zone_posts( $zone, $posts )` | `ZoneCurator::set_posts( $zone_id, $post_ids )` |
| `remove_zone_posts( $zone, $posts )` | `ZoneCurator::remove_posts( $zone_id, $post_ids )`, or `clear_posts( $zone_id )` to empty the zone |
| `lock_zone( $zone, $user_id )` | `ZoneLocker::lock( $zone_id, $user_id )` |
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
| `get_supported_post_types()` | `get_post_types_by_support( 'zoninator_zones' )` |
| `register_zone_post_type( $post_type )` | `add_post_type_support( $post_type, 'zoninator_zones' )` |
| `get_zone_lock_period()`, `get_zone_max_lock_period()` and `get_posts_per_page()` | `apply_filters( 'zoninator_zone_lock_period', 30 )`, `apply_filters( 'zoninator_zone_max_lock_period', 600 )` and `apply_filters( 'zoninator_posts_per_page', 10 )` |
| `get_zone_detail_defaults()` | `apply_filters( 'zoninator_zone_detail_defaults', array( 'description' => '' ) )` |
| `get_zone_feed( $zone )` | `GET /zoninator/v1/zones/{zone_id}/posts`, or `zoninator_get_posts_in_zone()` for the whole posts |
| `current_user_can_add_post_to_zone( $post )` | `post_type_supports( get_post_type( $post ), 'zoninator_zones' ) && current_user_can( 'read_post', $post_id )` |
| `verify_nonce()` and `verify_access()` | Nothing; they served the Zones screen's own form |
| `Zoninator::ZONE_TAXONOMY` and `Zoninator::ZONE_META_PREFIX` | `'zoninator_zones'` and `'_zoninator_order_'`. Stored zones depend on both, so they can't change |
| `Zoninator()->rest_api` | Nothing; to change a route, use core's REST hooks |
| `Zoninator()->default_post_types` | See [Zoninator boots on `plugins_loaded`](#zoninator-boots-on-plugins_loaded) |

The properties that 0.12.0 deprecated went with the class. The table under [`Zoninator`'s properties are no longer public](#zoninators-properties-are-no-longer-public) names their replacements, which are now the calls above. `$zone_messages` and `$zone_nonce_prefix` have none.

These methods of `Zoninator`, most of them helpers that were never meant to be public, are removed too:

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
| `_fill_zone_details( $zone )` | `zoninator_get_zone( $zone )`, which returns the zone with its details filled in |
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
| `get_admin_zone_post()` and `get_admin_zone_posts()` | `zoninator_get_posts_in_zone()` for the posts. Nothing in Zoninator used the arrays these built, and `zoninator_zone_post_columns` no longer fires for them |
| `admin_page_zone_tabs()`, `admin_page_zone_edit()`, `admin_page_zone_details()`, `admin_page_zone_content()`, `admin_page_zone_post()`, `zone_advanced_search_filters()` and `zone_admin_search_form()` | Nothing; the Zones screen renders these parts itself. To add to it, use the [zone field hooks](hooks.md#zoninator_pre_zone_fields--zoninator_post_zone_fields-actions), [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) and [`zoninator_advanced_search_fields`](hooks.md#zoninator_advanced_search_fields-action) |
| `is_zoninator_page()` | `'toplevel_page_zoninator' === get_current_screen()->id` |

### Hooks are registered on the objects that handle them

Up to 0.12.x, every Zoninator hook was registered on the `Zoninator` object, with a one-line method that handed the work to the class that does it. In 1.0.0 each hook is registered on that class's object, and the one-line methods are gone.

**Check your `remove_action()` and `remove_filter()` calls.** One that names an old callback, such as `remove_action( 'template_redirect', array( Zoninator(), 'do_zoninator_feeds' ) )`, now matches nothing. PHP gives no error and WordPress gives no notice, so the hook quietly keeps running. Replace it with the call in the last column. The new `zoninator_container()` function returns the container that builds Zoninator's classes, and its `get()` takes a class name and returns that class's object, the one that handles the hook. Use those objects only to unhook, as their other methods are internal.

| Hook | Old callback | How to unhook it in 1.0.0 |
|---|---|---|
| `init` | `array( Zoninator(), 'init' )`, priority 99, which registered the zone taxonomy | `remove_action( 'init', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Taxonomy::class ), 'register' ) )`, now at the default priority |
| `init` | `array( Zoninator(), 'add_zone_feed' )` | `remove_action( 'init', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint::class ), 'add_rewrite_rule' ) )` |
| `template_redirect` | `array( Zoninator(), 'do_zoninator_feeds' )` | `remove_action( 'template_redirect', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint::class ), 'serve' ) )` |
| `rest_api_init` | `array( Zoninator(), 'register_rest_routes' )` | `remove_action( 'rest_api_init', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper::class ), 'register_rest_routes' ) )` |
| `widgets_init` | `array( Zoninator(), 'widgets_init' )` | `unregister_widget( 'Zoninator_ZonePosts_Widget' )` on `widgets_init` at priority 11, as before. Or `remove_action( 'widgets_init', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Widgets::class ), 'register' ) )` |
| `split_shared_term` | `array( Zoninator(), 'split_shared_term' )` | Nothing to unhook; see [Zoninator no longer handles shared term splits](#zoninator-no-longer-handles-shared-term-splits) |
| `query_loop_block_query_vars` | `array( Zoninator(), 'zone_block_query_vars' )` | `remove_filter( 'query_loop_block_query_vars', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Block::class ), 'filter_query_vars' ) )` |
| `rest_post_query` | `array( Zoninator(), 'zone_block_rest_query' )` | `remove_filter( 'rest_post_query', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Block::class ), 'filter_rest_query' ) )` |
| `enqueue_block_editor_assets` | `array( Zoninator(), 'zone_block_editor_assets' )` | `remove_action( 'enqueue_block_editor_assets', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Block::class ), 'enqueue_editor_assets' ) )` |
| `admin_init` | `array( Zoninator(), 'admin_controller' )` | `remove_action( 'admin_init', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneForm::class ), 'handle_request' ) )` |
| `admin_init` | `array( Zoninator(), 'admin_init' )`, which added the two `admin_enqueue_scripts` callbacks | Nothing to unhook: the two callbacks below are now added directly |
| `admin_enqueue_scripts` | `array( Zoninator(), 'admin_enqueue_scripts' )` | `remove_action( 'admin_enqueue_scripts', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\Assets::class ), 'enqueue_scripts' ) )` |
| `admin_enqueue_scripts` | `array( Zoninator(), 'admin_enqueue_styles' )` | `remove_action( 'admin_enqueue_scripts', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\Assets::class ), 'enqueue_styles' ) )` |
| `admin_menu` | `array( Zoninator(), 'admin_page_init' )` | `remove_action( 'admin_menu', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\Menu::class ), 'register' ) )` |
| `toplevel_page_zoninator` | `array( Zoninator(), 'admin_page' )` | `remove_action( 'toplevel_page_zoninator', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen::class ), 'render' ) )` |
| `zoninator_advanced_search_fields` | `array( Zoninator(), 'zone_advanced_search_cat_filter' )` | `remove_action( 'zoninator_advanced_search_fields', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\SearchForm::class ), 'render_category_filter' ) )` |
| `zoninator_advanced_search_fields` | `array( Zoninator(), 'zone_advanced_search_date_filter' )`, priority 20 | `remove_action( 'zoninator_advanced_search_fields', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\SearchForm::class ), 'render_date_filter' ), 20 )` |

Zoninator adds all of these when it boots, on `plugins_loaded` at priority 10 (see [Zoninator boots on `plugins_loaded`](#zoninator-boots-on-plugins_loaded)). The admin ones used to wait for `init` at priority 99. Remove them on `plugins_loaded` at priority 20, which works for every hook in the table. The exception is `toplevel_page_zoninator`, which is added on `admin_menu`, so remove it on `admin_menu` at priority 11 or later.

The default callbacks in [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) have moved the same way: `array( Zoninator(), 'admin_page_zone_post_col_position' )` and `array( Zoninator(), 'admin_page_zone_post_col_info' )` are now `array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRow::class ), 'render_position_column' )` and `array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRow::class ), 'render_info_column' )`. If your filter compares them or calls them directly, update it.

### Zoninator boots on `plugins_loaded`

Zoninator used to build itself as soon as WordPress loaded its file. It now builds its classes and adds its hooks on `plugins_loaded`, at priority 10, so every plugin has loaded by the time it starts. Code that runs later, such as theme code and anything on `init`, sees no difference.

- Calling `zoninator_container()` or a template tag before `plugins_loaded`, from a mu-plugin or another plugin's main file, boots Zoninator there and then.
- If a theme loads Zoninator from its `functions.php`, after `plugins_loaded` has fired, Zoninator boots straight away.
- The classes under `Automattic\Zoninator\Infrastructure\` are `final` and marked `@internal`, apart from the container. Get them from the container to unhook their callbacks, and for nothing else. For everything else, use the template tags, the classes under `Automattic\Zoninator\Application\` and the hooks.

The setup that ran on `init` at priority 99 has changed too:

- The settings filters, such as `zoninator_zone_lock_period`, apply each time Zoninator reads the value, so a filter you add at any point before then counts. They used to apply once, on `init`.
- The `zoninator_pre_init` and `zoninator_post_init` actions are removed. Hook `init` instead, at whatever priority you need.
- Posts support zones from the moment Zoninator boots. To take zones away from posts, call `remove_post_type_support( 'post', 'zoninator_zones' )`. To add a post type, call `add_post_type_support( 'page', 'zoninator_zones' )`, or add `zoninator_zones` to the post type's `supports`. Either works at any point. This replaces `Zoninator()->default_post_types`.
- The `zoninator_zones` taxonomy registers on `init` at the default priority, with no post types attached. A post's place in a zone has always been post meta, not a term relationship, so nothing else changes, but `get_object_taxonomies( 'post' )` no longer lists it.
- `remove_action( 'init', array( Zoninator(), 'init' ), 99 )` no longer works; the table above gives the call that stops the taxonomy registering. To hide the Zones screen from some users, filter [`zoninator_manage_zone_cap`](hooks.md#zoninator_manage_zone_cap-filter).

### The internal classes have moved

The classes 0.12.0 added under `Automattic\Zoninator\` are internal, but if your code names one, for a constant or a type, it has a new name. They're now arranged in layers and loaded by a PSR-4 autoloader, so each class's namespace matches its folder under `src/`.

| 0.12.x | 1.0.0 |
|---|---|
| `Automattic\Zoninator\Admin\Screen` | Several classes under `Automattic\Zoninator\Infrastructure\WordPress\Admin\`, each with one part of the screen. `Screen` still prints the page, and its `PAGE_SLUG` constant is now `ScreenLocation::SLUG` |
| `Automattic\Zoninator\REST\Zones_Controller` | Four controllers under `Automattic\Zoninator\Infrastructure\WordPress\Rest\`: `ZonesController`, `ZonePostsController`, `SearchController` and `LockController`. Its error code constants are on `ErrorCodes` |
| `Automattic\Zoninator\Settings`, `Taxonomy`, `Capabilities`, `Request`, `Zones` and `Feed` | The same class name under `Automattic\Zoninator\Infrastructure\WordPress\` |
| `Automattic\Zoninator\Locks` | `Automattic\Zoninator\Application\ZoneLocker`, whose `lock()` and `get_blocker_id()` take a zone ID and a user ID |

The files moved with them, from `src/class-*.php` to paths such as `src/Infrastructure/WordPress/Feed.php`. If your code loads one with `require`, remove that line: the class loads itself when it's first used. The sections of this guide for 0.12.0 use the new names.

### Adding a post over REST no longer returns its row

`POST /zoninator/v1/zones/{zone_id}/posts/{post_id}` returned the post's row for the Zones screen, as HTML. It now returns `{ "success": true }`, like the other routes that change a zone, so the API gives data only. If your client used the `html`, read the zone's posts with `GET /zoninator/v1/zones/{zone_id}/posts` instead.

The [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) filter is unaffected: the Zones screen still shows the columns you add, for posts already in the zone and for ones just added.

### The locked zone error keeps its details under `data`

A REST request refused because someone else holds the zone's lock still gets `400` with the code `zone-locked`. Its `zone_id` and `blocked` used to sit beside `code` and `message`. They're now under `data`, where core's REST errors keep their details:

```json
{
  "code": "zone-locked",
  "message": "Sorry, this zone is in use by Jane Doe and is currently locked. Please try again later.",
  "data": { "status": 400, "zone_id": 12, "blocked": true }
}
```

If your client reads `zone_id` or `blocked` from the top of the response, read `data.zone_id` and `data.blocked`, or match on the `code`.

### Zone locks now stop every change, not only the Zones screen

A zone's lock used to stop a second person opening the zone on the Zones screen, and nothing else. The REST API saved whatever it was sent, even while someone else was editing the zone.

Now every change made for a user is checked in one place: the user may make it, may use each post, and isn't kept out by someone else's lock. That covers the Zones screen, its zone form and every REST route that changes a zone or its posts.

- **REST clients:** a write to a zone someone else is editing gets `400` with the code `zone-locked`, and the message names them. Retry later, or have the client take the lock first with `PUT /zoninator/v1/zones/{zone_id}/lock`. Requests made as the lock holder go through.
- **PHP code:** `GuardedZoneManager` and `GuardedZoneCurator`, under `Automattic\Zoninator\Application\`, make a change for a user with these checks; each method takes the user's ID first. `ZoneManager`, `ZoneRemover` and `ZoneCurator` do the same work with no checks, for code that isn't acting for a user, such as a scheduled job.
- **Taking a lock:** `ZoneLocker::lock()` no longer replaces a lock another user holds; it throws `ZoneLockedException`. To take a zone over, call `ZoneLocker::unlock()` first. It also throws `ZoneNotFoundException` for an ID that isn't a zone, where it used to store a lock for it.

### The zone post actions always pass post IDs

`zoninator_add_zone_posts` and `zoninator_remove_zone_posts` used to pass their first argument as whatever the caller gave: one post ID, an array of IDs, or `WP_Post` objects. They now always pass an array of post IDs. If your callback handled each form, you can drop that; if it expected `WP_Post` objects, call `get_post()` on each ID. The second argument is still the zone's `WP_Term`.

A post listed twice when a zone's posts are set now keeps its first place, where it used to end up in its last.

### The widget's cache filter has a new name

`zone_posts_widget_block_save_cache_seconds` is now [`zoninator_zone_posts_widget_block_save_cache_seconds`](hooks.md#zoninator_zone_posts_widget_block_save_cache_seconds-filter), with the prefix every other Zoninator hook uses. The old name no longer fires, so rename it in your `add_filter()` call.

The hooks that clear the cache have moved too. Each widget object used to add them to itself when it was built. Now one object adds them when Zoninator boots: `array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Widgets::class ), 'flush_cache' )`, on `save_post`, `deleted_post`, `switch_theme`, `zoninator_add_zone_posts`, `zoninator_remove_zone_posts` and `edited_zoninator_zones`. If you extend the widget and override `flush_widget_cache()`, those hooks no longer call your method. Hook it yourself, or call `flush_widget_cache()` where you need it.

### Zoninator no longer handles shared term splits

Before WordPress 4.2, a zone could share its term with a term in another taxonomy. WordPress 4.2 and 4.3 split those terms, and Zoninator moved the zone's posts to the zone's new ID when that happened. Every site that can run 1.0.0 split its shared terms long ago, so the handler is removed.

The `zoninator_split_shared_term` and `zoninator_did_split_shared_term` actions went with it and no longer fire. Remove any callbacks you added to them.

## To 0.12.0

**Going straight to 1.0.0?** Read [To 1.0.0](#to-100) first. This section tells you to call `Zoninator()` in places, and 1.0.0 removes it; where it does, use the replacement in [The `Zoninator` class and `Zoninator()` are gone](#the-zoninator-class-and-zoninator-are-gone).

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
| `$key` | `Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenLocation::SLUG` | can't be changed |
| `$zone_nonce_prefix` | `Zoninator()->_get_nonce_key()` | can't be changed |
| `$zone_ajax_nonce_action` | `Automattic\Zoninator\Admin\Ajax::NONCE_ACTION` | can't be changed |
| `$zone_term_prefix` | Zoninator never used it; use your own value | — |

The work behind `Zoninator` now lives in classes under `Automattic\Zoninator\`. They're `final` and internal, so keep using `Zoninator()`, the template tags and the hooks. Zoninator's hooks are still registered on the `Zoninator` object, so `remove_action( 'init', array( Zoninator(), 'init' ), 99 )` and the like keep working. 1.0.0 [moves most of them](#hooks-are-registered-on-the-objects-that-handle-them).

### The REST API runs on core's `WP_REST_Controller`

Zoninator no longer bundles the Mixtape REST library. The routes, their arguments and their response bodies are the same, except for the changes below. Code that used the library directly needs updating:

- The `Zoninator_Api`, `Zoninator_Api_Controller`, `Zoninator_Api_Filter_Search`, `Zoninator_Api_Schema_Converter` and `Zoninator_REST_*` classes are gone.
- The `mixtape_*`, `mt_*` and `rest_api_schema_properties` hooks are gone. To change a route, use core's REST hooks, such as `rest_endpoints` or `rest_pre_dispatch`.
- `Zoninator()->rest_api` is now the REST controller, set on `rest_api_init`, so it's `null` before then. (1.0.0 removes it.)

What changes for REST clients:

- A request refused by a capability check gets `401` when logged out and `403` when logged in, with the code `permission-denied`. It used to get `500`.
- Every error has a `code` alongside its `message`, in core's REST error shape. The `400` and `404` responses used to have only a `message`. A zone locked by someone else gives `zone-locked`, and its response keeps the `zone_id` and `blocked` it had before. (1.0.0 [moves them under `data`](#the-locked-zone-error-keeps-its-details-under-data).) See [Errors](rest-api.md#errors) for the codes.
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
