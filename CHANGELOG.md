# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-04-04

### Added

- Packagist and Codecov badges in README
- PHP version badge in README
- Published to Packagist as `apermo/site-bookkeeper-dashboard`
- Reporter plugin recommendation in README

### Fixed

- Unit test regressions: WP_List_Table stub for unit tests, updated mocks for
  new submenu pages, CategoryAdmin hooks, `set_transient` last_checked, protected
  `get_row_class` visibility, and `sites_count` column changes

## [0.1.0] - 2026-04-01

### Added

- Sites overview with sortable columns, stale indicator, and search
- Site detail view: environment, plugins, themes, custom fields, users, roles
- Cross-site plugin and theme reports with version lists and outdated filter
- Cross-site user search page
- Networks overview and network detail view (multisite)
- Site categories, notes editor, and overdue highlight
- API client with transient caching and cache flush
- Settings page with HTTPS enforcement
- WP-CLI commands for sites, plugins, themes, networks
- E2E and unit test suites

[0.1.1]: https://github.com/apermo/site-bookkeeper-dashboard/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/apermo/site-bookkeeper-dashboard/releases/tag/v0.1.0
