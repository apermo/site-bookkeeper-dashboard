<?php
/**
 * Plugin Name: DDEV: Skip SSL verify for *.ddev.site
 * Description: Disables SSL certificate verification for WP HTTP requests to *.ddev.site hosts. Local development only.
 * Version: 1.0.0
 * Author: Apermo
 *
 * @package Apermo\SiteBookkeeperDashboard\Dev
 */

declare(strict_types=1);

add_filter(
	'http_request_args',
	static function ( array $args, string $url ): array {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_string( $host ) && str_ends_with( $host, '.ddev.site' ) ) {
			$args['sslverify'] = false;
		}

		return $args;
	},
	10,
	2,
);
