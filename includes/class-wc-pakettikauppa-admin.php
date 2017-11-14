<?php

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipping-method.php' );
require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipment.php' );

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
  private $wc_pakettikauppa_shipment = null;
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
    // Delete the tracking label when order is deleted so the uploads directory doesn't get too bloated
    add_action( 'before_delete_post', array( $this, 'delete_order_shipping_label' ) );
    // Connect shipping service and pickup points in admin
    add_action( 'wp_ajax_admin_update_pickup_point', array( $this, 'update_meta_box_pickup_points' ) );

    try {
      $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
      $this->wc_pakettikauppa_shipment->load();

    } catch ( Exception $e ) {
      $this->add_error( $e->getMessage() );
      $this->add_error_notice( $e->getMessage() );
      return;
    }
  }

  /**
  * Check if the selected service has pickup points via wp_ajax
  */
  public function update_meta_box_pickup_points() {
    if ( isset( $_POST ) && ! empty( $_POST['service_id'] ) ) {
      $service_id = $_POST['service_id'];
      echo WC_Pakettikauppa_Shipment::service_has_pickup_points( $service_id );
      wp_die();
    }
  }

  /**
  * Add an error with a specified error message.
  *
  * @param string $message A message containing details about the error.
  */
  public function add_error( $message ) {
    if ( ! empty( $message ) ) {
      array_push( $this->errors, $message );
      error_log( $message );
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
  * Add an admin error notice to wp-admin.
  */
  function add_error_notice( $message ) {
    if ( ! empty( $message ) ) {
      $class = 'notice notice-error';
      $print_error = wp_sprintf( __( 'An error occured: %s', 'wc-pakettikauppa' ), $message );
      printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $print_error ) );
    }
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
        'service'    => '<a href="' . esc_url( 'https://www.pakettikauppa.fi' ) .
        '" aria-label="' . esc_attr__( 'Visit Pakettikauppa', 'wc-pakettikauppa' ) .
        '">' . esc_html__( 'Show site Pakettikauppa', 'wc-pakettikauppa' ) . '</a>',
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
      add_meta_box(
        'wc-pakettikauppa',
        __( 'Pakettikauppa', 'wc-pakettikauppa' ),
        array( $this, 'meta_box' ),
        $type,
        'side',
        'default'
      );
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

    if ( ! WC_Pakettikauppa_Shipment::validate_order_shipping_receiver( $order ) ) {
      _e( 'Please add shipping info to the order to manage Pakettikauppa shipments.', 'wc-pakettikauppa' );
      return;
    }

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
    if ( isset( $ids[1] ) && ! empty( $ids[1] ) ) {
      $service_id = (int) $ids[1];
    }

    $pickup_point = $order->get_meta('_pakettikauppa_pickup_point');
    $pickup_point_id = $order->get_meta('_pakettikauppa_pickup_point_id');
    $status = get_post_meta( $post->ID, '_wc_pakettikauppa_shipment_status', true);

    // Set defaults
    if ( empty( $cod ) ) { $cod = ( $order->get_payment_method() === 'cod' ); }
    if ( empty( $cod_amount) ) { $cod_amount = $order->get_total(); }
    if ( empty( $cod_reference) ) { $cod_reference = WC_Pakettikauppa_Shipment::calculate_reference( $post->ID ); }
    if ( empty( $service_id ) ) { $service_id = WC_Pakettikauppa_Shipment::get_default_service($post, $order); }

    $document_url = admin_url( 'admin-post.php?post=' . $post->ID . '&action=show_pakettikauppa&sid=' . $tracking_code );
    $tracking_url = WC_Pakettikauppa_Shipment::tracking_url( $service_id, $tracking_code );

    ?>
      <div>

        <?php if ( ! empty( $tracking_code ) ) { ?>
          <p class="pakettikauppa-shipment">
            <strong>
              <?php printf( '%1$s<br>%2$s<br>%3$s', $this->wc_pakettikauppa_shipment->service_title($service_id), $tracking_code, WC_Pakettikauppa_Shipment::get_status_text($status) ); ?>
            </strong><br>

            <a href="<?php echo $document_url; ?>" target="_blank" class="download"><?php _e( 'Print document', 'wc-pakettikauppa' ) ?></a>&nbsp;-&nbsp;

            <?php if ( ! empty( $tracking_url ) ) : ?>
              <a href="<?php echo $tracking_url; ?>" target="_blank" class="tracking"><?php _e( 'Track', 'wc-pakettikauppa' ) ?></a>
            <?php endif; ?>
          </p>
        <?php } ?>

        <?php if ( empty( $tracking_code ) ) : ?>
          <div class="pakettikauppa-services">
            <fieldset class="pakettikauppa-metabox-fieldset">
              <h4><?php _e( 'Service', 'wc-pakettikauppa' ); ?></h4>
              <?php foreach ( $active_shipping_options as $shipping_option_id ) { ?>
                <label for="service-<?php echo $shipping_option_id; ?>">
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
                  <span><?php print $this->wc_pakettikauppa_shipment->service_title( $shipping_option_id ); ?></span>
                </label>
                <br>
              <?php } ?>

              <h4><?php _e( 'Additional services', 'wc-pakettikauppa' ); ?></h4>
              <input type="checkbox" name="wc_pakettikauppa_cod" value="1" id="wc-pakettikauppa-cod" <?php if ( $cod ) { ?>checked="checked"<?php } ?> />
              <label for="wc-pakettikauppa-cod"><?php _e( 'Cash on Delivery', 'wc-pakettikauppa' ); ?></label>

              <div class="form-field" id="wc-pakettikauppa-cod-amount-wrapper">
                <label for="wc_pakettikauppa_cod_amount"><?php _e( 'Amount (€):', 'wc-pakettikauppa' ) ?></label>
                <input type="text" name="wc_pakettikauppa_cod_amount" value="<?php echo $cod_amount; ?>" id="wc_pakettikauppa_cod_amount" />
              </div>

              <div class="form-field" id="wc-pakettikauppa-cod-reference-wrapper">
                <label for="wc_pakettikauppa_cod_reference"><?php _e( 'Reference:', 'wc-pakettikauppa' ) ?></label>
                <input type="text" name="wc_pakettikauppa_cod_reference" value="<?php echo $cod_reference; ?>" id="wc_pakettikauppa_cod_reference" />
              </div>

              <input type="checkbox" style="display:none;" name="wc_pakettikauppa_pickup_points" value="1" id="wc-pakettikauppa-pickup-points" <?php if ( $pickup_point ) { ?>checked="checked"<?php } ?> />
              <?php

              try {
                $pickup_point_data = $this->wc_pakettikauppa_shipment->get_pickup_points( $order->get_shipping_postcode() );
                $pickup_points = json_decode( $pickup_point_data ); ?>

                 <div class="form-field" id="wc-pakettikauppa-pickup-points-wrapper">
                   <h4><?php _e( 'Pickup Point', 'wc-pakettikauppa' ); ?></h4>
                   <select name="wc_pakettikauppa_pickup_point_id" class="wc_pakettikauppa_pickup_point_id" id="wc_pakettikauppa_pickup_point_id">
                     <?php foreach ( $pickup_points as $key => $value ) : ?>
                     <option value="<?php echo( $value->pickup_point_id ); ?>" <?php if ( $pickup_point_id == $value->pickup_point_id ) { echo 'selected'; } ?> ><?php echo( $value->provider . ': ' . $value->name . ' (' . $value->street_address . ')' ); ?></option>
                     <?php endforeach; ?>
                   </select>
                 </div>
                 <?php
              } catch ( Exception $e ) {
                $this->add_error( $e->getMessage() );
                echo '<p class="wc-pakettikauppa-metabox-error">' . __( 'Pickup point search failed!
                  Check your error log for details.', 'wc-pakettikauppa' ) . '</p>';
              }
              ?>
            </fieldset>

          </div>
          <p>
            <input type="submit" value="<?php _e( 'Create', 'wc-pakettikauppa' ); ?>" name="wc_pakettikauppa_create" class="button" />
          </p>
          <?php else : ?>
            <p>
              <input type="submit" value="<?php _e( 'Update Status', 'wc-pakettikauppa' ); ?>" name="wc_pakettikauppa_get_status" class="button" />
              <input type="submit" value="<?php _e( 'Delete Shipping Label', 'wc-pakettikauppa' ); ?>" name="wc_pakettikauppa_delete_shipping_label"  class="button wc-pakettikauppa-delete-button" />
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

      // Bail out if the receiver has not been properly configured
      if ( ! WC_Pakettikauppa_Shipment::validate_order_shipping_receiver( wc_get_order( $post_id ) ) ) {
        add_action( 'admin_notices', function() {
          echo '<div class="update-nag notice">' .
            __( 'The shipping label was not created because the order does not contain valid shipping details.', 'wc-pakettikauppa' )
            . '</div>';
        });
        return;
      }

      $order = new WC_Order( $post_id );

      try {
        $shipment_data = $this->wc_pakettikauppa_shipment->create_shipment( $post_id );

        $document_url = admin_url( 'admin-post.php?post=' . $post_id . '&action=show_pakettikauppa&sid=' . $shipment_data['tracking_code'] );
        $tracking_url = WC_Pakettikauppa_Shipment::tracking_url( $shipment_data['service_id'], $shipment_data['tracking_code'] );

        // Add order note
        $dl_link = '<a href="' . $document_url . '" target="_blank">' . __( 'Print document', 'wc-pakettikauppa' ) . '</a>';
        $tracking_link = '<a href="' . $tracking_url . '" target="_blank">' . __( 'Track', 'wc-pakettikauppa' ) . '</a>';

        $order->add_order_note(
          sprintf( __('Created Pakettikauppa %1$s shipment.<br>%2$s<br>%1$s - %3$s<br>%4$s', 'wc-pakettikauppa'),
          $this->wc_pakettikauppa_shipment->service_title($shipment_data['service_id']),
          $shipment_data['tracking_code'],
          $dl_link,
          $tracking_link ) );

      } catch ( Exception $e ) {
        $this->add_error( $e->getMessage() );
        $order->add_order_note( sprintf( __('Failed to create Pakettikauppa shipment. Errors: %s', 'wc-pakettikauppa'), $e->getMessage() ) );
        add_action( 'admin_notices', function() {
          $this->add_error_notice( wp_sprintf( __( 'An error occured: %s', 'wc-pakettikauppa' ), $e->getMessage() ) );
        } );
        return;
      }

    } elseif ( isset( $_POST['wc_pakettikauppa_get_status'] ) ) {
      try {
        $status_code = $this->wc_pakettikauppa_shipment->get_shipment_status( $post_id );
         update_post_meta( $post_id, '_wc_pakettikauppa_shipment_status', $status_code );

      } catch ( Exception $e ) {
        $this->add_error( $e->getMessage() );
        add_action( 'admin_notices', function() {
          $this->add_error_notice( wp_sprintf( __( 'An error occured: %s', 'wc-pakettikauppa' ), $e->getMessage() ) );
        } );
        return;
      }

    } elseif ( isset( $_POST['wc_pakettikauppa_delete_shipping_label'] ) ) {
      try {
        // Delete old shipping label
        $this->delete_order_shipping_label( $post_id );
        // Delete old tracking code
        update_post_meta( $post_id, '_wc_pakettikauppa_tracking_code', '' );

        $order = new WC_Order( $post_id );
        $order->add_order_note( __('Successfully deleted Pakettikauppa shipping label.', 'wc-pakettikauppa') );

      } catch ( Exception $e ) {
        $this->add_error( $e->getMessage() );
        add_action( 'admin_notices', function() {
          $this->add_error_notice( wp_sprintf( __( 'An error occured: %s', 'wc-pakettikauppa' ), $e->getMessage() ) );
        } );

        $order = new WC_Order( $post_id );
        $order->add_order_note(
          sprintf( __('Deleting Pakettikauppa shipment failed! Errors: %s', 'wc-pakettikauppa'),
            $e->getMessage() ) );
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

    $settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );
    $add_to_email = $settings['add_tracking_to_email'];

    if ( 'yes' === $add_to_email && isset( $email->id ) && 'customer_completed_order' === $email->id ) {

      $tracking_code = get_post_meta( $order->get_ID(), '_wc_pakettikauppa_tracking_code', true );
      $tracking_url = WC_Pakettikauppa_Shipment::tracking_url( '', $tracking_code );

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

  /**
  * Remove the shipping label of an order from the wc-pakettikauppa uploads directory
  *
  * @param int $post_id The post id of the order which is to be deleted
  */
  public function delete_order_shipping_label( $post_id ) {
    // Check that the post type is order
    $post_type = get_post_type( $post_id );
    if ( $post_type !== 'shop_order' ) {
      return;
    }

    $tracking_code = get_post_meta( $post_id, '_wc_pakettikauppa_tracking_code', true );
    if ( ! empty( $tracking_code ) ) {
      $filepath = WC_PAKETTIKAUPPA_PRIVATE_DIR . '/' .  $tracking_code . '.pdf';

      // Delete if file exists
      !file_exists( $filepath) ?: unlink( $filepath );
    }
  }

}
