<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site theme report.
 *
 * Lists all known themes across all monitored sites, showing
 * which sites have each theme, versions, and update status.
 */
class ThemeReport {

	/**
	 * Column definitions for the theme report table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'name' => 'Theme',
			'slug' => 'Slug',
			'sites' => 'Sites',
			'versions' => 'Versions',
			'update_status' => 'Update Status',
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
	 * Render the sites column showing site count.
	 *
	 * @param array<string, mixed> $item Theme data row.
	 *
	 * @return string
	 */
	public function column_sites( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) ) {
			return '0';
		}

		$count = \count( $sites );
		$labels = [];
		foreach ( $sites as $site ) {
			if ( \is_array( $site ) ) {
				$labels[] = esc_html( (string) ( $site['label'] ?? $site['site_url'] ?? '' ) );
			}
		}

		return \sprintf(
			'<span title="%s">%s</span>',
			\implode( ', ', $labels ),
			esc_html( (string) $count ),
		);
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

		$versions = [];
		foreach ( $sites as $site ) {
			if ( \is_array( $site ) && isset( $site['version'] ) ) {
				$version = (string) $site['version'];
				if ( ! isset( $versions[ $version ] ) ) {
					$versions[ $version ] = 0;
				}
				$versions[ $version ]++;
			}
		}

		$parts = [];
		foreach ( $versions as $version => $count ) {
			$parts[] = \sprintf(
				'%s (%d)',
				esc_html( $version ),
				$count,
			);
		}

		return \implode( ', ', $parts );
	}

	/**
	 * Render the update status column.
	 *
	 * Shows whether any site has an outdated version.
	 *
	 * @param array<string, mixed> $item Theme data row.
	 *
	 * @return string
	 */
	public function column_update_status( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) ) {
			return '';
		}

		$outdated = 0;
		foreach ( $sites as $site ) {
			if ( \is_array( $site ) ) {
				$update = (string) ( $site['update_available'] ?? '' );
				if ( $update !== '' ) {
					$outdated++;
				}
			}
		}

		if ( $outdated > 0 ) {
			return \sprintf(
				'<span class="smd-has-updates">%s</span>',
				esc_html(
					\sprintf(
						'%d outdated',
						$outdated,
					),
				),
			);
		}

		return esc_html( 'Up to date' );
	}

	/**
	 * Render a default column value.
	 *
	 * @param array<string, mixed> $item        Theme data row.
	 * @param string               $column_name Column key.
	 *
	 * @return string
	 */
	public function column_default( array $item, string $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Sort items by the requested column.
	 *
	 * @param array<int, array<string, mixed>> $items   Theme data rows.
	 * @param string                           $orderby Column to sort by.
	 * @param string                           $order   Sort direction.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function sort_items( array $items, string $orderby = 'name', string $order = 'asc' ): array {
		\usort(
			$items,
			static function ( array $left, array $right ) use ( $orderby, $order ): int {
				$val_a = (string) ( $left[ $orderby ] ?? '' );
				$val_b = (string) ( $right[ $orderby ] ?? '' );
				$result = \strnatcasecmp( $val_a, $val_b );

				return $order === 'desc' ? -$result : $result;
			},
		);

		return $items;
	}
}
