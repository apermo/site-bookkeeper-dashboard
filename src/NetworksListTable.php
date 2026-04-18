<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Networks overview list table.
 *
 * Renders the admin page table showing all monitored
 * networks with sortable columns and stale highlighting.
 */
class NetworksListTable extends ApiListTable {

	/**
	 * Stale threshold in seconds (48 hours).
	 *
	 * @var int
	 */
	private const STALE_THRESHOLD = 172800;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'network',
				'plural'   => 'networks',
				'ajax'     => false,
			],
		);
	}

	/**
	 * Fetch network data from the hub API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		$client = ApiClient::from_settings();
		$data = $client->get_networks();

		if ( isset( $data['error'] ) ) {
			$this->api_error = $data;
			return [];
		}

		$networks = $data['networks'] ?? [];

		foreach ( $networks as &$network ) {
			$network['state']      = $this->is_stale( $network ) ? self::STATE_STALE : self::STATE_FRESH;
			$network['state_rank'] = self::state_rank( $network['state'] );
		}
		unset( $network );

		return $this->apply_filters( $networks );
	}

	/**
	 * Apply active filters to the items array.
	 *
	 * @param array<int, array<string, mixed>> $items Network data rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_filters( array $items ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( $state !== '' ) {
			$items = \array_filter(
				$items,
				static fn( array $item ): bool => ( $item['state'] ?? '' ) === $state,
			);
		}

		return \array_values( $items );
	}

	/**
	 * Return form page slug to wrap the table in a filter form.
	 *
	 * @return string
	 */
	protected function get_form_page(): string {
		return 'site_bookkeeper_dashboard_networks';
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter param.
		$current = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		$options = [
			self::STATE_FRESH => __( 'Fresh', 'site-bookkeeper-dashboard' ),
			self::STATE_STALE => __( 'Stale', 'site-bookkeeper-dashboard' ),
		];

		echo '<div class="alignleft actions">';
		echo '<select name="state">';
		echo '<option value="">' . esc_html__( 'All states', 'site-bookkeeper-dashboard' ) . '</option>';
		foreach ( $options as $value => $label ) {
			\printf(
				'<option value="%s" %s>%s %s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( self::state_emoji( $value ) ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'site-bookkeeper-dashboard' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Column definitions for the list table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'label'         => 'Network',
			'main_site_url' => 'Main Site URL',
			'subsite_count' => 'Subsites',
			'last_seen'     => 'Last Seen',
			'status'        => 'Status',
			'state'         => 'State',
		];
	}

	/**
	 * Sortable column definitions.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'label'         => [ 'label', false ],
			'subsite_count' => [ 'subsite_count', false ],
			'last_seen'     => [ 'last_seen', true ],
			'state'         => [ 'state_rank', true ],
		];
	}

	/**
	 * Render the state column with the appropriate emoji badge.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return string
	 */
	public function column_state( array $item ): string {
		$state = (string) ( $item['state'] ?? self::STATE_FRESH );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped inside helper.
		return self::state_badge_html( $state );
	}

	/**
	 * Render the label column with a detail link.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return string
	 */
	public function column_label( array $item ): string {
		$detail_url = admin_url(
			\sprintf(
				'admin.php?page=site_bookkeeper_dashboard_network_detail&network_id=%s',
				$item['id'] ?? '',
			),
		);

		return \sprintf(
			'<a href="%s"><strong>%s</strong></a><br><small>%s</small>',
			esc_url( $detail_url ),
			esc_html( (string) ( $item['label'] ?? '' ) ),
			esc_html( (string) ( $item['main_site_url'] ?? '' ) ),
		);
	}

	/**
	 * Render the status column with stale indicator.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return string
	 */
	public function column_status( array $item ): string {
		if ( $this->is_stale( $item ) ) {
			return \sprintf(
				'<span class="smd-badge smd-badge-warning">%s</span>',
				esc_html( 'Stale' ),
			);
		}

		return esc_html( 'OK' );
	}

	/**
	 * Return CSS class for stale networks.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return string
	 */
	protected function get_row_class( array $item ): string {
		if ( $this->is_stale( $item ) ) {
			return 'smd-stale';
		}

		return '';
	}

	/**
	 * Check whether a network is considered stale.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return bool
	 */
	private function is_stale( array $item ): bool {
		$last_seen = $item['last_seen'] ?? '';

		if ( $last_seen === '' ) {
			return true;
		}

		$timestamp = \strtotime( (string) $last_seen );

		if ( $timestamp === false ) {
			return true;
		}

		return ( \time() - $timestamp ) > self::STALE_THRESHOLD;
	}
}
