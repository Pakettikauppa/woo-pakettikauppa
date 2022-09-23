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

      add_filter('woocommerce_checkout_fields', array( $this, 'add_checkout_fields' ));

      $this->shipment = $this->core->shipment;
    }

    public function add_checkout_fields( $fields ) {
      $settings = $this->shipment->get_settings();
      $required_phone = (! empty($settings['field_phone_required'])) ? $settings['field_phone_required'] : 'no';

      // Add shipping phone is billing phone exists
      if ( isset($fields['billing']['billing_phone']) ) {
        $fields['shipping']['shipping_phone'] = $fields['billing']['billing_phone'];
        $fields['shipping']['shipping_phone']['required'] = ($required_phone == 'yes') ? 1 : 0;
      }

      // Add shipping email if billing email exists
      if ( isset($fields['billing']['billing_email']) ) {
        $fields['shipping']['shipping_email'] = $fields['billing']['billing_email'];
        $fields['shipping']['shipping_email']['required'] = 0;
      }

      return $fields;
    }

    public function save_pickup_point_info_to_session() {
      if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update', 'security') ) {
        return;
      }

      $pickup_point_id = esc_attr($_POST['pickup_point_id']);

      $this->set_pickup_point_session_data(
        array_replace(
          $this->get_pickup_point_session_data(),
          array(
            'pickup_point' => $pickup_point_id,
          )
        )
      );
    }

    public function reset_pickup_point_session_data() {
      WC()->session->set(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point', null);
    }

    public function set_pickup_point_session_data( $data ) {
      WC()->session->set(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point', $data);
    }

    public function get_pickup_point_session_data() {
      return WC()->session->get(
        str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point',
        array(
          'address' => WC()->customer->get_shipping_address(),
          'postcode' => WC()->customer->get_shipping_postcode(),
          'country' => WC()->customer->get_shipping_country(),
          'custom_address' => null,
          'pickup_point' => null,
        )
      );
    }

    public function use_custom_address_for_pickup_point() {
      if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update', 'security') ) {
        return;
      }

      if ( ! empty($_POST['address']) ) {
        $address = esc_attr($_POST['address']);
      } else {
        $address = null;
      }

      $this->set_pickup_point_session_data(
        array_replace(
          $this->get_pickup_point_session_data(),
          array(
            'custom_address' => $address,
            'pickup_point' => null,
          )
        )
      );

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

      wp_enqueue_style($this->core->prefix . '_css', $this->core->dir_url . 'assets/css/frontend.css', array(), $this->core->version);
      wp_enqueue_script($this->core->prefix . '_js', $this->core->dir_url . 'assets/js/frontend.js', array( 'jquery' ), $this->core->version, true);
      wp_localize_script(
        $this->core->prefix . '_js',
        'pakettikauppaData',
        array(
          'privatePickupPointConfirm' => $this->core->text->confirm_private_pickup_selection(),
        )
      );
    }

    /**
     * Update the order meta with pakettikauppa_pickup_point field value
     * Example value from checkout page: "DB Schenker: R-KIOSKI TRE AMURI (#6681)"
     *
     * @param int $order_id The id of the order to update
     */
    public function update_order_meta_pickup_point_field( $order_id ) {
      $logger = wc_get_logger();
      if ( ! wp_verify_nonce(sanitize_key($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout') ) {
        $logger->error('Failed to verify update_order_meta_pickup_point_field nonce');
        //        return;
      }

      $pickup_point = isset($_POST[str_replace('wc_', '', $this->core->prefix) . '_pickup_point']) ? $_POST[str_replace('wc_', '', $this->core->prefix) . '_pickup_point'] : array();

      if ( empty($pickup_point) ) {
        $pickup_point = WC()->session->get(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');
        WC()->session->set(str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', null);
      }

      if ( ! empty($pickup_point) ) {
        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', sanitize_text_field($pickup_point));
        // Find string like '(#6681)'
        preg_match('/\(#[A-Z0-9]+\)/', $pickup_point, $matches);
        // Cut the number out from a string of the form '(#6681)'
        $pakettikauppa_pickup_point_id = (! empty($matches)) ? substr($matches[0], 2, -1) : '';
        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', $pakettikauppa_pickup_point_id);

        preg_match('/\(\%[0-9]+\)/', $pickup_point, $matches);
        // Cut the number out from a string of the form '(#6681)'
        $pakettikauppa_pickup_point_provider_id = (! empty($matches)) ? substr($matches[0], 2, -1) : '';

        update_post_meta($order_id, '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_provider_id', $pakettikauppa_pickup_point_provider_id);
      }
    }

    private function shipping_needs_pickup_points() {
      $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

      if ( empty($chosen_shipping_methods) ) {
        return false;
      }

      $packages = WC()->shipping()->get_packages();

      /** @var WC_Shipping_Rate $shipping_rate */
      $shipping_rate = null;

      // Find first chosen shipping method that has shipping_rates
      foreach ( $chosen_shipping_methods as $chosen_shipping_id ) {
        foreach ( $packages as $package ) {
          if ( isset($package['rates'][$chosen_shipping_id]) ) {
            $shipping_rate = $package['rates'][$chosen_shipping_id];
          }
        }

        if ( $shipping_rate !== null ) {
          break;
        }
      }

      if ( $shipping_rate === null ) {
        return false;
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
          return false;
        }

        $instance_id = $temp_array[1];

        if ( ! empty($pickup_points[$instance_id]) ) {
          if ( ! empty($pickup_points[$instance_id]['service']) && $pickup_points[$instance_id]['service'] === '__PICKUPPOINTS__' ) {
            foreach ( $pickup_points[$instance_id] as $shipping_method => $shipping_method_data ) {
              if ( isset($shipping_method_data['active']) && $shipping_method_data['active'] === 'yes' ) {
                $shipping_method_providers[] = $shipping_method;
              }
            }
          } else if ( ! empty($pickup_points[$instance_id]['service']) ) {
            if ( $this->shipment->service_has_pickup_points($pickup_points[$instance_id]['service']) ) {
              if ( $pickup_points[$instance_id][$pickup_points[$instance_id]['service']]['pickuppoints'] === 'yes' ) {
                $shipping_method_providers[] = $pickup_points[$instance_id]['service'];
              }
            }
          }
        }
      }

      // Bail out if the shipping method is not one of the pickup point services
      if ( empty($shipping_method_providers) ) {
        return false;
      }

      return $shipping_method_providers;
    }

    /*
    * Customize the layout of the checkout screen so that there is a section
    * where the pickup point can be defined. Don't use the woocommerce_checkout_fields
    * filter, it only lists fields without values, and we need to know the postcode.
    * Also the WooCommerce_checkout_fields has separate billing and shipping address
    * listings, when we want to have only one single pickup point per order.
    */
    public function pickup_point_field_html() {
      if ( ! wp_doing_ajax() ) {
        return;
      }

      $error_msg = '';
      $select_field = null;
      $custom_field = null;
      $custom_field_title = '';
      $custom_field_desc = '';

      $shipping_method_providers = $this->shipping_needs_pickup_points();

      echo '<input type="hidden" name="' . $this->core->prefix . '_validate_pickup_points" value="' . ($shipping_method_providers === false ? 'false' : 'true') . '" />';

      if ( $shipping_method_providers === false ) {
        return;
      }

      $selected_payment_method = WC()->session->get('chosen_payment_method');
      $is_klarna = $selected_payment_method === 'kco';

      $shipping_postcode = WC()->customer->get_shipping_postcode();
      $billing_postcode = WC()->customer->get_billing_postcode();
      $shipping_address = WC()->customer->get_shipping_address();
      $shipping_country = WC()->customer->get_shipping_country();

      if ( empty($shipping_postcode) && ! empty($billing_postcode) ) {
        $shipping_postcode = $billing_postcode;
        $shipping_address = WC()->customer->get_billing_address();
        $shipping_country = WC()->customer->get_billing_country();
      }

      $session = $this->get_pickup_point_session_data();
      $stale_items = array_filter(
        $session,
        function ( $v, $k ) use ( $shipping_postcode, $shipping_address, $shipping_country ) {
          if ( $k === 'postcode' && $v !== $shipping_postcode ) {
            return true;
          } else if ( $k === 'address' && $v !== $shipping_address ) {
            return true;
          } else if ( $k === 'country' && $v !== $shipping_country ) {
            return true;
          }

          return false;
        },
        \ARRAY_FILTER_USE_BOTH
      );

      if ( ! empty($stale_items) ) {
        $this->reset_pickup_point_session_data();
        $session = $this->get_pickup_point_session_data();
      }

      if ( empty($shipping_country) ) {
        $shipping_country = 'FI';
      }

      // Return if the customer has not yet chosen a postcode
      if ( empty($shipping_postcode) ) {
        $error_msg = esc_attr__('Empty postcode. Please check your address information.', 'woo-pakettikauppa');
      } else if ( $shipping_country == 'FI' && ! is_numeric($shipping_postcode) ) {
        $error_msg = sprintf(
        /* translators: %s: Postcode */
          esc_attr__('Invalid postcode "%1$s". Please check your address information.', 'woo-pakettikauppa'),
          esc_attr($shipping_postcode)
        );
      } else {
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
          $error_msg = $e->getMessage();
        }

        $selected_point = false;
        if ( ! $error_msg ) {
          $list_type = 'select';

          if ( isset($settings['pickup_point_list_type']) && $settings['pickup_point_list_type'] === 'list' ) {
            $list_type = 'radio';

            array_splice($options_array, 0, 1);
          }

          $flatten = function ( $point ) {
            return $point['text'];
          };

          $private_points = \array_map(
            $flatten,
            \array_filter(
              $options_array,
              function ( $point ) {
                return isset($point['is_private']) ? $point['is_private'] === true : false;
              }
            )
          );

          $all_points = \array_map($flatten, $options_array);

          $selected_point = $session['pickup_point'];
          $selected_point_empty = empty($selected_point);

          if ( $is_klarna && $selected_point_empty ) {
            // Select the first point as the default when using Klarna Checkout, which does not validate the selection
            $selected_point = array_keys($all_points)[1];
          } else if ( $selected_point_empty ) {
            $selected_point = array_keys($all_points)[0];
          }

          $select_field = array(
            'name' => str_replace('wc_', '', $this->core->prefix) . '_pickup_point',
            'data' => array(
              'clear' => true,
              'type' => $list_type,
              'custom_attributes' => array(
                'style' => 'word-wrap: normal;',
                'onchange' => 'pakettikauppa_pickup_point_change(this)',
                'data-private-points' => join(';', array_keys($private_points)),
              ),
              'options' => $all_points,
              'required' => true,
              'default' => $selected_point,
            ),
            'value' => null,
          );
        }
        // Moved this section below select, issue #163
        $show_pickup_point_override_query = $this->core->shipping_method_instance->get_option('show_pickup_point_override_query');

        // Compatibility fixes below
        // Klarna Checkout changes the checkout flow; user types their address into an iframe instead
        // and selecting a pickup point is not possible
        // Also added condition that pickup point must be 'Other' to show this section - issue #163
        if ( $show_pickup_point_override_query === 'yes' && ($selected_point === 'other' || $is_klarna || ! $options_array) ) {
          $custom_field_title = $is_klarna ? $this->core->text->pickup_point_title() : $this->core->text->custom_pickup_point_title();

          $custom_field = array(
            'name' => 'pakettikauppacustom_pickup_point',
            'data' => array(
              'type' => 'textarea',
              'custom_attributes' => array(
                'onchange' => 'pakettikauppa_custom_pickup_point_change(this)',
              ),
            ),
            'value' => $session['custom_address'],
          );

          $custom_field_desc = ($is_klarna) ? $this->core->text->fill_pickup_address_above() : $this->core->text->custom_pickup_point_desc();
        }
      }

      wc_get_template(
        $this->core->templates->checkout_pickup,
        array(
          'nonce' => wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-pickup_point_update'),
          'error' => array(
            'msg' => $error_msg,
            'name' => esc_attr(str_replace('wc_', '', $this->core->prefix) . '_pickup_point'),
          ),
          'pickup' => array(
            'show' => ($select_field) ? true : false,
            'field' => $select_field,
          ),
          'custom' => array(
            'show' => ($custom_field) ? true : false,
            'title' => $custom_field_title,
            'field' => $custom_field,
            'desc' => $custom_field_desc,
          ),
        ),
        '',
        $this->core->templates_dir
      );
    }

    private function fetch_pickup_point_options( $shipping_postcode, $shipping_address, $shipping_country, $shipping_method_provider ) {
      $pickup_point = WC()->session->get(str_replace('wc_', 'woo_', $this->core->prefix) . '_pickup_point');
      if ( $pickup_point != null && isset($pickup_point['custom_address']) ) {
        $custom_address = $pickup_point['custom_address'];
      } else {
        $custom_address = false;
      }

      if ( $custom_address && $this->core->shipping_method_instance->get_option('show_pickup_point_override_query') === 'yes' ) {
        $pickup_point_data = $this->shipment->get_pickup_points_by_free_input($custom_address, $shipping_method_provider);
      } else {
        $pickup_point_data = $this->shipment->get_pickup_points($shipping_postcode, $shipping_address, $shipping_country, $shipping_method_provider);
      }

      return $this->process_pickup_points_to_option_array($pickup_point_data);
    }

    private function process_pickup_points_to_option_array( $pickup_points ) {
      $options_array = array( '' => array( 'text' => '- ' . __('Select a pickup point', 'woo-pakettikauppa') . ' -' ) );

      if ( ! empty($pickup_points) ) {
        $show_provider = false;
        $provider = '';
        foreach ( $pickup_points as $key => $value ) {
          if ( ! isset($value->provider) ) {
            $show_provider = true;
            break;
          }
          if ( ! empty($provider) && $provider !== $value->provider ) {
            $show_provider = true;
            break;
          }
          $provider = $value->provider;
        }
        foreach ( $pickup_points as $key => $value ) {
          if ( ! isset($value->provider) ) {
            continue;
          }
          $pickup_point_key = $value->provider . ': ' . $value->name . ' (#' . $value->pickup_point_id . ')';
          $pickup_point_value = $value->name . ' (' . $value->street_address . ')';

          if ( $show_provider ) {
            $pickup_point_value = $value->provider . ': ' . $pickup_point_value;
          }

          // $options_array[ $pickup_point_key ] = $pickup_point_value;
          $options_array[$pickup_point_key] = array(
            'text' => $pickup_point_value,
            'is_private' => $value->point_type === 'PRIVATE_LOCKER',
          );
        }
      }

      // issue #163 - added 'Other' option for custom address
      // $options_array[ $pickup_point_key ] = $pickup_point_value;
      $options_array['other'] = array(
        'text' => __('Other', 'woo-pakettikauppa'),
        //'is_private' => $value->point_type === 'PRIVATE_LOCKER',
      );

      //else unset($options_array['__NULL__']);

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
        wc_get_template($this->core->templates->account_order, array( 'pickup_point' => esc_attr($pickup_point) ), '', $this->core->templates_dir);
      }
    }

    public function validate_checkout() {
      $logger = wc_get_logger();
      if ( ! wp_verify_nonce(sanitize_key($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout') ) {
        $logger->error('Checkout nonce failed to verify');
        //$this->add_error(__('We were unable to process your order, please try again.', 'woo-pakettikauppa'));
        //return;
      }

      $key = str_replace('wc_', '', $this->core->prefix) . '_pickup_point';
      $pickup_data = isset($_POST[$key]) ? sanitize_key($_POST[$key]) : '__NULL__';
      $pickup_data = $pickup_data === '__null__' ? strtoupper($pickup_data) : $pickup_data;

      // if there is no pickup point data, let's see do we need it
      if ( $pickup_data === '__NULL__' || $pickup_data === '' || $pickup_data === 'other' ) {
        $key = $this->core->prefix . '_validate_pickup_points';
        // if the value does not exists, then we expect to have pickup point data
        $shipping_needs_pickup_points = isset($_POST[$key]) ? $_POST[$key] === 'true' : false;

        if ( $shipping_needs_pickup_points ) {
          $this->add_error(__('Please choose a pickup point.', 'woo-pakettikauppa'));
        }

        foreach ( $this->errors as $error ) {
          $this->display_error($error);
        }
      }
    }
  }
}
