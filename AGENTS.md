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
| **Version** | 0.11.1 |
| **Requires PHP** | 7.4+ |
| **Requires WP** | 6.4+ |

### Directory Structure

```
zoninator/
├── zoninator.php                    # Main plugin file (header, bootstrap)
├── functions.php                    # Template tag functions (z_*)
├── src/
│   ├── class-zoninator.php          # Main Zoninator class (zone CRUD, admin UI, AJAX)
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
├── .github/workflows/               # CI: cs-lint, integration, deploy
└── .phpcs.xml.dist                  # PHPCS configuration
```

### Key Classes and Files

- `src/class-zoninator.php` — `Zoninator` class: registers the `zoninator_zones` taxonomy, owns the admin screen, handles AJAX (search, lock, ordering), and exposes the JSON feed at `/zones/{slug}/feed.json`.
- `functions.php` — Public template tags: `z_get_zones()`, `z_get_zone()`, `z_get_posts_in_zone()`, `z_get_zone_query()`, `z_get_next_post_in_zone()`, `z_get_prev_post_in_zone()`, `z_get_post_zones()`. Used by themes in production.
- `src/class-zoninator-zoneposts-widget.php` — Classic widget (`Zoninator_ZonePosts_Widget`).
- `src/rest/class-zones-controller.php` — `Automattic\Zoninator\REST\Zones_Controller`, a `WP_REST_Controller` that registers the `zoninator/v1` routes. `Zoninator::register_rest_routes()` loads it on `rest_api_init`.
- `tests/Integration/TestCase.php` — Base class for integration tests.

### Storage model

Zones are taxonomy terms in the `zoninator_zones` taxonomy. Posts join a zone through term relationships, with order persisted in term meta keyed per post. The taxonomy is non-public and non-queryable — it exists purely as a data store.

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

- **Taxonomy-based storage**: Zones are stored as custom taxonomy terms; posts are assigned via term relationships and ordered via term meta. This leverages WordPress's built-in caching and query infrastructure. Do not switch to custom database tables.
- **Template tags in `functions.php`**: Theme developers consume zones through these functions. They are part of the public API — do not rename or change signatures without a deprecation cycle.
- **Namespaces**: Legacy classes and functions are in the global namespace; keep their names, as themes and integrations use them. New classes go under `Automattic\Zoninator\`, in WordPress-style `class-*.php` files loaded with `require_once` (the release package has no Composer autoloader).
- **REST controller on core**: The REST API extends core's `WP_REST_Controller` directly. `ZoninatorRestContractTest` pins its routes, methods, args and response bodies; change them only on purpose.
- **REST `/zones` index requires auth**: Since 0.11.0 the `GET /zoninator/v1/zones` endpoint requires `is_user_logged_in()` by default. Sites needing the historical anonymous behaviour must opt in via the `zoninator_rest_get_zones_permissions_check` filter.
- **WordPress.org deployment**: Releases push to the WP.org SVN via `.github/workflows/deploy.yml` on GitHub release. Do not manually modify SVN assets.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx --no wp-env start` running first. Run `npm ci` before it, so `npx` uses the `@wordpress/env` pinned in `package.json`. Always keep `--no`: without an installed `@wordpress/env`, plain `npx wp-env` fetches the unrelated `wp-env` package from npm.
- **Template tags are public API**: Functions in `functions.php` (e.g., `z_get_posts_in_zone()`) are used by themes in production. Do not rename, remove, or change their signatures without a deprecation cycle.
- **Zone ordering is significant**: posts within a zone have a specific display order. Preserve it through any query modifications.
- **Admin UI uses jQuery drag-and-drop**: test UI changes in a browser, not just with automated tests.
- **`zoninator_zones` taxonomy is intentionally not public**: it has no front-end archive. Don't flip `public => true` to "fix" missing archives — themes are expected to render zones via template tags or `z_get_zone_query()`.
- **Two version strings**: bump `zoninator.php` (header + `ZONINATOR_VERSION`), `package.json`, `package-lock.json`, and the `Stable tag` in `README.md`. The `composer bump:*` scripts cover most of this.
- **Don't add new hooks under the `zone_posts_widget_*` prefix**: that prefix is grandfathered. Use `zoninator_*` for any new hook (search the codebase before naming).
