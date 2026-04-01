<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorDashboard;

/**
 * Settings page for the Site Monitor Dashboard plugin.
 *
 * Manages hub URL and authentication token. Both settings can be
 * overridden via constants SITE_MONITOR_HUB_URL and
 * SITE_MONITOR_CLIENT_TOKEN.
 */
class Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'site_monitor_dashboard';

	/**
	 * Option name for the hub URL.
	 *
	 * @var string
	 */
	public const OPTION_HUB_URL = 'site_monitor_dashboard_hub_url';

	/**
	 * Option name for the authentication token.
	 *
	 * @var string
	 */
	public const OPTION_TOKEN = 'site_monitor_dashboard_token';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_options_page(
			'Site Monitor Dashboard',
			'Site Monitor',
			'manage_options',
			self::OPTION_GROUP,
			[ self::class, 'render_page' ],
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_HUB_URL,
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize_hub_url' ],
				'default'           => '',
			],
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_TOKEN,
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize_token' ],
				'default'           => '',
			],
		);

		add_settings_section(
			'site_monitor_dashboard_main',
			'Connection Settings',
			[ self::class, 'render_section' ],
			self::OPTION_GROUP,
		);

		add_settings_field(
			self::OPTION_HUB_URL,
			'Hub URL',
			[ self::class, 'render_hub_url_field' ],
			self::OPTION_GROUP,
			'site_monitor_dashboard_main',
		);

		add_settings_field(
			self::OPTION_TOKEN,
			'Client Token',
			[ self::class, 'render_token_field' ],
			self::OPTION_GROUP,
			'site_monitor_dashboard_main',
		);
	}

	/**
	 * Get the hub URL, preferring the constant override.
	 *
	 * @return string
	 */
	public static function get_hub_url(): string {
		if ( self::hub_url_is_configured_by_constant() ) {
			return (string) \constant( 'SITE_MONITOR_HUB_URL' );
		}

		return self::get_hub_url_option();
	}

	/**
	 * Get the hub URL from the database option.
	 *
	 * @return string
	 */
	public static function get_hub_url_option(): string {
		return (string) get_option( self::OPTION_HUB_URL, '' );
	}

	/**
	 * Get the authentication token, preferring the constant override.
	 *
	 * @return string
	 */
	public static function get_token(): string {
		if ( self::token_is_configured_by_constant() ) {
			return (string) \constant( 'SITE_MONITOR_CLIENT_TOKEN' );
		}

		return self::get_token_option();
	}

	/**
	 * Get the authentication token from the database option.
	 *
	 * @return string
	 */
	public static function get_token_option(): string {
		return (string) get_option( self::OPTION_TOKEN, '' );
	}

	/**
	 * Check whether the hub URL is configured via constant.
	 *
	 * @return bool
	 */
	public static function hub_url_is_configured_by_constant(): bool {
		return \defined( 'SITE_MONITOR_HUB_URL' )
			&& \constant( 'SITE_MONITOR_HUB_URL' ) !== '';
	}

	/**
	 * Check whether the token is configured via constant.
	 *
	 * @return bool
	 */
	public static function token_is_configured_by_constant(): bool {
		return \defined( 'SITE_MONITOR_CLIENT_TOKEN' )
			&& \constant( 'SITE_MONITOR_CLIENT_TOKEN' ) !== '';
	}

	/**
	 * Sanitize the hub URL value.
	 *
	 * @param string $value Raw input.
	 *
	 * @return string
	 */
	public static function sanitize_hub_url( string $value ): string {
		$value = esc_url_raw( \trim( $value ) );

		return \rtrim( $value, '/' );
	}

	/**
	 * Sanitize the token value.
	 *
	 * @param string $value Raw input.
	 *
	 * @return string
	 */
	public static function sanitize_token( string $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Monitor Dashboard Settings', 'site-monitor-dashboard' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the section description.
	 *
	 * @return void
	 */
	public static function render_section(): void {
		echo '<p>';
		esc_html_e(
			'Configure the connection to the Site Monitor Hub API.',
			'site-monitor-dashboard',
		);
		echo '</p>';
	}

	/**
	 * Render the hub URL input field.
	 *
	 * @return void
	 */
	public static function render_hub_url_field(): void {
		$disabled = self::hub_url_is_configured_by_constant();
		$value    = $disabled ? self::get_hub_url() : self::get_hub_url_option();

		\printf(
			'<input type="url" name="%s" value="%s" class="regular-text" %s />',
			esc_attr( self::OPTION_HUB_URL ),
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">';
			esc_html_e(
				'This value is defined by the SITE_MONITOR_HUB_URL constant.',
				'site-monitor-dashboard',
			);
			echo '</p>';
		}
	}

	/**
	 * Render the token input field.
	 *
	 * @return void
	 */
	public static function render_token_field(): void {
		$disabled = self::token_is_configured_by_constant();
		$value    = $disabled ? \str_repeat( '*', 12 ) : self::get_token_option();

		\printf(
			'<input type="password" name="%s" value="%s" class="regular-text" %s />',
			esc_attr( self::OPTION_TOKEN ),
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">';
			esc_html_e(
				'This value is defined by the SITE_MONITOR_CLIENT_TOKEN constant.',
				'site-monitor-dashboard',
			);
			echo '</p>';
		}
	}
}
