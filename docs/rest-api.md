# REST API

Zoninator registers a single namespace, `zoninator/v1`, in `src/class-zoninator-api-controller.php`. All endpoints sit under `/wp-json/zoninator/v1/`.

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

### `POST /zoninator/v1/zones/{zone_id}/posts`

Replaces the post list for a zone with the supplied IDs.

**Permission:** `zoninator_edit_zone_cap`.

**Body**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `post_ids` | int[] | yes | Ordered list of post IDs to assign to the zone. |

**Response:** `{ "success": true }` on success, `400` if any ID does not resolve to a post, `404` if the zone does not exist.

### `PUT /zoninator/v1/zones/{zone_id}/lock`

Acquires (or extends) the editing lock for a zone. Used by the admin UI to prevent two editors from clobbering each other.

**Permission:** `zoninator_edit_zone_cap`.

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

Errors follow the standard `WP_REST_Response` shape with a top-level `message` field describing the problem in the site's language. Common codes are documented as constants on `Zoninator_Api_Controller`:

| Constant | Meaning |
|---|---|
| `INVALID_ZONE_ID` / `ZONE_NOT_FOUND` | The path's `{zone_id}` does not resolve. |
| `INVALID_POST_ID` / `POST_NOT_FOUND` | One of the supplied post IDs does not exist. |
| `ZONE_ID_REQUIRED` / `ZONE_ID_POST_ID_REQUIRED` / `ZONE_ID_POST_IDS_REQUIRED` | A required identifier is missing. |
| `ZONE_FEED_ERROR` | The zone exists but its feed couldn't be assembled. |
| `PERMISSION_DENIED` | Caller failed the relevant capability check. |
| `INVALID_ZONE_SETTINGS` | Submitted zone arguments are malformed. |

## Implementation notes

- The endpoints are built on a vendored copy of the Mixtape REST framework (`src/zoninator_rest/`). The controller registers routes through `add_route()` rather than `register_rest_route()` directly, but the resulting routes behave like any other WordPress REST endpoint.
- Lock state is persisted in user meta keyed against the zone term, so locks survive page reloads but expire automatically when `zoninator_zone_lock_period` lapses.

## See also

- [hooks.md](hooks.md) — every filter and action exposed by the plugin, including the REST permissions filter.
- [theme-developers.md](theme-developers.md) — the template-tag API for use inside themes.
