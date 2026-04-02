<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperDashboard;

/**
 * HTTP client for the Site Bookkeeper Hub API.
 *
 * Handles authentication, transient caching (5 min TTL),
 * and error handling for all hub API endpoints.
 */
class ApiClient {

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	public const CACHE_TTL = 300;

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'sbd_api_';

	/**
	 * Hub base URL.
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Bearer token for authentication.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Create a new ApiClient instance.
	 *
	 * @param string $base_url Hub API base URL.
	 * @param string $token    Bearer token.
	 */
	public function __construct( string $base_url, string $token ) {
		$this->base_url = \rtrim( $base_url, '/' );
		$this->token    = $token;
	}

	/**
	 * Create a client from the plugin settings.
	 *
	 * @return self
	 */
	public static function from_settings(): self {
		return new self(
			Settings::get_hub_url(),
			Settings::get_token(),
		);
	}

	/**
	 * Clear all API caches by deleting transients with the cache prefix.
	 *
	 * @return void
	 */
	/**
	 * Get the timestamp of the last API fetch.
	 *
	 * @return int|null Unix timestamp or null if no cache exists.
	 */
	public static function get_last_checked(): ?int {
		$timestamp = get_transient( self::CACHE_PREFIX . 'last_checked' );

		return $timestamp !== false ? (int) $timestamp : null;
	}

	/**
	 * Clear all API caches by deleting transients with the cache prefix.
	 *
	 * @return void
	 */
	public static function flush_all_caches(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transient bulk delete, no object cache equivalent.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::CACHE_PREFIX . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%',
			),
		);
	}

	/**
	 * Fetch all sites from the hub.
	 *
	 * @return array<string, mixed>
	 */
	public function get_sites(): array {
		return $this->request( 'sites' );
	}

	/**
	 * Fetch a single site by ID.
	 *
	 * @param string $site_id Site UUID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_site( string $site_id ): array {
		return $this->request( 'sites/' . $site_id );
	}

	/**
	 * Fetch users across all sites, optionally filtered by search.
	 *
	 * @param array<string, string> $params Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_users( array $params = [] ): array {
		return $this->request( 'users', $params );
	}

	/**
	 * Fetch vulnerability provider status.
	 *
	 * @return array<string, mixed>
	 */
	public function get_vulnerability_status(): array {
		return $this->request( 'vulnerability-status' );
	}

	/**
	 * Fetch cross-site plugin data.
	 *
	 * @param array<string, string> $params Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_plugins( array $params = [] ): array {
		return $this->request( 'plugins', $params );
	}

	/**
	 * Fetch cross-site theme data.
	 *
	 * @param array<string, string> $params Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function get_themes( array $params = [] ): array {
		return $this->request( 'themes', $params );
	}

	/**
	 * Fetch all networks from the hub.
	 *
	 * @return array<string, mixed>
	 */
	public function get_networks(): array {
		return $this->request( 'networks' );
	}

	/**
	 * Fetch a single network by ID.
	 *
	 * @param string $network_id Network UUID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_network( string $network_id ): array {
		return $this->request( 'networks/' . $network_id );
	}

	/**
	 * Clear a specific API cache entry.
	 *
	 * @param string $endpoint The endpoint key (e.g. 'sites').
	 *
	 * @return void
	 */
	public function clear_cache( string $endpoint ): void {
		delete_transient( self::CACHE_PREFIX . $endpoint );
	}

	/**
	 * Make a cached HTTP GET request to the hub API.
	 *
	 * Refuses to send requests over plain HTTP unless the
	 * SITE_BOOKKEEPER_ALLOW_HTTP constant is defined and truthy.
	 *
	 * @param string                $endpoint Relative API endpoint.
	 * @param array<string, string> $params   Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function request( string $endpoint, array $params = [] ): array {
		if ( ! \str_starts_with( $this->base_url, 'https://' ) && ! Settings::http_is_allowed() ) {
			return [
				'error'   => 'https_required',
				'message' => 'The hub URL must use HTTPS.',
			];
		}

		$cache_key = self::CACHE_PREFIX . $endpoint;
		if ( $params !== [] ) {
			$cache_key .= '_' . \md5( (string) wp_json_encode( $params ) );
		}

		$cached = get_transient( $cache_key );
		if ( $cached !== false ) {
			return (array) $cached;
		}

		return $this->fetch_and_cache( $endpoint, $params, $cache_key );
	}

	/**
	 * Perform the HTTP request and cache the result.
	 *
	 * @param string                $endpoint  Relative API endpoint.
	 * @param array<string, string> $params    Query parameters.
	 * @param string                $cache_key Transient cache key.
	 *
	 * @return array<string, mixed>
	 */
	private function fetch_and_cache( string $endpoint, array $params, string $cache_key ): array {
		$url = $this->build_url( $endpoint, $params );

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token,
					'Accept'        => 'application/json',
				],
				'timeout' => 15,
			],
		);

		if ( is_wp_error( $response ) ) {
			return [
				'error'   => 'connection_error',
				'message' => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->parse_error_response( $body, $status_code );
		}

		return $this->decode_and_cache( $body, $cache_key );
	}

	/**
	 * Build the full request URL.
	 *
	 * @param string                $endpoint Relative API endpoint.
	 * @param array<string, string> $params   Query parameters.
	 *
	 * @return string
	 */
	private function build_url( string $endpoint, array $params ): string {
		$url = $this->base_url . '/' . $endpoint;
		if ( $params !== [] ) {
			$url .= '?' . \http_build_query( $params );
		}

		return $url;
	}

	/**
	 * Decode JSON response and store in transient cache.
	 *
	 * @param string $body      Response body.
	 * @param string $cache_key Transient cache key.
	 *
	 * @return array<string, mixed>
	 */
	private function decode_and_cache( string $body, string $cache_key ): array {
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			return [
				'error'   => 'json_decode_error',
				'message' => 'Failed to decode API response.',
			];
		}

		set_transient( $cache_key, $data, self::CACHE_TTL );
		set_transient( self::CACHE_PREFIX . 'last_checked', \time(), self::CACHE_TTL );

		return $data;
	}

	/**
	 * Parse an error response body.
	 *
	 * Falls back to a generic HTTP error when the response
	 * body does not contain a structured error payload.
	 *
	 * @param string $body        Response body.
	 * @param int    $status_code HTTP status code.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_error_response( string $body, int $status_code ): array {
		$data = \json_decode( $body, true );

		if ( \is_array( $data ) && isset( $data['error'] ) ) {
			return $data;
		}

		return [
			'error'   => 'http_error',
			'message' => \sprintf( 'Unexpected HTTP %d response.', $status_code ),
		];
	}
}
