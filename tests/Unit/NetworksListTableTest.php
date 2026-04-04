<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\NetworksListTable;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NetworksListTable class.
 */
class NetworksListTableTest extends TestCase {

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
	 * Verify get_columns returns expected column definitions.
	 *
	 * @return void
	 */
	public function test_get_columns_returns_expected_keys(): void {
		$table   = new NetworksListTable();
		$columns = $table->get_columns();

		$this->assertArrayHasKey( 'label', $columns );
		$this->assertArrayHasKey( 'main_site_url', $columns );
		$this->assertArrayHasKey( 'subsite_count', $columns );
		$this->assertArrayHasKey( 'last_seen', $columns );
		$this->assertArrayHasKey( 'status', $columns );
	}

	/**
	 * Verify get_sortable_columns returns sortable config.
	 *
	 * @return void
	 */
	public function test_get_sortable_columns(): void {
		$table    = new NetworksListTable();
		$sortable = $table->get_sortable_columns();

		$this->assertArrayHasKey( 'label', $sortable );
		$this->assertArrayHasKey( 'subsite_count', $sortable );
		$this->assertArrayHasKey( 'last_seen', $sortable );
	}

	/**
	 * Verify column_label renders link to network detail.
	 *
	 * @return void
	 */
	public function test_column_label_renders_link(): void {
		Functions\stubs(
			[
				'esc_html' => static fn( string $text ): string => $text,
				'esc_url' => static fn( string $url ): string => $url,
				'admin_url' => static fn( string $path ): string => '/wp-admin/' . $path,
			],
		);

		$table = new NetworksListTable();
		$item  = [
			'id'            => 'net-uuid-1',
			'label'         => 'My Network',
			'main_site_url' => 'https://network.example.tld',
		];

		$output = $table->column_label( $item );

		$this->assertStringContainsString( 'My Network', $output );
		$this->assertStringContainsString( 'net-uuid-1', $output );
		$this->assertStringContainsString( 'https://network.example.tld', $output );
	}

	/**
	 * Verify column_status renders stale indicator.
	 *
	 * @return void
	 */
	public function test_column_status_renders_stale(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new NetworksListTable();
		$item  = [
			'last_seen' => '2026-03-01T12:00:00+00:00',
		];

		$output = $table->column_status( $item );

		$this->assertStringContainsString( 'smd-badge', $output );
	}

	/**
	 * Verify column_status renders OK for recent check-in.
	 *
	 * @return void
	 */
	public function test_column_status_renders_ok(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new NetworksListTable();
		$item  = [
			'last_seen' => \gmdate( 'Y-m-d\TH:i:sP' ),
		];

		$output = $table->column_status( $item );

		$this->assertStringContainsString( 'OK', $output );
	}

	/**
	 * Verify column_default returns the correct value.
	 *
	 * @return void
	 */
	public function test_column_default_returns_value(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new NetworksListTable();
		$item  = [ 'subsite_count' => '5' ];

		$result = $table->column_default( $item, 'subsite_count' );

		$this->assertSame( '5', $result );
	}

	/**
	 * Verify single_row applies stale class for old last_seen.
	 *
	 * @return void
	 */
	public function test_single_row_applies_stale_class(): void {
		Functions\stubs( [ 'esc_attr' => static fn( string $text ): string => $text ] );

		$table = new NetworksListTable();
		$item  = [
			'last_seen' => '2026-01-01T12:00:00+00:00',
		];

		\ob_start();
		$table->single_row( $item );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'smd-stale', $output );
	}

	/**
	 * Verify single_row has no stale class for recent last_seen.
	 *
	 * @return void
	 */
	public function test_single_row_no_stale_class_for_active(): void {
		Functions\stubs( [ 'esc_attr' => static fn( string $text ): string => $text ] );

		$table = new NetworksListTable();
		$item  = [
			'last_seen' => \gmdate( 'Y-m-d\TH:i:sP' ),
		];

		\ob_start();
		$table->single_row( $item );
		$output = (string) \ob_get_clean();

		$this->assertStringNotContainsString( 'smd-stale', $output );
	}
}
