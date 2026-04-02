<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site plugin report.
 *
 * Lists all known plugins across all monitored sites, showing
 * which sites have each plugin, versions, and update status.
 */
class PluginReport extends ApiListTable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'plugin',
				'plural'   => 'plugins',
				'ajax'     => false,
			],
		);
	}

	/**
	 * Fetch plugin data from the hub API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		$client = ApiClient::from_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$params = $outdated ? [ 'outdated' => 'true' ] : [];

		$data = $client->get_plugins( $params );

		if ( isset( $data['error'] ) ) {
			$this->api_error = $data;
			return [];
		}

		return $data['plugins'] ?? [];
	}

	/**
	 * Column definitions for the plugin report table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'name'     => 'Plugin',
			'slug'     => 'Slug',
			'sites'    => 'Sites',
			'versions' => 'Versions',
		];
	}

	/**
	 * Sortable column definitions.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'name' => [ 'name', false ],
			'slug' => [ 'slug', false ],
		];
	}

	/**
	 * Render the name column with a link to WordPress.org if available.
	 *
	 * @param array<string, mixed> $item Plugin data row.
	 *
	 * @return string
	 */
	public function column_name( array $item ): string {
		$name = esc_html( (string) ( $item['name'] ?? '' ) );
		$slug = (string) ( $item['slug'] ?? '' );
		$link = SlugResolver::plugin_url( $slug );

		if ( $link !== null ) {
			return \sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $link ), $name );
		}

		return $name;
	}

	/**
	 * Render the sites column showing site count.
	 *
	 * @param array<string, mixed> $item Plugin data row.
	 *
	 * @return string
	 */
	public function column_sites( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) ) {
			return '0';
		}

		return esc_html( (string) \count( $sites ) );
	}

	/**
	 * Render the versions column showing distinct versions.
	 *
	 * @param array<string, mixed> $item Plugin data row.
	 *
	 * @return string
	 */
	public function column_versions( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) ) {
			return '';
		}

		$by_version = [];
		$outdated_versions = [];
		foreach ( $sites as $site ) {
			if ( ! \is_array( $site ) || ! isset( $site['version'] ) ) {
				continue;
			}

			$version = (string) $site['version'];
			$domain = (string) \preg_replace( '#^https?://#', '', $site['site_url'] ?? '' );

			$by_version[ $version ][] = $domain;

			$update = (string) ( $site['update_available'] ?? '' );
			if ( $update !== '' ) {
				$outdated_versions[ $version ] = true;
			}
		}

		\uksort(
			$by_version,
			static function ( string $left, string $right ): int {
				return \version_compare( $right, $left );
			},
		);

		$out = '<ul class="smd-version-list">';
		foreach ( $by_version as $version => $domains ) {
			$emoji = isset( $outdated_versions[ $version ] ) ? '🟠' : '✅';
			$out .= '<li>' . $emoji . ' <strong>' . esc_html( $version ) . '</strong><ul>';
			foreach ( $domains as $domain ) {
				$out .= '<li>' . esc_html( $domain ) . '</li>';
			}
			$out .= '</ul></li>';
		}

		return $out . '</ul>';
	}

	/**
	 * Render filter navigation above the table.
	 *
	 * @param string $which Top or bottom position.
	 *
	 * @return void
	 */
	protected function extra_tablenav( $which ): void { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- WP_List_Table override.
		if ( $which !== 'top' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$page_url = admin_url( 'admin.php?page=site_bookkeeper_dashboard_plugins' );

		echo '<div class="alignleft actions">';
		if ( $outdated ) {
			\printf(
				'<a href="%s">%s</a> | <strong>%s</strong>',
				esc_url( $page_url ),
				esc_html__( 'All', 'site-bookkeeper-dashboard' ),
				esc_html__( 'Outdated only', 'site-bookkeeper-dashboard' ),
			);
		} else {
			\printf(
				'<strong>%s</strong> | <a href="%s">%s</a>',
				esc_html__( 'All', 'site-bookkeeper-dashboard' ),
				esc_url( $page_url . '&outdated=1' ),
				esc_html__( 'Outdated only', 'site-bookkeeper-dashboard' ),
			);
		}
		echo '</div>';
	}
}
