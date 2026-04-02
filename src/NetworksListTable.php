<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Networks overview list table.
 *
 * Renders the admin page table showing all monitored
 * networks with sortable columns and stale highlighting.
 */
class NetworksListTable {

	/**
	 * Stale threshold in seconds (48 hours).
	 *
	 * @var int
	 */
	private const STALE_THRESHOLD = 172800;

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
		];
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
	 * Render a default column value.
	 *
	 * @param array<string, mixed> $item        Network data row.
	 * @param string               $column_name Column key.
	 *
	 * @return string
	 */
	public function column_default( array $item, string $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Get the CSS class for a table row.
	 *
	 * Returns 'smd-stale' for stale networks to allow visual
	 * highlighting via admin CSS.
	 *
	 * @param array<string, mixed> $item Network data row.
	 *
	 * @return string
	 */
	public function get_row_class( array $item ): string {
		if ( $this->is_stale( $item ) ) {
			return 'smd-stale';
		}

		return '';
	}

	/**
	 * Sort items by the requested column.
	 *
	 * @param array<int, array<string, mixed>> $items   Network data rows.
	 * @param string                           $orderby Column to sort by.
	 * @param string                           $order   Sort direction (asc|desc).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function sort_items( array $items, string $orderby = 'label', string $order = 'asc' ): array {
		\usort(
			$items,
			static function ( array $left, array $right ) use ( $orderby, $order ): int {
				$val_a  = (string) ( $left[ $orderby ] ?? '' );
				$val_b  = (string) ( $right[ $orderby ] ?? '' );
				$result = \strnatcasecmp( $val_a, $val_b );

				return $order === 'desc' ? -$result : $result;
			},
		);

		return $items;
	}

	/**
	 * Check whether a network is considered stale.
	 *
	 * A network is stale when its last_seen timestamp is
	 * older than 48 hours.
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
