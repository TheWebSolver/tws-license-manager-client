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

namespace TheWebSolver\License_Manager\Component;

use TheWebSolver\License_Manager\API\Manager;

/**
 * The Web Solver Licence Manager Client HTTP Client class.
 */
class Http_Client {
	/**
	 * The cURL handle.
	 *
	 * @var resource|\CurlHandle
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
	 * Error handler.
	 *
	 * @var \WP_Error
	 */
	private $error;

		/**
		 * API endpoint.
		 *
		 * @var string
		 */
	private $endpoint;

	/**
	 * Request method.
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Additional request headers.
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Any other errors.
	 *
	 * @var \WP_Error
	 */
	public $other_errors;

	/**
	 * Initializes HTTP client.
	 *
	 * @param string $url             Store URL.
	 * @param string $consumer_key    Consumer key.
	 * @param string $consumer_secret Consumer Secret.
	 * @param array  $options         Client options.
	 */
	public function __construct( $url, $consumer_key, $consumer_secret, $options ) {
		$this->error        = new \WP_Error();
		$this->other_errors = new \WP_Error();
		$this->options      = new Options( $options );

		// Stop further execution if REST API namespace not defined.
		if ( \is_wp_error( $this->options->namespace() ) ) {
			$this->error = $this->options->namespace();
			return;
		}

		$this->url             = $this->build_api_url( $url );
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;

		if ( ! \function_exists( 'curl_version' ) ) {
			$this->add_error(
				'cURL_not_installed',
				__( 'PHP cURL extension is not installed on this server. Please install it for license activation/deactivation.', 'tws-license-manager-client' )
			);
		}
	}

	/**
	 * Sets error data.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Optional. Error data.
	 */
	public function add_error( $code, $message, $data = '' ) {
		$this->error->add( $code, $message, $data );
	}

	/**
	 * Checks if site has SSL installed.
	 *
	 * @return bool
	 */
	public function is_ssl() {
		return 'https://' === \substr( $this->url, 0, 8 );
	}

	/**
	 * Builds REST API URL.
	 *
	 * @param string $url Store URL.
	 *
	 * @return string
	 */
	protected function build_api_url( $url ) {
		$api       = $this->options->api_prefix(); // must have leading and trailing slashes.
		$namespace = $this->options->namespace();
		$ver       = $this->options->get_version();

		return "{$url}{$api}{$namespace}/{$ver}/";
	}

	/**
	 * Builds remote URL for querying with parameters.
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
	 * Sets authentication method based on site's SSL installed status.
	 *
	 * @param array $parameters Request parameters.
	 *
	 * @return array
	 */
	protected function authenticate( $parameters = array() ) {
		$auth = $this->is_ssl()
			? new Basic_Auth( $this, $parameters )
			: new OAuth( $this, $parameters );

		return $auth->get_parameters();
	}

	/**
	 * Sets request method.
	 *
	 * @param string $method Request method.
	 */
	protected function setup_method( $method ) {
		$this->method = $method;

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		if ( 'POST' === $method ) {
			\curl_setopt( $this->ch, CURLOPT_POST, true );
		} elseif ( \in_array( $method, array( 'PUT', 'DELETE', 'OPTIONS' ), true ) ) {
			\curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $method );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_setopt
	}

	/**
	 * Creates request headers.
	 *
	 * @param bool  $send_data If request send data or not. Usually for `POST` method.
	 * @param array $args      Additional HTTP request headers.
	 *
	 * @return array
	 *
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers
	 */
	protected function create_request_headers( $send_data = false, $args = array() ) {
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => $this->options->user_agent() . '/' . Manager::VERSION,
			'Referer'    => get_site_url( get_current_blog_id() ),
		);

		if ( ! empty( $args ) ) {
			$headers = array_merge( $headers, $args );
		}

		if ( $send_data ) {
			$headers['Content-Type'] = 'application/json;charset=utf-8';
		}

		return $headers;
	}

	/**
	 * Creates request data for the remote server.
	 *
	 * @param string $endpoint   Request endpoint.
	 * @param string $method     Request method.
	 * @param array  $data       Request data. Usually for `POST` method.
	 * @param array  $parameters Request parameters.
	 *
	 * @return Request
	 */
	protected function create_request( $endpoint, $method, $data = array(), $parameters = array() ) {
		$body     = '';
		$url      = $this->url . $endpoint;
		$has_data = ! empty( $data );

		$this->setup_method( $method );

		$this->endpoint = $endpoint;
		$parameters     = $this->authenticate( $parameters );

		// Include post fields.
		if ( $has_data ) {
			$body = \wp_json_encode( $data );
			\curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}

		$this->request = new Request(
			$this->build_url_query( $url, $parameters ),
			$method,
			$parameters,
			$this->create_request_headers( $has_data, $this->headers ),
			$body
		);

		return $this->request;
	}

	/**
	 * Converts remote server response headers to an array.
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
	 * Creates response.
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
	 * Sets default cURL settings.
	 */
	protected function process_request() {
		$verify_ssl       = $this->options->verify_ssl();
		$verify_peer      = $this->is_ssl() ? 1 : false;
		$timeout          = $this->options->get_timeout();
		$follow_redirects = $this->options->get_follow_redirect();

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		\curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, $verify_peer );
		\curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl );
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
	 * Validates response data.
	 *
	 * @param object $parsed_response Parsed body response.
	 *
	 * @return object
	 */
	protected function validate_response( $parsed_response ) {
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

			$data = array(
				'request'        => $this->request,
				'response'       => $this->response,
				'response_error' => $errors,
			);

			$this->add_error( $error_code, $error_message, $data );
		}

		return $parsed_response;
	}

	/**
	 * Process response.
	 *
	 * @return \stdClass|\WP_Error Response as an object if everything valid, WP_Error otherwise.
	 */
	protected function process_response() {
		$body = $this->response->get_body();

		// Look for UTF-8 BOM and remove.
		if ( 0 === strpos( bin2hex( substr( $body, 0, 4 ) ), 'efbbbf' ) ) {
			$body = substr( $body, 3 );
		}

		$parsed_response = \json_decode( $body );

		// Test if return a valid JSON.
		if ( JSON_ERROR_NONE !== \json_last_error() ) {
			$message = \function_exists( 'json_last_error_msg' ) ? \json_last_error_msg() : __( 'Response is an invalid JSON.', 'tws-license-manager-client' );
			$this->add_error(
				'invalid_json_response',
				sprintf( 'JSON Error: %s', $message ),
				array(
					'request'  => $this->request,
					'response' => $this->response,
				)
			);
		}

		$parsed_response = $this->validate_response( $parsed_response );

		return $this->has_error() ? $this->get_error() : $parsed_response;
	}

	/**
	 * Makes remote server request.
	 *
	 * @param string $endpoint   Request endpoint.
	 * @param string $method     Request method.
	 * @param array  $data       Request data.
	 * @param array  $parameters Request parameters. Includes form validation params.
	 * @param array  $headers    Additional HTTP request headers.
	 *
	 * @return \stdClass|\WP_Error Response as an object if everything valid, WP_Error otherwise.
	 */
	public function request( $endpoint, $method, $data = array(), $parameters = array(), $headers = array() ) {
		$this->ch      = \curl_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		$this->headers = $headers;
		$request       = $this->create_request( $endpoint, $method, $data, $parameters );

		// If request triggered error, don't proceed any further.
		if ( $this->has_error() ) {
			return $this->get_error();
		}

		$this->process_request();

		$response = $this->create_response();
		$err_code = \curl_errno( $this->ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno

		// Check for cURL errors.
		if ( 0 < $err_code ) {
			$this->other_errors->add(
				'cURL_error',
				sprintf(
					'%1$s. cURL Error Number: [%2$s]. cURL Error Message: [%3$s]',
					__( 'An error occured when parsing response.', 'tws-license-manager-client' ),
					$err_code,
					\curl_error( $this->ch ) // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
				),
				array(
					'request'  => $request,
					'response' => $response,
				)
			);
		}

		\curl_close( $this->ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close

		return $this->process_response();
	}

	/**
	 * Gets cURL resource.
	 *
	 * @return resource
	 */
	public function get_resource() {
		return $this->ch;
	}

	/**
	 * Gets consumer key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->consumer_key;
	}

	/**
	 * Gets consumer secret.
	 *
	 * @return string
	 */
	public function get_secret() {
		return $this->consumer_secret;
	}

	/**
	 * Gets options.
	 *
	 * @return Options
	 */
	public function get_option() {
		return $this->options;
	}

		/**
		 * API url.
		 *
		 * @return string
		 */
	public function get_url() {
		return $this->url;
	}
	/**
	 * Gets API endpoint.
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * Gets request method.
	 *
	 * @return string
	 */
	public function get_method() {
		return $this->method;
	}

	/**
	 * Gets request data.
	 *
	 * @return Request
	 */
	public function get_request() {
		return $this->request;
	}

	/**
	 * Gets response data.
	 *
	 * @return Response
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Gets error.
	 *
	 * @return \WP_Error
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Checks if error has occured.
	 *
	 * @return bool True if error found, false otherwise.
	 */
	public function has_error() {
		return ! empty( $this->get_error()->errors );
	}
}
