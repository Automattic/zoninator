# Zoninator

Content zone management for WordPress editorial workflows.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `zoninator.php` |
| **Text domain** | `zoninator` |
| **Function prefix** | `zoninator_` (template tags and hooks) |
| **Class prefix** | `Automattic\Zoninator\`; there are no global classes |
| **Source directory** | Root level + `src/` |
| **Requires PHP** | 8.2+ |
| **Requires WP** | 6.9+ |

### Directory Structure

```
zoninator/
├── zoninator.php                    # Main plugin file (header, bootstrap)
├── functions.php                    # Template tag functions (zoninator_*)
├── src/
│   ├── Domain/                      # Zone rules, with no WordPress: entities, and interfaces for storage
│   │   ├── ZoneLock.php             # A zone's lock: who holds it, who it keeps out, how long it lasts
│   │   ├── LockStoreInterface.php   # Where locks are kept
│   │   ├── LockSettingsInterface.php # How long a lock is valid for
│   │   ├── ZoneLockedException.php  # Someone else holds the zone's lock
│   │   ├── Zone.php                 # A zone, and the rules for making and changing one
│   │   ├── ZoneRepositoryInterface.php # Where zones are kept
│   │   ├── PostIds.php              # Reads the post IDs a caller gives, refusing anything that isn't one
│   │   ├── ZonePostsRepositoryInterface.php # Where the posts in each zone are kept
│   │   ├── ZoneSettingsInterface.php # Which details every zone has
│   │   ├── ZoneAccessInterface.php  # What a user may do to zones
│   │   ├── *Exception.php           # InvalidZone, ZoneNotFound, ZoneNotSaved, ZoneFull, AccessDenied, PostNotAllowed
│   │   └── ZoninatorExceptionInterface.php # What every one of those exceptions implements
│   ├── Application/                 # Use cases, with no WordPress
│   │   ├── ZoneLocker.php           # Locks, checks and unlocks zones
│   │   ├── ZoneManager.php          # Creates, changes and deletes zones
│   │   ├── ZoneCurator.php          # Adds, orders and removes the posts in a zone
│   │   ├── ZoneGuard.php            # The checks a change made for a user must pass
│   │   ├── GuardedZoneManager.php   # ZoneManager, with those checks
│   │   └── GuardedZoneCurator.php   # ZoneCurator, with those checks
│   ├── Infrastructure/              # Everything that talks to WordPress, and the wiring
│   │   ├── DI/
│   │   │   └── Container.php        # Holds the built objects by class name
│   │   └── WordPress/
│   │       ├── PluginBootstrapper.php # The composition root; builds every class and registers hooks
│   │       ├── Settings.php         # Filtered values: lock periods, results per page, detail defaults, post limit
│   │       ├── Capabilities.php     # Who may add, edit and manage zones
│   │       ├── Errors.php           # The WP_Error for each thing that can go wrong
│   │       ├── Taxonomy.php         # Registers the zone taxonomy; which post types support zones
│   │       ├── Zones.php            # Reads zones and their posts as WP_Term, WP_Post and WP_Query
│   │       ├── TermZoneRepository.php # Keeps zones as taxonomy terms
│   │       ├── PostMetaZonePostsRepository.php # Keeps each post's place in a zone as post meta
│   │       ├── TransientLockStore.php # Keeps zone locks in transients
│   │       ├── Feed.php             # The JSON feed's data: a zone's published posts, feed fields only
│   │       ├── FeedEndpoint.php     # Serves the feed at /zones/{slug}/feed.json
│   │       ├── Search.php           # Finds posts to offer for a zone
│   │       ├── Block.php            # The Zone Posts Query Loop variation's queries and editor script
│   │       ├── Abilities.php        # Read-only Abilities API abilities
│   │       ├── CurationAbilities.php # Abilities that change a zone's posts
│   │       ├── Widgets.php          # Registers the Zone Posts widget
│   │       ├── ZonePostsWidget.php  # The classic Zone Posts widget
│   │       ├── Admin/               # The Zones admin screen
│   │       │   ├── Screen.php           # Adds Zones to the admin menu, and shows the screen: loads the data, hands it to the view
│   │       │   ├── ScreenData.php       # What the screen shows for one request
│   │       │   ├── ScreenDataLoader.php # Gathers a ScreenData for the current user
│   │       │   ├── ScreenView.php       # Prints the page: title, notices, zone list, and the open zone's editor
│   │       │   ├── ZoneDetailsView.php  # Prints a zone's name and description, as a form or text
│   │       │   ├── ZonePostRow.php      # Prints one post's row
│   │       │   ├── SearchForm.php       # Prints the "Add content" field and its filters
│   │       │   ├── ScreenLocation.php   # The screen's slug, URL and nonce actions
│   │       │   ├── Messages.php         # The screen's notices
│   │       │   ├── Assets.php           # Loads the screen's script and styles
│   │       │   ├── ZoneForm.php         # Saves the zone form
│   │       │   └── ZonePostRowController.php # The screen's own route for a row's HTML
│   │       └── Rest/                # The zoninator/v1 REST API
│   │           ├── ZonesController.php     # /zones and /zones/{zone_id}
│   │           ├── ZonePostsController.php # A zone's posts
│   │           ├── SearchController.php    # /search
│   │           ├── LockController.php      # A zone's lock
│   │           ├── Permissions.php         # The routes' permission callbacks
│   │           ├── RegistersRoutes.php     # Trait: the namespace, the hook and the zone ID arg
│   │           └── ErrorCodes.php          # The error codes clients match on
├── css/                             # Admin stylesheets
├── js/                              # Admin JavaScript (jQuery sortable, autocomplete) and the block editor script
├── tests/
│   ├── bootstrap.php                # PHPUnit bootstrap (yoast/wp-test-utils)
│   ├── Unit/                        # Unit tests of Domain and Application, which need no WordPress
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

- `zoninator.php` — Loads the template tags, registers the autoloader, and defines `zoninator_container()`. That function boots the plugin the first time it's called, by making a `Container` and calling `PluginBootstrapper::boot()`, and returns the container. Boot happens on `plugins_loaded` (straight away if that has fired, for themes that load plugins late), or earlier if `zoninator_container()` or a template tag is called first.
- `src/Infrastructure/WordPress/PluginBootstrapper.php` — `Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper`: the composition root. `build()` builds every class by hand, in dependency order, and adds each to the container. `register_hooks()` calls `register_hooks()` on each class in the container that has that method, found with `method_exists()`, and adds none of its own. `boot()` runs both.
- `src/Infrastructure/DI/Container.php` — `Automattic\Zoninator\Infrastructure\DI\Container`: holds objects by class name and knows nothing about them. `add()` stores an object, `get()` and `has()` are shaped like PSR-11's, and `all()` gives the bootstrapper every object. Sites reach the object that handles a hook through `zoninator_container()->get( FeedEndpoint::class )` and the like. Under `Infrastructure`, it, `ZonePostsWidget` and `Rest\ErrorCodes` are the only classes that aren't `@internal`; `Domain` and `Application` are public.
- `src/Infrastructure/WordPress/Settings.php` — `Automattic\Zoninator\Infrastructure\WordPress\Settings`: the lock periods, results per page, zone detail defaults and post limit, each filtered when read.
- `src/Infrastructure/WordPress/Capabilities.php` — `Automattic\Zoninator\Infrastructure\WordPress\Capabilities`: what a user may do to zones and which posts they may use, through the capability filters. It implements `Domain\ZoneAccessInterface` and has no other checks: each takes a user ID, and an entry point acting for whoever is logged in passes `get_current_user_id()`.
- `src/Infrastructure/WordPress/Errors.php` — `Automattic\Zoninator\Infrastructure\WordPress\Errors`: builds the `WP_Error` for each thing that can go wrong, with its code, message and status. `from_exception()` turns a `Domain` exception into its error. **The REST controllers, the abilities and the zone form all get their errors here**, so they can't word or code the same refusal differently; don't build a `WP_Error` for a refused change anywhere else.
- `src/Infrastructure/WordPress/Taxonomy.php` — `Automattic\Zoninator\Infrastructure\WordPress\Taxonomy`: registers the `zoninator_zones` taxonomy (`Taxonomy::NAME`) on `init`, with no object types, and gives posts zone support when the plugin boots. A post type supports zones if it has the `zoninator_zones` post type feature; `get_post_types()` asks core each time, with no cache.
- `src/Domain/Zone.php` — `Automattic\Zoninator\Domain\Zone`: a zone's ID, slug, name and details, with the rules for making one (`create()`) and changing one (`with_changes()`). It never changes itself. `ZoneRepositoryInterface` and `ZonePostsRepositoryInterface` say what storage must do.
- `src/Application/` — `ZoneManager` creates and changes zones, and deletes one with its posts' places and its lock, and `ZoneCurator` adds, orders and removes the posts in a zone. They take zone and post IDs, return `Zone`, and throw `Domain` exceptions (`InvalidZoneException`, `ZoneNotFoundException`, `ZoneNotSavedException`, `ZoneFullException`), or `InvalidArgumentException` for a post ID that isn't one. Every exception in `Domain` implements `ZoninatorExceptionInterface`, which is what an entry point catches; `tests/Unit/Domain/ExceptionsTest.php` checks that a new one does too.
- `src/Application/ZoneGuard.php` — the one place that checks a change made for a user: the user may make it (`Domain\ZoneAccessInterface`, which `Capabilities` implements), may use each post they put in the zone, and isn't kept out by someone else's lock. A post already in the zone isn't being put in, so it passes whether or not the user may read it: they can order the zone around it, and take it out. `GuardedZoneManager` and `GuardedZoneCurator` run those checks and then call the unguarded classes. **Every entry point that acts for a user (the Zones screen, REST, abilities, any future WP-CLI command) must use the guarded classes**, with the user's ID; the unguarded ones are for code with no user, such as a scheduled job.
- `src/Infrastructure/WordPress/TermZoneRepository.php` and `PostMetaZonePostsRepository.php` — the storage: zones as terms with their details serialized in the description, and each post's place as post meta. They apply `zoninator_insert_zone` and `zoninator_update_zone` and fire `zoninator_delete_zone`, `zoninator_add_zone_posts` and `zoninator_remove_zone_posts`, so every way of changing a zone fires them. `TermZoneRepository` also encodes `&`, `<` and `>` in a zone's name and details when it saves them, and decodes them when it builds a `Zone`. **Don't encode zone text anywhere else**: WordPress filters a term's description on save, and that form is the only one it leaves alone, so the serialized details keep their lengths.
- `src/Infrastructure/WordPress/Zones.php` — `Automattic\Zoninator\Infrastructure\WordPress\Zones`: reads zones and their posts for WordPress code, as `WP_Term`, `WP_Post` and `WP_Query`. The template tags are built on it. It changes nothing. Its methods have return types, but a zone argument stays untyped (an ID, a slug or a term), because themes pass it straight from a template tag and a bad one should give an empty result, not a fatal error. `shows_unpublished_posts()` is the one place it asks whether this is the admin; `WP_Query` asks the same question itself, so a caller can't usefully say it instead.
- `src/Application/ZoneLocker.php` — `Automattic\Zoninator\Application\ZoneLocker`: locks a zone for a user, says who is keeping a user out, and unlocks it. It throws `Domain\ZoneLockedException` when someone else holds the lock; the REST route and the Zones screen turn that into their own responses. `Domain\ZoneLock` holds the rules. `Infrastructure\WordPress\TransientLockStore` keeps the locks in transients, and `Settings` supplies the lock period through `Domain\LockSettingsInterface`.
- `src/Infrastructure/WordPress/Abilities.php` — `Automattic\Zoninator\Infrastructure\WordPress\Abilities`: the read-only Abilities API abilities (`zoninator/list-zones`, `zoninator/get-zone-posts`, `zoninator/search-posts`) in the `zoninator` category. It hooks `wp_abilities_api_categories_init` and `wp_abilities_api_init` in its `register_hooks()`. Like the REST route callbacks, the ability callbacks live on this class; sites remove an ability with `wp_unregister_ability()`.
- `src/Infrastructure/WordPress/CurationAbilities.php` — `Automattic\Zoninator\Infrastructure\WordPress\CurationAbilities`: the abilities that change a zone's posts (`zoninator/add-zone-posts`, `zoninator/set-zone-posts`, `zoninator/remove-zone-posts`), through `GuardedZoneCurator`. Unlike the read-only ones, they aren't public on the MCP adapter's default server.
- `src/Infrastructure/WordPress/Feed.php` — `Automattic\Zoninator\Infrastructure\WordPress\Feed`: a zone's published posts as the JSON feed lists them, limited to the feed's fields and passed through its filters. It has no hooks. `FeedEndpoint` and the REST route `GET /zones/{zone_id}/posts` both return it.
- `src/Infrastructure/WordPress/FeedEndpoint.php` — `Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint`: the rewrite rule and response for the JSON feed at `/zones/{slug}/feed.json`.
- `src/Infrastructure/WordPress/Search.php` — `Automattic\Zoninator\Infrastructure\WordPress\Search`: finds posts to offer for a zone, by text or the latest content. The REST search route and the `zoninator/search-posts` ability both call its `find_posts()`.
- `src/Infrastructure/WordPress/Block.php` — `Automattic\Zoninator\Infrastructure\WordPress\Block`: the Zone Posts variation of core's Query Loop block. `js/zoninator-block.js` (plain JS, no build step) registers the variation and its zone picker, which saves the zone ID as `zoninatorZone` in the block's query. `Block` turns that into `Zones::get_query_args()` on `query_loop_block_query_vars` for the front end and on `rest_post_query` for the editor preview.
- `src/Infrastructure/WordPress/Admin/` — the Zones screen (`admin.php?page=zoninator`), split so that one class gathers what to show and the rest only print it. `Screen` adds the menu item, and its `render()` is the menu page's callback: it reads the zone and notice the request asks for, has `ScreenDataLoader` build a `ScreenData` (the zones, the open zone, its posts, who is editing it and their name, what the user may do), and hands it to `ScreenView`. `ScreenView` prints the page, the zone list and the open zone's editor, and uses `SearchForm`, `ZonePostRow` and `ZoneDetailsView`. **`ScreenView` and `ZoneDetailsView` take `ScreenData` and decide nothing: they make no capability check and no query.** If a view needs something more, `ScreenDataLoader` loads it. `ZonePostRow` is the exception, because its columns are `zoninator_zone_post_columns` callbacks, which are given a post and a zone and nothing else: it looks up the post's position and its edit link itself. It also checks that the user may read the post, and prints a row that says nothing about one they may not, with only a Remove button, without running the callbacks. `ScreenLocation` has the slug, URL and nonce action names, `Messages` the notices and `Assets` the script and styles.
- `src/Infrastructure/WordPress/Admin/ZonePostRow.php` and `ZonePostRowController.php` — `ZonePostRow` renders one post's row, running the `zoninator_zone_post_columns` callbacks. `ZonePostRowController` is the screen's own route, `GET /zoninator/v1/zones/{zone_id}/posts/{post_id}/row`, which the screen's JavaScript calls after it adds a post. **The REST controllers under `Rest/` return data only and must not depend on anything under `Admin/`**; HTML for the screen comes from routes the screen owns.
- `src/Infrastructure/WordPress/Admin/ZoneForm.php` — saves the screen's zone form (create, update, delete) through `GuardedZoneManager`, then redirects. It checks the nonce with `check_admin_referer()` and reads its own fields from the request. It makes no capability check: `GuardedZoneManager` refuses a user who may not, and the form stops the request when it does.
- `functions.php` — The template tags themes use to read zones: `zoninator_get_zones()`, `zoninator_get_zone()`, `zoninator_get_posts_in_zone()`, `zoninator_get_zone_query()`, `zoninator_get_next_post_in_zone()`, `zoninator_get_prev_post_in_zone()` and `zoninator_get_post_zones()`, plus `zoninator_disable_locks()`. They return `WP_Term`, `WP_Post` and `WP_Query`. Before 1.0.0 they began with `z_`.
- `src/Infrastructure/WordPress/ZonePostsWidget.php` — `Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget`: the classic widget. It's public and not `final`: themes print it with `the_widget( ZonePostsWidget::class )`, and sites extend it. WordPress builds widgets from their class names, with no arguments, so it gets `Zones` from `zoninator_container()` when it needs it, as the template tags do. `Infrastructure\WordPress\Widgets` registers it on `widgets_init`; the widget itself adds no hooks. It has no output cache: core caches the zone's query, and `test_a_second_render_runs_no_queries` checks that stays true. Before 1.0.0 it was the global class `Zoninator_ZonePosts_Widget`. Don't change `ZonePostsWidget::ID_BASE`: saved widgets and their places in sidebars are keyed by it.
- `src/Infrastructure/WordPress/Rest/` — the `zoninator/v1` REST API, one `WP_REST_Controller` for each resource: `ZonesController` (`/zones`, `/zones/{zone_id}`), `ZonePostsController` (a zone's posts), `SearchController` (`/search`) and `LockController` (a zone's lock). Each takes what it works on plus `Permissions`, which holds the routes' permission callbacks. The `RegistersRoutes` trait gives them the namespace, their `register_hooks()` and the zone ID arg. A handler returns `rest_ensure_response()` or a `WP_REST_Response`, and gets every error from `Errors`. Route args have a `type`, so core validates and sanitizes them; a controller adds a callback only where core's check is looser than the API's, as with `post_ids`. `ErrorCodes` holds the error codes clients match on. Each controller's `register_hooks()` hooks its `register_routes()` on `rest_api_init`, so a site can unhook one controller's routes.
- `tests/Integration/TestCase.php` — Base class for integration tests. Its static helpers (`zones()`, `locker()`, `taxonomy()`, `settings()` and `feed()`) return the plugin's own objects from the container.

### Storage model

Zones are terms in the `zoninator_zones` taxonomy, with their details (such as the description) serialized in the term description, with `&`, `<` and `>` encoded and quotes left as they are. A post joins a zone through post meta: the key is `_zoninator_order_{zone_id}` (`Zones::META_PREFIX` plus the zone ID) and the value is its position. There are no term relationships between posts and zones. The taxonomy is non-public and non-queryable — it exists purely as a data store.

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
composer test:unit               # Run unit tests (no WordPress or wp-env needed)
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

**Note:** Unit tests cover the `Domain` and `Application` layers, with stand-ins for storage. Integration and E2E tests cover everything that touches WordPress.

## Conventions

See `CONTRIBUTING.md` for setup, checks and the contribution workflow. Key points:

- **Commits**: Every commit must be signed; `develop` and `main` reject unsigned commits. Use a [Conventional Commits](https://www.conventionalcommits.org/) prefix (`fix:`, `feat:`, `test:`, `docs:`, `ci:`, `chore:` and so on) with an imperative subject of about 50 characters, and a body that explains why the change was needed rather than what changed. One concern per commit.
- **PRs**: Open against `develop`. The title describes the outcome in sentence case, without a conventional commit prefix. The description explains the problem first, then how the PR solves it. PRs land as merge commits (squash and rebase merging are disabled), so every commit message reaches `develop` as written.
- **Testing**: Unit tests in `tests/Unit/` extend PHPUnit's `TestCase` and run with `composer test:unit`, without WordPress; put a stand-in for an interface in `tests/Unit/Doubles/`. A stand-in for storage must pass the same tests as the real one: `tests/ZonePostsRepositoryContract.php` is a trait of tests that both `InMemoryZonePostsRepositoryTest` and the integration test `PostMetaZonePostsRepositoryTest` use, and `tests/LockStoreContract.php` does the same for `InMemoryLockStoreTest` and `TransientLockStoreTest`. An integration test that only repeats a unit test belongs in the unit test instead. Integration tests run inside `wp-env` against a real WordPress install. Extend `Automattic\Zoninator\Tests\Integration\TestCase`. E2E tests in `tests/e2e/` cover only what a browser shows on the Zones screen; they share the development site's database, so create test data with the `zoninator` fixture, which deletes it afterwards, and never empty the site.
- **Code style**: WordPress coding standards via PHPCS (with VIP rules). Tabs for indentation.
- **Imports**: Name a class from another namespace with a `use` statement at the top of the file, then its short name, in code and docblocks alike. Write a fully qualified name inline only where a `use` can't help, such as an example of code a site would write.
- **i18n**: All user-facing strings must use the `zoninator` text domain.

## Architectural Decisions

- **Taxonomy and post meta storage**: Zones are custom taxonomy terms; each post's position in a zone is post meta, which the zone query orders by. This uses WordPress's built-in caching and query infrastructure. Do not switch to custom database tables.
- **Template tags in `functions.php`**: Theme developers consume zones through these functions. They are part of the public API: don't rename them or change their signatures outside a major release, and put any change in the upgrade guide.
- **Namespaces and layers**: Classes go under `Automattic\Zoninator\`, in `src/`, named and placed for PSR-4 (`Automattic\Zoninator\Infrastructure\WordPress\Feed` is `src/Infrastructure/WordPress/Feed.php`). A small autoloader in `zoninator.php` loads them on first use, as the release package has no Composer autoloader. The layers are `Domain` (zone rules, no WordPress), `Application` (use cases) and `Infrastructure` (WordPress and wiring). `Domain` and `Application` must run without WordPress: `Domain` names no other layer, `Application` names only `Domain`, and `tests/Unit/LayersTest.php` checks both. Every file under `src/` declares `strict_types`, and every class keeps what it's given as promoted `readonly` constructor properties. Every method declares its return type, `void` included. The exceptions are deliberate: `ZonePostsWidget`'s `widget()`, `update()` and `form()`, because a site's subclass that overrides one without a type would stop with a fatal error, and `Block`'s two filter callbacks, which hand back what they were given. `Domain` and `Application` report failure by throwing a domain exception, which `Infrastructure` turns into a `WP_Error` or a response. Zone locks, and creating, changing, deleting and curating zones, are there so far. Reading zones and their posts is still in `Infrastructure`, because themes are given `WP_Term`, `WP_Post` and `WP_Query`. Classes are `final` unless a site has a reason to extend them.
- **REST controllers on core**: Each REST controller extends core's `WP_REST_Controller` directly, and covers one resource. A controller parses the request, calls an `Application` class (a guarded one for any change) and builds the response; it holds no rules of its own. `ZoninatorRestContractTest` pins its routes, methods, args and response bodies; change them only on purpose.
- **Each class registers the hooks it handles** in a `register_hooks()` method, with callbacks on itself. Constructors don't add hooks; `PluginBootstrapper::register_hooks()` calls each `register_hooks()` once. Sites unhook through `zoninator_container()->get()`, on `plugins_loaded` at priority 20. Moving a callback breaks a site's `remove_action()` silently, so `ZoninatorHooksTest` pins every hook's callback, priority and accepted args; change it only on purpose, updating the table in `docs/php-api.md` and, for a site's existing `remove_action()`, the upgrade guide.
- **No hidden dependencies**: a class calls another only through an object it was given in its constructor, never through a static method, a global or a template tag. The template tags and the Zone Posts widget are the exceptions: WordPress or a theme calls them with no way to pass anything in, so they ask `zoninator_container()`.
- **Dependencies run one way**: `PluginBootstrapper::build()` builds every class by hand and passes each the objects it needs in its constructor. It's a small container of its own, not a library: the release ships without Composer's autoloader, and a bundled container would need scoping to avoid clashing with other plugins. Add a class by building it in `PluginBootstrapper::build()`. If it has a `register_hooks()` method, the bootstrapper calls it, so there's no list to add it to; its hooks are added in build order. `tests/Unit/LayersTest.php` checks that `Domain` and `Application` don't name a layer above them.
- **REST `/zones` index is for people who work on content**: `GET /zoninator/v1/zones` needs a user who can `edit_posts`, which the block editor's zone picker relies on, or who can curate zones. From 0.11.0 to 0.12.x it needed any logged-in user. Sites that want it open, to subscribers or to anyone, opt in via the `zoninator_rest_get_zones_permissions_check` filter.
- **WordPress.org deployment**: Releases push to the WP.org SVN via `.github/workflows/deploy.yml` on GitHub release. Do not manually modify SVN assets.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx --no wp-env start` running first. Run `npm ci` before it, so `npx` uses the `@wordpress/env` pinned in `package.json`. Always keep `--no`: without an installed `@wordpress/env`, plain `npx wp-env` fetches the unrelated `wp-env` package from npm.
- **The public PHP API is the template tags and the `Application` classes**: themes call the functions in `functions.php` in production, and sites change zones through `ZoneManager`, `ZoneCurator` and `ZoneLocker`. Don't rename them, remove them or change their signatures outside a major release, and put any change in the upgrade guide. There is no `Zoninator` class or `Zoninator()` function any more.
- **Zone ordering is significant**: posts within a zone have a specific display order. Preserve it through any query modifications.
- **Admin UI uses jQuery drag-and-drop**: test UI changes in a browser, not just with automated tests.
- **`zoninator_zones` taxonomy is intentionally not public**: it has no front-end archive. Don't flip `public => true` to "fix" missing archives — themes are expected to render zones via template tags such as `zoninator_get_zone_query()`.
- **Version strings in four files**: bump `zoninator.php` (header + `ZONINATOR_VERSION`), `package.json`, `package-lock.json`, and the `Stable tag` in `README.md`. The `npm run bump:*` scripts cover most of this.
- **Prefix every hook `zoninator_`**: every hook has the prefix. Search the codebase before naming a new one.
