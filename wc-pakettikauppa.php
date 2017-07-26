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
 * Add settings link to the plugins page.
 */
function wc_pakettikauppa_add_settings_link( $links ) {
  $url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wc_pakettikauppa_shipping_method' );
  $link = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';

  return array_merge( array( $link ), $links );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wc_pakettikauppa_add_settings_link' );


// No need to show pickup locations on the cart page, as the price of the order does not depend
// on the pickup location.
// add_action( 'woocommerce_cart_totals_after_shipping', 'wc_pakettikauppa_pickup_point_field_html' );


/**
 * Update the order meta with pakettikauppa_pickup_point field value
 * Example value from checkout page: "DB Schenker: R-KIOSKI TRE AMURI (#6681)"
 * Prefix values with underscore is they should be hidden from the metadata fields list.
 */
function wc_pakettikauppa_update_order_meta_pickup_point_field( $order_id ) {
  if ( ! empty( $_POST['pakettikauppa_pickup_point'] ) ) {
    error_log("saving ". $_POST['pakettikauppa_pickup_point']);
    update_post_meta( $order_id, 'pakettikauppa_pickup_point', sanitize_text_field( $_POST['pakettikauppa_pickup_point'] ) );
    // Find string like '(#6681)'
    preg_match( '/\(#[0-9]+\)/' , $_POST['pakettikauppa_pickup_point'], $matches);
    // Cut the number out from a string of the form '(#6681)'
    $pakettikauppa_pickup_point_id = intval( substr($matches[0], 2, -1) );
    update_post_meta( $order_id, 'pakettikauppa_pickup_point_id', $pakettikauppa_pickup_point_id );
  }
}
add_action( 'woocommerce_checkout_update_order_meta', 'wc_pakettikauppa_update_order_meta_pickup_point_field' );

/*
 * Display pickup point to customer after order
 */
function wc_pakettikauppa_display_order_data( $order ) {

  $pickup_point = $order->get_meta('pakettikauppa_pickup_point');
  if ( ! empty( $pickup_point ) ) {
    echo '
    <h2>'. __('Pickup point', 'wc-pakettikauppa' ) .'</h2>
    <p>'. $pickup_point .'</p>';
  }

}
add_action( 'woocommerce_order_details_after_order_table', 'wc_pakettikauppa_display_order_data' );

function wc_pakettikauppa_show_pickup_point_in_admin_order_meta( $order ) {
  echo '<p><strong>' . __('Requested pickup point', 'wc-pakettikauppa') . ':</strong><br>';
  if ( $order->get_meta('pakettikauppa_pickup_point') ) {
    echo $order->get_meta('pakettikauppa_pickup_point');
    echo '<br>ID: '. $order->get_meta('pakettikauppa_pickup_point_id');
  } else {
    echo __('None');
  }
  echo '</p>';
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_pakettikauppa_show_pickup_point_in_admin_order_meta', 10, 1 );

function wc_pakettikauppa_get_status_text( $status_code ) {
  $status = '';

  switch ( intval($status_code) ) {
    case 13: $status = "Item is collected from sender - picked up"; break;
    case 20: $status = "Exception"; break;
    case 22: $status = "Item has been handed over to the recipient"; break;
    case 31: $status = "Item is in transport"; break;
    case 38: $status = "C.O.D payment is paid to the sender"; break;
    case 45: $status = "Informed consignee of arrival"; break;
    case 48: $status = "Item is loaded onto a means of transport"; break;
    case 56: $status = "Item not delivered – delivery attempt made"; break;
    case 68: $status = "Pre-information is received from sender"; break;
    case 71: $status = "Item is ready for delivery transportation	"; break;
    case 77: $status = "Item is returning to the sender"; break;
    case 91: $status = "Item is arrived to a post office"; break;
    case 99: $status = "Outbound"; break;
    default: $status = "Unknown status: " . $status_code; break;
  }

  return $status;
}

/**
 * Calculate Finnish invoice reference from order ID
 * http://tarkistusmerkit.teppovuori.fi/tarkmerk.htm#viitenumero
*/
function wc_pakettikauppa_calculate_reference( $id ) {
  $weights = array( 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7 );
  $base = str_split( strval( ( $id + 100 ) ) );
  $reversed_base = array_reverse( $base );

  $sum = 0;
  for ( $i = 0; $i < count( $reversed_base ); $i++ ) {
    $coefficient = array_shift( $weights );
    $sum += $reversed_base[$i] * $coefficient;
  }

  $checksum = ( $sum % 10 == 0 ) ? 0 : ( 10 - $sum % 10 );

  $reference = implode( '', $base ) . $checksum;
  return $reference;
}

/**
 * Calculate total weight for this shipment
*/
function wc_pakettikauppa_order_weight( $order ) {
  $weight = 0;

  if ( sizeof( $order->get_items() ) > 0 ) {
    foreach ( $order->get_items() as $item ) {
      if ( $item['product_id'] > 0 ) {
        $product = $order->get_product_from_item( $item );
        if ( ! $product->is_virtual() ) {
          $weight += $product->get_weight() * $item['qty'];
        }
      }
    }
  }

  return $weight;
}

function wc_pakettikauppa_tracking_url( $service_id, $tracking_code ) {
  $tracking_url = '';

  switch ( $service_id ) {
    case 90010:
    case 90030:
    case 90080:
      $tracking_url = "https://www.matkahuolto.fi/seuranta/tilanne/?package_code={$tracking_code}";
      break;
    case 2104:
    case 2461:
    case 2103:
      $tracking_url = "http://www.posti.fi/yritysasiakkaat/seuranta/#/lahetys/{$tracking_code}";
      break;
    default:
      $tracking_url = '';
  }

  return $tracking_url;
}

require_once( 'includes/class-wc-pakettikauppa.php' );

add_action( 'admin_init', function() {
    $wc_pakettikauppa = new WC_Pakettikauppa();
    $wc_pakettikauppa->load();
} );

require_once( 'includes/class-wc-pakettikauppa-shipment-method.php' );
