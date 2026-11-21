# Changelog for the Zoninator WordPress plugin

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.11.1] - 2026-11-21

### Changed

* The zone screen's post search and Add Recent Content list no longer use a `NOT IN` clause to leave out posts already in the zone, which was slow on large posts tables. They fetch up to 100 extra rows instead and drop the zone's posts in PHP. The `zoninator_search_args` and `zoninator_recent_posts_args` filters receive the same arguments as before by @faisalahammad and @GaryJones in https://github.com/Automattic/zoninator/pull/182

### Fixed

* `z_get_zones()` and `Zoninator::get_zones()` no longer cause a fatal error on PHP 8 when the `zoninator_admin_page_get_zones_args` filter sets `fields` to return IDs or names by @faisalahammad in https://github.com/Automattic/zoninator/pull/175
* `Zoninator::get_zones()` no longer uses the `get_terms()` signature deprecated in WordPress 4.5, and always queries the zone taxonomy whatever arguments it's given by @trepmal and @GaryJones in https://github.com/Automattic/zoninator/pull/130

### Security

* The Zones screen's add and reorder handlers and the REST zone posts update only accept posts of a zone post type that the user can read. Sites that lower `zoninator_manage_zone_cap` so authors can curate zones will find authors can no longer add posts they can't read, such as another author's scheduled post by @GaryJones in https://github.com/Automattic/zoninator/pull/183
* `z_get_posts_in_zone()` and the zone query return unpublished posts only on admin screens and only to users who can manage zones. Before, any admin-ajax.php or admin-post.php request, including a logged-out one, got them too by @GaryJones in https://github.com/Automattic/zoninator/pull/183
* The zone lock notice shows the locking user's display name, without linking to their email address by @GaryJones in https://github.com/Automattic/zoninator/pull/183

### Maintenance

* Pushing a version tag now publishes the GitHub Release, with a ZIP and this changelog's entry as its notes, and deploys to WordPress.org by @GaryJones in https://github.com/Automattic/zoninator/pull/184
* Declare support for WordPress 7.1, test against it, and run each CI job's integration tests on the PHP version it names by @GaryJones in https://github.com/Automattic/zoninator/pull/168 and https://github.com/Automattic/zoninator/pull/167
* Run the integration tests on pull requests, including those from forks by @GaryJones in https://github.com/Automattic/zoninator/pull/173
* Develop and test in a single wp-env environment, pinned as a development dependency. The tests use their own tables, so a test run leaves the development site's content alone, the test and coverage scripts work from any checkout directory, and the unused `install-wp-tests.sh` script is gone by @GaryJones and @faisalahammad in https://github.com/Automattic/zoninator/pull/172, https://github.com/Automattic/zoninator/pull/174, https://github.com/Automattic/zoninator/pull/180, and https://github.com/Automattic/zoninator/pull/181
* Look zones up with core's `get_term_by()` instead of the deprecated `wpcom_vip_get_term_by()` by @faisalahammad in https://github.com/Automattic/zoninator/pull/178
* Document that core's query cache already covers zone posts, with tests that changes to a zone show up straight away by @faisalahammad in https://github.com/Automattic/zoninator/pull/177
* Fix the coding standards check for VIPCS 3.1 by @GaryJones in https://github.com/Automattic/zoninator/pull/164
* Generate the POT file with the plugin's slug, whatever the checkout directory is called by @GaryJones in https://github.com/Automattic/zoninator/pull/169
* Describe the plugin plainly in its header and readme by @GaryJones in https://github.com/Automattic/zoninator/pull/170
* Standardize the security policy, document signed commits and merge-commit-only pull requests, and keep AGENTS.md to material in the public repo by @GaryJones in https://github.com/Automattic/zoninator/pull/165, https://github.com/Automattic/zoninator/pull/163, and https://github.com/Automattic/zoninator/pull/171
* Name the pinned version of the WordPress.org deploy action by @GaryJones in https://github.com/Automattic/zoninator/pull/160
* Override vulnerable transitive development dependencies by @lancewillett in https://github.com/Automattic/zoninator/pull/157
* Update GitHub Actions dependencies by @dependabot

## [0.11.0] - 2026-04-25

This release raises the minimum WordPress version to 6.4. The REST `GET /wp-json/zoninator/v1/zones` endpoint now requires an authenticated user; sites that depended on anonymous access can restore the previous behaviour via the new `zoninator_rest_get_zones_permissions_check` filter.

### Security

* fix: Enforce auth and nonce on `zoninator_search_posts` AJAX handler by @GaryJones in https://github.com/Automattic/zoninator/pull/151
* fix: Harden zone description deserialisation and lock down `/zones` REST index by @GaryJones in https://github.com/Automattic/zoninator/pull/149
* fix: Harden admin UI against reflected and stored XSS by @GaryJones in https://github.com/Automattic/zoninator/pull/148

### Fixed

* fix: Small correctness fixes around locks, AJAX, and i18n by @GaryJones in https://github.com/Automattic/zoninator/pull/150

### Changed

* Bump minimum WordPress version to 6.4 by @GaryJones in https://github.com/Automattic/zoninator/pull/132

### Maintenance

* Migrate test infrastructure to wp-test-utils by @GaryJones in https://github.com/Automattic/zoninator/pull/142
* ci: Standardise test matrix and update readme by @GaryJones in https://github.com/Automattic/zoninator/pull/139
* chore: Migrate dependabot reviewers to CODEOWNERS by @GaryJones in https://github.com/Automattic/zoninator/pull/138
* Standardise configurations and harden GitHub Actions security by @GaryJones in https://github.com/Automattic/zoninator/pull/136
* Update composer.json for consistency by @GaryJones in https://github.com/Automattic/zoninator/pull/135
* Simplify .editorconfig file by @GaryJones in https://github.com/Automattic/zoninator/pull/134
* Migrate integration tests to use wp-env by @GaryJones in https://github.com/Automattic/zoninator/pull/133
* Add wp-env configuration for local development by @GaryJones in https://github.com/Automattic/zoninator/pull/131
* Update Composer, npm, and GitHub Actions dependencies via Dependabot

### Documentation

* Add AGENTS.md with structured agent guidance by @GaryJones in https://github.com/Automattic/zoninator/pull/143

## [0.10.2] - 2025-10-02

### Fixed

* fix: Prevent exposure of content for password-protected posts by @GaryJones in https://github.com/Automattic/zoninator/pull/125

### Maintenance

* Deploy: Don't include .git/ directory by @GaryJones in https://github.com/Automattic/zoninator/pull/106
* Add and apply Rector by @GaryJones in https://github.com/Automattic/zoninator/pull/107
* CS: Fix or ignore coding standards issues by @GaryJones in https://github.com/Automattic/zoninator/pull/112
* Consolidate classes by @GaryJones in https://github.com/Automattic/zoninator/pull/117
* Refactor root file by @GaryJones in https://github.com/Automattic/zoninator/pull/118
* Fix rector config by @GaryJones in https://github.com/Automattic/zoninator/pull/119
* Update GitHub action to install SVN before deploy to WordPress.org by @raamdev in https://github.com/Automattic/zoninator/pull/120
* ci: Add SVN installation step for integration tests by @GaryJones in https://github.com/Automattic/zoninator/pull/123
* fix: Address deprecation notices during tests by @GaryJones in https://github.com/Automattic/zoninator/pull/124
* chore: Modernise i18n by @GaryJones in https://github.com/Automattic/zoninator/pull/126

## [0.10.1] - 2024-08-15

### Fixed

* Deploy: Don't include the .git/ directory by @GaryJones in https://github.com/Automattic/zoninator/pull/106

## [0.10.0] - 2024-08-09

This release has PHP 7.4 and WordPress 5.9 as the minimum supported versions.

### Fixed

* Fix typos by @szepeviktor in https://github.com/Automattic/zoninator/pull/88
* UI: Fix typo and clarify Add Zone description by @GaryJones in https://github.com/Automattic/zoninator/pull/93

### Changed 

* Increase min WP and PHP versions by @GaryJones in https://github.com/Automattic/zoninator/pull/98
* Add WP_Error check by @aosmichenko in https://github.com/Automattic/zoninator/pull/78
* Send empty set when search yields no results by @dlh01 in https://github.com/Automattic/zoninator/pull/82
* Increase autocomplete timeout by @pkevan in https://github.com/Automattic/zoninator/pull/69

### Maintenance

* Fix bytes by @szepeviktor in https://github.com/Automattic/zoninator/pull/90
* CI: Use valid runs-on value, and fix some issues by @GaryJones in https://github.com/Automattic/zoninator/pull/95
* CI: Update action versions by @GaryJones in https://github.com/Automattic/zoninator/pull/96
* Docs: Various improvements by @GaryJones in https://github.com/Automattic/zoninator/pull/99
* NPM: Add version-bump-prompt by @GaryJones in https://github.com/Automattic/zoninator/pull/100
* Add auto-deploy for WordPress.org plugin repo by @GaryJones in https://github.com/Automattic/zoninator/pull/101

## [0.9] - 2023-10-20

* Correctly position cloned sortable element on mobile.
* Add method to add post type to Zoninator.
* Fix simple typo.
* Add filter to control fields included in Zoninator zone feed responses.
* Switch from travis to GitHub actions.
* Add default_post_types property to Zoninator class.

## [0.8] - 2018-01-31

* Added a REST API for retrieving and managing zones.
* Support for special characters in zone names and descriptions.

## 0.7 - 2017-04-18

* Added compatibility with WordPress 4.4 admin styles.
* Add the ability to save zone content to fix race conditions / problems with the autosave in [#51](https://github.com/Automattic/zoninator/pull/51).
* Add ability to filter the dates to search in [#52](https://github.com/Automattic/zoninator/pull/52).
* General performance improvements.

## 0.6

* Support for term splitting in 4.2.
* Run the init hook later so that we can allow custom post types to attach themselves to the plugin. See [this support ticket](http://wordpress.org/support/topic/plugin-zone-manager-zoninator-add-specific-custom-post-types).
* Better translation support.
* Coding standards cleanup.

## 0.5

* WordPress version requirements bumped to 3.5.
* Support for touch events for mobile via [jQuery UI Touch Punch](http://touchpunch.furf.com/).
* Filter recent posts or search-as-you-type by date (today, yesterday, all) or category for more refined results, props Paul Kevan and the Metro UK team.
* New actions fired when adding/removing posts from zones.
* Bits of clean-up.

## 0.4

* New dropdown that recent posts which can be adding to zones, props metromatic and Metro UK.
* New filter: `zoninator_posts_per_page`, to override the default posts_per_page setting.
* Use core bundled versions of jQuery UI.

## 0.3

* Introduce z_get_zone_query: returns a WP_Query object so you can run the loop like usual.
* Disable editing and prefixing of slugs. They're just problems waiting to happen...
* Add new filter to allow filtering of search args, props imrannathani for the suggestion.
* Allow scheduled posts to be added to zones, so they automagically show up when they're published, props imrannathani for the idea.
* Default to published post in all zone queries in the front-end. Scheduled posts can still be added via a filter.
* Run clean_term_cache when a post is added or deleted from a zone so that the necessary caches are flushed.
* Add new filter to limit editing access on a per-zone level. props hooman and the National Post team.
* Allow editor role (editor_others_posts) to manage zones (plus other capability fixes, props rinat k.)

## 0.2

* Move Zones to a top-level menu so that it's easier to access. And doesn't make much sense hidden under Dashboard.
* Change the way error and success messages are handled.
* jQuery 1.6.1 compatibility.
* Bug fix: Custom Post Types not being included in search. Thanks, Shawn!
* Bug fix: Custom Post Types not being included in post list. Thanks, Daniel!
* Bug fix: Error thrown when removing last post in a zone. Thanks, Daniel!
* Other cleanup.

## 0.1

* Initial Release!

[0.11.1]: https://github.com/Automattic/zoninator/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/automattic/zoninator/compare/0.10.2..0.11.0
[0.10.2]: https://github.com/automattic/zoninator/compare/0.10.1..0.10.2
[0.10.1]: https://github.com/automattic/zoninator/compare/0.10.0..0.10.1
[0.10.0]: https://github.com/automattic/zoninator/compare/0.9..0.10.0
[0.9]: https://github.com/automattic/zoninator/compare/0.8..0.9
[0.8]: https://github.com/automattic/zoninator/compare/0.7..0.8

