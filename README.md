[![GPL License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

<p align="center">
  <a href="https://github.com/TheWebSolver/tws-license-manager-client">
    <img src="REST_API/Assets/logo.png" alt="Logo" width="80" height="80">
  </a>
</p>

<h1 align="center">TWS License Manager Client</h1>

HANDLE PREMIUM PLUGIN LICENSES WITH YOUR OWN LICENSING SERVER BUILT ON WORDPRESS & WOOCOMMERCE

This plugin is to be included in the plugin being sold (client plugin) so user who purchase the plugin can easily activate/deactivate their license.

## Installation (via Composer):
### Inside client plugin
To install this plugin, edit your `composer.json` file:
```json
"require": {
	"thewebsolver/tws-license-manager-client": "dev-master"
}
```
Then from terminal, run:
```sh
$ composer install
```

### On Server
[License Manager for WooCommerce][server-plugin] must be installed and activated on server WordPress installation for this client to interact, validate, activate/deactivate license.

## Example Codes:
- Server docs on [License Manager][server-docs].
- [CLIENT.md](https://github.com/thewebsolver/tws-license-manager-client/blob/master/CLIENT.md) contains all the codes that will intialize license manager client, add submenu page to display activation/deactivation form and get response back from license manager server when valid data are submitted.
- [SERVER.md](https://github.com/thewebsolver/tws-license-manager-client/blob/master/SERVER.md) contains all codes that will validate the request from client site and activate the license.

## In brief, you must make changes to codes shown in `CLIENT.md` file:
- Set server URL `Client_Plugin::SERVER_URL` where your license manager server is ([License Manager for WooCommerce][server-plugin] plugin is installed, and activated),
- Set parent menu slug `Client_Plugin::PARENT_SLUG` (or create your own menu/submenu page, which is the recommended way),
- Set cunsumer key, consumer secret, validation fields and their error message inside method `Client_Pligin::start()` Generate them on server.
>To test license key, there must be a ***valid license key*** generated on ***server*** with proper WooCommerce Checkout ***checkout*** and order status changed to ***completed***.

<!-- CONTACT -->
## Contact

```sh
----------------------------------
DEVELOPED-MAINTAINED-SUPPPORTED BY
----------------------------------
███║     ███╗   ████████████████
███║     ███║   ═════════██████╗
███║     ███║        ╔══█████═╝
 ████████████║      ╚═█████
███║═════███║      █████╗
███║     ███║    █████═╝
███║     ███║   ████████████████╗
╚═╝      ╚═╝    ═══════════════╝
 ```
 Shesh Ghimire - [@hsehszroc](https://twitter.com/hsehszroc)

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[license-shield]: https://www.gnu.org/graphics/gplv3-or-later-sm.png
[license-url]: https://github.com/TheWebSolver/repo/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/LinkedIn-blue?style=flat-square&logo=linkedin&color=blue
[linkedin-url]: https://www.linkedin.com/in/sheshgh/
[server-plugin]: https://wordpress.org/plugins/license-manager-for-woocommerce/
[server-docs]: https://www.licensemanager.at/docs/