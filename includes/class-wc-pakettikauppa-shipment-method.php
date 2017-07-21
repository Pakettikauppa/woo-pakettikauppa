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
        $this->method_title = 'Pakettikauppa.fi'; // Title shown in admin
        $this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://pakettikauppa.fi/">Pakettikauppa.fi</a>.', 'wc-pakettikauppa' ); // Description shown in admin

        $this->enabled = 'yes';
        $this->title = 'Pakettikauppa.fi';

        $this->init();
      }

      /**
       * Initialize Pakettikauppa.fi shipping
       */
      public function init() {
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
          'active_shipping_options' => array(
            'title'   => __( 'Active shipping options', 'wc-pakettikauppa' ),
            'type'    => 'multiselect',
            'options' => WC_Pakettikauppa::services(),
          ),
          'fee' => array(
            'title'       => __( 'Fixed fee (€)', 'wc-pakettikauppa' ),
            'type'        => 'price',
            'description' => __( 'Default fixed price for all Pakettikauppa.fi shipping methods.', 'wc-pakettikauppa' ),
            'desc_tip'    => true,
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

        foreach ( WC_Pakettikauppa::services() as $key => $value ) {
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
