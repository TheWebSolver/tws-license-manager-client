<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver REST API Basic Authentication.
 *
 * @package  TheWebSolver\Core\REST_API
 */

namespace TheWebSolver\Core\REST_API\HttpClient;

/**
 * Basic Authentication class.
 */
class BasicAuth {
	/**
	 * The cURL handle.
	 *
	 * @var resource
	 */
	protected $ch;

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
	 * Do query string auth.
	 *
	 * @var bool
	 */
	protected $do_query;

	/**
	 * Request parameters.
	 *
	 * @var array
	 */
	protected $parameters;

	/**
	 * Initialize Basic Authentication class.
	 *
	 * @param resource $ch             cURL handle.
	 * @param string   $consumer_key    Consumer key.
	 * @param string   $consumer_secret Consumer Secret.
	 * @param bool     $do_query        Do or not query string auth.
	 * @param array    $parameters     Request parameters.
	 */
	public function __construct( $ch, $consumer_key, $consumer_secret, $do_query, $parameters = array() ) {
		$this->ch              = $ch;
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
		$this->do_query        = $do_query;
		$this->parameters      = $parameters;

		$this->process_authentication();
	}

	/**
	 * Process authentication.
	 */
	protected function process_authentication() {
		if ( $this->do_query ) {
			$this->parameters['consumer_key']    = $this->consumer_key;
			$this->parameters['consumer_secret'] = $this->consumer_secret;
		} else {
			\curl_setopt( $this->ch, CURLOPT_USERPWD, $this->consumer_key . ':' . $this->consumer_secret ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
	}

	/**
	 * Get parameters.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return $this->parameters;
	}
}
