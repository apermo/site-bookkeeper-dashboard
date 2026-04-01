<?php
/**
 * Plugin Name: Site_Monitor_Dashboard
 * Description: A WordPress plugin.
 * Version:     0.1.0
 * Author:      Christoph Daum
 * Author URI:  https://apermo.de
 * License:     GPL-2.0-or-later
 * Text Domain: site-monitor-dashboard
 * Requires at least: 6.2
 * Requires PHP: 8.2
 */

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard;

\defined( 'ABSPATH' ) || exit();

require_once __DIR__ . '/vendor/autoload.php';

Plugin::init( __FILE__ );
