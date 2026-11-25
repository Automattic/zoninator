# Abilities

Zoninator registers six abilities with the Abilities API that WordPress 6.9 added, so agents and other tools can discover and do what an editor does on the Zones screen. They're in the `zoninator` category.

Every ability needs the capability the Zones screen needs, `edit_others_posts` by default; see [`zoninator_manage_zone_cap`](hooks.md#zoninator_manage_zone_cap-filter). Each one runs as the current user.

## Reading

| Ability | Input | Returns |
|---|---|---|
| `zoninator/list-zones` | none | Every zone, with its `term_id`, `slug`, `name` and `description`. |
| `zoninator/get-zone-posts` | `zone_id` | The published and scheduled posts in the zone, in zone order, with `ID`, `post_date`, `post_title` and `post_status`. |
| `zoninator/search-posts` | `term`, `cat`, `date`, `exclude`, `limit`, `zone_id`, all optional | Posts that can be added to a zone. A post ID as the term finds that post. With no term, the latest posts. |

Each leaves out posts the user may not read.

## Changing a zone's posts

| Ability | Input | What it does |
|---|---|---|
| `zoninator/add-zone-posts` | `zone_id`, `post_ids` | Adds the posts to the end of the zone, in the order given. A post already in the zone keeps its place. |
| `zoninator/set-zone-posts` | `zone_id`, `post_ids` | Makes the zone hold these posts and no others, in the order given. Use it to reorder: send every post that should stay. An empty list empties the zone. |
| `zoninator/remove-zone-posts` | `zone_id`, `post_ids` | Takes the posts out of the zone. The posts themselves aren't deleted. |

Each returns `{ "success": true }`, or an error:

| Code | When |
|---|---|
| `zone-not-found` | There's no zone with that ID. |
| `invalid-post-id` | A post doesn't exist, isn't of a post type that supports zones, or isn't one the user may read. Nothing is changed. |
| `zone-locked` | Someone else is editing the zone. The message names them. An agent acting for the person who has the zone open goes through. |

These abilities pass the same checks as the Zones screen and the [REST API](rest-api.md), including the zone's lock.

## MCP

The three reading abilities are marked public for the MCP adapter, so its default server offers them. The three that change a zone aren't. To offer them to agents, add them to an MCP server of your own, or use core's `wp_register_ability_args` filter to set `meta.mcp.public` on them.
