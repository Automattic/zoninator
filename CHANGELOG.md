# Changelog for the Zoninator WordPress plugin

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-27

**A breaking release. It requires WordPress 6.9 and PHP 8.2. The `z_` template tags are now `zoninator_` (`z_get_posts_in_zone()` is `zoninator_get_posts_in_zone()`), and the `Zoninator` class and `Zoninator()` are gone, so a theme that calls the old names stops with a fatal error until it's updated. Hooks are registered on the objects that handle them, the Zone Posts widget class is renamed, the deprecated admin-ajax actions are removed, and several REST responses have changed. The [upgrade guide](https://github.com/Automattic/zoninator/blob/main/docs/upgrading-to-1.0.md) lists every change and what to use instead.**

### Added

* The Zone Posts block, a variation of the Query Loop block, shows a zone's posts in block themes and the block editor. Its titles link to their posts, and can be headings or paragraphs by @faisalahammad and @GaryJones in https://github.com/Automattic/zoninator/pull/233 and https://github.com/Automattic/zoninator/pull/282
* Abilities let agents and other tools list zones, read a zone's posts and search for posts to add, and add, remove and reorder a zone's posts with the same checks as the Zones screen. The abilities that change a zone aren't public on the MCP adapter's default server unless a site opts in by @faisalahammad and @GaryJones in https://github.com/Automattic/zoninator/pull/232 and https://github.com/Automattic/zoninator/pull/257
* A supported PHP API for changing zones: `ZoneManager` and `ZoneCurator`, and their `Guarded` versions that check the user first, from `zoninator_container()` by @GaryJones in https://github.com/Automattic/zoninator/pull/255, https://github.com/Automattic/zoninator/pull/256, and https://github.com/Automattic/zoninator/pull/266
* The `zoninator_post_count_limit` filter sets the most posts a zone may hold, proposed in #9 by @cojennin, by @GaryJones in https://github.com/Automattic/zoninator/pull/259

### Changed

* Requires WordPress 6.9 and PHP 8.2 by @GaryJones in https://github.com/Automattic/zoninator/pull/241
* The template tags have the `zoninator_` prefix, such as `zoninator_get_posts_in_zone()` and `zoninator_get_zone()` by @GaryJones in https://github.com/Automattic/zoninator/pull/255
* Every change to a zone goes through one set of checks: the user's capability, whether they can use each post, and the zone's lock. A zone's lock now stops changes from the REST API and the abilities as well as the Zones screen, with the code `zone-locked`, until its holder lets go by @GaryJones in https://github.com/Automattic/zoninator/pull/256
* A zone holds up to 100 posts by default. A zone that already holds more keeps them, but takes no more. Saving a zone's order only writes the positions that changed by @GaryJones in https://github.com/Automattic/zoninator/pull/276
* Zoninator boots on `plugins_loaded`, and each hook is registered on the object that handles it, so `remove_action()` calls need the new callbacks listed in the upgrade guide by @GaryJones in https://github.com/Automattic/zoninator/pull/248 and https://github.com/Automattic/zoninator/pull/249
* `zoninator_add_zone_posts` and `zoninator_remove_zone_posts` always pass an array of post IDs by @GaryJones in https://github.com/Automattic/zoninator/pull/254
* The Zone Posts widget class is `Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget`. Widgets already in sidebars keep working with the same settings by @GaryJones in https://github.com/Automattic/zoninator/pull/265
* The Zone Posts widget builds its output on each page view instead of caching it, and the JSON feed of an empty zone sends `[]` by @GaryJones in https://github.com/Automattic/zoninator/pull/272
* The locked zone REST error keeps `zone_id` and `blocked` under `data`, like core's REST errors by @GaryJones in https://github.com/Automattic/zoninator/pull/265
* `POST /zoninator/v1/zones/{zone_id}/posts/{post_id}` returns `{ "success": true }` instead of the Zones screen's HTML for the post by @GaryJones in https://github.com/Automattic/zoninator/pull/261
* `zoninator_current_user_can_edit_zone` receives the user's ID as a third argument by @GaryJones in https://github.com/Automattic/zoninator/pull/268

### Removed

* The `Zoninator` class, `Zoninator()`, the `$zoninator` global and `z_get_zoninator()`. The upgrade guide gives the replacement for each method by @GaryJones in https://github.com/Automattic/zoninator/pull/247 and https://github.com/Automattic/zoninator/pull/255
* The `z_` template tags, replaced by the `zoninator_` ones by @GaryJones in https://github.com/Automattic/zoninator/pull/255
* The `zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts`, `zoninator_update_recent` and `zoninator_update_lock` admin-ajax actions, deprecated in 0.12.0 by @GaryJones in https://github.com/Automattic/zoninator/pull/246
* The `zoninator_pre_init` and `zoninator_post_init` actions by @GaryJones in https://github.com/Automattic/zoninator/pull/249
* The handler for terms shared with another taxonomy before WordPress 4.2, with the `zoninator_split_shared_term` and `zoninator_did_split_shared_term` actions by @GaryJones in https://github.com/Automattic/zoninator/pull/265
* The `zone_posts_widget_block_save_cache_seconds` filter and `flush_widget_cache()` by @GaryJones in https://github.com/Automattic/zoninator/pull/272

### Fixed

* Zone names and descriptions keep quotes, ampersands, tags and line breaks as given, whichever way the zone is saved by @GaryJones in https://github.com/Automattic/zoninator/pull/267 and https://github.com/Automattic/zoninator/pull/280
* The JSON feed URL works straight after activation, without saving the permalink settings by @GaryJones in https://github.com/Automattic/zoninator/pull/279
* A failed zone save shows as an error on the Zones screen instead of a success by @GaryJones in https://github.com/Automattic/zoninator/pull/273
* The search's date filter ignores anything that isn't a `Y-m-d` date by @GaryJones in https://github.com/Automattic/zoninator/pull/273
* Deleting a zone releases its lock by @GaryJones in https://github.com/Automattic/zoninator/pull/244
* A zone holding a post the user can't read can still be reordered, and the post can be taken out by @GaryJones in https://github.com/Automattic/zoninator/pull/280
* The REST error for a zone that couldn't be deleted is translated again, and translators can change the separators in the search results by @GaryJones in https://github.com/Automattic/zoninator/pull/275

### Security

* The JSON feed and `GET /zoninator/v1/zones/{zone_id}/posts` leave out `post_content` by default, as stored content hasn't been through the filters a paywall or membership plugin uses. The `zoninator_zone_feed_fields` filter can add it back by @GaryJones in https://github.com/Automattic/zoninator/pull/280
* `GET /zoninator/v1/zones` needs a user who can edit posts or curate zones, not just a logged-in one by @GaryJones in https://github.com/Automattic/zoninator/pull/280
* The Zones screen shows a post the user can't read only as a placeholder with a Remove button, and the zone form's nonce is for one zone by @GaryJones in https://github.com/Automattic/zoninator/pull/280

### Maintenance

* Restructure the plugin into Domain, Application and Infrastructure layers built by a container, with a WordPress-free domain, strict types and one REST controller per resource by @GaryJones in https://github.com/Automattic/zoninator/pull/250, https://github.com/Automattic/zoninator/pull/251, https://github.com/Automattic/zoninator/pull/252, https://github.com/Automattic/zoninator/pull/253, https://github.com/Automattic/zoninator/pull/258, https://github.com/Automattic/zoninator/pull/260, https://github.com/Automattic/zoninator/pull/262, https://github.com/Automattic/zoninator/pull/263, https://github.com/Automattic/zoninator/pull/269, https://github.com/Automattic/zoninator/pull/270, and https://github.com/Automattic/zoninator/pull/273
* Add end-to-end tests of the Zones screen with axe accessibility checks, and make the tests able to fail by @GaryJones in https://github.com/Automattic/zoninator/pull/243, https://github.com/Automattic/zoninator/pull/245, https://github.com/Automattic/zoninator/pull/274, and https://github.com/Automattic/zoninator/pull/277
* Test WordPress trunk on PHP 8.6 by @GaryJones in https://github.com/Automattic/zoninator/pull/242
* Make the docs match 1.0.0, with an upgrade guide from 0.12.0 and a PHP API reference by @GaryJones in https://github.com/Automattic/zoninator/pull/264 and https://github.com/Automattic/zoninator/pull/278
* Settle the Application API and tidy loose ends before freezing it by @GaryJones in https://github.com/Automattic/zoninator/pull/265, https://github.com/Automattic/zoninator/pull/266, and https://github.com/Automattic/zoninator/pull/268

## [0.12.0] - 2026-11-24

**This release replaces the bundled Mixtape REST library with core's `WP_REST_Controller`, and moves the Zones screen onto the REST API. Code that uses the `Zoninator_Api*` or `Zoninator_REST_*` classes, the `mixtape_*` or `mt_*` hooks, or `Zoninator()`'s public properties needs updating, and REST clients get new error codes and permission checks. The [upgrade guide](https://github.com/Automattic/zoninator/blob/main/docs/upgrades.md) lists what to check.**

### Added

* Find a post by its ID in the Zones screen's search. A number that's a post the search could offer returns that post straight away, without a text search, and still respects `zoninator_search_args`; otherwise the number is searched as text by @tewariharish and @GaryJones in https://github.com/Automattic/zoninator/pull/91 and https://github.com/Automattic/zoninator/pull/231
* New REST routes for the Zones screen: `POST` and `DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}` add or remove one post, and `GET /zoninator/v1/search` searches for posts or lists the latest content by @GaryJones in https://github.com/Automattic/zoninator/pull/207
* The `zoninator_zone_posts_widget_html` filter replaces the Zone Posts widget's list of links with your own HTML, such as thumbnails or excerpts by @jonathanstegall and @GaryJones in https://github.com/Automattic/zoninator/pull/218
* The `zoninator_search_args` and `zoninator_recent_posts_args` filters receive the ID of the zone being edited, so a site can focus the search on what suits each zone by @imranglobal and @GaryJones in https://github.com/Automattic/zoninator/pull/219
* The `zoninator_zone_detail_defaults` filter adds your own zone details, and `Zoninator()->get_zone_lock_period()`, `get_zone_max_lock_period()`, `get_posts_per_page()` and `get_zone_detail_defaults()` read the tunable values by @faisalahammad and @GaryJones in https://github.com/Automattic/zoninator/pull/179

### Changed

* The Zones screen is rebuilt from core's admin styles, with the zones in a list table and the zone's content in a postbox. Its posts can be reordered with Move up and Move down buttons, which come first in each post's actions, as well as by dragging by @GaryJones in https://github.com/Automattic/zoninator/pull/188 and https://github.com/Automattic/zoninator/pull/234
* The Zones screen has one field for adding content, which offers the latest content before anything is typed, and saves each change as it's made by @GaryJones in https://github.com/Automattic/zoninator/pull/198
* The Zones screen adds, removes, reorders and searches for posts, and takes the zone lock, through the REST API by @GaryJones in https://github.com/Automattic/zoninator/pull/207 and https://github.com/Automattic/zoninator/pull/209
* The REST API runs on core's `WP_REST_Controller`. The routes, their arguments and their response bodies are unchanged except as listed here by @GaryJones in https://github.com/Automattic/zoninator/pull/191
* Every REST error has a `code`, in core's REST error shape, alongside the same `message`, so clients can tell errors apart without matching translated text. A missing zone gives `zone-not-found`, a post the user can't add gives `invalid-post-id`, a zone locked by another user gives `zone-locked` (keeping its `zone_id` and `blocked` fields), and a failed zone save passes on WordPress's own code, such as `term_exists` by @GaryJones in https://github.com/Automattic/zoninator/pull/226 and https://github.com/Automattic/zoninator/pull/231
* REST error messages read "Invalid post ID.", "Zone ID is required." and "Zone not found.", in place of "Invalid post id", "zone id required" and "Zone not found". Clients should match on the error code rather than the message by @GaryJones in https://github.com/Automattic/zoninator/pull/225
* A REST request refused by a capability check gets `401` when logged out and `403` when logged in, with the code `permission-denied`, instead of `500` by @GaryJones in https://github.com/Automattic/zoninator/pull/194
* `PUT /zoninator/v1/zones/{zone_id}/posts` and `PUT /zoninator/v1/zones/{zone_id}/lock` check `zoninator_manage_zone_cap`, the capability the Zones screen needs to curate a zone, instead of `zoninator_edit_zone_cap` and the `zoninator_current_user_can_edit_zone` filter by @GaryJones in https://github.com/Automattic/zoninator/pull/207 and https://github.com/Automattic/zoninator/pull/209
* Sending an empty `description` to `PUT /zoninator/v1/zones/{zone_id}` clears the zone's description. Before, an empty value was ignored. Leaving `description` out still keeps the current one by @GaryJones in https://github.com/Automattic/zoninator/pull/228
* `z_get_next_post_in_zone()` and `z_get_prev_post_in_zone()` run one fewer query, and the Zones screen loads jQuery UI Touch Punch in the footer by @GaryJones in https://github.com/Automattic/zoninator/pull/220

### Deprecated

* `Zoninator()`'s public properties. Reading one still works, with a notice that names its replacement, and writing to a tunable value still takes effect until 1.0.0. See the upgrade guide for each replacement by @faisalahammad and @GaryJones in https://github.com/Automattic/zoninator/pull/179
* The `zoninator_add_post`, `zoninator_remove_post`, `zoninator_reorder_posts`, `zoninator_search_posts`, `zoninator_update_recent` and `zoninator_update_lock` admin-ajax actions, and the `Zoninator::ajax_*()`, `ajax_return()`, `ajax_move_zone_post()` and `zone_admin_recent_posts_dropdown()` methods behind them. They keep working until 1.0.0; use the REST routes instead by @GaryJones in https://github.com/Automattic/zoninator/pull/207, https://github.com/Automattic/zoninator/pull/209, and https://github.com/Automattic/zoninator/pull/210

### Removed

* The bundled Mixtape REST library: the `Zoninator_Api`, `Zoninator_Api_Controller`, `Zoninator_Api_Filter_Search`, `Zoninator_Api_Schema_Converter` and `Zoninator_REST_*` classes, the `mixtape_*`, `mt_*` and `rest_api_schema_properties` hooks, and `Zoninator()->rest_api->bootstrap` by @GaryJones in https://github.com/Automattic/zoninator/pull/191
* The jQuery UI theme the Zones screen bundled by @GaryJones in https://github.com/Automattic/zoninator/pull/188

### Fixed

* `z_get_next_post_in_zone()` and `z_get_prev_post_in_zone()` no longer cause a fatal error, and the Zone Posts widget shows a zone's changes straight away by @GaryJones in https://github.com/Automattic/zoninator/pull/193
* `z_get_post_zones()` returns the zones a post is in, instead of `null` by @GaryJones in https://github.com/Automattic/zoninator/pull/212
* Malformed REST input, such as a non-numeric post ID, gets a `400` instead of a PHP fatal error by @GaryJones in https://github.com/Automattic/zoninator/pull/195
* Zone names and descriptions keep their backslashes, descriptions with accented letters are no longer lost, and updating a zone without a description keeps the one it has by @GaryJones in https://github.com/Automattic/zoninator/pull/196, https://github.com/Automattic/zoninator/pull/199, and https://github.com/Automattic/zoninator/pull/197
* The Zones screen can be used by keyboard and screen reader, and announces each change by @GaryJones in https://github.com/Automattic/zoninator/pull/188
* Zone positions stay right when another plugin filters post queries, for example by language by @GaryJones in https://github.com/Automattic/zoninator/pull/227
* No PHP warning after `Zoninator()->register_zone_post_type()` adds a post type by @GaryJones in https://github.com/Automattic/zoninator/pull/205
* The Zones screen leaves out the Edit link for posts the user can't edit, which also removes a PHP 8.1 deprecation notice by @GaryJones in https://github.com/Automattic/zoninator/pull/229
* Translators can place the post title in the Zones screen's row actions, and the screen's spacing follows the text direction in right-to-left languages by @GaryJones in https://github.com/Automattic/zoninator/pull/221
* The Add content field's loading spinner clears once its results show by @GaryJones in https://github.com/Automattic/zoninator/pull/234
* The deprecated recent posts dropdown and its admin-ajax action keep working until 1.0.0 instead of being removed outright by @GaryJones in https://github.com/Automattic/zoninator/pull/210

### Security

* The zone search and the latest content list only posts the user can read, so a zone manager no longer sees the titles of posts an access plugin hides from them by @GaryJones in https://github.com/Automattic/zoninator/pull/230
* Only Zoninator's own capabilities decide who can change zones. The zone taxonomy grants none of core's term capabilities, so core's term handling, such as `tax_input` on post saves, can no longer change zones, and the Zones screen's form checks the capability for the change it actually makes by @GaryJones in https://github.com/Automattic/zoninator/pull/224

### Maintenance

* Split the `Zoninator` class into `final` classes under `Automattic\Zoninator\` for the taxonomy, zone storage, locks, the JSON feed, settings, capabilities, requests, the admin screen and its AJAX handlers. `Zoninator` builds them and stays the public facade, so its methods, hooks and callbacks keep working by @GaryJones in https://github.com/Automattic/zoninator/pull/201, https://github.com/Automattic/zoninator/pull/202, https://github.com/Automattic/zoninator/pull/203, https://github.com/Automattic/zoninator/pull/204, https://github.com/Automattic/zoninator/pull/206, and https://github.com/Automattic/zoninator/pull/214
* Pin the REST API contract and the Zones screen's AJAX handlers and hooks with tests before changing them, test the form handler, JSON feed, widget and template tags, and check that the tests catch a missing permission check and each bug fixed since 0.11.1 by @GaryJones in https://github.com/Automattic/zoninator/pull/189, https://github.com/Automattic/zoninator/pull/200, https://github.com/Automattic/zoninator/pull/192, https://github.com/Automattic/zoninator/pull/187, and https://github.com/Automattic/zoninator/pull/223
* Make the docs match the code, describe what the zone posts hooks pass, and add an upgrade guide by @GaryJones in https://github.com/Automattic/zoninator/pull/222, https://github.com/Automattic/zoninator/pull/211, and https://github.com/Automattic/zoninator/pull/231
* Remove TODO comments nothing will act on by @GaryJones in https://github.com/Automattic/zoninator/pull/213
* Update GitHub Actions dependencies by @dependabot

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

[1.0.0]: https://github.com/Automattic/zoninator/compare/0.12.0...1.0.0
[0.12.0]: https://github.com/Automattic/zoninator/compare/0.11.1...0.12.0
[0.11.1]: https://github.com/Automattic/zoninator/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/automattic/zoninator/compare/0.10.2..0.11.0
[0.10.2]: https://github.com/automattic/zoninator/compare/0.10.1..0.10.2
[0.10.1]: https://github.com/automattic/zoninator/compare/0.10.0..0.10.1
[0.10.0]: https://github.com/automattic/zoninator/compare/0.9..0.10.0
[0.9]: https://github.com/automattic/zoninator/compare/0.8..0.9
[0.8]: https://github.com/automattic/zoninator/compare/0.7..0.8

