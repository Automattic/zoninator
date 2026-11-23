# Upgrading

What to check when you update Zoninator, release by release. Stored zones and their posts carry over unchanged: no release here needs a data migration.

For the full list of changes in each release, see the [CHANGELOG](../CHANGELOG.md).

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
- `PUT /zones/{zone_id}/posts` and `PUT /zones/{zone_id}/lock` check `zoninator_manage_zone_cap`, the capability the Zones screen has always needed to curate a zone. They used to check `zoninator_edit_zone_cap` and the `zoninator_current_user_can_edit_zone` filter. If you used that filter to stop some users changing a zone's posts over REST, it no longer does; the Zones screen never honored it for that either.
- Malformed input, such as a non-numeric post ID, gets a `400` instead of a PHP fatal error.

See the [REST API reference](rest-api.md) for the new routes the Zones screen uses.

### The Zones screen uses the REST API

The Zones screen now adds, removes, reorders and searches for posts, and takes the zone lock, through the REST API with `wp.apiFetch()`. These admin-ajax actions, and the `Zoninator` methods behind them, are deprecated and go in 1.0.0:

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
