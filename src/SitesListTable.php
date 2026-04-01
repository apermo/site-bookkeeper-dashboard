<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard;

/**
 * Sites overview list table.
 *
 * Renders the main admin page table showing all monitored
 * sites with sortable columns and stale/update highlighting.
 */
class SitesListTable {

	/**
	 * Column definitions for the list table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'label'           => 'Site',
			'wp_version'      => 'WordPress',
			'php_version'     => 'PHP',
			'pending_updates' => 'Pending Updates',
			'last_seen'       => 'Last Seen',
			'last_updated'    => 'Last Updated',
		];
	}

	/**
	 * Sortable column definitions.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'label'       => [ 'label', false ],
			'wp_version'  => [ 'wp_version', false ],
			'php_version' => [ 'php_version', false ],
			'last_seen'   => [ 'last_seen', true ],
		];
	}

	/**
	 * Render the label column with a detail link.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_label( array $item ): string {
		$detail_url = admin_url(
			\sprintf(
				'admin.php?page=site_monitor_dashboard_detail&site_id=%s',
				$item['id'] ?? '',
			),
		);

		return \sprintf(
			'<a href="%s"><strong>%s</strong></a><br><small>%s</small>',
			esc_url( $detail_url ),
			esc_html( (string) ( $item['label'] ?? '' ) ),
			esc_html( (string) ( $item['site_url'] ?? '' ) ),
		);
	}

	/**
	 * Render the WordPress version column.
	 *
	 * Shows the current version and highlights when an
	 * update is available.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_wp_version( array $item ): string {
		$version = esc_html( (string) ( $item['wp_version'] ?? '' ) );
		$update  = $item['wp_update_available'] ?? '';

		if ( $update !== '' ) {
			return \sprintf(
				'%s <span class="smd-update-available">(%s)</span>',
				$version,
				esc_html( (string) $update ),
			);
		}

		return $version;
	}

	/**
	 * Render the pending updates column.
	 *
	 * Highlights the count when updates are available.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function column_pending_updates( array $item ): string {
		$plugin_updates = (int) ( $item['pending_plugin_updates'] ?? 0 );
		$theme_updates  = (int) ( $item['pending_theme_updates'] ?? 0 );
		$total          = $plugin_updates + $theme_updates;

		if ( $total > 0 ) {
			return \sprintf(
				'<span class="smd-has-updates">%s</span>',
				esc_html( (string) $total ),
			);
		}

		return esc_html( (string) $total );
	}

	/**
	 * Render a default column value.
	 *
	 * @param array<string, mixed> $item        Site data row.
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
	 * Returns 'smd-stale' for stale sites to allow visual
	 * highlighting via admin CSS.
	 *
	 * @param array<string, mixed> $item Site data row.
	 *
	 * @return string
	 */
	public function get_row_class( array $item ): string {
		if ( isset( $item['stale'] ) && $item['stale'] === true ) {
			return 'smd-stale';
		}

		return '';
	}

	/**
	 * Sort items by the requested column.
	 *
	 * @param array<int, array<string, mixed>> $items   Site data rows.
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
}
