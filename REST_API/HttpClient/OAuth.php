<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client OAuth Authentication for non-SSL sites.
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

namespace TheWebSolver\License_Manager\REST_API\HttpClient;

/**
 * The Web Solver Licence Manager Client OAuth Authentication class.
 */
class OAuth {
	/**
	 * OAuth signature method algorithm.
	 */
	const HASH_ALGORITHM = 'SHA256';

	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Consumer key.
	 *
	 * @var string
	 */
	protected $consumer_key;

	/**
	 * Consumer secret.
	 *
	 * @var string
	 */
	protected $consumer_secret;

	/**
	 * API version.
	 *
	 * @var string
	 */
	protected $api_version;

	/**
	 * Request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * Request parameters.
	 *
	 * @var array
	 */
	protected $parameters;

	/**
	 * Timestamp.
	 *
	 * @var string
	 */
	protected $timestamp;

	/**
	 * Initialize oAuth class.
	 *
	 * @param string $url             API endpoint URL.
	 * @param string $consumer_key    Consumer key.
	 * @param string $consumer_secret Consumer Secret.
	 * @param string $api_version     API version.
	 * @param string $method          Request method.
	 * @param array  $parameters      Request parameters.
	 * @param string $timestamp       Timestamp.
	 */
	public function __construct(
		$url,
		$consumer_key,
		$consumer_secret,
		$api_version,
		$method,
		$parameters = array(),
		$timestamp = ''
	) {
		$this->url             = $url;
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
		$this->api_version     = $api_version;
		$this->method          = $method;
		$this->parameters      = $parameters;
		$this->timestamp       = $timestamp;
	}

	/**
	 * Encode according to RFC 3986.
	 *
	 * @param string|array $value Value to be normalized.
	 *
	 * @return string
	 */
	protected function encode( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'encode' ), $value );
		} else {
			return str_replace( array( '+', '%7E' ), array( ' ', '~' ), rawurlencode( $value ) );
		}
	}

	/**
	 * Normalize parameters.
	 *
	 * @param array $parameters Parameters to normalize.
	 *
	 * @return array
	 */
	protected function normalize_parameters( $parameters ) {
		$normalized = array();

		foreach ( $parameters as $key => $value ) {
			// Percent symbols (%) must be double-encoded.
			$key   = $this->encode( $key );
			$value = $this->encode( $value );

			$normalized[ $key ] = $value;
		}

		return $normalized;
	}

	/**
	 * Process filters.
	 *
	 * @param array $parameters Request parameters.
	 *
	 * @return array
	 */
	protected function process_filters( $parameters ) {
		if ( isset( $parameters['filter'] ) ) {
			$filters = $parameters['filter'];
			unset( $parameters['filter'] );
			foreach ( $filters as $filter => $value ) {
				$parameters[ 'filter[' . $filter . ']' ] = $value;
			}
		}

		return $parameters;
	}

	/**
	 * Get secret.
	 *
	 * @return string
	 */
	protected function get_secret() {
		return $this->consumer_secret . '&';
	}

	/**
	 * Generate oAuth1.0 signature.
	 *
	 * @param array $parameters Request parameters including oauth.
	 *
	 * @return string
	 */
	protected function generate_oauth_signature( $parameters ) {
		$base_request_uri = \rawurlencode( $this->url );

		// Extract filters.
		$parameters = $this->process_filters( $parameters );

		// Normalize parameter key/values and sort them.
		$parameters = $this->normalize_parameters( $parameters );
		\uksort( $parameters, 'strcmp' );

		// Set query string.
		$query_string   = \implode( '%26', $this->join_with_equals_sign( $parameters ) ); // Join with ampersand.
		$string_to_sign = $this->method . '&' . $base_request_uri . '&' . $query_string;
		$secret         = $this->get_secret();

		return \base64_encode( \hash_hmac( self::HASH_ALGORITHM, $string_to_sign, $secret, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Creates an array of urlencoded strings out of each array key/value pairs.
	 *
	 * @param  array  $params       Array of parameters to convert.
	 * @param  array  $query_params Array to extend.
	 * @param  string $key          Optional Array key to append.
	 *
	 * @return string[]             Array of urlencoded strings
	 */
	protected function join_with_equals_sign( $params, $query_params = array(), $key = '' ) {
		foreach ( $params as $param_key => $param_value ) {
			if ( $key ) {
				$param_key = $key . '%5B' . $param_key . '%5D'; // Handle multi-dimensional array.
			}

			if ( is_array( $param_value ) ) {
				$query_params = $this->join_with_equals_sign( $param_value, $query_params, $param_key );
			} else {
				$string         = $param_key . '=' . $param_value; // Join with equals sign.
				$query_params[] = $this->encode( $string );
			}
		}

		return $query_params;
	}

	/**
	 * Sort parameters.
	 *
	 * @param array $parameters Parameters to sort in byte-order.
	 *
	 * @return array
	 */
	protected function get_sorted_params( $parameters ) {
		\uksort( $parameters, 'strcmp' );

		foreach ( $parameters as $key => $value ) {
			if ( \is_array( $value ) ) {
				\uksort( $parameters[ $key ], 'strcmp' );
			}
		}

		return $parameters;
	}

	/**
	 * Get oAuth1.0 parameters.
	 *
	 * @return string
	 */
	public function get_parameters() {
		$parameters = \array_merge(
			$this->parameters,
			array(
				'consumer_key'           => $this->consumer_key,
				'consumer_secret'        => $this->consumer_secret,
				'oauth_timestamp'        => $this->timestamp,
				'oauth_nonce'            => \sha1( \microtime() ),
				'oauth_signature_method' => 'HMAC-' . self::HASH_ALGORITHM,
			)
		);

		// The parameters above must be included in the signature generation.
		$parameters['oauth_signature'] = $this->generate_oauth_signature( $parameters );

		return $this->get_sorted_params( $parameters );
	}
}
