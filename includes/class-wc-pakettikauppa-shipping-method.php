<?php

// Prevent direct access to the script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once( plugin_dir_path( __FILE__ ) . '/class-wc-pakettikauppa.php' );
require_once( plugin_dir_path(__FILE__ ) . '/class-wc-pakettikauppa-shipment.php' );

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
      * Required to access pakettikauppa client
      */
      private $wc_pakettikauppa_shipment = null;

      /**
       * Default active shipping options.
       *
       * @var array
       */
      public $active_shipping_options = array(
        '2103',
        '2461',
        '80010',
        '90010',
        '90080'
      );

      /**
       * Default shipping fee.
       *
       * @var int
       */
      public $fee = 5;

      /**
      * Constructor for Pakettikauppa shipping class
      *
      * @access public
      * @return void
      */
      public function __construct() {
        $this->id = 'WC_Pakettikauppa_Shipping_Method'; // ID for your shipping method. Should be unique.
        $this->method_title = 'Pakettikauppa'; // Title shown in admin
        $this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://pakettikauppa.fi/">Pakettikauppa</a>.', 'wc-pakettikauppa' ); // Description shown in admin

        $this->enabled = 'yes';
        $this->title = 'Pakettikauppa';

        $this->init();
      }

      /**
       * Initialize Pakettikauppa shipping
       */
      public function init() {
        // Make Pakettikauppa API accessible via WC_Pakettikauppa_Shipment
        $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment;
        $this->wc_pakettikauppa_shipment->load();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->active_shipping_options = $this->get_option( 'active_shipping_options', $this->active_shipping_options );
        $this->fee = $this->get_option( 'fee', $this->fee );

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
      }

      /**
       * Init form fields.
       */
      public function init_form_fields() {
        $this->form_fields = array(

          'mode' => array(
            'title'    => __( 'Mode', 'wc-pakettikauppa' ),
            'type'     => 'select',
            'default'  => 'test',
            'options'  => array(
              'test' => __( 'Test environment', 'wc-pakettikauppa' ),
              'production' => __( 'Production environment', 'wc-pakettikauppa' ),
            ),
          ),

          'account_number' => array(
            'title'    => __( 'API key', 'wc-pakettikauppa' ),
            'desc'     => __( 'API key provided by Pakettikauppa', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          'secret_key' => array(
            'title'    => __( 'API secret', 'wc-pakettikauppa' ),
            'desc'     => __( 'API Secret provided by Pakettikauppa', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          /* Start new section */
          array(
            'title' => __( 'Shipping settings', 'wc-pakettikauppa' ),
            'type' => 'title',
          ),

          'active_shipping_options' => array(
            'title'   => __( 'Active shipping options', 'wc-pakettikauppa' ),
            'type'    => 'multiselect',
            'options' => $this->wc_pakettikauppa_shipment->services(),
            'description' => __( 'Press and hold Ctrl or Cmd to select multiple shipping methods.', 'wc-pakettikauppa' ),
            'desc_tip'    => true,
          ),

          'fee' => array(
            'title'       => __( 'Fixed fee (€)', 'wc-pakettikauppa' ),
            'type'        => 'price',
            'description' => __( 'Default fixed price for all Pakettikauppa shipping methods.', 'wc-pakettikauppa' ),
            'desc_tip'    => true,
          ),

          'add_tracking_to_email' => array(
            'title'     => __( 'Add tracking link to the order completed email', 'wc-pakettikauppa' ),
            'type'     => 'checkbox',
            'default'  => 'no',
          ),

          'pickup_points_search_limit' => array(
            'title'     => __( 'Pickup point search limit', 'wc-pakettikauppa' ),
            'type'     => 'number',
            'default'  => 5,
            'description' => __( 'Limit the amount of nearest pickup points shown.', 'wc-pakettikauppa' ),
            'desc_tip'    => true,
          ),

          /* Start new section */
          array(
            'title' => __( 'Store owner information', 'wc-pakettikauppa' ),
            'type' => 'title',
          ),

          'sender_name' => array(
            'title'    => __( 'Sender name', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

          'sender_address' => array(
            'title'    => __( 'Sender address', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

          'sender_postal_code' => array(
            'title'    => __( 'Sender postal code', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

          'sender_city' => array(
            'title'    => __( 'Sender city', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

          'cod_iban' => array(
            'title'    => __( 'Bank account number for Cash on Delivery (IBAN)', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

          'cod_bic' => array(
            'title'    => __( 'BIC code for Cash on Delivery', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
          ),

        );
      }

      /**
       * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
       * Return only active shipping methods.
       *
       * @uses WC_Shipping_Method::add_rate()
       *
       * @param array $package Shipping package.
       */
      public function calculate_shipping( $package = array() ) {

        foreach ( $this->wc_pakettikauppa_shipment->services() as $key => $value ) {
          if ( in_array($key, $this->active_shipping_options) ) {
            $this->add_rate(
              array(
                'id'    => $this->id .':'. $key,
                'label' => $value,
                'cost'  => $this->fee
              )
            );
          }

        }

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
