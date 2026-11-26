# Upgrading to 1.0

This guide is for sites on 0.12.x. If you're on 0.11.x or earlier, read [Upgrading to 0.12](upgrading-to-0.12.md) first, then this.

1.0.0 is a breaking release. Old names are removed outright, so code that still uses one stops with a fatal error instead of a notice. Test the update on a staging site first.

Your zones, the posts in them, and your Zone Posts widgets carry over unchanged. There's no data migration.

## Before you update

Zoninator 1.0.0 requires **WordPress 6.9** and **PHP 8.2**, up from WordPress 6.4 and PHP 7.4. WordPress won't offer 1.0.0 to a site that doesn't meet both, so that site stays on 0.12.x. Update WordPress and PHP first.

Then work out which of the changes below affect your site:

| If your site's code… | Read |
|---|---|
| calls a `z_` function, such as `z_get_posts_in_zone()`. Most themes that show zones do | [The template tags have the `zoninator_` prefix](#the-template-tags-have-the-zoninator_-prefix) |
| calls `Zoninator()`, or uses the `$zoninator` global | [The `Zoninator` class is gone](#the-zoninator-class-is-gone) |
| removes one of Zoninator's callbacks with `remove_action()` or `remove_filter()` | [Hooks are registered on the objects that handle them](#hooks-are-registered-on-the-objects-that-handle-them) |
| hooks `zoninator_pre_init` or `zoninator_post_init`, or loads Zoninator in an unusual way | [Zoninator boots on `plugins_loaded`](#zoninator-boots-on-plugins_loaded) |
| hooks `zoninator_add_zone_posts` or `zoninator_remove_zone_posts` | [The zone post actions always pass post IDs](#the-zone-post-actions-always-pass-post-ids) |
| hooks `zoninator_split_shared_term` or `zoninator_did_split_shared_term` | [Shared term splits are no longer handled](#shared-term-splits-are-no-longer-handled) |
| prints, removes or extends the Zone Posts widget, or filters its HTML or cache | [The Zone Posts widget's class has a new name](#the-zone-posts-widgets-class-has-a-new-name) and [The widget no longer caches its output](#the-widget-no-longer-caches-its-output) |
| changes zones over the REST API | [REST API changes](#rest-api-changes) |
| calls the Zones screen's admin-ajax actions, or customizes the screen's JavaScript | [The admin-ajax actions are gone](#the-admin-ajax-actions-are-gone) |
| has a zone that holds, or needs to hold, more than 100 posts | [A zone holds up to 100 posts](#a-zone-holds-up-to-100-posts) |

A site that only uses the Zones screen, the Zone Posts widget and the template tags in a theme needs the first change in the table, and nothing else.

## The template tags have the `zoninator_` prefix

The template tags began with `z_`, which was too short a prefix to be safe from clashes. They now begin with `zoninator_`. They take the same arguments and return the same values.

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
| `z_get_loop_post_id_or_default()` | `get_the_ID()` |
| `z_get_zoninator()` | Nothing; see the next section |

A search for `z_get_` and `z_disable_` across your theme and plugins finds every call.

## The `Zoninator` class is gone

`Zoninator` did a little of everything, so nothing said which of its methods were the plugin's public API. 1.0.0 removes it, along with the `Zoninator()` function, the `$zoninator` global and `z_get_zoninator()`. Code that calls any of them stops with a fatal error.

- To read zones, use the [template tags](theme-developers.md).
- To change them, use the classes described in [Changing zones from PHP](php-api.md).

[Replacing the `Zoninator` class](replacing-zoninator-class.md) gives the replacement for each of its methods and properties.

## Hooks are registered on the objects that handle them

In 0.12.x every Zoninator hook was registered on the `Zoninator` object. In 1.0.0 each is registered on the object that does the work.

**Check your `remove_action()` and `remove_filter()` calls.** One that names an old callback with `Zoninator()`, such as `array( Zoninator(), 'do_zoninator_feeds' )`, stops with a fatal error. One that uses the `$zoninator` global gives no error, but removes nothing, so the callback keeps running. Replace it with the callback in the last column, which you get from `zoninator_container()`. [Unhooking Zoninator's callbacks](php-api.md#unhooking-zoninators-callbacks) shows how, and when to call it.

| Hook | 0.12.x callback on `Zoninator()` | 1.0.0 callback, under `Automattic\Zoninator\Infrastructure\WordPress\` |
|---|---|---|
| `init` | `init`, at priority 99, which registered the zone taxonomy | `Taxonomy::register`, at priority 10 |
| `init` | `add_zone_feed` | `FeedEndpoint::add_rewrite_rule` |
| `template_redirect` | `do_zoninator_feeds` | `FeedEndpoint::serve` |
| `rest_api_init` | `register_rest_routes` | `register_routes` on each of `Rest\ZonesController`, `Rest\ZonePostsController`, `Rest\SearchController`, `Rest\LockController` and `Admin\ZonePostRowController` |
| `widgets_init` | `widgets_init` | `Widgets::register` |
| `split_shared_term` | `split_shared_term` | Nothing; see [Shared term splits are no longer handled](#shared-term-splits-are-no-longer-handled) |
| `admin_init` | `admin_controller` | `Admin\ZoneForm::handle_request` |
| `admin_init` | `admin_init`, which added the two `admin_enqueue_scripts` callbacks | Nothing; those are added directly |
| `admin_enqueue_scripts` | `admin_enqueue_scripts` and `admin_enqueue_styles` | `Admin\Assets::enqueue_scripts` and `Admin\Assets::enqueue_styles` |
| `admin_menu` | `admin_page_init` | `Admin\Screen::register` |
| `toplevel_page_zoninator` | `admin_page` | `Admin\Screen::render` |
| `zoninator_advanced_search_fields` | `zone_advanced_search_cat_filter` | `Admin\SearchForm::render_category_filter` |
| `zoninator_advanced_search_fields` | `zone_advanced_search_date_filter`, at priority 20 | `Admin\SearchForm::render_date_filter`, at priority 20 |
| `zoninator_zone_post_columns` (the default columns) | `admin_page_zone_post_col_position` and `admin_page_zone_post_col_info` | `Admin\ZonePostRow::render_position_column` and `Admin\ZonePostRow::render_info_column` |

The admin callbacks used to be added on `init` at priority 99. They're now added when Zoninator boots, with the rest.

The [`zoninator_current_user_can_edit_zone`](hooks.md#zoninator_current_user_can_edit_zone-filter) filter has a third argument, the ID of the user being asked about. A callback that takes two keeps working, but if it calls `get_current_user_id()`, use the third argument instead: `GuardedZoneManager` can ask about a user who isn't the one logged in.

## Zoninator boots on `plugins_loaded`

Zoninator used to build itself as soon as WordPress loaded its file, and finish setting up on `init` at priority 99. It now builds its classes and adds its hooks on `plugins_loaded`, at priority 10. Theme code, and anything on `init` or later, sees no difference.

- The `zoninator_pre_init` and `zoninator_post_init` actions are removed. Hook `init` instead, at whatever priority you need.
- The settings filters, such as `zoninator_zone_lock_period`, apply each time Zoninator reads the value, so a filter added at any point before then counts. They used to apply once, on `init`.
- Posts support zones from the moment Zoninator boots, and `zoninator_zones` support can be added to or removed from a post type at any point; see [Which post types support zones](php-api.md#which-post-types-support-zones).
- The `zoninator_zones` taxonomy registers on `init` at the default priority, with no post types attached. A post's place in a zone has always been post meta, so nothing else changes, but `get_object_taxonomies( 'post' )` no longer lists it.
- Calling `zoninator_container()` or a template tag before `plugins_loaded`, from a mu-plugin or another plugin's main file, boots Zoninator there and then. A theme that loads Zoninator from its `functions.php`, after `plugins_loaded`, boots it straight away.

## The zone post actions always pass post IDs

`zoninator_add_zone_posts` and `zoninator_remove_zone_posts` used to pass their first argument as the caller gave it: one post ID, an array of IDs, or `WP_Post` objects. They now always pass an array of post IDs. If your callback handled each form, you can drop that. If it expected `WP_Post` objects, call `get_post()` on each ID. The second argument is still the zone's `WP_Term`.

Three smaller changes, for callbacks that look closely at the zone:

- Adding a post that's already in the zone leaves it where it is. `zoninator_add_zone_posts` passes only the posts that were added, and doesn't fire if every post was in the zone already. In 0.12.x it fired with every post given, and moved the zone's first post to the end if it was one of them.
- When a zone's posts are replaced, `zoninator_remove_zone_posts` still passes every post that was in the zone. But the posts staying in the zone keep their place while it runs; in 0.12.x the zone was empty at that point.
- Deleting a zone also releases its lock.

## Shared term splits are no longer handled

Before WordPress 4.2, a zone could share its term with a term in another taxonomy. WordPress 4.2 and 4.3 split those terms, and Zoninator moved the zone's posts to the zone's new ID. Every site that can run 1.0.0 split its shared terms long ago, so the handler is removed. The `zoninator_split_shared_term` and `zoninator_did_split_shared_term` actions went with it and no longer fire. Remove any callbacks you added to them.

## The Zone Posts widget's class has a new name

`Zoninator_ZonePosts_Widget` is now `Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget`, and the old name no longer exists.

Widgets already in your sidebars are untouched. The widget keeps its ID base, `zoninator_zoneposts_widget`, so each one stays where it is with the same settings. Only code that names the class needs to change:

| 0.12.x | 1.0.0 |
|---|---|
| `the_widget( 'Zoninator_ZonePosts_Widget', $instance )` | `the_widget( \Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget::class, $instance )` |
| `unregister_widget( 'Zoninator_ZonePosts_Widget' )` | `unregister_widget( \Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget::class )` |
| `class My_Zone_Widget extends Zoninator_ZonePosts_Widget` | `class My_Zone_Widget extends \Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget` |

**Two of these fail without an error.** `the_widget()` given the old name prints nothing, with only a `_doing_it_wrong()` notice, and `unregister_widget()` given the old name leaves the widget registered. A class that extends the old name stops with a fatal error. A search for `Zoninator_ZonePosts_Widget` across your theme and plugins finds every use.

The class loads itself when it's first used. If your code loads `src/class-zoninator-zoneposts-widget.php` with `require`, remove that line.

## The widget no longer caches its output

The widget used to keep its HTML in the object cache, and clear it when a post or zone changed. WordPress caches the zone's query, which is the costly part, and clears that itself, so the widget now builds its output on each page view.

| Removed | What to do |
|---|---|
| The `zone_posts_widget_block_save_cache_seconds` filter | Remove your `add_filter()` call |
| `Zoninator_ZonePosts_Widget::flush_widget_cache()` | Remove the call, and any override of it in a subclass |
| The `widget-zone-posts` and `widget-zone-posts-save_blocked` keys in the `widget` cache group | Nothing; they expire, or go when the cache is next flushed |

If you use [`zoninator_zone_posts_widget_html`](hooks.md#zoninator_zone_posts_widget_html-filter), your callback now runs on every page view that shows the widget. If it does costly work, such as a query of its own, cache its result in the callback.

## REST API changes

The routes and their arguments are unchanged. What a client sees has changed in five ways.

**Zone locks stop every change.** In 0.12.x a zone's lock only kept a second person out of the zone on the Zones screen, and the REST API saved whatever it was sent. Now every route that changes a zone or its posts is refused with `400` and the code `zone-locked` while someone else holds the zone's lock, and the message names them. Retry later, or have the client take the lock first with `PUT /zoninator/v1/zones/{zone_id}/lock`. Requests made as the lock holder go through.

**The locked zone error keeps its details under `data`.** Its `zone_id` and `blocked` used to sit beside `code` and `message`. They're now under `data`, where core's REST errors keep their details:

```json
{
  "code": "zone-locked",
  "message": "Sorry, this zone is in use by Jane Doe and is currently locked. Please try again later.",
  "data": { "status": 400, "zone_id": 12, "blocked": true }
}
```

If your client reads `zone_id` or `blocked` from the top of the response, read `data.zone_id` and `data.blocked`, or match on the `code`.

**Adding a post returns no HTML.** `POST /zoninator/v1/zones/{zone_id}/posts/{post_id}` returned the post's row for the Zones screen, as `html`. It now returns `{ "success": true }`, like the other routes that change a zone. If your client used the `html`, read the zone's posts with `GET /zoninator/v1/zones/{zone_id}/posts` instead.

**Listing the zones needs more than a login.** `GET /zoninator/v1/zones` answered any logged-in user, subscribers included. It now needs a user who can edit posts, which covers everyone who can use the block editor's zone picker, or who can curate zones. Anyone else gets `403` with the code `rest_forbidden`. To open the list to more users, or to anyone, use the [`zoninator_rest_get_zones_permissions_check`](hooks.md#zoninator_rest_get_zones_permissions_check-filter) filter.

**A full zone is refused.** A change that would take a zone past its post limit gets `400` with the code `zone-full`; see [A zone holds up to 100 posts](#a-zone-holds-up-to-100-posts).

The search's `date` argument must now be a date alone, as `Y-m-d`. A value with more in it, such as `2026-01-01 10:00:00`, used to filter by its date, and is now ignored.

The [REST API reference](rest-api.md) describes every route.

## The admin-ajax actions are gone

The six admin-ajax actions that 0.12.0 deprecated are removed. A request to one gets admin-ajax's `0` response, with a `400` status. Use the REST routes instead:

| admin-ajax action | REST route |
|---|---|
| `zoninator_add_post` | `POST /zoninator/v1/zones/{zone_id}/posts/{post_id}` |
| `zoninator_remove_post` | `DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}` |
| `zoninator_reorder_posts` | `PUT /zoninator/v1/zones/{zone_id}/posts` |
| `zoninator_search_posts` | `GET /zoninator/v1/search` |
| `zoninator_update_recent` | `GET /zoninator/v1/search` with no `term` |
| `zoninator_update_lock` | `PUT /zoninator/v1/zones/{zone_id}/lock` |

If your code unhooked one, with `remove_action( 'wp_ajax_zoninator_add_post', … )` or similar, delete that line: there's nothing left to unhook.

On the Zones screen, the hidden `zone-nonce-ajax-action` nonce field and the `ajaxNonceAction` entry in `zoninatorOptions` are gone, and so are the `zoninator.ajax()`, `zoninator.getAjaxNonce()`, `zoninator.getAjaxNonceField()` and `zoninator.updateAjaxNonce()` JavaScript functions. The screen still fires the `zoninator.ajax` jQuery event on `#zone-post-search` before each REST request, with the action name (such as `add_post`) and the request data, so listeners on it keep working.

The zone form's own nonce has changed too. It's sent in a `zoninator_nonce` field, not `_wpnonce`, and the nonce to change or delete a zone is for that zone only: its action ends with the zone's ID, as in `zone-nonce-update-12` and `zone-nonce-delete-12`. A new zone's is still `zone-nonce-insert`. If your code builds its own link or form that submits to the Zones screen, make its nonce the same way.

## A zone holds up to 100 posts

A zone had no limit on how many posts it held, and every list of a zone's posts reads them all: the template tags, the widget, the block and the JSON feed. A zone that editors only ever added to grew with every post, and so did each page that showed it. A zone now holds up to 100 posts. Adding past that is refused, on the Zones screen, over the REST API and through the abilities.

A zone that already holds more keeps every post. Editors can still reorder it and take posts out, but it takes no new ones until it's back under 100. To keep the old behavior, or set another limit, use the [`zoninator_post_count_limit`](hooks.md#zoninator_post_count_limit-filter) filter:

```php
add_filter( 'zoninator_post_count_limit', '__return_zero' );
```

## What's new

None of these needs any action:

- The [Zone Posts block](theme-developers.md#the-zone-posts-block) shows a zone in block themes and the block editor.
- [Abilities](abilities.md) let agents and other tools read zones and change their posts.
- [Changing zones from PHP](php-api.md) describes a supported PHP API for creating zones and changing their posts.
- The [JSON feed](theme-developers.md#the-json-feed) of an empty zone sends `[]`. In 0.12.x the request fell through to the theme, which showed an HTML page.
- When saving a zone's name or description fails, the Zones screen shows the error in red, where 0.12.x showed it as a success.
