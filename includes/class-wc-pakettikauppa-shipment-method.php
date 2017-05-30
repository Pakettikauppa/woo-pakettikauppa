<?php
// Prevent direct access to the script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}


/**
 * Pakettikauppa_Shipping_Method Class
 *
 * @class Pakettikauppa_Shipping_Method
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
function wc_pakettikauppa_shipping_method_init() {

  if ( ! class_exists( 'WC_Pakettikauppa_Shipping_Method' ) ) {

    class WC_Pakettikauppa_Shipping_Method extends WC_Shipping_Method {

      /**
      * Constructor for Posti shipping class
      *
      * @access public
      * @return void
      */
      public function __construct() {

        $this->id = 'WC_Pakettikauppa_Shipping_Method'; // ID for your shipping method. Should be unique.
        $this->method_title = 'Pakettikauppa.fi'; // Title shown in admin
        $this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://pakettikauppa.fi/">Pakettikauppa.fi</a>.', 'wc-pakettikauppa' ); // Description shown in admin
        $this->enabled = 'yes';

        $this->init();

      }

      /**
      * Init settings
      *
      * @access public
      * @return void
      */
      function init() {
        global $wc_pakettikauppa;

        $services = WC_Pakettikauppa::services();

        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

      }
    }
  }
}

add_action( 'woocommerce_shipping_init', 'wc_pakettikauppa_shipping_method_init' );

function add_wc_pakettikauppa_shipping_method( $methods ) {

  $methods[] = 'WC_Pakettikauppa_Shipping_Method';
  return $methods;

}

add_filter( 'woocommerce_shipping_methods', 'add_wc_pakettikauppa_shipping_method' );
