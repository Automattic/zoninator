# Contributing to Zoninator

Thanks for your interest in contributing. This document covers the practical bits: where to file what, how to set up a local environment, and what we expect from a pull request.

## Where things go

| Type of report | Where |
|---|---|
| Bug report | [GitHub issue](https://github.com/Automattic/zoninator/issues) |
| Feature request | [GitHub issue](https://github.com/Automattic/zoninator/issues) |
| Security vulnerability | [HackerOne](https://hackerone.com/automattic) — see [SECURITY.md](.github/SECURITY.md) |
| Support question | [WordPress.org support forum](https://wordpress.org/support/plugin/zoninator/) |

## Filing a useful bug report

The more specific you are, the easier it is to fix the bug. A good report includes:

1. **Versions** — Zoninator, WordPress, PHP, single-site or multisite.
2. **Reproduction steps** — start from a known state ("with all other plugins disabled and a default theme active…").
3. **Expected vs actual behavior** — even when it feels obvious, write it down.
4. **A screenshot or short clip** if it's a UI issue.

Before opening an issue, please check the [existing issues](https://github.com/Automattic/zoninator/issues) and the [WordPress.org support forum](https://wordpress.org/support/plugin/zoninator/) — your report might already be there.

## Pull requests

- Branch from `develop`. Releases are merged into `main` and tagged from there.
- Use a descriptive branch name: `fix/zone-lock-race`, `feature/zone-post-search`, etc.
- Keep the change focused. If you spot a separate issue while working on a fix, open a separate PR.
- Include tests for behavior changes. See [tests/README.md](tests/README.md) for how to run them.
- Make sure `composer cs`, `composer test:unit` and `composer test:integration` pass locally before pushing. If you change the Zones screen, run `npm run test:e2e` too.
- Write a clear commit message — the "why" matters more than the "what". The first line is a summary; details go below.
- **Sign your commits.** `develop` and `main` only accept commits with a verified signature, so a pull request with an unsigned commit cannot be merged until it is re-signed and force-pushed. See GitHub's guide to [signing commits](https://docs.github.com/en/authentication/managing-commit-signature-verification/signing-commits).

## Local development

The plugin uses [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) for its development environment, which requires Docker.

```bash
git clone git@github.com:Automattic/zoninator.git
cd zoninator
composer install
npm ci
npx --no wp-env start
```

`wp-env start` boots one WordPress environment, on WordPress trunk and the latest PHP, and mounts the plugin into it. The integration tests run in the same environment. Visit http://localhost:8888 to use the site (admin: `admin` / `password`).

To work on another WordPress or PHP version, set `WP_ENV_CORE` and `WP_ENV_PHP_VERSION` when you start it, as CI does:

```bash
WP_ENV_CORE=WordPress/WordPress#6.9 WP_ENV_PHP_VERSION=8.2 npx --no wp-env start
```

To run the end-to-end tests against it in a browser (see [tests/README.md](tests/README.md#end-to-end-tests)):

```bash
npx --no playwright install chromium
npm run test:e2e
```

To stop the environment:

```bash
npx --no wp-env stop
```

## Coding standards

- Code follows the [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/) as enforced by `automattic/vipwpcs`.
- Tabs for indentation.
- All user-facing strings use the `zoninator` text domain.
- Inline documentation follows the [WordPress documentation standards](https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/).
- Run `composer cs-fix` to auto-fix what can be auto-fixed; `composer cs` to check the rest.

## Public API

Treat anything in the table below as a public contract. Renaming, removing, or changing the signature of these surfaces is a breaking change and requires a deprecation cycle.

| Surface | Where |
|---|---|
| Template tag functions (`z_*`) | `functions.php` |
| Hooks (`zoninator_*` filters and actions) | See [docs/hooks.md](docs/hooks.md) |
| REST namespace `zoninator/v1` | See [docs/rest-api.md](docs/rest-api.md) |
| JSON feed at `/zones/{slug}/feed.json` | `src/Infrastructure/WordPress/Feed.php` |
| Zone Posts widget | `src/Infrastructure/WordPress/ZonePostsWidget.php` |

## Repository layout and architecture

For an overview of the codebase, see [AGENTS.md](AGENTS.md). It documents the directory structure, key classes, storage model, and architectural decisions worth knowing before opening a PR.
