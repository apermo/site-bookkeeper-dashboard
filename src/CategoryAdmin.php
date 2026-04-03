<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Category management on the settings page.
 *
 * Uses WP REST API for CRUD operations with visual feedback.
 */
class CategoryAdmin {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'site-bookkeeper/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/categories',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'rest_save' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories/(?P<id>[a-f0-9-]+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ self::class, 'rest_delete' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories/reorder',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'rest_reorder' ],
				'permission_callback' => [ self::class, 'check_permission' ],
			],
		);
	}

	/**
	 * Permission check for REST endpoints.
	 *
	 * @return bool
	 */
	public static function check_permission(): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Enqueue scripts on the settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		if ( ! \str_contains( $hook_suffix, 'site_bookkeeper_dashboard_settings' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'sbd-category-admin',
			plugins_url( 'assets/js/category-admin.js', Plugin::file() ),
			[ 'jquery', 'jquery-ui-sortable' ],
			Plugin::VERSION,
			true,
		);
		wp_localize_script(
			'sbd-category-admin',
			'sbdCategories',
			[
				'restUrl' => rest_url( self::REST_NAMESPACE . '/categories' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			],
		);
	}

	/**
	 * Render the categories management section.
	 *
	 * @return void
	 */
	public static function render(): void {
		$client = ApiClient::from_settings();
		$data = $client->get_categories();
		$categories = $data['categories'] ?? [];

		echo '<hr>';
		echo '<h2>' . esc_html__( 'Site Categories', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<p>' . esc_html__( 'Categories classify sites by importance. Each category defines how many hours before pending updates are considered overdue. Drag rows to reorder.', 'site-bookkeeper-dashboard' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped" id="sbd-categories-table">';
		echo '<thead><tr>';
		echo '<th style="width:20px"></th>';
		echo '<th>' . esc_html__( 'Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th style="width:120px">' . esc_html__( 'Overdue (hours)', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th style="width:80px">' . esc_html__( 'Actions', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="sbd-categories-body">';

		foreach ( $categories as $category ) {
			self::render_row( $category );
		}

		echo '</tbody></table>';

		echo '<p><button type="button" class="button" id="sbd-add-category">';
		echo '+ ' . esc_html__( 'Add Category', 'site-bookkeeper-dashboard' );
		echo '</button></p>';
	}

	/**
	 * Render a single category row.
	 *
	 * @param array<string, mixed> $category Category data.
	 *
	 * @return void
	 */
	private static function render_row( array $category ): void {
		$cat_id = esc_attr( (string) ( $category['id'] ?? '' ) );
		$name = esc_attr( (string) ( $category['name'] ?? '' ) );
		$overdue = (int) ( $category['overdue_hours'] ?? 48 );

		// All values are pre-escaped via esc_attr() and (int) cast above.
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr class="sbd-cat-row" data-id="' . $cat_id . '">';
		echo '<td class="sbd-drag-handle" style="cursor:move">&#9776;</td>';
		\printf( '<td><input type="text" class="sbd-cat-name regular-text" value="%s" /></td>', $name );
		\printf( '<td><input type="number" class="sbd-cat-overdue" value="%d" min="1" style="width:80px" /></td>', $overdue );
		// phpcs:enable
		echo '<td>';
		echo '<button type="button" class="button-link sbd-cat-delete" style="color:#b32d2e">';
		echo esc_html__( 'Delete', 'site-bookkeeper-dashboard' );
		echo '</button>';
		echo '<span class="sbd-cat-status"></span>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * REST: Save (create or update) a category.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_save( WP_REST_Request $request ): WP_REST_Response {
		$cat_id = sanitize_text_field( (string) $request->get_param( 'id' ) );
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$overdue = (int) $request->get_param( 'overdue_hours' );

		if ( $name === '' ) {
			return new WP_REST_Response( [ 'error' => 'Name is required.' ], 400 );
		}

		$client = ApiClient::from_settings();

		if ( $cat_id === '' ) {
			$result = $client->create_category(
				[
					'name'         => $name,
					'overdue_hours' => $overdue,
				],
			);
		} else {
			$result = $client->update_category(
				$cat_id,
				[
					'name'         => $name,
					'overdue_hours' => $overdue,
				],
			);
		}

		ApiClient::flush_all_caches();

		return new WP_REST_Response( $result );
	}

	/**
	 * REST: Delete a category.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_delete( WP_REST_Request $request ): WP_REST_Response {
		$cat_id = sanitize_text_field( (string) $request->get_param( 'id' ) );

		if ( $cat_id === '' ) {
			return new WP_REST_Response( [ 'error' => 'Missing ID.' ], 400 );
		}

		$client = ApiClient::from_settings();
		$client->delete_category( $cat_id );

		ApiClient::flush_all_caches();

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * REST: Reorder categories.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_reorder( WP_REST_Request $request ): WP_REST_Response {
		$order = $request->get_param( 'order' );
		if ( ! \is_array( $order ) ) {
			return new WP_REST_Response( [ 'error' => 'Missing order.' ], 400 );
		}

		$client = ApiClient::from_settings();
		foreach ( $order as $index => $cat_id ) {
			$cat_id = sanitize_text_field( (string) $cat_id );
			if ( $cat_id !== '' ) {
				$client->update_category( $cat_id, [ 'sort_order' => (int) $index ] );
			}
		}

		ApiClient::flush_all_caches();

		return new WP_REST_Response( [ 'status' => 'ok' ] );
	}
}
