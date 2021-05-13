<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client Manager.
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

namespace TheWebSolver\License_Manager\REST_API;

use TheWebSolver\License_Manager\REST_API\HttpClient\Http_Client;

/**
 * The Web Solver Licence Manager Client Manager class.
 */
class Manager {
	/**
	 * WooCommerce REST API Client Manager version.
	 */
	const VERSION = '1.0.0';

	/**
	 * API Consumer Key.
	 *
	 * @var string
	 */
	private $consumer_key = '';

	/**
	 * API Consumer Secret.
	 *
	 * @var string
	 */
	private $consumer_secret = '';

	/**
	 * Data to be validated for API request.
	 *
	 * @var array
	 */
	private $to_validate = array();

	/**
	 * Data that is validated for API request.
	 *
	 * @var array
	 */
	private $validated = array();

	/**
	 * Endpoints for REST API. Defaults to `licenses`.
	 *
	 * @var string
	 */
	private $endpoint = 'licenses';

	/**
	 * Product license key.
	 *
	 * It can also be generator ID in debug mode.
	 *
	 * @var string|false
	 */
	private $license = '';

	/**
	 * WooCommerce Product Name set on client site for this plugin.
	 *
	 * @var string
	 */
	public $product_slug = '';

	/**
	 * Method for calling REST API. Defaults to `GET`.
	 *
	 * @var string
	 */
	private $method = 'GET';

	/**
	 * REST API route.
	 *
	 * @var string
	 */
	private $route;

	/**
	 * The client connector instance.
	 *
	 * @var Http_Client
	 */
	public $client;

	/**
	 * The client plugin directory name.
	 *
	 * @var string
	 */
	private $dirname;

	/**
	 * The client plugin main file name.
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Parent slug for activation menu.
	 *
	 * @var string
	 */
	private $parent_slug;

	/**
	 * Option key.
	 *
	 * @var string
	 */
	public $option;

	/**
	 * Submenu hook suffix. Will only be set if parent slug is passed.
	 *
	 * @var string
	 */
	public $hook_suffix;

	/**
	 * Request query parameters for validation on server.
	 *
	 * @var array
	 */
	private $parameters = array();

	/**
	 * Form validation errors.
	 *
	 * @var string[]
	 */
	private $errors = array();

	/**
	 * The license page slug.
	 *
	 * @var string
	 */
	public $page_slug;

	/**
	 * Which step the page content to be shown.
	 *
	 * @var step
	 */
	public $step = '';

	/**
	 * Whether plugin is in debug mode.
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * License form.
	 *
	 * Whether form is `activate` or `deactivate` form.
	 *
	 * @var string
	 */
	private $form_state;

	/**
	 * Additional request headers.
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Unique site identifier.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Server response.
	 *
	 * @var \stdClass|\WP_Error
	 */
	private $response;

	/**
	 * Whether to disable form after activation/deactivation.
	 *
	 * @var bool
	 */
	private $disable_form = true;

	/**
	 * Sets client plugin directory, main file name and parent menu to hook license form submenu page.
	 *
	 * @param string $dirname   Required. The client plugin directory name. This will be used as prefixer.
	 * @param string $filename  Optional. If same as dirname, no need to pass it. The client plugin main file name (with extension `.php`).
	 * @param string $parent_slug Optional. Parent menu slug to display activation form.
	 *                            Form must be called elsewhere in plugin if parent slug not given.
	 *                            {@see @method `Manager::generate_form()`}.
	 */
	public function __construct( $dirname, $filename = '', $parent_slug = '' ) {
		// Set properties.
		$this->dirname     = $dirname;
		$this->filename    = '' === $filename ? $dirname . '.php' : $filename;
		$this->parent_slug = $parent_slug;
		$this->option      = $dirname . '-license-data';
		$this->page_slug   = 'tws-activate-' . $dirname;
		$this->key         = 'data-' . $this->parse_url( get_site_url( get_current_blog_id() ) );
		$this->debug       = defined( 'TWS_LICENSE_MANAGER_CLIENT_DEBUG' ) && TWS_LICENSE_MANAGER_CLIENT_DEBUG;

		if ( '' !== $parent_slug && is_string( $parent_slug ) ) {
			add_action( 'admin_menu', array( $this, 'add_license_page' ), 999 );
		}

		add_action( 'admin_init', array( $this, 'start' ), 9 );
	}

	/**
	 * Sets API keys for client.
	 *
	 * @param string $key    API Consumer Key.
	 * @param string $secret API Consumer Secret.
	 *
	 * @return Manager
	 */
	public function set_keys( $key, $secret ) {
		$this->consumer_key    = $key;
		$this->consumer_secret = $secret;

		return $this;
	}

	/**
	 * Sets API URL parameters.
	 *
	 * @param array $parameters API URL parameters.
	 */
	public function set_parameters( $parameters ) {
		$this->parameters = $parameters;
	}

	/**
	 * Sets API additional validation data.
	 *
	 * @param array $data The data can be of following types with their respective error message **(required)**.
	 * * @type `string` `license_key` The generated license key for this plugin.
	 * * @type `string` `email`       The customer email address (email that is used to purchase this plugin).
	 * * @type `int`    `order_id`    The order ID (order ID for which license is generated).
	 * * @type `string` `name`        The product name (this plugin woocommerce product title on server site).
	 *
	 * @return Manager
	 *
	 * @example usage
	 * ```
	 * // Check license key, email address, order_id, purchased product/plugin name for validation and their error message for client side.
	 * Manager::set_validation(
	 *  array(
	 *   // End user must input license key in form field. Validation on client site also if field is blank.
	 *   'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
	 *
	 *   // Email field will be generated and user must input email registered on server site and same was used to purchase the plugin. Validation on client site also if field is blank.
	 *   'email'       => __( 'Enter valid/same email address used at the time of purchase.', 'tws-license-manager-client' ),
	 *
	 *   // Order ID field will be generated and user must input the Order ID for which license key was generated. Validation on client site also if field is blank.
	 *   'order_id'    => __( 'Enter same/valid purchase order ID.', 'tws-license-manager-client' ),
	 *
	 *   // Hidden input field will be generated. The WooCommerce product name set for this plugin on server site that matches with the published product title. This will be used for validation on server site.
	 *   'slug'        => 'Sold Plugin Slug on server',
	 *  )
	 * );
	 * ```
	 */
	public function set_validation( $data = array() ) {
		$this->to_validate = $data;

		// Set the plugin/product slug on server if used for validation.
		if ( isset( $data['slug'] ) ) {
			$this->product_slug = $data['slug'];
		}

		return $this;
	}

	/**
	 * Initializes connection between client and server.
	 *
	 * @param string $server_url The Remote Server URL where licenses are saved.
	 * @param array  $options    Optional. Additional options for connection. (version, timeout, verify_ssl).
	 *
	 * @return Manager
	 */
	public function connect_with( $server_url, $options = array() ) {
		$defaults = array(
			'namespace' => 'lmfwc',
			'version'   => 'v2',
		);

		$options      = array_merge( $defaults, $options );
		$this->client = new Http_Client(
			$server_url,
			$this->consumer_key,
			$this->consumer_secret,
			$options
		);

		return $this;
	}

	/**
	 * Disables form after activation/deactivation complete.
	 *
	 * @param bool $disable True to disable form after success, false otherwise.
	 *
	 * @return Manager
	 */
	public function disable_form( $disable = true ) {
		$this->disable_form = $disable;

		return $this;
	}

	/**
	 * Sets license key/generator ID for debugging.
	 *
	 * @param string $value The license key or generator ID.
	 *
	 * @return Manager
	 */
	public function set_key_or_id( $value ) {
		$this->license = $value;

		return $this;
	}

	/**
	 * Starts page and makes server request and get response back.
	 *
	 * Save the response to database.
	 */
	public function start() {
		// Bail if not license page.
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
			return;
		}

		// Lets prettify the license form.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';

		// Bail if license form not submitted yet.
		if ( ! isset( $_POST['validate_license'] ) || 'validate_license' !== $_POST['validate_license'] ) {
			return;
		}
		// phpcs:enable

		// API Namespace is a must. if it is not given, then error is triggered. Stop processing further.
		if ( $this->client->has_error() ) {
			$this->response = $this->client->get_error();

			return;
		}

		// No proper form input data, request => invalid.
		// Internally it checks debug mode. Debug mode on, request => valid.
		if ( ! $this->has_valid_form_data() ) {
			return;
		}

		$this->response = $this->process_license_form();

		// Save the response to database if valid response.
		if (
			! is_wp_error( $this->response ) &&
			is_object( $this->response ) &&
			isset( $this->response->success ) &&
			$this->response->success &&
			! $this->debug
		) {
			$value = (object) array(
				'key'          => $this->response->data->key,
				'email'        => $this->response->data->email,
				'status'       => $this->response->data->state,
				'order_id'     => $this->response->data->orderId,
				'valid_for'    => $this->response->data->validFor,
				'expires_at'   => $this->response->data->expiresAt,
				'total_count'  => $this->response->data->timesActivatedMax,
				'license_key'  => $this->response->data->licenseKey,
				'purchased_on' => $this->response->data->createdAt,
			);

			update_option( $this->option, maybe_serialize( $value ) );
		}
	}

	/**
	 * Sets REST API call method.
	 *
	 * @param string $method The REST API method call type.
	 *
	 * @return Manager
	 */
	public function set_method( $method ) {
		$this->method = $method;

		return $this;
	}

	/**
	 * Gets product license data.
	 *
	 * @param string $data The license data to retrive. Possible values are:
	 * * `key`          - The key with which metdata is saved in server. Unique to each site
	 * * `email`        - The user email address registered on server
	 * * `status`       - The license current active/inactive status
	 * * `order_id`     - The WooCommerce order ID for which license is generated on server
	 * * `valid_for`    - Number of days the license is valid for
	 * * `expires_at`   - The license expiration date
	 * * `total_count`  - Total number of times license key can be activated
	 * * `license_key`  - The product license key
	 * * `purchased_on` - The license key created date and time.
	 *
	 * @return string|\stdClass
	 */
	public function get_license( $data = '' ) {
		$license = maybe_unserialize( get_option( $this->option, '' ) );

		if ( ! $data ) {
			return $license;
		}

		return is_object( $license ) && property_exists( $license, $data ) ? $license->{$data} : '';
	}

	/**
	 * Shows admin notices after license activation/deactivation.
	 *
	 * @param bool $show_count Whether to show remaining count if greater than 1.
	 */
	public function show_notice( $show_count = true ) {
		// Nothing to notify if not a valid response or notice already sent.
		if ( ! is_object( $this->response ) ) {
			return;
		}

		$type = 'error';
		$msg  = __( 'Something went wrong. Please contact plugin support.', 'tws-license-manager-client' );

		if ( is_wp_error( $this->response ) ) {
			$msg = $this->response->get_error_message();
		}

		if ( isset( $this->response->data->state ) ) {
			$type   = 'success';
			$status = strtoupper( $this->response->data->state );
			$key    = $this->response->data->licenseKey;
			$max    = $this->response->data->timesActivatedMax;

			// Presistent message.
			/* Translators: %1$s - License Key, %2$s - Current license status. */
			$now = sprintf( __( '%1$s - License Key is now %2$s', 'tws-license-manager-client' ), "<b>{$key}</b>", "<b>{$status}</b>" );

			$count_msg = '';

			// More than 1 activation possible and show count is true, show remaining notice.
			if ( 1 < $max && $show_count ) {
				$remain = $max - $this->response->data->timesActivated;

				/* Translators: %1$s - remaining activation count, %2$s - total activation count. */
				$count_msg = sprintf( __( '%1$s out of %2$s activation remaining for this license', 'tws-license-manager-client' ), "<b>{$remain}</b>", "<b>{$max}</b>" );
			}

			$msg = sprintf( '%1$s. %2$s.', $now, $count_msg );
		}

		echo '<div class="is-dismissible notice notice-' . esc_attr( $type ) . '">' . wp_kses_post( $msg ) . '</div>';
	}

	/**
	 * Gets request parameters.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return $this->parameters;
	}

	/**
	 * Gets validated data from license form.
	 *
	 * @return array
	 */
	public function get_validated_data() {
		return $this->validated;
	}

	/**
	 * Gets the manager prefix.
	 *
	 * This will be used for hooks, form fields, saving options, etc.
	 *
	 * @return string
	 */
	public function get_prefix() {
		return $this->dirname;
	}

	/**
	 * Gets form validation errors.
	 *
	 * Errors are form fields without any data.
	 *
	 * @return string[]
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Checks if form has any error.
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->get_errors() );
	}

	/**
	 * Gets server response.
	 *
	 * @return \stdClass|\WP_Error Response as an object, WP_Error if anything goes wrong.
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Make remote request.
	 *
	 * @param bool   $license     The endpoint after version. ***true*** for `licenses`, ***false*** for `generators`.
	 *                            The available endpoints are (without version).
	 *                            - `GET`  - _licenses_
	 *                            - `GET`  - _licenses/{license_key}_
	 *                            - `POST` - _licenses_
	 *                            - `PUT`  - _licenses/{license_key}_
	 *                            - `GET`  - _licenses/activate/{license_key}_
	 *                            - `GET`  - _licenses/deactivate/{license_key}_
	 *                            - `GET`  - _licenses/validate/{license_key}_
	 *                            - `GET`  - _generators_
	 *                            - `GET`  - _generators/{id}_
	 *                            - `POST` - _generators_
	 *                            - `GET`  - _generators/{id}_
	 *                            Above methods can be enabled/disabled on server site setting.
	 * @param string $method      The request method.
	 *                            Possible values: `GET`, `POST`, `PUT`.
	 * @param array  $insert_data The data for `PUT` or `POST` method.
	 *
	 * @return \stdClass|\WP_Error
	 */
	public function make_request_with( $license = true, $method = 'GET', $insert_data = array() ) {
		if ( ! $this->debug ) {
			return new \WP_Error(
				'debug_mode_disabled',
				sprintf(
					/* Translators: %s - Method name. */
					__( '%s can only be used when debug mode is on. Enable it first in wp-config.php.', 'tws-license-manager-client' ),
					'<b>' . __METHOD__ . '</b>'
				)
			);
		}

		// Prepare vars.
		$endpoint  = $license ? 'licenses' : 'generators';
		$namespace = (string) $this->client->get_option()->namespace();
		$version   = $this->client->get_option()->get_version();

		// Prepare license key and endpoint.
		if ( is_string( $this->license ) && 0 < strlen( $this->license ) ) {
			$endpoint = "{$endpoint}/{$this->license}";
		}

		// Prepare no data error.
		if ( empty( $insert_data ) ) {
			$insert_data = array(
				'insert_error_code' => 'no_insert_data_found',
				'insert_error_msg'  => __( 'Insert data was not found for making request.', 'tws-license-manager-client' ),
			);
		}

		// Prepare data to be inserted as per request method.
		switch ( $method ) {
			case 'PUT':
			case 'POST':
				$data = $insert_data;
				break;
			case 'GET':
				$data = array();
				break;
			default:
				$data = array();
		}

		// PUT or POST request without any data, request => invalid.
		if ( isset( $data['insert_error_code'] ) ) {
			$this->client->add_error( $data['insert_error_code'], $data['insert_error_msg'] );
		}

		// Set properties.
		$this->endpoint = $endpoint;
		$this->method   = $method;
		$this->route    = "/{$namespace}/{$version}/{$this->endpoint}";

		// Get response from server with given method.
		return $this->client->request( $this->endpoint, $this->method, $data, $this->parameters, $this->headers );
	}

	/**
	 * Validates Existing License on server.
	 *
	 * @param array $parameters The request parameters.
	 *
	 * @return \stdClass|\WP_Error
	 */
	public function validate_license( array $parameters ) {
		$license = $this->get_license();

		if ( ! isset( $license->key ) || ! isset( $license->email ) || ! isset( $license->purchased_on ) ) {
			return new \WP_Error(
				'license_data_invalid',
				__( 'Validation failed due to invalid or no license data.', 'tws-license-manager-client' )
			);
		}

		$headers = array(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'Authorization' => 'TWS ' . base64_encode( "{$license->key}:{$license->purchased_on}" ),
			'From'          => $license->email,
			'Referer'       => get_bloginfo( 'url' ),
		);

		return $this->client->request( 'licenses/validate/' . $license->license_key, 'GET', array(), $parameters, $headers );
	}

	/**
	 * Activates/deactivates the license key.
	 *
	 * This will automatically handle whether activation or deactivation request is being made.
	 *
	 * -----------------
	 * Following updates happen on server:
	 * * ***timesActivated*** will increase/decrease by the count of `1`.
	 * * ***status*** will be changed from `ACTIVE (denoted by "3")` to `DEACTIVE (denoted by "4")` & vice-vera.
	 * -----------------
	 *
	 * @return \stdClass|\WP_Error
	 */
	public function process_license_form() {
		$this->prepare_request();

		// Get response from server with "GET" method.
		return $this->client->request( $this->endpoint, 'GET', array(), $this->parameters, $this->headers );
	}

	/**
	 * Prepare request data to pass for getting response.
	 */
	private function prepare_request() {
		// Prevent sending request if is in debug mode.
		if ( $this->debug ) {
			$this->client->add_error(
				'debug_mode_not_allowed',
				__( 'Using license form is not allowed in debug mode.', 'tws-license-manager-client' )
			);

			return;
		}

		if ( $this->key === $this->get_license( 'key' ) ) {
			// No further processing if license is already active for this site.
			if ( 'deactivate' !== $this->step && 'active' === $this->get_license( 'status' ) ) {
				$this->client->add_error(
					'license_form_invalid_request',
					__( 'Oops! The license for this site has already been activated.', 'tws-license-manager-client' ),
					array( 'status' => 400 )
				);

				return;
			}

			// No further processing if license has never been activated when attempting to deactivate.
			if ( 'deactivate' === $this->step && 'active' !== $this->get_license( 'status' ) ) {
				$this->client->add_error(
					'license_form_invalid_request',
					__( 'Oops! The license for this site is not active yet. Activate your license first.', 'tws-license-manager-client' ),
					array( 'status' => 400 )
				);

				return;
			}
		}

		// Prepare validation.
		$license    = is_string( $this->license ) && 0 < strlen( $this->license );
		$base       = "licenses/{$this->form_state}/";
		$parameters = $this->parameters;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$this->headers['Authorization'] = 'TWS ' . base64_encode( $this->validated['authorize'] );

		// Clear form authorization token from sending as request query.
		unset( $this->validated['authorize'] );

		if ( isset( $this->validated['email'] ) ) {
			$this->headers['From'] = $this->validated['email'];

			// Clear form email address from sending as request query.
			unset( $this->validated['email'] );
		}

		// A required validation parameter so only form fields can be a valid server request.
		$validation       = ! $this->has_errors() ? $this->validated : array();
		$this->parameters = array_merge( $parameters, $validation );

		// Prepare final endpoint.
		if ( ! $license ) {
			// No license key is an error if not on a debug mode.
			if ( ! $this->debug ) {
				$this->client->add_error(
					'license_form_invalid_request',
					__( 'The License key was invalid or no license key was given.', 'tws-license-manager-client' ),
					array( 'status' => 400 )
				);
			}
		} else {
			$base = $base . $this->license;
		}

		// Prepare endpoint with or without license key.
		$this->endpoint = $base;

		// Prepare route.
		$namespace   = $this->client->get_option()->namespace();
		$version     = $this->client->get_option()->get_version();
		$this->route = "/{$namespace}/{$version}/{$this->endpoint}/";
	}

	/**
	 * Generates license page form by step name.
	 */
	public function generate_form() {
		if ( empty( $this->step ) ) {
			call_user_func( array( $this, 'show_license_form' ) );
		} elseif ( 'deactivate' === $this->step ) {
			call_user_func( array( $this, 'show_license_form' ), false );
		}
	}

	/**
	 * Generates form for displaying on client site.
	 *
	 * @param bool $to_activate License form to be shown on activation or deactivation page.
	 */
	protected function show_license_form( $to_activate = true ) {
		// Form states.
		$query      = array();
		$deactivate = false;
		$status     = (string) $this->get_license( 'status' );
		$value      = $status ? $status : __( 'Not Activated', 'tws-license-manager-client' );

		// Set form state for activating or deactivating license.
		if ( $to_activate ) {
			$btn_class  = ' hz_lmac_btn';
			$button     = __( 'Activate', 'tws-license-manager-client' );
			$disabled   = 'active' === $status && $this->disable_form ? ' disabled=disabled' : '';
			$deactivate = 'active' === $status ? true : $deactivate;
			$query      = array( 'step' => 'deactivate' );
		} else {
			$btn_class = ' hz_lmdac_btn';
			$button    = __( 'Deactivate', 'tws-license-manager-client' );
			$disabled  = 'inactive' === $status && $this->disable_form ? ' disabled=disabled' : '';
		}
		?>

		<div id="hz_license_form">
			<div class="hz_license_form_head">
				<div id="hz_license_branding">
					<div id="logo"><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>/Assets/logo.png"></div>
					<h2 id="tagline"><?php esc_html_e( 'The Web Solver License Manager Client', 'tws-license-manager-client' ); ?></h2>
				</div>
				<div id="hz_license_status">
					<span class="label"><?php esc_html_e( 'License Status', 'tws-license-manager-client' ); ?></span>
					<span class="value <?php echo 'active' === $status ? 'active' : 'inactive'; ?>"><?php echo esc_html( $value ); ?></span>
				</div>
			</div>
			<div class="hz_license_form_content">
				<form method="POST">
					<?php
						/**
						 * WPHOOK: Action -> fires before default fields.
						 *
						 * @param Manager $this The current manager object instance.
						 */
						do_action( $this->dirname . '_before_license_form', $this );
					?>

					<?php
					// phpcs:disable
					$error       = $email_error = $order_error = '';
					$class       = $email_class = $order_class = '';
					$license_key = isset( $_POST[ $this->dirname ]['license_key'] ) ? $_POST[ $this->dirname ]['license_key'] : $this->get_license( 'license_key' );
					// phpcs:enable

					if ( $this->has_errors() && isset( $this->errors['license_key'] ) ) {
						$error = $this->errors['license_key'];
						$class = 'field_error';
					}
					?>

					<fieldset id="hz_activate_plugin"<?php echo esc_attr( $disabled ); ?>>
						<div class="hz_license_key <?php echo esc_attr( $class ); ?>">
							<label for="<?php echo esc_attr( $this->dirname ); ?>[license_key]">
								<p class="label"><?php esc_html_e( 'License Key', 'tws-license-manager-client' ); ?></p>
								<p class="field"><input id="<?php echo esc_attr( $this->dirname ); ?>[license_key]" name="<?php echo esc_attr( $this->dirname ); ?>[license_key]" type="text" value="<?php echo esc_attr( $license_key ); ?>"></p>
							</label>

							<?php if ( $error ) : ?>
								<p class="error"><?php echo wp_kses_post( $error ); ?></p>
							<?php endif; ?>
						</div>
						<?php
						if ( array_key_exists( 'email', $this->to_validate ) ) :
							$for   = $this->dirname . '[email]';
							$email = isset( $_POST[ $this->dirname ]['email'] ) ? $_POST[ $this->dirname ]['email'] : $this->get_license( 'email' ); // phpcs:ignore

							if ( $this->has_errors() && isset( $this->errors['email'] ) ) :
								$email_error = $this->errors['email'];
								$email_class = 'field_error';
							endif;
							?>
							<div class="hz_license_email <?php echo esc_attr( $email_class ); ?>">
								<label for="<?php echo esc_attr( $for ); ?>">
									<p class="label"><?php esc_html_e( 'Email used for purchase', 'tws-license-manager-client' ); ?></p>
									<p class="field"><input id="<?php echo esc_attr( $for ); ?>" name="<?php echo esc_attr( $for ); ?>" type="text" value="<?php echo esc_attr( $email ); ?>"></p>
								</label>

								<?php if ( $email_error ) : ?>
									<p class="error"><?php echo wp_kses_post( $email_error ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php
						if ( array_key_exists( 'order_id', $this->to_validate ) ) :
							$for      = $this->dirname . '[order_id]';
							$order_id = isset( $_POST[ $this->dirname ]['order_id'] ) ? $_POST[ $this->dirname ]['order_id'] : $this->get_license( 'order_id' ); // phpcs:ignore

							if ( $this->has_errors() && isset( $this->errors['order_id'] ) ) :
								$order_error = $this->errors['order_id'];
								$order_class = 'field_error';
							endif;
							?>
							<div class="hz_license_order_id <?php echo esc_attr( $order_class ); ?>">
								<label for="<?php echo esc_attr( $for ); ?>">
									<p class="label"><?php esc_html_e( 'Purchase Order ID', 'tws-license-manager-client' ); ?></p>
									<p class="field"><input id="<?php echo esc_attr( $for ); ?>" name="<?php echo esc_attr( $for ); ?>" type="text" value="<?php echo esc_attr( $order_id ); ?>"></p>
								</label>

								<?php if ( $order_error ) : ?>
									<p class="error"><?php echo wp_kses_post( $order_error ); ?></p>
								<?php endif; ?>
							</div>
							<?php endif; ?>
							<?php if ( is_string( $this->product_slug ) && 0 < strlen( $this->product_slug ) ) : ?>
							<input type="hidden" id="<?php echo esc_attr( $this->dirname ); ?>[slug]" name="<?php echo esc_attr( $this->dirname ); ?>[slug]" type="text" value="<?php echo esc_attr( $this->product_slug ); ?>">
							<?php endif; ?>
					</fieldset>

					<?php
					/**
					 * WPHOOK: Action -> fires after default fields.
					 *
					 * @param Manager $this The current manager object instance.
					 */
					do_action( $this->dirname . '_after_license_form', $this );
					?>
					<div id="hz_license_actions">
						<fieldset class="hz_license_links">
							<a class="dashboard" href="<?php echo esc_url_raw( $this->build_query_url( admin_url(), array( 'referrer' => $this->page_slug ) ) ); ?>">← <?php esc_html_e( 'Dashboard', 'tws-license-manager-client' ); ?></a>
							<?php
							if ( ! $to_activate ) :
								if ( 'inactive' === $status ) {
									$class = 'activate';
									$text  = __( 'Activate', 'tws-license-manager-client' );
								} else {
									$class = 'cancel';
									$text  = __( 'Cancel', 'tws-license-manager-client' );
								}
								?>
								<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug ) ); ?>"><?php echo esc_html( $text ); ?></a>
							<?php endif; ?>
							<?php if ( $deactivate ) : ?>
								<a class="deactivate" href="<?php echo esc_url_raw( $this->build_query_url( false, $query ) ); ?>" class="button button-large button-next hz_btn__skip hz_btn__nav"><?php esc_html_e( 'Deactivate', 'tws-license-manager-client' ); ?></a>
							<?php endif; ?>
						</fieldset>
						<fieldset class="hz_license_button"<?php echo esc_attr( $disabled ); ?>>
							<input type="submit" class="hz_btn__prim<?php echo esc_attr( $btn_class ); ?>" value="<?php echo esc_html( $button ); ?>" />
						</fieldset>
					</div>
					<fieldset class="license_validation">
						<?php
						/**
						 * Without this hidden input field, save function call will not trigger.
						 *
						 * {@see @method Wizard::start()}
						 */
						?>
						<input type="hidden" name="validate_license" value="validate_license">
						<input type="hidden" name="tws_license_form" value="<?php echo $to_activate ? 'activate' : 'deactivate'; ?>">
					</fieldset>
				</form>
			</div>
		</div>

		<?php
	}

	/**
	 * Gets deactivation page link.
	 *
	 * @param string|false $url        The URL to redirect to. If same URL then set to false.
	 * @param array        $parameters The query parameters.
	 *
	 * @return string
	 */
	private function build_query_url( $url = false, $parameters ) {
		return add_query_arg( $parameters, $url );
	}

	/**
	 * Validates the form data to make REST API request.
	 *
	 * NOTE: Sanitization is a must for form data. Some are applied, others not.
	 *
	 * @return bool True if form has all fields value, false otherwise.
	 *
	 * @todo Make sanitization as required.
	 */
	public function has_valid_form_data() {
		// Bail if debug mode is enabled.
		if ( $this->debug ) {
			return true;
		}

		$data      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$with_data = array( 1 );

		// Internal check for validation field. If not found, request => invalid.
		if (
			! isset( $data['tws_license_form'] ) ||
			! isset( $data['validate_license'] )
		) {
			return false;
		}

		$this->form_state              = sanitize_key( $data['tws_license_form'] );
		$this->validated['form_state'] = $this->form_state;
		$this->validated['authorize']  = $data['validate_license'];

		if ( isset( $data[ $this->dirname ]['license_key'] ) ) {
			$this->license = sanitize_text_field( $data[ $this->dirname ]['license_key'] );

			// Catch error for license key with no data for client side validation.
			if ( isset( $this->to_validate['license_key'] ) ) {
				$license_key   = $data[ $this->dirname ]['license_key'];
				$license_error = $this->to_validate['license_key'];

				// Clear license key so it will not be used for validation.
				unset( $this->to_validate['license_key'] );

				if ( empty( $license_key ) ) {
					$this->errors['license_key'] = $license_error;
				}
			}
		}

		// Iterate over data to be validated and prepare validated and error data.
		foreach ( $this->to_validate as $key => $error ) {
			if ( isset( $data[ $this->dirname ][ $key ] ) && 'email' === $data[ $this->dirname ][ $key ] ) {
				$this->validated['email'] = sanitize_email( $data[ $this->dirname ][ $key ] );

				// Catch error for email with no data for client side validation.
				if ( empty( $data[ $this->dirname ]['email'] ) ) {
					$this->errors['email'] = $error;
					$with_data[]           = 0;
				}
				continue;
			}

			if ( isset( $data[ $this->dirname ][ $key ] ) && 'order_id' === $data[ $this->dirname ][ $key ] ) {
				$this->validated['order_id'] = intval( $data[ $this->dirname ][ $key ] );

				// Catch error for order ID with no data for client side validation.
				if ( empty( $data[ $this->dirname ]['order_id'] ) ) {
					$this->errors['order_id'] = $error;
					$with_data[]              = 0;
				}
				continue;
			}

			if ( isset( $data[ $this->dirname ][ $key ] ) && 'slug' === $data[ $this->dirname ][ $key ] ) {
				$this->validated['slug'] = sanitize_title( $data[ $this->dirname ][ $key ] );
			}

			// Any other validation data.
			if ( isset( $data[ $this->dirname ][ $key ] ) ) {
				$this->validated[ $key ] = sanitize_text_field( $data[ $this->dirname ][ $key ] );

				// Catch errors for other inputs with no data for client side validation.
				if ( empty( $data[ $this->dirname ][ $key ] ) ) {
					$this->errors[ $key ] = $error;
					$with_data[]          = 0;
				}
			}
		}

		return $this->can_make_request( $with_data );
	}

	/**
	 * Makes sure if form data is ready to make the REST API request.
	 *
	 * @param int[] $with_data Array of input fields validation. `0` means invalid input.
	 *
	 * @return bool True if all input field has data, false otherwise.
	 */
	public function can_make_request( $with_data ) {
		return in_array( 0, $with_data, true ) ? false : true;
	}

		/**
		 * Adds activation menu.
		 */
	public function add_license_page() {
		$this->hook_suffix = add_submenu_page(
			$this->parent_slug,
			__( 'Activate License', 'tws-license-manager-client' ),
			__( 'Activate License', 'tws-license-manager-client' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'generate_form' )
		);

		add_action( 'load-' . $this->hook_suffix, array( $this, 'loaded' ) );
	}

		/**
		 * Fires on activation submenu page.
		 */
	public function loaded() {
		/**
		 * WPHOOK: Fires on activation submenu page.
		 *
		 * @param Manager $client Current instance of license manager client.
		 */
		do_action( $this->dirname . '_activation_page_loaded', $this );
	}

	/**
	 * Enqueues necessary styles and scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( $this->dirname . '-style', plugin_dir_url( __FILE__ ) . '/Assets/style.css', array(), self::VERSION );
	}

	/**
	 * Parses URL to get the domain.
	 *
	 * @param string $domain The full URI.
	 *
	 * @return string
	 */
	private function parse_url( $domain ) {
		$domain = \wp_parse_url( $domain, PHP_URL_HOST );
		$domain = \ltrim( $domain, 'www.' );

		return sanitize_key( $domain );
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
		return $this->client->request( $endpoint, 'POST', $data );
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
		return $this->client->request( $endpoint, 'PUT', $data );
	}

	/**
	 * GET method.
	 *
	 * @param string $endpoint   API endpoint.
	 * @param array  $parameters Request parameters.
	 *
	 * @return \stdClass|\WP_Error
	 */
	public function get( $endpoint, $parameters = array() ) {
		return $this->client->request( $endpoint, 'GET', array(), $parameters );
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
		return $this->client->request( $endpoint, 'DELETE', array(), $parameters );
	}

	/**
	 * OPTIONS method.
	 *
	 * @param string $endpoint API endpoint.
	 *
	 * @return \stdClass
	 */
	public function options( $endpoint ) {
		return $this->client->request( $endpoint, 'OPTIONS', array(), array() );
	}
}
