<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver REST API HTTP Client Options.
 *
 * @package  TheWebSolver\Core\REST_API
 */

namespace TheWebSolver\Core\REST_API\HttpClient;

/**
 * REST API HTTP Client Options class.
 */
class Options {
	/**
	 * Default License Manager REST API version.
	 */
	const VERSION = 'lmfwc/v2';

	/**
	 * Default request timeout.
	 */
	const TIMEOUT = 15;

	/**
	 * Default WP API prefix.
	 * Including leading and trailing slashes.
	 */
	const WP_API_PREFIX = '/wp-json/';

	/**
	 * Default User Agent.
	 * No version number.
	 */
	const USER_AGENT = 'TheWebSolver API Client-PHP';

	/**
	 * Options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Initialize HTTP client options.
	 *
	 * @param array $options Client options.
	 */
	public function __construct( $options ) {
		$this->options = $options;
	}

	/**
	 * Get API version.
	 *
	 * @return string
	 */
	public function get_version() {
		return isset( $this->options['version'] ) ? $this->options['version'] : self::VERSION;
	}

	/**
	 * Check if need to verify SSL.
	 *
	 * @return bool
	 */
	public function verify_ssl() {
		return isset( $this->options['verify_ssl'] ) ? (bool) $this->options['verify_ssl'] : true;
	}

	/**
	 * Get timeout.
	 *
	 * @return int
	 */
	public function get_timeout() {
		return isset( $this->options['timeout'] ) ? (int) $this->options['timeout'] : self::TIMEOUT;
	}

	/**
	 * Basic Authentication as query string.
	 * Some old servers are not able to use CURLOPT_USERPWD.
	 *
	 * @return bool
	 */
	public function is_query_string_auth() {
		return isset( $this->options['query_string_auth'] ) ? (bool) $this->options['query_string_auth'] : false;
	}

	/**
	 * Check if is WP REST API.
	 *
	 * @return bool
	 */
	public function is_wp_api() {
		return isset( $this->options['wp_api'] ) ? (bool) $this->options['wp_api'] : true;
	}

	/**
	 * Custom API Prefix for WP API.
	 *
	 * @return string
	 */
	public function api_prefix() {
		return isset( $this->options['wp_api_prefix'] ) ? $this->options['wp_api_prefix'] : self::WP_API_PREFIX;
	}

	/**
	 * Gets oAuth timestamp.
	 *
	 * @return string
	 */
	public function oauth_timestamp() {
		return isset( $this->options['oauth_timestamp'] ) ? $this->options['oauth_timestamp'] : \time();
	}

	/**
	 * Custom user agent.
	 *
	 * @return string
	 */
	public function user_agent() {
		return isset( $this->options['user_agent'] ) ? $this->options['user_agent'] : self::USER_AGENT;
	}

	/**
	 * Get follow redirects
	 *
	 * @return bool
	 */
	public function get_follow_redirect() {
		return isset( $this->options['follow_redirects'] ) ? (bool) $this->options['follow_redirects'] : false;
	}
}
