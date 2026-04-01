<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard\Tests\Unit;

use Apermo\SiteMonitorDashboard\ThemeReport;
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
		$this->assertArrayHasKey( 'update_status', $columns );
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
		$this->assertStringContainsString( '2', $output );
	}

	/**
	 * Verify column_update_status shows when outdated.
	 *
	 * @return void
	 */
	public function test_column_update_status_outdated(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new ThemeReport();
		$item = [
			'sites' => [
				[
					'site_url' => 'https://one.example.tld',
					'version' => '3.0',
					'update_available' => '3.1',
				],
			],
		];

		$output = $report->column_update_status( $item );
		$this->assertStringContainsString( 'smd-has-updates', $output );
	}

	/**
	 * Verify column_update_status shows up to date.
	 *
	 * @return void
	 */
	public function test_column_update_status_current(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new ThemeReport();
		$item = [
			'sites' => [
				[
					'site_url' => 'https://one.example.tld',
					'version' => '3.1',
					'update_available' => '',
				],
			],
		];

		$output = $report->column_update_status( $item );
		$this->assertStringNotContainsString( 'smd-has-updates', $output );
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

	/**
	 * Verify sort_items sorts by name ascending.
	 *
	 * @return void
	 */
	public function test_sort_items(): void {
		$report = new ThemeReport();
		$items = [
			[ 'name' => 'Zeta' ],
			[ 'name' => 'Alpha' ],
		];

		$sorted = $report->sort_items( $items );

		$this->assertSame( 'Alpha', $sorted[0]['name'] );
		$this->assertSame( 'Zeta', $sorted[1]['name'] );
	}
}
