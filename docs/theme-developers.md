# Theme developer guide

Zoninator stores zones as taxonomy terms, but you don't need to think about that — themes consume zones through a small set of template tags in `functions.php`.

This guide covers the common patterns. For the full hook surface, see [hooks.md](hooks.md).

## Quick reference

| Function | Returns | When to use |
|---|---|---|
| `z_get_zones()` | `array` of `WP_Term` objects | List every zone (e.g. for an admin dropdown). |
| `z_get_zone( $zone )` | `WP_Term` | Look up a single zone by ID or slug. |
| `z_get_posts_in_zone( $zone, $args = [] )` | `array` of `WP_Post` objects | Read zone posts as a plain array. |
| `z_get_zone_query( $zone, $args = [] )` | `WP_Query` | Read zone posts as a query, so you can use `the_post()` and template tags. |
| `z_get_next_post_in_zone( $zone, $post_id = 0 )` | `WP_Post` or `false` | Build "next in zone" navigation. |
| `z_get_prev_post_in_zone( $zone, $post_id = 0 )` | `WP_Post` or `false` | Build "previous in zone" navigation. |
| `z_get_post_zones( $post_id = 0 )` | `array` of `WP_Term` | Find every zone a post belongs to. |

`$zone` accepts either a numeric term ID or a slug. The `$post_id = 0` arguments fall back to the current post in the loop.

## Looping over a zone

The `WP_Query` form is preferred when you want to use the standard loop and template tags inside it:

```php
$homepage = z_get_zone_query( 'homepage' );

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
$posts = z_get_posts_in_zone( 'homepage' );

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
$next = z_get_next_post_in_zone( 'homepage' );
$prev = z_get_prev_post_in_zone( 'homepage' );

if ( $next ) {
    printf( '<a href="%s">Next: %s</a>', esc_url( get_permalink( $next->ID ) ), esc_html( get_the_title( $next->ID ) ) );
}
```

Both functions accept a post ID as a second argument, but default to the current loop post when you don't pass one.

## Listing zones a post belongs to

Useful for displaying "appears in: Homepage, Sport, Top Stories" badges:

```php
$zones = z_get_post_zones();
foreach ( $zones as $zone ) {
    echo '<span class="zone-badge">' . esc_html( $zone->name ) . '</span>';
}
```

## Customising the underlying query

`z_get_posts_in_zone()` and `z_get_zone_query()` both accept a `$args` array that's merged into the underlying `WP_Query` arguments. For example, to fetch the first three posts only:

```php
$top = z_get_posts_in_zone( 'homepage', [ 'posts_per_page' => 3 ] );
```

For broader changes that should apply everywhere, prefer the `zoninator_recent_posts_args` and `zoninator_search_args` filters in [hooks.md](hooks.md) — they apply consistently across the admin UI as well.

## Performance notes

- Zone lookups hit the WordPress object cache via the standard taxonomy machinery. There is no plugin-level caching layer; if you need the result on a high-traffic page, wrap your call in `wp_cache_get` / `wp_cache_set` or use the persistent cache provided by your host.
- The `WP_Query` returned by `z_get_zone_query()` is a fresh query — if you call it twice with the same arguments, that's two queries. Cache the result in a variable.
- Zoninator orders posts by a meta value behind the scenes, which means a `JOIN` against `postmeta`. Keep zones small (the admin UI is built for tens of posts, not thousands).

## The Zone Posts widget

For sidebars, the bundled Zone Posts widget renders a list without any code. It registers under **Appearance → Widgets** with the title "Zone Posts" and exposes a zone selector and a "show description" toggle. The widget caches its rendered output for 5 seconds via `wp_cache_*`; that interval is filterable via the legacy `zone_posts_widget_block_save_cache_seconds` hook.

## The JSON feed

Zoninator exposes a per-zone JSON feed at `/zones/{slug}/feed.json` (rewrite rule registered on init). This is the simplest way for a downstream consumer to pull a zone, but for most needs the [REST API](rest-api.md) is a better fit because it supports authentication and is discoverable.

## See also

- [hooks.md](hooks.md) — every filter and action the plugin exposes.
- [rest-api.md](rest-api.md) — REST endpoints for headless / decoupled integrations.
