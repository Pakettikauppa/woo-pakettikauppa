<?php

// Prevent direct access to the script
if ( ! defined('ABSPATH') ) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '/class-wc-pakettikauppa.php';
require_once plugin_dir_path(__FILE__) . '/class-wc-pakettikauppa-shipment.php';

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

  if ( ! class_exists('WC_Pakettikauppa_Shipping_Method') ) {

    class WC_Pakettikauppa_Shipping_Method extends WC_Shipping_Method {
      /**
       * Required to access Pakettikauppa client
       */
      private $wc_pakettikauppa_shipment = null;

      /**
       * Default shipping fee
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
      public function __construct( $instance_id = 0 ) {
        $this->id = 'WC_Pakettikauppa_Shipping_Method'; // ID for your shipping method. Should be unique.
        $this->instance_id = absint($instance_id);

        $this->method_title = 'Pakettikauppa'; // Title shown in admin
        $this->method_description = __('All shipping methods with one contract. For more information visit <a href="https://www.pakettikauppa.fi/">Pakettikauppa</a>.', 'wc-pakettikauppa'); // Description shown in admin

        $this->enabled = 'yes';
        $this->title = 'Pakettikauppa';

        $this->init();
      }

      public function validate_pkprice_field( $key, $value ) {
        foreach ( $value as $service_code => $service_settings ) {
          $service_settings['price'] = wc_format_decimal(trim(stripslashes($service_settings['price'])));
          $service_settings['price_free'] = wc_format_decimal(trim(stripslashes($service_settings['price'])));
        }
        $values = json_encode($value);

        return $values;
      }

      public function generate_pkprice_html( $key, $value ) {
        $field_key = $this->get_field_key($key);

        if ( $this->get_option($key) !== '' ) {

          $values = $this->get_option($key);
          if ( is_string($values) ) {
            $values = json_decode($this->get_option($key), true);
          }
        } else {
          $values = array();
        }

        ob_start();
        ?>

      <tr valign="top">
        <?php if ( isset($value['title']) ) : ?>
          <th colspan="2"><label><?php esc_html($value['title']); ?></label></th>
        <?php endif; ?>
        </tr>
        <tr>
          <td colspan="2">
            <table>
              <thead>
              <tr>
                <th><?php esc_attr_e('Service', 'wc-pakettikauppa'); ?></th>
                <th style="width: 60px;"><?php esc_attr_e('Active', 'wc-pakettikauppa'); ?></th>
                <th style="text-align: center;"><?php esc_attr_e('Price', 'wc-pakettikauppa'); ?></th>
                <th style="text-align: center;"><?php esc_attr_e('Free shipping tier', 'wc-pakettikauppa'); ?></th>
                  <th style="text-align: center;"><?php esc_attr_e('Alternative name', 'wc-pakettikauppa'); ?></th>
              </tr>
              </thead>
              <tbody>
              <?php if ( isset($value['options']) ) : ?>
                <?php foreach ( $value['options'] as $service_code => $service_name ) : ?>
                  <?php if ( ! isset($values[ $service_code ]) ) : ?>
                    <?php $values[ $service_code ]['active'] = false; ?>
                    <?php $values[ $service_code ]['price'] = $this->fee; ?>
                    <?php $values[ $service_code ]['price_free'] = '0'; ?>
			              <?php $values[ $service_code ]['alternative_name'] = ''; ?>
                  <?php endif; ?>

                  <tr valign="top">
                    <th scope="row" class="titledesc">
                      <label><?php echo esc_html($service_name); ?></label>
                    </th>
                    <td>
                      <input type="hidden"
                             name="<?php echo esc_html($field_key) . '[' . esc_html($service_code) . '][active]'; ?>"
                             value="no">
                      <input type="checkbox"
                             name="<?php echo esc_html($field_key) . '[' . esc_html($service_code) . '][active]'; ?>"
                             value="yes" <?php echo $values[ $service_code ]['active'] === 'yes' ? 'checked' : ''; ?>>
                    </td>
                    <td>
                      <input type="number"
                             name="<?php echo esc_html($field_key) . '[' . esc_html($service_code) . '][price]'; ?>"
                             step="0.01"
                             value="<?php echo esc_html($values[ $service_code ]['price']); ?>">
                    </td>
                    <td>
                      <input type="number"
                             name="<?php echo esc_html($field_key) . '[' . esc_html($service_code) . '][price_free]'; ?>"
                             step="0.01"
                             value="<?php echo esc_html($values[ $service_code ]['price_free']); ?>">
                    </td>
                      <td>
                          <input type="text"
                                 name="<?php echo esc_html($field_key) . '[' . esc_html($service_code) . '][alternative_name]'; ?>"
                                 value="<?php echo esc_html($values[ $service_code ]['alternative_name']); ?>">
                      </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>

              </tbody>
            </table>
          </td>
        </tr>

        <?php

        $html = ob_get_contents();
        ob_end_clean();

        return $html;
      }

      /**
       * Initialize Pakettikauppa shipping
       */
      public function init() {
        // Make Pakettikauppa API accessible via WC_Pakettikauppa_Shipment
        $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
        $this->wc_pakettikauppa_shipment->load();

        // Save settings in admin if you have any defined
        add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));
      }

      /**
       * Initialize form fields
       */
      public function init_form_fields() {

        $this->form_fields = array(
          'mode' => array(
            'title'   => __('Mode', 'wc-pakettikauppa'),
            'type'    => 'select',
            'default' => 'test',
            'options' => array(
              'test'       => __('Testing environment', 'wc-pakettikauppa'),
              'production' => __('Production environment', 'wc-pakettikauppa'),
            ),
          ),

          'account_number' => array(
            'title'    => __('API key', 'wc-pakettikauppa'),
            'desc'     => __('API key provided by Pakettikauppa', 'wc-pakettikauppa'),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          'secret_key' => array(
            'title'    => __('API secret', 'wc-pakettikauppa'),
            'desc'     => __('API Secret provided by Pakettikauppa', 'wc-pakettikauppa'),
            'type'     => 'text',
            'default'  => '',
            'desc_tip' => true,
          ),

          /* Start new section */
          array(
            'title' => __('Shipping methods', 'wc-pakettikauppa'),
            'type'  => 'title',
          ),

          'active_shipping_options'    => array(
            'type'    => 'pkprice',
            'options' => $this->wc_pakettikauppa_shipment->services(true),
          ),

          array(
            'title' => __('Shipping settings', 'wc-pakettikauppa'),
            'type'  => 'title',
          ),

          'add_tracking_to_email' => array(
            'title'   => __('Add tracking link to the order completed email', 'wc-pakettikauppa'),
            'type'    => 'checkbox',
            'default' => 'no',
          ),

          'pickup_points_search_limit' => array(
            'title'       => __('Pickup point search limit', 'wc-pakettikauppa'),
            'type'        => 'number',
            'default'     => 5,
            'description' => __('Limit the amount of nearest pickup points shown.', 'wc-pakettikauppa'),
            'desc_tip'    => true,
          ),

          /* Start new section */
          array(
            'title' => __('Store owner information', 'wc-pakettikauppa'),
            'type'  => 'title',
          ),

          'sender_name' => array(
            'title'   => __('Sender name', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_address' => array(
            'title'   => __('Sender address', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_postal_code' => array(
            'title'   => __('Sender postal code', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'sender_city' => array(
            'title'   => __('Sender city', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'cod_iban' => array(
            'title'   => __('Bank account number for Cash on Delivery (IBAN)', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'cod_bic' => array(
            'title'   => __('BIC code for Cash on Delivery', 'wc-pakettikauppa'),
            'type'    => 'text',
            'default' => '',
          ),

          'info_code' => array(
            'title'   => __('Info-code for shipments'),
            'type'    => 'text',
            'default' => '',
          ),
        );
      }

      public function process_admin_options() {
        $this->init_form_fields();

        parent::process_admin_options();
      }

      public function get_admin_options_html() {
        $this->init_form_fields();

        $this->init_settings();

        return parent::get_admin_options_html();
      }

      /**
       * Mostly copy-pasted from WooCommerce:
       *   woocommerce/includes/abstracts/abstract-wc-shipping-method.php
       *   protected function get_taxes_per_item( $costs ) and edited it A LOT.
       *
       * @param $shippingCost
       * @return array
       */
      private function calculate_shipping_tax( $shippingCost ) {
        $taxes = array();

        $taxesTotal = 0;
        $cartObj = WC()->cart;
        $cart_total = $cartObj->get_cart_contents_total();

        $cart = $cartObj->get_cart();

        foreach ( $cart as $item ) {
          $cost_key = $item['key'];

          $costItem = $shippingCost * $item['line_total'] / $cart_total;

          $taxObj = WC_Tax::get_shipping_tax_rates($cart[ $cost_key ]['data']->get_tax_class());

          foreach ( $taxObj as $key => $value ) {
            if ( ! isset($taxes[ $key ]) ) {
              $taxes[ $key ] = 0.0;
            }
            $taxes[ $key ] += round( $costItem - $costItem / ( 1 + $value['rate'] / 100.0 ), 2);
          }
        }

        foreach ( $taxes as $_tax ) {
          $taxesTotal += $_tax;
        }

        return array(
          'total' => $taxesTotal,
          'taxes' => $taxes,
        );
      }

      /**
       * Call to calculate shipping rates for this method.
       * Rates can be added using the add_rate() method.
       * Return only active shipping methods.
       *
       * @uses WC_Shipping_Method::add_rate()
       *
       * @param array $package Shipping package.
       */
      public function calculate_shipping( $package = array() ) {
        $cart = WC()->cart;
        $cart_total = $cart->get_cart_contents_total() + $cart->get_cart_contents_tax();

        $shipping_settings = json_decode($this->get_option('active_shipping_options'), true);

        ksort($shipping_settings);

        foreach ( $shipping_settings as $service_code => $service_settings ) {
          if ( $service_settings['active'] !== 'yes' ) {
            continue;
          }

          $shipping_cost = $service_settings['price'];

          if ( $service_settings['price_free'] <= $cart_total && $service_settings['price_free'] > 0 ) {
            $shipping_cost = 0;
          }

          $taxes = $this->calculate_shipping_tax($shipping_cost);

          $shipping_cost = $shipping_cost - $taxes['total'];

          $service_title = $this->wc_pakettikauppa_shipment->service_title($service_code);

          if ( ! empty( $service_settings['alternative_name'] ) ) {
              $service_title = trim($service_settings['alternative_name']);
          }

          $this->add_rate(
            array(
              'id'        => 'pk:' . $service_code,
              'meta_data' => [ 'service_code' => $service_code ],
              'label'     => $service_title,
              'cost'      => (string) $shipping_cost,
              'taxes'     => $taxes['taxes'],
            )
          );
        }
      }
    }
  }
}

add_action('woocommerce_shipping_init', 'wc_pakettikauppa_shipping_method_init');

function add_wc_pakettikauppa_shipping_method( $methods ) {
  $methods[] = 'WC_Pakettikauppa_Shipping_Method';
  return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_wc_pakettikauppa_shipping_method');
