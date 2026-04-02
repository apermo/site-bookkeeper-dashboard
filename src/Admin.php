<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Admin page controller.
 *
 * Registers the top-level menu, submenu pages, and renders
 * the sites overview and detail views.
 */
class Admin {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_styles' ] );
		add_action( 'admin_init', [ self::class, 'handle_cache_flush' ] );
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @return void
	 */
	public static function register_pages(): void {
		add_menu_page(
			'Site Bookkeeper',
			'Site Bookkeeper',
			'manage_options',
			'site_bookkeeper_dashboard',
			[ self::class, 'render_sites_page' ],
			'dashicons-admin-site-alt3',
			100,
		);

		add_submenu_page(
			'site_bookkeeper_dashboard',
			'Site Detail',
			'',
			'manage_options',
			'site_bookkeeper_dashboard_detail',
			[ self::class, 'render_detail_page' ],
		);

		add_submenu_page(
			'site_bookkeeper_dashboard',
			'Networks',
			'Networks',
			'manage_options',
			'site_bookkeeper_dashboard_networks',
			[ self::class, 'render_networks_page' ],
		);

		add_submenu_page(
			'site_bookkeeper_dashboard',
			'Network Detail',
			'',
			'manage_options',
			'site_bookkeeper_dashboard_network_detail',
			[ self::class, 'render_network_detail_page' ],
		);

		add_submenu_page(
			'site_bookkeeper_dashboard',
			'Plugin Report',
			'Plugins',
			'manage_options',
			'site_bookkeeper_dashboard_plugins',
			[ self::class, 'render_plugin_report' ],
		);

		add_submenu_page(
			'site_bookkeeper_dashboard',
			'Theme Report',
			'Themes',
			'manage_options',
			'site_bookkeeper_dashboard_themes',
			[ self::class, 'render_theme_report' ],
		);
	}

	/**
	 * Enqueue admin styles on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public static function enqueue_styles( string $hook_suffix ): void {
		if ( ! \str_contains( $hook_suffix, 'site_bookkeeper_dashboard' ) ) {
			return;
		}

		wp_enqueue_style(
			'site-bookkeeper-dashboard',
			plugins_url( 'assets/css/admin.css', Plugin::file() ),
			[],
			Plugin::VERSION,
		);
	}

	/**
	 * Handle the cache flush action.
	 *
	 * Clears all API transients and redirects back to the referring page.
	 *
	 * @return void
	 */
	public static function handle_cache_flush(): void {
		if ( ! isset( $_GET['sbd_flush'] ) || $_GET['sbd_flush'] !== '1' ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_flush_cache' ) ) {
			return;
		}

		ApiClient::flush_all_caches();

		$redirect = remove_query_arg( [ 'sbd_flush', '_wpnonce' ] );
		wp_safe_redirect( $redirect );
		exit();
	}

	/**
	 * Render the "last checked / check again" line.
	 *
	 * @return void
	 */
	private static function render_last_checked(): void {
		$flush_url = wp_nonce_url(
			add_query_arg( 'sbd_flush', '1' ),
			'sbd_flush_cache',
		);

		$last_checked = ApiClient::get_last_checked();
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		if ( $last_checked !== null ) {
			$date_string = wp_date( $format, $last_checked );
			\printf(
				'<p class="smd-last-checked">%s <a href="%s">%s</a></p>',
				esc_html(
					\sprintf(
						/* translators: %s: formatted date/time */
						__( 'Last checked on %s.', 'site-bookkeeper-dashboard' ),
						$date_string,
					),
				),
				esc_url( $flush_url ),
				esc_html__( 'Check again.', 'site-bookkeeper-dashboard' ),
			);
		} else {
			\printf(
				'<p class="smd-last-checked"><a href="%s">%s</a></p>',
				esc_url( $flush_url ),
				esc_html__( 'Check now.', 'site-bookkeeper-dashboard' ),
			);
		}
	}

	/**
	 * Render the sites overview page.
	 *
	 * @return void
	 */
	public static function render_sites_page(): void {
		$client = ApiClient::from_settings();
		$data   = $client->get_sites();

		$table = new SitesListTable();
		$sites = $data['sites'] ?? [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sort params.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'label';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
		// phpcs:enable

		$sites = $table->sort_items( $sites, $orderby, $order );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Site Bookkeeper Dashboard', 'site-bookkeeper-dashboard' ) . '</h1>';

		if ( isset( $data['error'] ) ) {
			self::render_error( $data );
		} else {
			self::render_last_checked();
			self::render_sites_table( $table, $sites );
		}

		echo '</div>';
	}

	/**
	 * Render the site detail page.
	 *
	 * @return void
	 */
	public static function render_detail_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ID param.
		$site_id = isset( $_GET['site_id'] ) ? sanitize_text_field( wp_unslash( $_GET['site_id'] ) ) : '';

		if ( $site_id === '' ) {
			echo '<div class="wrap"><p>' . esc_html__( 'No site specified.', 'site-bookkeeper-dashboard' ) . '</p></div>';
			return;
		}

		$client = ApiClient::from_settings();
		$site   = $client->get_site( $site_id );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( (string) ( $site['label'] ?? 'Site Detail' ) ) . '</h1>';

		if ( isset( $site['error'] ) ) {
			self::render_error( $site );
		} else {
			SiteDetail::render( $site );
		}

		echo '</div>';
	}

	/**
	 * Render the networks overview page.
	 *
	 * @return void
	 */
	public static function render_networks_page(): void {
		$client = ApiClient::from_settings();
		$data   = $client->get_networks();

		$table    = new NetworksListTable();
		$networks = $data['networks'] ?? [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sort params.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'label';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
		// phpcs:enable

		$networks = $table->sort_items( $networks, $orderby, $order );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Networks', 'site-bookkeeper-dashboard' ) . '</h1>';

		if ( isset( $data['error'] ) ) {
			self::render_error( $data );
		} else {
			self::render_networks_table( $table, $networks );
		}

		echo '</div>';
	}

	/**
	 * Render the network detail page.
	 *
	 * @return void
	 */
	public static function render_network_detail_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ID param.
		$network_id = isset( $_GET['network_id'] ) ? sanitize_text_field( wp_unslash( $_GET['network_id'] ) ) : '';

		if ( $network_id === '' ) {
			echo '<div class="wrap"><p>';
			echo esc_html__( 'No network specified.', 'site-bookkeeper-dashboard' );
			echo '</p></div>';
			return;
		}

		$client  = ApiClient::from_settings();
		$network = $client->get_network( $network_id );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( (string) ( $network['label'] ?? 'Network Detail' ) ) . '</h1>';

		if ( isset( $network['error'] ) ) {
			self::render_error( $network );
		} else {
			NetworkDetail::render( $network );
		}

		echo '</div>';
	}

	/**
	 * Render the cross-site plugin report page.
	 *
	 * @return void
	 */
	public static function render_plugin_report(): void {
		$client = ApiClient::from_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$params = $outdated ? [ 'outdated' => 'true' ] : [];

		$data = $client->get_plugins( $params );
		$report = new PluginReport();
		$plugins = $data['plugins'] ?? [];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Cross-Site Plugin Report', 'site-bookkeeper-dashboard' ) . '</h1>';

		if ( isset( $data['error'] ) ) {
			self::render_error( $data );
		} else {
			self::render_last_checked();
			self::render_report_filter( 'site_bookkeeper_dashboard_plugins', $outdated );
			self::render_report_table( $report, $plugins );
		}

		echo '</div>';
	}

	/**
	 * Render the cross-site theme report page.
	 *
	 * @return void
	 */
	public static function render_theme_report(): void {
		$client = ApiClient::from_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$params = $outdated ? [ 'outdated' => 'true' ] : [];

		$data = $client->get_themes( $params );
		$report = new ThemeReport();
		$themes = $data['themes'] ?? [];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Cross-Site Theme Report', 'site-bookkeeper-dashboard' ) . '</h1>';

		if ( isset( $data['error'] ) ) {
			self::render_error( $data );
		} else {
			self::render_last_checked();
			self::render_report_filter( 'site_bookkeeper_dashboard_themes', $outdated );
			self::render_report_table( $report, $themes );
		}

		echo '</div>';
	}

	/**
	 * Render an API error notice.
	 *
	 * @param array<string, mixed> $data Error response data.
	 *
	 * @return void
	 */
	private static function render_error( array $data ): void {
		\printf(
			'<div class="notice notice-error"><p>%s: %s</p></div>',
			esc_html( (string) ( $data['error'] ?? 'error' ) ),
			esc_html( (string) ( $data['message'] ?? 'Unknown error.' ) ),
		);
	}

	/**
	 * Render the sites list table.
	 *
	 * @param SitesListTable                   $table List table instance.
	 * @param array<int, array<string, mixed>> $sites Site data rows.
	 *
	 * @return void
	 */
	private static function render_sites_table( SitesListTable $table, array $sites ): void {
		$columns = $table->get_columns( $sites );

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( $columns as $key => $label ) {
			echo '<th scope="col" class="column-' . esc_attr( $key ) . '">';
			echo esc_html( $label );
			echo '</th>';
		}
		echo '</tr></thead>';

		echo '<tbody>';
		if ( $sites === [] ) {
			echo '<tr><td colspan="' . \count( $columns ) . '">';
			esc_html_e( 'No sites found.', 'site-bookkeeper-dashboard' );
			echo '</td></tr>';
		}

		foreach ( $sites as $item ) {
			$row_class = $table->get_row_class( $item );
			echo '<tr class="' . esc_attr( $row_class ) . '">';
			foreach ( \array_keys( $columns ) as $column_name ) {
				echo '<td class="column-' . esc_attr( $column_name ) . '">';
				// Column renderers handle their own escaping.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_column( $table, $item, $column_name );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render the networks list table.
	 *
	 * @param NetworksListTable                $table    List table instance.
	 * @param array<int, array<string, mixed>> $networks Network data rows.
	 *
	 * @return void
	 */
	private static function render_networks_table( NetworksListTable $table, array $networks ): void {
		$columns = $table->get_columns();

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( $columns as $key => $label ) {
			echo '<th scope="col" class="column-' . esc_attr( $key ) . '">';
			echo esc_html( $label );
			echo '</th>';
		}
		echo '</tr></thead>';

		echo '<tbody>';
		if ( $networks === [] ) {
			echo '<tr><td colspan="' . \count( $columns ) . '">';
			esc_html_e( 'No networks found.', 'site-bookkeeper-dashboard' );
			echo '</td></tr>';
		}

		foreach ( $networks as $item ) {
			$row_class = $table->get_row_class( $item );
			echo '<tr class="' . esc_attr( $row_class ) . '">';
			foreach ( \array_keys( $columns ) as $column_name ) {
				echo '<td class="column-' . esc_attr( $column_name ) . '">';
				// Column renderers handle their own escaping.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_network_column( $table, $item, $column_name );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a single network column cell.
	 *
	 * @param NetworksListTable    $table       List table instance.
	 * @param array<string, mixed> $item        Network data row.
	 * @param string               $column_name Column key.
	 *
	 * @return string
	 */
	private static function render_network_column(
		NetworksListTable $table,
		array $item,
		string $column_name,
	): string {
		return match ( $column_name ) {
			'label'  => $table->column_label( $item ),
			'status' => $table->column_status( $item ),
			default  => $table->column_default( $item, $column_name ),
		};
	}

	/**
	 * Render a single column cell.
	 *
	 * @param SitesListTable       $table       List table instance.
	 * @param array<string, mixed> $item        Site data row.
	 * @param string               $column_name Column key.
	 *
	 * @return string
	 */
	private static function render_column(
		SitesListTable $table,
		array $item,
		string $column_name,
	): string {
		return match ( $column_name ) {
			'label'           => $table->column_label( $item ),
			'wp_version'      => $table->column_wp_version( $item ),
			'pending_updates' => $table->column_pending_updates( $item ),
			'network'         => $table->column_network( $item ),
			default           => $table->column_default( $item, $column_name ),
		};
	}

	/**
	 * Render the outdated-only filter toggle.
	 *
	 * @param string $page_slug Admin page slug.
	 * @param bool   $active    Whether the filter is active.
	 *
	 * @return void
	 */
	private static function render_report_filter( string $page_slug, bool $active ): void {
		$url = admin_url( 'admin.php?page=' . $page_slug );

		echo '<p>';
		if ( $active ) {
			\printf(
				'<a href="%s">%s</a> | <strong>%s</strong>',
				esc_url( $url ),
				esc_html__( 'All', 'site-bookkeeper-dashboard' ),
				esc_html__( 'Outdated only', 'site-bookkeeper-dashboard' ),
			);
		} else {
			\printf(
				'<strong>%s</strong> | <a href="%s">%s</a>',
				esc_html__( 'All', 'site-bookkeeper-dashboard' ),
				esc_url( $url . '&outdated=1' ),
				esc_html__( 'Outdated only', 'site-bookkeeper-dashboard' ),
			);
		}
		echo '</p>';
	}

	/**
	 * Render a report table (plugins or themes).
	 *
	 * @param PluginReport|ThemeReport         $report Report instance.
	 * @param array<int, array<string, mixed>> $items  Data rows.
	 *
	 * @return void
	 */
	private static function render_report_table( PluginReport|ThemeReport $report, array $items ): void {
		$columns = $report->get_columns();

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( $columns as $key => $label ) {
			echo '<th scope="col" class="column-' . esc_attr( $key ) . '">';
			echo esc_html( $label );
			echo '</th>';
		}
		echo '</tr></thead>';

		echo '<tbody>';
		if ( $items === [] ) {
			echo '<tr><td colspan="' . \count( $columns ) . '">';
			esc_html_e( 'No items found.', 'site-bookkeeper-dashboard' );
			echo '</td></tr>';
		}

		foreach ( $items as $item ) {
			echo '<tr>';
			foreach ( \array_keys( $columns ) as $column_name ) {
				echo '<td class="column-' . esc_attr( $column_name ) . '">';
				// Column renderers handle their own escaping.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_report_column( $report, $item, $column_name );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a single report column cell.
	 *
	 * @param PluginReport|ThemeReport $report      Report instance.
	 * @param array<string, mixed>     $item        Data row.
	 * @param string                   $column_name Column key.
	 *
	 * @return string
	 */
	private static function render_report_column(
		PluginReport|ThemeReport $report,
		array $item,
		string $column_name,
	): string {
		return match ( $column_name ) {
			'sites'    => $report->column_sites( $item ),
			'versions' => $report->column_versions( $item ),
			default    => $report->column_default( $item, $column_name ),
		};
	}
}
