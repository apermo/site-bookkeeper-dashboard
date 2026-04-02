<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * Resolves plugin/theme slugs to WordPress.org URLs.
 *
 * Results are cached in a single transient with no expiry.
 * A manual reset option clears the cache.
 */
class SlugResolver {

	/**
	 * Transient key for the slug cache.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'sbd_slug_urls';

	/**
	 * In-memory cache loaded from the transient.
	 *
	 * @var array<string, string|false>|null
	 */
	private static ?array $cache = null;

	/**
	 * Get the WordPress.org URL for a plugin slug, or null if not found.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return string|null URL or null if not on WordPress.org.
	 */
	public static function plugin_url( string $slug ): ?string {
		return self::resolve( 'plugin:' . $slug, 'https://wordpress.org/plugins/' . $slug . '/' );
	}

	/**
	 * Get the WordPress.org URL for a theme slug, or null if not found.
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return string|null URL or null if not on WordPress.org.
	 */
	public static function theme_url( string $slug ): ?string {
		return self::resolve( 'theme:' . $slug, 'https://wordpress.org/themes/' . $slug . '/' );
	}

	/**
	 * Get the number of cached slug resolutions.
	 *
	 * @return int
	 */
	public static function count(): int {
		self::load_cache();

		return \count( self::$cache );
	}

	/**
	 * Clear the entire slug cache.
	 *
	 * @return void
	 */
	public static function flush(): void {
		delete_option( self::CACHE_KEY );
		self::$cache = null;
	}

	/**
	 * Resolve a cache key to a URL by checking WordPress.org.
	 *
	 * @param string $cache_key Cache key (e.g. 'plugin:akismet').
	 * @param string $check_url URL to verify via HEAD request.
	 *
	 * @return string|null The URL if it exists, null otherwise.
	 */
	private static function resolve( string $cache_key, string $check_url ): ?string {
		self::load_cache();

		if ( \array_key_exists( $cache_key, self::$cache ) ) {
			$cached = self::$cache[ $cache_key ];

			return $cached !== false ? $cached : null;
		}

		$response = wp_remote_head( $check_url, [ 'timeout' => 5 ] );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$found = $status === 200;

		self::$cache[ $cache_key ] = $found ? $check_url : false;
		self::save_cache();

		return $found ? $check_url : null;
	}

	/**
	 * Load the cache from the transient if not already loaded.
	 *
	 * @return void
	 */
	private static function load_cache(): void {
		if ( self::$cache !== null ) {
			return;
		}

		$stored = get_option( self::CACHE_KEY, [] );
		self::$cache = \is_array( $stored ) ? $stored : [];
	}

	/**
	 * Persist the in-memory cache to the transient (no expiry).
	 *
	 * @return void
	 */
	private static function save_cache(): void {
		update_option( self::CACHE_KEY, self::$cache, false );
	}
}
