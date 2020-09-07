<?php
/**
 * Plugin Name: WooCommerce Pakettikauppa
 * Version: 2.4.0
 * Plugin URI: https://github.com/Seravo/woo-pakettikauppa
 * Description: Pakettikauppa shipping service for WooCommerce. Integrates Posti, Smartship, Matkahuolto, DB Schenker and others.
 * Author: Seravo
 * Author URI: https://seravo.com/
 * Text Domain: woo-pakettikauppa
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.4
 * WC tested up to: 4.0
 *
 * Copyright: © 2017-2020 Seravo Oy
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * Autoloader loads nothing but Pakettikauppa libraries. The classname of the generated autoloader is not unique,
 * whitelabel forks use the same autoloader which results in a fatal error if the main plugin and a whitelabel plugin
 * co-exist.
 */
if ( ! class_exists('\Pakettikauppa\Client') ) {
  require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'core/class-core.php';

class Wc_Pakettikauppa extends Woo_Pakettikauppa_Core\Core {

}

$instance = new Wc_Pakettikauppa(
  array(
    'root' => __FILE__,
    'version' => get_file_data(__FILE__, array( 'Version' ), 'plugin')[0],
    'shipping_method_name' => 'pakettikauppa_shipping_method',
    'vendor_name' => 'Pakettikauppa',
    'vendor_url' => 'https://www.pakettikauppa.fi/',
    'vendor_logo' => 'assets/img/pakettikauppa-logo.png',
    'setup_background' => 'assets/img/pakettikauppa-background.jpg',
    'setup_page' => 'wcpk-setup',
    'pakettikauppa_api_config' => array(
      'test' => array(
        'api_key' => '00000000-0000-0000-0000-000000000000',
        'secret' => '1234567890ABCDEF',
        'base_uri' => 'https://apitest.pakettikauppa.fi',
      ),
      'production' => array(
        'base_uri' => 'https://api.pakettikauppa.fi',
      ),
    ),
    // 'pakettikauppa_api_comment' => 'From WooCommerce', // Overrides default
  )
);
