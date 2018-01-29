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
       * Default shipping fee.
       *
       * @var int
       */
      public $fee = 5.95;

      /**
      * Constructor for Pakettikauppa shipping class
      *
      * @access public
      * @return void
      */
      public function __construct($instance_id = 0) {
        $this->id = 'WC_Pakettikauppa_Shipping_Method'; // ID for your shipping method. Should be unique.
        $this->instance_id        = absint( $instance_id );

        $this->method_title = 'Pakettikauppa'; // Title shown in admin
        $this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://pakettikauppa.fi/">Pakettikauppa</a>.', 'wc-pakettikauppa' ); // Description shown in admin

        $this->enabled = 'yes';
        $this->title = 'Pakettikauppa';

        $this->init();

      }

    public function validate_pkprice_field( $key, $value ) {

      foreach($value as $_serviceCode => $_serviceSettings) {
        $_serviceSettings['price'] = wc_format_decimal( trim( stripslashes( $value ) ) );
        $_serviceSettings['price_free'] = wc_format_decimal( trim( stripslashes( $value ) ) );
      }
      $values = json_encode($value);

      return $values;
    }

      public function generate_pkprice_html( $key, $value ) {
        $fieldKey = $this->get_field_key( $key );

        if($this->get_option( $key ) != '') {
          $values = json_decode($this->get_option( $key ), true);
        } else {
          $values = array();
        }

        $html = '
          <tr valign="top">
            <th colspan="2"><label>'.esc_html( $value['title'] ).'</label></th>
          </tr>
          <tr>
            <td colspan="2">
              <table>
                <thead>
                  <tr>
                   <th>Service</th>
                   <th style="width: 60px;">Active</th>
                   <th style="text-align: center;">Price</th>
                   <th style="text-align: center;">Free shipping tier</th>
                  </tr>
                </thead>
                <tbody>';
                foreach($value['options'] as $_serviceCode => $_serviceName) {
                  if(!isset($values[$_serviceCode])) {
                    $values[$_serviceCode]['active']  = false;
                    $values[$_serviceCode]['price']  = $this->fee;
                    $values[$_serviceCode]['price_free']  = '0';
                  }

                  $html.='
                  <tr valign="top">
                      <th scope="row" class="titledesc">
                          <label>'. esc_html( $_serviceName). '</label>
                      </th>
                      <td>
                       <input type="hidden" name="'. $fieldKey .'['. $_serviceCode. '][active]" value="no">
                       <input type="checkbox" name="'. $fieldKey .'['. $_serviceCode. '][active]" value="yes" '.($values[$_serviceCode]['active'] == 'yes' ? 'checked': '').'>
                      </td>
                      <td>
                        <input type="number" name="'. $fieldKey .'['. $_serviceCode. '][price]" step="0.01" value="'.($values[$_serviceCode]['price']).'">
                      </td>
                      <td>
                        <input type="number" name="'. $fieldKey .'['. $_serviceCode. '][price_free]" step="0.01" value="'.($values[$_serviceCode]['price_free']).'">
                      </td>
                  </tr>';
                }

                $html.='
              </tbody>
            </table>
            </td>
          </tr>';

          return $html;
        }


      /**
       * Initialize Pakettikauppa shipping
       */
      public function init() {
        // Make Pakettikauppa API accessible via WC_Pakettikauppa_Shipment
        $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment;
        $this->wc_pakettikauppa_shipment->load();

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

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
            'type'        => 'pkprice',
            'options' => $this->wc_pakettikauppa_shipment->services(),
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
        global $woocommerce;

        $cartTotal = $woocommerce->cart->cart_contents_total;

        $shippingSettings = json_decode($this->get_option( 'active_shipping_options' ), true);

        foreach($shippingSettings as $_serviceCode => $_serviceSettings) {

          if ($_serviceSettings['active'] == 'yes') {

            $_shippingCost = $_serviceSettings['price'];

            if ($_serviceSettings['price_free'] < $cartTotal && $_serviceSettings['price_free'] > 0) {
              $_shippingCost = 0;
            }

            $this->add_rate(
              array(
                'id'    => $this->id .':'. $_serviceCode,
                'label' => $this->wc_pakettikauppa_shipment->service_title($_serviceCode),
                'cost'  => (string) $_shippingCost
              )
            );
          } // endif
        } // endforeach
      } // end function
    }
  }
}



add_action( 'woocommerce_shipping_init', 'wc_pakettikauppa_shipping_method_init' );

function add_wc_pakettikauppa_shipping_method( $methods ) {

  $methods[] = 'WC_Pakettikauppa_Shipping_Method';
  return $methods;

}

add_filter( 'woocommerce_shipping_methods', 'add_wc_pakettikauppa_shipping_method' );
