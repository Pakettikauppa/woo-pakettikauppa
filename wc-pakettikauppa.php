<?php
/**
 * Plugin Name: WooCommerce Pakettikauppa
 * Version: 3.4.0
 * Plugin URI: https://github.com/Seravo/woo-pakettikauppa
 * Description: Pakettikauppa shipping service for WooCommerce. Integrates Posti, Smartship, Matkahuolto, DB Schenker and others.
 * Author: Pakettikauppa
 * Author URI: https://pakettikauppa.fi/
 * Text Domain: woo-pakettikauppa
 * Domain Path: /core/languages
 * License: GPL v3 or later
 *
 * Tested up to: 5.8.2
 * WC requires at least: 3.4
 * WC tested up to: 6.0.0
 *
 * Copyright: © 2017-2020 Seravo Oy, 2020-2021 Posti Oy
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
    'vendor_fullname' => 'Woocommerce Pakettikauppa',
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
    'tracking_base_url' => 'https://www.pakettikauppa.fi/seuranta/?',
    // 'pakettikauppa_api_comment' => 'From WooCommerce', // Overrides default
    'max_weight' => array( //kg
      '2461' => 2, //Pikkupaketti
      '2103' => 25, //Postipaketti
      '2108' => 25, //Palautus
      '2104' => 25, //Kotipaketti
      '2102' => 25, //Express-paketti
      '2331' => 35, //Postipaketti Baltia
      '2711' => 31.5, //Parcel Connect
      '2017' => 30, //EMS
      '2015' => 30, //Priority
      '2317' => 35, //Express Business day
      //??? => 250, //Express rahti: rullakko
      //??? => 500, //Express rahti: teholava
      //??? => 1000, //Express rahti: lava
      '90084' => 30, //XXS
      '90080' => 35, //Lähellä-paketti
      '90034' => 35, //Kotijakelu
      '90030' => 35, //Jakopaketti
      '90010' => 35, //Bussipaketti
      '80010' => 20, //Noutopistepaketti
      '70010' => 35, //Euro Business Parcel
      '60002' => 2, //Lähetys
      '60001' => 2, //Pieni lähetys
    ),
  )
);

