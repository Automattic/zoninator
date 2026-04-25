# Contributing to Zoninator

Thanks for your interest in contributing. This document covers the practical bits: where to file what, how to set up a local environment, and what we expect from a pull request.

## Where things go

| Type of report | Where |
|---|---|
| Bug report | [GitHub issue](https://github.com/Automattic/zoninator/issues) |
| Feature request | [GitHub issue](https://github.com/Automattic/zoninator/issues) |
| Security vulnerability | [HackerOne](https://hackerone.com/automattic) — see [SECURITY.md](SECURITY.md) |
| Support question | [WordPress.org support forum](https://wordpress.org/support/plugin/zoninator/) |

## Filing a useful bug report

The more specific you are, the easier it is to fix the bug. A good report includes:

1. **Versions** — Zoninator, WordPress, PHP, single-site or multisite.
2. **Reproduction steps** — start from a known state ("with all other plugins disabled and a default theme active…").
3. **Expected vs actual behaviour** — even when it feels obvious, write it down.
4. **A screenshot or short clip** if it's a UI issue.

Before opening an issue, please check the [existing issues](https://github.com/Automattic/zoninator/issues) and the [WordPress.org support forum](https://wordpress.org/support/plugin/zoninator/) — your report might already be there.

## Pull requests

- Branch from `develop`. Releases are merged into `main` and tagged from there.
- Use a descriptive branch name: `fix/zone-lock-race`, `feature/cli-zone-create`, etc.
- Keep the change focused. If you spot a separate issue while working on a fix, open a separate PR.
- Include tests for behaviour changes. See [tests/README.md](tests/README.md) for how to run them.
- Make sure `composer cs` and `composer test:integration` pass locally before pushing.
- Write a clear commit message — the "why" matters more than the "what". The first line is a summary; details go below.

## Local development

The plugin uses [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) for its development environment, which requires Docker.

```bash
git clone git@github.com:Automattic/zoninator.git
cd zoninator
composer install
npx wp-env start
```

`wp-env start` boots two WordPress containers (one for development, one for tests) and mounts the plugin into both. Visit http://localhost:8888 to use the dev site (admin: `admin` / `password`).

To stop the environment:

```bash
npx wp-env stop
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
| JSON feed at `/zones/{slug}/feed.json` | `src/class-zoninator.php` |
| Zone Posts widget | `src/class-zoninator-zoneposts-widget.php` |

## Repository layout and architecture

For an overview of the codebase, see [AGENTS.md](AGENTS.md). It documents the directory structure, key classes, storage model, and architectural decisions worth knowing before opening a PR.
