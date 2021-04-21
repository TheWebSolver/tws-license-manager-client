<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver REST API Client.
 *
 * @package  TheWebSolver\Core\REST_API
 */

namespace TheWebSolver\Core\REST_API;

use TheWebSolver\Core\REST_API\HttpClient\Http_Client;

/**
 * REST API Client class.
 */
class Client {
	/**
	 * WooCommerce REST API Client version.
	 */
	const VERSION = '1.0.0';

	/**
	 * Http_Client instance.
	 *
	 * @var Http_Client
	 */
	public $http;

	/**
	 * Initialize client.
	 *
	 * @param string $url            Store URL.
	 * @param string $consumer_key    Consumer key.
	 * @param string $consumer_secret Consumer secret.
	 * @param array  $options        Options (version, timeout, verify_ssl).
	 */
	public function __construct( $url, $consumer_key, $consumer_secret, $options = array() ) {
		$this->http = new Http_Client( $url, $consumer_key, $consumer_secret, $options );
	}

	/**
	 * POST method.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return \stdClass
	 */
	public function post( $endpoint, $data ) {
		return $this->http->request( $endpoint, 'POST', $data );
	}

	/**
	 * PUT method.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return \stdClass
	 */
	public function put( $endpoint, $data ) {
		return $this->http->request( $endpoint, 'PUT', $data );
	}

	/**
	 * GET method.
	 *
	 * @param string $endpoint   API endpoint.
	 * @param array  $parameters Request parameters.
	 *
	 * @return \stdClass
	 */
	public function get( $endpoint, $parameters = array() ) {
		return $this->http->request( $endpoint, 'GET', array(), $parameters );
	}

	/**
	 * DELETE method.
	 *
	 * @param string $endpoint   API endpoint.
	 * @param array  $parameters Request parameters.
	 *
	 * @return \stdClass
	 */
	public function delete( $endpoint, $parameters = array() ) {
		return $this->http->request( $endpoint, 'DELETE', array(), $parameters );
	}

	/**
	 * OPTIONS method.
	 *
	 * @param string $endpoint API endpoint.
	 *
	 * @return \stdClass
	 */
	public function options( $endpoint ) {
		return $this->http->request( $endpoint, 'OPTIONS', array(), array() );
	}
}
