# Zoninator

Content zone management for WordPress editorial workflows.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `zoninator.php` |
| **Text domain** | `zoninator` |
| **Function prefix** | `zoninator_` |
| **Namespace** | Global (legacy) |
| **Source directory** | Root level + `src/` |
| **Version** | 0.8.0 |
| **Requires PHP** | 7.4+ |

### Directory Structure

```
zoninator/
├── zoninator.php           # Main plugin file and Zoninator class
├── functions.php            # Helper functions
├── src/                    # Modern code additions
├── css/                    # Admin stylesheets
├── js/                     # Admin JavaScript (zone management UI)
├── bin/                    # Build/utility scripts
├── tests/                  # Integration tests
├── languages/              # Translation files
├── .github/workflows/      # CI: cs-lint, deploy, integration
└── .phpcs.xml.dist         # PHPCS configuration
```

### Key Classes and Files

- `zoninator.php` — Main `Zoninator` class: zone CRUD, post assignment, admin UI
- `functions.php` — Template tag functions for theme developers
- Zone data stored as taxonomy terms with post relationships

### Dependencies

- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`

## Commands

```bash
composer cs                # Check code standards (PHPCS)
composer cs-fix            # Auto-fix code standard violations
composer lint              # PHP syntax lint
composer test:integration  # Run integration tests (requires wp-env)
composer test:integration-ms  # Run multisite integration tests
composer coverage          # Run tests with HTML coverage report
```

**Note:** This plugin has integration tests only — no separate unit test suite.

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details. Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Testing**: Integration tests only in this plugin. Use the existing test patterns. New tests should follow the integration test approach.
- **Code style**: WordPress coding standards via PHPCS. Tabs for indentation.
- **i18n**: All user-facing strings must use the `zoninator` text domain.

## Architectural Decisions

- **Taxonomy-based storage**: Zones are stored as custom taxonomy terms, and posts are assigned to zones via term relationships. This leverages WordPress's built-in taxonomy infrastructure rather than custom tables. Do not switch to custom database tables.
- **Template tags in functions.php**: Theme developers use functions from `functions.php` to display zone content. These are part of the public API — do not rename or remove them without a deprecation cycle.
- **Global namespace (legacy)**: The main class and functions are in the global namespace. This is legacy — do not introduce namespaced classes alongside global ones without a migration plan.
- **WordPress.org deployment**: Has a deploy workflow for WordPress.org SVN. Do not manually modify SVN assets.
## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx wp-env start` running first.
- **Template tags are public API**: Functions in `functions.php` (e.g., `z_get_zone_posts()`, `z_get_posts_in_zone()`) are used by themes in production. Do not rename, remove, or change their signatures without a deprecation cycle.
- Zone ordering is significant — posts within a zone have a specific display order. Ensure any query modifications preserve ordering.
- The admin UI uses jQuery-based drag-and-drop for zone ordering. Test UI changes in the browser, not just with automated tests.
- Do not create custom database tables for zone storage. The taxonomy-based approach is intentional and integrates well with WordPress caching and query infrastructure.
