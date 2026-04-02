<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Cross-site plugin report.
 *
 * Lists all known plugins across all monitored sites, showing
 * which sites have each plugin, versions, and update status.
 */
class PluginReport {

	/**
	 * Column definitions for the plugin report table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'name' => 'Plugin',
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
	 * @param array<string, mixed> $item Plugin data row.
	 *
	 * @return string
	 */
	public function column_sites( array $item ): string {
		$sites = $item['sites'] ?? [];
		if ( ! \is_array( $sites ) ) {
			return '—';
		}

		$labels = [];
		foreach ( $sites as $site ) {
			if ( \is_array( $site ) ) {
				$labels[] = esc_html( (string) ( $site['label'] ?? \preg_replace( '#^https?://#', '', $site['site_url'] ?? '' ) ) );
			}
		}

		return \implode( ', ', $labels );
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
		foreach ( $sites as $site ) {
			if ( ! \is_array( $site ) || ! isset( $site['version'] ) ) {
				continue;
			}

			$version = (string) $site['version'];
			$label   = (string) ( $site['label'] ?? \preg_replace( '#^https?://#', '', $site['site_url'] ?? '' ) );

			$by_version[ $version ][] = $label;
		}

		if ( \count( $by_version ) === 1 ) {
			return esc_html( \array_key_first( $by_version ) );
		}

		$out = '<ul style="margin:0">';
		foreach ( $by_version as $version => $labels ) {
			$out .= '<li>' . esc_html( $version ) . '<ul>';
			foreach ( $labels as $label ) {
				$out .= '<li>' . esc_html( $label ) . '</li>';
			}
			$out .= '</ul></li>';
		}

		return $out . '</ul>';
	}

	/**
	 * Render the update status column.
	 *
	 * Shows whether any site has an outdated version.
	 *
	 * @param array<string, mixed> $item Plugin data row.
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
	 * @param array<string, mixed> $item        Plugin data row.
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
	 * @param array<int, array<string, mixed>> $items   Plugin data rows.
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
