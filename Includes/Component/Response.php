<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client Response.
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
 * The Web Solver Licence Manager Client Response class.
 */
class Response {
	/**
	 * Response code.
	 *
	 * @var int
	 */
	private $code;

	/**
	 * Response headers.
	 *
	 * @var array
	 */
	private $headers;

	/**
	 * Response body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Initialize response.
	 *
	 * @param int    $code    Response code.
	 * @param array  $headers Response headers.
	 * @param string $body    Response body.
	 */
	public function __construct( $code = 0, $headers = array(), $body = '' ) {
		$this->code    = $code;
		$this->headers = $headers;
		$this->body    = $body;
	}

	/**
	 * Set code.
	 *
	 * @param int $code Response code.
	 */
	public function set_code( $code ) {
		$this->code = (int) $code;
	}

	/**
	 * Set headers.
	 *
	 * @param array $headers Response headers.
	 */
	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Set body.
	 *
	 * @param string $body Response body.
	 */
	public function set_body( $body ) {
		$this->body = $body;
	}

	/**
	 * Get code.
	 *
	 * @return int
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Get headers.
	 *
	 * @return array $headers Response headers.
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Get body.
	 *
	 * @return string $body Response body.
	 */
	public function get_body() {
		return $this->body;
	}
}
