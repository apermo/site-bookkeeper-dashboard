<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\CLI;

use Apermo\SiteBookkeeperDashboard\ApiClient;
use WP_CLI;
// phpcs:ignore Universal.UseStatements.DisallowUseFunction.FoundWithoutAlias -- WP-CLI utility function.
use function WP_CLI\Utils\format_items;

/**
 * WP-CLI commands for the Site Bookkeeper Dashboard.
 *
 * Provides CLI access to monitored site data from the
 * central hub: sites overview, site detail, cross-site
 * plugin/theme reports, and connection testing.
 *
 * ## EXAMPLES
 *
 *     # List all monitored sites
 *     wp site-bookkeeper sites
 *
 *     # Show detail for a single site
 *     wp site-bookkeeper site <id>
 *
 *     # Cross-site plugin report
 *     wp site-bookkeeper plugins --outdated
 *
 *     # List all networks
 *     wp site-bookkeeper networks
 *
 *     # Show detail for a single network
 *     wp site-bookkeeper network <id>
 *
 *     # Test hub connection
 *     wp site-bookkeeper test
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
	 * Columns displayed in the networks table.
	 *
	 * @var array<int, string>
	 */
	private const NETWORKS_COLUMNS = [
		'id',
		'main_site_url',
		'label',
		'subsite_count',
		'last_seen',
	];

	/**
	 * Network info fields for the network detail view.
	 *
	 * @var array<int, string>
	 */
	private const NETWORK_INFO_FIELDS = [
		'main_site_url',
		'subsite_count',
		'last_seen',
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
	 *     wp site-bookkeeper sites
	 *     wp site-bookkeeper sites --format=json
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
	 *     wp site-bookkeeper site abc-123-def
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
	 *     wp site-bookkeeper plugins
	 *     wp site-bookkeeper plugins --outdated
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
	 *     wp site-bookkeeper themes
	 *     wp site-bookkeeper themes --outdated
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
	 * List all monitored networks.
	 *
	 * Displays a table of all WordPress multisite networks
	 * known to the central monitoring hub.
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
	 *     wp site-bookkeeper networks
	 *     wp site-bookkeeper networks --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function networks( array $args, array $assoc_args ): void {
		$data = $this->client->get_networks();

		if ( isset( $data['error'] ) ) {
			WP_CLI::error( $this->format_error( $data ) );
			return;
		}

		$networks = $data['networks'] ?? [];
		$format   = $assoc_args['format'] ?? 'table';

		format_items( $format, $networks, self::NETWORKS_COLUMNS );
	}

	/**
	 * Show full detail for a single network.
	 *
	 * Displays network info, network-activated plugins,
	 * super admins, network settings, and subsites.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The network UUID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-bookkeeper network net-uuid-1
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function network( array $args, array $assoc_args ): void {
		$network_id = $args[0];
		$network    = $this->client->get_network( $network_id );

		if ( isset( $network['error'] ) ) {
			WP_CLI::error( $this->format_error( $network ) );
			return;
		}

		$this->render_network_info( $network );
		$this->render_network_plugins_section( $network );
		$this->render_super_admins_section( $network );
		$this->render_network_settings_section( $network );
		$this->render_subsites_section( $network );
	}

	/**
	 * Test connection to the central hub.
	 *
	 * Verifies that the hub URL and token are configured
	 * correctly by making a test API call.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-bookkeeper test
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
			[ 'user_login', 'display_name', 'email', 'role' ],
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
			[ 'name', 'is_custom', 'is_modified' ],
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
	 * Render the network info section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private function render_network_info( array $network ): void {
		WP_CLI::log( '' );
		WP_CLI::log( '--- Network Info ---' );

		$rows = [];
		foreach ( self::NETWORK_INFO_FIELDS as $field ) {
			if ( ! isset( $network[ $field ] ) ) {
				continue;
			}

			$value = $network[ $field ];
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
	 * Render the network plugins section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private function render_network_plugins_section( array $network ): void {
		$plugins = $network['network_plugins'] ?? [];
		if ( ! \is_array( $plugins ) || $plugins === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Network-Activated Plugins ---' );

		format_items(
			'table',
			$plugins,
			[ 'slug', 'name', 'version', 'update_available' ],
		);
	}

	/**
	 * Render the super admins section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private function render_super_admins_section( array $network ): void {
		$admins = $network['super_admins'] ?? [];
		if ( ! \is_array( $admins ) || $admins === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Super Admins ---' );

		format_items(
			'table',
			$admins,
			[ 'user_login', 'display_name', 'email' ],
		);
	}

	/**
	 * Render the network settings section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private function render_network_settings_section( array $network ): void {
		$settings = $network['network_settings'] ?? [];
		if ( ! \is_array( $settings ) || $settings === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Network Settings ---' );

		format_items(
			'table',
			$settings,
			[ 'key', 'label', 'value' ],
		);
	}

	/**
	 * Render the subsites section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private function render_subsites_section( array $network ): void {
		$subsites = $network['subsites'] ?? [];
		if ( ! \is_array( $subsites ) || $subsites === [] ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--- Subsites ---' );

		format_items(
			'table',
			$subsites,
			[ 'id', 'site_url', 'label' ],
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
