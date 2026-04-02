<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site users list table.
 *
 * Lists all users across monitored sites, grouped by email.
 * Supports search, and filtering by site and role.
 */
class UsersListTable extends ApiListTable {

	/**
	 * All items before filtering (for building dropdowns).
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
				'singular' => 'user',
				'plural'   => 'users',
				'ajax'     => false,
			],
		);
	}

	/**
	 * Check if a user has an account on a specific site.
	 *
	 * @param array<string, mixed> $item        User data row.
	 * @param string               $site_filter Site URL to match.
	 *
	 * @return bool
	 */
	private static function user_has_site( array $item, string $site_filter ): bool {
		foreach ( $item['sites'] ?? [] as $site ) {
			if ( ( $site['site_url'] ?? '' ) === $site_filter ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a user has a specific role on any site.
	 *
	 * @param array<string, mixed> $item        User data row.
	 * @param string               $role_filter Role to match.
	 *
	 * @return bool
	 */
	private static function user_has_role( array $item, string $role_filter ): bool {
		foreach ( $item['sites'] ?? [] as $site ) {
			if ( ( $site['role'] ?? '' ) === $role_filter ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetch user data from the hub API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		$client = ApiClient::from_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search param.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$params = $search !== '' ? [ 'search' => $search ] : [];

		$data = $client->get_users( $params );

		if ( isset( $data['error'] ) ) {
			$this->api_error = $data;
			return [];
		}

		$users = $data['users'] ?? [];
		$this->all_items = $users;

		return $this->apply_user_filters( $users );
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'user_login'   => 'Login',
			'display_name' => 'Display Name',
			'email'        => 'Email',
			'sites'        => 'Sites',
		];
	}

	/**
	 * Sortable column definitions.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'user_login'   => [ 'user_login', false ],
			'display_name' => [ 'display_name', false ],
			'email'        => [ 'email', false ],
		];
	}

	/**
	 * Render the sites column with linked site list and roles.
	 *
	 * @param array<string, mixed> $item User data row.
	 *
	 * @return string
	 */
	public function column_sites( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) || $sites === [] ) {
			return '&mdash;';
		}

		$out = '<ul style="margin:0">';
		foreach ( $sites as $site ) {
			$label = $this->format_site_label(
				(string) ( $site['label'] ?? '' ),
				(string) ( $site['site_url'] ?? '' ),
			);
			$role = (string) ( $site['role'] ?? '' );
			$site_id = (string) ( $site['site_id'] ?? '' );

			$detail_url = admin_url(
				'admin.php?page=site_bookkeeper_dashboard_detail&site_id=' . $site_id,
			);

			$out .= '<li><a href="' . esc_url( $detail_url ) . '">' . esc_html( $label ) . '</a>';
			if ( $role !== '' ) {
				$out .= ' <small>(' . esc_html( $role ) . ')</small>';
			}
			$out .= '</li>';
		}

		return $out . '</ul>';
	}

	/**
	 * Render filter dropdowns above the table.
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
		$current_site = isset( $_GET['site'] ) ? sanitize_text_field( wp_unslash( $_GET['site'] ) ) : '';
		$current_role = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
		// phpcs:enable

		echo '<div class="alignleft actions">';
		$this->render_site_filter( $current_site );
		$this->render_role_filter( $current_role );
		submit_button( __( 'Filter', 'site-bookkeeper-dashboard' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Return form page slug for search form.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return 'site_bookkeeper_dashboard_users';
	}

	/**
	 * Apply site and role filters.
	 *
	 * @param array<int, array<string, mixed>> $items User data rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_user_filters( array $items ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$site_filter = isset( $_GET['site'] ) ? sanitize_text_field( wp_unslash( $_GET['site'] ) ) : '';
		$role_filter = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
		// phpcs:enable

		if ( $site_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => self::user_has_site( $item, $site_filter ),
			);
		}

		if ( $role_filter !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => self::user_has_role( $item, $role_filter ),
			);
		}

		return \array_values( $items );
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
		foreach ( $this->all_items as $user ) {
			foreach ( $user['sites'] ?? [] as $site ) {
				$url = $site['site_url'] ?? '';
				if ( $url !== '' ) {
					$site_urls[ $url ] = $this->format_site_label( (string) ( $site['label'] ?? '' ), $url );
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
	 * Render the role filter dropdown.
	 *
	 * @param string $current Currently selected role.
	 *
	 * @return void
	 */
	private function render_role_filter( string $current ): void {
		$roles = [];
		foreach ( $this->all_items as $user ) {
			foreach ( $user['sites'] ?? [] as $site ) {
				$role = $site['role'] ?? '';
				if ( $role !== '' ) {
					$roles[ $role ] = true;
				}
			}
		}
		\ksort( $roles );

		echo '<select name="role">';
		echo '<option value="">' . esc_html__( 'All roles', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( \array_keys( $roles ) as $role ) {
			\printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $role ),
				selected( $current, $role, false ),
				esc_html( \ucfirst( $role ) ),
			);
		}
		echo '</select>';
	}
}
