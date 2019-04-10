<?php

// Prevent direct access to the script
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once plugin_dir_path( __FILE__ ) . '/class-wc-pakettikauppa.php';
require_once plugin_dir_path( __FILE__ ) . '/class-wc-pakettikauppa-shipment.php';

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
       * Required to access Pakettikauppa client
       * @var WC_Pakettikauppa_Shipment $wc_pakettikauppa_shipment
       */
      private $wc_pakettikauppa_shipment = null;

      /**
       * Default shipping fee
       *
       * @var string
       */
      public $fee = 5.95;

      /**
       * Constructor for Pakettikauppa shipping class
       *
       * @access public
       * @return void
       */
      public function __construct( $instance_id = 0 ) {
        parent::__construct( $instance_id );

        $this->id = 'pakettikauppa_shipping_method'; // ID for your shipping method. Should be unique.

        $this->method_title = 'Pakettikauppa';

        $this->method_description = __( 'Edit to select shipping company and shipping prices.', 'wc-pakettikauppa' ); // Description shown in admin
        //        $this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://www.pakettikauppa.fi/">Pakettikauppa</a>.', 'wc-pakettikauppa' ); // Description shown in admin

        // Make Pakettikauppa API accessible via WC_Pakettikauppa_Shipment
        $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
        $this->wc_pakettikauppa_shipment->load();

        $this->supports = array(
          'shipping-zones',
          'instance-settings',
          'settings',
          'instance-settings-modal',
        );

        $this->init();

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array(
          $this,
          'process_admin_options',
        ) );

        if ( ! empty($this->get_instance_option('shipping_method')) ) {
          /* translators: %s: shipping method */
          $this->method_description = sprintf(__( 'Selected shipping method: %s', 'wc-pakettikauppa'), $this->wc_pakettikauppa_shipment->service_title($this->get_instance_option('shipping_method')));
        }

      }

      /**
       * Initialize Pakettikauppa shipping
       */
      public function init() {
        $this->instance_form_fields = $this->my_instance_form_fields();
        $this->form_fields          = $this->my_global_form_fields();
        $this->title                = $this->get_option( 'title' );
      }

      public function validate_pickuppoints_field( $key, $value ) {
        $values = wp_json_encode( $value );
        return $values;
      }

      public function generate_pickuppoints_html( $key, $value ) {
        $field_key = $this->get_field_key( $key );
        if ( $this->get_option( $key ) !== '' ) {
          $values = $this->get_option( $key );
          if ( is_string( $values ) ) {
            $values = json_decode( $this->get_option( $key ), true );
          }
        } else {
          $values = array();
        }

        $all_shipping_methods = $this->wc_pakettikauppa_shipment->services();
        $additional_services = array();

        ob_start();
        ?>
        <script>
            function pkChangeOptions(elem, methodId) {

                var strUser = elem.options[elem.selectedIndex].value;

                var elements = document.getElementsByClassName('pk-services');
                for(var i=0; i<elements.length; ++i) {
                    elements[i].style.display = "none";
                }

                if (strUser == '__PICKUPPOINTS__') {
                    document.getElementById(methodId + '-pickuppoints').style.display = "block";
                    document.getElementById(strUser + '-services').style.display = "none";
                } else {
                    document.getElementById(methodId + '-pickuppoints').style.display = "none";
                    console.log(strUser + '-services');
                    document.getElementById(strUser + '-services').style.display = "block";
                }
            }
        </script>
          <tr>
              <th colspan="2" class="titledesc" scope="row"><?php echo esc_html( $value['title'] ); ?></th>
          </tr>
          <tr>
              <td colspan="2">
                    <?php foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $zone_index => $zone ) : ?>
                        <h2><?php echo $zone['zone_name']; ?></h2>
                      <?php foreach ( $zone['shipping_methods'] as $method_id => $shipping_method ) : ?>
                        <?php if ( $shipping_method->enabled === 'yes' && $shipping_method->id !== 'pakettikauppa_shipping_method' && $shipping_method->id !== 'local_pickup' ) : ?>
                          <?php
                          $selected_service = $values[ $method_id ]['service'];
                          if ( empty( $selected_service ) ) {
                              $selected_service = '__PICKUPPOINTS__';
                          }
                          ?>
                            <table>
                                <th><?php echo $shipping_method->title; ?></th>
                                <td style="vertical-align: top;">
                                    <select id="<?php echo $method_id; ?>-select" name="<?php echo esc_html( $field_key ) . '[' . esc_attr( $method_id ) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo $method_id; ?>');">
                                        <option value="__NULL__"><?php esc_html_e( 'No shipping', 'wc-pakettikauppa'); ?></option>
                                        <option value="__PICKUPPOINTS__" <?php echo ( $selected_service === '__PICKUPPOINTS__' ? 'selected' : '' ); ?>>Noutopisteet</option>
                                        <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                                          <?php if ( ! in_array ( $service_id, array( '2103', '80010', '90080' ) ) ) : ?>
                                            <option value="<?php echo $service_id; ?>" <?php echo ( strval( $selected_service ) === strval( $service_id ) ? 'selected' : '' ); ?>><?php echo $service_name; ?></option>
                                          <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="vertical-align: top;">
                                    <div style='display: none;' id="<?php echo $method_id; ?>-pickuppoints">

                                  <?php
                                  $methods = array(
                                    '2103'  => 'Posti',
                                    '90080' => 'Matkahuolto',
                                    '80010' => 'DB Schenker',
                                    '2711'  => 'Posti International',
                                  );
                                  ?>
                                  <?php foreach ( $methods as $method_code => $method_name ) : ?>
                                      <input type="hidden"
                                             name="<?php echo esc_html( $field_key ) . '[' . esc_attr( $method_id ) . '][' . $method_code . '][active]'; ?>"
                                             value="no">
                                      <p>
                                          <input type="checkbox"
                                                 name="<?php echo esc_html( $field_key ) . '[' . esc_attr( $method_id ) . '][' . $method_code . '][active]'; ?>"
                                                 value="yes" <?php echo $values[ $method_id ][ $method_code ]['active'] === 'yes' ? 'checked' : ''; ?>>
                                        <?php echo $method_name; ?>
                                      </p>
                                  <?php endforeach; ?>
                                    </div>

                                    <?php $all_additional_services = $this->wc_pakettikauppa_shipment->get_additional_services(); ?>
                                    <?php foreach ( $all_additional_services as $method_code => $additional_services ) : ?>
                                    <div class="pk-services" style='display: none;' id="<?php echo $method_code; ?>-services">
                                      <?php foreach ( $additional_services as $additional_service ) : ?>
                                        <?php if ( empty( $additional_service->specifiers ) ) : ?>
                                        <input type="hidden"
                                               name="<?php echo esc_html( $field_key ) . '[' . esc_attr( $method_id ) . '][' . esc_attr( $method_code ) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                               value="no">
                                        <p>
                                            <input type="checkbox"
                                                   name="<?php echo esc_html( $field_key ) . '[' . esc_attr( $method_id ) . '][' . esc_attr( $method_code ) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                                   value="yes" <?php echo $values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ] === 'yes' ? 'checked' : ''; ?>>
                                          <?php echo $additional_service->name; ?>
                                        </p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </td>
                            </table>
                        <script>pkChangeOptions(document.getElementById("<?php echo $method_id; ?>-select"), '<?php echo $method_id; ?>');</script>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
              </td>
          </tr>

          <?php
              $html = ob_get_contents();
          ob_end_clean();
          return $html;
      }

      /**
       * Initialize form fields
       */
      private function my_instance_form_fields() {
        $all_shipping_methods = array( '' => __( 'Select one shipping method', 'wc-pakettikauppa' ) );

        $all_services = $this->wc_pakettikauppa_shipment->services();

        foreach ( $all_services as $key => $value ) {
          $all_shipping_methods[ $key ] = $value;
        }

        if ( empty( $all_services ) ) {
          $fields = array(
            'title' => array(
              'title'       => __( 'Title', 'woocommerce' ),
              'type'        => 'text',
              'description' => __( 'Can not connect to Pakettikauppa server - please check Pakettikauppa API credentials, servers error log and firewall settings.', 'wc-pakettikauppa' ),
              'default'     => 'Pakettikauppa',
              'desc_tip'    => true,
            ),
          );

          return $fields;
        }

        $fields = array(
          'title'           => array(
            'title'       => __( 'Title', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default'     => 'Pakettikauppa',
            'desc_tip'    => true,
          ),
            /* Start new section */
          array(
            'title' => __( 'Shipping methods', 'wc-pakettikauppa' ),
            'type'  => 'title',
          ),

          'shipping_method' => array(
            'title'   => __( 'Shipping method', 'wc-pakettikauppa' ),
            'type'    => 'select',
            'options' => $all_shipping_methods,
          ),

          array(
            'title'       => __( 'Shipping class costs', 'woocommerce' ),
            'type'        => 'title',
            'default'     => '',
                /* translators: %s: URL for link. */
            'description' => sprintf( __( 'These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ),
          ),
        );

        $shipping_classes = WC()->shipping->get_shipping_classes();

        if ( ! empty( $shipping_classes ) ) {
          foreach ( $shipping_classes as $shipping_class ) {
            if ( ! isset( $shipping_class->term_id ) ) {
              continue;
            }

            $fields[] = array(
              /* translators: %s: shipping class cost */
              'title'   => sprintf( __( '"%s" shipping class cost', 'woocommerce' ), esc_html( $shipping_class->name ) ),
              'type'    => 'title',
              'default' => '',
            );

            $fields[ 'class_cost_' . $shipping_class->term_id . '_price' ] = array(
            /* translators: %s: shipping class name */
              'title'       => __( 'Price (vat included)', 'wc-pakettikauppa' ),
              'type'        => 'number',
              'default'     => null,
              'placeholder' => __( 'N/A', 'woocommerce' ),
              'description' => __( 'Shipping cost', 'wc-pakettikauppa' ),
              'desc_tip'    => true,
            );

            $fields[ 'class_cost_' . $shipping_class->term_id . '_price_free' ] = array(
              'title'       => __( 'Free shipping tier', 'wc-pakettikauppa' ),
              'type'        => 'number',
              'default'     => null,
              'description' => __( 'After which amount shipping is free.', 'wc-pakettikauppa' ),
              'desc_tip'    => true,
            );
          }

          $fields['type'] = array(
            'title'   => __( 'Calculation type', 'woocommerce' ),
            'type'    => 'select',
            'class'   => 'wc-enhanced-select',
            'default' => 'class',
            'options' => array(
              'class' => __( 'Per class: Charge shipping for each shipping class individually', 'woocommerce' ),
              'order' => __( 'Per order: Charge shipping for the most expensive shipping class', 'woocommerce' ),
            ),
          );

        }

        $fields[] = array(
          'title'   => __( 'Default shipping class cost', 'wc-pakettikauppa' ),
          'type'    => 'title',
          'default' => '',
        );

        $fields['price'] = array(
          'title'       => __( 'No shipping class cost (vat included)', 'wc-pakettikauppa' ),
          'type'        => 'number',
          'default'     => $this->fee,
          'placeholder' => __( 'N/A', 'woocommerce' ),
          'description' => __( 'Shipping cost  (vat included)', 'wc-pakettikauppa' ),
          'desc_tip'    => true,
        );

        $fields['price_free'] = array(
          'title'       => __( 'Free shipping tier', 'wc-pakettikauppa' ),
          'type'        => 'number',
          'default'     => '',
          'description' => __( 'After which amount shipping is free.', 'wc-pakettikauppa' ),
          'desc_tip'    => true,
        );

        return $fields;
      }

      private function my_global_form_fields() {
        return array(
          'mode'                       => array(
            'title'   => __( 'Mode', 'wc-pakettikauppa' ),
            'type'    => 'select',
            'default' => 'test',
            'options' => array(
              'test'       => __( 'Testing environment', 'wc-pakettikauppa' ),
              'production' => __( 'Production environment', 'wc-pakettikauppa' ),
            ),
          ),

          'account_number'             => array(
            'title'    => __( 'API key', 'wc-pakettikauppa' ),
            'desc'     => __( 'API key provided by Pakettikauppa', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          'secret_key'                 => array(
            'title'    => __( 'API secret', 'wc-pakettikauppa' ),
            'desc'     => __( 'API Secret provided by Pakettikauppa', 'wc-pakettikauppa' ),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          'pickup_points'              => array(
            'title' => __( 'Show pickup points for non-Pakettikauppa shipments', 'wc-pakettikauppa' ),
            'type'  => 'pickuppoints',
          ),

            /* Start new section */
          array(
            'title'       => __( 'Shipping settings', 'wc-pakettikauppa' ),
            'type'        => 'title',
            /* translators: %s: url to documentation */
            'description' => sprintf(__( 'You can activate new shipping method to checkout in <b>WooCommerce > Settings > Shipping > Shipping zones</b>. For more information, see <a target="_blank" href="%1$s">%1$s</a>', 'wc-pakettikauppa'), 'https://docs.woocommerce.com/document/setting-up-shipping-zones/'),

          ),

          'add_tracking_to_email'      => array(
            'title'   => __( 'Add tracking link to the order completed email', 'wc-pakettikauppa' ),
            'type'    => 'checkbox',
            'default' => 'no',
          ),

          'pickup_points_search_limit' => array(
            'title'       => __( 'Pickup point search limit', 'wc-pakettikauppa' ),
            'type'        => 'number',
            'default'     => 5,
            'description' => __( 'Limit the amount of nearest pickup points shown.', 'wc-pakettikauppa' ),
            'desc_tip'    => true,
          ),

          array(
            'title' => __( 'Store owner information', 'wc-pakettikauppa' ),
            'type'  => 'title',
          ),

          'sender_name'                => array(
            'title'   => __( 'Sender name', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_address'             => array(
            'title'   => __( 'Sender address', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_postal_code'         => array(
            'title'   => __( 'Sender postal code', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_city'                => array(
            'title'   => __( 'Sender city', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'cod_iban'                   => array(
            'title'   => __( 'Bank account number for Cash on Delivery (IBAN)', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'cod_bic'                    => array(
            'title'   => __( 'BIC code for Cash on Delivery', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),

          'info_code'                  => array(
            'title'   => __( 'Info-code for shipments', 'wc-pakettikauppa' ),
            'type'    => 'text',
            'default' => '',
          ),
        );
      }

      /**
       * Mostly copy-pasted from WooCommerce:
       *   woocommerce/includes/abstracts/abstract-wc-shipping-method.php
       *   protected function get_taxes_per_item( $costs ) and edited it A LOT.
       *
       * @param $shipping_cost
       *
       * @return array
       */
      private function calculate_shipping_tax( $shipping_cost ) {
        $taxes = array();

        $taxes_total = 0;
        $cart_obj    = WC()->cart;
        $cart_total = $cart_obj->get_cart_contents_total();

        $cart = $cart_obj->get_cart();

        foreach ( $cart as $item ) {
          $cost_key = $item['key'];

          $cost_item = $shipping_cost * $item['line_total'] / $cart_total;

          if ( $cart[ $cost_key ]['data'] !== null ) {
            $tax_obj = WC_Tax::get_shipping_tax_rates( $cart[ $cost_key ]['data']->get_tax_class() );

            foreach ( $tax_obj as $key => $value ) {
              if ( ! isset( $taxes[ $key ] ) ) {
                $taxes[ $key ] = 0.0;
              }

              $taxes[ $key ] += round( $cost_item - $cost_item / ( 1 + $value['rate'] / 100.0 ), 2 );
            }
          }
        }

        foreach ( $taxes as $_tax ) {
          $taxes_total += $_tax;
        }

        return array(
          'total' => $taxes_total,
          'taxes' => $taxes,
        );
      }

      /**
       * Finds and returns shipping classes and the products with said class.
       *
       * @param mixed $package Package of items from cart.
       *
       * @return array
       */
      private function find_shipping_classes( $package ) {
        $found_shipping_classes = array();

        foreach ( $package['contents'] as $item_id => $values ) {
          if ( $values['data']->needs_shipping() ) {
            $found_class = $values['data']->get_shipping_class();

            if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
              $found_shipping_classes[ $found_class ] = array();
            }

            $found_shipping_classes[ $found_class ][ $item_id ] = $values;
          }
        }

        return $found_shipping_classes;
      }

      private function get_shipping_cost( $cart_total, $key_base = '' ) {
        if ( $key_base !== '' ) {
          $key_base = "class_cost_{$key_base}_";
        }

        $shipping_cost = $this->get_option( $key_base . 'price', - 1 );

        if ( $shipping_cost < 0 ) {
          $shipping_cost = null;
        }

        if ( $this->get_option( $key_base . 'price_free', 0 ) <= $cart_total && $this->get_option( $key_base . 'price_free', 0 ) > 0 ) {
          $shipping_cost = 0;
        }

        return $shipping_cost;
      }

      /**
       * Call to calculate shipping rates for this method.
       * Rates can be added using the add_rate() method.
       * Return only active shipping methods.
       *
       * Part doing the calculation of shipping classes is copied from flatrate shipping module and edited to
       * fit and work with this code.
       *
       * @uses WC_Shipping_Method::add_rate()
       *
       * @param array $package Shipping package.
       */
      public function calculate_shipping( $package = array() ) {
        $cart = WC()->cart;

        $cart_total = $cart->get_cart_contents_total() + $cart->get_cart_contents_tax();

        $service_code = $this->get_option( 'shipping_method' );
        $service_title = $this->get_option( 'title' );

        $all_applied_coupons = $cart->get_applied_coupons();
        if ( $all_applied_coupons ) {
          foreach ( $all_applied_coupons as $coupon_code ) {
            $this_coupon = new WC_Coupon( $coupon_code );
            if ( $this_coupon->get_free_shipping() ) {

              $this->add_rate(
                array(
                  'meta_data' => [ 'service_code' => $service_code ],
                  'label'     => $service_title,
                  'cost'      => (string) 0,
                  'package'   => $package,
                )
              );

              return;
            }
          }
        }

        $shipping_cost = null;

        $shipping_classes = WC()->shipping->get_shipping_classes();

        if ( ! empty( $shipping_classes ) ) {
          $found_shipping_classes = $this->find_shipping_classes( $package );
          $highest_class_cost     = 0;

          foreach ( $found_shipping_classes as $shipping_class => $products ) {
            $shipping_zone = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );

            $class_shipping_cost = $this->get_shipping_cost( $cart_total, $shipping_zone->term_id );

            if ( $class_shipping_cost !== null ) {
              if ( $shipping_cost === null ) {
                $shipping_cost = 0;
              }

              if ( 'class' === $this->get_option( 'type' ) ) {
                $shipping_cost += $class_shipping_cost;
              } else {
                $highest_class_cost = $class_shipping_cost > $highest_class_cost ? $class_shipping_cost : $highest_class_cost;
              }
            }
          }

          if ( 'order' === $this->get_option( 'type' ) && $highest_class_cost ) {
            $shipping_cost += $highest_class_cost;
          }
        }

        if ( $shipping_cost === null ) {
          $shipping_cost = $this->get_shipping_cost( $cart_total );
        }

        $taxes = $this->calculate_shipping_tax( $shipping_cost );

        $shipping_cost = $shipping_cost - $taxes['total'];

        $this->add_rate(
            array(
              'meta_data' => [ 'service_code' => $service_code ],
              'label'     => $service_title,
              'cost'      => (string) $shipping_cost,
              'taxes'     => $taxes['taxes'],
              'package'   => $package,
            )
        );
      }

      public function process_admin_options() {
        if ( ! $this->instance_id ) {
          delete_transient( 'wc_pakettikauppa_shipping_methods' );
        }

        return parent::process_admin_options();
      }
    }
  }
}

add_action( 'woocommerce_shipping_init', 'wc_pakettikauppa_shipping_method_init' );

function add_wc_pakettikauppa_shipping_method( $methods ) {
  $methods['pakettikauppa_shipping_method'] = 'WC_Pakettikauppa_Shipping_Method';

  return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_wc_pakettikauppa_shipping_method' );
