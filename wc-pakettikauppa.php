<?php
/**
 * Plugin Name: WooCommerce Pakettikauppa
 * Version: 2.0.4
 * Plugin URI: https://github.com/Seravo/woocommerce-pakettikauppa
 * Description: Pakettikauppa shipping service for WooCommerce. Integrates Posti, Smartship, Matkahuolto, DB Schenker and others. Version 2 breaks 1.x pricing settings.
 * Author: Seravo
 * Author URI: https://seravo.com/
 * Text Domain: wc-pakettikauppa
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.2
 *
 * Copyright: © 2017 Seravo Oy
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// @TODO: Also check for other solutions to refer to plugin_basename and plugin_dir_path in includes/ directory
define( 'WC_PAKETTIKAUPPA_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_PAKETTIKAUPPA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PAKETTIKAUPPA_VERSION', get_file_data( __FILE__, array( 'Version' ), 'plugin' )[0] );

/**
 * Load plugin textdomain
 *
 * @return void
 */
function wc_pakettikauppa_load_textdomain() {
  load_plugin_textdomain(
    'wc-pakettikauppa',
    false,
    dirname( plugin_basename( __FILE__ ) ) . '/languages/'
  );
}

add_action( 'plugins_loaded', 'wc_pakettikauppa_load_textdomain' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa-shipping-method.php';

/**
 * Load the WC_Pakettikauppa class when in frontend
 */
function wc_pakettikauppa_load() {

  if ( ! is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa.php';
    $wc_pakettikauppa = new WC_Pakettikauppa();
    $wc_pakettikauppa->load();
  }
}

add_action( 'init', 'wc_pakettikauppa_load' );

/**
 * Load the WC_Pakettikauppa_Admin class in wp-admin
 */
function wc_pakettikauppa_load_admin() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-pakettikauppa-admin.php';
  $wc_pakettikauppa_admin = new WC_Pakettikauppa_Admin();
  $wc_pakettikauppa_admin->load();
}

add_action( 'admin_init', 'wc_pakettikauppa_load_admin' );
