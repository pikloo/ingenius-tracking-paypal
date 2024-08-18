<?php


/**
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Ingenius_Tracking_Paypal
 *
 * @wordpress-plugin
 * Plugin Name:       Ingenius Tracking Paypal
 * Plugin URI:        http://example.com/ingenius-tracking-paypal-uri/
 * Description:       This plugin retrieves tracking numbers and carrier data when an order is paid by paypal and then sends this information to WooCommerce Paypal Payment.
 * Version:           1.0.0
 * Author:            Ingenius
 * Author URI:        https://ingenius.agency/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ingenius-tracking-paypal
 * Domain Path:       /languages
 * Requires Plugins: woocommerce, woocommerce-paypal-payments, aftership-woocommerce-tracking
 */

use IngeniusTrackingPaypal\Includes\Ingenius_Tracking_Paypal;
use IngeniusTrackingPaypal\Includes\Ingenius_Tracking_Paypal_Activator;
use IngeniusTrackingPaypal\Includes\Ingenius_Tracking_Paypal_Check_Dependencies;
use IngeniusTrackingPaypal\Includes\Ingenius_Tracking_Paypal_Deactivator;

// Charger l'autoloader de Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'INGENIUS_TRACKING_PAYPAL_VERSION', '1.0.0' );

define( 'TEXT_DOMAIN', 'ingenius-tracking-paypal');
define( 'PLUGIN_NAME', 'Ingenius Tracking Paypal');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ingenius-tracking-paypal-activator.php
 */
function it_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ingenius-tracking-paypal-activator.php';
	Ingenius_Tracking_Paypal_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ingenius-tracking-paypal-deactivator.php
 */
function it_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ingenius-tracking-paypal-deactivator.php';
	Ingenius_Tracking_Paypal_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'it_activate' );
register_deactivation_hook( __FILE__, 'it_deactivate' );

/**
 * The code check dependencies.
 * This action is documented in includes/class-ingenius-tracking-paypal-check-dependencies.php
 */
function it_check_dependencies() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ingenius-tracking-paypal-check-dependencies.php';
	Ingenius_Tracking_Paypal_Check_Dependencies::check_dependencies();
}

add_action('admin_notices', 'it_check_dependencies');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ingenius-tracking-paypal.php';

/**
 * Begins execution of the plugin.
 *
 */
function it_run() {

	$plugin = new Ingenius_Tracking_Paypal();
	$plugin->run();

}
it_run();