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
		self::render_environment( $site );
		self::render_plugins( $site );
		self::render_themes( $site );
		self::render_custom_fields( $site );
		self::render_users( $site );
		self::render_roles( $site );
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
			'wp_version'  => 'WordPress Version',
			'php_version' => 'PHP Version',
			'db_version'  => 'Database Version',
			'multisite'   => 'Multisite',
			'site_url'    => 'Site URL',
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
			echo esc_html( (string) ( $field['value'] ?? '' ) );

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
			echo '<td>' . esc_html( (string) ( $user['login'] ?? '' ) ) . '</td>';
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
