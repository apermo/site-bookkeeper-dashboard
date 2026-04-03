<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Category management on the settings page.
 *
 * Uses AJAX for CRUD operations with visual feedback.
 */
class CategoryAdmin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// phpcs:disable Apermo.WordPress.NoAdminAjax.Found -- Proxy to external hub API, no WP data to expose via REST.
		add_action( 'wp_ajax_sbd_category_save', [ self::class, 'ajax_save' ] );
		add_action( 'wp_ajax_sbd_category_delete', [ self::class, 'ajax_delete' ] );
		add_action( 'wp_ajax_sbd_category_reorder', [ self::class, 'ajax_reorder' ] );
		// phpcs:enable
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sbd_category_admin' ),
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
	 * AJAX: Save (create or update) a category.
	 *
	 * @return void
	 */
	public static function ajax_save(): void {
		check_ajax_referer( 'sbd_category_admin' );

		$cat_id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$overdue = (int) sanitize_text_field( wp_unslash( $_POST['overdue_hours'] ?? '48' ) );

		if ( $name === '' ) {
			wp_send_json_error( 'Name is required.' );
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

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Delete a category.
	 *
	 * @return void
	 */
	public static function ajax_delete(): void {
		check_ajax_referer( 'sbd_category_admin' );

		$cat_id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		if ( $cat_id === '' ) {
			wp_send_json_error( 'Missing ID.' );
		}

		$client = ApiClient::from_settings();
		$client->delete_category( $cat_id );

		ApiClient::flush_all_caches();

		wp_send_json_success();
	}

	/**
	 * AJAX: Reorder categories.
	 *
	 * @return void
	 */
	public static function ajax_reorder(): void {
		check_ajax_referer( 'sbd_category_admin' );

		$order = isset( $_POST['order'] ) ? \array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['order'] ) ) : [];

		$client = ApiClient::from_settings();
		foreach ( $order as $index => $cat_id ) {
			if ( $cat_id !== '' ) {
				$client->update_category( $cat_id, [ 'sort_order' => $index ] );
			}
		}

		ApiClient::flush_all_caches();

		wp_send_json_success();
	}
}
