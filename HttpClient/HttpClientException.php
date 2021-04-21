<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver REST API HTTP Client Exception.
 *
 * @package  TheWebSolver\Core\REST_API
 */

namespace TheWebSolver\Core\REST_API\HttpClient;

/**
 * REST API HTTP Client Exception class.
 */
class Http_Client_Exception extends \Exception {
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
	 * Initialize exception.
	 *
	 * @param string   $message  Error message.
	 * @param int      $code     Error code.
	 * @param Request  $request  Request data.
	 * @param Response $response Response data.
	 */
	public function __construct( $message, $code, Request $request, Response $response ) {
		parent::__construct( $message, $code );

		$this->request  = $request;
		$this->response = $response;
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
