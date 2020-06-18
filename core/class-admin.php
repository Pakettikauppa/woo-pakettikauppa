<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Admin') ) {
  /**
   * Admin Class
   *
   * @class Admin
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Admin {

    /**
     * @var Shipment
     */
    private $shipment = null;

    /**
     * @var Core
     */
    public $core = null;
    private $errors = array();

    public function __construct( Core $plugin ) {
      // $this->id = self::$module_config['admin']; // Doesn't do anything
      $this->core = $plugin;
    }

    public function load() {
      add_action('current_screen', array( $this, 'maybe_show_notices' ));
      add_filter('plugin_action_links_' . $this->core->basename, array( $this, 'add_settings_link' ));
      add_filter('plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2);
      add_filter('bulk_actions-edit-shop_order', array( $this, 'register_multi_create_orders' ));
      add_action('woocommerce_admin_order_actions_end', array( $this, 'register_quick_create_order' ), 10, 2); //to add print option at the end of each orders in orders page
      add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ));
      add_action('add_meta_boxes', array( $this, 'register_meta_boxes' ));
      add_action('admin_post_show_pakettikauppa', array( $this, 'show' ), 10);
      add_action('admin_post_quick_create_label', array( $this, 'create_multiple_shipments' ), 10);
      add_action('woocommerce_email_order_meta', array( $this, 'attach_tracking_to_email' ), 10, 4);
      add_action('woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_pickup_point_in_admin_order_meta' ), 10, 1);
      add_action('handle_bulk_actions-edit-shop_order', array( $this, 'create_multiple_shipments' )); // admin_action_{action name}
      add_action(str_replace('wc_', '', $this->core->prefix) . '_create_shipments', array( $this, 'hook_create_shipments' ), 10, 2);
      add_action(str_replace('wc_', '', $this->core->prefix) . '_fetch_shipping_labels', array( $this, 'hook_fetch_shipping_labels' ), 10, 2);
      add_action(str_replace('wc_', '', $this->core->prefix) . '_fetch_tracking_code', array( $this, 'hook_fetch_tracking_code' ), 10, 2);
      add_action('woocommerce_product_options_shipping', array( $this, 'add_custom_product_fields' ));
      add_action('woocommerce_process_product_meta', array( $this, 'save_custom_product_fields' ));
      add_action('wp_ajax_pakettikauppa_meta_box', array( $this, 'ajax_meta_box' ));
      add_action('woocommerce_order_status_changed', array( $this, 'create_shipment_for_order_automatically' ));

      $this->shipment = $this->core->shipment;
    }

    public function maybe_show_notices( $current_screen ) {
      // Don't show the setup notice in every screen because that would be excessive.
      $show_notice_in_screens = array( 'plugins', 'dashboard' );

      // Always show the setup notice in plugin settings page
      $tab = isset($_GET['tab']) ? filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS) : false;
      $section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS) : false;
      $is_in_wc_settings = $current_screen->id === 'woocommerce_page_wc-settings' && $tab === 'shipping' && $section === str_replace('wc_', '', $this->core->prefix) . '_shipping_method';

      if ( in_array($current_screen->id, $show_notice_in_screens, true) ) {
        // Determine if this is a new install by checking if the plugin settings
        // have been saved even once. There's a longstanding bug that causes the plugin to save it's options pretty much immediately after activating,
        // as the show_pakettikauppa_shipping_method option is set to `no` by default. There are more than one saved setting if the user has ACTUALLY saved the settings...
        $settings = $this->shipment->get_settings();

        if ( empty($settings) || count($settings) < 2 ) {
          add_action('admin_notices', array( $this, 'new_install_notice_content' ));
        }
      } elseif ( $is_in_wc_settings ) {
        add_action('admin_notices', array( $this, 'settings_page_setup_notice' ));
      }
    }

    public function new_install_notice_content() {
      ?>
      <div class="notice notice-info pakettikauppa-notice pakettikauppa-notice--setup">
        <div class="pakettikauppa-notice__logo">
          <img src="<?php echo $this->core->dir_url; ?>assets/img/pakettikauppa-logo-black.png" alt="<?php echo $this->core->text->shipping_method_name(); ?>">
        </div>

        <div class="pakettikauppa-notice__content">
          <p>
            <?php esc_html_e('Thank you for installing WooCommerce Pakettikauppa! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'); ?>

            <br />
            <br />

            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $this->core->setup_page)); ?>">
              <?php echo $this->core->text->setup_button_text(); ?>
            </a>
          </p>
        </div>
      </div>
      <?php
    }

    public function settings_page_setup_notice() {
      ?>
      <div class="notice notice-info pakettikauppa-notice pakettikauppa-notice--setup">
        <div class="pakettikauppa-notice__logo">
          <img src="<?php echo $this->core->dir_url; ?>assets/img/pakettikauppa-logo-black.png" alt="<?php echo $this->core->text->shipping_method_name(); ?>">
        </div>

        <div class="pakettikauppa-notice__content">
          <p>
            <?php esc_html_e('Thank you for installing WooCommerce Pakettikauppa! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'); ?>

            <br />
            <br />

            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $this->core->setup_page)); ?>">
              <?php echo $this->core->text->setup_button_text(); ?>
            </a>
          </p>
        </div>
      </div>
      <?php
    }


    public function create_shipment_for_order_automatically( $order_id ) {
      $order = new \WC_Order($order_id);

      if ( $this->shipment->can_create_shipment_automatically($order) ) {
        $this->shipment->create_shipment($order);
      }
    }

    public function ajax_meta_box() {
      check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security');

      $error_count = count($this->get_errors());

      if ( ! is_numeric($_POST['post_id']) ) {
        wp_die('', '', 501);
      }
      $this->save_ajax_metabox($_POST['post_id']);

      if ( count($this->get_errors()) !== $error_count ) {
        wp_die('', '', 501);
      }

      $this->meta_box(get_post($_POST['post_id']));
      wp_die();
    }

    public function save_custom_product_fields( $post_id ) {
      if ( ! is_numeric($_POST['post_id']) ) {
        return;
      }
      $custom_fields = array( str_replace('wc_', '', $this->core->prefix) . '_tariff_codes', str_replace('wc_', '', $this->core->prefix) . '_country_of_origin' );

      if ( ! (isset($_POST['woocommerce_meta_nonce']) && wp_verify_nonce(sanitize_key($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data')) ) {
        return false;
      }

      foreach ( $custom_fields as $custom_field ) {
        $value = $_POST[ $custom_field ];
        if ( ! empty($value) ) {
          update_post_meta($post_id, $custom_field, strtoupper(esc_attr($value)));
        } else {
          delete_post_meta($post_id, $custom_field);
        }
      }
    }

    public function add_custom_product_fields() {
      $args = array(
        'id' => str_replace('wc_', '', $this->core->prefix) . '_tariff_codes',
        'label' => __('HS tariff number', 'woo-pakettikauppa'),
        'desc_tip' => true,
        'description' => __('The HS tariff number must be based on the Harmonized Commodity Description and Coding System developed by the World Customs Organization.', 'woo-pakettikauppa'),
      );
      woocommerce_wp_text_input($args);

      $args = array(
        'id' => str_replace('wc_', '', $this->core->prefix) . '_country_of_origin',
        'label' => __('Country of origin', 'woo-pakettikauppa'),
        'desc_tip' => true,
        'description' => __('"Country of origin" means the country where the goods originated, e.g. were produced/manufactured or assembled.', 'woo-pakettikauppa'),
        'custom_attributes' => array(
          'maxlength' => '2',
        ),
      );
      woocommerce_wp_text_input($args);
    }

    /**
     * action -hook to fetch tracking code of the order.
     *
     * Call for example:
     *
     * $tracking_code='';
     * $args = array( $order_id, &$tracking_code );
     * do_action_ref_array(str_replace("wc_", "", $this->core->prefix) . '_fetch_tracking_code', $args);"
     *
     * @param $order_id
     * @param $tracking_code
     */
    public function hook_fetch_tracking_code( $order_id, &$tracking_code ) {
      $order = new \WC_Order($order_id);
      $tracking_code = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_code', true);
    }

    /**
     * action -hook to create shipments to orders.
     *
     * Call for example:
     *
     * $args = array( $order_id, $order_id2, ... );
     * do_action(str_replace("wc_", "", $this->core->prefix) . '_create_shipments', $args);"
     *
     * @param $order_ids
     */
    public function hook_create_shipments( $order_ids ) {
      $this->create_shipments($order_ids);
    }

    /**
     * action -hook to create shipments to orders.
     *
     * Call for example:
     * $pdf = '';
     * $order_ids = array (15, 16, 17);
     * $args = array( $order_ids, &$pdf );
     * do_action_ref_array(str_replace("wc_", "", $this->core->prefix) . '_create_shipments', $args);"
     *
     * @param $order_ids
     * @param $pdf
     */
    public function hook_fetch_shipping_labels( $order_ids, &$pdf ) {
      $tracking_codes = $this->create_shipments($order_ids);

      $contents = $this->fetch_shipping_labels($tracking_codes);

      $pdf = base64_decode($contents->{'response.file'});
    }

    /**
     * @param $bulk_actions
     *
     * @return mixed
     */
    public function register_multi_create_orders( $bulk_actions ) {
      $bulk_actions[str_replace('wc_', '', $this->core->prefix) . '_create_multiple_shipping_labels'] = __('Create and fetch shipping labels', 'woo-pakettikauppa');

      return $bulk_actions;
    }

    /**
     * @param $actions
     * @param WC_Order $order
     *
     * @return array
     */
    public function register_quick_create_order( $order ) {
      $shipping_methods = $order->get_shipping_methods();

      if ( ! empty($shipping_methods) ) {
        $shipping_method = array_pop($shipping_methods);

        if ( ! empty($shipping_method) ) {
          $method_id = $shipping_method->get_method_id();

          if ( $method_id === 'local_pickup' ) {
            return;
          }
        }
      }

      $document_url = wp_nonce_url(admin_url('admin-post.php?post[]=' . $order->get_id() . '&action=quick_create_label'), 'bulk-posts');

      $class = str_replace('wc_', '', $this->core->prefix) . '_create_shipping_label';

      $actions = array(
        'name'   => __('Create shipping label', 'woo-pakettikauppa'),
        'action' => str_replace('wc_', '', $this->core->prefix) . '_create_shipping_label',
        'url'    => $document_url,
      );

      printf('<a class="button wc-action-button wc-action-button-%s %s" href="%s" title="%s" target="_blank">%s</a>', $class, $class, $actions['url'], $actions['name'], $actions['name']);
    }

    /**
     * This function exits on success, returns on error
     *
     * @throws Exception
     */
    public function create_multiple_shipments() {
      if ( ! isset($_REQUEST['post']) ) {
        return;
      }

      if ( ! is_array($_REQUEST['post']) ) {
        return;
      }

      $action = null;

      if ( isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ) {
        $action = $_REQUEST['action'];
      } elseif ( isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1' ) {
        $action = $_REQUEST['action2'];
      }

      if ( $action === null ) {
        return;
      }

      if ( ! ($action === str_replace('wc_', '', $this->core->prefix) . '_create_multiple_shipping_labels' || $action === 'quick_create_label') ) {
        return;
      }

      if ( ! wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'bulk-posts') ) {
        return;
      }

      $tracking_codes = $this->create_shipments(sanitizize_key($_REQUEST['post']));

      $contents = $this->fetch_shipping_labels($tracking_codes);

      if ( $contents->{'response.file'}->__toString() === '' ) {
        esc_attr_e('Cannot find shipments with given shipment numbers.', 'woo-pakettikauppa');

        return;
      }

      $this->output_shipping_label($contents, 'multiple-shipping-labels');
    }

    private function fetch_shipping_labels( $tracking_codes ) {
      return $this->shipment->fetch_shipping_labels($tracking_codes);
    }

    private function create_shipments( $order_ids ) {
      $tracking_codes = array();

      foreach ( $order_ids as $order_id ) {
        $order = new \WC_Order($order_id);
        $tracking_code = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_code', true);

        if ( empty($tracking_code) ) {
          $tracking_code = $this->shipment->create_shipment($order);
        }

        if ( $tracking_code !== null ) {
          $tracking_codes[] = $tracking_code;
        }
      }

      return $tracking_codes;
    }


    public function wc_pakettikauppa_updated() {
      $shipping_method_found = false;
      $shipping_zones = \WC_Shipping_Zones::get_zones();

      foreach ( $shipping_zones as $shipping_zone ) {
        foreach ( $shipping_zone['shipping_methods'] as $shipping_object ) {
          if ( get_class($shipping_object) === __NAMESPACE__ . '\Shipping_Method' ) {
            $shipping_method_found = true;
          }
        }
      }

      $settings = $this->shipment->get_settings();

      if ( ! empty($settings['pickup_points']) ) {
        $pickup_points = json_decode($settings['pickup_points'], true);

        if ( ! empty($pickup_points) ) {
          foreach ( $pickup_points as $shipping_method ) {
            foreach ( $shipping_method as $provider ) {
              if ( isset($provider['active']) && $provider['active'] === 'yes' ) {
                $shipping_method_found = true;
              }
            }
          }
        }
      }

      if ( ! $shipping_method_found ) {
        echo '<div class="updated warning">';
        echo sprintf('<p>%s</p>', __('WooCommerce Pakettikauppa has been installed/updated but no shipping methods are currently active!', 'woo-pakettikauppa'));
        echo '</div>';
      }
    }

    /**
     * Add an error with a specified error message.
     *
     * @param string $message A message containing details about the error.
     */
    public function add_error( $message ) {
      $this->shipment->add_error($message);
    }

    /**
     * Return all errors that have been added via add_error().
     *
     * @return array Errors
     */
    public function get_errors() {
      return $this->shipment->get_errors();
    }

    /**
     * Clear all existing errors that have been added via add_error().
     */
    public function clear_errors() {
      $this->shipment->clear_errors();
    }

    /**
     * Add an admin error notice to wp-admin.
     */
    public function add_error_notice( $message ) {
      if ( ! empty($message) ) {
        $class = 'notice notice-error';
        /* translators: %s: Error message */
        $print_error = wp_sprintf(__('An error occurred: %s', 'woo-pakettikauppa'), $message);
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
      }
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param  mixed $links Plugin Row Meta
     * @param  mixed $file Plugin Base file
     *
     * @return  array
     */
    public function plugin_row_meta( $links, $file ) {
      if ( $file === $this->core->basename ) {
        $row_meta = array(
          'service' => sprintf(
            '<a href="%1$s" aria-label="%2$s">%3$s</a>',
            esc_url('https://www.pakettikauppa.fi'),
            esc_attr__('Visit Pakettikauppa', 'woo-pakettikauppa'),
            esc_html__('Show site Pakettikauppa', 'woo-pakettikauppa')
          ),
        );

        return array_merge($links, $row_meta);
      }

      return (array) $links;
    }

    /**
     * Register meta boxes for WooCommerce order metapage.
     */
    public function register_meta_boxes() {
      foreach ( wc_get_order_types('order-meta-boxes') as $type ) {
        add_meta_box(
          'woo-pakettikauppa', // Using a variable WILL BREAK JS
          // $this->core->prefix,
          esc_attr($this->core->text->shipping_method_name()),
          array(
            $this,
            'meta_box',
          ),
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
      wp_enqueue_style($this->core->prefix . '_admin', $this->core->dir_url . 'assets/css/admin.css', array(), $this->core->version);
      wp_enqueue_script($this->core->prefix . '_admin_js', $this->core->dir_url . 'assets/js/admin.js', array( 'jquery' ), $this->core->version, true);
    }

    /**
     * Add settings link to the Pakettikauppa metabox on the plugins page when used with
     * the WordPress hook plugin_action_links_woo-pakettikauppa.
     *
     * @param array $links Already existing links on the plugin metabox
     *
     * @return array The plugin settings page link appended to the already existing links
     */
    public function add_settings_link( $links ) {
      $url  = admin_url('admin.php?page=wc-settings&tab=shipping&section=' . $this->core->shippingmethod);
      $link = sprintf('<a href="%1$s">%2$s</a>', $url, esc_attr__('Settings'));

      return array_merge(array( $link ), $links);
    }

    /**
     * Show the selected pickup point in admin order meta. Use together with the hook
     * woocommerce_admin_order_data_after_shipping_address.
     *
     * @param WC_Order $order The order that is currently being viewed in wp-admin
     */
    public function show_pickup_point_in_admin_order_meta( $order ) {
      echo sprintf('<p class="form-field"><strong>%s:</strong><br>', esc_attr__('Requested pickup point', 'woo-pakettikauppa'));
      if ( $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point') ) {
        echo esc_attr($order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point'));
      } else {
        echo esc_attr__('None');
      }
      echo '</p>';
    }

    /**
     * Meta box for managing shipments.
     *
     * @param $post
     */
    public function meta_box( $post ) {
      $order = wc_get_order($post->ID);

      if ( $order === null ) {
        return;
      }

      if ( ! Shipment::validate_order_shipping_receiver($order) ) {
        esc_attr_e('Please add shipping info to the order to manage Pakettikauppa shipments.', 'woo-pakettikauppa');

        return;
      }

      // The tracking code will only be available if the shipment label has been generated
      $tracking_code = get_post_meta($post->ID, '_' . $this->core->prefix . '_tracking_code', true);
      $label_code = get_post_meta($post->ID, '_' . $this->core->prefix . '_label_code', true);
      $tracking_url = get_post_meta($post->ID, '_' . $this->core->prefix . '_tracking_url', true);

      if ( empty($tracking_url) ) {
        $tracking_url = Shipment::tracking_url($tracking_code);
      }

      $service_id = get_post_meta($post->ID, '_' . $this->core->prefix . '_custom_service_id', true);
      $default_service_id = $this->shipment->get_service_id_from_order($order, false);

      if ( empty($service_id) ) {
        $service_id = $default_service_id;
      }

      $pickup_point_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');
      $status          = get_post_meta($post->ID, '_' . $this->core->prefix . '_shipment_status', true);

      $document_url = admin_url('admin-post.php?post=' . $post->ID . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);

      foreach ( $this->shipment->get_additional_services_from_order($order) as $_additional_service ) {
        $additional_services[] = key($_additional_service);
      }

      $return_shipments = get_post_meta($post->ID, '_' . $this->core->prefix . '_return_shipment');

      $all_shipment_services = $this->shipment->services();

      $all_additional_services = $this->shipment->get_additional_services();
      $all_shipment_additional_services = array();
      if ( ! empty($all_additional_services) ) {
        $all_shipment_additional_services = $all_additional_services[$service_id];
      }

      if ( ! empty($all_shipment_additional_services) ) {
        foreach ( $all_shipment_additional_services as $additional_service ) {
          $additional_service_names[(string) $additional_service->service_code] = $additional_service->name;
        }
      }
      ?>
      <div>
        <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-meta-box'); ?>" id="pakettikauppa_metabox_nonce" />
        <?php if ( ! empty($tracking_code) ) : ?>
          <p>
            <strong>
              <?php echo esc_attr($this->shipment->service_title($service_id)); ?><br />
              <?php echo esc_attr($tracking_code); ?><br />
              <?php echo esc_attr(Shipment::get_status_text($status)); ?><br />
              <?php if ( ! empty($label_code) ) : ?>
                <?php echo __('Label code', 'woo-pakettikauppa'); ?>: <?php echo $label_code; ?><br />
              <?php endif; ?>
            </strong>
            <br>
            <a href="<?php echo esc_url($document_url); ?>" target="_blank" class="download"><?php esc_attr_e('Print document', 'woo-pakettikauppa'); ?></a>&nbsp;-&nbsp;
            <?php if ( ! empty($tracking_url) ) : ?>
              <a href="<?php echo esc_url($tracking_url); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a>
            <?php endif; ?>
          </p>
          <p class="pakettikauppa-shipment">
            <button type="button" value="get_status" name="wc_pakettikauppa[get_status]" class="button pakettikauppa_meta_box" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Update Status', 'woo-pakettikauppa'); ?></button>
            <button type="button" value="create_return_label" name="wc_pakettikauppa[create_return_label]" onclick="pakettikauppa_meta_box_submit(this);" class="button pakettikauppa_meta_box"><?php echo __('Create Return Label', 'woo-pakettikauppa'); ?></button>
            <button type="button" value="all" name="wc_pakettikauppa[delete_shipping_label]" onclick="pakettikauppa_meta_box_submit(this);" class="button pakettikauppa_meta_box woo-pakettikauppa-delete-button">
            <?php if ( empty($return_shipments) ) : ?>
              <?php echo __('Delete Shipping Label', 'woo-pakettikauppa'); ?>
            <?php else : ?>
              <?php echo __('Delete Shipping Label and return labels', 'woo-pakettikauppa'); ?>
            <?php endif; ?>
            </button>
          </p>
          <?php if ( ! empty($return_shipments) ) : ?>
            <?php foreach ( $return_shipments as $return_label ) : ?>
            <p>
              <strong>
                <?php echo esc_attr($this->shipment->service_title($return_label['service_id'])); ?><br />
                <?php echo esc_attr($return_label['tracking_code']); ?><br />
                <?php echo __('Label code', 'woo-pakettikauppa'); ?>: <?php echo $return_label['label_code']; ?><br />
              </strong>
            </p>
            <p>
              <a href="<?php echo esc_url($return_label['document_url']); ?>" target="_blank" class="download"><?php esc_attr_e('Print document', 'woo-pakettikauppa'); ?></a>&nbsp;-&nbsp;
              <a href="<?php echo esc_url($return_label['tracking_url']); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a>
            </p>
                <p class="pakettikauppa-shipment">
              <button type="button" value="<?php echo esc_attr($return_label['tracking_code']); ?>" name="wc_pakettikauppa[delete_shipping_label]" onclick="pakettikauppa_meta_box_submit(this);" class="button pakettikauppa_meta_box woo-pakettikauppa-delete-button"><?php echo __('Delete Shipping Label', 'woo-pakettikauppa'); ?></button>
            </p>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else : ?>
          <div class="pakettikauppa-services">
            <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_shipping_method">
              <h4><?php echo esc_html($this->shipment->service_title($default_service_id)); ?></h4>
              <?php if ( ! empty($additional_services) ) : ?>
                <h4><?php echo esc_attr__('Additional services', 'woo-pakettikauppa'); ?>:</h4>
                <ol style="list-style: circle;">
                  <?php foreach ( $additional_services as $i => $additional_service ) : ?>
                    <?php if ( ! in_array($additional_service, array( '3102' ), true) ) : ?>
                      <li>
                        <?php if ( isset($additional_service_names[ $additional_service ]) ) : ?>
                          <?php echo $additional_service_names[ $additional_service ]; ?>
                        <?php else : ?>
                          <?php echo $additional_service; ?>
                        <?php endif; ?>
                      </li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if ( in_array('3102', $additional_services, true) ) : ?>
                    <li>
                      <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                      <input type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 3em;" min="1" step="1" max="15">
                    </li>
                  <?php endif; ?>
                </ol>
              <?php endif; ?>

              <?php if ( $pickup_point_id ) : ?>
                <h4>
                  <?php echo esc_html__('Requested pickup point', 'woo-pakettikauppa'); ?>
                </h4>
                <p>
                  <?php echo esc_html($order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point')); ?>
                </p>
              <?php endif; ?>
            </fieldset>

            <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_custom_shipping_method" style="display: none;">
              <select name="wc_pakettikauppa_service_id" id="pakettikauppa-service" class="pakettikauppa_metabox_values" onchange="pakettikauppa_change_shipping_method();">
                <option value="__NULL__"><?php esc_html_e('No shipping', 'woo-pakettikauppa'); ?></option>
                <?php foreach ( $all_shipment_services as $_service_code => $_service_title ) : ?>
                  <option
                    <?php if ( strval($_service_code) === $service_id ) : ?>
                          selected="selected"
                    <?php endif; ?>
                          value="<?php echo esc_attr($_service_code); ?>">
                    <?php echo esc_html($_service_title); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <?php foreach ( $all_additional_services as $method_code => $_additional_services ) : ?>
                <ol style="list-style: circle; display: none;" class="pk-admin-additional-services" id="pk-admin-additional-services-<?php echo $method_code; ?>">
                  <?php $show_3102 = false; ?>
                  <?php foreach ( $_additional_services as $additional_service ) : ?>
                    <?php if ( empty($additional_service->specifiers) || $additional_service->service_code === '3101' ) : ?>
                      <li>
                        <input
                                type="checkbox"
                                class="pakettikauppa_metabox_array_values"
                                name="wc_pakettikauppa_additional_services"
                                value="<?php echo $additional_service->service_code; ?>"> <?php echo $additional_service->name; ?>
                      </li>
                    <?php elseif ( $additional_service->service_code === '3102' ) : ?>
                      <?php $show_3102 = true; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if ( $show_3102 ) : ?>
                    <li>
                      <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                      <input class="pakettikauppa_metabox_values" type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 3em;" min="1" step="1" max="15">
                    </li>
                  <?php endif; ?>
                </ol>
              <?php endforeach; ?>
            </fieldset>
          </div>
          <p>
            <button type="button" value="create" name="wc_pakettikauppa[create]" class="button pakettikauppa_meta_box" onclick="pakettikauppa_meta_box_submit(this);">
              <?php echo __('Create', 'woo-pakettikauppa'); ?>
            </button>
            <button type="button" value="change" class="button pakettikauppa_meta_box" onclick="pakettikauppa_change_method(this);">
              <?php echo __('Change shipping...', 'woo-pakettikauppa'); ?>
            </button>
          </p>
        <?php endif; ?>
      </div>
      <?php
    }

    /**
     * Save metabox values and fetch the shipping label for the order.
     */
    public function save_ajax_metabox( $post_id ) {
      /**
       * Because this function is called every time something is saved in WooCommerce, then let's check this first
       * so it won't slow down saving other stuff too much.
       */
      if ( ! isset($_POST['wc_pakettikauppa']) ) {
        return;
      }

      if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security') ) {
        return;
      }

      if ( ! current_user_can('edit_post', $post_id) ) {
        return;
      }

      if ( wp_is_post_autosave($post_id) ) {
        return;
      }

      if ( wp_is_post_revision($post_id) ) {
        return;
      }

      $order = new \WC_Order($post_id);

      $command = key($_POST['wc_pakettikauppa']);

      $service_id = null;

      switch ( $command ) {
        case 'create':
          if ( ! empty($_REQUEST['wc_pakettikauppa_service_id']) ) {
            $service_id = sanitize_key($_REQUEST['wc_pakettikauppa_service_id']);
          }

          if ( empty($_REQUEST['custom_method']) ) {
            $additional_services = null;

            $pickup_point_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');

            if ( empty($pickup_point_id) && ! empty($_REQUEST['wc_pakettikauppa_pickup_point_id']) ) {
              $pickup_point_id = sanitize_key($_REQUEST['wc_pakettikauppa_pickup_point_id']);

              update_post_meta($order->get_id(), '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', $pickup_point_id);
            }
          } else {
            $additional_services = array();

            if ( ! empty($_REQUEST['additional_services']) ) {
              foreach ( $_REQUEST['additional_services'] as $_additional_service_code ) {
                if ( $_additional_service_code !== '3101' ) {
                  $additional_services[] = array( $_additional_service_code => null );
                } else {
                  $settings = $this->shipment->get_settings();

                  $additional_services[] = array(
                    '3101' => array(
                      'amount' => $order->get_total(),
                      'account' => $settings['cod_iban'],
                      'codbic' => $settings['cod_bic'],
                      'reference' => $this->shipment->calculate_reference($order->get_id()),
                    ),
                  );

                }
              }
            }

            if ( ! empty($_REQUEST['wc_pakettikauppa_mps_count']) ) {
              $additional_services[] = array( '3102' => array( 'count' => (int) $_REQUEST['wc_pakettikauppa_mps_count'] ) );
            }
          }

          return $this->shipment->create_shipment($order, $service_id, $additional_services);
        case 'get_status':
          $this->get_status($order);
          break;
        case 'delete_shipping_label':
          $tracking_code = esc_attr($_POST['wc_pakettikauppa'][$command]);

          $this->delete_shipping_label($order, $tracking_code);
          break;
        case 'create_return_label':
          $this->create_return_label($order);
          break;
      }
    }

    /**
     * @param WC_Order $order
     *
     * @throws Exception
     */
    private function create_return_label( \WC_Order $order ) {
      $service_id = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_custom_service_id', true);

      $service_provider = $this->shipment->service_provider($service_id);

      $additional_services = array(
        array(
          '9902' => array(),
        ),
      );
      $return_service_id = null;
      switch ( $service_provider ) {
        case 'Posti':
          $return_service_id = '2108';
          break;
        case 'DB Schenker':
          $return_service_id = '80020';
          break;
        case 'Matkahuolto':
        default:
          $order->add_order_note(__('Unable to create return label for this shipment type.', 'woo-pakettikauppa'));
          return;
      }

      $shipment = $this->shipment->create_shipment_from_order($order, $return_service_id, $additional_services);

      if ( $shipment !== null ) {
        $tracking_code = null;

        if ( isset($shipment->{'response.trackingcode'}) ) {
          $tracking_code = $shipment->{'response.trackingcode'}->__toString();
          $document_url  = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);
          $tracking_url  = (string) $shipment->{'response.trackingcode'}['tracking_url'];
          $label_code    = (string) $shipment->{'response.trackingcode'}['labelcode'];

          add_post_meta(
            $order->get_id(),
            '_' . $this->core->prefix . '_return_shipment',
            array(
              'service_id' => $return_service_id,
              'tracking_code' => $tracking_code,
              'document_url' => $document_url,
              'tracking_url' => $tracking_url,
              'label_code' => $label_code,
            )
          );
        }
      }
    }

    /**
     * @param WC_Order $order
     */
    private function delete_shipping_label( \WC_Order $order, $tracking_code ) {
      try {
        if ( $tracking_code === 'all' ) {
          // delete all return shipments first
          delete_post_meta($order->get_id(), '_' . $this->core->prefix . '_return_shipment');

          // Delete old tracking code
          update_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_code', '');

          /* translators: %%s: tracking code */
          $order->add_order_note(sprintf(esc_attr__('Successfully deleted Pakettikauppa shipping label %s.', 'woo-pakettikauppa'), $tracking_code));
        } else {
          $return_shipments = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_return_shipment');

          foreach ( $return_shipments as $return_shipment ) {
            if ( $return_shipment['tracking_code'] === $tracking_code ) {
              delete_post_meta($order->get_id(), '_' . $this->core->prefix . '_return_shipment', $return_shipment);
              /* translators: %%s: tracking code */
              $order->add_order_note(sprintf(esc_attr__('Successfully deleted Pakettikauppa shipping label %s.', 'woo-pakettikauppa'), $tracking_code));
              return;
            }
          }
        }
      } catch ( \Exception $e ) {
        $this->add_error($e->getMessage());
        add_action(
          'admin_notices',
          function() use ( $e ) {
            /* translators: %s: Error message */
            $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
          }
        );

        $order->add_order_note(
          sprintf(
            /* translators: %s: Error message */
            esc_attr__('Deleting Pakettikauppa shipment failed! Errors: %s', 'woo-pakettikauppa'),
            $e->getMessage()
          )
        );
      }
    }

    /**
     * @param WC_Order $order
     */
    private function get_status( \WC_Order $order ) {
      try {
        $status_code = $this->shipment->get_shipment_status($order->get_id());
        update_post_meta($order->get_id(), '_' . $this->core->prefix . '_shipment_status', $status_code);
      } catch ( \Exception $e ) {
        $this->add_error($e->getMessage());
        add_action(
          'admin_notices',
          function() use ( $e ) {
            /* translators: %s: Error message */
            $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
          }
        );
      }
    }

    /**
     * Output shipment label as PDF in browser.
     */
    public function show() {
      // Find shipment ID either from GET parameters or from the order
      // data.
      if ( empty( $_REQUEST['tracking_code'] ) ) { // @codingStandardsIgnoreLine
        esc_attr_e('Shipment tracking code is not defined.', 'woo-pakettikauppa');

        return;
      }

      $tracking_code = esc_attr($_REQUEST['tracking_code']); // @codingStandardsIgnoreLine

      $contents = $this->shipment->fetch_shipping_label($tracking_code);

      if ( $contents->{'response.file'}->__toString() === '' ) {
        esc_attr_e('Cannot find shipment with given shipment number.', 'woo-pakettikauppa');

        return;
      }

      $this->output_shipping_label($contents, $tracking_code);
    }

    /**
     * Fetches PDF from the XML and outputs it. Ends execution.
     *
     * @param $contents
     * @param $filename
     */
    private function output_shipping_label( $contents, $filename ) {
      $settings = $this->shipment->get_settings();

      if ( $settings['download_type_of_labels'] === 'download' ) {
        header('Content-Type: application/octet-stream');
        $content_disposition = 'attachment';
      } else {
        header('Content-Type: application/pdf');
        $content_disposition = 'inline';
      }

      $pdf = base64_decode( $contents->{'response.file'} ); // @codingStandardsIgnoreLine

      header('Content-Description: File Transfer');
      header('Content-Transfer-Encoding: binary');
      header("Content-Disposition: $content_disposition;filename=\"{$filename}.pdf\"");
      header('Content-Length: ' . strlen($pdf));

      echo $pdf;

      exit();
    }

    /**
     * Attach tracking URL to email.
     *
     * @param $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param null $email
     */
    public function attach_tracking_to_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
      $settings = $this->shipment->get_settings();
      $add_to_email = $settings['add_tracking_to_email'];
      $add_pickup_point_to_email = $settings['add_pickup_point_to_email'];

      if ( ! ($add_to_email === 'yes' && isset($email->id) && $email->id === 'customer_completed_order') ) {
        return;
      }

      $tracking_code = get_post_meta($order->get_ID(), '_' . $this->core->prefix . '_tracking_code', true);
      $tracking_url  = Shipment::tracking_url($tracking_code);

      if ( empty($tracking_code) || empty($tracking_url) ) {
        return;
      }

      if ( $plain_text ) {
        /* translators: %s: Shipment tracking URL */
        if ( ! empty($order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point')) && 'yes' === $add_pickup_point_to_email ) {
          echo sprintf("%s: %s\n\n", __('Requested pickup point', 'woo-pakettikauppa'), $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point'));
        }

        /* translators: Shipment tracking url */
        echo sprintf(__("You can track your order at %1\$s.\n\n", 'woo-pakettikauppa'), esc_url($tracking_url));
      } else {
        if ( ! empty($order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point')) && 'yes' === $add_pickup_point_to_email ) {
          echo sprintf('<h2>%s</h2>', esc_attr__('Requested pickup point', 'woo-pakettikauppa'));
          echo sprintf('<p>%s</p>', esc_attr($order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point')));
        }

        echo '<h2>' . esc_attr__('Tracking', 'woo-pakettikauppa') . '</h2>';
        /* translators: 1: Shipment tracking URL 2: Shipment tracking code */
        echo '<p>' . sprintf(__('You can <a href="%1$s">track your order</a> with tracking code %2$s.', 'woo-pakettikauppa'), esc_url($tracking_url), esc_attr($tracking_code)) . '</p>';
      }
    }
  }
}
