<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Settings page for the Site Bookkeeper Dashboard plugin.
 *
 * Manages hub URL and authentication token. Both settings can be
 * overridden via constants SITE_BOOKKEEPER_HUB_URL and
 * SITE_BOOKKEEPER_CLIENT_TOKEN.
 */
class Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'site_bookkeeper_dashboard';

	/**
	 * Option name for the hub URL.
	 *
	 * @var string
	 */
	public const OPTION_HUB_URL = 'site_bookkeeper_dashboard_hub_url';

	/**
	 * Option name for the authentication token.
	 *
	 * @var string
	 */
	public const OPTION_TOKEN = 'site_bookkeeper_dashboard_token';

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
			'Site Bookkeeper Dashboard',
			'Site Bookkeeper',
			'manage_options',
			self::OPTION_GROUP . '_settings',
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
			'site_bookkeeper_dashboard_main',
			'Connection Settings',
			[ self::class, 'render_section' ],
			self::OPTION_GROUP,
		);

		add_settings_field(
			self::OPTION_HUB_URL,
			'Hub URL',
			[ self::class, 'render_hub_url_field' ],
			self::OPTION_GROUP,
			'site_bookkeeper_dashboard_main',
		);

		add_settings_field(
			self::OPTION_TOKEN,
			'Client Token',
			[ self::class, 'render_token_field' ],
			self::OPTION_GROUP,
			'site_bookkeeper_dashboard_main',
		);
	}

	/**
	 * Get the hub URL, preferring the constant override.
	 *
	 * @return string
	 */
	public static function get_hub_url(): string {
		if ( self::hub_url_is_configured_by_constant() ) {
			return (string) \constant( 'SITE_BOOKKEEPER_HUB_URL' );
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
			return (string) \constant( 'SITE_BOOKKEEPER_CLIENT_TOKEN' );
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
		return \defined( 'SITE_BOOKKEEPER_HUB_URL' )
			&& \constant( 'SITE_BOOKKEEPER_HUB_URL' ) !== '';
	}

	/**
	 * Check whether the token is configured via constant.
	 *
	 * @return bool
	 */
	public static function token_is_configured_by_constant(): bool {
		return \defined( 'SITE_BOOKKEEPER_CLIENT_TOKEN' )
			&& \constant( 'SITE_BOOKKEEPER_CLIENT_TOKEN' ) !== '';
	}

	/**
	 * Check whether plain HTTP is allowed via constant.
	 *
	 * @return bool
	 */
	public static function http_is_allowed(): bool {
		return \defined( 'SITE_BOOKKEEPER_ALLOW_HTTP' )
			&& \constant( 'SITE_BOOKKEEPER_ALLOW_HTTP' );
	}

	/**
	 * Sanitize the hub URL value.
	 *
	 * Rejects non-HTTPS URLs unless the SITE_BOOKKEEPER_ALLOW_HTTP
	 * constant is defined and truthy.
	 *
	 * @param string $value Raw input.
	 *
	 * @return string
	 */
	public static function sanitize_hub_url( string $value ): string {
		$value = esc_url_raw( \trim( $value ) );
		$value = \rtrim( $value, '/' );

		if ( $value !== '' && ! \str_starts_with( $value, 'https://' ) && ! self::http_is_allowed() ) {
			add_settings_error(
				self::OPTION_HUB_URL,
				'https_required',
				__( 'The hub URL must use HTTPS.', 'site-bookkeeper-dashboard' ),
			);

			return self::get_hub_url_option();
		}

		return $value;
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
		$flush_url = wp_nonce_url(
			admin_url( 'options-general.php?page=' . self::OPTION_GROUP . '_settings&sbd_flush_slugs=1' ),
			'sbd_flush_slugs',
		);
		$flushed = isset( $_GET['sbd_slugs_flushed'] ) && $_GET['sbd_slugs_flushed'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Bookkeeper Dashboard Settings', 'site-bookkeeper-dashboard' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				submit_button();
				?>
			</form>
			<?php CategoryAdmin::render(); ?>
			<hr>
			<h2><?php esc_html_e( 'Cache', 'site-bookkeeper-dashboard' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					\sprintf(
						/* translators: %d: number of cached entries */
						__( 'Plugin and theme names link to WordPress.org when available. Results are cached permanently. Currently %d entries cached.', 'site-bookkeeper-dashboard' ),
						SlugResolver::count(),
					),
				);
				?>
			</p>
			<?php
			if ( $flushed ) {
				echo '<div class="notice notice-success inline"><p>';
				esc_html_e( 'Link cache cleared.', 'site-bookkeeper-dashboard' );
				echo '</p></div>';
			}
			?>
			<p>
				<a href="<?php echo esc_url( $flush_url ); ?>" class="button">
					<?php esc_html_e( 'Reset link cache', 'site-bookkeeper-dashboard' ); ?>
				</a>
			</p>
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
			'Configure the connection to the Site Bookkeeper Hub API.',
			'site-bookkeeper-dashboard',
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
				'This value is defined by the SITE_BOOKKEEPER_HUB_URL constant.',
				'site-bookkeeper-dashboard',
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
				'This value is defined by the SITE_BOOKKEEPER_CLIENT_TOKEN constant.',
				'site-bookkeeper-dashboard',
			);
			echo '</p>';
		}
	}
}
