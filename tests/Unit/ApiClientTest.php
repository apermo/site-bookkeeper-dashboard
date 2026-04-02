<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\ApiClient;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ApiClient class.
 */
class ApiClientTest extends TestCase {

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
	 * Create an ApiClient instance for testing.
	 *
	 * @return ApiClient
	 */
	private function create_client(): ApiClient {
		return new ApiClient( 'https://monitor.example.tld', 'test-token' );
	}

	/**
	 * Verify get_sites returns decoded site data from cache.
	 *
	 * @return void
	 */
	public function test_get_sites_returns_cached_data(): void {
		$cached = [
			'sites' => [
				[
					'id'       => 'uuid-1',
					'site_url' => 'https://site.example.tld',
				],
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->with( 'sbd_api_sites' )
			->andReturn( $cached );

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertSame( $cached, $result );
	}

	/**
	 * Verify get_sites makes HTTP request when no cache.
	 *
	 * @return void
	 */
	public function test_get_sites_makes_request_when_no_cache(): void {
		$body = '{"sites":[{"id":"uuid-1","site_url":"https://site.example.tld"}]}';

		$this->stub_uncached_request( 'sbd_api_sites', 'https://monitor.example.tld/sites', $body );

		Functions\expect( 'set_transient' )
			->once()
			->with(
				'sbd_api_sites',
				[
					'sites' => [
						[
							'id'       => 'uuid-1',
							'site_url' => 'https://site.example.tld',
						],
					],
				],
				300,
			);

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertSame(
			[
				'sites' => [
					[
						'id'       => 'uuid-1',
						'site_url' => 'https://site.example.tld',
					],
				],
			],
			$result,
		);
	}

	/**
	 * Verify get_site makes correct request.
	 *
	 * @return void
	 */
	public function test_get_site_makes_request(): void {
		$body = '{"id":"uuid-1","site_url":"https://site.example.tld"}';

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://monitor.example.tld/sites/uuid-1',
				Mockery::type( 'array' ),
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $body,
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );
		Functions\expect( 'set_transient' )->once();

		$client = $this->create_client();
		$result = $client->get_site( 'uuid-1' );

		$this->assertSame(
			[
				'id'       => 'uuid-1',
				'site_url' => 'https://site.example.tld',
			],
			$result,
		);
	}

	/**
	 * Verify get_plugins supports query parameters.
	 *
	 * @return void
	 */
	public function test_get_plugins_with_params(): void {
		$body = '{"plugins":[]}';

		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\stubs( [ 'wp_json_encode' => 'json_encode' ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://monitor.example.tld/plugins?outdated=true',
				Mockery::type( 'array' ),
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $body,
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );
		Functions\expect( 'set_transient' )->once();

		$client = $this->create_client();
		$result = $client->get_plugins( [ 'outdated' => 'true' ] );

		$this->assertSame( [ 'plugins' => [] ], $result );
	}

	/**
	 * Verify get_themes supports query parameters.
	 *
	 * @return void
	 */
	public function test_get_themes_with_params(): void {
		$body = '{"themes":[]}';

		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\stubs( [ 'wp_json_encode' => 'json_encode' ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://monitor.example.tld/themes?slug=twentytwentyfive',
				Mockery::type( 'array' ),
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $body,
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );
		Functions\expect( 'set_transient' )->once();

		$client = $this->create_client();
		$result = $client->get_themes( [ 'slug' => 'twentytwentyfive' ] );

		$this->assertSame( [ 'themes' => [] ], $result );
	}

	/**
	 * Verify connection error returns error array.
	 *
	 * @return void
	 */
	public function test_connection_error_returns_error_array(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( false );

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->once()
			->andReturn( 'Connection refused' );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( $wp_error );
		Functions\expect( 'is_wp_error' )->once()->andReturn( true );

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'connection_error', $result['error'] );
		$this->assertSame( 'Connection refused', $result['message'] );
	}

	/**
	 * Verify HTTP error returns error array.
	 *
	 * @return void
	 */
	public function test_http_error_returns_error_array(): void {
		$body = '{"error":"server_error","message":"Internal error"}';

		Functions\expect( 'get_transient' )->once()->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 500 ],
					'body'     => '',
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 500 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'server_error', $result['error'] );
	}

	/**
	 * Verify invalid JSON returns error array.
	 *
	 * @return void
	 */
	public function test_invalid_json_returns_error_array(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'not json',
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( 'not json' );

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'json_decode_error', $result['error'] );
	}

	/**
	 * Verify HTTP 401 returns auth error.
	 *
	 * @return void
	 */
	public function test_http_401_returns_auth_error(): void {
		$body = '{"error":"unauthorized","message":"Invalid token"}';

		Functions\expect( 'get_transient' )->once()->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'response' => [ 'code' => 401 ] ] );

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 401 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );

		$client = $this->create_client();
		$result = $client->get_sites();

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'unauthorized', $result['error'] );
	}

	/**
	 * Verify clear_cache deletes the matching transient.
	 *
	 * @return void
	 */
	public function test_clear_cache_deletes_transient(): void {
		Functions\expect( 'delete_transient' )
			->once()
			->with( 'sbd_api_sites' );

		$client = $this->create_client();
		$client->clear_cache( 'sites' );
	}

	/**
	 * Stub an uncached successful API request.
	 *
	 * @param string $cache_key Expected transient key.
	 * @param string $url       Expected request URL.
	 * @param string $body      Response body JSON.
	 *
	 * @return void
	 */
	private function stub_uncached_request( string $cache_key, string $url, string $body ): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( $cache_key )
			->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				$url,
				Mockery::on(
					static function ( array $args ): bool {
						return isset( $args['headers']['Authorization'] )
							&& $args['headers']['Authorization'] === 'Bearer test-token';
					},
				),
			)
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => $body,
				],
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );
	}
}
