## License Manager Server Handler:

File Server.php must be placed in your server WordPress installation. It can be done in one of the following ways:
- Copy `Server.php` file and place it inside `wp-content/mu-plugins` folder. If the `mu-plugins` folder doesn't exist, create one and place this file inside it. This way it will be auto-activated by WordPress, or
- Copy to *root directory* of your *active theme* and require it in functions.php like so:
	- `require_once get_template_directory() . '/Server.php';`

> Do not use both methods described above as the same class exists twice, which is PHP Fatal Error.

---

Once the server validation file is included, then you can make some adjustments. Open your active theme functions.php file to initialize the server instance.
```php
<?php
use TheWebSolver\License_Manager\REST_API\Manager;

// Uncomment below if you copied the "Server.php" file to your server WordPress theme root directory (instead of "wp-content/mu-plugins").
// if ( ! class_exists( '\TheWebSolver\License_Manager\REST_API\Manager' ) ) {
// 	require_once get_template_directory() . '/Server.php';
// }

$server = new Manager();
$server->set_validation(
	array(
		'license_key' => __( 'License key not found.', 'tws-license-manager-server' ),
		'email'       => __( 'Email address not found.', 'tws-license-manager-server' ),
		'order_id'    => __( 'Order not found.', 'tws-license-manager-server' ),
		'name'        => __( 'Product not found.', 'tws-license-manager-server' ),
	)
);
$server->validate();
$server->update();
```
Let's breakdown the above code into steps:
- First, initialize the server manager so our request validation and response modification get applied.
- It accepts two parameters:
	- **$debug** is used for testing purposes. No need to set any constants on the `wp-config.php` file here like that of the client.
	- **$license** is the request route endpoint. It must be `true` when debug is set to `false`. The endpoint will be appended after the version. Set this to ***true*** for `licenses`, ***false*** for `generators`. The final ***endpoint*** will then be `/lmfwc/v2/licenses` or `/lmfwc/v2/generators` depending on what you set its value.

#### Validation
- `Manager::set_validation` keys with their respective error message. Default supported validation keys are `email`, `order_id`, and `name`. `license_key` is optional but it is highly recommended to add here for showing a validation error.
- Though the method name is the same as the client method, it is just for sending back error messages if validation failed.
	- **Validation Keys** should be the same that you set for the client. For eg. `order_id` is set here *(on the server)* and it is set in *client site* also. On the server, the order ID didn't match with that of the license form field **(Purchase Order ID)** on *client site*. In that case, the error message you set here *(on the server)* will be returned as **WP_Error()** message and the same will be displayed as admin notice on *client site*.
	- **Validation Error** is for displaying an error message of the respective field if that field validation fails. _It will not be used for client error_. For client error, check it [here](https://github.com/thewebsolver/tws-license-manager-client/blob/master/CLIENT.md#validation).

### Validate
Every request validation happens at this stage.
- If the request is made and server debug mode is off, then
	- authorization of license form is checked (which is passed as request header `From` from the client)
	- activate/deactivate endpoint is checked
	- request route and route created from the license form endpoint is matched
	- license key from the license form must be present on the request route
	- client URL, active status, activate/deactivate form state, and email (if set for validation from the client) must not already exist as license metadata (these are saved at [later stage](#License-meta-keyvalue) when sending back response)

Each of the above steps will return `WP_Error()` if validation failed and no response will be sent.
Further checks are made if everything at this stage is valid.

>Unique meta key is generated from client site URL (which is passed as request header `Referer` from the client) that will be the part of meta key explained [later](#License-meta-keyvalue).

- Each validation keys passed from [client](https://github.com/thewebsolver/tws-license-manager-client/blob/master/CLIENT.md#validation) are checked. If any one of those failed to match, then `WP_Error()` is sent back instead of a response.
- There is a `hzfex_license_manager_server_request_validation` action hook if any additional checks to be performed by you except those default set from [client](https://github.com/thewebsolver/tws-license-manager-client/blob/master/CLIENT.md#validation).

### Update
- It accepts two parameters:
	- **$status** `true` to update the license status as active/inactive and vice-versa. If the given license key can be activated only once, then its status will be changed on the server also.
	- **$data** Additional metadata to save as license meta value on the server after license activation.

>If debug mode is on, request query parameters not found, or form state from request query parameters can not create response route as `v2/licenses/activate/{license_key}` or `v2/licenses/deactivate/{license_key}`, then the response data are sent back to the client as-is.

If all the above requirements are valid, then updates happen.

### License meta-key/value
By default, meta keys and meta values are generated for the license key from the request parameters.
- **Meta key** - It will be auto-generated and will be something like `data-clienturlcom`. `data-` being the prefix and rest generated from client URL using method `Manager::parse_url()`.
- **Meta value** -  By default *email* (if it is passed for validation from client), *url*, and *status* will be saved with the above meta key. On every form activate/deactivate, the *status* gets updated to ***active/inactive***.
- Updated values with `key`, `email`, `state`, and `status` (if the license can only be activated once) will then be sent back along with other default responses.
