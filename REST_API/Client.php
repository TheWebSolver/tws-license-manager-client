<?php // phpcs:ignore WordPress.NamingConventions
/**
 * The Web Solver Licence Manager Client.
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
use WP_Error;

/**
 * The Web Solver Licence Manager Client class.
 */
class Client {
	/**
	 * WooCommerce REST API Client version.
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
	 * API Validation data.
	 *
	 * @var array
	 */
	private $validation_data = array();

	/**
	 * Endpoints for REST API. Defaults to `licenses`.
	 *
	 * @var string
	 */
	private $endpoint = 'licenses';

	/**
	 * Product license key.
	 *
	 * @var string
	 */
	private $license;

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
	 * Sets client plugin directory, main file name and parent menu to hook license form submenu page.
	 *
	 * @param string $dirname   Required. The client plugin directory name. This will be used as prefixer.
	 * @param string $filename  Optional. If same as dirname, no need to pass it. The client plugin main file name (with extension `.php`).
	 * @param string $parent_slug Optional. Parent menu slug to display activation form.
	 *                            Form must be called elsewhere in plugin if parent slug not given.
	 *                            {@see @method `Client::generate_form()`}.
	 */
	public function __construct( $dirname, $filename = '', $parent_slug = '' ) {
		// Set properties.
		$this->dirname     = $dirname;
		$this->filename    = '' === $filename ? $dirname . '.php' : $filename;
		$this->parent_slug = $parent_slug;
		$this->option      = $dirname . '-license-data';

		if ( '' !== $parent_slug && is_string( $parent_slug ) ) {
			add_action( 'admin_menu', array( $this, 'add_license_page' ), 999 );
		}
	}

	/**
	 * Sets API keys for client.
	 *
	 * @param string $key    API Consumer Key.
	 * @param string $secret API Consumer Secret.
	 *
	 * @return Client
	 */
	public function set_keys( $key, $secret ) {
		$this->consumer_key    = $key;
		$this->consumer_secret = $secret;

		return $this;
	}

	/**
	 * Sets API additional validation data.
	 *
	 * @param array $data The data can be of following types.
	 * * @type `string` `email`    The customer email address (email that is used to purchase this plugin).
	 * * @type `int`    `order_id` The order ID (order ID for which license is generated).
	 * * @type `string` `name`     The product name (this plugin woocommerce product title).
	 *
	 * @return Client
	 *
	 * @example usage
	 * ```
	 * // Check license key, email address, order_id, purchased product/plugin name for validation and their error message for client side.
	 * Client::set_validation(
	 *  array(
	 *   'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
	 *   'email'       => __( 'Enter valid email address registered when purchasing the plugin.', 'tws-license-manager-client' ),
	 *   'order_id'    => __( 'Enter valid order ID when purchasing this plugin.', 'tws-license-manager-client' ),
	 *   'name'        => '', // empty as this should be passed as parameter like this -> Client::set_product_name('My Client Plugin'). So no errors on client side.
	 *  )
	 * );
	 * ```
	 */
	public function set_validation( $data = array() ) {
		$this->validation_data = $data;

		return $this;
	}

	/**
	 * Initializes connection between client and server.
	 *
	 * @param string $server_url The Remote Server URL where licenses are saved.
	 * @param array  $options    Optional. Additional options for connection. (version, timeout, verify_ssl).
	 *
	 * @return Client
	 */
	public function connect_with( $server_url, $options = array() ) {
		$this->client = new Http_Client(
			$server_url,
			$this->consumer_key,
			$this->consumer_secret,
			$options
		);

		return $this;
	}

	/**
	 * Sets REST API endpoint.
	 *
	 * @param string $endpoint The REST API Route endpoint.
	 *
	 * @return Client
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;

		// Set route.
		$namespace   = $this->client->get_option()->namespace();
		$version     = $this->client->get_option()->get_version();
		$this->route = '/' . $namespace . '/' . $version . '/' . $endpoint . '/';

		return $this;
	}

	/**
	 * Sets license key.
	 *
	 * ____________
	 * NOTE: This is an optional method used for validation purposes only.
	 * ____________
	 *
	 * @param string $product_name The WooCommerce product name set for this plugin on server site that matches with the product when order was made. This will be used for validation on server when you pass `name` in {@see @method `Client::set_validation()`} array parameter.
	 *
	 * @return Client
	 */
	public function set_product_name( $product_name = '' ) {
		$this->product_name = $product_name;

		return $this;
	}

	/**
	 * Sets REST API call method.
	 *
	 * @param string $method The REST API method call type.
	 *
	 * @return Client
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
	 * REST API call with GET method.
	 *
	 * @param array $parameters Query parameters.
	 *
	 * @return \stdClass|WP_Error
	 */
	public function get_call( $parameters = array() ) {
		$license  = $this->license ? '/' . $this->license : '';
		$endpoint = $this->endpoint . $license;

		return $this->get( $endpoint, $parameters );
	}

	/**
	 * Generates form for displaying on client site.
	 */
	public function generate_form() {
		$parameters  = get_option( $this->option, array() );
		$license_key = isset( $parameters['license_key'] ) ? $parameters['license_key'] : '';
		$email       = isset( $parameters['email'] ) ? $parameters['email'] : '';
		$order_id    = isset( $parameters['order_id'] ) ? $parameters['order_id'] : '';

		// Check status at this stage too so no early call ignored.
		$this->is_active = ! empty( $parameters ) && isset( $parameters['success'] ) && $parameters['success'];
		?>
			<form method="POST">
				<?php
					/**
					 * WPHOOK: Action -> fires after default fields.
					 *
					 * @param string[] $parameters The form field parameters.
					 */
					do_action( $this->dirname . '_before_license_form', $parameters );
				?>

				<?php
				// phpcs:disable
				$error = $email_error = $order_error = '';
				$class = $email_class = $order_class = '';
				// phpcs:enable

				if ( $this->has_errors() && isset( $this->errors['license_key'] ) ) {
					$error = $this->errors['license_key'];
					$class = 'field_error';
				}
				?>

				<fieldset id="hz_activate_plugin"<?php echo $this->is_active ? ' disabled="disabled"' : ''; ?>>
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
					if ( array_key_exists( 'email', $this->validation_data ) ) :
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
					if ( array_key_exists( 'order_id', $this->validation_data ) ) :
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
					<?php if ( array_key_exists( 'name', $this->validation_data ) && '' !== $this->product_name ) : ?>
						<input type="hidden" id="<?php echo esc_attr( $this->dirname ); ?>[name]" name="<?php echo esc_attr( $this->dirname ); ?>[name]" type="text" value="<?php echo esc_attr( $this->product_name ); ?>">
						<?php endif; ?>
				</fieldset>

				<?php
				/**
				 * WPHOOK: Action -> fires after default fields.
				 *
				 * @param string[] $parameters The form field parameters.
				 */
				do_action( $this->dirname . '_after_license_form', $parameters );
				?>

				<fieldset class="action_buttons"<?php echo $this->is_active ? ' disabled="disabled"' : ''; ?>>
					<input type="submit" class="button-primary button button-large button-next hz_btn__prim" value="<?php esc_html_e( 'Activate', 'tws-license-manager-client' ); ?>" />
					<?php wp_nonce_field( 'hzfex-validate-license-form' ); ?>
					<input type="hidden" name="activate_license" value="activate_license">
				</fieldset>
			</form>
		<?php
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
	public function is_valid_request( $save = true ) {
		$data      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$with_data = array( 1 );

		// Internal check if we have activate license hidden field.
		if ( isset( $data['activate_license'] ) && 'activate_license' === $data['activate_license'] ) {
			if ( isset( $data[ $this->dirname ]['license_key'] ) ) {
				$this->license = strtoupper( sanitize_key( $data[ $this->dirname ]['license_key'] ) );

				// Catch error for license key with no data for client side validation.
				if (
					empty( $data[ $this->dirname ]['license_key'] ) &&
					isset( $this->validation_data['license_key'] )
				) {
					$this->errors['license_key'] = $this->validation_data['license_key'];
				}
			}

			// Clear license key for setting parameters.
			if ( isset( $this->validation_data['license_key'] ) ) {
				unset( $this->validation_data['license_key'] );
			}

			// Iterate over validation fields and set properties.
			foreach ( $this->validation_data as $key => $error ) {
				if (
					isset( $data[ $this->dirname ][ $key ] ) &&
					'email' === $data[ $this->dirname ][ $key ]
				) {
					$this->parameters['email'] = sanitize_email( $data[ $this->dirname ][ $key ] );

					// Catch error for email with no data for client side validation.
					if ( empty( $data[ $this->dirname ]['email'] ) ) {
						$this->errors['email'] = $error;
						$with_data[]           = 0;
					}
					continue;
				}

				if ( isset( $data[ $this->dirname ][ $key ] ) ) {
					$this->parameters[ $key ] = $data[ $this->dirname ][ $key ];

					// Catch errors for other inputs with no data for client side validation.
					if ( empty( $data[ $this->dirname ][ $key ] ) ) {
						$this->errors[ $key ] = $error;
						$with_data[]          = 0;
					}
				}
			}
		}

		// Prepare data to save to database.
		$value = array_merge( array( 'license_key' => $this->license ), $this->parameters );

		// Save form data to database if not activated before.
		if ( $save && ! $this->is_active ) {
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
		// Get activation option.
		$options         = get_option( $this->option, array() );
		$this->is_active = ! empty( $options ) && isset( $options['success'] ) && $options['success'];

		$this->hook_suffix = add_submenu_page(
			$this->parent_slug,
			__( 'Activate License', 'tws-license-manager-client' ),
			__( 'Activate License', 'tws-license-manager-client' ),
			'manage_options',
			'activate-' . $this->dirname,
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
		 * @param Client $client Current instance of license manager client.
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
