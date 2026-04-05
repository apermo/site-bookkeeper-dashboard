# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress admin dashboard plugin that reads site health data from a central monitoring hub API
(`site-bookkeeper-hub`). Displays sites overview, site details, cross-site plugin/theme reports.

**PHP 8.2+ minimum.** Strict types everywhere (`declare(strict_types=1)`).

## Architecture

- PSR-4 autoloading under `src/` (namespace `Apermo\SiteBookkeeperDashboard`)
- Main entry: `plugin.php` -> `src/Plugin.php`
- Coding standards: `apermo/apermo-coding-standards` (PHPCS)
- Static analysis: `apermo/phpstan-wordpress-rules` + `szepeviktor/phpstan-wordpress`
- Testing: PHPUnit + Brain Monkey + Yoast PHPUnit Polyfills
- Test suites: `tests/Unit/` and `tests/Integration/`

## Commands

```bash
composer cs              # Run PHPCS
composer cs:fix          # Fix PHPCS violations
composer analyse         # Run PHPStan
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:integration # Run integration tests only
```

## Conventions

- Example domains must use `.tld` TLD (e.g. `https://monitor.example.tld`)
- Use post-increment (`$var++`) not pre-increment
- TDD: write test first, verify it fails, write code to pass

## Git Hooks

Pre-commit hook runs PHPCS and PHPStan on staged files. Enabled via:

```bash
git config core.hooksPath .githooks
```

## CI (GitHub Actions)

- `ci.yml` -- PHPCS + PHPStan + PHPUnit across PHP 8.2, 8.3, 8.4
- `integration.yml` -- WP integration tests (real WP + MySQL, multisite matrix)
- `e2e.yml` -- Playwright E2E tests against running WordPress
- `wp-beta.yml` -- Nightly WP beta/RC compatibility check
- `release.yml` -- CHANGELOG-driven releases
- `pr-validation.yml` -- conventional commit and changelog checks

## Local Development (DDEV)

```bash
ddev start && ddev orchestrate   # Full WordPress environment
```

- WordPress installs into `.ddev/wordpress/` subdirectory (keeps project root clean)
- `ddev-orchestrate` symlinks the project into the WP plugins/themes directory automatically
