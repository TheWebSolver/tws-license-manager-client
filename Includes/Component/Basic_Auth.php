<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client Basic Authentication for SSL sites.
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
 * The Web Solver Licence Manager Client Basic Authentication class.
 */
class Basic_Auth {
	/**
	 * The cURL handle.
	 *
	 * @var resource|\CurlHandle
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
	 * HTTP Client
	 *
	 * @var Http_Client
	 */
	protected $client;

	/**
	 * Initialize Basic Authentication class.
	 *
	 * @param Http_Client $client     The HTTP Client making authentication.
	 * @param array       $parameters Request parameters.
	 */
	public function __construct( $client, $parameters ) {
		$this->client          = $client;
		$this->ch              = $client->get_resource();
		$this->consumer_key    = $client->get_key();
		$this->consumer_secret = $client->get_secret();
		$this->do_query        = $client->get_option()->is_query_string_auth();
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
		// IF not a SSL site but SSL verification needed, pass error data to client.
		if ( ! $this->client->is_ssl() && $this->client->get_option()->verify_ssl() ) {
			$this->client->add_error(
				'basicauth_ssl_not_installed',
				__( 'SSL is not installed on this site for Basic Authentication.', 'tws-license-manager-client' ),
				$this->parameters
			);
		}

		return $this->parameters;
	}
}
