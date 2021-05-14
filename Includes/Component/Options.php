<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client Options.
 *
 * @package TheWebSolver\License_Manager\Client
 *
 * -----------------------------------
 * DEVELOPED-MAINTAINED-SUPPPORTED BY
 * -----------------------------------
 * ███║     ███╗   ████████████████
 * ███║     ███║   ═════════██████╗
 * ███║     ███║        ╔══█████═╝
 *  ████████████║      ╚═█████
 * ███║═════███║      █████╗
 * ███║     ███║    █████═╝
 * ███║     ███║   ████████████████╗
 * ╚═╝      ╚═╝    ═══════════════╝
 */

namespace TheWebSolver\License_Manager\Component;

/**
 * The Web Solver Licence Manager Client Options class.
 */
class Options {
	/**
	 * Default License Manager REST API version.
	 */
	const VERSION = 'v1';

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
	const USER_AGENT = 'TheWebSolver License Manager API Client-PHP';

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
	 * @return int
	 */
	public function verify_ssl() {
		return isset( $this->options['verify_ssl'] ) ? (int) $this->options['verify_ssl'] : 2;
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
	 * Custom API Prefix for WP API.
	 *
	 * @return string
	 */
	public function api_prefix() {
		return isset( $this->options['wp_api_prefix'] ) ? $this->options['wp_api_prefix'] : self::WP_API_PREFIX;
	}

	/**
	 * Namespace for the API.
	 *
	 * @return string|\WP_Error
	 */
	public function namespace() {
		return isset( $this->options['namespace'] )
		? $this->options['namespace']
		: new \WP_Error(
			'rest_api_namespace_not_valid',
			__( 'REST API namespace must be defined first.', 'tws-license-manager-client' ),
		);
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
