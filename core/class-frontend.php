<?php

namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

if ( ! class_exists(__NAMESPACE__ . '\Frontend') ) {
  /**
   * Frontend Class
   *
   * @class Frontend
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Frontend {
    /**
     * @var Core
     */
    public $core = null;

    /**
     * @var Shipment
     */
    private $shipment = null;
    private $errors = array();

    public function __construct( Core $plugin ) {
      // $this->id = 'woo-pakettikauppa'; // not used for anything
      $this->core = $plugin;
    }

    public function load() {
      add_action('wp_enqueue_scripts', array( $this, 'enqueue_scripts' ));
      add_action('woocommerce_review_order_after_shipping', array( $this, 'pickup_point_field_html' ));
      add_action('woocommerce_order_details_after_order_table', array( $this, 'display_order_data' ));
      add_action('woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_pickup_point_field' ));
      add_action('woocommerce_checkout_process', array( $this, 'validate_checkout' ));
      add_action('woocommerce_order_status_changed', array( $this, 'create_shipment_for_order_automatically' ));

      add_action('wp_ajax_pakettikauppa_save_pickup_point_info_to_session', array( $this, 'save_pickup_point_info_to_session' ), 10);
      add_action('wp_ajax_nopriv_pakettikauppa_save_pickup_point_info_to_session', array( $this, 'save_pickup_point_info_to_session' ), 10);

      add_action('wp_ajax_pakettikauppa_use_custom_address_for_pickup_point', array( $this, 'use_custom_address_for_pickup_point' ), 10);
      add_action('wp_ajax_nopriv_pakettikauppa_use_custom_address_for_pickup_point', array( $this, 'use_custom_address_for_pickup_point' ), 10);

      $this->shipment = $this->core->shipment;
    }

    public function save_pickup_point_info_to_session() {
      if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update', 'security') ) {
        return;
      }

      $pickup_point_id = $_POST['pickup_point_id'];

      WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', $pickup_point_id);
    }

    public function use_custom_address_for_pickup_point() {
      if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update', 'security') ) {
        return;
      }

      if ( ! empty($_POST['address']) ) {
        $address = $_POST['address'];
      } else {
        $address = null;
      }

      // Reset the pickup point
      WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', null);

      // And set the pickup address
      WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_custom_pickup_point_address', $address);

      // Rest is handled in Frontend\fetch_pickup_point_options
    }

    public function create_shipment_for_order_automatically( $order_id ) {
      $order = new \WC_Order($order_id);

      if ( $this->shipment->can_create_shipment_automatically($order) ) {
        $this->shipment->create_shipment($order);
      }
    }

    /**
     * Add an error with a specified error message.
     *
     * @param string $message A message containing details about the error.
     */
    public function add_error( $message ) {
      if ( ! empty($message) ) {
        array_push($this->errors, $message);
      }
    }

    /**
     * Display error in woocommerce
     */
    public function display_error( $error = null ) {
      if ( ! $error ) {
        $error = __('An error occurred. Please try again later.', 'woo-pakettikauppa');
      }

      wc_add_notice($error, 'error');
    }

    /**
     * Enqueue frontend-specific styles and scripts.
     */
    public function enqueue_scripts() {

      if ( ! is_checkout() ) {
        return;
      }

      wp_enqueue_style($this->core->prefix . '', $this->core->dir_url . 'assets/css/frontend.css', array(), $this->core->version);
      wp_enqueue_script($this->core->prefix . '_js', $this->core->dir_url . 'assets/js/frontend.js', array( 'jquery' ), $this->core->version, true);
      wp_localize_script(
        $this->core->prefix . '_js',
        'pakettikauppaData',
        [
          'privatePickupPointConfirm' => $this->core->text->confirm_private_pickup_selection(),
        ]
      );
    }

    /**
     * Update the order meta with pakettikauppa_pickup_point field value
     * Example value from checkout page: "DB Schenker: R-KIOSKI TRE AMURI (#6681)"
     *
     * @param int $order_id The id of the order to update
     */
    public function update_order_meta_pickup_point_field( $order_id ) {
      if ( ! wp_verify_nonce(sanitize_key($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout') ) {
        return;
      }

      $pickup_point = isset($_POST[str_replace('wc_', '', $this->core->prefix) . '_pickup_point']) ? $_POST[str_replace('wc_', '', $this->core->prefix) . '_pickup_point'] : [];

      if ( empty($pickup_point) ) {
        $pickup_point = WC()->session->get(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');
        WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', null);
      }

      if ( ! empty($pickup_point) ) {
        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', sanitize_text_field($pickup_point));
        // Find string like '(#6681)'
        preg_match('/\(#[0-9]+\)/', $pickup_point, $matches);
        // Cut the number out from a string of the form '(#6681)'
        $pakettikauppa_pickup_point_id = substr($matches[0], 2, - 1);
        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', $pakettikauppa_pickup_point_id);

        preg_match('/\(\%[0-9]+\)/', $pickup_point, $matches);
        // Cut the number out from a string of the form '(#6681)'
        $pakettikauppa_pickup_point_provider_id = substr($matches[0], 2, - 1);

        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_provider_id', $pakettikauppa_pickup_point_provider_id);
      }
    }

    /*
    * Customize the layout of the checkout screen so that there is a section
    * where the pickup point can be defined. Don't use the woocommerce_checkout_fields
    * filter, it only lists fields without values, and we need to know the postcode.
    * Also the WooCommerce_checkout_fields has separate billing and shipping address
    * listings, when we want to have only one single pickup point per order.
    */
    public function pickup_point_field_html() {
      $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
      $selected_payment_method = WC()->session->get('chosen_payment_method');
      $is_klarna = $selected_payment_method === 'kco';

      WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', null);

      if ( empty($chosen_shipping_methods) ) {
        return;
      }

      $packages = WC()->shipping()->get_packages();

      /** @var WC_Shipping_Rate $shipping_rate */
      $shipping_rate = null;

      // Find first chosen shipping method that has shipping_rates
      foreach ( $chosen_shipping_methods as $chosen_shipping_id ) {
        foreach ( $packages as $package ) {
          if ( isset($package['rates'][ $chosen_shipping_id ]) ) {
            $shipping_rate = $package['rates'][ $chosen_shipping_id ];
          }
        }

        if ( $shipping_rate !== null ) {
          break;
        }
      }

      if ( $shipping_rate === null ) {
        return;
      }

      $shipping_method_providers = array();
      $shipment_meta_data = $shipping_rate->get_meta_data();

      $settings = $this->shipment->get_settings();

      if ( isset($shipment_meta_data['service_code']) ) {
        $shipping_method_id = $shipment_meta_data['service_code'];

        if ( $this->shipment->service_has_pickup_points($shipping_method_id) ) {
          $shipping_method_providers[] = $shipping_method_id;
        }
      } else {
        // Pickup points might not be set in settings.
        $pickup_points = json_decode(isset($settings['pickup_points']) ? $settings['pickup_points'] : '[]', true);

        $temp_array = explode(':', $chosen_shipping_id); // for php 5.6 compatibility

        if ( count($temp_array) < 2 ) {
          // no instance_id available -> return
          return;
        }

        $instance_id = $temp_array[1];

        if ( ! empty($pickup_points[ $instance_id ]) ) {
          if ( ! empty($pickup_points[ $instance_id ]['service']) && $pickup_points[ $instance_id ]['service'] === '__PICKUPPOINTS__' ) {
            foreach ( $pickup_points[ $instance_id ] as $shipping_method => $shipping_method_data ) {
              if ( isset($shipping_method_data['active']) && $shipping_method_data['active'] === 'yes' ) {
                $shipping_method_providers[] = $shipping_method;
              }
            }
          } elseif ( ! empty($pickup_points[ $instance_id ]['service']) ) {
            if ( $this->shipment->service_has_pickup_points($pickup_points[ $instance_id ]['service']) ) {
              $shipping_method_providers[] = $pickup_points[ $instance_id ]['service'];
            }
          }
        }
      }

      // Bail out if the shipping method is not one of the pickup point services
      if ( empty($shipping_method_providers) ) {
        return;
      }

      $shipping_postcode = WC()->customer->get_shipping_postcode();
      $shipping_address  = WC()->customer->get_shipping_address();
      $shipping_country  = WC()->customer->get_shipping_country();

      if ( empty($shipping_country) ) {
        $shipping_country = 'FI';
      }

      $show_pickup_point_override_query = $this->core->shipping_method_instance->get_option('show_pickup_point_override_query');

      // Compatibility fixes below
      // Klarna Checkout changes the checkout flow; user types their address into an iframe instead
      // and selecting a pickup point is not possible
      if ( $show_pickup_point_override_query === 'yes' || $is_klarna ) {
        $title = $is_klarna ? $this->core->text->pickup_point_title() : $this->core->text->custom_pickup_point_title();

        echo '<tr class="shipping-custom-pickup-point">';
        echo '<th>' . $title . '</th>';
        echo '<td data-title="' . $title . '">';

        woocommerce_form_field(
          str_replace('wc_', '', $this->core->prefix) . 'custom_pickup_point',
          array(
            'type'              => 'textarea',
            'custom_attributes' => array(
              'onchange' => 'pakettikauppa_custom_pickup_point_change(this)',
            ),
          ),
          WC()->session->get(str_replace('wc_', '', $this->core->prefix) . '_custom_pickup_point_address')
        );

        echo '<p>';
        echo $is_klarna ? $this->core->text->fill_pickup_address_above() : $this->core->text->custom_pickup_point_desc();
        echo '</p>';

        echo '</td></tr>';
      }

      echo '<tr class="shipping-pickup-point">';
      echo '<th>' . esc_attr__('Pickup point', 'woo-pakettikauppa') . '</th>';
      echo '<td data-title="' . esc_attr__('Pickup point', 'woo-pakettikauppa') . '">';

      ?>
      <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update'); ?>" id="pakettikauppa_pickup_point_update_nonce" />
      <?php

      // Return if the customer has not yet chosen a postcode
      if ( empty($shipping_postcode) ) {
        echo '<p>';
        esc_attr_e('Insert your shipping details to view nearby pickup points', 'woo-pakettikauppa');
        echo '</p>';
      } elseif ( ! is_numeric($shipping_postcode) ) {
        echo '<p>';
        printf(
          /* translators: %s: Postcode */
          esc_attr__('Invalid postcode "%1$s". Please check your address information.', 'woo-pakettikauppa'),
          esc_attr($shipping_postcode)
        );
        echo '</p>';
      } else {
        $error = null;

        try {
          $options_array = $this->fetch_pickup_point_options($shipping_postcode, $shipping_address, $shipping_country, implode(',', $shipping_method_providers));
        } catch ( \Exception $e ) {
          $options_array = false;

          // The error prints twice if the page is refreshed and there's an invalid address.
          // Which doesn't make any sense as this method should only be called *once*.
          // The error is displayed differently because of that.
          // $this->display_error($e->getMessage());

          // Adding the error to $this->errors doesn't work either, as the errors are only displayed in the
          // woocommerce_checkout_process hook which is triggered when the user submits the order.
          // $this->add_error($e->getMessage());

          // This works though. It prevents the pickup point input from rendering,
          // and if there's no pickup point selected, the order will error in woocommerce_checkout_process.
          $error = $e->getMessage();
        }

        if ( $error ) {
          $name = esc_attr(str_replace('wc_', '', $this->core->prefix) . '_pickup_point');

          echo '<p>';
          echo $error;
          echo '</p>';

          // Ensure that there's something to check against in $this->validate_checkout
          echo "<input type='hidden' name='$name' value='__NULL__'>";
        } else {
          echo esc_html__('Choose one of pickup points close to the address you entered:', 'woo-pakettikauppa');

          $list_type = 'select';

          if ( isset($settings['pickup_point_list_type']) && $settings['pickup_point_list_type'] === 'list' ) {
            $list_type = 'radio';

            array_splice($options_array, 0, 1);
          }

          $flatten = function( $point ) {
            return $point['text'];
          };

          $private_points = \array_map(
            $flatten,
            \array_filter(
              $options_array,
              function( $point ) {
                return isset($point['is_private']) ? $point['is_private'] === true : false;
              }
            )
          );

          $all_points = \array_map($flatten, $options_array);

          woocommerce_form_field(
            str_replace('wc_', '', $this->core->prefix) . '_pickup_point',
            array(
              'clear'             => true,
              'type'              => $list_type,
              'custom_attributes' => array(
                'style' => 'word-wrap: normal;',
                'onchange' => 'pakettikauppa_pickup_point_change(this)',
                'data-private-points' => join(';', array_keys($private_points)),
              ),
              'options'           => $all_points,
            ),
            null
          );
        }
      }
      echo '</td></tr>';
    }

    private function fetch_pickup_point_options( $shipping_postcode, $shipping_address, $shipping_country, $shipping_method_provider ) {
      $custom_address = WC()->session->get(str_replace('wc_', '', $this->core->prefix) . '_custom_pickup_point_address');

      if ( $custom_address && $this->core->shipping_method_instance->get_option('show_pickup_point_override_query') === 'yes' ) {
        $pickup_point_data = $this->shipment->get_pickup_points_by_free_input($custom_address, $shipping_method_provider);
      } else {
        $pickup_point_data = $this->shipment->get_pickup_points($shipping_postcode, $shipping_address, $shipping_country, $shipping_method_provider);
      }

      return $this->process_pickup_points_to_option_array($pickup_point_data);
    }

    private function process_pickup_points_to_option_array( $pickup_point_data ) {
      $pickup_points = json_decode($pickup_point_data);
      $options_array = array( '__NULL__' => [ 'text' => '- ' . __('Select a pickup point', 'woo-pakettikauppa') . ' -' ] );

      $methods = array_flip($this->core->shipment->get_pickup_point_methods());

      if ( ! empty($pickup_points) ) {
        foreach ( $pickup_points as $key => $value ) {
          $pickup_point_key = $value->provider . ': ' . $value->name . ' (#' . $value->pickup_point_id . ') (%' . $methods[ $value->provider ] . ')';
          $pickup_point_value = $value->provider . ': ' . $value->name . ' (' . $value->street_address . ')';

          // $options_array[ $pickup_point_key ] = $pickup_point_value;
          $options_array[ $pickup_point_key ] = [
            'text' => $pickup_point_value,
            'is_private' => $value->point_type === 'PRIVATE_LOCKER',
          ];
        }
      }

      return $options_array;
    }

    /**
     * Display pickup point to customer after order.
     *
     * @param WC_Order $order the order that was placed
     */
    public function display_order_data( $order ) {
      $pickup_point = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point');

      if ( ! empty($pickup_point) ) {
        echo '<h2>' . esc_attr__('Pickup point', 'woo-pakettikauppa') . '</h2>';
        echo '<p>' . esc_attr($pickup_point) . '</p>';
      }
    }

    public function validate_checkout() {
      if ( ! wp_verify_nonce(sanitize_key($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout') ) {
        return;
      }

      $key = str_replace('wc_', '', $this->core->prefix) . '_pickup_point';
      $pickup = isset($_POST[$key]) ? $_POST[$key] : false;

      if ( $pickup && $pickup === '__NULL__' ) {
        $this->add_error(__('Please choose a pickup point.', 'woo-pakettikauppa'));
      }

      foreach ( $this->errors as $error ) {
        $this->display_error($error);
      }
    }
  }
}
