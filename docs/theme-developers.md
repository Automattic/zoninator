# Theme developer guide

Zoninator stores zones as taxonomy terms, but you don't need to think about that — themes consume zones through a small set of template tags in `functions.php`.

This guide covers the common patterns. For the full hook surface, see [hooks.md](hooks.md).

Before 1.0.0 these functions began with `z_`, as in `z_get_zone()`. Those names are gone; the [upgrade guide](upgrades.md#the-template-tags-have-the-zoninator_-prefix) lists each one.

## Quick reference

| Function | Returns | When to use |
|---|---|---|
| `zoninator_get_zones()` | `array` of `WP_Term` objects, or `false` on error | List every zone (e.g. for an admin dropdown). |
| `zoninator_get_zone( $zone )` | `WP_Term`, or `false` if there's no such zone | Look up a single zone by ID or slug. |
| `zoninator_get_posts_in_zone( $zone, $args = [] )` | `array` of `WP_Post` objects | Read zone posts as a plain array. |
| `zoninator_get_zone_query( $zone, $args = [] )` | `WP_Query` | Read zone posts as a query, so you can use `the_post()` and template tags. |
| `zoninator_get_next_post_in_zone( $zone, $post_id = 0 )` | `WP_Post` or `false` | Build "next in zone" navigation. |
| `zoninator_get_prev_post_in_zone( $zone, $post_id = 0 )` | `WP_Post` or `false` | Build "previous in zone" navigation. |
| `zoninator_get_post_zones( $post_id = 0 )` | `array` of `WP_Term` | Find every zone a post belongs to. |

`$zone` accepts either a numeric term ID or a slug. The `$post_id = 0` arguments fall back to the current post in the loop.

## Looping over a zone

The `WP_Query` form is preferred when you want to use the standard loop and template tags inside it:

```php
$homepage = zoninator_get_zone_query( 'homepage' );

if ( $homepage->have_posts() ) :
    while ( $homepage->have_posts() ) :
        $homepage->the_post();
        ?>
        <article <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <?php the_excerpt(); ?>
        </article>
        <?php
    endwhile;
    wp_reset_postdata();
endif;
```

`wp_reset_postdata()` is the right cleanup for a `WP_Query` you constructed yourself. (Older code used `wp_reset_query()` — that's intended for `query_posts()` and is unnecessary here.)

## Reading a zone as an array

When you only need post data and not the full loop machinery:

```php
$posts = zoninator_get_posts_in_zone( 'homepage' );

foreach ( $posts as $post ) {
    printf(
        '<li><a href="%s">%s</a></li>',
        esc_url( get_permalink( $post->ID ) ),
        esc_html( get_the_title( $post->ID ) )
    );
}
```

This is also handy when you're rendering posts inside an existing loop and don't want to pollute global state.

## "Next" and "previous" within a zone

Use these on single post templates to build zone-aware navigation:

```php
$next = zoninator_get_next_post_in_zone( 'homepage' );
$prev = zoninator_get_prev_post_in_zone( 'homepage' );

if ( $next ) {
    printf( '<a href="%s">Next: %s</a>', esc_url( get_permalink( $next->ID ) ), esc_html( get_the_title( $next->ID ) ) );
}
```

Both functions accept a post ID as a second argument, but default to the current loop post when you don't pass one. They return `false` when the post isn't in the zone or has no neighbor in that direction.

They work since 0.12.0. In earlier versions, every call caused a fatal error.

## Listing zones a post belongs to

Useful for displaying "appears in: Homepage, Sport, Top Stories" badges:

```php
$zones = zoninator_get_post_zones();
foreach ( $zones as $zone ) {
    echo '<span class="zone-badge">' . esc_html( $zone->name ) . '</span>';
}
```

The zones come back in zone ID order. As with `zoninator_get_posts_in_zone()`, a post only counts as in a zone where it would be listed: outside the admin, a scheduled or draft post isn't in any zone yet. The function returns an empty array when the post isn't in a zone. It works since 0.12.0; earlier versions always returned `null`.

## Customizing the underlying query

`zoninator_get_posts_in_zone()` and `zoninator_get_zone_query()` both accept a `$args` array that's merged into the underlying `WP_Query` arguments. For example, to fetch the first three posts only:

```php
$top = zoninator_get_posts_in_zone( 'homepage', [ 'posts_per_page' => 3 ] );
```

Zoninator always sets `orderby` and `meta_key`, so the posts stay in zone order; anything else you pass wins over the defaults.

The `zoninator_recent_posts_args` and `zoninator_search_args` filters in [hooks.md](hooks.md) change the post search on the Zones screen, not these queries.

## Performance notes

- Zone lookups hit the WordPress object cache via the standard taxonomy machinery. There is no plugin-level caching layer; if you need the result on a high-traffic page, wrap your call in `wp_cache_get` / `wp_cache_set` or use the persistent cache provided by your host.
- The `WP_Query` returned by `zoninator_get_zone_query()` is a fresh query — if you call it twice with the same arguments, that's two queries. Cache the result in a variable.
- Zoninator orders posts by a meta value behind the scenes, which means a `JOIN` against `postmeta`. Keep zones small (the admin UI is built for tens of posts, not thousands).

## The Zone Posts block

Since 1.0.0, block themes and the block editor can show a zone with the **Zone Posts** block, a variation of core's Query Loop block. Insert it, pick the zone under **Zone** in the block settings, and design each item with the Post Template, Post Title, Post Featured Image and other blocks as you would for any Query Loop. Zoninator adds no markup of its own, so the theme styles it like any other query.

The block uses the same query as `zoninator_get_zone_query()`: published posts in the zone, in zone order. The block's items per page, offset and max pages still apply. Until a zone is picked, it shows no posts.

The editor preview reads posts through the `/wp/v2/posts` REST route, so it only shows the zone's posts of the `post` post type. A zone of pages or other post types previews with fewer posts, or none, but the front end shows them all.

## The Zone Posts widget

For sidebars, the bundled Zone Posts widget renders a list without any code. It registers under **Appearance → Widgets** with the title "Zone Posts" and exposes a zone selector and a "show description" toggle.

To show the posts your own way, such as with thumbnails, return your HTML from the [`zoninator_zone_posts_widget_html`](hooks.md#zoninator_zone_posts_widget_html-filter) filter, since 0.12.0.

The widget builds its output on each page view and keeps no cache of its own. WordPress caches the zone's query in the object cache, and clears it when a post or its place in a zone changes, so the widget always shows the zone as it is. If your `zoninator_zone_posts_widget_html` callback does costly work, cache its result yourself.

## The JSON feed

Zoninator exposes a per-zone JSON feed at `/zones/{slug}/feed.json` (rewrite rule registered on init). This is the simplest way for a downstream consumer to pull a zone, but for most needs the [REST API](rest-api.md) is a better fit because it supports authentication and is discoverable.

A slug that names no zone gets a `406` status and a body of `null`, as does a feed that a `zoninator_json_feed_results` callback refuses with a `WP_Error`.

## See also

- [hooks.md](hooks.md) — every filter and action the plugin exposes.
- [rest-api.md](rest-api.md) — REST endpoints for headless / decoupled integrations.
