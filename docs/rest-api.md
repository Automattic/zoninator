# REST API

Zoninator registers a single namespace, `zoninator/v1`, in `src/rest/class-zones-controller.php`. All endpoints sit under `/wp-json/zoninator/v1/`.

There is also a non-REST JSON feed at `/zones/{slug}/feed.json` — see the [theme developer guide](theme-developers.md) for that.

## Authentication

All endpoints require an authenticated request by default. Use any standard WordPress REST authentication mechanism: cookie + `_wpnonce` for first-party JavaScript, application passwords for server-to-server, JWT or OAuth via plugins, etc.

The `GET /zones` index endpoint requires authentication as of 0.11.0. Sites that depend on the previous anonymous behaviour can opt back in with the `zoninator_rest_get_zones_permissions_check` filter — see [hooks.md](hooks.md#zoninator_rest_get_zones_permissions_check).

The remaining endpoints have always required appropriate `edit_others_posts`-equivalent capabilities; see [hooks.md](hooks.md#capabilities) for the filters that govern them.

## Endpoints

### `GET /zoninator/v1/zones`

Returns every zone on the site.

**Permission:** logged-in by default; filter via `zoninator_rest_get_zones_permissions_check`.

**Response:** an array of zone objects.

```json
[
  {
    "term_id": 12,
    "name": "Homepage",
    "slug": "homepage",
    "description": "..."
  }
]
```

### `POST /zoninator/v1/zones`

Creates a new zone.

**Permission:** `zoninator_add_zone_cap` (defaults to `edit_others_posts`).

**Body**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `name` | string | no | Display name. Defaults to `slug` if omitted. |
| `slug` | string | no | URL-safe identifier. Defaults to `name`. |
| `description` | string | no | Free text. |

**Response:** the created zone, with status `201`.

### `PUT /zoninator/v1/zones/{zone_id}`

Updates a zone.

**Permission:** `zoninator_edit_zone_cap`, plus the per-zone `zoninator_current_user_can_edit_zone` filter.

**Body** — same fields as `POST /zones`. Only the fields you send are updated.

**Response:** `{ "success": true }` on success, `404` if the zone does not exist.

### `DELETE /zoninator/v1/zones/{zone_id}`

Deletes a zone. The posts themselves are not affected — only the zone-to-post relationships.

**Permission:** `zoninator_edit_zone_cap`.

**Response:** `{ "success": true }` on success.

### `GET /zoninator/v1/zones/{zone_id}/posts`

Returns the posts assigned to a zone, in zone order.

**Permission:** publicly accessible.

**Response:** array of post objects, with the fields whitelisted by the `zoninator_zone_feed_fields` filter (by default: `ID`, `post_date`, `post_title`, `post_content`, `post_excerpt`, `post_status`, `guid`). Content and excerpt are blanked for password-protected posts.

### `PUT /zoninator/v1/zones/{zone_id}/posts`

Replaces the post list for a zone with the supplied IDs. The Zones screen saves the order this way.

**Permission:** `zoninator_manage_zone_cap`. Before 0.12.0 it was `zoninator_edit_zone_cap`, with the `zoninator_current_user_can_edit_zone` filter.

**Body**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `post_ids` | int[] | yes | Ordered list of post IDs to assign to the zone. |

**Response:** `{ "success": true }` on success, `400` if any ID is not a post of a zone post type that the user can read, `404` if the zone does not exist.

### `POST /zoninator/v1/zones/{zone_id}/posts/{post_id}`

Adds a post to the end of a zone. Since 0.12.0.

**Permission:** `zoninator_manage_zone_cap`.

**Response:** `201` with the post's row for the Zones screen, rendered by the [`zoninator_zone_post_columns`](hooks.md) callbacks:

```json
{
  "html": "<div id=\"zone-post-42\" class=\"zone-post\" …>…</div>"
}
```

`400` if the post isn't of a zone post type that the user can read, `404` if the zone does not exist.

### `DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}`

Removes a post from a zone. Since 0.12.0.

**Permission:** `zoninator_manage_zone_cap`.

**Response:** `{ "success": true }` on success, `400` if the post isn't of a zone post type that the user can read, `404` if the zone does not exist.

### `GET /zoninator/v1/search`

Finds posts to add to a zone. With no `term`, lists the latest content. Since 0.12.0.

**Permission:** `zoninator_manage_zone_cap`.

**Query**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `term` | string | no | Search term. Leave it out for the latest content. |
| `cat` | int | no | Category ID to filter by. |
| `date` | string | no | Date to filter by, as `Y-m-d`. |
| `exclude` | int[] | no | Post IDs to leave out, usually those already in the zone. |
| `limit` | int | no | Number of search results, from 1 to 100. Defaults to the `zoninator_posts_per_page` value. The latest content ignores it. |

The search applies the [content search filters](hooks.md#content-search). Published and scheduled posts are included.

**Response:** an array of results.

```json
[
  {
    "title": "Hello world",
    "post_id": 42,
    "date": "November 22, 2026",
    "post_type": "post",
    "post_type_label": "Post",
    "post_status": "publish",
    "post_status_label": "Published"
  }
]
```

### `PUT /zoninator/v1/zones/{zone_id}/lock`

Acquires (or extends) the editing lock for a zone. The Zones screen calls it every `zoninator_zone_lock_period` seconds, so two people can't curate the same zone at once.

**Permission:** `zoninator_manage_zone_cap`. Before 0.12.0 it was `zoninator_edit_zone_cap`, with the `zoninator_current_user_can_edit_zone` filter.

**Response on success:** `200`

```json
{
  "zone_id": 12,
  "timeout": 30,
  "max_lock_period": 600
}
```

`timeout` and `max_lock_period` are filterable via [`zoninator_zone_lock_period`](hooks.md#zoninator_zone_lock_period) and [`zoninator_zone_max_lock_period`](hooks.md#zoninator_zone_max_lock_period).

**Response when the zone is already locked:** `400`

```json
{
  "zone_id": 12,
  "blocked": true
}
```

Look up the holder via the WordPress users API if you need to display them.

## Errors

Errors follow the standard `WP_REST_Response` shape with a top-level `message` field describing the problem in the site's language. Common codes are documented as constants on `Automattic\Zoninator\REST\Zones_Controller`:

| Constant | Meaning |
|---|---|
| `INVALID_ZONE_ID` / `ZONE_NOT_FOUND` | The path's `{zone_id}` does not resolve. |
| `INVALID_POST_ID` / `POST_NOT_FOUND` | One of the supplied post IDs does not exist. |
| `ZONE_ID_REQUIRED` / `ZONE_ID_POST_ID_REQUIRED` / `ZONE_ID_POST_IDS_REQUIRED` | A required identifier is missing. |
| `ZONE_FEED_ERROR` | The zone exists but its feed couldn't be assembled. |
| `PERMISSION_DENIED` | Caller failed the relevant capability check. Returned with HTTP 401 when logged out and 403 when logged in. |
| `INVALID_ZONE_SETTINGS` | Submitted zone arguments are malformed. |

## Implementation notes

- The endpoints are registered by `Automattic\Zoninator\REST\Zones_Controller` (`src/rest/class-zones-controller.php`), which extends core's `WP_REST_Controller`. The file loads only on `rest_api_init`.
- The update routes (`/zones/{zone_id}`, `/zones/{zone_id}/posts` and `/zones/{zone_id}/lock`) accept `POST`, `PUT` and `PATCH`.
- The Zones screen uses these routes through `wp.apiFetch()`. Its `zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts` and `zoninator_update_lock` admin-ajax actions are deprecated since 0.12.0, and go in 1.0.0.
- A lock is a transient keyed against the zone, holding the locking user's ID. It survives page reloads, and expires when `zoninator_zone_lock_period` lapses without a renewal.

## See also

- [hooks.md](hooks.md) — every filter and action exposed by the plugin, including the REST permissions filter.
- [theme-developers.md](theme-developers.md) — the template-tag API for use inside themes.
