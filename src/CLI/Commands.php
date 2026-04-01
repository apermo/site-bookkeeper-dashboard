<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard\CLI;

use Apermo\SiteMonitorDashboard\ApiClient;
use WP_CLI;
// phpcs:ignore Universal.UseStatements.DisallowUseFunction.FoundWithoutAlias -- WP-CLI utility function.
use function WP_CLI\Utils\format_items;

/**
 * WP-CLI commands for the Site Monitor Dashboard.
 *
 * Provides CLI access to monitored site data from the
 * central hub: sites overview, site detail, cross-site
 * plugin/theme reports, and connection testing.
 *
 * ## EXAMPLES
 *
 *     # List all monitored sites
 *     wp site-monitor sites
 *
 *     # Show detail for a single site
 *     wp site-monitor site <id>
 *
 *     # Cross-site plugin report
 *     wp site-monitor plugins --outdated
 *
 *     # Test hub connection
 *     wp site-monitor test
 */
class Commands {

	/**
	 * Columns displayed in the sites table.
	 *
	 * @var array<int, string>
	 */
	private const SITES_COLUMNS = [
		'id',
		'site_url',
		'label',
		'wp_version',
		'php_version',
		'pending_plugin_updates',
		'pending_theme_updates',
		'last_seen',
		'stale',
	];

	/**
	 * Columns displayed in plugin/theme report tables.
	 *
	 * @var array<int, string>
	 */
	private const REPORT_COLUMNS = [
		'slug',
		'name',
		'site_count',
		'versions',
	];

	/**
	 * Environment fields for the site detail view.
	 *
	 * @var array<int, string>
	 */
	private const ENVIRONMENT_FIELDS = [
		'wp_version',
		'php_version',
		'db_version',
		'multisite',
		'site_url',
		'active_theme',
	];

	/**
	 * API client instance.
	 *
	 * @var ApiClient
	 */
	private ApiClient $client;

	/**
	 * Create a new Commands instance.
	 *
	 * @param ApiClient|null $client Optional API client for testing.
	 */
	public function __construct( ?ApiClient $client = null ) {
		$this->client = $client ?? ApiClient::from_settings();
	}

	/**
	 * List all monitored sites.
	 *
	 * Displays a table of all sites known to the central
	 * monitoring hub with key environment and update info.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-monitor sites
	 *     wp site-monitor sites --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function sites( array $args, array $assoc_args ): void {
		$data = $this->client->get_sites();

		if ( isset( $data['error'] ) ) {
			WP_CLI::error( $this->format_error( $data ) );
			return;
		}

		$sites  = $data['sites'] ?? [];
		$format = $assoc_args['format'] ?? 'table';

		format_items( $format, $sites, self::SITES_COLUMNS );
	}

	/**
	 * Show full detail for a single site.
	 *
	 * Displays environment info, plugins, themes, users,
	 * roles, and custom fields for the specified site.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The site UUID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-monitor site abc-123-def
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function site( array $args, array $assoc_args ): void {
		$site_id = $args[0];
		$site    = $this->client->get_site( $site_id );

		if ( isset( $site['error'] ) ) {
			WP_CLI::error( $this->format_error( $site ) );
			return;
		}

		$this->render_environment( $site );
		$this->render_plugins_section( $site );
		$this->render_themes_section( $site );
		$this->render_users_section( $site );
		$this->render_roles_section( $site );
		$this->render_custom_fields_section( $site );
	}

	/**
	 * Cross-site plugin report.
	 *
	 * Shows all known plugins across monitored sites with
	 * version details and site counts.
	 *
	 * ## OPTIONS
	 *
	 * [--outdated]
	 * : Only show plugins with pending updates.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-monitor plugins
	 *     wp site-monitor plugins --outdated
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function plugins( array $args, array $assoc_args ): void {
		$params = [];
		if ( isset( $assoc_args['outdated'] ) ) {
			$params['outdated'] = 'true';
		}

		$data = $this->client->get_plugins( $params );

		if ( isset( $data['error'] ) ) {
			WP_CLI::error( $this->format_error( $data ) );
			return;
		}

		$plugins = $data['plugins'] ?? [];
		$items   = $this->build_report_items( $plugins );
		$format  = $assoc_args['format'] ?? 'table';

		format_items( $format, $items, self::REPORT_COLUMNS );
	}

	/**
	 * Cross-site theme report.
	 *
	 * Shows all known themes across monitored sites with
	 * version details and site counts.
	 *
	 * ## OPTIONS
	 *
	 * [--outdated]
	 * : Only show themes with pending updates.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-monitor themes
	 *     wp site-monitor themes --outdated
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function themes( array $args, array $assoc_args ): void {
		$params = [];
		if ( isset( $assoc_args['outdated'] ) ) {
			$params['outdated'] = 'true';
		}

		$data = $this->client->get_themes( $params );

		if ( isset( $data['error'] ) ) {
			WP_CLI::error( $this->format_error( $data ) );
			return;
		}

		$themes = $data['themes'] ?? [];
		$items  = $this->build_report_items( $themes );
		$format = $assoc_args['format'] ?? 'table';

		format_items( $format, $items, self::REPORT_COLUMNS );
	}

	/**
	 * Test connection to the central hub.
	 *
	 * Verifies that the hub URL and token are configured
	 * correctly by making a test API call.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-monitor test
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {
		$data = $this->client->get_sites();

		if ( isset( $data['error'] ) ) {
			WP_CLI::error( $this->format_error( $data ) );
			return;
		}

		$sites = $data['sites'] ?? [];
		$count = \count( $sites );

		WP_CLI::success(
			\sprintf( 'Connection successful. Found %d sites.', $count ),
		);
	}

	/**
	 * Build flattened report items from API plugin/theme data.
	 *
	 * @param array<int, array<string, mixed>> $items Raw API items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_report_items( array $items ): array {
		$rows = [];

		foreach ( $items as $item ) {
			$sites    = $item['sites'] ?? [];
			$versions = $this->format_versions( $sites );

			$rows[] = [
				'slug'       => $item['slug'] ?? '',
				'name'       => $item['name'] ?? '',
				'site_count' => \is_array( $sites ) ? \count( $sites ) : 0,
				'versions'   => $versions,
			];
		}

		return $rows;
	}

	/**
	 * Format version details from site entries.
	 *
	 * @param mixed $sites Site entries from the API.
	 *
	 * @return string
	 */
	private function format_versions( mixed $sites ): string {
		if ( ! \is_array( $sites ) ) {
			return '';
		}

		$parts = [];
		foreach ( $sites as $site ) {
			if ( ! \is_array( $site ) ) {
				continue;
			}

			$label   = (string) ( $site['label'] ?? $site['site_url'] ?? '' );
			$version = (string) ( $site['version'] ?? '' );
			$update  = (string) ( $site['update_available'] ?? '' );

			$entry = $label . ': ' . $version;
			if ( $update !== '' ) {
				$entry .= ' -> ' . $update;
			}

			$parts[] = $entry;
		}

		return \implode( ', ', $parts );
	}

	/**
	 * Render the environment section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_environment( array $site ): void {
		WP_CLI::log( '' );
		WP_CLI::log( '--- Environment ---' );

		$rows = [];
		foreach ( self::ENVIRONMENT_FIELDS as $field ) {
			if ( ! isset( $site[ $field ] ) ) {
				continue;
			}

			$value = $site[ $field ];
			if ( \is_bool( $value ) ) {
				$value = $value ? 'Yes' : 'No';
			}

			$rows[] = [
				'field' => $field,
				'value' => (string) $value,
			];
		}

		if ( $rows !== [] ) {
			format_items( 'table', $rows, [ 'field', 'value' ] );
		}
	}

	/**
	 * Render the plugins section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_plugins_section( array $site ): void {
		$plugins = $site['plugins'] ?? [];
		if ( ! \is_array( $plugins ) || $plugins === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Plugins ---' );

		format_items(
			'table',
			$plugins,
			[ 'name', 'slug', 'version', 'update_available', 'active' ],
		);
	}

	/**
	 * Render the themes section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_themes_section( array $site ): void {
		$themes = $site['themes'] ?? [];
		if ( ! \is_array( $themes ) || $themes === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Themes ---' );

		format_items(
			'table',
			$themes,
			[ 'name', 'slug', 'version', 'update_available', 'active' ],
		);
	}

	/**
	 * Render the users section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_users_section( array $site ): void {
		$users = $site['users'] ?? [];
		if ( ! \is_array( $users ) || $users === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Users ---' );

		format_items(
			'table',
			$users,
			[ 'login', 'display_name', 'email', 'role' ],
		);
	}

	/**
	 * Render the roles section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_roles_section( array $site ): void {
		$roles = $site['roles'] ?? [];
		if ( ! \is_array( $roles ) || $roles === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Roles ---' );

		format_items(
			'table',
			$roles,
			[ 'name', 'custom', 'modified', 'capability_count' ],
		);
	}

	/**
	 * Render the custom fields section for site detail.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private function render_custom_fields_section( array $site ): void {
		$fields = $site['custom_fields'] ?? [];
		if ( ! \is_array( $fields ) || $fields === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Custom Fields ---' );

		format_items(
			'table',
			$fields,
			[ 'key', 'label', 'value', 'status' ],
		);
	}

	/**
	 * Format an API error response for CLI output.
	 *
	 * @param array<string, mixed> $data Error response data.
	 *
	 * @return string
	 */
	private function format_error( array $data ): string {
		return \sprintf(
			'%s: %s',
			$data['error'] ?? 'error',
			$data['message'] ?? 'Unknown error.',
		);
	}
}
