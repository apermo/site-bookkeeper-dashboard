<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site users list table.
 *
 * Lists all users across monitored sites, grouped by email.
 * Supports search via WP_List_Table's built-in search box.
 */
class UsersListTable extends ApiListTable {

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

		return $data['users'] ?? [];
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
	 * Return form page slug for search form.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return 'site_bookkeeper_dashboard_users';
	}
}
