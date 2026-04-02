<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\SitesListTable;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SitesListTable class.
 */
class SitesListTableTest extends TestCase {

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
		$table   = new SitesListTable();
		$columns = $table->get_columns();

		$this->assertArrayHasKey( 'label', $columns );
		$this->assertArrayHasKey( 'wp_version', $columns );
		$this->assertArrayHasKey( 'php_version', $columns );
		$this->assertArrayHasKey( 'pending_updates', $columns );
		$this->assertArrayHasKey( 'last_seen', $columns );
		$this->assertArrayHasKey( 'last_updated', $columns );
	}

	/**
	 * Verify get_sortable_columns returns sortable config.
	 *
	 * @return void
	 */
	public function test_get_sortable_columns(): void {
		$table    = new SitesListTable();
		$sortable = $table->get_sortable_columns();

		$this->assertArrayHasKey( 'label', $sortable );
		$this->assertArrayHasKey( 'wp_version', $sortable );
		$this->assertArrayHasKey( 'php_version', $sortable );
		$this->assertArrayHasKey( 'last_seen', $sortable );
	}

	/**
	 * Verify column_default renders label with link.
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

		$table = new SitesListTable();
		$item  = [
			'id'    => 'uuid-1',
			'label' => 'Test Site',
		];

		$output = $table->column_label( $item );

		$this->assertStringContainsString( 'Test Site', $output );
		$this->assertStringContainsString( 'uuid-1', $output );
	}

	/**
	 * Verify column_default returns the correct value.
	 *
	 * @return void
	 */
	public function test_column_default_returns_value(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new SitesListTable();
		$item  = [
			'php_version' => '8.3.4',
		];

		$result = $table->column_default( $item, 'php_version' );

		$this->assertSame( '8.3.4', $result );
	}

	/**
	 * Verify stale sites get appropriate row class.
	 *
	 * @return void
	 */
	public function test_get_row_class_for_stale_site(): void {
		$table = new SitesListTable();
		$item  = [ 'stale' => true ];

		$this->assertSame( 'smd-stale', $table->get_row_class( $item ) );
	}

	/**
	 * Verify non-stale sites get empty row class.
	 *
	 * @return void
	 */
	public function test_get_row_class_for_active_site(): void {
		$table = new SitesListTable();
		$item  = [ 'stale' => false ];

		$this->assertSame( '', $table->get_row_class( $item ) );
	}

	/**
	 * Verify pending_updates column highlights when > 0.
	 *
	 * @return void
	 */
	public function test_column_pending_updates_with_updates(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new SitesListTable();
		$item = [
			'pending_plugin_updates' => 3,
			'pending_theme_updates' => 1,
		];
		$output = $table->column_pending_updates( $item );

		$this->assertStringContainsString( 'smd-has-updates', $output );
		$this->assertStringContainsString( '4', $output );
	}

	/**
	 * Verify pending_updates column with zero updates.
	 *
	 * @return void
	 */
	public function test_column_pending_updates_with_none(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new SitesListTable();
		$item = [
			'pending_plugin_updates' => 0,
			'pending_theme_updates' => 0,
		];
		$output = $table->column_pending_updates( $item );

		$this->assertStringNotContainsString( 'smd-has-updates', $output );
		$this->assertStringContainsString( '0', $output );
	}

	/**
	 * Verify wp_version column shows update available.
	 *
	 * @return void
	 */
	public function test_column_wp_version_with_update(): void {
		Functions\stubs( [ 'esc_html' => static fn( string $text ): string => $text ] );

		$table = new SitesListTable();
		$item = [
			'wp_version' => '6.8',
			'wp_update_available' => '6.8.1',
		];
		$output = $table->column_wp_version( $item );

		$this->assertStringContainsString( '6.8', $output );
		$this->assertStringContainsString( '6.8.1', $output );
	}
}
