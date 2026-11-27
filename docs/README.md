# Zoninator documentation

What Zoninator does, and how to install it, is covered in the [README](../README.md). These pages go further.

## Showing zones on your site

- [Theme developer guide](theme-developers.md): the template tags, the Zone Posts block and widget, and the JSON feed.

## Extending Zoninator

- [Filters and actions](hooks.md): every hook the plugin provides.
- [Changing zones from PHP](php-api.md): creating zones and changing their posts in code, and unhooking Zoninator's own callbacks.
- [REST API](rest-api.md): the `zoninator/v1` endpoints, for headless and decoupled integrations.
- [Abilities](abilities.md): reading zones and changing their posts from AI agents and other tools.

## Upgrading

- [Upgrading](upgrades.md): which guide to read for the version you're on.
- [Upgrading to 1.0](upgrading-to-1.0.md): from 0.12.x.
- [Replacing the `Zoninator` class](replacing-zoninator-class.md): the replacement for each of its methods and properties, which 1.0.0 removes.
- [Upgrading to 0.12](upgrading-to-0.12.md): from 0.11.x or earlier.

For every change in each release, see the [CHANGELOG](../CHANGELOG.md).

## Contributing

- [Contributing guide](../CONTRIBUTING.md): local environment, tests, coding standards and pull requests.
- [AGENTS.md](../AGENTS.md): the codebase's structure and architectural decisions, for coding agents and people alike.
- [Security policy](../.github/SECURITY.md): how to report a vulnerability.

## Concepts

- **Zone:** a named, ordered list of posts that editors curate, such as "Homepage" or "Top stories". Stored as a term in the `zoninator_zones` taxonomy.
- **Zones screen:** the **Zones** page in wp-admin, where editors create zones and add, remove and reorder their posts.
- **Zone lock:** while someone edits a zone on the Zones screen, it's locked, so no one else can change it until they've finished.
- **Post types that support zones:** posts, and any post type with `zoninator_zones` support. Only these can be added to a zone.
