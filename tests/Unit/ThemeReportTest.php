<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\ThemeReport;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ThemeReport class.
 */
class ThemeReportTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Verify get_columns returns expected column keys.
	 *
	 * @return void
	 */
	public function test_get_columns_returns_expected_keys(): void {
		$report = new ThemeReport();
		$columns = $report->get_columns();

		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'slug', $columns );
		$this->assertArrayHasKey( 'sites', $columns );
		$this->assertArrayHasKey( 'versions', $columns );
	}

	/**
	 * Verify get_sortable_columns returns sortable config.
	 *
	 * @return void
	 */
	public function test_get_sortable_columns(): void {
		$report = new ThemeReport();
		$sortable = $report->get_sortable_columns();

		$this->assertArrayHasKey( 'name', $sortable );
		$this->assertArrayHasKey( 'slug', $sortable );
	}

	/**
	 * Verify column_sites renders site count.
	 *
	 * @return void
	 */
	public function test_column_sites_renders_count(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new ThemeReport();
		$item = [
			'sites' => [
				[ 'site_url' => 'https://one.example.tld' ],
				[ 'site_url' => 'https://two.example.tld' ],
			],
		];

		$output = $report->column_sites( $item );
		$this->assertSame( '0', $output );
	}

	/**
	 * Verify column_sites renders sites_count when present.
	 *
	 * @return void
	 */
	public function test_column_sites_renders_sites_count(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new ThemeReport();
		$item = [ 'sites_count' => 2 ];

		$output = $report->column_sites( $item );
		$this->assertSame( '2', $output );
	}

	/**
	 * Verify column_default returns value from item.
	 *
	 * @return void
	 */
	public function test_column_default(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new ThemeReport();
		$result = $report->column_default( [ 'name' => 'Twenty Twenty-Five' ], 'name' );

		$this->assertSame( 'Twenty Twenty-Five', $result );
	}
}
