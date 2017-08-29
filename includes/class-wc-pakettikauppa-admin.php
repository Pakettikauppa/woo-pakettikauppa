<?php

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Client.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Shipment.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Shipment/Sender.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Shipment/Receiver.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Shipment/Info.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'vendor/Pakettikauppa/Shipment/AdditionalService.php');
require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipping-method.php' );
require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa.php' );

use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Client;

/**
 * WC_Pakettikauppa_Admin Class
 *
 * @class WC_Pakettikauppa_Admin
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
class WC_Pakettikauppa_Admin {
  private $pakettikauppa = null;
  private $errors = array();

  function __construct() {
    $this->id = 'wc_pakettikauppa_admin';
  }

  public function load() {
    add_filter( 'plugin_action_links_' . WC_PAKETTIKAUPPA_BASENAME, array( $this, 'add_settings_link' ) );
    add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

    add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
    add_action( 'admin_post_show_pakettikauppa', array( $this, 'show' ), 10 );
    add_action( 'woocommerce_email_order_meta', array( $this, 'attach_tracking_to_email' ), 10, 4 );
    add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_pickup_point_in_admin_order_meta' ), 10, 1 );

    $this->wc_pakettikauppa_client = null;

    try {
      // Use option from database directly as WC_Pakettikauppa_Shipping_Method object is not accessible here
      $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
      $account_number = $settings['mode'];
      $secret_key = $settings['secret_key'];
      $mode = $settings['mode'];
      $is_test_mode = ($mode == 'production' ? false : true);
      $this->wc_pakettikauppa_client = new Client( array( 'api_key' => $account_number, 'secret' => $secret_key, 'test_mode' => $is_test_mode ) );
    } catch ( Exception $e ) {
      // @TODO handle errors
      die('pakettikauppa fail');
    }
  }

  /**
  * Return all errors that have been added via add_error().
  *
  * @return array Errors
  */
  public function get_errors() {
      return $this->errors;
  }

  /**
  * Clear all existing errors that have been added via add_error().
  */
  public function clear_errors() {
    unset( $this->errors );
    $this->errors = array();
  }

  /**
  * Add an error with a specified error message.
  *
  * @param string $message A message containing details about the error.
  */
  public function add_error( $message ) {
    if ( ! empty( $message ) ) {
      array_push( $this->errors, $message );
    }
  }

  /**
  * Add an admin error notice to wp-admin.
  */
  function add_error_notice() {
    $class = 'notice notice-error';
    $message = _e( 'Operation failed! Please try again.', 'wc-pakettikauppa' );
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
  }

  /**
   * Show row meta on the plugin screen.
   *
   * @param  mixed $links Plugin Row Meta
   * @param  mixed $file  Plugin Base file
   * @return  array
   */
  public static function plugin_row_meta( $links, $file ) {
    if ( WC_PAKETTIKAUPPA_BASENAME == $file ) {
      $row_meta = array(
        'service'    => '<a href="' . esc_url( 'https://pakettikauppa.fi' ) . '" aria-label="' . esc_attr__( 'Visit Pakettikauppa.fi', 'pakettikauppa' ) . '">' . esc_html__( 'Show site Pakettikauppa.fi', 'pakettikauppa' ) . '</a>',
      );
      return array_merge( $links, $row_meta );
    }
    return (array) $links;
  }

  /**
  * Register meta boxes for WooCommerce order metapage.
  */
  public function register_meta_boxes() {
    foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
      $order_type_object = get_post_type_object( $type );
      add_meta_box( 'wc-pakettikauppa', __( 'Pakettikauppa.fi', 'wc-pakettikauppa' ), array( $this, 'meta_box' ), $type, 'side', 'default' );
    }
  }

  /**
  * Enqueue admin-specific styles and scripts.
  */
  public function admin_enqueue_scripts() {
    wp_enqueue_style( 'wc_pakettikauppa_admin', plugin_dir_url( __FILE__ ) . '../assets/css/wc-pakettikauppa-admin.css' );
    wp_enqueue_script( 'wc_pakettikauppa_admin_js', plugin_dir_url( __FILE__ ) . '../assets/js/wc-pakettikauppa-admin.js', array( 'jquery' ) );
  }

  /**
  * Return the default shipping service if none has been specified
  *
  * @TODO: Does this method really need $post or $order, as the default service should
  * not be order-specific?
  */
  public function get_default_service( $post, $order ) {
    // @TODO: Maybe use an option in database so the merchant can set it in settings
    $service = '2103';

    // $order = new WC_Order( $post->ID );
    // $shipping_methods = $order->get_shipping_methods();
    //
    // if ( ! empty( $shipping_methods ) ) {
    //   $shipping_method = reset( $shipping_methods );
    //   $ids = explode( ':', $shipping_method['method_id'] );
    //   $instance_id = $ids[1];
    //
    //   // @TODO: This option does not exist
    //   $service = get_option( 'wc_pakettikauppa_shipping_method_' . $instance_id, '2103' );
    // }

    return $service;
  }

  /**
   * Add settings link to the Pakettikauppa metabox on the plugins page when used with
   * the WordPress hook plugin_action_links_woocommerce-pakettikauppa.
   *
   * @param array $links Already existing links on the plugin metabox
   * @return array The plugin settings page link appended to the already existing links
   */
  public function add_settings_link( $links ) {
    $url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wc_pakettikauppa_shipping_method' );
    $link = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';

    return array_merge( array( $link ), $links );
  }

  /**
  * Show the selected pickup point in admin order meta. Use together with the hook
  * woocommerce_admin_order_data_after_shipping_address.
  *
  * @param WC_Order $order The order that is currently being viewed in wp-admin
  */
  public function show_pickup_point_in_admin_order_meta( $order ) {
    echo '<p class="form-field"><strong>' . __('Requested pickup point', 'wc-pakettikauppa') . ':</strong><br>';
    if ( $order->get_meta('_pakettikauppa_pickup_point') ) {
      echo $order->get_meta('_pakettikauppa_pickup_point');
      echo '<br>ID: '. $order->get_meta('_pakettikauppa_pickup_point_id');
    } else {
      echo __('None');
    }
    echo '</p>';
  }

  /**
   * Meta box for managing shipments.
   */
  public function meta_box( $post ) {
    $order = wc_get_order( $post->ID );

    // Get active services from active_shipping_options
    $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
    $active_shipping_options = $settings['active_shipping_options'];

    // The tracking code will only be available if the shipment label has been generated
    $tracking_code = get_post_meta( $post->ID, '_wc_pakettikauppa_tracking_code', true);
    $cod = get_post_meta( $post->ID, '_wc_pakettikauppa_cod', true);
    $cod_amount = get_post_meta( $post->ID, '_wc_pakettikauppa_cod_amount', true);
    $cod_reference = get_post_meta( $post->ID, '_wc_pakettikauppa_cod_reference', true);
    $service_id = get_post_meta( $post->ID, '_wc_pakettikauppa_service_id', true);

    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset( $shipping_methods );
    $ids = explode( ':', $shipping_method['method_id'] );
    $service_id = (int) $ids[1];

    $pickup_point = $order->get_meta('_pakettikauppa_pickup_point');
    $pickup_point_id = $order->get_meta('_pakettikauppa_pickup_point_id');
    $status = get_post_meta( $post->ID, '_wc_pakettikauppa_shipment_status', true);

    // Set defaults
    if ( empty( $cod_amount) ) { $cod_amount = $order->get_total(); }
    if ( empty( $cod_reference) ) { $cod_reference = WC_Pakettikauppa::calculate_reference( $post->ID ); }
    if ( empty( $service_id ) ) { $service_id = $this->get_default_service($post, $order); }

    $document_url = admin_url( 'admin-post.php?post=' . $post->ID . '&action=show_pakettikauppa&sid=' . $tracking_code );
    $tracking_url = WC_Pakettikauppa::tracking_url( $service_id, $tracking_code );

    ?>
      <div>

        <?php if ( ! empty( $tracking_code ) ) { ?>
          <p class="pakettikauppa-shipment">
            <strong>
              <?php printf( '%1$s<br>%2$s<br>%3$s', WC_Pakettikauppa::service_title($service_id), $tracking_code, WC_Pakettikauppa::get_status_text($status) ); ?>
            </strong><br>

            <a href="<?php echo $document_url; ?>" target="_blank" class="download"><?php _e( 'Print document', 'wc-pakettikauppa' ) ?></a>&nbsp;-&nbsp;

            <?php if ( ! empty( $tracking_url ) ) : ?>
              <a href="<?php echo $tracking_url; ?>" target="_blank" class="tracking"><?php _e( 'Track', 'wc-pakettikauppa' ) ?></a>
            <?php endif; ?>
          </p>
        <?php } ?>

        <?php if ( empty( $tracking_code ) ) : ?>
          <div class="pakettikauppa-services">
            <fieldset>
              <h4><?php _e( 'Service', 'wc-pakettikauppa' ); ?></h4>
              <?php foreach ( $active_shipping_options as $shipping_option_id ) { ?>
                <label for="service-<?php echo $key; ?>">
                  <input type="radio"
                    name="wc_pakettikauppa_service_id"
                    value="<?php echo $shipping_option_id; ?>"
                    id="service-<?php echo $shipping_option_id; ?>"
                    <?php
                    // Show the customer selected pickup point as active by default
                    if ( $service_id == $shipping_option_id ) {
                      echo 'checked="checked"';
                    }
                    ?>
                  >
                  <span><?php print WC_Pakettikauppa::service_title( $shipping_option_id ); ?></span>
                </label>
                <br>
              <?php } ?>
              <h4><?php _e( 'Additional services', 'wc-pakettikauppa' ); ?></h4>
              <input type="checkbox" name="wc_pakettikauppa_cod" value="1" id="wc-pakettikauppa-cod" <?php if ( $cod ) { ?>checked="checked"<?php } ?> />
              <label for="wc-pakettikauppa-cod"><?php _e( 'Cash on Delivery', 'wc-pakettikauppa' ); ?></label>

              <p class="form-field" id="wc-pakettikauppa-cod-amount-wrapper">
                <label for="wc_pakettikauppa_cod_amount"><?php _e( 'Amount (€):', 'wc-pakettikauppa' ) ?></label>
                <input type="text" name="wc_pakettikauppa_cod_amount" value="<?php echo $cod_amount; ?>" id="wc_pakettikauppa_cod_amount" />
              </p>

              <p class="form-field" id="wc-pakettikauppa-cod-reference-wrapper">
                <label for="wc_pakettikauppa_cod_reference"><?php _e( 'Reference:', 'wc-pakettikauppa' ) ?></label>
                <input type="text" name="wc_pakettikauppa_cod_reference" value="<?php echo $cod_reference; ?>" id="wc_pakettikauppa_cod_reference" />
              </p>

              <br><input type="checkbox" name="wc_pakettikauppa_pickup_points" value="1" id="wc-pakettikauppa-pickup-points" <?php if ( $pickup_point ) { ?>checked="checked"<?php } ?> />
              <label for="wc-pakettikauppa-pickup-points"><?php _e( 'Pickup Point', 'wc-pakettikauppa' ); ?></label>

             <?php
               // Use option from database directly as WC_Pakettikauppa_Shipping_Method object is not accessible here
               $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
               $account_number = $settings['mode'];
               $secret_key = $settings['secret_key'];
               $mode = $settings['mode'];
               $is_test_mode = ($mode == 'production' ? false : true);
               $wc_pakettikauppa_client = new Client( array( 'api_key' => $account_number, 'secret' => $secret_key, 'test_mode' => $is_test_mode ) );
               $pickup_point_data = $wc_pakettikauppa_client->searchPickupPoints( $order->get_shipping_postcode() );

               if ( $pickup_point_data == 'Authentication error' ) {
                 // @TODO: Add proper error handling
               }

               $pickup_points = json_decode( $pickup_point_data );
              ?>

              <p class="form-field" id="wc-pakettikauppa-pickup-points-wrapper">
                <select name="wc_pakettikauppa_pickup_point_id" class="wc_pakettikauppa_pickup_point_id" id="wc_pakettikauppa_pickup_point_id">
                  <?php foreach ( $pickup_points as $key => $value ) : ?>
                    <option value="<?php echo( $value->pickup_point_id ); ?>" <?php if ( $pickup_point_id == $value->pickup_point_id ) { echo 'selected'; } ?> ><?php echo( $value->provider . ': ' . $value->name . ' (' . $value->street_address . ')' ); ?></option>
                  <?php endforeach; ?>
                </select>
              </p>

            </fieldset>

          </div>
          <p>
            <input type="submit" value="<?php _e( 'Create', 'wc-pakettikauppa' ); ?>" name="wc_pakettikauppa_create" class="button" />
          </p>
          <?php else : ?>
            <p>
              <input type="submit" value="<?php _e( 'Update Status', 'wc-pakettikauppa' ); ?>" name="wc_pakettikauppa_get_status" class="button" />
            </p>
          <?php endif; ?>
        </div>

    <?php
  }

  /**
   * Save metabox values and fetch the shipping label for the order.
   */
  public function save_metabox( $post_id, $post ) {
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
      return;
    }

    if ( wp_is_post_autosave( $post_id ) ) {
      return;
    }

    if ( wp_is_post_revision( $post_id ) ) {
      return;
    }

    if ( isset( $_POST['wc_pakettikauppa_create'] ) ) {
      $shipment = new Shipment();
      $service_id = $_REQUEST['wc_pakettikauppa_service_id'];
      $shipment->setShippingMethod( $service_id );
      $settings = WC()->shipping->shipping_methods['WC_Pakettikauppa_Shipping_Method']->settings;

      $sender = new Sender();
      $sender->setName1( $settings['sender_name'] );
      $sender->setAddr1( $settings['sender_address'] );
      $sender->setPostcode( $settings['sender_postal_code'] );
      $sender->setCity( $settings['sender_city'] );
      $sender->setCountry( 'FI' );

      $shipment->setSender($sender);

      $order = new WC_Order( $post_id );

      $receiver = new Receiver();
      $receiver->setName1( $order->get_formatted_shipping_full_name() );
      $receiver->setAddr1( $order->shipping_address_1 );
      $receiver->setPostcode( $order->shipping_postcode );
      $receiver->setCity( $order->shipping_city );
      $receiver->setCountry('FI');
      $receiver->setEmail( $order->billing_email );
      $receiver->setPhone( $order->billing_phone );

      $shipment->setReceiver( $receiver );

      $info = new Info();
      $info->setReference( $order->get_order_number() );

      $shipment->setShipmentInfo( $info );

      $cod = false;

      if ( $_REQUEST['wc_pakettikauppa_cod'] ) {
        $cod = true;
        $cod_amount = floatval( str_replace( ',', '.', $_REQUEST['wc_pakettikauppa_cod_amount'] ) );
        $cod_reference = trim( $_REQUEST['wc_pakettikauppa_cod_reference'] );
        $cod_iban = $settings['cod_iban'];
        $cod_bic = $settings['cod_bic'];

        $additional_service = new AdditionalService();
        $additional_service->addSpecifier( 'amount', $cod_amount );
        $additional_service->addSpecifier( 'account', $cod_iban );
        $additional_service->addSpecifier( 'codbic', $cod_bic );
        $additional_service->setServiceCode( 3101 );
        $shipment->addAdditionalService($additional_service);
      }

      $pickup_point = false;
      if ( $_REQUEST['wc_pakettikauppa_pickup_points'] ) {
        $pickup_point = true;
        $pickup_point_id = intval( $_REQUEST['wc_pakettikauppa_pickup_point_id'] );
        $shipment->setPickupPoint( $pickup_point_id );
      }

      try {
        if ( $this->wc_pakettikauppa_client->createTrackingCode($shipment) ) {
          $tracking_code = $shipment->getTrackingCode()->__toString();
        } else {
          // @TODO error message
        }

        if ( ! empty( $tracking_code ) ) {
          $this->wc_pakettikauppa_client->fetchShippingLabel( $shipment );
          $upload_dir = wp_upload_dir();
          $filepath = WC_PAKETTIKAUPPA_PRIVATE_DIR . '/' . $tracking_code . '.pdf';
          file_put_contents( $filepath , base64_decode( $shipment->getPdf() ) );
        }

        // Update post meta
        update_post_meta( $post_id, '_wc_pakettikauppa_tracking_code', $tracking_code);
        update_post_meta( $post_id, '_wc_pakettikauppa_service_id', $service_id);
        update_post_meta( $post_id, '_wc_pakettikauppa_cod', $cod);
        update_post_meta( $post_id, '_wc_pakettikauppa_cod_amount', $cod_amount);
        update_post_meta( $post_id, '_wc_pakettikauppa_cod_reference', $cod_reference);
        update_post_meta( $post_id, '_wc_pakettikauppa_pickup_point', $pickup_point);
        update_post_meta( $post_id, '_wc_pakettikauppa_pickup_point_id', $pickup_point_id);

        $this->clear_errors();

        $document_url = admin_url( 'admin-post.php?post=' . $post_id . '&action=show_pakettikauppa&sid=' . $tracking_code );
        $tracking_url = WC_Pakettikauppa::tracking_url( $service_id, $tracking_code );

        // Add order note
        $dl_link = '<a href="' . $document_url . '" target="_blank">' . __( 'Print document', 'wc-pakettikauppa' ) . '</a>';
        $tracking_link = '<a href="' . $tracking_url . '" target="_blank">' . __( 'Track', 'wc-pakettikauppa' ) . '</a>';

        $order->add_order_note( sprintf( __('Created Pakettikauppa %1$s shipment.<br>%2$s<br>%1$s - %3$s<br>%4$s', 'wc-pakettikauppa'), WC_Pakettikauppa::service_title($service_id), $tracking_code, $dl_link, $tracking_link ) );

        // @TODO check corrects shipment stuff
      } catch ( Exception $e ) {
        $order->add_order_note( sprintf( __('Failed to create Pakettikauppa shipment. Errors: %s', 'wc-pakettikauppa'), join( ', ', $shipment->get_errors() ) ) );
        add_action( 'admin_notices', 'add_error_notice' );

        // @TODO errors

        return;
      }

    } elseif ( isset( $_POST['wc_pakettikauppa_get_status'] ) ) {
      try {
         $tracking_code = get_post_meta( $post_id, '_wc_pakettikauppa_tracking_code', true);

         if ( ! empty( $tracking_code ) ) {
           $result = $this->wc_pakettikauppa_client->getShipmentStatus($tracking_code);

           $data = json_decode( $result );
           $status_code = $data[0]->{'status_code'};
           update_post_meta( $post_id, '_wc_pakettikauppa_shipment_status', $status_code );
         }

         $this->clear_errors();
      } catch ( Exception $e ) {
        $this->add_error( $e->getMessage() );
        return;
      }

    } else {
      return;
    }
  }


  /**
   * Output shipment label as PDF in browser.
   */
  public function show() {
    $shipment_id = false;

    // Find shipment ID either from GET parameters or from the order
    // data.
    if ( isset( $_REQUEST['sid'] ) ) {
      $shipment_id = $_REQUEST['sid'];
  } else

  _e( 'Shipment tracking code is not defined...
   shipment with given shipment number.', 'wc-pakettikauppa' );


    if ( false != $shipment_id ) {
      $upload_dir = wp_upload_dir();

      // Read file
      $filepath = WC_PAKETTIKAUPPA_PRIVATE_DIR . '/' .  $shipment_id . '.pdf';
      header('X-Sendfile: ' . $filepath);

      // Output
      $contents = file_get_contents( $filepath );
      header('Content-type:application/pdf');
      header("Content-Disposition:inline;filename='{$shipment_id}.pdf'");
      print $contents;
      exit;
    }

    _e( 'Cannot find shipment with given shipment number.', 'wc-pakettikauppa' );
    exit;
  }

  /**
   * Attach tracking URL to email.
   */
  public function attach_tracking_to_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {

    $add_to_email = WC()->shipping->shipping_methods['WC_Pakettikauppa_Shipping_Method']->settings['add_tracking_to_email'];

    if ( 'yes' === $add_to_email && isset( $email->id ) && 'customer_completed_order' === $email->id ) {

      $shipping_methods = $order->get_shipping_methods();
      $shipping_method = reset( $shipping_methods );
      $ids = explode( ':', $shipping_method['method_id'] );
      $instance_id = (int) $ids[1];

      $tracking_code = get_post_meta( $order->get_ID(), '_wc_pakettikauppa_tracking_code', true );
      $tracking_url = WC_Pakettikauppa::tracking_url( $instance_id, $tracking_code );

      if ( ! empty( $tracking_code ) && ! empty( $tracking_url ) ) {
        if ( $plain_text ) {
          echo sprintf( __( "You can track your order at %1$s.\n\n", 'wc-pakettikauppa' ), $tracking_url );
        } else {
          echo '<h2>' . __( 'Tracking', 'wc-pakettikauppa' ) . '</h2>';
          echo '<p>' . sprintf( __( 'You can <a href="%1$s">track your order</a> with tracking code %2$s.', 'wc-pakettikauppa' ), $tracking_url, $tracking_code ) . '</p>';
        }
      }
    }
  }

}
