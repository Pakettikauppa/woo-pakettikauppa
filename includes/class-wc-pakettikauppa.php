<?php

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Client.php');

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
  private $pakettikauppa = null;
  private $errors = array();

  function __construct() {
    $this->id = 'wc_pakettikauppa';
  }

  public function load() {
    add_action( 'enqueue_scripts', array( $this, 'enqueue_scripts' ) );

    add_action( 'woocommerce_review_order_after_shipping', array( $this, 'pickup_point_field_html') );
    add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_data' ) );
    add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_pickup_point_field' ) );

    $this->wc_pakettikauppa_client = null;

    try {
      // Use option from database directly as WC_Pakettikauppa_Shipping_Method object is not accessible here
      $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
      $account_number = $settings['mode'];
      $secret_key = $settings['secret_key'];
      $mode = $settings['mode'];
      $is_test_mode = ($mode == 'production' ? false : true);
      $this->wc_pakettikauppa_client = new Pakettikauppa\Client( array( 'api_key' => $account_number, 'secret' => $secret_key, 'test_mode' => $is_test_mode ) );

    } catch ( Exception $e ) {
      // @TODO handle errors
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
   * @TODO: Prefix values with underscore is they should be hidden from the metadata fields list.
   *
   * @param int $order_id The id of the order to update
   */
  public function update_order_meta_pickup_point_field( $order_id ) {
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

  /**
  * Return pickup points near a location specified by the parameters.
  *
  * @param int $postcode The postcode of the pickup point
  * @param string $street_address The street address of the pickup point
  * @param string $country The country in which the pickup point is located
  * @param string $service_provider A service that should be provided by the pickup point
  * @return array The pickup points based on the parameters, or empty array if none were found
  */
  public function get_pickup_points( $postcode, $street_address = null, $country = null, $service_provider = null ) {
    try {
      $pickup_point_data = $this->wc_pakettikauppa_client->searchPickupPoints( $postcode, $street_address, $country, $service_provider);
      if ( $pickup_point_data == 'Authentication error' ) {
        // @TODO: Add proper error handling
      }
      return $pickup_point_data;
    } catch ( Exception $e ) {
      $this->add_error( 'Unable to connect to Pakettikauppa service.' );
      return [];
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
    if ( ! in_array(
            $shipping_method_id,
            array(
              '2103',
              '80010',
              '90010',
              '90080'
            )
          )
        ) {
      return;
    }

    $pickup_point_data = '';
    $shipping_postcode = WC()->customer->get_shipping_postcode();

    try {
      $pickup_point_data = $this->get_pickup_points( $shipping_postcode );

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

  /*
   * Display pickup point to customer after order.
   */
  public function display_order_data( $order ) {

    $pickup_point = $order->get_meta('pakettikauppa_pickup_point');
    if ( ! empty( $pickup_point ) ) {
      echo '
      <h2>'. __('Pickup point', 'wc-pakettikauppa' ) .'</h2>
      <p>'. $pickup_point .'</p>';
    }
  }

  /**
   * Get all available shipping services.
   *
   * @return array Available shipping services
   */
  public static function services() {
    $services = array();

    // @TODO: Save shipping method list as transient for 24 hours or so to avoid doing unnecessary lookups
    // @TODO: File bug upstream about result being string instead of object by default
    // We cannot access the WC_Pakettikauppa_Shipping_Method here as it has not yet been initialized,
    // so access the settings directly from database using option name.
    $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
    $account_number = $settings['mode'];
    $secret_key = $settings['secret_key'];
    $mode = $settings['mode'];
    $is_test_mode = ($mode == 'production' ? false : true);
    $wc_pakettikauppa_client = new Pakettikauppa\Client( array( 'api_key' => $account_number, 'secret' => $secret_key, 'test_mode' => $is_test_mode ) );
    $all_shipping_methods = json_decode($wc_pakettikauppa_client->listShippingMethods());


    // List all available methods as shipping options on checkout page
    if ( ! empty( $all_shipping_methods ) ) {
        foreach ( $all_shipping_methods as $shipping_method ) {
          $services[$shipping_method->shipping_method_code] = sprintf( '%1$s %2$s', $shipping_method->service_provider, $shipping_method->name );
        }
    }
    return $services;
  }

  /**
   * Get the title of a service by providing its code.
   *
   * @param int $service_code The code of a service
   * @return string The service title matching with the provided code, or false if not found
   */
  public static function service_title( $service_code ) {
    $services = self::services();
    if ( isset( $services[$service_code] ) ) {
      return $services[$service_code];
    }

    return false;
  }

  /**
  * Get the status text that matches a specified status code.
  *
  * @param int $status_code A status code
  * @return string The status text matching the provided code, or unknown status if the
  * code is unknown.
  */
  public static function get_status_text( $status_code ) {
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
  * Calculate the total shipping weight of an order.
  *
  * @param WC_Order $order The order to calculate the weight of
  * @return int The total weight of the order
  */
  public static function order_weight( $order ) {
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

  /**
  * Get the full-length tracking url of a shipment by providing its service id and tracking code
  *
  * @param int $service_id The id of the service that is used for the shipment
  * @param int $tracking_code The tracking code of the shipment
  * @return string The full tracking url for the order
  */
  public static function tracking_url( $service_id, $tracking_code ) {
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

  /**
  * Calculate Finnish invoice reference from order ID
  * http://tarkistusmerkit.teppovuori.fi/tarkmerk.htm#viitenumero
  *
  * @param int $id The id of the order to calculate the reference of
  * @return int The reference number calculated from the id
  */
  public static function calculate_reference( $id ) {
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

}
