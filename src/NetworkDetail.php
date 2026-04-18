<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Network detail view renderer.
 *
 * Renders the full report for a single network: network
 * info, network-activated plugins, super admins, network
 * settings, and subsites list.
 */
class NetworkDetail {

	/**
	 * Render the complete network detail view.
	 *
	 * @param array<string, mixed> $network Full network data from the API.
	 *
	 * @return void
	 */
	public static function render( array $network ): void {
		self::render_network_info( $network );
		self::render_network_plugins( $network );
		self::render_super_admins( $network );
		self::render_network_settings( $network );
		self::render_subsites( $network );
	}

	/**
	 * Render the network info section.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private static function render_network_info( array $network ): void {
		$fields = [
			'main_site_url' => 'Main Site URL',
			'subsite_count' => 'Subsite Count',
			'last_seen'     => 'Last Seen',
		];

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Network Info', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped smd-kv-table">';

		foreach ( $fields as $key => $label ) {
			if ( ! isset( $network[ $key ] ) ) {
				continue;
			}

			$value = $network[ $key ];
			if ( \is_bool( $value ) ) {
				$value = $value ? 'Yes' : 'No';
			}

			echo '<tr>';
			echo '<th>' . esc_html( $label ) . '</th>';
			echo '<td>' . esc_html( (string) $value ) . '</td>';
			echo '</tr>';
		}

		echo '</table></div>';
	}

	/**
	 * Render the network-activated plugins table.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private static function render_network_plugins( array $network ): void {
		$plugins = $network['network_plugins'] ?? [];
		if ( ! \is_array( $plugins ) || $plugins === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Network-Activated Plugins', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Slug', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Version', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Update', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $plugins as $plugin ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $plugin['slug'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $plugin['name'] ?? '' ) ) . '</td>';
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
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the super admins table.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private static function render_super_admins( array $network ): void {
		$admins = $network['super_admins'] ?? [];
		if ( ! \is_array( $admins ) || $admins === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Super Admins', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Login', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Display Name', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $admins as $admin ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $admin['user_login'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $admin['display_name'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $admin['email'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render network settings as key-value table.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private static function render_network_settings( array $network ): void {
		$settings = $network['network_settings'] ?? [];
		if ( ! \is_array( $settings ) || $settings === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Network Settings', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped smd-kv-table">';

		foreach ( $settings as $setting ) {
			if ( ! \is_array( $setting ) ) {
				continue;
			}

			echo '<tr>';
			echo '<th>' . esc_html( (string) ( $setting['label'] ?? $setting['key'] ?? '' ) ) . '</th>';
			echo '<td>' . esc_html( (string) ( $setting['value'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</table></div>';
	}

	/**
	 * Render the subsites table with links to site detail.
	 *
	 * @param array<string, mixed> $network Network data.
	 *
	 * @return void
	 */
	private static function render_subsites( array $network ): void {
		$subsites = $network['subsites'] ?? [];
		if ( ! \is_array( $subsites ) || $subsites === [] ) {
			return;
		}

		echo '<div class="smd-detail-section">';
		echo '<h2>' . esc_html__( 'Subsites', 'site-bookkeeper-dashboard' ) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Site', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '<th class="column-state">' . esc_html__( 'State', 'site-bookkeeper-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $subsites as $subsite ) {
			if ( ! \is_array( $subsite ) ) {
				continue;
			}

			$detail_url = admin_url(
				\sprintf(
					'admin.php?page=site_bookkeeper_dashboard_detail&site_id=%s',
					$subsite['id'] ?? '',
				),
			);

			$state = ApiListTable::derive_state( $subsite );
			$row_class = match ( $state ) {
				ApiListTable::STATE_STALE_OVERDUE => 'smd-overdue',
				ApiListTable::STATE_OVERDUE       => 'smd-overdue',
				ApiListTable::STATE_STALE         => 'smd-stale',
				default                               => '',
			};

			\printf( '<tr class="%s">', esc_attr( $row_class ) );
			echo '<td>';
			\printf(
				'<a href="%s">%s</a>',
				esc_url( $detail_url ),
				esc_html( (string) ( $subsite['label'] ?? $subsite['site_url'] ?? '' ) ),
			);
			echo '</td>';
			echo '<td>' . esc_html( (string) ( $subsite['site_url'] ?? '' ) ) . '</td>';
			echo '<td class="column-state">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped inside helper.
			echo ApiListTable::state_badge_html( $state );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
