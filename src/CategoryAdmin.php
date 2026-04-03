<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Category management on the settings page.
 *
 * Handles CRUD operations for site categories via the hub API.
 */
class CategoryAdmin {

	/**
	 * Process category form submissions.
	 *
	 * Must be called early (admin_init) before output.
	 *
	 * @return void
	 */
	public static function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_create();
		self::handle_update();
		self::handle_delete();
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
		echo '<p>' . esc_html__( 'Categories classify sites by importance. Each category defines how many hours before pending updates are considered overdue.', 'site-bookkeeper-dashboard' ) . '</p>';

		if ( $categories !== [] ) {
			self::render_table( $categories );
		}

		self::render_add_form();
	}

	/**
	 * Render the categories table with inline edit forms.
	 *
	 * @param array<int, array<string, mixed>> $categories Category rows.
	 *
	 * @return void
	 */
	private static function render_table( array $categories ): void {
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Overdue (hours)', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Sort Order', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $categories as $category ) {
			$cat_id = (string) $category['id'];
			echo '<tr>';
			echo '<form method="post">';
			wp_nonce_field( 'sbd_update_category_' . $cat_id );
			echo '<input type="hidden" name="sbd_update_category_id" value="' . esc_attr( $cat_id ) . '" />';

			\printf( '<td><input type="text" name="sbd_cat_name" value="%s" class="regular-text" /></td>', esc_attr( (string) $category['name'] ) );
			\printf( '<td><input type="text" name="sbd_cat_slug" value="%s" style="width:100px" /></td>', esc_attr( (string) $category['slug'] ) );
			\printf( '<td><input type="number" name="sbd_cat_overdue" value="%d" min="1" style="width:80px" /></td>', (int) $category['overdue_hours'] );
			\printf( '<td><input type="number" name="sbd_cat_order" value="%d" min="0" style="width:60px" /></td>', (int) $category['sort_order'] );
			echo '<td>';
			submit_button( __( 'Update', 'site-bookkeeper-dashboard' ), 'small', 'sbd_update_category', false );
			echo ' ';
			echo '</form>';

			// Delete form (separate to avoid nesting).
			echo '<form method="post" style="display:inline">';
			wp_nonce_field( 'sbd_delete_category_' . $cat_id );
			echo '<input type="hidden" name="sbd_delete_category_id" value="' . esc_attr( $cat_id ) . '" />';
			submit_button( __( 'Delete', 'site-bookkeeper-dashboard' ), 'small delete', 'sbd_delete_category', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the add category form.
	 *
	 * @return void
	 */
	private static function render_add_form(): void {
		echo '<h3>' . esc_html__( 'Add Category', 'site-bookkeeper-dashboard' ) . '</h3>';
		echo '<form method="post">';
		wp_nonce_field( 'sbd_create_category' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<td><input type="text" name="sbd_new_cat_name" class="regular-text" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Overdue (hours)', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<td><input type="number" name="sbd_new_cat_overdue" value="48" min="1" style="width:80px" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sort Order', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<td><input type="number" name="sbd_new_cat_order" value="0" min="0" style="width:60px" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add Category', 'site-bookkeeper-dashboard' ) );
		echo '</form>';
	}

	/**
	 * Handle create category submission.
	 *
	 * @return void
	 */
	private static function handle_create(): void {
		if ( ! isset( $_POST['sbd_new_cat_name'] ) ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_create_category' ) ) {
			return;
		}

		$client = ApiClient::from_settings();
		$client->create_category(
			[
				'name'         => sanitize_text_field( wp_unslash( $_POST['sbd_new_cat_name'] ) ),
				'overdue_hours' => (int) sanitize_text_field( wp_unslash( $_POST['sbd_new_cat_overdue'] ?? '48' ) ),
				'sort_order'   => (int) sanitize_text_field( wp_unslash( $_POST['sbd_new_cat_order'] ?? '0' ) ),
			],
		);

		ApiClient::flush_all_caches();
	}

	/**
	 * Handle update category submission.
	 *
	 * @return void
	 */
	private static function handle_update(): void {
		if ( ! isset( $_POST['sbd_update_category_id'] ) ) {
			return;
		}

		$cat_id = sanitize_text_field( wp_unslash( $_POST['sbd_update_category_id'] ) );
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_update_category_' . $cat_id ) ) {
			return;
		}

		$client = ApiClient::from_settings();
		$client->update_category(
			$cat_id,
			[
				'name'         => sanitize_text_field( wp_unslash( $_POST['sbd_cat_name'] ?? '' ) ),
				'slug'         => sanitize_text_field( wp_unslash( $_POST['sbd_cat_slug'] ?? '' ) ),
				'overdue_hours' => (int) sanitize_text_field( wp_unslash( $_POST['sbd_cat_overdue'] ?? '48' ) ),
				'sort_order'   => (int) sanitize_text_field( wp_unslash( $_POST['sbd_cat_order'] ?? '0' ) ),
			],
		);

		ApiClient::flush_all_caches();
	}

	/**
	 * Handle delete category submission.
	 *
	 * @return void
	 */
	private static function handle_delete(): void {
		if ( ! isset( $_POST['sbd_delete_category_id'] ) ) {
			return;
		}

		$cat_id = sanitize_text_field( wp_unslash( $_POST['sbd_delete_category_id'] ) );
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_delete_category_' . $cat_id ) ) {
			return;
		}

		$client = ApiClient::from_settings();
		$client->delete_category( $cat_id );

		ApiClient::flush_all_caches();
	}
}
