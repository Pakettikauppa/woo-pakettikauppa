<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to the script
use WC_Countries;

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
     * Required to access Pakettikauppa client
     * @var Shipment $shipment
     */
    private $shipment = null;

    public $is_loaded = false;

    /**
     * Constructor for Pakettikauppa shipping class
     *
     * @access public
     * @return void
     */
    public function __construct( $instance_id = 0 ) {
      parent::__construct($instance_id);

      $this->load();
    }

    /**
     * Inject plugin core class to have access to other classes such as Text.
     * Better solution than making the core class a singleton and calling it with a hardcoded name.
     * Kept for "historical value".
     */
    /*public function injectCore( Core $plugin ) {
      $this->core = $plugin;

      return $this;
    } */

    public function get_core() {
      return \Wc_Pakettikauppa::get_instance();
    }

    public function load() {
      if ( $this->is_loaded ) {
        return;
      }

      $this->id = $this->get_core()->shippingmethod; // ID for your shipping method. Should be unique.
      $this->method_title = $this->get_core()->text->shipping_method_name();
      $this->method_description = $this->get_core()->text->shipping_method_desc(); // Description shown in admin

      $this->supports = array(
        'settings',
      );

      $this->init();

      // Save settings in admin if you have any defined
      add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));

      $settings = $this->get_core()->shipment->get_settings();
      $mode = $settings['mode'];
      $configs = $this->get_core()->api_config;
      $configs[$mode] = array_merge(
        array(
          'api_key'   => $settings['account_number'],
          'secret'    => $settings['secret_key'],
          'use_posti_auth' => false,
        ),
        $this->get_core()->api_config[$mode]
      );
      $this->client = new \Pakettikauppa\Client($configs, $mode);
      $this->client->setComment($this->get_core()->api_comment);
      if ( $configs[$mode]['use_posti_auth'] ) {
        $transient_name = $this->get_core()->prefix . '_access_token';
        $token = get_transient($transient_name);
        if ( empty($token) ) {
          $token = $this->client->getToken();
          set_transient($transient_name, $token, $token->expires_in - 100);
        }
        $this->client->setAccessToken($token->access_token);
      }

      $this->is_loaded = true;
    }

    /**
     * Initialize Pakettikauppa shipping
     */
    public function init() {
      $this->form_fields          = $this->my_global_form_fields();
      $this->title                = $this->get_option('title');
    }

    public function validate_pickuppoints_field( $key, $value ) {
      $values = wp_json_encode($value);
      return $values;
    }

    public function generate_notices_html( $key, $value ) {
      $settings = $this->get_core()->shipment->get_settings();
      $shipping_method = $this->get_core()->shippingmethod;
      $field_pref = 'woocommerce_' . $shipping_method . '_';
      $configs = $this->get_core()->api_config;
      if ( isset($_POST[$field_pref . 'mode']) ) {
        $settings['mode'] = $_POST[$field_pref . 'mode'];
        $settings['account_number'] = $_POST[$field_pref . 'account_number'];
        $settings['secret_key'] = $_POST[$field_pref . 'secret_key'];
      }
      $mode = $settings['mode'];

      $api_good = true;
      if ( empty($settings['account_number']) || empty($settings['secret_key']) ) {
        $api_good = false;
      } else {
        $result = $this->client->listShippingMethods();
        if ( empty($result) ) {
          $api_good = false;
        }
      }

      ob_start();
      ?>
      <script>
      jQuery(function( $ ) {
        $( document ).ready(function() {
          hide_mode_react();
          $( document ).on("change", "#woocommerce_pakettikauppa_shipping_method_mode", function() {
            hide_mode_react();
          });
        });
        function hide_mode_react() {
          var show = true;
          if ($("#woocommerce_pakettikauppa_shipping_method_mode").val() == 'production') {
            <?php if ( $api_good ) : ?>
              show = true;
            <?php else : ?>
              show = false;
            <?php endif; ?>
          }
          if (show) {
            $(".mode_react").closest("tr").removeClass("row-disabled");
            $("h3.mode_react").removeClass("row-disabled");
          }
          else {
            $(".mode_react").closest("tr").addClass("row-disabled");
            $("h3.mode_react").addClass("row-disabled");
          }
        }
      });
      </script>
      <?php if ( $mode == 'production' && ! $api_good ) : ?>
        <tr><td colspan="2">
          <div class="pakettikauppa-notice notice-error">
            <p><?php esc_attr_e('API credentials are not working. Please check that API credentials are correct.', 'woo-pakettikauppa'); ?></p>
          </div>
        </td></tr>
      <?php endif; ?>
      <?php
      $html = ob_get_contents();
      ob_end_clean();
      return $html;
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

      $all_shipping_methods = $this->get_core()->shipment->services();
      if ( empty($all_shipping_methods) ) {
        $all_shipping_methods = array();
      }

      $methods = $this->get_core()->shipment->get_pickup_point_methods();

      ob_start();
    ?>
      <script>
        function pkChangeOptions(elem, methodId) {

            var strUser = elem.options[elem.selectedIndex].value;
            var elements = document.getElementsByClassName('pk-services-' + methodId);

            var servicesElement = document.getElementById('services-' + methodId + '-' + strUser);
            var pickuppointsElement = document.getElementById('pickuppoints-' + methodId);

            for(var i=0; i<elements.length; ++i) {
                elements[i].style.display = "none";
            }

            if (strUser == '__PICKUPPOINTS__') {
              if (pickuppointsElement) pickuppointsElement.style.display = "block";
              if (servicesElement) servicesElement.style.display = "none";
            } else {
              if (pickuppointsElement) pickuppointsElement.style.display = "none";
              if (servicesElement) servicesElement.style.display = "block";
            }
        }
      </script>
      <tr>
        <th colspan="2" class="titledesc mode_react" scope="row"><?php echo esc_html($value['title']); ?></th>
      </tr>
      <tr>
        <td colspan="2" class="mode_react">
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
              <?php if ( $shipping_method->enabled === 'yes' && $shipping_method->id !== $this->get_core()->shippingmethod && $shipping_method->id !== 'local_pickup' ) : ?>
                <?php
                $selected_service = null;
                if ( ! empty($values[ $method_id ]['service']) ) {
                  $selected_service = $values[ $method_id ]['service'];
                }
                if ( empty($selected_service) && ! empty($methods) ) {
                  $selected_service = '__PICKUPPOINTS__';
                }
                ?>
            <table style="border-collapse: collapse;" border="0">
              <th><?php echo $shipping_method->title; ?></th>
              <td style="vertical-align: top;">
                <select id="<?php echo $method_id; ?>-select" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo $method_id; ?>');">
                  <option value="__NULL__"><?php echo $this->get_core()->text->no_shipping(); ?></option>  //Issue: #171, was no echo
                  <?php if ( ! empty($methods) ) : ?>
                    <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>>Noutopisteet</option>
                  <?php endif; ?>
                  <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                    <option value="<?php echo $service_id; ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?>>
                      <?php echo $service_name; ?>
                      <?php if ( $this->get_core()->shipment->service_has_pickup_points($service_id) ) : ?>
                        (<?php $this->get_core()->text->includes_pickup_points(); ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="vertical-align: top;">
                <div style='display: none;' id="pickuppoints-<?php echo $method_id; ?>">
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

                <?php
                $all_additional_services = $this->get_core()->shipment->get_additional_services();
                if ( empty($all_additional_services) ) {
                  $all_additional_services = array();
                }
                ?>
                <?php foreach ( $all_additional_services as $method_code => $additional_services ) : ?>
                  <div class="pk-services-<?php echo $method_id; ?>" style='display: none;' id="services-<?php echo $method_id; ?>-<?php echo $method_code; ?>">
                    <?php foreach ( $additional_services as $additional_service ) : ?>
                      <?php if ( empty($additional_service->specifiers) || in_array($additional_service->service_code, array( '3102' ), true) ) : ?>
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

    protected function get_form_field_mode() {
      return array(
        'title'   => $this->get_core()->text->mode(),
        'type'    => 'select',
        'default' => 'test',
        'options' => array(
          'test'       => $this->get_core()->text->testing_environment(),
          'production' => $this->get_core()->text->production_environment(),
        ),
      );
    }

    private function my_global_form_fields() {
      $wc_countries = new WC_Countries();

      return array(
        'notices'    => array(
          'type'     => 'notices',
        ),

        array(
          'title' => '',
          'type'  => 'title',
          'class' => 'hidden',
        ),

        'mode'                       => $this->get_form_field_mode(),

        'account_number'             => array(
          'title'    => $this->get_core()->text->api_key_title(),
          'desc'     => $this->get_core()->text->api_key_desc($this->get_core()->vendor_name),
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'secret_key'                 => array(
          'title'    => $this->get_core()->text->api_secret_title(),
          'desc'     => $this->get_core()->text->api_secret_desc($this->get_core()->vendor_name),
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'pickup_points'              => array(
          'title' => $this->get_core()->text->pickup_points_title(),
          'type'  => 'pickuppoints',
        ),

        array(
          'title'       => $this->get_core()->text->shipping_settings_title(),
          'type'        => 'title',
          /* translators: %s: url to documentation */
          'description' => $this->get_core()->text->shipping_settings_desc(),
        ),

        'add_tracking_to_email'      => array(
          'title'   => $this->get_core()->text->add_tracking_link_to_email(),
          'type'    => 'checkbox',
          'default' => 'yes',
          'class'   => 'mode_react',
        ),

        'add_pickup_point_to_email'      => array(
          'title'   => $this->get_core()->text->add_pickup_point_to_email(),
          'type'    => 'checkbox',
          'default' => 'yes',
          'class'   => 'mode_react',
        ),

        'change_order_status_to'      => array(
          'title'   => $this->get_core()->text->change_order_status_to(),
          'type'    => 'select',
          'default' => '',
          'options' => array(
            '' => $this->get_core()->text->no_order_status_change(),
            'completed'  => __('Completed', 'woocommerce'),
            'processing' => __('Processing', 'woocommerce'),
          ),
          'class'   => 'mode_react',
        ),

        'create_shipments_automatically'     => array(
          'title'   => $this->get_core()->text->create_shipments_automatically(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => $this->get_core()->text->no_automatic_creation_of_labels(),
            /* translators: %s: order status */
            'completed'  => $this->get_core()->text->when_order_status_is(__('Completed', 'woocommerce')),
            /* translators: %s: order status */
            'processing' => $this->get_core()->text->when_order_status_is(__('Processing', 'woocommerce')),
          ),
          'class'   => 'mode_react',
        ),

        'download_type_of_labels'     => array(
          'title'   => $this->get_core()->text->download_type_of_labels_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'browser'  => $this->get_core()->text->download_type_of_labels_option_browser(),
            'download'  => $this->get_core()->text->download_type_of_labels_option_download(),
          ),
          'class'   => 'mode_react',
        ),

        'post_label_to_url' => array(
          'title'   => $this->get_core()->text->post_shipping_label_to_url_title(),
          'type'    => 'text',
          'default' => '',
          'description' => $this->get_core()->text->post_shipping_label_to_url_desc(),
          'class'   => 'mode_react',
        ),

        'pickup_points_search_limit' => array(
          'title'       => $this->get_core()->text->pickup_points_search_limit_title(),
          'type'        => 'number',
          'default'     => 5,
          'description' => $this->get_core()->text->pickup_points_search_limit_desc(),
          'desc_tip'    => true,
          'class'   => 'mode_react',
        ),
        'pickup_point_list_type'     => array(
          'title'   => $this->get_core()->text->pickup_point_list_type_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'menu'  => $this->get_core()->text->pickup_point_list_type_option_menu(),
            'list'  => $this->get_core()->text->pickup_point_list_type_option_list(),
          ),
          'class'   => 'mode_react',
        ),
        array(
          'title' => $this->get_core()->text->store_owner_information(),
          'type'  => 'title',
        ),

        'sender_name'                => array(
          'title'   => $this->get_core()->text->sender_name(),
          'type'    => 'text',
          'default' => get_bloginfo('name'),
        ),

        'sender_address'             => array(
          'title'   => $this->get_core()->text->sender_address(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_address(),
        ),

        'sender_postal_code'         => array(
          'title'   => $this->get_core()->text->sender_postal_code(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_postcode(),
        ),

        'sender_city'                => array(
          'title'   => $this->get_core()->text->sender_city(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_city(),
        ),

        'sender_country'                => array(
          'title'   => $this->get_core()->text->sender_country(),
          'type'    => 'select',
          'default' => WC()->countries->get_base_country(),
          'options'   => $wc_countries->get_countries(),
        ),

        'sender_phone'                => array(
          'title'   => $this->get_core()->text->sender_phone(),
          'type'    => 'text',
        ),

        'sender_email'                => array(
          'title'   => $this->get_core()->text->sender_email(),
          'type'    => 'email',
        ),

        'info_code'                  => array(
          'title'   => $this->get_core()->text->info_code(),
          'type'    => 'text',
          'default' => '',
        ),
        'cod_title' => array(
          'title' => $this->get_core()->text->cod_settings(),
          'type'  => 'title',
        ),
        'cod_iban'                   => array(
          'title'   => $this->get_core()->text->cod_iban(),
          'type'    => 'text',
          'default' => '',
        ),
        'cod_bic'                    => array(
          'title'   => $this->get_core()->text->cod_bic(),
          'type'    => 'text',
          'default' => '',
        ),
        array(
          'title' => $this->get_core()->text->advanced_settings(),
          'type'  => 'title',
        ),
        'show_pickup_point_override_query' => array(
          'title'   => $this->get_core()->text->show_pickup_point_override_query(),
          'type'    => 'select',
          'default' => 'yes',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
        ),
      );
    }

    public function process_admin_options() {
      delete_transient($this->get_core()->prefix . '_shipping_methods');

      return parent::process_admin_options();
    }
  }
}
