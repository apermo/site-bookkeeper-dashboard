<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\PluginReport;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PluginReport class.
 */
class PluginReportTest extends TestCase {

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
		$report = new PluginReport();
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
		$report = new PluginReport();
		$sortable = $report->get_sortable_columns();

		$this->assertArrayHasKey( 'name', $sortable );
		$this->assertArrayHasKey( 'slug', $sortable );
	}

	/**
	 * Verify column_sites renders site count.
	 *
	 * @return void
	 */
	public function test_column_sites_renders_names(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new PluginReport();
		$item = [
			'sites' => [
				[ 'site_url' => 'https://one.example.tld' ],
				[
					'site_url' => 'https://two.example.tld',
					'label' => 'Site Two',
				],
			],
		];

		$output = $report->column_sites( $item );
		$this->assertStringContainsString( 'one.example.tld', $output );
		$this->assertStringContainsString( 'Site Two', $output );
	}

	/**
	 * Verify column_versions renders version list.
	 *
	 * @return void
	 */
	public function test_column_versions_renders_versions(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new PluginReport();
		$item = [
			'sites' => [
				[
					'site_url' => 'https://one.example.tld',
					'version' => '1.0.0',
				],
				[
					'site_url' => 'https://two.example.tld',
					'version' => '1.1.0',
				],
			],
		];

		$output = $report->column_versions( $item );
		$this->assertStringContainsString( '1.0.0', $output );
		$this->assertStringContainsString( '1.1.0', $output );
	}

	/**
	 * Verify column_update_status shows when outdated.
	 *
	 * @return void
	 */
	public function test_column_update_status_outdated(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$report = new PluginReport();
		$item = [
			'sites' => [
				[
					'site_url' => 'https://one.example.tld',
					'version' => '1.0.0',
					'update_available' => '1.1.0',
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

		$report = new PluginReport();
		$item = [
			'sites' => [
				[
					'site_url' => 'https://one.example.tld',
					'version' => '1.1.0',
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

		$report = new PluginReport();
		$result = $report->column_default( [ 'name' => 'Akismet' ], 'name' );

		$this->assertSame( 'Akismet', $result );
	}
}
