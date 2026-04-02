<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Sites overview list table.
 *
 * Renders the main admin page table showing all monitored
 * sites with sortable columns, pagination, and stale highlighting.
 */
class SitesListTable extends ApiListTable {

	/**
	 * Whether any site in the dataset has a network_id.
	 *
	 * @var bool
	 */
	private bool $has_networks = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'site',
				'plural'   => 'sites',
				'ajax'     => false,
			],
		);
	}

	/**
	 * Fetch site data from the hub API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		$client = ApiClient::from_settings();
		$data = $client->get_sites();

		if ( isset( $data['error'] ) ) {
			$this->api_error = $data;
			return [];
		}

		$sites = $data['sites'] ?? [];
		$this->has_networks = $this->detect_networks( $sites );

		return $sites;
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		$columns = [
			'label'            => 'Site',
			'environment_type' => 'Environment',
			'wp_version'       => 'WordPress',
			'php_version'      => 'PHP',
			'pending_updates'  => 'Pending Updates',
			'last_seen'        => 'Last Seen',
			'last_updated'     => 'Last Updated',
		];

		if ( $this->has_networks ) {
			$columns = \array_slice( $columns, 0, 1, true )
				+ [ 'network' => 'Network' ]
				+ \array_slice( $columns, 1, null, true );
		}

		return $columns;
	}

	/**
	 * Sortable column definitions.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'label'            => [ 'label', false ],
			'environment_type' => [ 'environment_type', false ],
			'wp_version'       => [ 'wp_version', false ],
			'php_version'      => [ 'php_version', false ],
			'last_seen'        => [ 'last_seen', true ],
		];
	}

	/**
	 * Render the label column with a detail link.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_label( array $item ): string {
		$detail_url = admin_url(
			\sprintf(
				'admin.php?page=site_bookkeeper_dashboard_detail&site_id=%s',
				$item['id'] ?? '',
			),
		);

		return \sprintf(
			'<a href="%s"><strong>%s</strong></a><br><small>%s</small>',
			esc_url( $detail_url ),
			esc_html( (string) ( $item['label'] ?? '' ) ),
			esc_html( (string) ( $item['site_url'] ?? '' ) ),
		);
	}

	/**
	 * Render the WordPress version column.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_wp_version( array $item ): string {
		$version = (string) ( $item['wp_version'] ?? '' );
		$update = (string) ( $item['wp_update_available'] ?? '' );

		if ( $update === '' ) {
			return \sprintf(
				'<span class="smd-wp-current">%s</span>',
				esc_html( $version ),
			);
		}

		$current_major = \implode( '.', \array_slice( \explode( '.', $version ), 0, 2 ) );
		$update_major = \implode( '.', \array_slice( \explode( '.', $update ), 0, 2 ) );

		if ( $current_major !== $update_major ) {
			return \sprintf(
				'<span class="smd-wp-acceptable">%s</span> <span class="smd-update-available">(%s)</span>',
				esc_html( $version ),
				esc_html( $update ),
			);
		}

		return \sprintf(
			'%s <span class="smd-update-available">(%s)</span>',
			esc_html( $version ),
			esc_html( $update ),
		);
	}

	/**
	 * Render the pending updates column.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_pending_updates( array $item ): string {
		$plugin_updates = (int) ( $item['pending_plugin_updates'] ?? 0 );
		$theme_updates = (int) ( $item['pending_theme_updates'] ?? 0 );
		$total = $plugin_updates + $theme_updates;

		if ( $total > 0 ) {
			return \sprintf(
				'<span class="smd-has-updates">%s</span>',
				esc_html( (string) $total ),
			);
		}

		return '<span class="smd-no-updates">0</span>';
	}

	/**
	 * Render the network column.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_network( array $item ): string {
		$network_id = $item['network_id'] ?? null;

		if ( $network_id === null || $network_id === '' ) {
			return '&mdash;';
		}

		$detail_url = admin_url(
			\sprintf(
				'admin.php?page=site_bookkeeper_dashboard_network_detail&network_id=%s',
				$network_id,
			),
		);

		return \sprintf(
			'<a href="%s">%s</a>',
			esc_url( $detail_url ),
			esc_html( (string) ( $item['network_label'] ?? (string) $network_id ) ),
		);
	}

	/**
	 * Render a date/time column.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_last_seen( array $item ): string {
		return $this->format_datetime( (string) ( $item['last_seen'] ?? '' ) );
	}

	/**
	 * Render last_updated column.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_last_updated( array $item ): string {
		return $this->format_datetime( (string) ( $item['last_updated'] ?? '' ) );
	}

	/**
	 * Return CSS class for stale sites.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	protected function get_row_class( array $item ): string {
		if ( isset( $item['stale'] ) && $item['stale'] === true ) {
			return 'smd-stale';
		}

		return '';
	}

	/**
	 * Display the table with vulnerability status.
	 *
	 * @return void
	 */
	public function display(): void {
		if ( $this->api_error !== null ) {
			\printf(
				'<div class="notice notice-error"><p>%s: %s</p></div>',
				esc_html( (string) ( $this->api_error['error'] ?? 'error' ) ),
				esc_html( (string) ( $this->api_error['message'] ?? 'Unknown error.' ) ),
			);
			return;
		}

		$this->render_last_checked();
		$this->render_vuln_status();
		parent::display();
	}

	/**
	 * Render vulnerability provider status.
	 *
	 * @return void
	 */
	private function render_vuln_status(): void {
		$client = ApiClient::from_settings();
		$status = $client->get_vulnerability_status();

		if ( isset( $status['error'] ) || ( $status['enabled'] ?? false ) !== true ) {
			echo '<p class="smd-vuln-status">';
			esc_html_e( 'Vulnerability scanning: not configured.', 'site-bookkeeper-dashboard' );
			echo '</p>';
			return;
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$parts = [];

		foreach ( $status['providers'] ?? [] as $provider ) {
			$name = esc_html( (string) ( $provider['name'] ?? '' ) );
			$last = $provider['last_sync'] ?? null;

			if ( $last !== null ) {
				$date = wp_date( $format, \strtotime( $last ) );
				$parts[] = \sprintf( '<strong>%s</strong> (last sync: %s)', $name, esc_html( (string) $date ) );
			} else {
				$parts[] = \sprintf( '<strong>%s</strong> (never synced)', $name );
			}
		}

		echo '<p class="smd-vuln-status">';
		\printf(
			'%s %s',
			esc_html__( 'Vulnerability providers:', 'site-bookkeeper-dashboard' ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is escaped above.
			\implode( ', ', $parts ),
		);
		echo '</p>';
	}

	/**
	 * Format an ISO date string to the site's configured format.
	 *
	 * @param string $raw ISO 8601 date string.
	 *
	 * @return string
	 */
	private function format_datetime( string $raw ): string {
		if ( $raw === '' ) {
			return '&mdash;';
		}

		$timestamp = \strtotime( $raw );
		if ( $timestamp === false ) {
			return esc_html( $raw );
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return esc_html( (string) wp_date( $format, $timestamp ) );
	}

	/**
	 * Check whether any site has a network_id.
	 *
	 * @param array<int, array<string, mixed>> $sites Site data rows.
	 *
	 * @return bool
	 */
	private function detect_networks( array $sites ): bool {
		foreach ( $sites as $site ) {
			$network_id = $site['network_id'] ?? null;
			if ( $network_id !== null && $network_id !== '' ) {
				return true;
			}
		}

		return false;
	}
}
