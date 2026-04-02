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
			$domain  = (string) \preg_replace( '#^https?://#', '', $site['site_url'] ?? '' );

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
