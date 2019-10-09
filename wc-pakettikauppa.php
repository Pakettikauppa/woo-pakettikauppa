<?php
/**
 * Plugin Name: WooCommerce Pakettikauppa
 * Version: 2.0.22
 * Plugin URI: https://github.com/Seravo/woocommerce-pakettikauppa
 * Description: Pakettikauppa shipping service for WooCommerce. Integrates Posti, Smartship, Matkahuolto, DB Schenker and others. Version 2 breaks 1.x pricing settings.
 * Author: Seravo
 * Author URI: https://seravo.com/
 * Text Domain: wc-pakettikauppa
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.4
 * WC tested up to: 3.7.0
 *
 * Copyright: © 2017 Seravo Oy
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

define('WC_PAKETTIKAUPPA_BASENAME', plugin_basename(__FILE__));
define('WC_PAKETTIKAUPPA_DIR', plugin_dir_path(__FILE__));
define('WC_PAKETTIKAUPPA_VERSION', get_file_data(__FILE__, array( 'Version' ), 'plugin')[0]);
define('WC_PAKETTIKAUPPA_TEXT_DOMAIN', 'wc-pakettikauppa');
define('WC_PAKETTIKAUPPA_SHIPPING_METHOD', 'pakettikauppa_shipping_method');
define('WC_PAKETTIKAUPPA_ADMIN', 'wc_pakettikauppa_admin');
define('WC_PAKETTIKAUPPA_URL', 'https://www.pakettikauppa.fi/');

/**
 * Load plugin textdomain
 *
 * @return void
 */
function wc_pakettikauppa_load_textdomain() {
  load_plugin_textdomain(
    WC_PAKETTIKAUPPA_TEXT_DOMAIN,
    false,
    dirname(plugin_basename(__FILE__)) . '/languages/'
  );
}

add_action('plugins_loaded', 'wc_pakettikauppa_load_textdomain');

/**
 * Load WC_Pakettikauppa
 */
function wc_pakettikauppa_load() {
  if ( is_admin() ) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-pakettikauppa-admin.php';
    $wc_pakettikauppa_admin = new WC_Pakettikauppa_Admin();
    $wc_pakettikauppa_admin->load();
  } else {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-pakettikauppa.php';
    $wc_pakettikauppa = new WC_Pakettikauppa();
    $wc_pakettikauppa->load();
  }
}

/**
 * Display an error notice when WooCommerce is not actived.
 */
function wc_pakettikauppa_woocommerce_inactive_notice() {
  echo '<div class="notice notice-error">';
  echo '<p>' . __('WooCommerce Pakettikauppa requires WooCommerce to be installed and activated!', WC_PAKETTIKAUPPA_TEXT_DOMAIN) . '</p>';
  echo '</div>';
}

// This plugin needs WooCommerce to be activated in order to work properly, so
// don't load any plugin functionalities if WooCommerce is not active.
if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true) ) {
  require_once plugin_dir_path(__FILE__) . 'includes/class-wc-pakettikauppa-shipping-method.php';
  wc_pakettikauppa_load();

} else {
  // Alert the site admin when in WP Admin
  add_action('admin_notices', 'wc_pakettikauppa_woocommerce_inactive_notice');
}
