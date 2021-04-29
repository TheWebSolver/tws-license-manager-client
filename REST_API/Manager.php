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
	public $product_name = '';

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
	 * License active status.
	 *
	 * @var bool
	 */
	public $is_active = false;

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
		$options           = get_option( $this->option, array() );
		$this->debug       = defined( 'TWS_LICENSE_MANAGER_CLIENT_DEBUG' ) && TWS_LICENSE_MANAGER_CLIENT_DEBUG;
		if (
			! empty( $options ) &&
			isset( $options['success'] ) &&
			$options['success'] &&
			! $this->debug
		) {
			$this->is_active = true;
		}

		if ( '' !== $parent_slug && is_string( $parent_slug ) ) {
			add_action( 'admin_menu', array( $this, 'add_license_page' ), 999 );
		}

		add_action( 'admin_init', array( $this, 'start' ), 5 );
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
	 *   'name'        => 'Sold Plugin Name',
	 *  )
	 * );
	 * ```
	 */
	public function set_validation( $data = array() ) {
		$this->to_validate = $data;

		// Set the plugin/product name on server if used for validation.
		if ( isset( $data['name'] ) ) {
			$this->product_name = $data['name'];
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
	 * Starts page.
	 */
	public function start() {
		// Bail if not license page.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
			return;
		}

		$this->step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		// phpcs:enable
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
	 * Gets product license key.
	 *
	 * @return string
	 */
	public function get_license() {
		return $this->license;
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
		return $this->client->request( $this->endpoint, $this->method, $data, $this->parameters );
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
		return $this->client->request( $this->endpoint, 'GET', array(), $this->parameters );
	}

	/**
	 * Prepare request data to pass for getting response.
	 */
	private function prepare_request() {
		// Prepare validation.
		$validation = ! $this->has_errors() ? $this->validated : array();
		$license    = is_string( $this->license ) && 0 < strlen( $this->license );
		$base       = "licenses/{$this->form_state}/";
		$parameters = $this->parameters;

		// A required validation parameter so only form fields can be a valid server request.
		$license_form     = array( 'form_id' => \sha1( 'validate_licenses' ) );
		$this->parameters = array_merge( $parameters, $license_form, $validation );

		// Prepare final endpoint.
		if ( ! $license ) {
			// No license key is an error if not on a debug mode.
			if ( ! $this->debug ) {
				$this->client->add_error(
					'license_key_not_valid',
					__( 'License key was invalid or no license key was given.', 'tws-license-manager-client' )
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
		$parameters  = get_option( $this->option, array() );
		$license_key = isset( $parameters['license_key'] ) ? $parameters['license_key'] : '';
		$email       = isset( $parameters['email'] ) ? $parameters['email'] : '';
		$order_id    = isset( $parameters['order_id'] ) ? $parameters['order_id'] : '';

		// Check status at this stage too so no early call ignored.
		if (
			! empty( $parameters ) &&
			isset( $parameters['success'] ) &&
			$parameters['success'] &&
			! $this->debug
		) {
			$this->is_active = true;
		}

		// Form states.
		$disabled   = '';
		$deactivate = false;

		// Set form state for activating or deactivating license.
		if ( $to_activate ) {
			$btn_class  = ' hz_lmac_btn';
			$button     = $this->is_active ? __( 'Activated', 'tws-license-manager-client' ) : __( 'Activate Now', 'tws-license-manager-client' );
			$disabled   = $this->is_active ? ' disabled=disabled' : '';
			$deactivate = $this->is_active ? true : false;
		} else {
			$btn_class = ' hz_lmdac_btn';
			$button    = __( 'Deactivate Now', 'tws-license-manager-client' );
		}
		$disabled = false;
		?>

		<div class="hz_license_form">
			<div class="hz_license_form_head"></div>
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
					// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
					$error = $email_error = $order_error = '';
					$class = $email_class = $order_class = '';
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
							$for = $this->dirname . '[email]';

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
							$for = $this->dirname . '[order_id]';

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
						<?php if ( is_string( $this->product_name ) && 0 < strlen( $this->product_name ) ) : ?>
							<input type="hidden" id="<?php echo esc_attr( $this->dirname ); ?>[name]" name="<?php echo esc_attr( $this->dirname ); ?>[name]" type="text" value="<?php echo esc_attr( $this->product_name ); ?>">
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

					<fieldset class="action_buttons"<?php echo esc_attr( $disabled ); ?>>
						<input type="submit" class="button-primary button button-large button-next hz_btn__prim<?php echo esc_attr( $btn_class ); ?>" value="<?php echo esc_html( $button ); ?>" />
					</fieldset>
					<?php if ( $deactivate ) : ?>
						<fieldset class="deactivate_license">
							<a href="<?php echo esc_url( $this->build_query_url() ); ?>" class="button button-large button-next hz_btn__skip hz_btn__nav"><?php esc_html_e( 'Deactivate License', 'tws-license-manager-client' ); ?></a>
						</fieldset>
					<?php else : ?>
						<a href="<?php echo esc_url( $this->build_query_url( admin_url(), array( 'referrer' => $this->page_slug ) ) ); ?>">
							<?php esc_html_e( 'Back to Dashboard', 'tws-license-manager-client' ); ?>
						</a>
					<?php endif; ?>
					<fieldset class="license_validation">
						<?php
						wp_nonce_field( 'hzfex-validate-license-form' );
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
	private function build_query_url( $url = false, $parameters = array() ) {
		$deactivate = array( 'step' => 'deactivate' );
		$parameters = array_merge( $parameters, $deactivate );
		return add_query_arg( $parameters, $url );
	}

	/**
	 * Validates the form data to make REST API request.
	 *
	 * NOTE: Sanitization is a must for form data. Some are applied, others not.
	 *
	 * @param bool $save Whether to save form inputs to database or not.
	 *
	 * @return bool True if form has all fields value, false otherwise.
	 *
	 * @todo Make sanitization as required.
	 */
	public function has_valid_form_data( $save = true ) {
		// Bail if debug mode is enabled.
		if ( $this->debug ) {
			return true;
		}

		$data      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$with_data = array( 1 );

		// Internal check for validation field. If not found, request => invalid.
		if (
			! isset( $data['tws_license_form'] ) ||
			! isset( $data['validate_license'] ) ||
			'validate_license' !== $data['validate_license']
		) {
			return false;
		}

		$this->form_state              = sanitize_key( $data['tws_license_form'] );
		$this->validated['form_state'] = $this->form_state;

		if ( isset( $data[ $this->dirname ]['license_key'] ) ) {
			$this->license = strtoupper( sanitize_key( $data[ $this->dirname ]['license_key'] ) );

			// Catch error for license key with no data for client side validation.
			if ( isset( $this->to_validate['license_key'] ) ) {
				$license_key = $this->to_validate['license_key'];

				// Clear license key so it will not be set as validation parameter.
				unset( $this->to_validate['license_key'] );

				if ( empty( $license_key ) ) {
					$this->errors['license_key'] = $this->to_validate['license_key'];
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

			if ( isset( $data[ $this->dirname ][ $key ] ) && 'name' === $data[ $this->dirname ][ $key ] ) {
				$this->validated['name'] = sanitize_title( $data[ $this->dirname ][ $key ] );

				// Catch error for product name with no data for client side validation.
				if ( empty( $data[ $this->dirname ]['name'] ) ) {
					$this->errors['name'] = $error;
					$with_data[]          = 0;
				}
				continue;
			}

			// Any other validation data.
			if ( isset( $data[ $this->dirname ][ $key ] ) ) {
				$this->validated[ $key ] = $data[ $this->dirname ][ $key ];

				// Catch errors for other inputs with no data for client side validation.
				if ( empty( $data[ $this->dirname ][ $key ] ) ) {
					$this->errors[ $key ] = $error;
					$with_data[]          = 0;
				}
			}
		}

		// Prepare data to save to database.
		$value = array_merge( array( 'license_key' => $this->license ), $this->validated );

		// Save form data to database if not activated before. REVIEW: update setting.
		if ( $save ) {
			update_option( $this->option, $value );
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
