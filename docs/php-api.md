# Changing zones from PHP

To read zones in a theme, use the [template tags](theme-developers.md). To create, change or delete zones, or change the posts in them, use the classes under `Automattic\Zoninator\Application\`. This page covers those classes, and how to unhook Zoninator's own callbacks.

## Getting the classes

`zoninator_container()` returns the container that builds Zoninator's classes. Its `get()` takes a class name and returns that class's object:

```php
use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneManager;

$zone = zoninator_container()->get( ZoneManager::class )->create( 'homepage', 'Home page' );

zoninator_container()->get( ZoneCurator::class )->set_posts( $zone->get_id(), array( 12, 34, 56 ) );
```

Zoninator boots on `plugins_loaded`, at priority 10. Calling `zoninator_container()` or a template tag before then, such as from a mu-plugin, boots it there and then.

## The classes

| Class | Methods | What it does |
|---|---|---|
| `ZoneManager` | `create( $slug, $name = '', $details = array() )`, `update( $zone_id, $changes )`, `remove( $zone_id )` | Creates, changes and deletes zones. `$changes` can hold `name`, `slug` and `details`; anything left out, or empty, stays as it is. |
| `ZoneCurator` | `add_posts( $zone_id, $post_ids )`, `set_posts( $zone_id, $post_ids )`, `remove_posts( $zone_id, $post_ids )`, `clear_posts( $zone_id )` | Changes the posts in a zone. |
| `GuardedZoneManager` | The same as `ZoneManager`, with the user's ID first | Makes the change for a user, after the checks below. |
| `GuardedZoneCurator` | The same as `ZoneCurator`, apart from `clear_posts()`, with the user's ID first | Makes the change for a user, after the checks below. |
| `ZoneLocker` | `lock( $zone_id, $user_id )`, `unlock( $zone_id )`, `get_blocker_id( $zone_id, $user_id )`, `check_unlocked( $zone_id, $user_id )` | Takes, renews and releases a zone's [lock](#zone-locks). |

`ZoneManager` and `ZoneCurator` make no checks. Use them for code that isn't acting for a user, such as a scheduled job. For anything a user asked for, use the `Guarded` classes. They make the same checks as the Zones screen and the [REST API](rest-api.md):

- The user has the capability for the change: [`zoninator_add_zone_cap`](hooks.md#zoninator_add_zone_cap-filter) to create a zone, [`zoninator_edit_zone_cap`](hooks.md#zoninator_edit_zone_cap-filter) and [`zoninator_current_user_can_edit_zone`](hooks.md#zoninator_current_user_can_edit_zone-filter) to change or delete one, and [`zoninator_manage_zone_cap`](hooks.md#zoninator_manage_zone_cap-filter) to change its posts.
- Each post is of a post type that supports zones, and the user can read it.
- No one else holds the zone's lock.

### Zones and posts are IDs

Every method takes a zone's ID, not its slug or term. `zoninator_get_zone( 'homepage' )->term_id` gets the ID.

Posts are IDs too. A `WP_Post` or anything else that isn't an integer, or a string of digits, throws an `InvalidArgumentException`, so pass `wp_list_pluck( $posts, 'ID' )`.

### What they return

`create()` and `update()` return an `Automattic\Zoninator\Domain\Zone`, with `get_id()`, `get_slug()`, `get_name()`, `get_description()` and `get_details()`. The other methods return nothing.

Give a zone's name and details as plain text. Zoninator encodes them for storage, so don't call `esc_html()` or `htmlspecialchars()` on them first, or they're encoded twice. A `Zone` gives plain text back. The template tags give the stored text, in which `&`, `<` and `>` are encoded.

### How the posts change

- `add_posts()` adds the posts to the end of the zone, in the order given. A post already in the zone keeps its place.
- `set_posts()` makes the zone hold these posts and no others, in the order given. A post listed twice keeps its first place. Use it to reorder a zone.
- `remove_posts()` takes the posts out of the zone. The posts themselves aren't changed.
- `clear_posts()` takes every post out of the zone.

A zone holds up to 100 posts. A change that would take it past its [limit](hooks.md#zoninator_post_count_limit-filter) is refused. A zone that already holds more keeps its posts, and can be reordered or trimmed, but takes no more.

Each change fires [`zoninator_add_zone_posts`](hooks.md#zoninator_add_zone_posts-action) or [`zoninator_remove_zone_posts`](hooks.md#zoninator_remove_zone_posts-action), or both.

### Exceptions

When a change is refused, the method throws an exception from `Automattic\Zoninator\Domain\`, and nothing is changed:

| Exception | When |
|---|---|
| `InvalidZoneException` | A new zone has no slug. |
| `ZoneNotFoundException` | There's no zone with that ID. |
| `ZoneNotSavedException` | WordPress refused to save or delete the zone, for example because another zone has its name. `get_error_code()` gives WordPress's error code. |
| `ZoneFullException` | The zone would hold more posts than its limit. |
| `ZoneLockedException` | Someone else holds the zone's lock. `get_holder_id()` gives their user ID. |
| `AccessDeniedException` | The user doesn't have the capability. `Guarded` classes only. |
| `PostNotAllowedException` | A post isn't of a post type that supports zones, or the user can't read it. `Guarded` classes only. |

All seven implement `Automattic\Zoninator\Domain\ZoninatorExceptionInterface`, so `catch ( ZoninatorExceptionInterface $exception )` covers every way a change can be refused.

```php
use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;

try {
	zoninator_container()->get( GuardedZoneCurator::class )->add_posts( get_current_user_id(), $zone_id, array( $post_id ) );
} catch ( ZoninatorExceptionInterface $exception ) {
	// Tell the user it didn't work.
}
```

## Zone locks

The Zones screen locks a zone while someone edits it, so two people can't change it at once. A lock lasts [`zoninator_zone_lock_period`](hooks.md#zoninator_zone_lock_period-filter) seconds unless it's renewed. The screen renews it that often, until the editor has made no change for [`zoninator_zone_max_lock_period`](hooks.md#zoninator_zone_max_lock_period-filter) seconds. Every change to the zone, from the screen, the REST API, the abilities or the `Guarded` classes, is refused while someone else holds its lock.

- `lock()` locks a zone for a user, or renews the lock they hold. It throws `ZoneLockedException` if someone else holds the lock.
- `unlock()` releases the lock, whoever holds it. To take a zone over, call `unlock()` and then `lock()`.
- `get_blocker_id()` gives the ID of the user whose lock keeps this user out, or `null` if there's none.
- `check_unlocked()` throws `ZoneLockedException` if someone else's lock keeps this user out.

Deleting a zone releases its lock.

## Which post types support zones

Posts support zones. To add a post type, call `add_post_type_support( 'page', 'zoninator_zones' )`, or add `zoninator_zones` to the post type's `supports` when you register it. To take zones away from posts, call `remove_post_type_support( 'post', 'zoninator_zones' )`. Both work at any point.

`get_post_types_by_support( 'zoninator_zones' )` lists the post types that support zones.

## How zones are stored

A zone is a term in the `zoninator_zones` taxonomy. Its details are stored, serialized, in the term's description. A post's place in a zone is post meta, under the key `_zoninator_order_` followed by the zone's ID, so `get_post_meta( $post_id, '_zoninator_order_' . $zone_id, true )` gives its position. The taxonomy has no post types attached, and grants none of core's term capabilities, so zones can only be changed through Zoninator.

## Unhooking Zoninator's callbacks

Each of Zoninator's hooks is registered on the object that handles it. To stop one running, get that object from the container and remove it, on `plugins_loaded` at priority 20:

```php
use Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint;

add_action(
	'plugins_loaded',
	function () {
		remove_action( 'template_redirect', array( zoninator_container()->get( FeedEndpoint::class ), 'serve' ) );
	},
	20
);
```

The one exception is `toplevel_page_zoninator`, which is added on `admin_menu`: remove it on `admin_menu` at priority 11 or later.

`FeedEndpoint::activate()`, on `activate_zoninator/zoninator.php`, adds the JSON feed's URL and flushes the rewrite rules when the plugin is activated. You can't remove it on `plugins_loaded`, as WordPress loads the plugin and activates it in the same request, after `plugins_loaded` has fired. The activation and deactivation hooks are named after the plugin's folder and file, so if your copy isn't in a `zoninator` folder, use your folder's name.

| Hook | Class, under `Automattic\Zoninator\Infrastructure\WordPress\` | Method | What it does |
|---|---|---|---|
| `init` | `Taxonomy` | `register` | Registers the zone taxonomy. |
| `init` | `FeedEndpoint` | `add_rewrite_rule` | Adds the [JSON feed](theme-developers.md#the-json-feed)'s URL. |
| `template_redirect` | `FeedEndpoint` | `serve` | Sends the JSON feed. |
| `deactivate_zoninator/zoninator.php` | `FeedEndpoint` | `deactivate` | Removes the JSON feed's URL and flushes the rewrite rules, when the plugin is deactivated. |
| `rest_api_init` | `Rest\ZonesController`, `Rest\ZonePostsController`, `Rest\SearchController`, `Rest\LockController` | `register_routes` | Registers the [REST API](rest-api.md), one controller for each resource. |
| `rest_api_init` | `Admin\ZonePostRowController` | `register_routes` | Registers the route the Zones screen gets each post's row from. The screen needs it. |
| `widgets_init` | `Widgets` | `register` | Registers the [Zone Posts widget](theme-developers.md#the-zone-posts-widget). Or call `unregister_widget( ZonePostsWidget::class )` on `widgets_init` at priority 11. |
| `query_loop_block_query_vars` and `rest_post_query` | `Block` | `filter_query_vars` and `filter_rest_query` | Run the [Zone Posts block](theme-developers.md#the-zone-posts-block)'s query, on the front end and in the editor. |
| `enqueue_block_editor_assets` | `Block` | `enqueue_editor_assets` | Adds the Zone Posts block to the editor. |
| `wp_abilities_api_categories_init` | `Abilities` | `register_category` | Registers the `zoninator` [abilities](abilities.md) category. |
| `wp_abilities_api_init` | `Abilities` and `CurationAbilities` | `register` | Registers the reading abilities, and those that change a zone's posts. |
| `admin_menu` | `Admin\Screen` | `register` | Adds the Zones screen to the menu. |
| `toplevel_page_zoninator` | `Admin\Screen` | `render` | Prints the Zones screen. |
| `admin_init` | `Admin\ZoneForm` | `handle_request` | Saves the Zones screen's zone form. |
| `admin_enqueue_scripts` | `Admin\Assets` | `enqueue_scripts` and `enqueue_styles` | Loads the Zones screen's JavaScript and CSS. |
| `zoninator_advanced_search_fields` | `Admin\SearchForm` | `render_category_filter`, and `render_date_filter` at priority 20 | Prints the search's category and date filters. |

The default columns in [`zoninator_zone_post_columns`](hooks.md#zoninator_zone_post_columns-filter) are `render_position_column` and `render_info_column` on `Admin\ZonePostRow`.

Use these objects only to unhook their callbacks. The classes under `Automattic\Zoninator\Infrastructure\` are `final` and internal, apart from the container, the Zone Posts widget and the REST API's `ErrorCodes`, so their other methods can change in any release.
