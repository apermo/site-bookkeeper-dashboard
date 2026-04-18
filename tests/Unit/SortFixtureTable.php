<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\ApiListTable;

/**
 * Test-only subclass of ApiListTable that serves fixture rows and a
 * configurable column set for sort_fetched_items() coverage.
 */
final class SortFixtureTable extends ApiListTable {

	/**
	 * Fixture rows returned by fetch_data.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $fetched;

	/**
	 * Column id to label map.
	 *
	 * @var array<string, string>
	 */
	public array $columns;

	/**
	 * Column id to [orderby, desc_first] map.
	 *
	 * @var array<string, array{0: string, 1: bool}>
	 */
	public array $sortable;

	/**
	 * Capture fixtures and column config from the test.
	 *
	 * @param array<int, array<string, mixed>>         $items    Fixture rows.
	 * @param array<string, string>                    $columns  Columns map.
	 * @param array<string, array{0: string, 1: bool}> $sortable Sortable map.
	 */
	public function __construct( array $items, array $columns, array $sortable ) {
		$this->fetched  = $items;
		$this->columns  = $columns;
		$this->sortable = $sortable;
	}

	/**
	 * Return the captured fixture rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function fetch_data(): array {
		return $this->fetched;
	}

	/**
	 * Return the configured columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Return the configured sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return $this->sortable;
	}
}
