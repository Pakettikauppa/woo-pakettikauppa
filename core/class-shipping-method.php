<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to the script
if ( ! defined('ABSPATH') ) {
  exit;
}

if ( ! class_exists(__NAMESPACE__ . '\Shipping_Method') ) {
  /**
   * Shipping_Method Class
   *
   * @class Shipping_Method
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Shipping_Method extends \WC_Shipping_Method {
    /**
     * @var Core
     */
    public $core;
    // public static $core = null;

    /**
     * Required to access Pakettikauppa client
     * @var Shipment $shipment
     */
    private $shipment = null;

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
      parent::__construct($instance_id);

    }

    public function injectCore( Core $plugin ) {
      $this->core = $plugin;

      return $this;
    }

    public function load() {
      $this->id = $this->core->shippingmethod; // ID for your shipping method. Should be unique.
      $this->method_title = $this->core->text->shipping_method_name();
      $this->method_description = $this->core->text->shipping_method_desc(); // Description shown in admin

      $this->supports = array(
        'settings',
      );

      $this->init();

      // Save settings in admin if you have any defined
      add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));

      if ( ! empty($this->get_instance_option('shipping_method')) ) {
        $this->method_description = $this->core->text->selected_shipping_method(
          $this->core->shipment->service_title($this->get_instance_option('shipping_method'))
        );

      }
    }

    /**
     * Initialize Pakettikauppa shipping
     */
    public function init() {
      $settings = $this->core->shipment->get_settings();
      $show_method_set = isset($settings['show_pakettikauppa_shipping_method']);
      $show_pakettikauppa_shipping_method = ! $show_method_set ? 'yes' : $settings['show_pakettikauppa_shipping_method'];

      /**
       * The shipping method should only be shown to users who used an earlier version of the plugin. Recent
       * versions of the plugin do not require using a shipping method; instead the plugin maps to existing
       * shipping methods in the store.
       */
      if ( $this->instance_id === 0 ) {
        if ( ! $show_method_set ) {
          $shipping_zones = \WC_Shipping_Zones::get_zones();

          /**
           * Disable the shipping method automatically if it's not set yet, unless
           * some zone shipping method matches with Shipping_Method.
           */
          $show_pakettikauppa_shipping_method = 'no';

          foreach ( $shipping_zones as $shipping_zone ) {
            foreach ( $shipping_zone['shipping_methods'] as $shipping_object ) {
              if ( get_class($shipping_object) === __NAMESPACE__ . '\Shipping_Method' ) {
                $show_pakettikauppa_shipping_method = 'yes';
              }
            }
          }

          $this->core->shipment->update_setting('show_pakettikauppa_shipping_method', $show_pakettikauppa_shipping_method);
          $this->core->shipment->save_settings();
          $settings = $this->core->shipment->get_settings();
        }
      }

      if ( $show_pakettikauppa_shipping_method === 'yes' ) {
        $this->supports[] = 'instance-settings';
        $this->supports[] = 'instance-settings-modal';
        $this->supports[] = 'shipping-zones';
      }

      $this->instance_form_fields = $this->my_instance_form_fields();
      $this->form_fields          = $this->my_global_form_fields();
      $this->title                = $this->get_option('title');
    }

    public function validate_pickuppoints_field( $key, $value ) {
      $values = wp_json_encode($value);
      return $values;
    }

    public function generate_pickuppoints_html( $key, $value ) {
      $field_key = $this->get_field_key($key);

      if ( $this->get_option($key) !== '' ) {
        $values = $this->get_option($key);
        if ( is_string($values) ) {
          $values = json_decode($this->get_option($key), true);
        }
      } else {
        $values = array();
      }

      $all_shipping_methods = $this->core->shipment->services();
      $additional_services = array();

      ob_start();
    ?>
      <script>
        function pkChangeOptions(elem, methodId) {

            var strUser = elem.options[elem.selectedIndex].value;

            var elements = document.getElementsByClassName('pk-services-' + methodId);
            for(var i=0; i<elements.length; ++i) {
                elements[i].style.display = "none";
            }

            if (strUser == '__PICKUPPOINTS__') {
                document.getElementById(methodId + '-pickuppoints').style.display = "block";
                document.getElementById(methodId + '-' + strUser + '-services').style.display = "none";
            } else {
                document.getElementById(methodId + '-pickuppoints').style.display = "none";
                document.getElementById(methodId + '-' + strUser + '-services').style.display = "block";
            }
        }
      </script>
      <tr>
        <th colspan="2" class="titledesc" scope="row"><?php echo esc_html($value['title']); ?></th>
      </tr>
      <tr>
        <td colspan="2">
          <?php foreach ( \WC_Shipping_Zones::get_zones('admin') as $zone_raw ) : ?>
            <hr>
            <?php $zone = new \WC_Shipping_Zone($zone_raw['zone_id']); ?>
            <h3>
              <?php esc_html_e('Zone name', 'woocommerce'); ?>: <?php echo $zone->get_zone_name(); ?>
            </h3>
            <p>
              <?php esc_html_e('Zone regions', 'woocommerce'); ?>: <?php echo $zone->get_formatted_location(); ?>
            </p>
            <h4><?php esc_html_e('Shipping method(s)', 'woocommerce'); ?></h4>
            <?php foreach ( $zone->get_shipping_methods() as $method_id => $shipping_method ) : ?>
              <?php if ( $shipping_method->enabled === 'yes' && $shipping_method->id !== $this->core->shippingmethod && $shipping_method->id !== 'local_pickup' ) : ?>
                <?php
                $selected_service = null;
                if ( ! empty($values[ $method_id ]['service']) ) {
                  $selected_service = $values[ $method_id ]['service'];
                }
                if ( empty($selected_service) ) {
                  $selected_service = '__PICKUPPOINTS__';
                }
                ?>
            <table style="border-collapse: collapse;" border="0">
              <th><?php echo $shipping_method->title; ?></th>
              <td style="vertical-align: top;">
                <select id="<?php echo $method_id; ?>-select" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo $method_id; ?>');">
                  <option value="__NULL__"><?php $this->core->text->no_shipping(); ?></option>
                  <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>>Noutopisteet</option>
                  <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                    <option value="<?php echo $service_id; ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?>>
                      <?php echo $service_name; ?>
                      <?php if ( $this->core->shipment->service_has_pickup_points($service_id) ) : ?>
                        (<?php $this->core->text->includes_pickup_points(); ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="vertical-align: top;">
                <div style='display: none;' id="<?php echo $method_id; ?>-pickuppoints">
                  <?php
                  $methods = $this->core->shipment->get_pickup_point_methods();
                  ?>
                  <?php foreach ( $methods as $method_code => $method_name ) : ?>
                    <input type="hidden"
                            name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                            value="no">
                    <p>
                      <input type="checkbox"
                              name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                              value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['active']) && $values[ $method_id ][ $method_code ]['active'] === 'yes') ? 'checked' : ''; ?>>
                      <?php echo $method_name; ?>
                    </p>
                  <?php endforeach; ?>
                </div>

                <?php $all_additional_services = $this->core->shipment->get_additional_services(); ?>
                <?php foreach ( $all_additional_services as $method_code => $additional_services ) : ?>
                  <div class="pk-services-<?php echo $method_id; ?>" style='display: none;' id="<?php echo $method_id; ?>-<?php echo $method_code; ?>-services">
                    <?php foreach ( $additional_services as $additional_service ) : ?>
                      <?php if ( empty($additional_service->specifiers) || in_array($additional_service->service_code, array( '3102' )) ) : ?>
                        <input type="hidden"
                                name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                value="no">
                        <p>
                          <input type="checkbox"
                                  name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                  value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ]) && $values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ] === 'yes') ? 'checked' : ''; ?>>
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
          <hr>

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
      $all_shipping_methods = array( '' => $this->core->text->select_one_shipping_method() );

      $all_services = $this->core->shipment->services();

      if ( $all_services !== null ) {
        foreach ( $all_services as $key => $value ) {
          $all_shipping_methods[ $key ] = $value;
        }
      }

      if ( empty($all_services) ) {
        $fields = array(
          'title' => array(
            'title'       => __('Title', 'woocommerce'),
            'type'        => 'text',
            'description' => $this->core->text->unable_connect_to_vendor_server(),
            'default'     => $this->core->vendor_name,
            'desc_tip'    => true,
          ),
        );

        return $fields;
      }

      $fields = array(
        /* Start new section */
        array(
          'description' => $this->core->text->legacy_shipping_method_desc(),
          'type'  => 'title',
          'title' => $this->core->text->note(),
        ),
        'title'           => array(
          'title'       => __('Title', 'woocommerce'),
          'type'        => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
          'default'     => $this->core->vendor_name,
          'desc_tip'    => true,
        ),
        array(
          'title' => $this->core->text->shipping_methods(),
          'type'  => 'title',
        ),

        'shipping_method' => array(
          'title'   => $this->core->text->shipping_method(),
          'type'    => 'select',
          'options' => $all_shipping_methods,
        ),

        array(
          'title'       => __('Shipping class costs', 'woocommerce'),
          'type'        => 'title',
          'default'     => '',
              /* translators: %s: URL for link. */
          'description' => sprintf(__('These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=shipping&section=classes')),
        ),
      );

      $shipping_classes = WC()->shipping->get_shipping_classes();

      if ( ! empty($shipping_classes) ) {
        foreach ( $shipping_classes as $shipping_class ) {
          if ( ! isset($shipping_class->term_id) ) {
            continue;
          }

          $fields[] = array(
            /* translators: %s: shipping class cost */
            'title'   => sprintf(__('"%s" shipping class cost', 'woocommerce'), esc_html($shipping_class->name)),
            'type'    => 'title',
            'default' => '',
          );

          $fields[ 'class_cost_' . $shipping_class->term_id . '_price' ] = array(
          /* translators: %s: shipping class name */
            'title'       => $this->core->text->price_vat_included(),
            'type'        => 'number',
            'default'     => null,
            'placeholder' => __('N/A', 'woocommerce'),
            'description' => $this->core->text->shipping_cost(),
            'desc_tip'    => true,
          );

          $fields[ 'class_cost_' . $shipping_class->term_id . '_price_free' ] = array(
            'title'       => $this->core->text->free_shipping_tier(),
            'type'        => 'number',
            'default'     => null,
            'description' => $this->core->text->free_shipping_tier_desc(),
            'desc_tip'    => true,
          );
        }

        $fields['type'] = array(
          'title'   => __('Calculation type', 'woocommerce'),
          'type'    => 'select',
          'class'   => 'wc-enhanced-select',
          'default' => 'class',
          'options' => array(
            'class' => __('Per class: Charge shipping for each shipping class individually', 'woocommerce'),
            'order' => __('Per order: Charge shipping for the most expensive shipping class', 'woocommerce'),
          ),
        );

      }

      $fields[] = array(
        'title'   => $this->core->text->default_shipping_class_cost(),
        'type'    => 'title',
        'default' => '',
      );

      $fields['price'] = array(
        'title'       => $this->core->text->no_shipping_class_cost(),
        'type'        => 'number',
        'default'     => $this->fee,
        'placeholder' => __('N/A', 'woocommerce'),
        'description' => $this->core->text->shipping_cost_vat_included(),
        'desc_tip'    => true,
      );

      $fields['price_free'] = array(
        'title'       => $this->core->text->free_shipping_tier(),
        'type'        => 'number',
        'default'     => '',
        'description' => $this->core->text->free_shipping_tier_desc(),
        'desc_tip'    => true,
      );

      return $fields;
    }

    private function my_global_form_fields() {
      return array(
        'mode'                       => array(
          'title'   => $this->core->text->mode(),
          'type'    => 'select',
          'default' => 'test',
          'options' => array(
            'test'       => $this->core->text->testing_environment(),
            'production' => $this->core->text->production_environment(),
          ),
        ),

        'account_number'             => array(
          'title'    => $this->core->text->api_key_title(),
          'desc'     => $this->core->text->api_key_desc($this->core->vendor_name),
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'secret_key'                 => array(
          'title'    => $this->core->text->api_secret_title(),
          'desc'     => $this->core->text->api_secret_desc($this->core->vendor_name),
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'pickup_points'              => array(
          'title' => $this->core->text->pickup_points_title(),
          'type'  => 'pickuppoints',
        ),

        array(
          'title'       => $this->core->text->shipping_settings_title(),
          'type'        => 'title',
          /* translators: %s: url to documentation */
          'description' => $this->core->text->shipping_settings_desc(),

        ),

        'add_tracking_to_email'      => array(
          'title'   => $this->core->text->add_tracking_link_to_email(),
          'type'    => 'checkbox',
          'default' => 'no',
        ),

        'add_pickup_point_to_email'      => array(
          'title'   => $this->core->text->add_pickup_point_to_email(),
          'type'    => 'checkbox',
          'default' => 'yes',
        ),

        'change_order_status_to'      => array(
          'title'   => $this->core->text->change_order_status_to(),
          'type'    => 'select',
          'default' => '',
          'options' => array(
            '' => $this->core->text->no_order_status_change(),
            'completed'  => __('Completed', 'woocommerce'),
            'processing' => __('Processing', 'woocommerce'),
          ),
        ),

        'create_shipments_automatically'     => array(
          'title'   => $this->core->text->create_shipments_automatically(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => $this->core->text->no_automatic_creation_of_labels(),
            /* translators: %s: order status */
            'completed'  => $this->core->text->when_order_status_is(__('Completed', 'woocommerce')),
            /* translators: %s: order status */
            'processing' => $this->core->text->when_order_status_is(__('Processing', 'woocommerce')),
          ),
        ),

        'download_type_of_labels'     => array(
          'title'   => $this->core->text->download_type_of_labels_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'browser'  => $this->core->text->download_type_of_labels_option_browser(),
            'download'  => $this->core->text->download_type_of_labels_option_download(),
          ),
        ),

        'post_label_to_url' => array(
          'title'   => $this->core->text->post_shipping_label_to_url_title(),
          'type'    => 'text',
          'default' => '',
          'description' => $this->core->text->post_shipping_label_to_url_desc(),
        ),

        'pickup_points_search_limit' => array(
          'title'       => $this->core->text->pickup_points_search_limit_title(),
          'type'        => 'number',
          'default'     => 5,
          'description' => $this->core->text->pickup_points_search_limit_desc(),
          'desc_tip'    => true,
        ),
        'pickup_point_list_type'     => array(
          'title'   => $this->core->text->pickup_point_list_type_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'menu'  => $this->core->text->pickup_point_list_type_option_menu(),
            'list'  => $this->core->text->pickup_point_list_type_option_list(),
          ),
        ),
        array(
          'title' => $this->core->text->store_owner_information(),
          'type'  => 'title',
        ),

        'sender_name'                => array(
          'title'   => $this->core->text->sender_name(),
          'type'    => 'text',
          'default' => get_bloginfo('name'),
        ),

        'sender_address'             => array(
          'title'   => $this->core->text->sender_address(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_address(),
        ),

        'sender_postal_code'         => array(
          'title'   => $this->core->text->sender_postal_code(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_postcode(),
        ),

        'sender_city'                => array(
          'title'   => $this->core->text->sender_city(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_city(),
        ),
        'info_code'                  => array(
          'title'   => $this->core->text->info_code(),
          'type'    => 'text',
          'default' => '',
        ),
        array(
          'title' => $this->core->text->cod_settings(),
          'type'  => 'title',
        ),
        'cod_iban'                   => array(
          'title'   => $this->core->text->cod_iban(),
          'type'    => 'text',
          'default' => '',
        ),
        'cod_bic'                    => array(
          'title'   => $this->core->text->cod_bic(),
          'type'    => 'text',
          'default' => '',
        ),
        array(
          'title' => $this->core->text->advanced_settings(),
          'type'  => 'title',
        ),
        'show_pakettikauppa_shipping_method' => array(
          'title'   => $this->core->text->show_shipping_method(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
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

        // Prevent division by zero when the cart total is zero
        $cost_item = 0;
        if ( $cart_total !== 0 ) {
          $cost_item = $shipping_cost * $item['line_total'] / $cart_total;
        }

        if ( $cart[ $cost_key ]['data'] !== null ) {
          $tax_obj = \WC_Tax::get_shipping_tax_rates($cart[ $cost_key ]['data']->get_tax_class());

          foreach ( $tax_obj as $key => $value ) {
            if ( ! isset($taxes[ $key ]) ) {
              $taxes[ $key ] = 0.0;
            }

            $taxes[ $key ] += round($cost_item - $cost_item / (1 + $value['rate'] / 100.0), 2);
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
          $exists = ! empty($found_class);

          // If WC_Product_Simple->get_shipping_class() returns nothing, skip. Item doesn't have a shipping class.
          if ( ! $exists ) {
            continue;
          }

          // Create the array on first iteration
          if ( $exists && ! isset($found_shipping_classes[ $found_class ]) ) {
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

      $shipping_cost = $this->get_option($key_base . 'price', - 1);

      if ( $shipping_cost < 0 ) {
        $shipping_cost = null;
      }

      if ( $this->get_option($key_base . 'price_free', 0) <= $cart_total && $this->get_option($key_base . 'price_free', 0) > 0 ) {
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

      $service_code = $this->get_option('shipping_method');
      $service_title = $this->get_option('title');

      $all_applied_coupons = $cart->get_applied_coupons();
      if ( $all_applied_coupons ) {
        foreach ( $all_applied_coupons as $coupon_code ) {
          $this_coupon = new \WC_Coupon($coupon_code);
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

      if ( ! empty($shipping_classes) ) {
        $found_shipping_classes = $this->find_shipping_classes($package);
        $highest_class_cost     = 0;

        foreach ( $found_shipping_classes as $shipping_class => $products ) {
          $shipping_zone = get_term_by('slug', $shipping_class, 'product_shipping_class');

          $class_shipping_cost = $this->get_shipping_cost($cart_total, $shipping_zone->term_id);

          if ( $class_shipping_cost !== null ) {
            if ( $shipping_cost === null ) {
              $shipping_cost = 0;
            }

            if ( 'class' === $this->get_option('type') ) {
              $shipping_cost += $class_shipping_cost;
            } else {
              $highest_class_cost = $class_shipping_cost > $highest_class_cost ? $class_shipping_cost : $highest_class_cost;
            }
          }
        }

        if ( 'order' === $this->get_option('type') && $highest_class_cost ) {
          $shipping_cost += $highest_class_cost;
        }
      }

      if ( $shipping_cost === null ) {
        $shipping_cost = $this->get_shipping_cost($cart_total);
      }

      $taxes = $this->calculate_shipping_tax($shipping_cost);

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
        delete_transient($this->core->prefix . '_shipping_methods');
      }

      return parent::process_admin_options();
    }
  }
}
