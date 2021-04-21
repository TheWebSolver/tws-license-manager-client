<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client HTTP Client.
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

use TheWebSolver\License_Manager\REST_API\Client;

/**
 * The Web Solver Licence Manager Client HTTP Client class.
 */
class Http_Client {
	/**
	 * The cURL handle.
	 *
	 * @var resource
	 */
	protected $ch;

	/**
	 * Store API URL.
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
	 * Client options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Request.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Response.
	 *
	 * @var Response
	 */
	private $response;

	/**
	 * Response headers.
	 *
	 * @var string
	 */
	private $response_headers;

	/**
	 * Initialize HTTP client.
	 *
	 * @param string $url            Store URL.
	 * @param string $consumer_key    Consumer key.
	 * @param string $consumer_secret Consumer Secret.
	 * @param array  $options        Client options.
	 *
	 * @throws Http_Client_Exception REST API HTTP Client Exceptions.
	 */
	public function __construct( $url, $consumer_key, $consumer_secret, $options ) {
		if ( ! \function_exists( 'curl_version' ) ) {
			throw new Http_Client_Exception( 'cURL is NOT installed on this server', -1, new Request(), new Response() );
		}

		$this->options         = new Options( $options );
		$this->url             = $this->build_api_url( $url );
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	}

	/**
	 * Check if is under SSL.
	 *
	 * @return bool
	 */
	protected function is_ssl() {
		return 'https://' === \substr( $this->url, 0, 8 );
	}

	/**
	 * Build API URL.
	 *
	 * @param string $url Store URL.
	 *
	 * @return string
	 */
	protected function build_api_url( $url ) {
		$api = $this->options->is_wp_api() ? $this->options->api_prefix() : '/wc-api/';

		return \rtrim( $url, '/' ) . $api . $this->options->get_version() . '/';
	}

	/**
	 * Build URL.
	 *
	 * @param string $url        URL.
	 * @param array  $parameters Query string parameters.
	 *
	 * @return string
	 */
	protected function build_url_query( $url, $parameters = array() ) {
		if ( ! empty( $parameters ) ) {
			$url .= '?' . \http_build_query( $parameters );
		}

		return $url;
	}

	/**
	 * Authenticate.
	 *
	 * @param string $url        Request URL.
	 * @param string $method     Request method.
	 * @param array  $parameters Request parameters.
	 *
	 * @return array
	 */
	protected function authenticate( $url, $method, $parameters = array() ) {
		// Setup authentication.
		if ( $this->is_ssl() ) {
			$basic_auth = new BasicAuth(
				$this->ch,
				$this->consumer_key,
				$this->consumer_secret,
				$this->options->is_query_string_auth(),
				$parameters
			);
			$this->auth = $basic_auth;
			$parameters = $basic_auth->get_parameters();
		} else {
			$o_auth     = new OAuth(
				$url,
				$this->consumer_key,
				$this->consumer_secret,
				$this->options->get_version(),
				$method,
				$parameters,
				$this->options->oauth_timestamp()
			);
			$this->auth = $o_auth;
			$parameters = $o_auth->get_parameters();
		}

		return $parameters;
	}

	/**
	 * Setup method.
	 *
	 * @param string $method Request method.
	 */
	protected function setup_method( $method ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		if ( 'POST' === $method ) {
			\curl_setopt( $this->ch, CURLOPT_POST, true );
		} elseif ( \in_array( $method, array( 'PUT', 'DELETE', 'OPTIONS' ) ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			\curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $method );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_setopt
	}

	/**
	 * Get request headers.
	 *
	 * @param bool $send_data If request send data or not.
	 *
	 * @return array
	 */
	protected function get_request_headers( $send_data = false ) {
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => $this->options->user_agent() . '/' . Client::VERSION,
		);

		if ( $send_data ) {
			$headers['Content-Type'] = 'application/json;charset=utf-8';
		}

		return $headers;
	}

	/**
	 * Create request.
	 *
	 * @param string $endpoint   Request endpoint.
	 * @param string $method     Request method.
	 * @param array  $data       Request data.
	 * @param array  $parameters Request parameters.
	 *
	 * @return Request
	 */
	protected function create_request( $endpoint, $method, $data = array(), $parameters = array() ) {
		$body     = '';
		$url      = $this->url . $endpoint;
		$has_data = ! empty( $data );

		// Setup authentication.
		$parameters = $this->authenticate( $url, $method, $parameters );

		// Setup method.
		$this->setup_method( $method );

		// Include post fields.
		if ( $has_data ) {
			$body = \json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			\curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}

		$this->request = new Request(
			$this->build_url_query( $url, $parameters ),
			$method,
			$parameters,
			$this->get_request_headers( $has_data ),
			$body
		);

		return $this->get_request();
	}

	/**
	 * Get response headers.
	 *
	 * @return array
	 */
	protected function get_response_headers() {
		$headers = array();
		$lines   = \explode( "\n", $this->response_headers );
		$lines   = \array_filter( $lines, 'trim' );

		foreach ( $lines as $index => $line ) {
			// Remove HTTP/xxx params.
			if ( strpos( $line, ': ' ) === false ) {
				continue;
			}

			list($key, $value) = \explode( ': ', $line );

			$headers[ $key ] = isset( $headers[ $key ] ) ? $headers[ $key ] . ', ' . trim( $value ) : trim( $value );
		}

		return $headers;
	}

	/**
	 * Create response.
	 *
	 * @return Response
	 */
	protected function create_response() {

		// Set response headers.
		$this->response_headers = '';
		\curl_setopt( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			$this->ch,
			CURLOPT_HEADERFUNCTION,
			function ( $_, $headers ) {
				$this->response_headers .= $headers;
				return \strlen( $headers );
			}
		);

		// Get response data.
		$body    = \curl_exec( $this->ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code    = \curl_getinfo( $this->ch, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$headers = $this->get_response_headers();

		// Register response.
		$this->response = new Response( $code, $headers, $body );

		return $this->get_response();
	}

	/**
	 * Set default cURL settings.
	 */
	protected function set_default_curl_settings() {
		$verify_ssl       = $this->options->verify_ssl();
		$timeout          = $this->options->get_timeout();
		$follow_redirects = $this->options->get_follow_redirect();

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		\curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, false );
		if ( ! $verify_ssl ) {
			\curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl );
		}
		if ( $follow_redirects ) {
			\curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		}
		\curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		\curl_setopt( $this->ch, CURLOPT_TIMEOUT, $timeout );
		\curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $this->request->get_raw_headers() );
		\curl_setopt( $this->ch, CURLOPT_URL, $this->request->get_url() );
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_setopt
	}

	/**
	 * Look for errors in the request.
	 *
	 * @param object $parsed_response Parsed body response.
	 *
	 * @throws Http_Client_Exception REST API HTTP Client Exceptions.
	 */
	protected function look_for_errors( $parsed_response ) {
		// Any non-200/201/202 response code indicates an error.
		if ( ! \in_array( $this->response->get_code(), array( '200', '201', '202' ) ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			$errors        = isset( $parsed_response->errors ) ? $parsed_response->errors : $parsed_response;
			$error_message = '';
			$error_code    = '';

			if ( is_array( $errors ) ) {
				$error_message = $errors[0]->message;
				$error_code    = $errors[0]->code;
			} elseif ( isset( $errors->message, $errors->code ) ) {
				$error_message = $errors->message;
				$error_code    = $errors->code;
			}

			throw new Http_Client_Exception(
				\sprintf( 'Error: %s [%s]', $error_message, $error_code ),
				$this->response->get_code(),
				$this->request,
				$this->response
			);
		}
	}

	/**
	 * Process response.
	 *
	 * @return \stdClass
	 *
	 * @throws Http_Client_Exception REST API HTTP Client Exceptions.
	 */
	protected function process_response() {
		$body = $this->response->get_body();

		// Look for UTF-8 BOM and remove.
		if ( 0 === strpos( bin2hex( substr( $body, 0, 4 ) ), 'efbbbf' ) ) {
			$body = substr( $body, 3 );
		}

		$parsed_response = \json_decode( $body );

		// Test if return a valid JSON.
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$message = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'Invalid JSON returned';
			throw new Http_Client_Exception(
				sprintf( 'JSON ERROR: %s', $message ),
				$this->response->get_code(),
				$this->request,
				$this->response
			);
		}

		$this->look_for_errors( $parsed_response );

		return $parsed_response;
	}

	/**
	 * Make requests.
	 *
	 * @param string $endpoint   Request endpoint.
	 * @param string $method     Request method.
	 * @param array  $data       Request data.
	 * @param array  $parameters Request parameters.
	 *
	 * @return \stdClass
	 *
	 * @throws Http_Client_Exception REST API HTTP Client Exceptions.
	 */
	public function request( $endpoint, $method, $data = array(), $parameters = array() ) {
		// Initialize cURL.
		$this->ch = \curl_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init

		// Set request args.
		$request = $this->create_request( $endpoint, $method, $data, $parameters );

		// Default cURL settings.
		$this->set_default_curl_settings();

		// Get response.
		$response = $this->create_response();

		// Check for cURL errors.
		if ( \curl_errno( $this->ch ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno
			throw new Http_Client_Exception( 'cURL Error: ' . \curl_error( $this->ch ), 0, $request, $response ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		}

		\curl_close( $this->ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close

		return $this->process_response();
	}

	/**
	 * Get request data.
	 *
	 * @return Request
	 */
	public function get_request() {
		return $this->request;
	}

	/**
	 * Get response data.
	 *
	 * @return Response
	 */
	public function get_response() {
		return $this->response;
	}
}
