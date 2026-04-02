<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard\Tests\Unit;

use Apermo\SiteBookkeeperDashboard\NetworkDetail;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NetworkDetail class.
 */
class NetworkDetailTest extends TestCase {

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
				'esc_url' => static fn( string $url ): string => $url,
				'admin_url' => static fn( string $path ): string => '/wp-admin/' . $path,
			],
		);
	}

	/**
	 * Verify render outputs network info section.
	 *
	 * @return void
	 */
	public function test_render_outputs_network_info(): void {
		$this->stub_escaping();

		$network = [
			'main_site_url' => 'https://network.example.tld',
			'subsite_count' => 5,
			'last_seen'     => '2026-04-01T12:00:00+00:00',
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Network Info', $output );
		$this->assertStringContainsString( 'https://network.example.tld', $output );
		$this->assertStringContainsString( '5', $output );
	}

	/**
	 * Verify render outputs network plugins table.
	 *
	 * @return void
	 */
	public function test_render_outputs_network_plugins(): void {
		$this->stub_escaping();

		$network = [
			'network_plugins' => [
				[
					'slug'             => 'akismet',
					'name'             => 'Akismet',
					'version'          => '5.3',
					'update_available' => '5.4',
				],
			],
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Network-Activated Plugins', $output );
		$this->assertStringContainsString( 'Akismet', $output );
		$this->assertStringContainsString( '5.4', $output );
	}

	/**
	 * Verify render outputs super admins table.
	 *
	 * @return void
	 */
	public function test_render_outputs_super_admins(): void {
		$this->stub_escaping();

		$network = [
			'super_admins' => [
				[
					'user_login'   => 'admin',
					'display_name' => 'Site Admin',
					'email'        => 'admin@example.tld',
				],
			],
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Super Admins', $output );
		$this->assertStringContainsString( 'admin', $output );
		$this->assertStringContainsString( 'admin@example.tld', $output );
	}

	/**
	 * Verify render outputs network settings.
	 *
	 * @return void
	 */
	public function test_render_outputs_network_settings(): void {
		$this->stub_escaping();

		$network = [
			'network_settings' => [
				[
					'key'   => 'registration',
					'label' => 'Registration',
					'value' => 'none',
				],
			],
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Network Settings', $output );
		$this->assertStringContainsString( 'Registration', $output );
		$this->assertStringContainsString( 'none', $output );
	}

	/**
	 * Verify render outputs subsites table with links.
	 *
	 * @return void
	 */
	public function test_render_outputs_subsites(): void {
		$this->stub_escaping();

		$network = [
			'subsites' => [
				[
					'id'       => 'site-uuid-1',
					'site_url' => 'https://sub.example.tld',
					'label'    => 'Subsite One',
				],
			],
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Subsites', $output );
		$this->assertStringContainsString( 'Subsite One', $output );
		$this->assertStringContainsString( 'site-uuid-1', $output );
	}

	/**
	 * Verify render skips empty sections.
	 *
	 * @return void
	 */
	public function test_render_skips_empty_sections(): void {
		$this->stub_escaping();

		$network = [
			'main_site_url' => 'https://network.example.tld',
			'subsite_count' => 3,
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( 'Network Info', $output );
		$this->assertStringNotContainsString( 'Network-Activated Plugins', $output );
		$this->assertStringNotContainsString( 'Super Admins', $output );
		$this->assertStringNotContainsString( 'Network Settings', $output );
		$this->assertStringNotContainsString( 'Subsites', $output );
	}

	/**
	 * Verify network plugins without update show dash.
	 *
	 * @return void
	 */
	public function test_network_plugin_without_update_shows_dash(): void {
		$this->stub_escaping();

		$network = [
			'network_plugins' => [
				[
					'slug'             => 'akismet',
					'name'             => 'Akismet',
					'version'          => '5.3',
					'update_available' => '',
				],
			],
		];

		\ob_start();
		NetworkDetail::render( $network );
		$output = (string) \ob_get_clean();

		$this->assertStringContainsString( '&mdash;', $output );
	}
}
