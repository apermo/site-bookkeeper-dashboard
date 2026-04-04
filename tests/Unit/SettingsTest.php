<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Settings class.
 */
class SettingsTest extends TestCase {

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
	 * Verify init registers admin hooks.
	 *
	 * @return void
	 */
	public function test_init_registers_hooks(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', [ Settings::class, 'add_menu_page' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', [ Settings::class, 'register_settings' ] );

		Settings::init();
	}

	/**
	 * Verify get_hub_url returns constant value when defined.
	 *
	 * @return void
	 */
	public function test_get_hub_url_returns_constant_when_defined(): void {
		if ( ! \defined( 'SITE_BOOKKEEPER_HUB_URL' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- User-facing constant.
			\define( 'SITE_BOOKKEEPER_HUB_URL', 'https://monitor.example.tld' );
		}

		$this->assertSame( 'https://monitor.example.tld', Settings::get_hub_url() );
	}

	/**
	 * Verify get_token returns constant value when defined.
	 *
	 * @return void
	 */
	public function test_get_token_returns_constant_when_defined(): void {
		if ( ! \defined( 'SITE_BOOKKEEPER_CLIENT_TOKEN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- User-facing constant.
			\define( 'SITE_BOOKKEEPER_CLIENT_TOKEN', 'test-token-123' );
		}

		$this->assertSame( 'test-token-123', Settings::get_token() );
	}

	/**
	 * Verify get_hub_url falls back to option when constant not defined.
	 *
	 * @return void
	 */
	public function test_get_hub_url_falls_back_to_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'site_bookkeeper_dashboard_hub_url', '' )
			->andReturn( 'https://hub.example.tld' );

		// Constants are already defined from previous tests,
		// so we test the option fallback path separately.
		$result = Settings::get_hub_url_option();

		$this->assertSame( 'https://hub.example.tld', $result );
	}

	/**
	 * Verify get_token falls back to option when constant not defined.
	 *
	 * @return void
	 */
	public function test_get_token_falls_back_to_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'site_bookkeeper_dashboard_token', '' )
			->andReturn( 'option-token-456' );

		$result = Settings::get_token_option();

		$this->assertSame( 'option-token-456', $result );
	}

	/**
	 * Verify hub_url_is_configured_by_constant returns true when defined.
	 *
	 * @return void
	 */
	public function test_hub_url_is_configured_by_constant(): void {
		$this->assertTrue( Settings::hub_url_is_configured_by_constant() );
	}

	/**
	 * Verify token_is_configured_by_constant returns true when defined.
	 *
	 * @return void
	 */
	public function test_token_is_configured_by_constant(): void {
		$this->assertTrue( Settings::token_is_configured_by_constant() );
	}

	/**
	 * Verify register_settings registers option group and fields.
	 *
	 * @return void
	 */
	public function test_register_settings_registers_options(): void {
		Functions\expect( 'register_setting' )
			->once()
			->with(
				'site_bookkeeper_dashboard',
				'site_bookkeeper_dashboard_hub_url',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'register_setting' )
			->once()
			->with(
				'site_bookkeeper_dashboard',
				'site_bookkeeper_dashboard_token',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'add_settings_section' )
			->once()
			->with(
				'site_bookkeeper_dashboard_main',
				Mockery::type( 'string' ),
				Mockery::type( 'array' ),
				'site_bookkeeper_dashboard',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_bookkeeper_dashboard_hub_url',
				Mockery::type( 'string' ),
				Mockery::type( 'array' ),
				'site_bookkeeper_dashboard',
				'site_bookkeeper_dashboard_main',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_bookkeeper_dashboard_token',
				Mockery::type( 'string' ),
				Mockery::type( 'array' ),
				'site_bookkeeper_dashboard',
				'site_bookkeeper_dashboard_main',
			);

		Settings::register_settings();
	}

	/**
	 * Verify add_menu_page registers the options page.
	 *
	 * @return void
	 */
	public function test_add_menu_page_registers_page(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->with(
				'Site Bookkeeper Dashboard',
				'Site Bookkeeper',
				'manage_options',
				'site_bookkeeper_dashboard_settings',
				[ Settings::class, 'render_page' ],
			);

		Settings::add_menu_page();
	}

	/**
	 * Verify sanitize_hub_url trims and validates URL.
	 *
	 * @return void
	 */
	public function test_sanitize_hub_url_trims_trailing_slash(): void {
		Functions\stubs( [ 'esc_url_raw' => static fn( string $url ): string => $url ] );

		$this->assertSame(
			'https://monitor.example.tld',
			Settings::sanitize_hub_url( 'https://monitor.example.tld/' ),
		);
	}

	/**
	 * Verify sanitize_hub_url handles empty string.
	 *
	 * @return void
	 */
	public function test_sanitize_hub_url_handles_empty(): void {
		Functions\stubs( [ 'esc_url_raw' => static fn( string $url ): string => $url ] );

		$this->assertSame( '', Settings::sanitize_hub_url( '' ) );
	}

	/**
	 * Verify sanitize_hub_url rejects HTTP URLs.
	 *
	 * @return void
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sanitize_hub_url_rejects_http(): void {
		Functions\stubs( [ 'esc_url_raw' => static fn( string $url ): string => $url ] );

		Functions\expect( 'get_option' )
			->once()
			->with( 'site_bookkeeper_dashboard_hub_url', '' )
			->andReturn( 'https://old.example.tld' );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'site_bookkeeper_dashboard_hub_url',
				'https_required',
				Mockery::type( 'string' ),
			);

		Functions\stubs( [ '__' => static fn( string $text ): string => $text ] );

		$result = Settings::sanitize_hub_url( 'http://insecure.example.tld' );

		$this->assertSame( 'https://old.example.tld', $result );
	}

	/**
	 * Verify sanitize_hub_url accepts HTTPS URLs.
	 *
	 * @return void
	 */
	public function test_sanitize_hub_url_accepts_https(): void {
		Functions\stubs( [ 'esc_url_raw' => static fn( string $url ): string => $url ] );

		$result = Settings::sanitize_hub_url( 'https://secure.example.tld' );

		$this->assertSame( 'https://secure.example.tld', $result );
	}

	/**
	 * Verify sanitize_hub_url allows HTTP when constant is set.
	 *
	 * @return void
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_sanitize_hub_url_allows_http_with_constant(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- User-facing constant.
		\define( 'SITE_BOOKKEEPER_ALLOW_HTTP', true );

		Functions\stubs( [ 'esc_url_raw' => static fn( string $url ): string => $url ] );

		$result = Settings::sanitize_hub_url( 'http://local.example.tld' );

		$this->assertSame( 'http://local.example.tld', $result );
	}

	/**
	 * Verify sanitize_token trims whitespace.
	 *
	 * @return void
	 */
	public function test_sanitize_token_trims_whitespace(): void {
		Functions\stubs( [ 'sanitize_text_field' => static fn( string $text ): string => \trim( $text ) ] );

		$this->assertSame(
			'my-token',
			Settings::sanitize_token( '  my-token  ' ),
		);
	}
}
