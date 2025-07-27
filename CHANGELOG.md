# Changelog for the Zoninator WordPress plugin

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.10.1]: https://github.com/automattic/zoninator/compare/0.10.0..0.10.1
[0.10.0]: https://github.com/automattic/zoninator/compare/0.9..0.10.0
[0.9]: https://github.com/automattic/zoninator/compare/0.8..0.9
[0.8]: https://github.com/automattic/zoninator/compare/0.7..0.8

