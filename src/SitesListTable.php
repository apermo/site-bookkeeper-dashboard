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
	 * All items before filtering (for building filter dropdowns).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $all_items = [];

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

		// Add computed fields for sorting/filtering.
		foreach ( $sites as &$site ) {
			$site['pending_updates'] = ( (int) ( $site['pending_plugin_updates'] ?? 0 ) )
				+ ( (int) ( $site['pending_theme_updates'] ?? 0 ) );
			$cat = $site['category'] ?? null;
			$site['category_name'] = \is_array( $cat ) ? ( $cat['name'] ?? '' ) : '';
			$site['category_slug'] = \is_array( $cat ) ? ( $cat['slug'] ?? '' ) : '';
		}
		unset( $site );

		$this->all_items = $sites;

		return $this->apply_filters( $sites );
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		$columns = [
			'label'            => 'Site',
			'category_name'    => 'Category',
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
			'category_name'    => [ 'category_name', false ],
			'environment_type' => [ 'environment_type', false ],
			'wp_version'       => [ 'wp_version', false ],
			'php_version'      => [ 'php_version', false ],
			'pending_updates'  => [ 'pending_updates', false ],
			'last_seen'        => [ 'last_seen', true ],
			'last_updated'     => [ 'last_updated', false ],
		];
	}

	/**
	 * Render filter dropdowns above the table.
	 *
	 * @param string $which Top or bottom position.
	 *
	 * @return void
	 */
	protected function extra_tablenav( $which ): void { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- WP_List_Table override.
		if ( $which !== 'top' ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$current_cat = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$current_env = isset( $_GET['environment_type'] ) ? sanitize_text_field( wp_unslash( $_GET['environment_type'] ) ) : '';
		$current_wp = isset( $_GET['wp_version'] ) ? sanitize_text_field( wp_unslash( $_GET['wp_version'] ) ) : '';
		$current_updates = isset( $_GET['has_updates'] ) ? sanitize_text_field( wp_unslash( $_GET['has_updates'] ) ) : '';
		// phpcs:enable

		echo '<div class="alignleft actions">';
		$this->render_category_filter( $current_cat );
		$this->render_env_filter( $current_env );
		$this->render_wp_filter( $current_wp );
		$this->render_updates_filter( $current_updates );
		submit_button( __( 'Filter', 'site-bookkeeper-dashboard' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Apply active filters to the items array.
	 *
	 * @param array<int, array<string, mixed>> $items Site data rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_filters( array $items ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$cat_filter = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$env_filter = isset( $_GET['environment_type'] ) ? sanitize_text_field( wp_unslash( $_GET['environment_type'] ) ) : '';
		$wp_filter = isset( $_GET['wp_version'] ) ? sanitize_text_field( wp_unslash( $_GET['wp_version'] ) ) : '';
		$updates_filter = isset( $_GET['has_updates'] ) ? sanitize_text_field( wp_unslash( $_GET['has_updates'] ) ) : '';
		// phpcs:enable

		if ( $cat_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['category_slug'] ?? '' ) === $cat_filter,
			);
		}

		if ( $env_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['environment_type'] ?? '' ) === $env_filter,
			);
		}

		if ( $wp_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['wp_version'] ?? '' ) === $wp_filter,
			);
		}

		if ( $updates_filter === 'any' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['pending_updates'] ?? 0 ) > 0,
			);
		} elseif ( $updates_filter === 'none' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['pending_updates'] ?? 0 ) === 0,
			);
		}

		return \array_values( $items );
	}

	/**
	 * Render the environment type filter dropdown.
	 *
	 * @param string $current Currently selected value.
	 *
	 * @return void
	 */
	/**
	 * Render the category filter dropdown.
	 *
	 * @param string $current Currently selected slug.
	 *
	 * @return void
	 */
	private function render_category_filter( string $current ): void {
		$categories = [];
		foreach ( $this->all_items as $item ) {
			$slug = $item['category_slug'] ?? '';
			$name = $item['category_name'] ?? '';
			if ( $slug !== '' && $name !== '' ) {
				$categories[ $slug ] = $name;
			}
		}
		\asort( $categories );

		echo '<select name="category">';
		echo '<option value="">' . esc_html__( 'All categories', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $categories as $slug => $name ) {
			\printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( $name ),
			);
		}
		echo '</select>';
	}

	/**
	 * Render the environment type filter dropdown.
	 *
	 * @param string $current Currently selected value.
	 *
	 * @return void
	 */
	private function render_env_filter( string $current ): void {
		$options = [ 'production', 'staging', 'development', 'local' ];

		echo '<select name="environment_type">';
		echo '<option value="">' . esc_html__( 'All environments', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $options as $option ) {
			\printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option ),
				selected( $current, $option, false ),
				esc_html( \ucfirst( $option ) ),
			);
		}
		echo '</select>';
	}

	/**
	 * Render the WordPress version filter dropdown.
	 *
	 * @param string $current Currently selected value.
	 *
	 * @return void
	 */
	private function render_wp_filter( string $current ): void {
		$versions = [];
		foreach ( $this->all_items as $item ) {
			$version = $item['wp_version'] ?? '';
			if ( $version !== '' ) {
				$versions[ $version ] = true;
			}
		}
		\uksort( $versions, 'version_compare' );
		$versions = \array_reverse( \array_keys( $versions ) );

		echo '<select name="wp_version">';
		echo '<option value="">' . esc_html__( 'All WP versions', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $versions as $version ) {
			\printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $version ),
				selected( $current, $version, false ),
				esc_html( $version ),
			);
		}
		echo '</select>';
	}

	/**
	 * Render the pending updates filter dropdown.
	 *
	 * @param string $current Currently selected value.
	 *
	 * @return void
	 */
	private function render_updates_filter( string $current ): void {
		echo '<select name="has_updates">';
		echo '<option value="">' . esc_html__( 'All update states', 'site-bookkeeper-dashboard' ) . '</option>';
		\printf(
			'<option value="any" %s>%s</option>',
			selected( $current, 'any', false ),
			esc_html__( 'Has updates', 'site-bookkeeper-dashboard' ),
		);
		\printf(
			'<option value="none" %s>%s</option>',
			selected( $current, 'none', false ),
			esc_html__( 'Up to date', 'site-bookkeeper-dashboard' ),
		);
		echo '</select>';
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

		if ( isset( $item['overdue'] ) && $item['overdue'] === true ) {
			return 'smd-overdue';
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

		$this->render_vuln_status();
		parent::display();
	}

	/**
	 * Return form page slug for filter wrapping.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return 'site_bookkeeper_dashboard';
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
