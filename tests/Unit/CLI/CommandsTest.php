<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard\Tests\Unit\CLI;

use Apermo\SiteMonitorDashboard\ApiClient;
use Apermo\SiteMonitorDashboard\CLI\Commands;
use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CLI Commands class.
 */
class CommandsTest extends TestCase {

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
	 * Create a Commands instance with a mocked ApiClient.
	 *
	 * @param ApiClient $client Mocked API client.
	 *
	 * @return Commands
	 */
	private function create_commands( ApiClient $client ): Commands {
		return new Commands( $client );
	}

	/**
	 * Create a mock ApiClient.
	 *
	 * @return ApiClient&Mockery\MockInterface
	 */
	private function create_mock_client(): ApiClient {
		return Mockery::mock( ApiClient::class );
	}

	/**
	 * Verify test command reports success on valid response.
	 *
	 * @return void
	 */
	public function test_test_command_reports_success(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_sites' )
			->once()
			->andReturn(
				[
					'sites' => [
						[
							'id' => 'uuid-1',
							'site_url' => 'https://site.example.tld',
						],
						[
							'id' => 'uuid-2',
							'site_url' => 'https://other.example.tld',
						],
					],
				],
			);

		$commands = $this->create_commands( $client );

		$this->expect_cli_success( 'Connection successful. Found 2 sites.' );

		$commands->test( [], [] );
	}

	/**
	 * Verify test command reports error on API failure.
	 *
	 * @return void
	 */
	public function test_test_command_reports_error(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_sites' )
			->once()
			->andReturn(
				[
					'error'   => 'connection_error',
					'message' => 'Connection refused',
				],
			);

		$commands = $this->create_commands( $client );

		$this->expect_cli_error( 'connection_error: Connection refused' );

		$commands->test( [], [] );
	}

	/**
	 * Verify sites command calls format_items with correct data.
	 *
	 * @return void
	 */
	public function test_sites_command_formats_items(): void {
		$sites = [ $this->create_site_fixture() ];

		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_sites' )
			->once()
			->andReturn( [ 'sites' => $sites ] );

		$commands = $this->create_commands( $client );

		$format_called = false;
		$this->mock_format_items(
			function ( string $format, array $items, array $columns ) use ( &$format_called ): void {
				$format_called = true;
				$this->assert_sites_format( $format, $items, $columns );
			},
		);

		$commands->sites( [], [] );

		$this->assertTrue( $format_called, 'format_items was not called' );
	}

	/**
	 * Verify sites command handles API error.
	 *
	 * @return void
	 */
	public function test_sites_command_handles_error(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_sites' )
			->once()
			->andReturn(
				[
					'error'   => 'http_error',
					'message' => 'Unexpected HTTP 500 response.',
				],
			);

		$commands = $this->create_commands( $client );

		$this->expect_cli_error( 'http_error: Unexpected HTTP 500 response.' );

		$commands->sites( [], [] );
	}

	/**
	 * Verify site command calls API with correct ID.
	 *
	 * @return void
	 */
	public function test_site_command_calls_api_with_id(): void {
		$site_data = $this->create_site_detail_fixture();

		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_site' )
			->once()
			->with( 'uuid-1' )
			->andReturn( $site_data );

		$commands = $this->create_commands( $client );

		$this->mock_format_items(
			static function (): void {
				// Accept any call.
			},
		);

		$commands->site( [ 'uuid-1' ], [] );
	}

	/**
	 * Verify site command handles API error.
	 *
	 * @return void
	 */
	public function test_site_command_handles_error(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_site' )
			->once()
			->with( 'uuid-1' )
			->andReturn(
				[
					'error'   => 'not_found',
					'message' => 'Site not found.',
				],
			);

		$commands = $this->create_commands( $client );

		$this->expect_cli_error( 'not_found: Site not found.' );

		$commands->site( [ 'uuid-1' ], [] );
	}

	/**
	 * Verify plugins command calls API without params.
	 *
	 * @return void
	 */
	public function test_plugins_command_calls_api(): void {
		$plugins = [ $this->create_plugin_fixture() ];

		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_plugins' )
			->once()
			->with( [] )
			->andReturn( [ 'plugins' => $plugins ] );

		$commands = $this->create_commands( $client );

		$format_called = false;
		$this->mock_format_items(
			function ( string $format, array $items ) use ( &$format_called ): void {
				$format_called = true;
				$this->assert_plugin_format( $format, $items );
			},
		);

		$commands->plugins( [], [] );

		$this->assertTrue( $format_called, 'format_items was not called' );
	}

	/**
	 * Verify plugins command passes outdated param.
	 *
	 * @return void
	 */
	public function test_plugins_command_passes_outdated_param(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_plugins' )
			->once()
			->with( [ 'outdated' => 'true' ] )
			->andReturn( [ 'plugins' => [] ] );

		$commands = $this->create_commands( $client );

		$this->mock_format_items( static function (): void {} );

		$commands->plugins( [], [ 'outdated' => true ] );
	}

	/**
	 * Verify themes command calls API without params.
	 *
	 * @return void
	 */
	public function test_themes_command_calls_api(): void {
		$themes = [ $this->create_theme_fixture() ];

		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_themes' )
			->once()
			->with( [] )
			->andReturn( [ 'themes' => $themes ] );

		$commands = $this->create_commands( $client );

		$format_called = false;
		$this->mock_format_items(
			function ( string $format, array $items ) use ( &$format_called ): void {
				$format_called = true;
				$this->assert_theme_format( $format, $items );
			},
		);

		$commands->themes( [], [] );

		$this->assertTrue( $format_called, 'format_items was not called' );
	}

	/**
	 * Verify themes command passes outdated param.
	 *
	 * @return void
	 */
	public function test_themes_command_passes_outdated_param(): void {
		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_themes' )
			->once()
			->with( [ 'outdated' => 'true' ] )
			->andReturn( [ 'themes' => [] ] );

		$commands = $this->create_commands( $client );

		$this->mock_format_items( static function (): void {} );

		$commands->themes( [], [ 'outdated' => true ] );
	}

	/**
	 * Verify plugins command formats site versions correctly.
	 *
	 * @return void
	 */
	public function test_plugins_command_formats_versions(): void {
		$plugins = [ $this->create_multi_site_plugin_fixture() ];

		$client = $this->create_mock_client();
		$client->shouldReceive( 'get_plugins' )
			->once()
			->andReturn( [ 'plugins' => $plugins ] );

		$commands = $this->create_commands( $client );

		$this->mock_format_items(
			function ( string $format, array $items ): void {
				$this->assertSame( 2, $items[0]['site_count'] );
				$this->assertStringContainsString( 'Shop A', $items[0]['versions'] );
				$this->assertStringContainsString( '8.0', $items[0]['versions'] );
			},
		);

		$commands->plugins( [], [] );
	}

	/**
	 * Assert sites format_items receives correct arguments.
	 *
	 * @param string             $format  Output format.
	 * @param array<int, mixed>  $items   Formatted items.
	 * @param array<int, string> $columns Column list.
	 *
	 * @return void
	 */
	private function assert_sites_format(
		string $format,
		array $items,
		array $columns,
	): void {
		$this->assertSame( 'table', $format );
		$this->assertCount( 1, $items );
		$this->assertSame( 'uuid-1', $items[0]['id'] );
		$this->assertContains( 'id', $columns );
		$this->assertContains( 'site_url', $columns );
		$this->assertContains( 'stale', $columns );
	}

	/**
	 * Assert plugins format_items receives correct arguments.
	 *
	 * @param string            $format Output format.
	 * @param array<int, mixed> $items  Formatted items.
	 *
	 * @return void
	 */
	private function assert_plugin_format( string $format, array $items ): void {
		$this->assertSame( 'table', $format );
		$this->assertCount( 1, $items );
		$this->assertSame( 'akismet', $items[0]['slug'] );
		$this->assertSame( 1, $items[0]['site_count'] );
	}

	/**
	 * Assert themes format_items receives correct arguments.
	 *
	 * @param string            $format Output format.
	 * @param array<int, mixed> $items  Formatted items.
	 *
	 * @return void
	 */
	private function assert_theme_format( string $format, array $items ): void {
		$this->assertSame( 'table', $format );
		$this->assertCount( 1, $items );
		$this->assertSame( 'twentytwentyfive', $items[0]['slug'] );
		$this->assertSame( 1, $items[0]['site_count'] );
	}

	/**
	 * Create a site fixture for list tests.
	 *
	 * @return array<string, mixed>
	 */
	private function create_site_fixture(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- API response fixture.
		return [
			'id'                     => 'uuid-1',
			'site_url'               => 'https://site.example.tld',
			'label'                  => 'Example Site',
			'wp_version'             => '6.7',
			'php_version'            => '8.2',
			'pending_plugin_updates' => 2,
			'pending_theme_updates'  => 0,
			'last_seen'              => '2026-04-01 12:00:00',
			'stale'                  => false,
		];
	}

	/**
	 * Create a site detail fixture for detail tests.
	 *
	 * @return array<string, mixed>
	 */
	private function create_site_detail_fixture(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- API response fixture.
		return [
			'id'          => 'uuid-1',
			'label'       => 'Example Site',
			'site_url'    => 'https://site.example.tld',
			'wp_version'  => '6.7',
			'php_version' => '8.2',
			'db_version'  => '8.0',
			'multisite'   => false,
			'plugins'     => [],
			'themes'      => [],
			'users'       => [],
			'roles'       => [],
		];
	}

	/**
	 * Create a plugin fixture for report tests.
	 *
	 * @return array<string, mixed>
	 */
	private function create_plugin_fixture(): array {
		return [
			'slug'  => 'akismet',
			'name'  => 'Akismet',
			'sites' => [
				[
					'site_url'         => 'https://site.example.tld',
					'version'          => '5.0',
					'update_available' => '',
				],
			],
		];
	}

	/**
	 * Create a theme fixture for report tests.
	 *
	 * @return array<string, mixed>
	 */
	private function create_theme_fixture(): array {
		return [
			'slug'  => 'twentytwentyfive',
			'name'  => 'Twenty Twenty-Five',
			'sites' => [
				[
					'site_url'         => 'https://site.example.tld',
					'version'          => '1.0',
					'update_available' => '1.1',
				],
			],
		];
	}

	/**
	 * Create a multi-site plugin fixture for version tests.
	 *
	 * @return array<string, mixed>
	 */
	private function create_multi_site_plugin_fixture(): array {
		return [
			'slug'  => 'woocommerce',
			'name'  => 'WooCommerce',
			'sites' => [
				[
					'site_url'         => 'https://shop-a.example.tld',
					'label'            => 'Shop A',
					'version'          => '8.0',
					'update_available' => '9.0',
				],
				[
					'site_url'         => 'https://shop-b.example.tld',
					'label'            => 'Shop B',
					'version'          => '9.0',
					'update_available' => '',
				],
			],
		];
	}

	/**
	 * Set up expectation for WP_CLI::error() to be called.
	 *
	 * @param string $message Expected error message.
	 *
	 * @return void
	 */
	private function expect_cli_error( string $message ): void {
		$mock_cli = Mockery::mock( 'alias:WP_CLI' );
		$mock_cli->shouldReceive( 'error' )
			->once()
			->with( $message );
	}

	/**
	 * Set up expectation for WP_CLI::success() to be called.
	 *
	 * @param string $message Expected success message.
	 *
	 * @return void
	 */
	private function expect_cli_success( string $message ): void {
		$mock_cli = Mockery::mock( 'alias:WP_CLI' );
		$mock_cli->shouldReceive( 'success' )
			->once()
			->with( $message );
	}

	/**
	 * Mock WP_CLI\Utils\format_items function.
	 *
	 * @param callable $callback Validation callback.
	 *
	 * @return void
	 */
	private function mock_format_items( callable $callback ): void {
		$mock_cli = Mockery::mock( 'alias:WP_CLI' );
		$mock_cli->shouldReceive( 'error' )->zeroOrMoreTimes();
		$mock_cli->shouldReceive( 'success' )->zeroOrMoreTimes();
		$mock_cli->shouldReceive( 'log' )->zeroOrMoreTimes();

		Monkey\Functions\expect( 'WP_CLI\Utils\format_items' )
			->zeroOrMoreTimes()
			->andReturnUsing( $callback );
	}
}
