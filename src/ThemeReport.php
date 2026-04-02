<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site theme report.
 *
 * Lists all known themes across all monitored sites, showing
 * which sites have each theme, versions, and update status.
 */
class ThemeReport extends ApiListTable {

	/**
	 * All items before filtering (for building site dropdown).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $all_items = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'theme',
				'plural'   => 'themes',
				'ajax'     => false,
			],
		);
	}

	/**
	 * Check if any site in the item matches a URL.
	 *
	 * @param array<string, mixed> $item        Theme data row.
	 * @param string               $site_filter Site URL to match.
	 *
	 * @return bool
	 */
	private static function item_has_site( array $item, string $site_filter ): bool {
		foreach ( $item['sites'] ?? [] as $site ) {
			if ( ( $site['site_url'] ?? '' ) === $site_filter ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any site has the theme inactive.
	 *
	 * @param mixed $sites Sites array.
	 *
	 * @return bool
	 */
	private static function has_inactive_site( mixed $sites ): bool {
		if ( ! \is_array( $sites ) ) {
			return false;
		}

		foreach ( $sites as $site ) {
			if ( \is_array( $site ) && ( $site['active'] ?? 0 ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any site entry has vulnerabilities.
	 *
	 * @param mixed $sites Sites array.
	 *
	 * @return bool
	 */
	private static function has_vulnerability( mixed $sites ): bool {
		if ( ! \is_array( $sites ) ) {
			return false;
		}

		foreach ( $sites as $site ) {
			if ( \is_array( $site ) && ( $site['security_update'] ?? false ) === true ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetch theme data from the hub API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		$client = ApiClient::from_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$params = $outdated ? [ 'outdated' => 'true' ] : [];

		$data = $client->get_themes( $params );

		if ( isset( $data['error'] ) ) {
			$this->api_error = $data;
			return [];
		}

		$themes = $data['themes'] ?? [];

		foreach ( $themes as &$theme ) {
			$sites = $theme['sites'] ?? [];
			$theme['sites_count'] = \is_array( $sites ) ? \count( $sites ) : 0;
			$theme['has_inactive'] = self::has_inactive_site( $sites );
			$theme['has_vulnerability'] = self::has_vulnerability( $sites );
		}
		unset( $theme );

		$this->all_items = $themes;

		return $this->apply_report_filters( $themes );
	}

	/**
	 * Column definitions for the theme report table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'name'     => 'Theme',
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
			'name'        => [ 'name', false ],
			'slug'        => [ 'slug', false ],
			'sites_count' => [ 'sites_count', false ],
		];
	}

	/**
	 * Render the name column with a link to WordPress.org if available.
	 *
	 * @param array<string, mixed> $item Theme data row.
	 *
	 * @return string
	 */
	public function column_name( array $item ): string {
		$name = esc_html( (string) ( $item['name'] ?? '' ) );
		$slug = (string) ( $item['slug'] ?? '' );
		$link = SlugResolver::theme_url( $slug );

		if ( $link !== null ) {
			return \sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $link ), $name );
		}

		return $name;
	}

	/**
	 * Render the sites column showing site count.
	 *
	 * @param array<string, mixed> $item Theme data row.
	 *
	 * @return string
	 */
	public function column_sites( array $item ): string {
		return esc_html( (string) ( $item['sites_count'] ?? 0 ) );
	}

	/**
	 * Render the versions column showing distinct versions.
	 *
	 * @param array<string, mixed> $item Theme data row.
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
			$status = ( $site['active'] ?? 0 ) === 0 ? 'inactive' : '';

			$by_version[ $version ][] = [ $domain, $status ];

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
		foreach ( $by_version as $version => $entries ) {
			$emoji = isset( $outdated_versions[ $version ] ) ? '🟠' : '✅';
			$out .= '<li>' . $emoji . ' <strong>' . esc_html( $version ) . '</strong><ul>';
			foreach ( $entries as $entry ) {
				$out .= '<li>' . esc_html( $entry[0] );
				if ( $entry[1] !== '' ) {
					$out .= ' <small class="smd-activation-label">(' . esc_html( $entry[1] ) . ')</small>';
				}
				$out .= '</li>';
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === '1';
		$site_filter = isset( $_GET['site'] ) ? sanitize_text_field( wp_unslash( $_GET['site'] ) ) : '';
		$inactive_filter = isset( $_GET['inactive'] ) && $_GET['inactive'] === '1';
		$vuln_filter = isset( $_GET['vulnerable'] ) && $_GET['vulnerable'] === '1';
		// phpcs:enable

		$page_url = admin_url( 'admin.php?page=site_bookkeeper_dashboard_themes' );

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

		echo ' &nbsp; ';

		$this->render_site_filter( $site_filter );

		\printf(
			' <label><input type="checkbox" name="inactive" value="1" %s /> %s</label>',
			checked( $inactive_filter, true, false ),
			esc_html__( 'Inactive somewhere', 'site-bookkeeper-dashboard' ),
		);
		\printf(
			' <label><input type="checkbox" name="vulnerable" value="1" %s /> %s</label> ',
			checked( $vuln_filter, true, false ),
			esc_html__( 'Has vulnerabilities', 'site-bookkeeper-dashboard' ),
		);

		submit_button( __( 'Filter', 'site-bookkeeper-dashboard' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Return form page slug for filter wrapping.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return 'site_bookkeeper_dashboard_themes';
	}

	/**
	 * Preserve the outdated filter in the form.
	 *
	 * @return void
	 */
	protected function render_hidden_filters(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		if ( isset( $_GET['outdated'] ) && $_GET['outdated'] === '1' ) {
			echo '<input type="hidden" name="outdated" value="1" />';
		}
	}

	/**
	 * Render the site filter dropdown.
	 *
	 * @param string $current Currently selected site URL.
	 *
	 * @return void
	 */
	private function render_site_filter( string $current ): void {
		$site_urls = [];
		foreach ( $this->all_items as $theme ) {
			foreach ( $theme['sites'] ?? [] as $site ) {
				$url = $site['site_url'] ?? '';
				if ( $url !== '' ) {
					$label = $site['label'] ?? $url;
					$site_urls[ $url ] = $label;
				}
			}
		}
		\asort( $site_urls );

		echo '<select name="site">';
		echo '<option value="">' . esc_html__( 'All sites', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $site_urls as $url => $label ) {
			\printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $url ),
				selected( $current, $url, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
	}

	/**
	 * Apply report-specific filters.
	 *
	 * @param array<int, array<string, mixed>> $items Theme data rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_report_filters( array $items ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$site_filter = isset( $_GET['site'] ) ? sanitize_text_field( wp_unslash( $_GET['site'] ) ) : '';
		$inactive_filter = isset( $_GET['inactive'] ) && $_GET['inactive'] === '1';
		$vuln_filter = isset( $_GET['vulnerable'] ) && $_GET['vulnerable'] === '1';
		// phpcs:enable

		if ( $site_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => self::item_has_site( $item, $site_filter ),
			);
		}

		if ( $inactive_filter ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => $item['has_inactive'] === true,
			);
		}

		if ( $vuln_filter ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => $item['has_vulnerability'] === true,
			);
		}

		return \array_values( $items );
	}
}
