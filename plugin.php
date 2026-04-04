<?php
/**
 * Plugin Name: Site_Bookkeeper_Dashboard
 * Description: A WordPress plugin.
 * Version:     0.1.1
 * Author:      Christoph Daum
 * Author URI:  https://apermo.de
 * License:     GPL-2.0-or-later
 * Text Domain: site-bookkeeper-dashboard
 * Requires at least: 6.2
 * Requires PHP: 8.2
 */

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

use WP_CLI;

\defined( 'ABSPATH' ) || exit();

require_once __DIR__ . '/vendor/autoload.php';

Plugin::init( __FILE__ );

if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
	WP_CLI::add_command( 'bookkeeper-dashboard', CLI\Commands::class );
}
