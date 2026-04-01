<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard\Tests\Unit;

use Apermo\SiteMonitorDashboard\SiteDetail;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SiteDetail class.
 */
class SiteDetailTest extends TestCase {

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
	 * Stub common WordPress escaping functions.
	 *
	 * @return void
	 */
	private function stub_escaping(): void {
		Functions\stubs(
			[
				'esc_html' => static fn( string $text ): string => $text,
				'esc_html__' => static fn( string $text ): string => $text,
				'esc_attr' => static fn( string $text ): string => $text,
			],
		);
	}

	/**
	 * Verify render outputs environment section.
	 *
	 * @return void
	 */
	public function test_render_outputs_environment_section(): void {
		$this->stub_escaping();

		$site = [
			'wp_version' => '6.8',
			'php_version' => '8.3.4',
			'db_version' => '10.6',
			'site_url' => 'https://site.example.tld',
			'active_theme' => 'Twenty Twenty-Five',
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Environment', $output );
		$this->assertStringContainsString( '6.8', $output );
		$this->assertStringContainsString( '8.3.4', $output );
		$this->assertStringContainsString( 'Twenty Twenty-Five', $output );
	}

	/**
	 * Verify render outputs plugins table.
	 *
	 * @return void
	 */
	public function test_render_outputs_plugins_table(): void {
		$this->stub_escaping();

		$site = [
			'plugins' => [
				[
					'name' => 'Akismet',
					'version' => '5.3',
					'update_available' => '5.4',
					'active' => true,
					'last_updated' => '2026-03-15',
				],
			],
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Plugins', $output );
		$this->assertStringContainsString( 'Akismet', $output );
		$this->assertStringContainsString( '5.4', $output );
	}

	/**
	 * Verify render outputs themes table.
	 *
	 * @return void
	 */
	public function test_render_outputs_themes_table(): void {
		$this->stub_escaping();

		$site = [
			'themes' => [
				[
					'name' => 'Twenty Twenty-Five',
					'version' => '1.0',
					'update_available' => '',
					'active' => true,
				],
			],
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Themes', $output );
		$this->assertStringContainsString( 'Twenty Twenty-Five', $output );
	}

	/**
	 * Verify render outputs custom fields with badges.
	 *
	 * @return void
	 */
	public function test_render_outputs_custom_fields(): void {
		$this->stub_escaping();

		$site = [
			'custom_fields' => [
				[
					'key' => 'ssl_expiry',
					'label' => 'SSL Certificate',
					'value' => '2026-12-01',
					'status' => 'good',
				],
				[
					'key' => 'disk_usage',
					'label' => 'Disk Usage',
					'value' => '85%',
					'status' => 'warning',
				],
			],
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Custom Fields', $output );
		$this->assertStringContainsString( 'SSL Certificate', $output );
		$this->assertStringContainsString( 'smd-badge-good', $output );
		$this->assertStringContainsString( 'smd-badge-warning', $output );
	}

	/**
	 * Verify render outputs users table.
	 *
	 * @return void
	 */
	public function test_render_outputs_users_table(): void {
		$this->stub_escaping();

		$site = [
			'users' => [
				[
					'login' => 'admin',
					'display_name' => 'Administrator',
					'email' => 'admin@example.tld',
					'role' => 'administrator',
					'meta' => [ '2fa' => 'enabled' ],
				],
			],
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Users', $output );
		$this->assertStringContainsString( 'admin', $output );
		$this->assertStringContainsString( '2fa: enabled', $output );
	}

	/**
	 * Verify render outputs roles section.
	 *
	 * @return void
	 */
	public function test_render_outputs_roles_section(): void {
		$this->stub_escaping();

		$site = [
			'roles' => [
				[
					'name' => 'editor',
					'custom' => false,
					'modified' => false,
					'capability_count' => 35,
				],
				[
					'name' => 'shop_manager',
					'custom' => true,
					'modified' => false,
					'capability_count' => 42,
				],
			],
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Roles', $output );
		$this->assertStringContainsString( 'shop_manager', $output );
		$this->assertStringContainsString( 'Custom', $output );
		$this->assertStringContainsString( 'Default', $output );
	}

	/**
	 * Verify render skips empty sections.
	 *
	 * @return void
	 */
	public function test_render_skips_empty_sections(): void {
		$this->stub_escaping();

		$site = [
			'wp_version' => '6.8',
		];

		\ob_start();
		SiteDetail::render( $site );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Environment', $output );
		$this->assertStringNotContainsString( 'Plugins', $output );
		$this->assertStringNotContainsString( 'Themes', $output );
		$this->assertStringNotContainsString( 'Custom Fields', $output );
		$this->assertStringNotContainsString( 'Users', $output );
		$this->assertStringNotContainsString( 'Roles', $output );
	}
}
