# Zoninator

Content zone management for WordPress editorial workflows.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `zoninator.php` |
| **Text domain** | `zoninator` |
| **Function prefix** | `z_` (template tags), `zoninator_` (hooks) |
| **Class prefix** | `Zoninator`, `Zoninator_` (legacy, global); `Automattic\Zoninator\` for new classes |
| **Source directory** | Root level + `src/` |
| **Requires PHP** | 8.2+ |
| **Requires WP** | 6.9+ |

### Directory Structure

```
zoninator/
├── zoninator.php                    # Main plugin file (header, bootstrap)
├── functions.php                    # Template tag functions (z_*)
├── src/
│   ├── class-zoninator.php          # Main Zoninator class (builds the rest, registers hooks; facade for themes)
│   ├── class-settings.php           # Settings: filtered values and the screen's messages
│   ├── class-capabilities.php       # Capabilities: who may add, edit and manage zones
│   ├── class-request.php            # Request: request variables, sanitizing, nonces and access checks
│   ├── class-taxonomy.php           # Taxonomy: registers the zone taxonomy and its post types
│   ├── class-zones.php              # Zones: zone CRUD, zone posts and their order
│   ├── class-locks.php              # Locks: zone edit locks
│   ├── class-feed.php               # Feed: the JSON feed at /zones/{slug}/feed.json
│   ├── class-abilities.php          # Abilities: read-only Abilities API abilities (WP 6.9+)
│   ├── admin/
│   │   ├── class-screen.php         # Zones admin screen (markup, form handling, assets)
│   │   └── class-ajax.php           # Zones screen admin-ajax handlers
│   ├── class-zoninator-zoneposts-widget.php  # Zone Posts widget
│   └── rest/
│       └── class-zones-controller.php # REST endpoints (/zoninator/v1/zones)
├── css/                             # Admin stylesheets
├── js/                              # Admin JavaScript (jQuery sortable, autocomplete)
├── tests/
│   ├── bootstrap.php                # PHPUnit bootstrap (yoast/wp-test-utils)
│   └── Integration/                 # Integration tests + TestCase base
├── docs/                            # Audience-targeted documentation
├── languages/                       # Translation files
├── .wordpress-org/                  # WP.org assets (screenshots)
├── .github/workflows/               # CI: cs-lint, integration, release, deploy
└── .phpcs.xml.dist                  # PHPCS configuration
```

### Key Classes and Files

- `src/class-zoninator.php` — `Zoninator` class: builds every other class, hands each the ones it needs, and registers every hook. Nothing else depends on `Zoninator`. Its settings getters (such as `get_zone_lock_period()`), its underscore request, capability and sanitizing helpers, and its zone data, lock, feed, admin screen and `ajax_*` methods are all thin delegates. `Zoninator::ZONE_TAXONOMY` and `Zoninator::ZONE_META_PREFIX` are public aliases of `Taxonomy::NAME` and `Zones::META_PREFIX`. The old public properties still work through `__get()` and `__set()`, with a notice, until 1.0.0, and writes reach `Settings`. The delegates stay so `array( Zoninator(), 'method' )` callbacks and existing calls keep working.
- `src/class-settings.php` — `Automattic\Zoninator\Settings`: the lock periods, results per page and zone detail defaults, filtered on `init`, and the Zones screen's messages.
- `src/class-capabilities.php` — `Automattic\Zoninator\Capabilities`: the add, edit and manage capability checks (and their filters), and whether the user may put a given post in a zone.
- `src/class-request.php` — `Automattic\Zoninator\Request`: reads and sanitizes request variables, validates the search filters, and checks nonces and access, stopping the request if either fails.
- `src/class-taxonomy.php` — `Automattic\Zoninator\Taxonomy`: registers the `zoninator_zones` taxonomy (`Taxonomy::NAME`) on `init` and tracks which post types support zones.
- `src/class-zones.php` — `Automattic\Zoninator\Zones`: creates, reads, updates and deletes zones, and stores each zone's posts in order under post meta keys starting `Zones::META_PREFIX`. It reads the detail defaults from `Settings`.
- `src/class-locks.php` — `Automattic\Zoninator\Locks`: takes, reports and releases the transient that stops two people editing a zone at once.
- `src/class-abilities.php` — `Automattic\Zoninator\Abilities`: the read-only Abilities API abilities (`zoninator/list-zones`, `zoninator/get-zone-posts`, `zoninator/search-posts`) in the `zoninator` category. `Zoninator` loads it and hooks it on `wp_abilities_api_categories_init` and `wp_abilities_api_init` only when `wp_register_ability()` exists (WordPress 6.9+). Like the REST route callbacks, the ability callbacks live on this class; sites remove an ability with `wp_unregister_ability()`.
- `src/class-feed.php` — `Automattic\Zoninator\Feed`: the rewrite rule and response for the JSON feed at `/zones/{slug}/feed.json`.
- `src/admin/class-screen.php` — `Automattic\Zoninator\Admin\Screen`: renders the Zones screen (`Screen::PAGE_SLUG`, `admin.php?page=zoninator`), saves its zone form and loads its assets.
- `src/admin/class-ajax.php` — `Automattic\Zoninator\Admin\Ajax`: the screen's old admin-ajax handlers, which all check `Ajax::NONCE_ACTION`. Since 0.12.0 the screen uses the REST API for everything, so every action here is deprecated and the class goes in 1.0.0.
- `functions.php` — Public template tags: `z_get_zones()`, `z_get_zone()`, `z_get_posts_in_zone()`, `z_get_zone_query()`, `z_get_next_post_in_zone()`, `z_get_prev_post_in_zone()`, `z_get_post_zones()`. Used by themes in production.
- `src/class-zoninator-zoneposts-widget.php` — Classic widget (`Zoninator_ZonePosts_Widget`).
- `src/rest/class-zones-controller.php` — `Automattic\Zoninator\REST\Zones_Controller`, a `WP_REST_Controller` that registers the `zoninator/v1` routes, including the ones the Zones screen uses. Its `find_posts()` is the post search for both the REST route and the deprecated AJAX action. `Zoninator::register_rest_routes()` loads it on `rest_api_init`.
- `tests/Integration/TestCase.php` — Base class for integration tests.

### Storage model

Zones are terms in the `zoninator_zones` taxonomy, with their details (such as the description) serialized in the term description. A post joins a zone through post meta: the key is `_zoninator_order_{zone_id}` (`Zones::META_PREFIX` plus the zone ID) and the value is its position. There are no term relationships between posts and zones. The taxonomy is non-public and non-queryable — it exists purely as a data store.

### Dependencies

- **Runtime**: `composer/installers` (only required for non-Composer-aware sites).
- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`, `phpunit/phpunit`, `php-parallel-lint/php-parallel-lint`, `phpcompatibility/phpcompatibility-wp`, `rector/rector`.

## Commands

```bash
composer cs                      # Check code standards (PHPCS)
composer cs-fix                  # Auto-fix code standard violations
composer lint                    # PHP parallel-lint (syntax)
composer lint-ci                 # PHP parallel-lint with checkstyle output
composer i18n                    # Generate languages/zoninator.pot (requires wp-cli)
composer test:integration        # Run integration tests (requires wp-env)
composer test:integration-ms     # Run integration tests in multisite mode
composer coverage                # Run tests with HTML coverage report (build/coverage-html)
composer coverage-ci             # Run tests, print text coverage summary

npm ci                           # Install @wordpress/env (pinned in package.json)
npx --no wp-env start            # Start the local WordPress environment
npx --no wp-env stop             # Stop it
```

**Note:** This plugin has integration tests only — no separate unit test suite.

## Conventions

See `CONTRIBUTING.md` for setup, checks and the contribution workflow. Key points:

- **Commits**: Every commit must be signed; `develop` and `main` reject unsigned commits. Use a [Conventional Commits](https://www.conventionalcommits.org/) prefix (`fix:`, `feat:`, `test:`, `docs:`, `ci:`, `chore:` and so on) with an imperative subject of about 50 characters, and a body that explains why the change was needed rather than what changed. One concern per commit.
- **PRs**: Open against `develop`. The title describes the outcome in sentence case, without a conventional commit prefix. The description explains the problem first, then how the PR solves it. PRs land as merge commits (squash and rebase merging are disabled), so every commit message reaches `develop` as written.
- **Testing**: Integration tests only, run inside `wp-env` against a real WordPress install. Extend `Automattic\Zoninator\Tests\Integration\TestCase`, or `WP_Ajax_UnitTestCase` for admin-ajax handlers.
- **Code style**: WordPress coding standards via PHPCS (with VIP rules). Tabs for indentation.
- **i18n**: All user-facing strings must use the `zoninator` text domain.

## Architectural Decisions

- **Taxonomy and post meta storage**: Zones are custom taxonomy terms; each post's position in a zone is post meta, which the zone query orders by. This uses WordPress's built-in caching and query infrastructure. Do not switch to custom database tables.
- **Template tags in `functions.php`**: Theme developers consume zones through these functions. They are part of the public API — do not rename or change signatures without a deprecation cycle.
- **Namespaces**: Legacy classes and functions are in the global namespace; keep their names, as themes and integrations use them. New classes go under `Automattic\Zoninator\`, are `final` unless a site has a reason to extend them, in WordPress-style `class-*.php` files loaded with `require_once` (the release package has no Composer autoloader).
- **REST controller on core**: The REST API extends core's `WP_REST_Controller` directly. `ZoninatorRestContractTest` pins its routes, methods, args and response bodies; change them only on purpose.
- **`Zoninator` is the facade**: register hooks, and any callback handed to WordPress, as `array( $zoninator, 'method' )`, never on `Taxonomy`, `Zones`, `Locks`, `Feed`, `Admin\Screen` or `Admin\Ajax`. Sites unhook with `array( Zoninator(), 'method' )`, which silently does nothing if the callback lives on another object. `ZoninatorAdminAjaxTest` pins this and the AJAX response shapes `js/zoninator.js` depends on.
- **Dependencies run one way**: `Zoninator` builds the other classes and passes each the objects it needs in its constructor. No other class takes or calls `Zoninator`, and `ZoninatorDependencyTest` checks the constructors. Callbacks that must stay on the facade, such as the default zone post columns, are passed in as callables.
- **REST `/zones` index requires auth**: Since 0.11.0 the `GET /zoninator/v1/zones` endpoint requires `is_user_logged_in()` by default. Sites needing the historical anonymous behavior must opt in via the `zoninator_rest_get_zones_permissions_check` filter.
- **WordPress.org deployment**: Releases push to the WP.org SVN via `.github/workflows/deploy.yml` on GitHub release. Do not manually modify SVN assets.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx --no wp-env start` running first. Run `npm ci` before it, so `npx` uses the `@wordpress/env` pinned in `package.json`. Always keep `--no`: without an installed `@wordpress/env`, plain `npx wp-env` fetches the unrelated `wp-env` package from npm.
- **Template tags are public API**: Functions in `functions.php` (e.g., `z_get_posts_in_zone()`) are used by themes in production. Do not rename, remove, or change their signatures without a deprecation cycle.
- **Zone ordering is significant**: posts within a zone have a specific display order. Preserve it through any query modifications.
- **Admin UI uses jQuery drag-and-drop**: test UI changes in a browser, not just with automated tests.
- **`zoninator_zones` taxonomy is intentionally not public**: it has no front-end archive. Don't flip `public => true` to "fix" missing archives — themes are expected to render zones via template tags or `z_get_zone_query()`.
- **Two version strings**: bump `zoninator.php` (header + `ZONINATOR_VERSION`), `package.json`, `package-lock.json`, and the `Stable tag` in `README.md`. The `npm run bump:*` scripts cover most of this.
- **Don't add new hooks under the `zone_posts_widget_*` prefix**: that prefix is grandfathered. Use `zoninator_*` for any new hook (search the codebase before naming).
