<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard;

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
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @return void
	 */
	public static function register_pages(): void {
		add_menu_page(
			'Site Monitor',
			'Site Monitor',
			'manage_options',
			'site_monitor_dashboard',
			[ self::class, 'render_sites_page' ],
			'dashicons-admin-site-alt3',
			100,
		);

		add_submenu_page(
			'site_monitor_dashboard',
			'Site Detail',
			'',
			'manage_options',
			'site_monitor_dashboard_detail',
			[ self::class, 'render_detail_page' ],
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
		if ( ! \str_contains( $hook_suffix, 'site_monitor_dashboard' ) ) {
			return;
		}

		wp_enqueue_style(
			'site-monitor-dashboard',
			plugins_url( 'assets/css/admin.css', Plugin::file() ),
			[],
			Plugin::VERSION,
		);
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
		echo '<h1>' . esc_html__( 'Site Monitor Dashboard', 'site-monitor-dashboard' ) . '</h1>';

		if ( isset( $data['error'] ) ) {
			self::render_error( $data );
		} else {
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
			echo '<div class="wrap"><p>' . esc_html__( 'No site specified.', 'site-monitor-dashboard' ) . '</p></div>';
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
		if ( $sites === [] ) {
			echo '<tr><td colspan="' . \count( $columns ) . '">';
			esc_html_e( 'No sites found.', 'site-monitor-dashboard' );
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
			default           => $table->column_default( $item, $column_name ),
		};
	}
}
