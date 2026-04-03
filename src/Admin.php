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
		add_action( 'admin_enqueue_scripts', [ CategoryAdmin::class, 'enqueue_scripts' ] );
		add_action( 'admin_init', [ self::class, 'handle_cache_flush' ] );
		add_action( 'admin_init', [ self::class, 'handle_slug_cache_flush' ] );
		CategoryAdmin::init();
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @return void
	 */
	public static function register_pages(): void {
		$parent = 'site_bookkeeper_dashboard';

		add_menu_page(
			'Site Bookkeeper',
			'Site Bookkeeper',
			'manage_options',
			$parent,
			[ self::class, 'render_sites_page' ],
			'dashicons-admin-site-alt3',
			100,
		);

		$subpages = [
			[ 'Site Detail', '', 'detail', 'render_detail_page' ],
			[ 'Networks', 'Networks', 'networks', 'render_networks_page' ],
			[ 'Network Detail', '', 'network_detail', 'render_network_detail_page' ],
			[ 'Plugin Report', 'Plugins', 'plugins', 'render_plugin_report' ],
			[ 'Theme Report', 'Themes', 'themes', 'render_theme_report' ],
			[ 'User Search', 'User Search', 'users', 'render_user_search' ],
		];

		foreach ( $subpages as $page ) {
			add_submenu_page( $parent, $page[0], $page[1], 'manage_options', $parent . '_' . $page[2], [ self::class, $page[3] ] );
		}
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
	 * Handle the API cache flush action.
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
	 * Handle the slug cache flush action.
	 *
	 * @return void
	 */
	public static function handle_slug_cache_flush(): void {
		if ( ! isset( $_GET['sbd_flush_slugs'] ) || $_GET['sbd_flush_slugs'] !== '1' ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_flush_slugs' ) ) {
			return;
		}

		SlugResolver::flush();

		$redirect = remove_query_arg( [ 'sbd_flush_slugs', '_wpnonce' ] );
		$redirect = add_query_arg( 'sbd_slugs_flushed', '1', $redirect );
		wp_safe_redirect( $redirect );
		exit();
	}

	/**
	 * Render the sites overview page.
	 *
	 * @return void
	 */
	public static function render_sites_page(): void {
		$table = new SitesListTable();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Site Bookkeeper Dashboard', 'site-bookkeeper-dashboard' ) . '</h1>';
		$table->display();
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
		$table = new NetworksListTable();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Networks', 'site-bookkeeper-dashboard' ) . '</h1>';
		$table->display();
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
		$table = new PluginReport();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Cross-Site Plugin Report', 'site-bookkeeper-dashboard' ) . '</h1>';
		$table->display();
		echo '</div>';
	}

	/**
	 * Render the cross-site theme report page.
	 *
	 * @return void
	 */
	public static function render_theme_report(): void {
		$table = new ThemeReport();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Cross-Site Theme Report', 'site-bookkeeper-dashboard' ) . '</h1>';
		$table->display();
		echo '</div>';
	}

	/**
	 * Render the cross-site users page.
	 *
	 * @return void
	 */
	public static function render_user_search(): void {
		$table = new UsersListTable();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Users', 'site-bookkeeper-dashboard' ) . '</h1>';
		$table->display();
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
}
