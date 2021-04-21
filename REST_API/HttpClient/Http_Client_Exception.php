<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client Exception.
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
 * The Web Solver Licence Manager Client Exception class.
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
