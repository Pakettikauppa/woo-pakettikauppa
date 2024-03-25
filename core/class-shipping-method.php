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

      ob_start();
      ?>
      <script>
      jQuery(function( $ ) {
        $( document ).ready(function() {
          hide_mode_react();

          $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
              action: 'check_api',
              api_account: "<?php echo $settings['account_number']; ?>",
              api_secret: "<?php echo $settings['secret_key']; ?>"
            },
            dataType: 'json'
          }).done(function( status ) {
            <?php if ( $mode == 'production' ) : ?>
              hide_mode_react(status.api_good);
              if (status.api_good) {
                show_api_notice("", false);
              } else {
                var msg = status.msg;
                if (status.error) {
                  msg += ".<br/><b><?php _e('Error', 'woo-pakettikauppa'); ?>:</b> " + status.error;
                }
                if (status.code) {
                  msg += " <i>(<?php _e('Code', 'woo-pakettikauppa'); ?> " + status.code + ")</i>";
                }
                show_api_notice(msg, true);
              }
            <?php endif; ?>
          });

          $( document ).on("change", "#woocommerce_pakettikauppa_shipping_method_mode", function() {
            hide_mode_react();
            show_api_notice("", false);
          });
        });

        function hide_mode_react( show = true ) {
          if (show) {
            $(".mode_react").closest("tr").removeClass("row-disabled");
            $("h3.mode_react").removeClass("row-disabled");
          }
          else {
            $(".mode_react").closest("tr").addClass("row-disabled");
            $("h3.mode_react").addClass("row-disabled");
          }
        }

        function show_api_notice(text, show = true) {
          if (show) {
            $("#pakettikauppa_notices").show();
            $("#pakettikauppa_notice_api span").html(text+".");
            $("#pakettikauppa_notice_api").show();
          } else {
            $("#pakettikauppa_notices").hide();
            $("#pakettikauppa_notice_api").hide();
            $("#pakettikauppa_notice_api p").text('');
          }
        }
      });
      </script>
      <tr id="pakettikauppa_notices" style="display:none;"><td colspan="2">
        <div id="pakettikauppa_notice_api" class="pakettikauppa-notice notice-error">
          <p><b><?php echo strtoupper(__('API error!', 'woo-pakettikauppa')); ?></b> <span></span></p>
        </div>
      </td></tr>
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
        function pkSetInputs(parent, disabled) {
            var inputs = parent.querySelectorAll('input');
            for(var j=0; j<inputs.length; ++j) {
                if (disabled){
                    inputs[j].setAttribute('disabled', disabled);
                } else {
                    inputs[j].removeAttribute('disabled');
                }
            }
        }

        function pkChangeOptions(elem, methodId) {

            var strUser = elem.options[elem.selectedIndex].value;
            var elements = document.getElementsByClassName('pk-services-' + methodId);

            var servicesElement = document.getElementById('services-' + methodId + '-' + strUser);
            var pickuppointsElement = document.getElementById('pickuppoints-' + methodId);
            var servicePickuppointsElement = document.getElementById('service-' + methodId + '-' + strUser + '-pickuppoints');

            for(var i=0; i<elements.length; ++i) {
                elements[i].style.display = "none";
                pkSetInputs(elements[i], true);
            }



            if (strUser == '__PICKUPPOINTS__') {
              if (pickuppointsElement) {
                  pickuppointsElement.style.display = "block";
                  pkSetInputs(pickuppointsElement, false);
              }
              if (servicesElement) {
                  servicesElement.style.display = "none";
                  pkSetInputs(servicesElement, true);
              }
            } else {
              if (pickuppointsElement) {
                  pickuppointsElement.style.display = "none";
                  pkSetInputs(pickuppointsElement, true);
              }
              if (servicesElement) {
                  servicesElement.style.display = "block";
                  pkSetInputs(servicesElement, false);
              }
              if (elem.options[elem.selectedIndex].getAttribute('data-haspp') == 'true') {
                  servicePickuppointsElement.style.display = "block";
                  pkSetInputs(servicePickuppointsElement, false);
              }
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
                if ( empty($selected_service) && ! empty($methods) && isset($values[$method_id]) ) {
                  $selected_service = '__PICKUPPOINTS__';
                }
                ?>
            <table style="border-collapse: collapse;" border="0">
              <th><?php echo $shipping_method->title; ?></th>
              <td style="vertical-align: top;">
                <select id="<?php echo $method_id; ?>-select" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo $method_id; ?>');">
                  <option value="__NULL__"><?php echo $this->get_core()->text->no_shipping(); ?></option>
                  <?php if ( ! empty($methods) ) : ?>
                    <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>>Noutopisteet</option>
                  <?php endif; ?>
                  <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                    <?php $has_pp = ($this->get_core()->shipment->service_has_pickup_points($service_id)) ? true : false; ?>
                    <option value="<?php echo $service_id; ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?> data-haspp="<?php echo ($has_pp) ? 'true' : 'false'; ?>">
                      <?php echo $service_name; ?>
                      <?php if ( $has_pp ) : ?>
                        (<?php echo $this->get_core()->text->includes_pickup_points(); ?>)
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
                      <label>
                        <input type="checkbox"
                              name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                              value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['active']) && $values[ $method_id ][ $method_code ]['active'] === 'yes') ? 'checked' : ''; ?>>
                        <?php echo $method_name; ?>
                      </label>
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
                          <label>
                            <input type="checkbox"
                                  name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                  value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ]) && $values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ] === 'yes') ? 'checked' : ''; ?>>
                            <?php echo $additional_service->name; ?>
                          </label>
                        </p>
                      <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden"
                      name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>"
                      value="no">
                    <p>
                      <label>
                        <input type="checkbox"
                              name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>"
                              value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services']['return_label']) && $values[ $method_id ][ $method_code ]['additional_services']['return_label'] === 'yes') ? 'checked' : ''; ?>>
                        <?php echo __('Include return label (if available)', 'woo-pakettikauppa'); ?>
                      </label>
                    </p>
                  </div>
                <?php endforeach; ?>
                <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                  <?php if ( $this->get_core()->shipment->service_has_pickup_points($service_id) ) : ?>
                    <div id="service-<?php echo $method_id; ?>-<?php echo $service_id; ?>-pickuppoints" class="pk-services-<?php echo $method_id; ?>" style="display: none;">
                      <input type="hidden"
                        name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="no">
                      <p>
                        <label>
                          <input type="checkbox"
                            name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>"
                            value="yes" <?php echo ((! empty($values[ $method_id ][ $service_id ]['pickuppoints']) && $values[ $method_id ][ $service_id ]['pickuppoints'] === 'yes') || empty($values[ $method_id ][ $service_id ]['pickuppoints'])) ? 'checked' : ''; ?>>
                          <?php echo __('Pickup points', 'woo-pakettikauppa'); ?>
                        </label>
                      </p>
                    </div>
                  <?php endif; ?>
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

    public function generate_enchancedtextarea_html( $key, $value ) {
      $field_key = $this->get_field_key($key);
      $field_value = $this->get_option($key);

      ob_start();
      ?>

      <tr valign="top" class="pakettikauppa-setting">
        <th scope="row" class="titledesc">
          <label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label>
        </th>
        <td class="forminp">
          <fieldset>
            <legend class="screen-reader-text"><span><?php echo esc_html($value['title']); ?></span></legend>
            <textarea rows="3" cols="20" class="input-text wide-input " type="textarea" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" style="" placeholder=""><?php echo esc_html($field_value); ?></textarea>
            <?php if ( ! empty($value['available_params']) && is_array($value['available_params']) ) : ?>
                <?php foreach ( $value['available_params'] as $param_key => $param_desc ) : ?>
                  <p class="description enchtext noselect">
                    <code class="enchtext-code" data-param="<?php echo esc_html($param_key); ?>" onclick="click_enchancedtextarea_code('<?php echo $field_key; ?>', '<?php echo esc_html($param_key); ?>');">{<?php echo esc_html($param_key); ?>}</code> - <?php echo esc_html($param_desc); ?>
                  </p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ( ! empty($value['description']) ) : ?>
              <p class="description"><?php echo $value['description']; ?></p>
            <?php endif; ?>
          </fieldset>
        </td>
      </tr>

      <?php
      $html = ob_get_contents();
      ob_end_clean();
      return $html;
    }

    public function generate_button_html( $key, $value ) {
      $field_key = $this->get_field_key($key);
      ob_start();
      ?>
      <tr valign="top" class="pakettikauppa-setting">
        <th scope="row" class="titledesc">
          <label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label>
        </th>
        <td class="forminp">
          <fieldset>
            <a class="button button-primary" href="<?php echo $value['url']; ?>">
              <?php echo $value['text']; ?>
            </a>
          </fieldset>
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

      $fields = array(
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
          'type'     => 'password',
          'default'  => '',
          'desc_tip' => true,
        ),

        'order_pickup'              => array(
          'title' => $this->get_core()->text->order_pickup_title(),
          'type'  => 'title',
        ),

        'order_pickup_customer_id'                 => array(
          'title'    => $this->get_core()->text->customer_id_title(),
          'desc'     => '',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'order_pickup_invoice_id'                 => array(
          'title'    => $this->get_core()->text->invoice_id_title(),
          'desc'     => '',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'order_pickup_sender_id'                 => array(
          'title'    => $this->get_core()->text->sender_id_title(),
          'desc'     => '',
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

        'ignore_product_weight'      => array(
          'title'   => $this->get_core()->text->ignore_product_weight(),
          'type'    => 'checkbox',
          'default' => 'no',
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

        'labels_size'     => array(
          'title'   => $this->get_core()->text->labels_size_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'A5'  => 'A5',
            '107x225'  => '107x225',
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

        array(
          'title' => $this->get_core()->text->checkout_settings(),
          'type'  => 'title',
        ),

        'field_phone_required' => array(
          'title'   => $this->get_core()->text->field_phone_required(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
        ),

        'pickup_points_type' => array(
          'title' => $this->get_core()->text->pickup_points_type_title(),
          'type' => 'multiselect',
          'options' => array(
            'all' => $this->get_core()->text->pickup_points_type_all(),
            'PRIVATE_LOCKER' => $this->get_core()->text->pickup_points_type_private_locker(),
            'OUTDOOR_LOCKER' => $this->get_core()->text->pickup_points_type_outdoor_locker(),
            'PARCEL_LOCKER' => $this->get_core()->text->pickup_points_type_parcel_locker(),
            'PICKUP_POINT,AGENCY' => $this->get_core()->text->pickup_points_type_pickup_point(),
          ),
          'default' => 'all',
          'description' => $this->get_core()->text->pickup_points_type_desc(),
          'desc_tip'    => true,
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
        'show_pickup_point_override_query' => array(
          'title'   => $this->get_core()->text->show_pickup_point_override_query(),
          'type'    => 'select',
          'default' => 'yes',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
          'description' => $this->get_core()->text->pickup_points_override_query_desc(),
          'desc_tip'    => true,
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
        'label_additional_info' => array(
          'title'   => $this->get_core()->text->additional_info_param_title(),
          'type'    => 'enchancedtextarea',
          'description' => '',
          'available_params' => array(
            'ORDER_NUMBER' => $this->get_core()->text->additional_info_param_order_number(),
            'PRODUCTS_NAMES' => $this->get_core()->text->additional_info_param_products_names(),
            'PRODUCTS_SKU' => $this->get_core()->text->additional_info_param_products_sku(),
          ),
        ),
      );
      //unset order pickup settings if feature is disabled
      if ( ! $this->get_core()->order_pickup ) {
          unset($fields['order_pickup']);
          unset($fields['order_pickup_customer_id']);
          unset($fields['order_pickup_invoice_id']);
          unset($fields['order_pickup_sender_id']);
      }
      if ( get_option($this->get_core()->prefix . '_wizard_done') == 1 ) {
        $fields['setup_wizard'] = array(
          'title'   => $this->get_core()->text->setup_wizard(),
          'type'    => 'button',
          'url'     => esc_url(admin_url('admin.php?page=' . $this->get_core()->setup_page)),
          'text'    => $this->get_core()->text->restart_setup_wizard(),
        );
      }
      return $fields;
    }

    public function process_admin_options() {
      delete_transient($this->get_core()->prefix . '_shipping_methods');
      update_option($this->get_core()->prefix . '_wizard_done', 1);
      //delete token on update, in case settings changed
      delete_transient($this->get_core()->prefix . '_access_token');
      return parent::process_admin_options();
    }
  }
}
