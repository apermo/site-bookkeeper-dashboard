# Site Bookkeeper Dashboard

A monitoring tool for your WordPress Sites — Dashboard Plugin

[![PHP CI](https://github.com/apermo/site-bookkeeper-dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/apermo/site-bookkeeper-dashboard/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/apermo/site-bookkeeper-dashboard/graph/badge.svg)](https://codecov.io/gh/apermo/site-bookkeeper-dashboard)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/apermo/site-bookkeeper-dashboard)](https://packagist.org/packages/apermo/site-bookkeeper-dashboard)
[![PHP Version](https://img.shields.io/packagist/dependency-v/apermo/site-bookkeeper-dashboard/php)](https://packagist.org/packages/apermo/site-bookkeeper-dashboard)

WordPress admin dashboard for viewing site health data from a central
[Site Bookkeeper Hub](https://github.com/apermo/site-bookkeeper-hub). Provides an at-a-glance overview of
all monitored sites, their plugin/theme update status, and cross-site reports.

## Requirements

- PHP 8.2+
- WordPress 6.2+
- A running [Site Bookkeeper Hub](https://github.com/apermo/site-bookkeeper-hub) instance with a client
  token
- One or more sites using the
  [Site Bookkeeper Reporter](https://github.com/apermo/site-bookkeeper-reporter) plugin to push data to the
  hub

## Installation

Install via Composer:

```bash
composer require apermo/site-bookkeeper-dashboard
```

Activate the plugin and configure in **Settings > Site Bookkeeper Dashboard**, or define constants in
`wp-config.php`:

```php
define( 'SITE_BOOKKEEPER_HUB_URL', 'https://monitor.example.tld' );
define( 'SITE_BOOKKEEPER_CLIENT_TOKEN', 'your-client-token-here' );
```

## Security

The plugin enforces HTTPS for all communication with the hub. Both the settings page and the API client will reject
plain HTTP URLs.

For local development you can opt out by defining the following constant in `wp-config.php`:

```php
define( 'SITE_BOOKKEEPER_ALLOW_HTTP', true );
```

## Features

### Sites Overview

`WP_List_Table` with sortable columns: site URL, WordPress version, PHP version, pending plugin/theme
updates, last seen, stale indicator. Stale sites (no report in 48h) are highlighted.

### Site Detail

Full report for a single site: environment info, plugins with versions and update status, themes, users
with roles and meta (e.g. 2FA status), custom fields with status badges.

### Cross-Site Reports

Plugin-centric and theme-centric views showing which versions are installed across all sites, with an
"outdated only" filter.

### Network Views (Multisite)

Networks overview listing all multisite networks with subsite count. Network detail view showing
network-activated plugins, super admins, network settings, and linked subsites.

## WP-CLI Commands

```bash
wp bookkeeper-dashboard sites                # List all monitored sites
wp bookkeeper-dashboard site <id>            # Full detail for a single site
wp bookkeeper-dashboard plugins              # Cross-site plugin report
wp bookkeeper-dashboard plugins --outdated   # Only plugins with pending updates
wp bookkeeper-dashboard themes               # Cross-site theme report
wp bookkeeper-dashboard themes --outdated    # Only themes with pending updates
wp bookkeeper-dashboard networks             # List all networks
wp bookkeeper-dashboard network <id>         # Full network detail
wp bookkeeper-dashboard test                 # Test connection to the hub
```

All commands support `--format=table|csv|json|yaml`.

## Development

```bash
composer install
composer cs              # Run PHPCS
composer cs:fix          # Fix PHPCS violations
composer analyse         # Run PHPStan
composer test:unit       # Run unit tests
```

## License

[GPL-2.0-or-later](LICENSE)
