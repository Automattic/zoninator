# REST API

Zoninator registers a single namespace, `zoninator/v1`, in `src/rest/class-zones-controller.php`. All endpoints sit under `/wp-json/zoninator/v1/`.

There is also a non-REST JSON feed at `/zones/{slug}/feed.json` — see the [theme developer guide](theme-developers.md) for that.

## Authentication

Every endpoint except `GET /zones/{zone_id}/posts` needs an authenticated request. Use any standard WordPress REST authentication mechanism: cookie + `_wpnonce` for first-party JavaScript, application passwords for server-to-server, JWT or OAuth via plugins, etc.

`GET /zones/{zone_id}/posts` is public. It lists only published posts, the same as the [JSON feed](theme-developers.md#the-json-feed).

The `GET /zones` index endpoint requires a logged-in user as of 0.11.0. Sites that depend on the previous anonymous behavior can opt back in with the `zoninator_rest_get_zones_permissions_check` filter — see [hooks.md](hooks.md#zoninator_rest_get_zones_permissions_check-filter).

The other endpoints check a capability, `edit_others_posts` by default; see [hooks.md](hooks.md#capabilities) for the filters that govern them.

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

Send `name`, `slug` or both: with neither, the zone isn't created.

**Response:** the created zone, with status `201`. `400` with a `message` if the zone couldn't be created, such as when neither `name` nor `slug` was sent.

### `PUT /zoninator/v1/zones/{zone_id}`

Updates a zone.

**Permission:** `zoninator_edit_zone_cap`, plus the per-zone `zoninator_current_user_can_edit_zone` filter.

**Body** — same fields as `POST /zones`. Only the fields you send are updated. An empty value counts as not sent, so you can't clear a zone's description this way.

**Response:** `{ "success": true }` on success, `404` if the zone does not exist.

### `DELETE /zoninator/v1/zones/{zone_id}`

Deletes a zone. The posts themselves are not affected — only the zone-to-post relationships.

**Permission:** `zoninator_edit_zone_cap`, plus the per-zone `zoninator_current_user_can_edit_zone` filter.

**Response:** `{ "success": true }` on success, `404` if the zone does not exist.

### `GET /zoninator/v1/zones/{zone_id}/posts`

Returns the posts assigned to a zone, in zone order.

**Permission:** publicly accessible.

**Response:** array of the zone's published post objects, with the fields allowed by the `zoninator_zone_feed_fields` filter (by default: `ID`, `post_date`, `post_title`, `post_content`, `post_excerpt`, `post_status`, `guid`). Content and excerpt are blanked for password-protected posts.

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
| `term` | string | no | Search term, or a post ID. Leave it out for the latest content. |
| `cat` | int | no | Category ID to filter by. |
| `date` | string | no | Date to filter by, as `Y-m-d`. |
| `exclude` | int[] | no | Post IDs to leave out, usually those already in the zone. |
| `limit` | int | no | Number of search results, from 1 to 100. Defaults to the `zoninator_posts_per_page` value. The latest content ignores it. |
| `zone_id` | int | no | The zone being edited, passed to the query args filters. Since 0.12.0. |

The search applies the [content search filters](hooks.md#content-search). Published and scheduled posts are included.

Since 0.12.0, a `term` made only of digits looks that post up by ID. If it's a published or scheduled post of a zone post type, that the user can read and that isn't in `exclude`, the response is just that post, and `zoninator_search_args` doesn't run. Otherwise the term is searched as text, so a number in a title, such as "1984", still matches.

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

`timeout` and `max_lock_period` are filterable via [`zoninator_zone_lock_period`](hooks.md#zoninator_zone_lock_period-filter) and [`zoninator_zone_max_lock_period`](hooks.md#zoninator_zone_max_lock_period-filter).

**Response when the zone is already locked:** `400`

```json
{
  "zone_id": 12,
  "blocked": true
}
```

Look up the holder via the WordPress users API if you need to display them.

## Errors

Most errors are a JSON object with a `message` describing the problem in the site's language:

| Status | When |
|---|---|
| `400` | The request can't be carried out, such as a post that isn't of a zone post type the user can read, or a zone that couldn't be saved. The body is just `{ "message": "…" }`. |
| `400` | A parameter fails validation, such as a non-numeric `post_ids` entry. Core returns these, with the code `rest_invalid_param`. |
| `401` / `403` | The capability check failed: `401` when logged out, `403` when logged in. The code is `permission-denied`. Before 0.12.0, refused writes returned `500`. |
| `404` | The zone in the path doesn't exist. The body is just `{ "message": "…" }`. |

The lock endpoint's `400`, when someone else holds the lock, has the body shown above instead. A feed error on `GET /zones/{zone_id}/posts` has the code `zone-feed-error`.

`Automattic\Zoninator\REST\Zones_Controller` defines more error code constants, such as `INVALID_ZONE_ID` and `POST_NOT_FOUND`, but the endpoints don't return them as codes. Match on the status, not the code.

## Implementation notes

- The endpoints are registered by `Automattic\Zoninator\REST\Zones_Controller` (`src/rest/class-zones-controller.php`), which extends core's `WP_REST_Controller`. The file loads only on `rest_api_init`.
- The update routes (`/zones/{zone_id}`, `/zones/{zone_id}/posts` and `/zones/{zone_id}/lock`) accept `POST`, `PUT` and `PATCH`.
- The Zones screen uses these routes through `wp.apiFetch()`. Its `zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts`, `zoninator_update_lock` and `zoninator_update_recent` admin-ajax actions are deprecated since 0.12.0, and go in 1.0.0.
- A lock is a transient keyed against the zone, holding the locking user's ID. It survives page reloads, and expires when `zoninator_zone_lock_period` lapses without a renewal.

## See also

- [hooks.md](hooks.md) — every filter and action exposed by the plugin, including the REST permissions filter.
- [theme-developers.md](theme-developers.md) — the template-tag API for use inside themes.
