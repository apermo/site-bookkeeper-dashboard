# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.3] - 2026-04-18

### Fixed

- DDEV `pre-start` hook now installs the `apermo/ddev-orchestrate` addon
  automatically, so `ddev orchestrate` works on fresh clones and in CI
  without a manual `ddev add-on get` step

## [0.1.2] - 2026-04-18

### Fixed

- DDEV installs WordPress into `.ddev/wordpress/` subdirectory to keep the
  project root clean
- Root `.gitignore` explicitly ignores `wp-config.php` as a safety net against
  committing DB credentials left over from a pre-migration install
- PHPCS excludes `.ddev/` from analysis

### Added

- `.wp-env.json` configuration for the reusable E2E workflow
- **State** column on the sites, networks, and subsite tables with emoji
  indicators (🟢 fresh / ⚠️ stale / ⏰ overdue / ⚠️⏰ both), sortable
  (worst first by default) and filterable, including a "Stale or overdue"
  union option on the sites table
- DDEV mu-plugin (`.ddev/mu-plugins/ddev-skip-ssl-verify.php`) that skips
  SSL verification for requests to `*.ddev.site` hosts, plus an orchestrate
  project fragment that installs it during `ddev orchestrate`

### Changed

- Pin reusable GitHub workflows to `apermo/reusable-workflows@v0.4.3`
- Track `composer.lock` in the repository for reproducible installs
- Stale / overdue rows are now marked with a colored left-border stripe
  (amber / red) instead of a dimmed row, so the table stays easy to scan
  even when most rows have issues

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

[0.1.3]: https://github.com/apermo/site-bookkeeper-dashboard/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/apermo/site-bookkeeper-dashboard/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/apermo/site-bookkeeper-dashboard/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/apermo/site-bookkeeper-dashboard/releases/tag/v0.1.0
