<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Site detail view renderer.
 *
 * Renders the full report for a single site: environment
 * info, plugins, themes, custom fields, users, and roles.
 */
class SiteDetail {

	/**
	 * Render the complete site detail view.
	 *
	 * @param array<string, mixed> $site Full site data from the API.
	 *
	 * @return void
	 */
	public static function render( array $site ): void {
		self::render_category_and_notes( $site );
		self::render_environment( $site );
		self::render_plugins( $site );
		self::render_themes( $site );
		self::render_custom_fields( $site );
		self::render_users( $site );
		self::render_roles( $site );
	}

	/**
	 * Render category selector and notes editor.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_category_and_notes( array $site ): void {
		$site_id = (string) ( $site['id'] ?? '' );
		$category_id = (string) ( $site['category_id'] ?? '' );
		$notes = (string) ( $site['notes'] ?? '' );
		$notes_hash = (string) ( $site['notes_hash'] ?? '' );

		$client = ApiClient::from_settings();
		$categories_data = $client->get_categories();
		$categories = $categories_data['categories'] ?? [];

		// Handle save action.
		self::handle_site_meta_save( $site_id );

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Category & Notes', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'sbd_site_meta_' . $site_id );
		echo '<input type="hidden" name="sbd_site_id" value="' . esc_attr( $site_id ) . '" />';
		echo '<input type="hidden" name="sbd_notes_hash" value="' . esc_attr( $notes_hash ) . '" />';

		echo '<table class="form-table"><tbody>';

		// Category selector.
		echo '<tr><th scope="row">' . esc_html__( 'Category', 'site-bookkeeper-dashboard' ) . '</th><td>';
		echo '<select name="sbd_category_id">';
		echo '<option value="">' . esc_html__( '— None —', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $categories as $category ) {
			\printf(
				'<option value="%s" %s>%s (%dh)</option>',
				esc_attr( (string) $category['id'] ),
				selected( $category_id, (string) $category['id'], false ),
				esc_html( (string) $category['name'] ),
				(int) ( $category['overdue_hours'] ?? 48 ),
			);
		}
		echo '</select></td></tr>';

		// Notes textarea.
		echo '<tr><th scope="row">' . esc_html__( 'Notes', 'site-bookkeeper-dashboard' ) . '</th><td>';
		echo '<textarea name="sbd_notes" rows="4" class="large-text">' . esc_textarea( $notes ) . '</textarea>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save', 'site-bookkeeper-dashboard' ) );
		echo '</form></div>';
	}

	/**
	 * Handle the site meta save form submission.
	 *
	 * @param string $site_id Site UUID.
	 *
	 * @return void
	 */
	private static function handle_site_meta_save( string $site_id ): void {
		if ( ! isset( $_POST['sbd_site_id'] ) || $_POST['sbd_site_id'] !== $site_id ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sbd_site_meta_' . $site_id ) ) {
			return;
		}

		$client = ApiClient::from_settings();
		$patch_data = [
			'category_id' => sanitize_text_field( wp_unslash( $_POST['sbd_category_id'] ?? '' ) ),
			'notes' => sanitize_textarea_field( wp_unslash( $_POST['sbd_notes'] ?? '' ) ),
			'notes_hash' => sanitize_text_field( wp_unslash( $_POST['sbd_notes_hash'] ?? '' ) ),
		];

		$result = $client->patch_site( $site_id, $patch_data );

		if ( isset( $result['error'] ) && $result['error'] === 'conflict' ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Notes were modified by another user. Your changes were not saved. Please review and try again.', 'site-bookkeeper-dashboard' );
			echo '</p></div>';
			return;
		}

		if ( isset( $result['error'] ) ) {
			\printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( (string) ( $result['message'] ?? 'Save failed.' ) ),
			);
			return;
		}

		ApiClient::flush_all_caches();
		echo '<div class="notice notice-success"><p>';
		esc_html_e( 'Saved.', 'site-bookkeeper-dashboard' );
		echo '</p></div>';
	}

	/**
	 * Render the environment info section.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_environment( array $site ): void {
		$fields = [
			'environment_type' => 'Environment',
			'wp_version'       => 'WordPress Version',
			'php_version'      => 'PHP Version',
			'db_version'       => 'Database Version',
			'multisite'        => 'Multisite',
			'site_url'         => 'Site URL',
		];

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Environment', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped smd-kv-table">';

		foreach ( $fields as $key => $label ) {
			if ( ! isset( $site[ $key ] ) ) {
				continue;
			}

			$value = $site[ $key ];
			if ( \is_bool( $value ) ) {
				$value = $value ? 'Yes' : 'No';
			}

			echo '<tr>';
			echo '<th>' . esc_html( $label ) . '</th>';
			echo '<td>' . esc_html( (string) $value ) . '</td>';
			echo '</tr>';
		}

		if ( isset( $site['active_theme'] ) ) {
			echo '<tr>';
			echo '<th>' . esc_html__( 'Active Theme', 'site-bookkeeper-dashboard' ) . '</th>';
			echo '<td>' . esc_html( (string) $site['active_theme'] ) . '</td>';
			echo '</tr>';
		}

		echo '</table></div>';
	}

	/**
	 * Render the plugins table section.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_plugins( array $site ): void {
		$plugins = $site['plugins'] ?? [];
		if ( ! \is_array( $plugins ) || $plugins === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Plugins', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Plugin', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Version', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Update', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Active', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Last Updated', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $plugins as $plugin ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $plugin['name'] ?? $plugin['slug'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $plugin['version'] ?? '' ) ) . '</td>';
			echo '<td>';
			$update = $plugin['update_available'] ?? '';
			if ( $update !== '' ) {
				echo '<span class="smd-update-available">';
				echo esc_html( (string) $update );
				echo '</span>';
			} else {
				echo '&mdash;';
			}
			echo '</td>';
			echo '<td>' . esc_html( isset( $plugin['active'] ) && $plugin['active'] === true ? 'Yes' : 'No' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $plugin['last_updated'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the themes table section.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_themes( array $site ): void {
		$themes = $site['themes'] ?? [];
		if ( ! \is_array( $themes ) || $themes === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Themes', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Theme', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Version', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Update', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Active', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Last Updated', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $themes as $theme ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $theme['name'] ?? $theme['slug'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $theme['version'] ?? '' ) ) . '</td>';
			echo '<td>';
			$update = $theme['update_available'] ?? '';
			if ( $update !== '' ) {
				echo '<span class="smd-update-available">';
				echo esc_html( (string) $update );
				echo '</span>';
			} else {
				echo '&mdash;';
			}
			echo '</td>';
			echo '<td>' . esc_html( isset( $theme['active'] ) && $theme['active'] === true ? 'Yes' : 'No' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $theme['last_updated'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render custom fields as key-value table.
	 *
	 * Supports optional status badges (good, warning, critical).
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_custom_fields( array $site ): void {
		$fields = $site['custom_fields'] ?? [];
		if ( ! \is_array( $fields ) || $fields === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Custom Fields', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped smd-kv-table">';

		foreach ( $fields as $field ) {
			if ( ! \is_array( $field ) ) {
				continue;
			}

			echo '<tr>';
			echo '<th>' . esc_html( (string) ( $field['label'] ?? $field['key'] ?? '' ) ) . '</th>';
			echo '<td>';

			$field_key = $field['key'] ?? '';
			$field_value = (string) ( $field['value'] ?? '' );
			if ( $field_key === 'site_health_url' && $field_value !== '' ) {
				\printf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( $field_value ),
					esc_html__( 'Open Site Health', 'site-bookkeeper-dashboard' ),
				);
			} else {
				echo esc_html( $field_value );
			}

			$status = $field['status'] ?? '';
			if ( $status !== '' ) {
				\printf(
					' <span class="smd-badge smd-badge-%s">%s</span>',
					esc_attr( (string) $status ),
					esc_html( (string) $status ),
				);
			}

			echo '</td></tr>';
		}

		echo '</table></div>';
	}

	/**
	 * Render the users table section.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_users( array $site ): void {
		$users = $site['users'] ?? [];
		if ( ! \is_array( $users ) || $users === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Users', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Login', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Display Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Role', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Meta', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $users as $user ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $user['user_login'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $user['display_name'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $user['email'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $user['role'] ?? '' ) ) . '</td>';
			echo '<td>';
			$meta = $user['meta'] ?? [];
			if ( \is_array( $meta ) ) {
				$parts = [];
				foreach ( $meta as $meta_key => $meta_value ) {
					$parts[] = esc_html( $meta_key . ': ' . $meta_value );
				}
				// Each part is already escaped via esc_html() above.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo \implode( ', ', $parts );
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the roles section.
	 *
	 * Highlights custom or modified roles.
	 *
	 * @param array<string, mixed> $site Site data.
	 *
	 * @return void
	 */
	private static function render_roles( array $site ): void {
		$roles = $site['roles'] ?? [];
		if ( ! \is_array( $roles ) || $roles === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Roles', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Role', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Capabilities', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $roles as $role ) {
			$is_custom = ( $role['custom'] ?? false ) === true;
			$is_modified = ( $role['modified'] ?? false ) === true;
			$row_class = ( $is_custom || $is_modified ) ? 'smd-has-updates' : '';

			echo '<tr class="' . esc_attr( $row_class ) . '">';
			echo '<td>' . esc_html( (string) ( $role['name'] ?? '' ) ) . '</td>';
			echo '<td>';
			if ( $is_custom ) {
				echo '<span class="smd-badge smd-badge-warning">Custom</span>';
			} elseif ( $is_modified ) {
				echo '<span class="smd-badge smd-badge-warning">Modified</span>';
			} else {
				echo 'Default';
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) ( $role['capability_count'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
