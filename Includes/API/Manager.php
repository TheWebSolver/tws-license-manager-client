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

namespace TheWebSolver\License_Manager\API;

use TheWebSolver\License_Manager\Component\Http_Client;

/**
 * Handles Client.
 */
final class Manager {
	/**
	 * The Web Solver License Manager Client Manager version.
	 */
	const VERSION = '2.0.0';

	/**
	 * Consumer Key.
	 *
	 * @var string
	 */
	private $consumer_key = '';

	/**
	 * Consumer Secret.
	 *
	 * @var string
	 */
	private $consumer_secret = '';

	/**
	 * Product Secret Key used by server.
	 *
	 * @var string
	 */
	private $hash = '';

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
	 * WooCommerce Product Slug set on server for this product.
	 *
	 * @var string
	 */
	private $product_slug = '';

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
	 * The client directory name.
	 *
	 * @var string
	 */
	public $dirname;

	/**
	 * The client main file name.
	 *
	 * @var string
	 */
	public $filename;

	/**
	 * Parent slug for creating activate/deactivate license submenu.
	 *
	 * @var string
	 */
	private $parent_slug;

	/**
	 * License data option key.
	 *
	 * @var string
	 */
	public $license_option;

	/**
	 * Product data option key.
	 *
	 * @var string
	 */
	public $product_option;

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
	 * Current license form step.
	 *
	 * If license deactivation happening, it will be set to `deactivate`.
	 *
	 * @var step
	 */
	private $step = '';

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
	 * Unique client identifier.
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
	 * Manage license of product by type.
	 *
	 * Possible values are: `plugin` or `theme`.
	 *
	 * @var string
	 */
	private $type = 'plugin';

	/**
	 * License status check schedule tag.
	 *
	 * @var string
	 */
	private $schedule;

	/**
	 * The license server URL.
	 *
	 * @var string
	 */
	private $server_url;

	/**
	 * Whether to cache product update data.
	 *
	 * Defaults are:
	 * * `0` `bool` `true` - cache is enabled.
	 * * `1` `int`  `2`    - total hours after which cache is flushed.
	 *
	 * @var array
	 */
	private $cache_product_data = array( true, 2 );

	/**
	 * Sets client directory, main file name and parent menu to hook license form submenu page.
	 *
	 * @param string $dirname     Required. The client directory name. This will be used as prefix.
	 *                            This can either be theme directory name or plugin directory name
	 *                            depending upon the type you set at `Manager::set_type()` (only
	 *                            need to set it if the product is theme).
	 * @param string $filename    Optional. Only for plugin. If same as dirname, no need to pass it.
	 *                            The client plugin main file name (with extension `.php`).
	 * @param string $parent_slug Optional. Parent menu slug to display activation form.
	 *                            Form must be called elsewhere in plugin if parent slug not given.
	 *                            {@see @method `Manager::generate_form()`}.
	 */
	public function __construct( string $dirname, string $filename = '', string $parent_slug = '' ) {
		$this->dirname        = $dirname;
		$this->filename       = '' === $filename ? $dirname . '.php' : $filename;
		$this->parent_slug    = $parent_slug;
		$this->license_option = $dirname . '-license-data';
		$this->product_option = $dirname . '-product-data';
		$this->page_slug      = 'tws-activate-' . $dirname;
		$this->key            = 'data-' . $this->parse_url( get_bloginfo( 'url' ) );
		$this->debug          = defined( 'TWS_LICENSE_MANAGER_CLIENT_DEBUG' ) && TWS_LICENSE_MANAGER_CLIENT_DEBUG;
		$this->schedule       = 'tws_' . $this->dirname . '_schedule_license_status_check';

		add_action( $this->schedule, array( $this, 'check_license_status' ) );

		if ( '' !== $parent_slug ) {
			add_action( 'admin_menu', array( $this, 'add_license_page' ), 999 );
		}

		add_action( 'admin_init', array( $this, 'start' ), 9 );
		add_filter( 'admin_body_class', array( $this, 'set_current_license_status' ) );
	}

	/**
	 * Sets the product type.
	 *
	 * The possible types are: `plugin` or `theme`.
	 *
	 * @param string $type The product type.
	 *
	 * @return Manager
	 */
	public function set_type( string $type = 'plugin' ): Manager {
		$this->type = $type;

		return $this;
	}

	/**
	 * Sets API keys for client.
	 *
	 * @param string $key    API Consumer Key.
	 * @param string $secret API Consumer Secret.
	 *
	 * @return Manager
	 */
	public function authenticate_with( string $key, string $secret ): Manager {
		$this->consumer_key    = $key;
		$this->consumer_secret = $secret;

		return $this;
	}

	/**
	 * Sets the secret key for validating the product.
	 *
	 * The secret key set on the server.
	 *
	 * @param string $key The secret key.
	 *
	 * @return Manager
	 */
	public function hash_with( string $key ): Manager {
		$this->hash = $key;

		return $this;
	}

	/**
	 * Sets API URL parameters.
	 *
	 * Any additional parameters to pass along with request.
	 *
	 * @param array $parameters API URL parameters.
	 *
	 * @return Manager
	 */
	public function query_with( array $parameters ): Manager {
		$this->parameters = $parameters;

		return $this;
	}

	/**
	 * Sets API additional validation data.
	 *
	 * @param array $data The data can be of following types with their respective error message **(required)**.
	 * * @type `string` `license_key` The generated license key for this product.
	 * * @type `string` `email`       The email address (used to purchase this product on server).
	 * * @type `int`    `order_id`    The order ID (order ID for which license is generated).
	 * * @type `string` `slug`        The slug (this product woocommerce product slug on server).
	 *
	 * @return Manager
	 *
	 * @example usage
	 * ```
	 * // Check license key, email address, order ID, purchased plugin/theme slug on server and show respective error messages when activating/deactivating license on client.
	 * Manager::validate_with(
	 *  array(
	 *   // REQUIRED: End user must input license key in form field. Shows client error if field is blank.
	 *   'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
	 *
	 *   // OPTIONAL BUT RECOMMENDED: Email field will be generated and user must input email registered on server and same was used to purchase this product. Shows client error if field is blank.
	 *   'email'       => __( 'Enter valid/same email address used at the time of purchase.', 'tws-license-manager-client' ),
	 *
	 *   // OPTIONAL: Order ID field will be generated and user must input the Order ID for which license key was generated. Shows client error if field is blank.
	 *   'order_id'    => __( 'Enter same/valid purchase order ID.', 'tws-license-manager-client' ),
	 *
	 *   // REQUIRED: Hidden input field will be generated. The WooCommerce product slug set for this product on server that matches with the published product slug. Gets response error if slug didn't match.
	 *   'slug'        => 'woocommerce-product-slug-for-this-product-on-server',
	 *  )
	 * );
	 * ```
	 */
	public function validate_with( array $data = array() ): Manager {
		$this->to_validate = $data;

		// Set the product slug on server if used for validation.
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
	public function connect_with( string $server_url, array $options = array() ): Manager {
		$defaults = array(
			'namespace' => 'lmfwc',
			'version'   => 'v2',
		);

		$options      = wp_parse_args( $options, $defaults );
		$this->client = new Http_Client(
			$server_url,
			$this->consumer_key,
			$this->consumer_secret,
			$options
		);

		$this->server_url = $server_url;

		return $this;
	}

	/**
	 * Disables form after activation/deactivation complete.
	 *
	 * @param bool $disable True to disable form after success, false otherwise.
	 *
	 * @return Manager
	 */
	public function disable_form( bool $disable = true ): Manager {
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
	public function set_key_or_id( string $value ): Manager {
		$this->license = $value;

		return $this;
	}

	/**
	 * Sets updating caching system.
	 *
	 * @param bool $enable Whether to enable cache or not.
	 * @param int  $hours  Number of hours to save the cache.
	 *                     It has no effect if `$enable` is `false`.
	 *
	 * @return Manager
	 */
	public function cache_product_data( bool $enable = true, int $hours = 2 ): Manager {
		$this->cache_product_data = array( $enable, $hours );

		return $this;
	}

	/**
	 * Deletes WordPress update transitent and product info transitents.
	 */
	private function purge_cache() {
		// Clear site transitent so fresh request for updates made.
		delete_site_transient( "update_{$this->type}s" );

		// Delete product details transient.
		delete_transient( "cached_{$this->product_option}" );
	}

	/**
	 * Saves response to database.
	 *
	 * Only does this when debug is OFF.
	 *
	 * @param \stdClass $response The server response.
	 */
	private function parse_response( $response ) {
		// Prevent executions on debug mode.
		if ( $this->debug ) {
			return;
		}

		$this->purge_cache();

		$details = (array) $response->data;

		if ( isset( $details['product_meta'] ) ) {
			$data = (object) $details['product_meta'];

			$this->save_product_data_to_cache( $data );

			// Clear product details before saving license details.
			unset( $details['product_meta'] );
		}

		update_option( $this->license_option, maybe_serialize( (object) $details ), false );
	}

	/**
	 * Checks if license has expired for the given data.
	 *
	 * This will only ever be used if license is activated/deactivated
	 * using license form but the license has already been expired.
	 *
	 * @param \WP_Error $data The data as WP_Error.
	 *
	 * @return bool True if expired, false otherwise.
	 */
	private function has_license_expired( \WP_Error $data ): bool {
		if ( 'lmfwc_rest_license_expired' === $data->get_error_code() ) {
			$message = $data->get_error_message( 'lmfwc_rest_license_expired' );
			$message = str_replace( 'The license Key expired on ', '', $message );
			$message = substr( $message, 0, strpos( $message, '(' ) );
			$date    = rtrim( $message );

			// Save expired status and expiry date to database.
			if ( 'expired' !== $this->get_license( 'status' ) ) {
				$this->make_license_expire( $date );
			}

			return true;
		}

		return false;
	}

	/**
	 * Performs task for events happening on current product's license page.
	 */
	public function start() {
		// Bail if not license page.
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
			return;
		}

		// Lets prettify the license form.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Steps must not work if license has expired.
		$this->step = isset( $_GET['step'] ) && 'expired' !== (string) $this->get_license( 'status' ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';

		// Bail if license form not submitted yet.
		if ( ! isset( $_POST['validate_license'] ) || $this->hash !== $_POST['validate_license'] ) {
			return;
		}
		// phpcs:enable

		// API Namespace is a must. It's an error if not given. Stop processing further.
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

		// There are errors on HTTP Client even after getting response.
		// Lets first check if response is a valid response.
		if (
			is_object( $this->response ) &&
			isset( $this->response->success ) &&
			$this->response->success
		) {
			$this->parse_response( $this->response );

			return;
		}

		// Then handle error, if any.
		if ( is_wp_error( $this->response ) ) {
			$this->has_license_expired( $this->response );
		}
	}

	/**
	 * Gets product license data.
	 *
	 * @param string $data The license data to retrive. Possible values are:
	 * * `key`          - The key with which metdata is saved in server. Unique to each site
	 * * `email`        - The user email address registered on server
	 * * `status`       - The license current active/inactive status
	 * * `order_id`     - The WooCommerce order ID for which license is generated on server
	 * * `expires_at`   - The license expiration date
	 * * `product_id`   - The license assigned product ID.
	 * * `total_count`  - Total number of times license key can be activated
	 * * `active_count` - Number of times license key has been activated
	 * * `license_key`  - The product license key
	 * * `purchased_on` - The license key created date and time.
	 *
	 * @return string|\stdClass
	 */
	public function get_license( string $data = '' ) {
		$license = maybe_unserialize( get_option( $this->license_option, '' ) );

		if ( ! $data ) {
			return $license;
		}

		return is_object( $license ) && isset( $license->{$data} ) ? $license->{$data} : '';
	}

	/**
	 * Expires current license.
	 *
	 * @param string $date The expiry date to update.
	 *
	 * @return bool True on success, false otherwise.
	 */
	private function make_license_expire( string $date = '' ): bool {
		$licence = (array) maybe_unserialize( get_option( $this->license_option, array() ) );

		// Check license is valid.
		if ( ! isset( $licence['status'] ) ) {
			return false;
		}

		if ( $date ) {
			$expiry_date   = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
			$is_valid_date = $expiry_date && $expiry_date->format( 'Y-m-d H:i:s' ) === $date;

			// Given date is a valid date (format) used on server, update expiry date too.
			if ( $is_valid_date ) {
				$licence['expires_at'] = $date;
			}
		}

		$licence['status'] = 'expired';
		$update            = (object) $licence;

		update_option( $this->license_option, maybe_serialize( $update ), false );

		return true;
	}

	/**
	 * Gets cached product data from database.
	 *
	 * @return \stdClass|false False if invalid data or cache expired.
	 */
	private function get_product_data_from_cache() {
		global $pagenow;

		// Current page is an update page. Lets not use cached data.
		if ( 'update-core.php' === $pagenow ) {
			return false;
		}

		return get_transient( "cached_{$this->product_option}" );
	}

	/**
	 * Saves product data to database.
	 *
	 * By default data is saved in a cache for 2 hours before making another server request.
	 *
	 * @param \stdClass|false $data The product data to save.
	 */
	private function save_product_data_to_cache( $data ) {
		if ( ! $data ) {
			return;
		}

		$hours = absint( $this->cache_product_data[1] );
		set_transient( "cached_{$this->product_option}", $data, HOUR_IN_SECONDS * $hours );
	}

	/**
	 * Fetches product data from server.
	 *
	 * @param string $flag The validation flag.
	 *
	 * @return \stdClass|false False if bad response.
	 */
	private function fetch_product_data_from_server( string $flag ) {
		/**
		 * Making changes to this var will trigger server level error.
		 *
		 * Server must get proper request with the current license in client.
		 * If server can't verify the validation state,
		 * it will assume that client codes has been tempered with.
		 * (in this case, that must be the end-user).
		 *
		 * If it is tempered, server will never send a valid response back.
		 *
		 * Response code:
		 * * `200` Flag not verified.
		 *
		 * Response error codes:
		 * * `400` License can not be verified.
		 * * `401` License status can not be verified.
		 * * `402` License is not active.
		 * * `403` License has expired. (changes license status as expired, if not already).
		 *
		 * @var \stdClass|\WP_Error
		 */
		$response = $this->validate_license( $this->get_validation_args( $flag ) );

		// Response has no state defined, data => invalid.
		if ( ! is_object( $response ) || ! isset( $response->data->state ) || ! isset( $response->data->product_meta ) ) {
			return false;
		}

		$product_data = $response->data->product_meta;

		// License expired on server, save that change in client too.
		if ( 'expired' !== $this->get_license( 'status' ) && 'expired' === $response->data->state ) {
			$this->make_license_expire( $response->data->expiresAt );
		}

		/**
		 * The file extensions for icons (except "svg") can be jpg, png, gif.
		 * However, use same extension for all combination.
		 * Possible array keys for icons are:
		 * ```
		 * array(
		 *  'svg' => 'http://imageurl/icon.svg',
		 *  '1x'  => 'http://imageurl/icon-size-with-128x128-px.png',
		 *  '2x'  => 'http://imageurl/icon-size-with-256x256-px.png',
		 * );
		 * ```
		 *
		 * @var string[]
		 */
		$logo = isset( $product_data->logo ) ? (array) $product_data->logo : array();

		/**
		 * The file extensions for banners can be jpg, png.
		 * However, use same extension for all combination.
		 * Possible array keys for icons are:
		 * ```
		 * array(
		 *  '1x' => 'http://imageurl/banner-size-with-772x250-px.png',
		 *  '2x' => 'http://imageurl/banner-size-with-1544x500-px.png',
		 * );
		 * ```
		 *
		 * @var string[]
		 */
		$cover = isset( $product_data->cover ) ? (array) $product_data->cover : array();

		$content = isset( $product_data->content ) ? (array) $product_data->content : array();

		$product_data->logo    = $logo;
		$product_data->cover   = $cover;
		$product_data->content = $content;

		return $product_data;
	}

	/**
	 * Gets product info either from cache or server.
	 *
	 * @param string $flag The flag.
	 *
	 * @return \stdClass|false False if invalid data.
	 */
	private function get_product_info( string $flag = '' ) {
		// Caching disabled, always make server request.
		if ( ! $this->cache_product_data[0] ) {
			return $this->fetch_product_data_from_server( $flag );
		}

		$data = $this->get_product_data_from_cache();

		// Cache not set, expired, invalid data, etc. Send request to server to fetch new data.
		if ( false === $data ) {
			$data = $this->fetch_product_data_from_server( $flag );
			$this->save_product_data_to_cache( $data );
		}

		return $data;
	}

	/**
	 * Handles product update.
	 */
	public function handle_update() {
		add_filter( "pre_set_site_transient_update_{$this->type}s", array( $this, 'check_for_update' ), 10, 2 );
	}

	/**
	 * Validates license with server and checks if product has any update.
	 *
	 * @param string $new_version The product version from server response.
	 * @param string $old_version The product version currently installed.
	 *
	 * @return bool True if product's new version available on server, false otherwise.
	 */
	private function has_update( string $new_version, string $old_version ): bool {
		return version_compare( $new_version, $old_version, '>' ) ? true : false;
	}

	/**
	 * Gets plugin/theme update data.
	 *
	 * @param string $id    The product ID.
	 * @param mixed  $value The transient value.
	 * @param object $meta  The new product metadata.
	 *
	 * @return mixed
	 */
	private function maybe_get_update( $id, $value, $meta ) {
		if ( 'theme' === $this->type ) {
			// Prepare theme data as update value response. TODO: add later.
			if ( ! isset( $value->response[ $id ] ) ) {
				$value->response[ $id ] = array(
					'new_version' => $meta->version,
				);

				return $value;
			}
		}

		$plugin              = new \stdClass();
		$plugin->id          = $id;
		$plugin->slug        = $this->dirname;
		$plugin->plugin      = $id;
		$plugin->url         = esc_url( "{$this->server_url}/{$this->product_slug}" );
		$plugin->new_version = $meta->version;
		$plugin->icons       = $meta->logo;
		$plugin->banners     = $meta->cover;

		if ( isset( $meta->wp_tested ) ) {
			$plugin->tested = $meta->wp_tested;
		}

		if ( isset( $meta->wp_requires ) ) {
			$plugin->requires_php = $meta->wp_requires;
		}

		// Package comes with meta, set that for update.
		if ( isset( $meta->package ) ) {
			$plugin->package = $meta->package;
		}

		$value->response[ $id ] = $plugin;

		return $value;
	}

	/**
	 * Generates product data by product/client type.
	 *
	 * @param string $key The specific product data value. Possible keys are:
	 * * `string` `id`      - The product data ID (`theme_dir` or `plugin_dir/plugin_file.php`).
	 * * `string` `name`    - The product name (theme name or plugin name).
	 * * `string` `version` - The product currently installed version.
	 * * `string` `page`    - The product (themes.php/plugins.php) admin page.
	 *
	 * @return string|string[] Data in an array if key not given, else value for the key.
	 */
	private function get_product_data( string $key = '' ) {
		if ( 'theme' === $this->type ) {
			$theme = wp_get_theme();

			$data = array(
				'id'      => $theme->get_template(),
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'page'    => 'themes.php',
			);

			return $key ? $data[ $key ] : $data;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$basename = "{$this->dirname}/{$this->filename}";
		$file     = trailingslashit( WP_PLUGIN_DIR ) . $basename;
		$plugin   = get_plugin_data( $file, false );

		$data = array(
			'id'      => $basename,
			'name'    => $plugin['Name'],
			'version' => $plugin['Version'],
			'page'    => 'plugins.php',
		);

		return $key ? $data[ $key ] : $data;
	}

	/**
	 * Gets args for license validation.
	 *
	 * @param string $flag The validation flag.
	 *
	 * @return string[]
	 */
	private function get_validation_args( string $flag ): array {
		return array(
			'form_state' => 'validate',
			'flag'       => $flag,
		);
	}

	/**
	 * Checks for update.
	 *
	 * @param mixed  $value     The transient value.
	 * @param string $transient The transient name. (Requires WP 4.4 or newer).
	 */
	public function check_for_update( $value, $transient ) {
		if ( ! is_object( $value ) ) {
			$value = new \stdClass();
		}

		$data = $this->get_product_data();
		$id   = $data['id'];

		global $pagenow;

		// Do not trigger updates on themes or plugins page on a multisite.
		if ( $data['page'] === $pagenow && is_multisite() ) {
			return $value;
		}

		// WordPress update may have already been triggered and this product update data already exist.
		// There is no need for further processing until WordPress triggeres update again.
		if ( ! empty( $value->response ) && ! empty( $value->response[ $id ] ) ) {
			return $value;
		}

		$product = $this->get_product_info( $transient );

		// Product invalid or no product version, update => invalid.
		if ( ! is_object( $product ) || ! isset( $product->version ) ) {
			return $value;
		}

		// No updates available, update => invalid.
		if ( ! $this->has_update( $product->version, $data['version'] ) ) {
			return $value;
		}

		return $this->maybe_get_update( $id, $value, $product );
	}

	/**
	 * Shows admin notices after license activation/deactivation.
	 *
	 * @param bool $show_count Whether to show remaining count if greater than 1.
	 */
	public function show_notice( bool $show_count = true ) {
		// Nothing to notify if not a valid response or notice already sent.
		if ( ! is_object( $this->response ) ) {
			return;
		}

		$type = 'error';
		$msg  = __( 'Something went wrong. Please contact plugin support.', 'tws-license-manager-client' );

		if ( is_wp_error( $this->response ) ) {
			$msg = $this->response->get_error_message();
		}

		if ( isset( $this->response->data->status ) ) {
			$type   = 'success';
			$status = strtoupper( $this->response->data->status );
			$key    = $this->response->data->license_key;
			$max    = $this->response->data->total_count;

			// Presistent message.
			/* Translators: %1$s - License Key, %2$s - Current license status. */
			$now = sprintf( __( '%1$s - License Key is now %2$s', 'tws-license-manager-client' ), "<b>{$key}</b>", "<b>{$status}</b>" );

			$count_msg = '';

			// More than 1 activation possible and show count is true, show remaining notice.
			if ( 1 < $max && $show_count ) {
				$remain    = $max - $this->response->data->active_count;
				$count_msg = '&nbsp;';

				/* Translators: %1$s - remaining activation count, %2$s - total activation count. */
				$count_msg .= sprintf( __( '%1$s out of %2$s activation remaining for this license.', 'tws-license-manager-client' ), "<b>{$remain}</b>", "<b>{$max}</b>" );
			}

			$msg = sprintf( '%1$s.%2$s', $now, $count_msg );
		}

		echo '<div class="is-dismissible notice notice-' . esc_attr( $type ) . '">' . wp_kses_post( $msg ) . '</div>';
	}

	/**
	 * Shows persistent admin notice if license has never been activated.
	 */
	public function is_not_activated_notice() {
		// Bail if the current product's license page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $this->page_slug === $_GET['page'] ) {
			return;
		}

		$status = array( 'active', 'inactive', 'expired' );

		// Bail if already performed one of these events for product license.
		if ( in_array( $this->get_license( 'status' ), $status, true ) ) {
			return;
		}

		$name = $this->get_product_data( 'name' );

		echo wp_kses_post(
			sprintf(
				'<div class="notice notice-warning">%1$s %2$s %3$s. <p><a href="%4$s" class="button button-primary">%5$s</a></p></div>',
				__( 'Activate license for', 'tws-license-manager-client' ),
				'<b>' . $name . '</b>',
				__( 'to receive automatic updates and support', 'tws-license-manager-client' ),
				esc_url( admin_url( "admin.php?page={$this->page_slug}" ) ),
				__( 'Activate Now', 'tws-license-manager-client' )
			)
		);
	}

	/**
	 * Gets request parameters.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return $this->parameters;
	}

	/**
	 * Gets validated data from license form.
	 *
	 * @return array
	 */
	public function get_validated_data(): array {
		return $this->validated;
	}

	/**
	 * Gets the manager prefix.
	 *
	 * This will be used for hooks, form fields, saving options, etc.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->dirname;
	}

	/**
	 * Gets form validation errors.
	 *
	 * Errors are form fields without any data.
	 *
	 * @return string[]
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Checks if form has any error.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
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
	public function make_request_with( bool $license = true, string $method = 'GET', array $insert_data = array() ) {
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
		$this->route    = "/{$namespace}/{$version}/{$this->endpoint}";

		// Get response from server with given method.
		return $this->client->request( $this->endpoint, $method, $data, $this->parameters, $this->headers );
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

		if ( ! isset( $license->key ) || ! isset( $license->purchased_on ) ) {
			return new \WP_Error(
				'license_data_invalid',
				__( 'Validation failed due to invalid or no license data.', 'tws-license-manager-client' )
			);
		}

		$headers = array(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'Authorization' => 'TWS ' . base64_encode( "{$license->key}/{$license->purchased_on}:{$this->hash}" ),
			'Referer'       => get_bloginfo( 'url' ),
		);

		if ( isset( $license->email ) ) {
			$headers['From'] = $license->email;
		}

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
			$this->client->add_error(
				'license_form_invalid_request',
				__( 'The License key was invalid or no license key was given.', 'tws-license-manager-client' ),
				array( 'status' => 400 )
			);
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
	private function show_license_form( $to_activate = true ) {
		// Form states.
		$query      = array();
		$deactivate = false;
		$status     = (string) $this->get_license( 'status' );
		$value      = $status ? $status : __( 'Not Activated', 'tws-license-manager-client' );
		$logo       = plugin_dir_url( $this->root() ) . '/Assets/logo.png';

		// Set form state for activating or deactivating license.
		if ( $to_activate ) {
			$btn_class  = ' hz_lmac_btn';
			$button     = __( 'Activate', 'tws-license-manager-client' );
			$disabled   = 'active' === $status && $this->disable_form ? ' disabled=disabled' : '';
			$deactivate = 'active' === $status ? true : $deactivate;
			$query      = array( 'step' => 'deactivate' );
			$state      = 'activate';
		} else {
			$btn_class = ' hz_lmdac_btn';
			$button    = __( 'Deactivate', 'tws-license-manager-client' );
			$disabled  = 'inactive' === $status && $this->disable_form ? ' disabled=disabled' : '';
			$state     = 'deactivate';
		}

		/**
		 * WPHOOK: Filter -> license form header.
		 *
		 * Modify the license form header.
		 *
		 * @param string[] $content The header contents. `logo` and `title`.
		 * @param string   $prefix  The current client prefix (directory name).
		 * @var   string[] The filtered  content.
		 */
		$header = apply_filters(
			'hzfex_license_manager_client_license_form_header',
			array(
				'logo'  => $logo,
				'title' => $this->get_product_data( 'name' ),
			),
			$this->dirname
		);

		// Expired license Alert!!!
		$url           = $this->get_renewal_link();
		$content_class = 'expired' === $status || $disabled ? ' disabled' : '';
		?>

		<div id="hz_license_form" class="<?php echo sanitize_key( $value ); ?>">
			<div class="hz_license_form_head">
				<div id="hz_license_branding">
					<div id="logo"><img src="<?php echo esc_url( $header['logo'] ); ?>"></div>
					<h2 id="tagline"><?php echo esc_html( $header['title'] ); ?></h2>
				</div>
				<div id="hz_license_status">
					<span class="label"><?php esc_html_e( 'License Status', 'tws-license-manager-client' ); ?></span>
					<span class="value <?php echo sanitize_key( $value ); ?>"><?php echo esc_html( $value ); ?></span>
				</div>
			</div>
			<?php
			/**
			 * Below event occurs if filters are not applied to vars
			 * and server has renewal feature turned on from setting.
			 *
			 * When the license expires, a new notice will be displayed with below content.
			 * The notice will have a renewal link which when clicked will take user to
			 * the server checkout page with the product for which license key was issued
			 * added to the cart automatically.
			 *
			 * The renewal link contains product ID, license key and client URL.
			 *
			 * License key will be saved in cookie for 10 minutes so user will have enough time
			 * to login and place order. The cookie will be used as the value for "existing license
			 * key" checkout field. After valid order status change is triggered, the license
			 * expiry date will be extended by the same number of days in the license generator
			 * that was used to generate license (generator must be set on product level).
			 */
			if ( $url && 'expired' === $status ) :
				?>
				<div class="hz_expired_notice">
					<p>
						<?php esc_html_e( 'The license has expired. Renew your license in few minutes!', 'tws-license-manager-client' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url_raw( $url ); ?>">
							<?php esc_html_e( 'Renew Now', 'tws-license-manager-client' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			<div class="hz_license_form_content<?php echo esc_attr( $content_class ); ?>">
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

					/**
					 * When license expires, new checkbox (toggle) is displayed.
					 * Input fields and submit button will be disabled by default.
					 *
					 * When checkbox (toggle) is turned on, fields are re-enabled
					 * for user to enter data on respective fields and submit the form
					 * so activation happens with renewed/new license key.
					 */
					if ( 'expired' === $status ) :
						$disabled = ' disabled=disabled';
						?>
						<fieldset class="enableForm hz_switcher_control">
							<label for="enableForm"><?php esc_html_e( 'Got a valid license? Turn on the toggle to activate it.', 'tws-license-manager-client' ); ?></label>
							<input id="enableForm" type="checkbox" class="hz_switcher_control">
						</fieldset>
					<?php endif; ?>

					<fieldset id="hz_activate_plugin"<?php echo esc_attr( $disabled ); ?>>
						<div class="hz_license_key <?php echo esc_attr( $class ); ?>">
							<label for="<?php echo esc_attr( $this->dirname ); ?>[license_key]">
								<p class="label"><?php esc_html_e( 'License Key', 'tws-license-manager-client' ); ?></p>
								<p class="field"><input id="<?php echo esc_attr( $this->dirname ); ?>[license_key]" name="<?php echo esc_attr( $this->dirname ); ?>[license_key]" type="text" value="<?php echo 'expired' !== $status ? esc_attr( $license_key ) : ''; ?>"></p>
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
									<p class="field"><input id="<?php echo esc_attr( $for ); ?>" name="<?php echo esc_attr( $for ); ?>" type="text" value="<?php echo 'expired' !== $status ? esc_attr( $email ) : ''; ?>"></p>
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
									<p class="field"><input id="<?php echo esc_attr( $for ); ?>" name="<?php echo esc_attr( $for ); ?>" type="text" value="<?php echo 'expired' !== $status ? esc_attr( $order_id ) : ''; ?>"></p>
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
							<a class="dashboard" href="<?php echo esc_url_raw( add_query_arg( array( 'referrer' => $this->page_slug ), admin_url() ) ); ?>">← <?php esc_html_e( 'Dashboard', 'tws-license-manager-client' ); ?></a>
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
								<a class="deactivate" href="<?php echo esc_url_raw( add_query_arg( $query ) ); ?>" class="button button-large button-next hz_btn__skip hz_btn__nav"><?php esc_html_e( 'Deactivate', 'tws-license-manager-client' ); ?></a>
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
						 * {@see @method Manager::start()}
						 */
						?>
						<input type="hidden" name="validate_license" value="<?php echo esc_attr( $this->hash ); ?>">
						<input type="hidden" name="tws_license_form" value="<?php echo esc_attr( $state ); ?>">
					</fieldset>
				</form>
				<?php if ( 'expired' === $status ) : ?>
					<script type="text/javascript">
						var btn = jQuery('#enableForm');
						var formWrapper    = jQuery('.hz_license_form_content');
						var activateFields = jQuery('#hz_activate_plugin');
						var activateAction = jQuery('.hz_license_button');
						var isEnabled = () => {
							if( jQuery(btn).is(':checked')) {
								jQuery(activateFields).removeProp('disabled');
								jQuery(activateAction).removeProp('disabled');
								jQuery(formWrapper).removeClass('disabled');
							} else {
								jQuery(activateFields).attr('disabled', 'disabled');
								jQuery(activateAction).attr('disabled', 'disabled');
								jQuery(formWrapper).addClass('disabled');
							}
						};

						isEnabled();
						jQuery('#enableForm').on('click', isEnabled);
					</script>
				<?php endif; ?>
			</div>
		</div>

		<?php
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
			}

			if ( isset( $data[ $this->dirname ][ $key ] ) && 'order_id' === $data[ $this->dirname ][ $key ] ) {
				$this->validated['order_id'] = intval( $data[ $this->dirname ][ $key ] );

				// Catch error for order ID with no data for client side validation.
				if ( empty( $data[ $this->dirname ]['order_id'] ) ) {
					$this->errors['order_id'] = $error;
					$with_data[]              = 0;
				}
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
		 * Fires on license form submenu page.
		 */
	public function loaded() {
		/**
		 * WPHOOK: Fires on license form submenu page.
		 *
		 * @param Manager $client Current instance of license manager client.
		 */
		do_action( $this->dirname . '_license_page_loaded', $this );
	}

	/**
	 * Enqueues necessary styles and scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( $this->dirname . '-style', plugin_dir_url( $this->root() ) . '/Assets/style.css', array(), self::VERSION );
	}

	/**
	 * Parses URL to get the domain.
	 *
	 * @param string $domain The full URI.
	 *
	 * @return string
	 */
	private function parse_url( $domain ) {
		$domain = wp_parse_url( $domain, PHP_URL_HOST );
		$domain = str_replace( 'www.', '', $domain );

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

	/**
	 * Gets root directory name.
	 *
	 * @return string
	 */
	public function root(): string {
		return dirname( __FILE__, 2 );
	}

	/**
	 * Set the license status class to admin body.
	 *
	 * @param string $class The admin body class.
	 */
	public function set_current_license_status( $class ) {
		$status = $this->get_license( 'status' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		/**
		 * WPHOOK: Filter -> Admin license page body class.
		 *
		 * @param bool   $apply Whether to apply color scheme by license status or not.
		 * @param string $page  The current license page.
		 * @var   bool
		 */
		$apply = apply_filters( 'hzfex_license_manager_license_page_body_class', true, $this->page_slug );

		if ( $page === $this->page_slug && $apply ) {
			$class .= 'license-' . $status ? " {$status}" : ' not-activated';
		}

		return $class;
	}

	/**
	 * Product renewal link.
	 *
	 * Filter can be used to return an empty string which in turns disables license renewal notice.
	 *
	 * Following events occur on server:
	 * * Licensed product is added on server cart,
	 * * Server will be redirected to checkout page, and
	 * * Existing license key field will be set with the current license.
	 *
	 * @return string Remote server checkout link with licensed product in cart.
	 */
	private function get_renewal_link() {
		$license_key = (string) $this->get_license( 'license_key' );
		$product_id  = (string) $this->get_license( 'product_id' );

		if ( ! $license_key || ! $product_id ) {
			return '';
		}

		$query = add_query_arg(
			urlencode_deep(
				array(
					'add-to-cart'     => $product_id,
					'tws_license_key' => $license_key,
					'referrer'        => get_bloginfo( 'url' ),
				)
			),
			"{$this->server_url}/checkout"
		);

		/**
		 * WPHOOK: Filter -> Remote check link with license key in query and product in cart.
		 *
		 * @param string $link        The remote check link.
		 * @param string $license_key The current license key.
		 * @param string $product_id  The current product ID.
		 * @var   string
		 */
		$link = apply_filters( 'hzfex_license_manager_client_renew_license_link', $query, $license_key, $product_id );

		return $link;
	}

	/**
	 * Checks license status on scheduled time.
	 */
	public function check_license_status() {
		// Nothing to do for expired license.
		if ( 'expired' === $this->get_license( 'status' ) ) {
			return;
		}

		$response = $this->validate_license( $this->get_validation_args( 'cron' ) );

		if ( is_object( $response ) && isset( $response->success ) && $response->success ) {
			$this->parse_response( $response );
		}
	}

	/**
	 * Starts cron task.
	 *
	 * This must be called either ways by the client type:
	 * * **Theme:** `add_action( 'after_switch_theme', Manager::run_scheduled_task )`.
	 * * **Plugin:** `register_activation_hook( __FILE__, Manager::run_scheduled_task )`.
	 *
	 * It will run a task every day task to validate license and set it's status appropriately.
	 */
	public function run_scheduled_task() {
		if ( ! wp_next_scheduled( $this->schedule ) ) {
			wp_schedule_event( time(), 'daily', $this->schedule );
			wp_schedule_single_event( time() + 60, $this->schedule );
		}
	}

	/**
	 * Stops scheduled task.
	 *
	 * This method must be called either ways by the client type:
	 * * **Theme:** `add_action( 'switch_theme', Manager::terminate_scheduled_task )`.
	 * * **Plugin:** `register_deactivation_hook( __FILE__, Manager::terminate_scheduled_task )`.
	 *
	 * It will terminate every day task to validate license.
	 */
	public function terminate_scheduled_task() {
		wp_clear_scheduled_hook( $this->schedule );
	}
}
