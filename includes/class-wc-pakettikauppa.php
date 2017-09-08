<?php

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/autoload.php' );
require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipment.php' );

/**
 * WC_Pakettikauppa Class
 *
 * @class WC_Pakettikauppa
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
class WC_Pakettikauppa {
  private $wc_pakettikauppa_shipment = null;
  private $errors = array();

  function __construct() {
    $this->id = 'wc_pakettikauppa';
  }

  public function load() {
    add_action( 'enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    add_action( 'woocommerce_review_order_after_shipping', array( $this, 'pickup_point_field_html') );
    add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_data' ) );
    add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_pickup_point_field' ) );

    try {
      $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
      $this->wc_pakettikauppa_shipment->load();

    } catch ( Exception $e ) {
      // @TODO Handle frontend errors, should they be shown to customer?
      die('pakettikauppa fail');
    }
  }

  /**
  * Enqueue frontend-specific styles and scripts.
  */
  public function enqueue_scripts() {
    wp_enqueue_style( 'wc_pakettikauppa', plugin_dir_url( __FILE__ ) . '../assets/css/wc-pakettikauppa.css' );
    wp_enqueue_script( 'wc_pakettikauppa_js', plugin_dir_url( __FILE__ ) . '../assets/js/wc-pakettikauppa.js', array( 'jquery' ) );
  }

  /**
   * Update the order meta with pakettikauppa_pickup_point field value
   * Example value from checkout page: "DB Schenker: R-KIOSKI TRE AMURI (#6681)"
   *
   * @param int $order_id The id of the order to update
   */
  public function update_order_meta_pickup_point_field( $order_id ) {
    if ( ! empty( $_POST['pakettikauppa_pickup_point'] ) ) {
      update_post_meta( $order_id, '_pakettikauppa_pickup_point', sanitize_text_field( $_POST['pakettikauppa_pickup_point'] ) );
      // Find string like '(#6681)'
      preg_match( '/\(#[0-9]+\)/' , $_POST['pakettikauppa_pickup_point'], $matches);
      // Cut the number out from a string of the form '(#6681)'
      $pakettikauppa_pickup_point_id = intval( substr($matches[0], 2, -1) );
      update_post_meta( $order_id, '_pakettikauppa_pickup_point_id', $pakettikauppa_pickup_point_id );
    }
  }

  /*
   * Customize the layout of the checkout screen so that there is a section
   * where the pickup point can be defined. Don't use the woocommerce_checkout_fields
   * filter, it only lists fields without values, and we need to know the postcode.
   * Also the woocommerce_checkout_fields has separate billing and shipping address
   * listings, when we want to have only one single pickup point per order.
   */
  public function pickup_point_field_html( ) {

    $shipping_method_name = explode(':', WC()->session->get( 'chosen_shipping_methods' )[0])[0];
    $shipping_method_id = explode(':', WC()->session->get( 'chosen_shipping_methods' )[0])[1];

    // Bail out if the shipping method is not one of the pickup point services
    if ( ! WC_Pakettikauppa_Shipment::service_has_pickup_points( $shipping_method_id ) ) {
      return;
    }

    $pickup_point_data = '';
    $shipping_postcode = WC()->customer->get_shipping_postcode();

    try {
      $pickup_point_data = $this->wc_pakettikauppa_shipment->get_pickup_points( $shipping_postcode );

      if ( $pickup_point_data == 'Authentication error' ) {
        // @TODO: test if data is a proper array or throw error
      }
    } catch ( Exception $e ) {
      // @TODO: throw error
    }

    $pickup_points = json_decode( $pickup_point_data );
    $options_array = array( '' => '- '. __('Select a pickup point', 'wc-pakettikauppa') .' -' );

    foreach ( $pickup_points as $key => $value ) {
      $pickup_point_key = $value->provider . ': ' . $value->name . ' (#' . $value->pickup_point_id . ')';
      $pickup_point_value = $value->provider . ': ' . $value->name . ' (' . $value->street_address . ')';
      $options_array[ $pickup_point_key ] = $pickup_point_value;
    }

    echo '
    <tr class="shipping-pickup-point">
      <th>' . __('Pickup point', 'wc-pakettikauppa') . '</th>
      <td data-title="' . __('Pickup point', 'wc-pakettikauppa') . '">';

    echo '<p>';
      // Return if the customer has not yet chosen a postcode
    if ( empty( $shipping_postcode ) ) {
      _e( 'Insert your shipping details to view nearby pickup points', 'wc-pakettikauppa' );
      return;
    }
    printf(
        esc_html__( 'Choose one of the pickup points close to your postcode %s below:', 'wc-pakettikauppa' ),
        '<span class="shipping_postcode_for_pickup">'. $shipping_postcode .'</span>'
    );
    echo '</p>';

    woocommerce_form_field( 'pakettikauppa_pickup_point', array(
        'clear'       => true,
        'type'        => 'select',
        'custom_attributes' => array('style' => 'max-width:18em;'),
        'options'     => $options_array,
    ),  null );
    // WC()->cart['pakettikauppa_pickup_point_id']

    echo '</div>';

  }

  /**
   * Display pickup point to customer after order.
   *
   * @param WC_Order $order the order that was placed
   */
  public function display_order_data( $order ) {

    $pickup_point = $order->get_meta('_pakettikauppa_pickup_point');

    if ( ! empty( $pickup_point ) ) {
      echo '
      <h2>'. __('Pickup point', 'wc-pakettikauppa' ) .'</h2>
      <p>'. $pickup_point .'</p>';
    }
  }

}
