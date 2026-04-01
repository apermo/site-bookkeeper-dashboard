<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard\Tests\Unit;

use Apermo\SiteMonitorDashboard\Admin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Admin class.
 */
class AdminTest extends TestCase {

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
	 * Verify init registers admin_menu hook.
	 *
	 * @return void
	 */
	public function test_init_registers_admin_menu_hook(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', [ Admin::class, 'register_pages' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', [ Admin::class, 'enqueue_styles' ] );

		Admin::init();
	}

	/**
	 * Verify register_pages adds menu and submenu pages.
	 *
	 * @return void
	 */
	public function test_register_pages_adds_menu_page(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'Site Monitor',
				'Site Monitor',
				'manage_options',
				'site_monitor_dashboard',
				[ Admin::class, 'render_sites_page' ],
				'dashicons-admin-site-alt3',
				Mockery::type( 'int' ),
			);

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'site_monitor_dashboard',
				'Site Detail',
				'',
				'manage_options',
				'site_monitor_dashboard_detail',
				[ Admin::class, 'render_detail_page' ],
			);

		Admin::register_pages();
	}
}
