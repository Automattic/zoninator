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
│   ├── Infrastructure/              # Everything that talks to WordPress, and the wiring
│   │   ├── DI/
│   │   │   └── Container.php        # Holds the built objects by class name
│   │   └── WordPress/
│   │       ├── PluginBootstrapper.php # The composition root; builds every class and registers hooks
│   │       ├── Settings.php         # Filtered values and the screen's messages
│   │       ├── Capabilities.php     # Who may add, edit and manage zones
│   │       ├── Request.php          # Request variables, sanitizing, nonces and access checks
│   │       ├── Taxonomy.php         # Registers the zone taxonomy; which post types support zones
│   │       ├── Zones.php            # Zone CRUD, zone posts and their order
│   │       ├── Locks.php            # Zone edit locks
│   │       ├── Feed.php             # The JSON feed's data: a zone's published posts, feed fields only
│   │       ├── FeedEndpoint.php     # Serves the feed at /zones/{slug}/feed.json
│   │       ├── Search.php           # Finds posts to offer for a zone
│   │       ├── Block.php            # The Zone Posts Query Loop variation's queries and editor script
│   │       ├── Abilities.php        # Read-only Abilities API abilities
│   │       ├── Admin/
│   │       │   └── Screen.php       # Zones admin screen (markup, form handling, assets)
│   │       └── Rest/
│   │           └── ZonesController.php # REST endpoints (/zoninator/v1/zones)
│   ├── class-zoninator.php          # Zoninator: the public API that Zoninator() returns (global class)
│   └── class-zoninator-zoneposts-widget.php  # Zone Posts widget (global class)
├── css/                             # Admin stylesheets
├── js/                              # Admin JavaScript (jQuery sortable, autocomplete) and the block editor script
├── tests/
│   ├── bootstrap.php                # PHPUnit bootstrap (yoast/wp-test-utils)
│   ├── Integration/                 # Integration tests + TestCase base
│   └── e2e/                         # Playwright tests of the Zones screen, with axe checks
├── docs/                            # Audience-targeted documentation
├── languages/                       # Translation files
├── .wordpress-org/                  # WP.org assets (screenshots)
├── .github/workflows/               # CI: cs-lint, integration, e2e, release, deploy
├── .phpcs.xml.dist                  # PHPCS configuration
└── playwright.config.js             # Playwright config (extends @wordpress/scripts)
```

### Key Classes and Files

- `zoninator.php` — Loads the classes and defines the two public functions. `zoninator_container()` boots the plugin the first time it's called, by making a `Container` and calling `PluginBootstrapper::boot()`, and returns the container. `Zoninator()` returns the public API from it. Boot happens on `plugins_loaded` (straight away if that has fired, for themes that load plugins late), or earlier if either function is called first.
- `src/Infrastructure/WordPress/PluginBootstrapper.php` — `Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper`: the composition root. `build()` builds every class by hand, in dependency order, and adds each to the container; the REST controller and `Abilities` go in as factories, so their files load only when needed. `register_hooks()` calls `register_hooks()` on each class that has hooks, and hooks its own methods that register the REST routes and the abilities. `boot()` runs both and sets the `$zoninator` global.
- `src/Infrastructure/DI/Container.php` — `Automattic\Zoninator\Infrastructure\DI\Container`: holds objects by class name and knows nothing about them. `add()` stores an object, `factory()` stores a builder that `get()` calls the first time it's asked, and `get()` and `has()` are shaped like PSR-11's. Sites reach the object that handles a hook through `zoninator_container()->get( Feed::class )` and the like. It's the one class under `Automattic\Zoninator\` that isn't `@internal`.
- `src/class-zoninator.php` — `Zoninator`, `final`: the public API that `Zoninator()` returns. Its constructor takes the parts it delegates to and builds nothing, and it has no hooks. Its methods are the zone, zone post, lock and post type methods and the settings getters (such as `get_zone_lock_period()`). `Zoninator::ZONE_TAXONOMY` and `Zoninator::ZONE_META_PREFIX` are public aliases of `Taxonomy::NAME` and `Zones::META_PREFIX`.
- `src/Infrastructure/WordPress/Settings.php` — `Automattic\Zoninator\Infrastructure\WordPress\Settings`: the lock periods, results per page and zone detail defaults, each filtered when read, and the Zones screen's messages.
- `src/Infrastructure/WordPress/Capabilities.php` — `Automattic\Zoninator\Infrastructure\WordPress\Capabilities`: the add, edit and manage capability checks (and their filters), and whether the user may put a given post in a zone.
- `src/Infrastructure/WordPress/Request.php` — `Automattic\Zoninator\Infrastructure\WordPress\Request`: reads and sanitizes request variables, validates the search filters, and checks nonces and access, stopping the request if either fails. Its helpers are instance methods, so a class that uses one takes `Request` in its constructor.
- `src/Infrastructure/WordPress/Taxonomy.php` — `Automattic\Zoninator\Infrastructure\WordPress\Taxonomy`: registers the `zoninator_zones` taxonomy (`Taxonomy::NAME`) on `init`, with no object types, and gives posts zone support when the plugin boots. A post type supports zones if it has the `zoninator_zones` post type feature; `get_post_types()` asks core each time, with no cache.
- `src/Infrastructure/WordPress/Zones.php` — `Automattic\Zoninator\Infrastructure\WordPress\Zones`: creates, reads, updates and deletes zones, and stores each zone's posts in order under post meta keys starting `Zones::META_PREFIX`. It reads the detail defaults from `Settings`.
- `src/Infrastructure/WordPress/Locks.php` — `Automattic\Zoninator\Infrastructure\WordPress\Locks`: takes, reports and releases the transient that stops two people editing a zone at once.
- `src/Infrastructure/WordPress/Abilities.php` — `Automattic\Zoninator\Infrastructure\WordPress\Abilities`: the read-only Abilities API abilities (`zoninator/list-zones`, `zoninator/get-zone-posts`, `zoninator/search-posts`) in the `zoninator` category. The bootstrapper hooks `wp_abilities_api_categories_init` and `wp_abilities_api_init`, and the container builds it the first time either fires. Like the REST route callbacks, the ability callbacks live on this class; sites remove an ability with `wp_unregister_ability()`.
- `src/Infrastructure/WordPress/Feed.php` — `Automattic\Zoninator\Infrastructure\WordPress\Feed`: a zone's published posts as the JSON feed lists them, limited to the feed's fields and passed through its filters. It has no hooks. `FeedEndpoint` and the REST route `GET /zones/{zone_id}/posts` both return it.
- `src/Infrastructure/WordPress/FeedEndpoint.php` — `Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint`: the rewrite rule and response for the JSON feed at `/zones/{slug}/feed.json`.
- `src/Infrastructure/WordPress/Search.php` — `Automattic\Zoninator\Infrastructure\WordPress\Search`: finds posts to offer for a zone, by text or the latest content. The REST search route and the `zoninator/search-posts` ability both call its `find_posts()`.
- `src/Infrastructure/WordPress/Block.php` — `Automattic\Zoninator\Infrastructure\WordPress\Block`: the Zone Posts variation of core's Query Loop block. `js/zoninator-block.js` (plain JS, no build step) registers the variation and its zone picker, which saves the zone ID as `zoninatorZone` in the block's query. `Block` turns that into `Zones::get_query_args()` on `query_loop_block_query_vars` for the front end and on `rest_post_query` for the editor preview.
- `src/Infrastructure/WordPress/Admin/Screen.php` — `Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen`: renders the Zones screen (`Screen::PAGE_SLUG`, `admin.php?page=zoninator`), saves its zone form and loads its assets.
- `functions.php` — Public template tags: `z_get_zones()`, `z_get_zone()`, `z_get_posts_in_zone()`, `z_get_zone_query()`, `z_get_next_post_in_zone()`, `z_get_prev_post_in_zone()`, `z_get_post_zones()`. Used by themes in production.
- `src/class-zoninator-zoneposts-widget.php` — Classic widget (`Zoninator_ZonePosts_Widget`). It takes `Zones` in its constructor; WordPress builds widgets itself with no arguments, so it falls back to the container's.
- `src/Infrastructure/WordPress/Rest/ZonesController.php` — `Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController`, a `WP_REST_Controller` that registers the `zoninator/v1` routes, including the ones the Zones screen uses. Its search route calls `Search`. `PluginBootstrapper::register_rest_routes()` gets it from the container, which loads it, on `rest_api_init`.
- `tests/Integration/TestCase.php` — Base class for integration tests.

### Storage model

Zones are terms in the `zoninator_zones` taxonomy, with their details (such as the description) serialized in the term description. A post joins a zone through post meta: the key is `_zoninator_order_{zone_id}` (`Zones::META_PREFIX` plus the zone ID) and the value is its position. There are no term relationships between posts and zones. The taxonomy is non-public and non-queryable — it exists purely as a data store.

### Dependencies

- **Runtime**: `composer/installers` (only required for non-Composer-aware sites).
- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`, `phpunit/phpunit`, `php-parallel-lint/php-parallel-lint`, `phpcompatibility/phpcompatibility-wp`, `rector/rector`.
- **Dev (npm)**: `@wordpress/env`, and for the E2E tests `@playwright/test`, `@wordpress/scripts`, `@wordpress/e2e-test-utils-playwright` and `@axe-core/playwright`.

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

npm ci                           # Install @wordpress/env and Playwright (pinned in package.json)
npx --no wp-env start            # Start the local WordPress environment
npx --no wp-env stop             # Stop it
npx --no playwright install chromium  # Download the browser for the E2E tests (once)
npm run test:e2e                 # Run the Playwright E2E tests (requires wp-env)
```

**Note:** This plugin has integration and E2E tests — no separate unit test suite.

## Conventions

See `CONTRIBUTING.md` for setup, checks and the contribution workflow. Key points:

- **Commits**: Every commit must be signed; `develop` and `main` reject unsigned commits. Use a [Conventional Commits](https://www.conventionalcommits.org/) prefix (`fix:`, `feat:`, `test:`, `docs:`, `ci:`, `chore:` and so on) with an imperative subject of about 50 characters, and a body that explains why the change was needed rather than what changed. One concern per commit.
- **PRs**: Open against `develop`. The title describes the outcome in sentence case, without a conventional commit prefix. The description explains the problem first, then how the PR solves it. PRs land as merge commits (squash and rebase merging are disabled), so every commit message reaches `develop` as written.
- **Testing**: Integration tests run inside `wp-env` against a real WordPress install. Extend `Automattic\Zoninator\Tests\Integration\TestCase`. E2E tests in `tests/e2e/` cover only what a browser shows on the Zones screen; they share the development site's database, so create test data with the `zoninator` fixture, which deletes it afterwards, and never empty the site.
- **Code style**: WordPress coding standards via PHPCS (with VIP rules). Tabs for indentation.
- **i18n**: All user-facing strings must use the `zoninator` text domain.

## Architectural Decisions

- **Taxonomy and post meta storage**: Zones are custom taxonomy terms; each post's position in a zone is post meta, which the zone query orders by. This uses WordPress's built-in caching and query infrastructure. Do not switch to custom database tables.
- **Template tags in `functions.php`**: Theme developers consume zones through these functions. They are part of the public API — do not rename or change signatures without a deprecation cycle.
- **Namespaces and layers**: Classes go under `Automattic\Zoninator\`, in `src/`, named and placed for PSR-4 (`Automattic\Zoninator\Infrastructure\WordPress\Feed` is `src/Infrastructure/WordPress/Feed.php`). A small autoloader in `zoninator.php` loads them on first use, as the release package has no Composer autoloader. The layers follow `plugin-standards/ARCHITECTURE.md` and legacy-redirector: `Domain` (zone rules, no WordPress), `Application` (use cases) and `Infrastructure` (WordPress and wiring). Today every class is in `Infrastructure`, because each one calls WordPress; the `Domain` and `Application` layers get filled as that code is separated out. A class may depend on its own layer or one below it, never one above. Classes are `final` unless a site has a reason to extend them. The two legacy global classes, `Zoninator` and `Zoninator_ZonePosts_Widget`, stay in `class-*.php` files loaded with `require_once`.
- **REST controller on core**: The REST API extends core's `WP_REST_Controller` directly. `ZoninatorRestContractTest` pins its routes, methods, args and response bodies; change them only on purpose.
- **Each class registers the hooks it handles** in a `register_hooks()` method, with callbacks on itself. Constructors don't add hooks (except the widget's, as WordPress builds widgets itself); `PluginBootstrapper::register_hooks()` calls each `register_hooks()` once. Sites unhook through `zoninator_container()->get()`, on `plugins_loaded` at priority 20. Moving a callback breaks a site's `remove_action()` silently, so `ZoninatorHooksTest` pins every hook's callback, priority and accepted args; change it only with a row in the upgrade guide's unhooking table.
- **No hidden dependencies**: a class calls another only through an object it was given in its constructor, never through a static method, a global or a template tag. The widget's fallback to the container is the one exception, because WordPress builds it.
- **Dependencies run one way**: `PluginBootstrapper::build()` builds every class by hand and passes each the objects it needs in its constructor. It's a small container of its own, not a library: the release ships without Composer's autoloader, and a bundled container would need scoping to avoid clashing with other plugins. Add a class by building it in `PluginBootstrapper::build()`, or as a factory there if only some requests need it. No class takes or calls `Zoninator`, and `ZoninatorDependencyTest` checks the constructors.
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
- **Prefix every hook `zoninator_`**: 1.0.0 renamed the last hook without it, `zone_posts_widget_block_save_cache_seconds`. Search the codebase before naming a new one.
