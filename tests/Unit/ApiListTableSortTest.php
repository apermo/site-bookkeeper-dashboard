<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ApiListTable::sort_fetched_items() via the fixture subclass.
 *
 * WP_List_Table emits the mapped [0] field (e.g. `state_rank`) as the URL
 * `orderby` value, not the column id. The shared sort helper must resolve
 * against the mapped values, otherwise sorting by a column whose id and
 * field differ silently falls back to the default column.
 */
class ApiListTableSortTest extends TestCase {

	/**
	 * Set up Brain Monkey and reset globals.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs(
			[
				'sanitize_text_field' => static fn( string $text ): string => $text,
				'wp_unslash'          => static fn( mixed $value ): mixed => $value,
			],
		);
		$_GET = [];
	}

	/**
	 * Tear down Brain Monkey and reset globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_GET = [];
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Sorting by the `state` column via `orderby=state_rank` must order
	 * rows by the numeric rank, not fall back to the default column.
	 *
	 * @return void
	 */
	public function test_orderby_state_rank_sorts_by_rank_descending(): void {
		$_GET['orderby'] = 'state_rank';
		$_GET['order']   = 'desc';

		$table = new SortFixtureTable(
			[
				[
					'label'      => 'alpha',
					'state_rank' => 0,
				],
				[
					'label'      => 'bravo',
					'state_rank' => 3,
				],
				[
					'label'      => 'charlie',
					'state_rank' => 1,
				],
				[
					'label'      => 'delta',
					'state_rank' => 2,
				],
			],
			[
				'label' => 'Label',
				'state' => 'State',
			],
			[
				'label' => [ 'label', false ],
				'state' => [ 'state_rank', true ],
			],
		);

		$table->prepare_items();

		$labels = \array_column( $table->items, 'label' );

		$this->assertSame( [ 'bravo', 'delta', 'charlie', 'alpha' ], $labels );
	}

	/**
	 * An unknown `orderby` value must fall back to sorting by the first
	 * sortable column's mapped field, not silently break.
	 *
	 * @return void
	 */
	public function test_unknown_orderby_falls_back_to_first_sortable(): void {
		$_GET['orderby'] = 'not_a_real_column';
		$_GET['order']   = 'asc';

		$table = new SortFixtureTable(
			[
				[ 'label' => 'charlie' ],
				[ 'label' => 'alpha' ],
				[ 'label' => 'bravo' ],
			],
			[ 'label' => 'Label' ],
			[ 'label' => [ 'label', false ] ],
		);

		$table->prepare_items();

		$labels = \array_column( $table->items, 'label' );

		$this->assertSame( [ 'alpha', 'bravo', 'charlie' ], $labels );
	}
}
