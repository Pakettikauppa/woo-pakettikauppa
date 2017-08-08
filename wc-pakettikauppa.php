<?php
/**
 * Plugin Name: WooCommerce Pakettikauppa.fi
 * Version: 0.9.0
 * Plugin URI: https://github.com/Seravo/woocommerce-pakettikauppa
 * Description: Pakettikauppa.fi shipping service for WooCommerce. Integrates Prinetti, Matkahuolto, DB Schenker and others.
 * Author: Seravo
 * Author URI: https://seravo.com/
 * Text Domain: pakettikauppa
 * Domain Path: /languages/
 * License: GPL v3 or later
 */

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// @TODO: Also check for other solutions to refer to plugin_basename and plugin_dir_path in includes/ directory
define( 'WC_PAKETTIKAUPPA_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_PAKETTIKAUPPA_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Prepare private directory for pakettikauppa documents.
 */
function wc_pakettikauppa_prepare_directory() {
  $upload_dir = wp_upload_dir();

  @wp_mkdir_p( $upload_dir['basedir'] . '/wc-pakettikauppa' );
  // @TODO: Locate this outside of htdocs and allow access only via X-Sendfile with auth or similar
}
register_activation_hook( __FILE__, 'wc_pakettikauppa_prepare_directory' );

/**
 * Load plugin textdomain
 *
 * @return void
 */
function wc_pakettikauppa_load_textdomain() {
  load_plugin_textdomain( 'wc-pakettikauppa', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'wc_pakettikauppa_load_textdomain' );

require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa-shipment-method.php' );

/**
* Load the WC_Pakettikauppa class when in frontend
*/
function wc_pakettikauppa_load() {
  if ( ! is_admin() ) {
    require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa.php' );
    $wc_pakettikauppa = new WC_Pakettikauppa();
    $wc_pakettikauppa->load();
  }
}
add_action( 'init', 'wc_pakettikauppa_load' );

/**
* Load the WC_Pakettikauppa_Admin class in wp-admin
*/
function wc_pakettikauppa_load_admin() {
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa-admin.php' );
  $wc_pakettikauppa_admin = new WC_Pakettikauppa_Admin();
  $wc_pakettikauppa_admin->load();
}
add_action( 'admin_init', 'wc_pakettikauppa_load_admin' );
