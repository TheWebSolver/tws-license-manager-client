## License Manager Client Handler example codes:

For debugging purpose, set the debug constant on `wp-config.php`.

```php
define( 'TWS_LICENSE_MANAGER_CLIENT_DEBUG', true );
```

Quick test codes (when debug constant is set to `true`):

```php
function test_client_manager() {
	// replace parameters with your own.
	$manager = new Manager( 'my-plugin-folder-name', 'my-plugin-main-file-name.php' );

	$manager->validate_with(
		array(
			'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
			'email'       => __( 'Enter valid/same email address used at the time of purchase.', 'tws-license-manager-client' ),
			'order_id'    => __( 'Enter same/valid purchase order ID.', 'tws-license-manager-client' ),
			'slug'        => 'my-plugin-with-license-manager-client',
		)
	)
	->authenticate_with(
		'ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', // replace with consumer key.
		'cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', // replace with consumer secret.
	)
	->hash_with( 'server-secret-key' );
	// ->set_key_or_id( 'THE-LICENSE-KEY-HERE' ) // uncomment this to get the given license key data.
	// ->set_key_or_id( '1' ) // uncomment this to get the first generator data.
	->connect_with( esc_url( 'serverurl.com' ), array( 'verify_ssl' => 0 ) ) // replace server url.
	->disable_form();

	if ( $manager->client->has_error() ) {
		$response = $manager->client->get_error();
	} else {
		$response = $manager->make_request_with( 'licenses' );
		// $response = $manager->make_request_with( 'generators' ); // uncomment this to request generators (comment above code if this is uncommented).
	}

	echo '<pre>'; print_r( $response ); echo '</pre>';
}
add_action( 'admin_notices', 'test_client_manager' );
```

The above code will get data from the server when you are on WordPress admin and debug mode set to `true`.

Now let's create the actual codes for the selling plugin with license form to enter the license key and other additional [validation fields set](#Validation).

```php
use TheWebSolver\License_Manager\API\Manager; // The client manager class.

class Client_Plugin_License_Handler {
	const SERVER_URL = 'test.tws'; // License server URL.
	const PARENT_SLUG = 'index.php'; // Dashboard as main menu for license submenu page.
	public $dirname;
	public $manager;
	private $response;

	/**
	 * Instantiate singleton class.
	 */
	public static function init() {
		static $plugin;

		if ( ! is_a( $plugin, get_class() ) ) {
			$plugin = new self();
		}

		return $plugin;
	}
}
```

Let's breakdown the above code into steps:

- Create a class to handle license activation/deactivation.
- Set constant `Client_Plugin_License_Handler::SERVER_URL` where [License Manager for WooCommerce](https://wordpress.org/plugins/license-manager-for-woocommerce/) plugin is installed and activated.
- Set constant `Client_Plugin_License_Handler::PARENT_SLUG` where submenu page for the license activation/deactivation form will be added. This can be set as an empty string if you want to handle submenu creation at a different file of your plugin. Example for this at the [end](#submenu).

```php
private function __construct() {
	// Include the client manager from composer autoload.
	require_once __DIR__ . '/vendor/autoload.php';
	$this->dirname = basename( dirname( __FILE__ ) );

	if ( is_admin() ) {
		// Initialize the license manager client handler.
		$this->manager = new Manager( $this->dirname, basename( __FILE__ ), self::PARENT_SLUG );

		add_action( 'after_setup_theme', array( $this, 'start' ), 5 );
		add_action( 'admin_notices', array( $this, 'show_license_notice' ) );
	}
}
```

Let's breakdown the above code into steps:

- Inside the constructor, we require all license manager client files from composer autoload.
- Set the property `Client_Plugin_License_Handler::$dirname` to the selling plugin folder name. This will be used for WordPress hooks, creating slugs, creating license forms, saving options, etc. In other words, dirname will be used as the **_prefix_**.
- Check if on admin interface and initialize manager class. It will handle everything on the client site.
- Start manager with method `Client_Plugin_License_Handler::start()` but only at `after_setup_theme` action hook.
- Show license active/inactive/error notice with `Client_Plugin_License_Handler::show_license_notice()` hooked to `admin_notices` action hook.

```php
public function start() {
	$this->manager->validate_with(
		array(
			'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
			'email'       => __( 'Enter valid/same email address used at the time of purchase.', 'tws-license-manager-client' ),
			'order_id'    => __( 'Enter same/valid purchase order ID.', 'tws-license-manager-client' ),
			'slug'        => 'my-plugin-with-license-manager-client',
		)
	)
	->authenticate_with(
		'ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
		'cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
	)
	->hash_with( 'server-secret-key' )
	->connect_with(
		esc_url( self::SERVER_URL ),
		array(
			'timeout'           => 15,
			'namespace'         => 'lmfwc',
			'version'           => 'v2',
			'verify_ssl'        => 0,
			'query_string_auth' => true,
		)
	)
	->disable_form(); // true to disable after license active/deactive, false for not disabling form fields.
}
```

Lets breakdown the above code in steps:

#### Validation

- First, `Client_Plugin_License_Handler::$manager::validate_with()` keys with their respective error message. Default supported validation keys are `email`, `order_id` and `slug`. `license_key` is optional but it is highly recommended to add here for showing validation error.
  - **Validation Keys** will set/unset the respective license form field. Let's say, `order_id` was not set, then **_Purchase Order ID_** field won't be displayed on license form as well as it will not be checked on the server also whether the license key was actually for that Order ID.
  - **Validation Error** is for displaying an error message of the respective field if that field is left empty after activate/deactivate button is clicked. _It will not be used for server error_. For server error, check it [here](https://github.com/thewebsolver/tws-license-manager-client/blob/master/SERVER.md#validation)

#### Validation Keys

- Second, `Client_Plugin_License_Handler::$manager::authenticate_with()` is self explanatory. Set the consumer key and consumer secret that has been generated on the server for the user with the capabilities to handle the server. Usually user with `manage_options` capability.
- Also, use `Client_Plugin_License_Handler::$manager::hash_with()` method to set the product secret key for authorization.

#### Connection Details

- lastly, `Client_Plugin_License_Handler::$manager::connect_with()` will set the server URL to handle license validation. The same constant `Client_Plugin_License_Handler::SERVER_URL` is passed here. Optionally, other options can be passed. For production use, `verify_ssl` must be set to `2` (server must also be on **_HTTPS_**).

#### Disable Form

- Highly recommended to set `Client_Plugin_License_Handler::$manager::disable_form()` to `true` on production so no multiple activation/deactivation attempt happens for already active/inactive license.

```php
public function show_license_notice() {
	$this->manager->show_notice( true );
}
```

The above code simply shows a response message after the **Activate/Deactivate** button is clicked.

- `Client_Plugin_License_Handler::$manager::show_notice()` takes one parameter to show or not to show the total number of activation remaining. This only works if [Activation](https://www.licensemanager.at/docs/handbook/license-keys/overview/) for the license is set to more than `1`.

---

### Useful method/properties

- `Client_Plugin_License_Handler::init()->manager->get_license()` - Get the license data. You can pass additional parameter to get the data you want. Some of the useful ones are: `status`, `valid_for`, `expires_at`, and `total_count`. For full list, see the method. `{@filesource - tws-license-manager-client/Includes/API/Manager.php}`
- `Client_Plugin_License_Handler::init()->manager->option` - The Options API key. Useful to get the actual response data saved to database.
	- Geting value: `get_option( Client_Plugin_License_Handler::init()->manager->option )`

---

All of the above codes are compiled as complete code below. It is a fully working example that you can copy to your selling plugin. Just make changes where applicable and you are good to go. Also, needless to say, you must have at least one license key on the server to test it.

```php
<?php

use TheWebSolver\License_Manager\API\Manager;

/**
 * The Web Solver License Manager Client Plugin class.
 */
class Client_Plugin {
	/**
	 * The Server URL to perform API query.
	 *
	 * @var string
	 */
	const SERVER_URL = 'test.tws';

	/**
	 * The main menu slug where license form submenu page to be hooked. Defaults to dashboard menu.
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'index.php';

	/**
	 * Used as the plugin prefix.
	 *
	 * Defaults to the plugin folder name where this client be included.
	 * Recommended to include this file to the root of the selling plugin.
	 *
	 * @var string
	 */
	public $dirname;

	/**
	 * License Manager.
	 *
	 * @var Manager
	 */
	public $manager;

	/**
	 * Response from server.
	 *
	 * @var stdClass|WP_Error
	 */
	private $response;

	/**
	 * Get instance.
	 *
	 * @return Client_Plugin
	 */
	public static function init() {
		static $plugin;

		if ( ! is_a( $plugin, get_class() ) ) {
			$plugin = new self();
		}

		return $plugin;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		require_once __DIR__ . '/vendor/autoload.php';
		$this->dirname = basename( dirname( __FILE__ ) );

		if ( is_admin() ) {
			// Initialize the license manager client handler.
			$this->manager = new Manager( $this->dirname, basename( __FILE__ ), self::PARENT_SLUG );

			add_action( 'after_setup_theme', array( $this, 'start' ), 5 );
		}
	}

	/**
	 * Sets request validation parameters including consumer key and consumer secret.
	 */
	public function start() {
		$this->manager
		->validate_with(
			array(
				'license_key' => __( 'Enter a valid license key.', 'tws-license-manager-client' ),
				'email'       => __( 'Enter valid/same email address used at the time of purchase.', 'tws-license-manager-client' ),
				'order_id'    => __( 'Enter same/valid purchase order ID.', 'tws-license-manager-client' ),
				'slug'        => 'my-plugin-with-license-manager-client',
			)
		)
		->authenticate_with(
			'ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
			'cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
		)
		->hash_with( 'server-secret-key' )
		->connect_with(
			esc_url( self::SERVER_URL ),
			array(
				'timeout'           => 15,
				'namespace'         => 'lmfwc',
				'version'           => 'v2',

				// set 0 if no HTTPS verification needed (not recommended).
				'verify_ssl'        => 0,

				// Only works if passed as query in BasicAuth authentication (if site has SSL).
				// If set to false, $_SERVER['PHP_AUTH_USER'] & $_SERVER['PHP_AUTH_PW']
				// must be set for authorization on server site.
				// {@filesource license-manager-for-woocommerce/includes/api/Authentication.php}.
				'query_string_auth' => true,
			)
		)
		->disable_form( true );
	}

} // class end.

// Initialize the client plugin.
Client_Plugin::init();
```

---

### Submenu

Below code shows adding submenu differently without setting it from `Client_Plugin_License_Handler` directly.

> Note that page slug and function must be the same from the manager for this to work.

Check and perform task in this order:

- **STEP 1.** Check if submenu is already added by `Client_Plugin::PARENT_SLUG`.
- **STEP 2.** Add submenu to the "Settings" main menu with page slug defined in `Client_Plugin::$manager::$page_slug`. **_Must use this slug_**.
- **STEP 3.** Generate form for the submenu page from the method defined in `Client_Plugin::$manager::generate_form()`. **_Must use this method_**.

**Each step is commented below for easier understanding.**

```php
// STEP: 1.
if ( '' === Client_Plugin::PARENT_SLUG ) {
	/**
	 * Any menu page can be used to add a license page.
	 *
	 * It can be a menu or submenu page.
	 * As an example, the license page is added as a submenu page under the "settings" menu.
	 */
	function add_license_menu() {
		add_options_page(
			__( 'Activate License', 'tws-license-manager-client' ),
			__( 'Activate License', 'tws-license-manager-client' ),
			'manage_options',
			Client_Plugin::init()->manager->page_slug, // STEP: 2.
			array( Client_Plugin::init()->manager, 'generate_form' ) // STEP: 3.
		);
	}
	add_action( 'admin_menu', 'add_license_menu' );
}

```