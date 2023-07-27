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
      add_filter('plugin_row_meta', array( $this, 'plugin_row_meta_wrapper' ), 10, 2);
      add_filter('bulk_actions-edit-shop_order', array( $this, 'register_multi_create_orders' ));
      add_action('woocommerce_admin_order_actions_end', array( $this, 'register_quick_create_order' ), 10, 2); //to add print option at the end of each orders in orders page
      add_action('admin_notices', array( $this, 'show_admin_notices' ));
      add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ));
      add_action('add_meta_boxes', array( $this, 'register_meta_boxes' ));
      add_action('admin_post_show_pakettikauppa', array( $this, 'show' ), 10);
      add_action('admin_post_quick_create_label', array( $this, 'create_multiple_shipments' ), 10, 2);
      add_action('woocommerce_email_order_meta', array( $this, 'attach_tracking_to_email' ), 10, 4);
      add_action('woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_pickup_point_in_admin_order_meta' ), 10, 1);
      add_action('save_post', array( $this, 'save_admin_order_meta' ));
      add_action('handle_bulk_actions-edit-shop_order', array( $this, 'create_multiple_shipments' ), 10, 2); // admin_action_{action name}
      add_action($this->core->params_prefix . 'create_shipments', array( $this, 'hook_create_shipments' ), 10, 2);
      add_action($this->core->params_prefix . 'fetch_shipping_labels', array( $this, 'hook_fetch_shipping_labels' ), 10, 2);
      add_action($this->core->params_prefix . 'fetch_tracking_code', array( $this, 'hook_fetch_tracking_code' ), 10, 2);
      add_action('wp_ajax_pakettikauppa_meta_box', array( $this, 'ajax_meta_box' ));
      add_action('woocommerce_order_status_changed', array( $this, 'create_shipment_for_order_automatically' ));
      add_action('wp_ajax_get_pickup_point_by_custom_address', array( $this, 'get_pickup_point_by_custom_address' ));
      add_action('wp_ajax_update_estimated_shipping_price', array( $this, 'update_estimated_shipping_price' ));
      add_action('wp_ajax_check_api', array( $this, 'ajax_check_credentials' ));
      add_action('wp_ajax_pakettikauppa_meta_box_bulk', array( $this, 'ajax_meta_box_bulk' ));
      add_action('admin_menu', array( $this, 'add_submenu' ));
      add_action('wp_ajax_pakettikauppa_get_pickup_points', array( $this, 'ajax_get_pickup_points' ));

      $this->shipment = $this->core->shipment;
    }

    public function add_submenu() {
      $submenu = add_submenu_page('', 'Create custom shipments', 'Custom shipments', 'manage_woocommerce', 'bulk-create-custom-shipment', array( $this, 'create_custom_shipment_table' ));
      add_action('load-' . $submenu, array( $this, 'load_admin_custom_shipment_js' ));
    }

    public function load_admin_custom_shipment_js() {
      add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_js' ));
    }

    public function enqueue_admin_js() {
      wp_enqueue_script($this->core->prefix . '_admin_custom_shipment_js', $this->core->dir_url . 'assets/js/admin_custom_shipment.js', array( 'jquery' ), $this->core->version, true);
    }

    public function create_custom_shipment_table() {
      echo '<div class="wrap">';
      echo $this->get_custom_shipment_table();
      echo '</div>';
    }

    public function add_admin_notice( $msg, $type ) {
      if ( ! session_id() ) {
        session_start();
      }
      if ( ! isset($_SESSION['pakettikauppa_notices']) ) {
        $_SESSION['pakettikauppa_notices'] = array();
      }
      $_SESSION['pakettikauppa_notices'][] = array(
        'msg' => $msg,
        'type' => $type,
      );
    }

    public function show_admin_notices() {
      if ( ! session_id() ) {
        session_start();
      }
      if ( $_SESSION !== null && array_key_exists('pakettikauppa_notices', $_SESSION) ) {
        foreach ( $_SESSION['pakettikauppa_notices'] as $notice ) {
          if ( $notice['type'] === 'error' ) {
            $this->add_error_notice($notice['msg'], false);
          }
          if ( $notice['type'] === 'success' ) {
            $this->add_success_notice($notice['msg'], false);
          }
        }
        unset($_SESSION['pakettikauppa_notices']);
      }
    }

    public function maybe_show_notices( $current_screen ) {
      // Don't show the setup notice in every screen because that would be excessive.
      $show_notice_in_screens = array( 'plugins', 'dashboard' );

      // Always show the setup notice in plugin settings page
      $tab = isset($_GET['tab']) ? filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS) : false;
      $section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS) : false;
      $is_in_wc_settings = $current_screen->id === 'woocommerce_page_wc-settings' && $tab === 'shipping' && $section === $this->core->params_prefix . 'shipping_method';

      if ( in_array($current_screen->id, $show_notice_in_screens, true) ) {
        // Determine if this is a new install by checking if the plugin settings
        // have been saved even once. There's a longstanding bug that causes the plugin to save it's options pretty much immediately after activating,
        // as the show_pakettikauppa_shipping_method option is set to `no` by default. There are more than one saved setting if the user has ACTUALLY saved the settings...
        $settings = $this->shipment->get_settings();

        if ( empty($settings) || count($settings) < 2 ) {
          add_action('admin_notices', array( $this, 'new_install_notice_content' ));
        }
      } elseif ( $is_in_wc_settings ) {
        if ( get_option($this->core->prefix . '_wizard_done') !== '1' ) {
          add_action('admin_notices', array( $this, 'settings_page_setup_notice' ));
        }
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
            <?php
            /* translators: %s: Vendor full name */
            printf(esc_html__('Thank you for installing %s! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'), $this->core->vendor_fullname);
            ?>

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
            <?php
            /* translators: %s: Vendor full name */
            printf(esc_html__('Thank you for installing %s! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'), $this->core->vendor_fullname);
            ?>

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
        $this->add_admin_notice(__('Received Post ID is not a number', 'woo-pakettikauppa'), 'error');
        wp_die('', '', 501);
      }
      $this->save_ajax_metabox((int) $_POST['post_id']);

      if ( count($this->get_errors()) !== $error_count ) {
        foreach ( $this->get_errors() as $error ) {
          $this->add_admin_notice($error, 'error');
        }
        wp_die('', '', 501);
      }

      $this->meta_box(get_post((int) $_POST['post_id']));
      wp_die();
    }

    public function ajax_meta_box_bulk() {
      check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security');

      $error_count = count($this->get_errors());

      if ( ! is_numeric($_POST['post_id']) ) {
        $this->add_admin_notice(__('Received Post ID is not a number', 'woo-pakettikauppa'), 'error');
        wp_die('', '', 501);
      }
      $this->save_ajax_metabox((int) $_POST['post_id']);

      if ( count($this->get_errors()) !== $error_count ) {
        foreach ( $this->get_errors() as $error ) {
          $this->add_admin_notice($error, 'error');
        }
        wp_die('', '', 501);
      }
      $this->get_current_shipment(wc_get_order((int) $_POST['post_id']));
      wp_die();
    }

    /**
     * action -hook to fetch tracking codes of the order as array.
     *
     * Call for example:
     *
     * $tracking_code=array();
     * $args = array( $order_id, &$tracking_code );
     * do_action_ref_array($this->core->params_prefix . 'fetch_tracking_code', $args);"
     *
     * @param $order_id
     * @param $tracking_code
     */
    public function hook_fetch_tracking_codes( $order_id, &$tracking_codes ) {
      $order = new \WC_Order($order_id);
      $tracking_codes = $this->shipment->get_labels($order->getid());
    }

    /**
     * action -hook to create shipments to orders.
     *
     * Call for example:
     *
     * $args = array( $order_id, $order_id2, ... );
     * do_action($this->core->params_prefix . 'create_shipments', $args);"
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
     * do_action_ref_array($this->core->params_prefix . 'create_shipments', $args);"
     *
     * @param $order_ids
     * @param $pdf
     */
    public function hook_fetch_shipping_labels( $order_ids, &$pdf ) {
      $tracking_codes = $this->create_shipments($order_ids);

      $contents = $this->fetch_shipping_labels($tracking_codes);
      if ( ! $contents ) {
        return;
      }

      $pdf = base64_decode($contents->{'response.file'});
    }

    /**
     * @param $bulk_actions
     *
     * @return mixed
     */
    public function register_multi_create_orders( $bulk_actions ) {
      global $wp_version;

      if ( version_compare($wp_version, '5.6.0', '>=') ) {
        $bulk_actions[$this->core->vendor_name] = array(
          $this->core->params_prefix . 'create_multiple_shipping_labels' => __('Create and fetch shipping labels', 'woo-pakettikauppa'),
          $this->core->params_prefix . 'create_custom_shipments' => __('Create custom shipments', 'woo-pakettikauppa'),
        );
      } else {
        $bulk_actions[$this->core->params_prefix . 'create_multiple_shipping_labels'] = $this->core->vendor_name . ': ' . __('Create and fetch shipping labels', 'woo-pakettikauppa');
        $bulk_actions[$this->core->params_prefix . 'create_custom_shipments'] = $this->core->vendor_name . ': ' . __('Create custom shipments', 'woo-pakettikauppa');
      }

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

      $class = $this->core->params_prefix . 'create_shipping_label';

      $actions = array(
        'name'   => __('Create shipping label', 'woo-pakettikauppa'),
        'action' => $this->core->params_prefix . 'create_shipping_label',
        'url'    => $document_url,
      );

      printf('<a class="button wc-action-button wc-action-button-%s %s" href="%s" title="%s" target="_blank">%s</a>', $class, $class, $actions['url'], $actions['name'], $actions['name']);
    }

    /**
     * This function exits on success, returns on error
     *
     * @throws Exception
     */
    public function create_multiple_shipments( $redirect_to ) {
      if ( ! isset($_REQUEST['post']) ) {
        return;
      }

      if ( ! is_array($_REQUEST['post']) ) {
        return;
      }

      $action = null;

      if ( isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ) {
        $action = sanitize_key($_REQUEST['action']);
      } elseif ( isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1' ) {
        $action = sanitize_key($_REQUEST['action2']);
      }

      if ( $action === null ) {
        return;
      }

      $order_ids = array();

      // instead of array_map we use foreach because array_map is not allowed by sniff rules
      foreach ( $_REQUEST['post'] as $order_id ) {
          $order_ids[] = sanitize_text_field($order_id);
      }

      if ( $action === $this->core->params_prefix . 'create_custom_shipments' ) {
        return add_query_arg('id', $order_ids, menu_page_url('bulk-create-custom-shipment'));
      }

      if ( ! ($action === $this->core->params_prefix . 'create_multiple_shipping_labels' || $action === 'quick_create_label') ) {
        return;
      }

      if ( ! wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'bulk-posts') ) {
        return;
      }

      $tracking_codes = $this->create_shipments($order_ids);

      $contents = $this->fetch_shipping_labels($tracking_codes);
      if ( ! $contents ) {
        return;
      }

      if ( $contents->{'response.file'}->__toString() === '' ) {
        $this->add_admin_notice(__('Cannot find shipments with given shipment numbers.', 'woo-pakettikauppa'), 'error');

        return $redirect_to;
      }

      $this->output_shipping_label($contents, 'multiple-shipping-labels');
    }

    private function fetch_shipping_labels( $tracking_codes ) {
      $shipping_labels = false;
      $settings = $this->shipment->get_settings();
      $labels_size = (isset($settings['labels_size'])) ? $settings['labels_size'] : null;

      try {
        $shipping_labels = $this->shipment->fetch_shipping_labels($tracking_codes, $labels_size);
      } catch ( \Exception $e ) {
        $this->add_admin_notice($e->getMessage(), 'error');
      }

      return $shipping_labels;
    }

    private function create_shipments( $order_ids ) {
      $tracking_codes = array();

      foreach ( $order_ids as $order_id ) {
        $order = new \WC_Order($order_id);

        $labels = $this->shipment->get_labels($order_id);
        if ( ! empty($labels) ) {
          $last_label = end($labels);
          $tracking_code = $last_label['tracking_code'];
        } else {
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
        /* translators: %s: Vendor full name */
        echo '<p>' . sprintf(__('%s has been installed/updated but no shipping methods are currently active!', 'woo-pakettikauppa'), $this->core->vendor_fullname) . '</p>';
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
    public function add_error_notice( $message, $show_prefix_text = true ) {
      if ( ! empty($message) ) {
        $class = 'notice notice-error';
        if ( $show_prefix_text ) {
          /* translators: %s: Error message */
          $print_error = wp_sprintf(__('An error occurred: %s', 'woo-pakettikauppa'), $message);
        } else {
          $print_error = $message;
        }
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
      }
    }

    /**
     * Add an admin success notice to wp-admin.
     */
    public function add_success_notice( $message, $show_prefix_text = true ) {
      if ( ! empty($message) ) {
        $class = 'notice notice-success';
        if ( $show_prefix_text ) {
          /* translators: %s: Error message */
          $print_error = wp_sprintf(__('Succeed: %s', 'woo-pakettikauppa'), $message);
        } else {
          $print_error = $message;
        }
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
      }
    }

    public function plugin_row_meta_wrapper( $links, $file ) {
      return $this->core->admin->plugin_row_meta($links, $file);
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
            esc_attr__('Visit Website', 'woo-pakettikauppa'),
            /* translators: %s: Vendor name */
            sprintf(esc_html__('Show site %s', 'woo-pakettikauppa'), $this->core->vendor_name)
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
      $service_id = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_custom_service_id', true);
      $default_service_id = $this->shipment->get_service_id_from_order($order, false);
      if ( empty($service_id) && empty($default_service_id) ) {
        return;
      }

      $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, 'kg');

      $services_data = array(
        'lqweight' => array(
          'title' => esc_attr__('Total weight of dangerous goods', 'woo-pakettikauppa'),
          'value' => (! empty($dangerous_goods['weight'])) ? $dangerous_goods['weight'] : 0,
          'unit' => 'kg',
          'show_always' => false,
        ),
      );
      ?>
      <div style="clear: both;"></div>
      <h4>
        <?php
        /* translators: %s: Vendor name */
        printf(esc_attr__('%s Shipping', 'woo-pakettikauppa'), $this->core->vendor_name);
        ?>
      </h4>
      <div class="address pakettikauppa">
        <p class="form-field pakettikauppa-field">
          <strong><?php esc_attr_e('Requested pickup point', 'woo-pakettikauppa'); ?></strong>
          <?php
          if ( $order->get_meta('_' . $this->core->params_prefix . 'pickup_point') ) {
            echo esc_attr($order->get_meta('_' . $this->core->params_prefix . 'pickup_point'));
          } else {
            echo esc_attr__('None');
          }
          ?>
          <br>
          <?php foreach ( $services_data as $service_key => $service_params ) : ?>
            <?php if ( ! empty($service_params['value']) || $service_params['show_always'] === true ) : ?>
              <strong><?php echo $service_params['title']; ?></strong>
              <?php
              if ( empty($service_params['unit']) ) {
                $service_params['unit'] = '';
              }
              $nr_dec = '.'; //Number decimals char
              $nr_tsd = ' '; //Number thousands char
              switch ( $service_params['unit'] ) {
                case 'kg':
                  $value_text = number_format($service_params['value'], 3, $nr_dec, $nr_tsd) . ' kg';
                  break;
                case 'g':
                  $value_text = number_format($service_params['value'], false, $nr_dec, $nr_tsd) . ' g';
                  break;
                default:
                  $value_text = $service_params['value'];
              }
              echo $value_text;
              ?>
              <br>
            <?php endif; ?>
          <?php endforeach; ?>
          <br>
          <?php echo __('Phone', 'woocommerce') . ': ' . get_post_meta($order->get_id(), '_shipping_phone', true); ?>
          <br>
          <?php echo __('Email', 'woocommerce') . ': ' . get_post_meta($order->get_id(), '_shipping_email', true); ?>
        </p>
      </div>
      <div class="edit_address pakettikauppa">
        <p class="form-field pakettikauppa-field">
          <strong><?php esc_attr_e('Requested pickup point', 'woo-pakettikauppa'); ?></strong>
          <?php
          if ( $order->get_meta('_' . $this->core->params_prefix . 'pickup_point') ) {
            echo esc_attr($order->get_meta('_' . $this->core->params_prefix . 'pickup_point'));
          } else {
            echo esc_attr__('None');
          }
          ?>
        </p>
        <?php
        /* $field_key = $this->core->params_prefix . 'shipping_phone'; ?>
        <p class="form-field <?php echo $field_key; ?>">
          <label for="<?php echo $field_key; ?>"><?php esc_attr_e('Phone', 'woo-pakettikauppa'); ?></label>
          <input type="text" class="short" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" value="<?php echo esc_attr(get_post_meta($order->get_id(), '_shipping_phone', true)); ?>">
        </p> */
        ?>
        <?php $field_key = $this->core->params_prefix . 'shipping_email'; ?>
        <p class="form-field <?php echo $field_key; ?>">
          <label for="<?php echo $field_key; ?>"><?php esc_attr_e('Email', 'woo-pakettikauppa'); ?></label>
          <input type="email" class="short" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" value="<?php echo esc_attr($order->get_meta('_shipping_email')); ?>">
        </p>
      </div>
      <div class="clear"></div>
      <?php
    }

    /**
     * Save custom order meta in order edit page
     */
    public function save_admin_order_meta( $post_id ) {
      global $post_type;
      if ( 'shop_order' != $post_type ) {
        return $post_id;
      }
      if ( isset($_POST[$this->core->params_prefix . 'shipping_phone']) ) {
        update_post_meta($post_id, '_shipping_phone', wc_clean($_POST[$this->core->params_prefix . 'shipping_phone']));
      }
      if ( isset($_POST[$this->core->params_prefix . 'shipping_email']) ) {
        update_post_meta($post_id, '_shipping_email', wc_clean($_POST[$this->core->params_prefix . 'shipping_email']));
      }
    }

    /**
     * Template for metabox section title which is little bigger then other titles.
     *
     * @param string $title Section title
     */
    private function tpl_section_title( $title ) {
      ?>
      <div class="pakettikauppa-section-title">
        <h3><?php echo $title; ?></h3>
      </div>
      <?php
    }

    /**
     * Template for metabox section end.
     */
    private function tpl_section_end() {
      ?>
      <hr>
      <?php
    }

    /**
     * Template for shipping label in Order metabox.
     *
     * @param array $label Label information
     */
    private function tpl_shipping_label( $label, $post_id ) {
      ?>
      <?php if ( ! empty($label['tracking_code']) ) : ?>
        <div class="pakettikauppa-shiplabel pakettikauppa-design-foldedcorner">
          <div class="corner">
            <div class="corner-triangle"></div>
          </div>
          <p>
            <span class="label_time"><?php echo (isset($label['timestamp'])) ? esc_attr(gmdate('Y-m-d H:i:s', $label['timestamp'])) : ''; ?></span>
            <strong><?php echo esc_attr($label['tracking_code']); ?></strong><br />
            <span><?php echo esc_attr($this->shipment->service_title($label['service_id'])); ?></span><br />
            <br />
            <?php $manifest_id = get_post_meta($post_id, $this->core->prefix . '_manifest', true); ?>
            <?php if ( $manifest_id ) : ?>
              <strong><?php echo __('Pickup order', 'woo-pakettikauppa'); ?>:</strong> <span>#<?php echo $manifest_id; ?></span><br />
              <?php
                $manifest = get_post($manifest_id);
                $pickup_order_status = get_post_status_object(get_post_status($manifest))->label;
              ?>
              <strong><?php echo __('Pickup order status', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $pickup_order_status; ?></span><br />
            <?php endif; ?>
            <strong><?php echo __('Status', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo esc_attr(Shipment::get_status_text($label['shipment_status'])); ?></span><br />
            <?php if ( ! empty($label['label_code']) ) : ?>
              <strong><?php echo __('Label code', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['label_code']; ?></span><br />
            <?php endif; ?>
            <?php if ( ! empty($label['pickup_id']) ) : ?>
              <strong><?php echo __('Pickup point', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['pickup_name']; ?></span><br />
            <?php endif; ?>
            <?php if ( ! empty($label['additional_services']) ) : ?>
              <?php
              $services = '';
              $exclude = array( '2106', '3102' );
              foreach ( $label['additional_services'] as $serv_key => $serv_content ) {
                if ( ! in_array($serv_key, $exclude) && isset($serv_content['name']) ) {
                  if ( ! empty($services) ) {
                    $services .= ', ';
                  }
                  $services .= $serv_content['name'];
                }
              }
              ?>
              <?php if ( ! empty($services) ) : ?>
                <strong><?php echo __('Services', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $services; ?></span><br />
              <?php endif; ?>
            <?php endif; ?>
            <?php if ( ! empty($label['products']) ) : ?>
              <?php
              $products = '';
              foreach ( $label['products'] as $prod ) {
                $product = wc_get_product($prod['prod']);
                $product_name = ($product) ? $product->get_title() : __('Unknown product', 'woo-pakettikauppa') . ' (ID: ' . $prod['prod'] . ')';
                $products .= '<br/>' . $prod['qty'] . ' x ' . $product_name;
              }
              ?>
              <strong><?php echo __('Products', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $products; ?></span><br />
            <?php endif; ?>
            <br />
            <a href="<?php echo esc_url($label['document_url']); ?>" target="_blank" class="download"><?php esc_attr_e('Print', 'woo-pakettikauppa'); ?></a> -
            <?php if ( ! empty($label['tracking_url']) ) : ?>
              <a href="<?php echo esc_url($label['tracking_url']); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a> -
            <?php endif; ?>
            <a href="javascript:void(0)" class="status" name="wc_pakettikauppa[get_status]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Refresh', 'woo-pakettikauppa'); ?></a> -
            <?php if ( $this->core->order_pickup ) : ?>
            <a href="javascript:void(0)" class="manifest" name="wc_pakettikauppa[add_to_manifest]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Add to pickup order', 'woo-pakettikauppa'); ?></a> -
            <?php endif; ?>
            <a href="javascript:void(0)" class="delete" name="wc_pakettikauppa[delete_shipping_label]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Delete', 'woo-pakettikauppa'); ?></a>
          </p>
        </div>
      <?php endif; ?>
      <?php
    }

    /**
     * Template for return label in Order metabox.
     *
     * @param array $label Label information
     */
    private function tpl_return_label( $label ) {
      ?>
      <div class="pakettikauppa-returnlabel pakettikauppa-design-foldedcorner">
        <div class="corner">
          <div class="corner-triangle"></div>
        </div>
        <p>
          <span class="label_time"><?php echo (isset($label['timestamp'])) ? esc_attr(gmdate('Y-m-d H:i:s', $label['timestamp'])) : ''; ?></span>
          <strong><?php echo esc_attr($label['tracking_code']); ?></strong><br />
          <span><?php echo esc_attr($this->shipment->service_title($label['service_id'])); ?></span><br />
          <br />
          <strong><?php echo __('Label code', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['label_code']; ?></span><br />
          <br />
          <a href="<?php echo esc_url($label['document_url']); ?>" target="_blank" class="download"><?php esc_attr_e('Print', 'woo-pakettikauppa'); ?></a> -
          <a href="<?php echo esc_url($label['tracking_url']); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a> -
          <a href="javascript:void(0)" class="delete" name="wc_pakettikauppa[delete_return_label]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Delete', 'woo-pakettikauppa'); ?></a>
        </p>
      </div>
      <?php
    }

    /**
     * Template for metabox return label buttons, which is using to control all return labels.
     */
    private function tpl_return_label_global_buttons() {
      ?>
      <div class="pakettikauppa-global-labels-buttons">
        <button type="button" value="create_return_label" name="wc_pakettikauppa[create_return_label]" onclick="pakettikauppa_meta_box_submit(this);" class="button pakettikauppa_meta_box"><?php echo __('Create Return Label', 'woo-pakettikauppa'); ?></button>
      </div>
      <?php
    }

    /**
     * Template for Order metabox products selection field.
     *
     * @param WC_Order $order The order that is currently being viewed in wp-admin
     */
    private function tpl_products_selector( $order ) {
      ?>
      <div class="pakettikauppa-metabox-products">
        <?php
        $items = $order->get_items();
        $order_items = array();
        foreach ( $items as $item ) {
          $item_data = $item->get_data();
          array_push(
            $order_items,
            array(
              'id' => $item_data['product_id'],
              'name' => $item_data['name'],
              'max' => $item_data['quantity'],
              'lqweight' => $this->core->product->get_product_dg_weight($item_data['product_id'], 'kg'),
            )
          );
        }
        ?>
        <h4><?php echo __('Create for products', 'woo-pakettikauppa'); ?></h4>
        <div class="prod_select_dropdown">
          <textarea id="prod_select_droptxt" class="list" readonly>-</textarea>
          <div id="prod_select_content" class="content">
            <?php foreach ( $order_items as $item ) : ?>
              <div class="list list_item">
                <label for="prod_<?php echo $item['id']; ?>" class="list item_label">
                  <input type="checkbox" id="prod_<?php echo $item['id']; ?>" class="list item_cb" value="<?php echo $item['id']; ?>" data-name="<?php echo $item['name']; ?>" data-lqweight="<?php echo $item['lqweight']; ?>" />
                  <span><?php echo $item['name']; ?> </span>
                  <input type="hidden" class="list quantity" min="1" max="<?php echo $item['max']; ?>" value="<?php echo $item['max']; ?>" />
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <script type="text/javascript">
          if (typeof init_prod_select !== "undefined") {
            init_prod_select();
          } else {
            document.addEventListener('DOMContentLoaded', function() {
              init_prod_select();
            }, false);
          }
        </script>
      </div>
      <?php
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
        esc_attr_e('Please add shipping info to the order to manage shipments.', 'woo-pakettikauppa');

        return;
      }

      $labels = $this->shipment->get_labels($post->ID);
      $service_id = null;

      foreach ( $labels as $key => $label ) {
        if ( empty($label['tracking_url']) ) {
          $labels[$key]['tracking_url'] = Shipment::tracking_url($this->core->tracking_base_url, $label['tracking_code']);
        }
        if ( empty($label['service_id']) ) {
          $labels[$key]['service_id'] = $this->shipment->get_service_id_from_order($order, false);
        }
        if ( empty($service_id) ) {
          $service_id = $labels[$key]['service_id'];
        }
        $labels[$key]['document_url'] = admin_url('admin-post.php?post=' . $post->ID . '&action=show_pakettikauppa&tracking_code=' . $label['tracking_code']);
      }

      $default_service_id = $this->shipment->get_service_id_from_order($order, false);
      if ( empty($service_id) ) {
        $service_id = $default_service_id;
      }

      $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');

      $default_additional_services = array();
      foreach ( $this->shipment->get_additional_services_from_order($order) as $_additional_service ) {
        $default_additional_services[] = key($_additional_service);
      }

      $return_shipments = get_post_meta($post->ID, '_' . $this->core->prefix . '_return_shipment');

      $all_shipment_services = $this->shipment->services();

      $all_additional_services = $this->shipment->get_additional_services();

      if ( empty($all_additional_services) ) {
        $all_additional_services = array();
      }
      $all_shipment_additional_services = array();
      if ( ! empty($all_additional_services) && ! empty($service_id) ) {
        $all_shipment_additional_services = $all_additional_services[$service_id];
      }

      if ( ! empty($all_shipment_additional_services) ) {
        foreach ( $all_shipment_additional_services as $additional_service ) {
          $additional_service_names[(string) $additional_service->service_code] = $additional_service->name;
        }
      }

      $order_postcode = $order->get_shipping_postcode();
      $order_address  = $order->get_shipping_address_1() . ' ' . $order->get_shipping_city();
      $order_country  = $order->get_shipping_country();

      $weight_unit = 'kg';
      $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, $weight_unit);

      $is_cod = $order->get_payment_method() === 'cod';
      $show_section = 'main';
      if ( empty($service_id) ) {
        $show_section = 'custom';
      }
      ?>
      <div>
        <?php if ( $show_section === 'custom' ) : ?>
          <div class="pakettikauppa-notice notice-error">
            <p>
              <?php _e('No shipping method configured! Configure shipping method from settings.', 'woo-pakettikauppa'); ?>
            </p>
          </div>
        <?php endif; ?>
        <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-meta-box'); ?>" id="pakettikauppa_metabox_nonce" />
        <input type="hidden" name="pakettikauppa_order_id" value="<?php echo esc_html($post->ID); ?>" id="pakettikauppa_metabox_order_id" />
        <?php
        if ( empty($service_id) ) {
          $this->tpl_section_title(__('Send order', 'woo-pakettikauppa'));
        }
        if ( ! empty($labels) ) {
          $this->tpl_section_title(__('Shipping labels', 'woo-pakettikauppa'));
          foreach ( $labels as $label ) {
            $this->tpl_shipping_label($label, $post->ID);
          }
        }
        if ( (! empty($labels) || ! empty($return_shipments)) && ! empty($service_id) ) {
          $this->tpl_section_title(__('Return labels', 'woo-pakettikauppa'));
          if ( ! empty($return_shipments) ) {
            foreach ( $return_shipments as $return_label ) {
              $this->tpl_return_label($return_label);
            }
          }
          if ( ! empty($labels) && ! empty($service_id) ) {
            $this->tpl_return_label_global_buttons();
          }
          $this->tpl_section_end();
        }
        ?>
          <div class="pakettikauppa-services">
            <?php $show_main = ($show_section == 'main') ? '' : 'display:none;'; ?>
            <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_shipping_method" style="<?php echo $show_main; ?>">
              <h4><?php echo esc_html($this->shipment->service_title($default_service_id)); ?></h4>
              <?php if ( ! empty($default_additional_services) ) : ?>
                <h4><?php echo esc_attr__('Additional services', 'woo-pakettikauppa'); ?>:</h4>
                <ol style="list-style: circle;">
                  <?php foreach ( $default_additional_services as $i => $additional_service ) : ?>
                    <?php if ( ! in_array($additional_service, array( '3102' ), true) ) : ?>
                      <li>
                        <?php if ( isset($additional_service_names[ $additional_service ]) ) : ?>
                          <?php echo $additional_service_names[ $additional_service ]; ?>
                        <?php else : ?>
                          <?php echo $additional_service; ?>
                        <?php endif; ?>
                        <?php if ( $additional_service == '3143' ) : ?>
                          <span class="service_info">(<span class="changeable_lqweight"><?php echo $dangerous_goods['weight']; ?></span> <?php echo $weight_unit; ?>)</span>
                        <?php endif; ?>
                      </li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if ( in_array('3102', $default_additional_services, true) ) : ?>
                    <li>
                      <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                      <input type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 3em;" min="1" step="1" max="15">
                    </li>
                  <?php endif; ?>
                </ol>
              <?php endif; ?>

              <?php if ( $pickup_point_id ) : ?>
                <?php
                $pickpoint_requested = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point');
                ?>
                <div class="pakettikauppa-pickup-point-requested">
                  <h4>
                    <?php echo esc_html__('Requested pickup point', 'woo-pakettikauppa'); ?>
                  </h4>
                  <p id="pickup-point-requested-txt">
                    <?php echo esc_html($pickpoint_requested); ?>
                  </p>
                </div>
              <?php endif; ?>
            </fieldset>

            <?php $show_custom = ($show_section == 'custom') ? '' : 'display:none;'; ?>
            <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_custom_shipping_method" style="<?php echo $show_custom; ?>">
              <?php if ( ! empty($all_shipment_services) ) : ?>
              <select name="wc_pakettikauppa_service_id" id="pakettikauppa-service" class="pakettikauppa_metabox_values" onchange="pakettikauppa_change_shipping_method(this);">
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
              <?php else : ?>
                <?php
                $settings_url = '/wp-admin/admin.php?page=wc-settings&tab=shipping&section=pakettikauppa_shipping_method';
                /* translators: %s: Settings page url */
                $message = sprintf(__('Service not working. Please check <a href="%s">settings</a>.', 'woo-pakettikauppa'), $settings_url);
                ?>
                <span class="pakettikauppa-msg-error"><?php echo $message; ?></span>
              <?php endif; ?>

              <?php foreach ( $all_additional_services as $method_code => $_additional_services ) : ?>
                <ol style="list-style: circle; display: none;" class="pk-admin-additional-services" id="pk-admin-additional-services-<?php echo $method_code; ?>">
                  <?php $show_3102 = false; ?>
                  <?php foreach ( $_additional_services as $additional_service ) : ?>
                    <?php if ( empty($additional_service->specifiers) || $additional_service->service_code === '3101' ) : ?>
                      <?php $elem_id = 'pk_custom_service_' . $method_code . '_' . $additional_service->service_code; ?>
                      <?php $info_text = ''; ?>
                      <?php if ( $additional_service->service_code === '3143' ) : ?>
                        <?php $info_text = '<span class="changeable_lqweight">' . $dangerous_goods['weight'] . '</span> kg'; ?>
                      <?php endif; ?>
                      <li class="service-<?php echo $additional_service->service_code; ?>">
                        <input
                                type="checkbox"
                                id="<?php echo $elem_id; ?>"
                                class="pakettikauppa_metabox_array_values"
                                name="wc_pakettikauppa_additional_services"
                                value="<?php echo $additional_service->service_code; ?>"
                                <?php echo ($additional_service->service_code === '3101' && $is_cod || in_array($additional_service->service_code, $default_additional_services) ? 'checked': ''); ?>
                                />
                        <label for="<?php echo $elem_id; ?>"><?php echo $additional_service->name; ?></label>
                        <?php if ( ! empty($info_text) ) : ?>
                          <span class="service_info">(<?php echo $info_text; ?>)</span>
                        <?php endif; ?>
                      </li>
                    <?php elseif ( $additional_service->service_code === '3102' ) : ?>
                      <?php $show_3102 = true; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if ( $show_3102 ) : ?>
                    <li class="service-3102">
                      <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                      <input class="pakettikauppa_metabox_values" type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 4em;" min="1" step="1" max="15">
                    </li>
                  <?php endif; ?>
                </ol>
                <?php if ( $this->shipment->service_has_pickup_points($method_code) ) : ?>
                  <?php
                  $address_override_field_name = $this->core->params_prefix . 'merchant_override_custom_pickup_point_address';
                  $custom_address = get_post_meta($order->get_id(), $address_override_field_name, true);
                  $custom_address = empty($custom_address) ? "$order_address, $order_postcode, $order_country" : $custom_address;
                  $pickup_points = $this->get_pickup_points_for_method($method_code, $order_postcode, $order_address, $order_country, $custom_address);
                  $select_first_option = '- ' . __('Select', 'woo-pakettikauppa') . ' -';
                  $settings = $this->shipment->get_settings();
                  $pickup_points_type = array();
                  if ( isset($settings['pickup_points_type']) && ! empty($settings['pickup_points_type']) && ! in_array('all', $settings['pickup_points_type']) ) {
                    $pickup_points_type = $settings['pickup_points_type'];
                  }
                  ?>
                  <div id="pickup-changer-<?php echo $method_code; ?>" class="pakettikauppa-pickup-changer" style="display: none;">
                    <script>
                      var btn_values_<?php echo $method_code; ?> = {
                        container_id : "pickup-changer-<?php echo $method_code; ?>"
                      };
                    </script>
                    <div class="pakettikauppa-pickup-search">
                      <h4><?php echo __('Search pickup points', 'woo-pakettikauppa'); ?></h4>
                      <input class="pakettikauppa-pickup-method" type="hidden" value="<?php echo $method_code; ?>">
                      <textarea class="pakettikauppa-pickup-search-field" rows="2" onchange="pakettikauppa_change_element_value('.pakettikauppa-pickup-search-field',this.value);"><?php echo $custom_address; ?></textarea>
                      <?php if ( $pickup_points_type ) { ?>
                        <ol style="list-style:circle;">
                          <li>
                            <input
                                    type="radio"
                                    id="search_filter_all_<?php echo $method_code; ?>"
                                    class="pakettikauppa_metabox_array_values"
                                    name="wc_pakettikauppa_search_filter"
                                    value="all"
                                    />
                            <label for="search_filter_all_<?php echo $method_code; ?>"><?php echo __('Without filters', 'woo-pakettikauppa'); ?></label>
                          </li>
                          <li>
                            <input
                                    type="radio"
                                    id="search_filter_filters_<?php echo $method_code; ?>"
                                    class="pakettikauppa_metabox_array_values"
                                    name="wc_pakettikauppa_search_filter"
                                    value="<?php echo implode(',', $pickup_points_type); ?>"
                                    />
                            <label for="search_filter_filters_<?php echo $method_code; ?>"><?php echo __('With filters', 'woo-pakettikauppa'); ?></label>
                          </li>
                        </ol>
                       <?php } ?>
                      <button type="button" value="search" class="button button-small btn-search" onclick="pakettikauppa_pickup_points_by_custom_address(btn_values_<?php echo $method_code; ?>);"><?php echo __('Search', 'woo-pakettikauppa'); ?></button>
                      <span class="pakettikauppa-msg-error error-pickup-search" style="display:none;"><?php echo __('No pickup points were found', 'woo-pakettikauppa'); ?></span>
                    </div>
                    <div class="pakettikauppa-pickup-select-block">
                      <h4><?php echo __('Select pickup point', 'woo-pakettikauppa'); ?></h4>
                      <select class="pakettikauppa_metabox_values pakettikauppa-pickup-select" onchange="pakettikauppa_change_selected_pickup_point(this);">
                        <?php if ( is_array($pickup_points) ) : ?>
                          <?php foreach ( $pickup_points as $point ) : ?>
                            <?php
                            $point_name    = $point->provider . ': ' . $point->name;
                            $point_id      = ' (#' . $point->pickup_point_id . ')';
                            $point_address = ' (' . $point->street_address . ')';
                            ?>
                            <option value="<?php echo $point_name . $point_id; ?>" data-id="<?php echo $point->pickup_point_id; ?>"><?php echo $point_name . $point_address; ?></option>
                          <?php endforeach; ?>
                        <?php else : ?>
                          <option>---</option>
                        <?php endif; ?>
                      </select>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php $settings = $this->shipment->get_settings(); ?>
              <div>
                <h4><?php echo $this->core->text->additional_info_param_title(); ?></h4>
                <textarea class="pakettikauppa-additional-info" rows="2"><?php echo $settings['label_additional_info'] ?? ''; ?></textarea>
              </div>
            </fieldset>
              <fieldset id = "default_shipment_additional_services">
                  <ol style="list-style: circle;">
                  </ol>
              </fieldset>
          </div>
          <div class="pakettikauppa-general">
            <?php $this->tpl_products_selector($order); ?>
            <?php if ( $this->core->shippingmethod == 'pakettikauppa_shipping_method' ) : ?>
              <div class="pakettikauppa-estimated-price">
                <span class="title">
                  <?php echo esc_html__('Estimated shipping price', 'woo-pakettikauppa'); ?>:
                </span>
                <span id="estimated-shipping-price" class="value" data-service="<?php echo esc_html($service_id); ?>">
                  <?php $estimated_price = $this->core->shipment->get_estimated_shipping_price($order, $service_id); ?>
                  <?php echo ($estimated_price) ? wc_price($estimated_price / 100) : str_replace('0', '-', wc_price(0)); ?>
                </span>
              </div>
            <?php endif; ?>
          </div>
          <p class="pakettikauppa-metabox-footer">
            <?php if ( $this->core->order_pickup ) : ?>
            <label for="wc_pakettikauppa_add_to_manifest" id="custom_add_to_manifest">
              <input type="checkbox" id="wc_pakettikauppa_add_to_manifest" class="pakettikauppa_metabox_array_values" name="wc_pakettikauppa_add_to_manifest" value="1">
              <?php echo esc_html__('Add to pickup order', 'woo-pakettikauppa'); ?>
            </label>
            <?php endif; ?>
            <?php if ( ! empty($service_id) ) : ?>
              <?php $button_text = __('Custom shipping...', 'woo-pakettikauppa'); ?>
              <button type="button" value="change" id="pakettikauppa_metabtn_change" class="button pakettikauppa_meta_box" onclick="pakettikauppa_change_method(this);" data-txt1="<?php echo $button_text; ?>" data-txt2="<?php echo __('Original shipping...', 'woo-pakettikauppa'); ?>">
                <?php echo $button_text; ?>
              </button>
            <?php endif; ?>
            <input type="hidden" id="pakettikauppa_microtime" name="pakettikauppa_microtime" value="<?php echo round(microtime(true) * 1000); ?>"/>
            <button type="button" value="create" id="pakettikauppa_metabtn_create" name="wc_pakettikauppa[create]" class="button pakettikauppa_meta_box button-primary" onclick="pakettikauppa_meta_box_submit(this);">
              <?php echo __('Create', 'woo-pakettikauppa'); ?>
            </button>
          </p>
      </div>
      <?php
    }

    public function get_pickup_point_by_custom_address() {
      $method_code = $_POST['method'];
      $custom_address = $_POST['address'];
      $type = $_POST['type'];
      $pickup_points = $this->get_pickup_points_for_method($method_code, null, null, null, $custom_address, $type);
      if ( $pickup_points == 'error-zip' ) {
        echo $pickup_points;
      } else {
        echo json_encode($pickup_points);
      }
      wp_die();
    }

    private function get_pickup_points_for_method( $method_code, $postcode, $address = null, $country = null, $custom_address = null, $type = null ) {
      $pickup_points = array();
      try {
        $settings = $this->shipment->get_settings();
        if ( $custom_address && $settings['show_pickup_point_override_query'] === 'yes' ) {
          $pickup_points = $this->shipment->get_pickup_points_by_free_input($custom_address, $method_code, $type);
        } elseif ( ! empty($postcode) ) {
          $pickup_points = $this->shipment->get_pickup_points($postcode, $address, $country, $method_code, $type);
        }
      } catch ( \Exception $e ) {
        $pickup_points = 'error-zip';
      }
      return $pickup_points;
    }

    public function update_estimated_shipping_price() {
      $method_code = $_POST['method'];
      $order_id = $_POST['order_id'];

      if ( empty($order_id) ) {
        wp_die();
      }

      $order = new \WC_Order((int) $order_id);

      if ( ! Shipment::validate_order_shipping_receiver($order) ) {
        wp_die();
      }

      $selected_point = '';
      if ( ! empty($_POST['point']) ) {
        preg_match('~\(#(.*?)\)~', $_POST['point'], $selected_point_id);
        if ( ! empty(intval($selected_point_id[1])) ) {
          $selected_point = intval($selected_point_id[1]);
        }
      }

      $selected_products = (isset($_POST['selected']) && is_array($_POST['selected'])) ? $_POST['selected'] : array();

      $additional_services = null;
      $selected_additional_services = (isset($_POST['services']) && is_array($_POST['services'])) ? $_POST['services'] : array();

      if ( ! empty($selected_point) ) {
        $selected_additional_services[] = array(
          'key' => '2106',
          'param' => $selected_point,
        );
      }

      if ( ! empty($selected_additional_services) ) {
        $additional_services = array();
        foreach ( $selected_additional_services as $service ) {
          $service_values = '';
          if ( $service['key'] == '3143' ) {
            $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
            if ( ! empty($dangerous_goods['weight']) ) {
              $service_values = array(
                'lqweight' => $dangerous_goods['weight'],
                'lqcount' => $dangerous_goods['count'],
              );
            }
          }
          if ( $service['key'] == 'wc_pakettikauppa_mps_count' && intval($service['param']) > 1 ) {
            $service['key'] = '3102';
            $service_values = array(
              'count' => (string) intval($service['param']),
            );
          }
          if ( $service['key'] == '2106' ) {
            $service_values = array(
              'pickup_point_id' => $selected_point,
            );
          }
          $additional_services[] = array( $service['key'] => $service_values );
        }
      }

      $estimated_price = $this->shipment->get_estimated_shipping_price($order, $method_code, $additional_services, $selected_products);

      echo ($estimated_price) ? wc_price($estimated_price / 100) : str_replace('0', '-', wc_price(0));
      wp_die();
    }

    public function ajax_check_credentials() {
      $account_number = $_POST['api_account'];
      $secret_key = $_POST['api_secret'];
      $api_check = $this->shipment->check_api_credentials($account_number, $secret_key);
      echo json_encode($api_check);
      wp_die();
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

      $old_request_id = $order->get_meta('_' . $this->core->params_prefix . 'request_id');
      if ( ! empty($_REQUEST['request_id']) && $old_request_id == $_REQUEST['request_id'] ) {
        return;
      } else {
        update_post_meta($order->get_id(), '_' . $this->core->params_prefix . 'request_id', $_REQUEST['request_id']);
      }

      if ( isset($_REQUEST['add_to_manifest']) ) {
        (new Manifest($this->core))->add_manifest_orders(null, str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest', array( $order->get_id() ));
      }

      $command = sanitize_key(key($_POST['wc_pakettikauppa']));

      $service_id = null;

      switch ( $command ) {
        case 'create':
          if ( ! empty($_REQUEST['wc_pakettikauppa_service_id']) ) {
            $service_id = sanitize_key($_REQUEST['wc_pakettikauppa_service_id']);
          }

          $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');
          $selected_products = (! empty($_REQUEST['for_products'])) ? $_REQUEST['for_products'] : array();

          if ( empty($_REQUEST['custom_method']) ) {
            $additional_services = null;

            if ( empty($pickup_point_id) && ! empty($_REQUEST['wc_pakettikauppa_pickup_point_id']) ) {
              $pickup_point_id = strtoupper(sanitize_key($_REQUEST['wc_pakettikauppa_pickup_point_id']));

              update_post_meta($order->get_id(), '_' . $this->core->params_prefix . 'pickup_point_id', $pickup_point_id);
            }
          } else {
            $additional_services = array();

            $settings = $this->shipment->get_settings();
            $additional_services_with_params = array(
              '3101' => array(
                'amount' => $order->get_total(),
                'account' => $settings['cod_iban'],
                'codbic' => $settings['cod_bic'],
                'reference' => $this->shipment->calculate_reference($order->get_id()),
              ),
            );

            $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
            if ( ! empty($dangerous_goods['weight']) ) {
              $additional_services_with_params['3143'] = array(
                'lqweight' => $dangerous_goods['weight'],
                'lqcount' => $dangerous_goods['count'],
              );
            }

            if ( ! empty($_REQUEST['additional_services']) ) {
              foreach ( $_REQUEST['additional_services'] as $_additional_service_code ) {
                $additional_service_params = null;
                if ( isset($additional_services_with_params[$_additional_service_code]) ) {
                  $additional_service_params = $additional_services_with_params[$_additional_service_code];
                }
                $additional_services[] = array( (string) $_additional_service_code => $additional_service_params );
              }
            }

            if ( ! empty($_REQUEST['wc_pakettikauppa_mps_count']) ) {
              $additional_services[] = array( '3102' => array( 'count' => (string) intval($_REQUEST['wc_pakettikauppa_mps_count']) ) );
            }

            if ( ! empty($_REQUEST['custom_pickup']) ) {
              $pickup_point_id = strtoupper(sanitize_key($_REQUEST['custom_pickup']));

              $additional_services[] = array(
                '2106' => array(
                  'pickup_point_id' => $pickup_point_id,
                ),
              );
            }
          }
          // additional text for custom shipment
          $extra_params = array();
          if ( isset($_REQUEST['additional_text']) ) {
            $extra_params['additional_text'] = sanitize_textarea_field($_REQUEST['additional_text']);
          }

          $tracking_code = $this->shipment->create_shipment($order, $service_id, $additional_services, $selected_products, $extra_params);

          return $tracking_code;
          break;
        case 'get_status':
          $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);
          $this->get_status($order, $tracking_code);
          break;
        case 'delete_shipping_label':
          $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);

          $this->delete_shipping_label($order, $tracking_code);
          break;
        case 'create_return_label':
          $this->create_return_label($order);
          break;
        case 'delete_return_label':
          $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);
          $this->delete_return_label($order, $tracking_code);
          break;
        case 'add_to_manifest':
          (new Manifest($this->core))->add_manifest_orders(null, str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest', array( $order->get_id() ));
          break;
      }
    }

    /**
     * @param WC_Order $order
     *
     * @throws Exception
     */
    private function create_return_label( \WC_Order $order ) {
      $shipping_label = $this->shipment->get_single_label($order->get_id());
      if ( ! $shipping_label ) {
        $this->add_error_notice(esc_attr__('It is not allowed to create a return label when shipping labels not exists', 'woo-pakettikauppa'));
        return;
      }

      if ( isset($shipping_label['service_id']) && ! empty($shipping_label['service_id']) ) {
        $service_id = $shipping_label['service_id'];
      } else {
        $service_id = $this->shipment->get_service_id_from_order($order, false);
      }

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
          $return_service_id = '90280';
          break;
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

          if ( version_compare(get_bloginfo('version'), '5.3.0', '>=') ) {
            $current_time = strtotime(wp_date('Y-m-d H:i:s'));
          } else {
            $current_time = current_time('timestamp');
          }

          add_post_meta(
            $order->get_id(),
            '_' . $this->core->prefix . '_return_shipment',
            array(
              'service_id' => $return_service_id,
              'tracking_code' => $tracking_code,
              'document_url' => $document_url,
              'tracking_url' => $tracking_url,
              'label_code' => $label_code,
              'timestamp' => $current_time,
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
        $old_label = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_code', true);
        $labels = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_labels', true);
        if ( $tracking_code == $old_label ) {
          $this->shipment->delete_old_structure_label($order->get_id());
        }
        foreach ( $labels as $key => $label ) {
          if ( $label['tracking_code'] == $tracking_code ) {
            unset($labels[$key]);
          }
        }
        update_post_meta($order->get_id(), '_' . $this->core->prefix . '_labels', $labels);
        /* translators: %1$s: Vendor name, %2$s: tracking code */
        $order->add_order_note(sprintf(esc_attr__('Deleted %1$s shipping label %2$s.', 'woo-pakettikauppa'), $this->core->vendor_name, $tracking_code));
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
            /* translators: %1$s: Vendor name, %2$s: Error message */
            esc_attr__('Deleting %1$s shipment failed! Errors: %2$s', 'woo-pakettikauppa'),
            $this->core->vendor_name,
            $e->getMessage()
          )
        );
      }
    }

    /**
     * @param WC_Order $order
     */
    private function delete_return_label( \WC_Order $order, $tracking_code ) {
      try {
        $return_shipments = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_return_shipment');

        foreach ( $return_shipments as $return_shipment ) {
          if ( $return_shipment['tracking_code'] === $tracking_code ) {
            delete_post_meta($order->get_id(), '_' . $this->core->prefix . '_return_shipment', $return_shipment);
            /* translators: %%s: tracking code */
            $order->add_order_note(sprintf(esc_attr__('Deleted %1$s return label %2$s.', 'woo-pakettikauppa'), $this->core->vendor_name, $tracking_code));
            return;
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
            /* translators: %1$s: Vendor name, %2$s: Error message */
            esc_attr__('Deleting %1$s return label failed! Errors: %2$s', 'woo-pakettikauppa'),
            $this->core->vendor_name,
            $e->getMessage()
          )
        );
      }
    }

    /**
     * @param WC_Order $order
     */
    private function get_status( \WC_Order $order, $tracking_code ) {
      try {
        $status_code = $this->shipment->get_shipment_status($tracking_code);
        $this->shipment->save_label(
          $order->get_id(),
          array(
            'tracking_code' => $tracking_code,
            'shipment_status' => $status_code,
          )
        );
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

      $tracking_code = sanitize_text_field($_REQUEST['tracking_code']); // @codingStandardsIgnoreLine
      $settings = $this->shipment->get_settings();
      $labels_size = (isset($settings['labels_size'])) ? $settings['labels_size'] : null;

      try {
        $contents = $this->shipment->fetch_shipping_label($tracking_code, $labels_size);
      } catch ( \Exception $e ) {
        esc_attr_e('Failed to get shipment label.', 'woo-pakettikauppa');
        echo '</br>' . esc_attr__('Error', 'woo-pakettikauppa') . ': ' . $e->getMessage();

        return;
      }

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

      $labels = $this->shipment->get_labels($order->get_ID());

      $tracking_codes = array();
      foreach ( $labels as $label ) {
        if ( ! empty($label['tracking_code']) ) {
          array_push(
            $tracking_codes,
            array(
              'code' => $label['tracking_code'],
              'url' => $label['tracking_url'],
              'point' => $label['pickup_name'],
            )
          );
        }
      }

      if ( empty($tracking_codes) ) {
        return;
      }

      $template = $plain_text ? $this->core->templates->tracking_email->txt : $this->core->templates->tracking_email->html;
      wc_get_template(
        $template,
        array(
          'tracking_codes' => $tracking_codes,
          'add_pickup_point_to_email' => $add_pickup_point_to_email,
        ),
        '',
        $this->core->templates_dir
      );
    }

    public function get_custom_shipment_table() {
      if ( ! isset($_REQUEST['id']) ) {
        return false;
      }

      $ids = $_REQUEST['id'];

      ?>
      <div class="loader-wrapper"><span class="loader"></span></div>
      <div id="pakettikauppa-shipments-table">
        <div>
          <h3><?php echo esc_attr__('Pakettikaupa create custom shipments', 'woo-pakettikauppa'); ?></h3>
          <div>
            <table class="wp-list-table widefat fixed striped table-view-list posts">
              <thead>
                <tr>
                  <th><?php echo esc_attr__('Order', 'woo-pakettikauppa'); ?></th>
                  <th><?php echo esc_attr__('Current shipping method', 'woo-pakettikauppa'); ?></th>
                  <th><?php echo esc_attr__('New shipping method', 'woo-pakettikauppa'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php
                  foreach ( $ids as $id ) {
                    $order = wc_get_order($id);
                    if ( $order ) {
                ?>
                      <tr class="inside" id="woo-pakettikauppa_<?php echo $id; ?>">
                        <td>#<?php echo $id; ?> <?php echo $order->get_formatted_shipping_full_name(); ?></td>
                        <?php $this->meta_box_custom_shipments($order); ?>
                      </tr>
                      <?php
                        }
                      }
                      ?>
              </tbody>
            </table>
          </div>
        </div>

        <button type="button" value="create" id="pakettikauppa_metabtn_create_bulk" name="wc_pakettikauppa[create]" class="button pakettikauppa_meta_box button-primary" onclick="pakettikauppa_meta_box_bulk_submit(this);">
          <?php echo esc_attr__('Create', 'woo-pakettikauppa'); ?>
        </button>
      </div>
      <?php
    }

    public function ajax_get_pickup_points() {
      if ( ! isset($_POST['id']) ) {
        return '';
        wp_die();
      }

      $id = $_POST['id'];
      $this->get_pickup_points_html($id);
      wp_die();
    }

    /**
     * Meta box for managing shipments.
     *
     * @param $post
     */
    public function meta_box_custom_shipments( $order ) {
      if ( $order === null ) {
        return;
      }

      if ( ! Shipment::validate_order_shipping_receiver($order) ) {
        esc_attr_e('Please add shipping info to the order to manage shipments.', 'woo-pakettikauppa');
        return;
      }

      $service_id = '';

      $default_service_id = $this->shipment->get_service_id_from_order($order, false);
      if ( empty($service_id) ) {
        $service_id = $default_service_id;
      }

      $all_shipment_services = $this->shipment->services();
      ?>
      <td class="current">
      <?php $this->get_current_shipment($order); ?>
      </td>
      <td>
        <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_custom_shipping_method">
          <?php if ( ! empty($all_shipment_services) ) : ?>
          <select name="wc_pakettikauppa_service_id" id="pakettikauppa-service" class="pakettikauppa_metabox_values" onchange="pakettikauppa_change_shipping_method(this);">
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
          <?php else : ?>
            <?php
            $settings_url = '/wp-admin/admin.php?page=wc-settings&tab=shipping&section=pakettikauppa_shipping_method';
            /* translators: %s: Settings page url */
            $message = sprintf(__('Service not working. Please check <a href="%s">settings</a>.', 'woo-pakettikauppa'), $settings_url);
            ?>
            <span class="pakettikauppa-msg-error"><?php echo $message; ?></span>
          <?php endif; ?>

        </fieldset>

        <input type="hidden" name="pakettikauppa_microtime" value="<?php echo round(microtime(true) * 1000); ?>"/>
        <input type="hidden" name="pakettikauppa_order_id[]" value="<?php echo $order->get_id(); ?>"/>

      </td>
      <?php
    }

    public function get_current_shipment( $order ) {
      if ( $order === null ) {
        return;
      }
      $default_service_id = $this->shipment->get_service_id_from_order($order, false);
      $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');
      ?>
      <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-meta-box'); ?>" id="pakettikauppa_metabox_nonce" />
      <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_shipping_method">
        <p><?php echo esc_html($this->shipment->service_title($default_service_id)); ?></p>

        <?php if ( $pickup_point_id ) : ?>
          <?php
          $labels = $this->shipment->get_labels($order->get_id());
          if ( ! empty($labels) ) {
            $last_label = end($labels);
            $pickpoint_requested = $last_label['pickup_name'];
          } else {
            $pickpoint_requested = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point');
          }
          ?>
          <div class="pakettikauppa-pickup-point-requested">
            <p>
              <b><?php echo esc_html__('Pickup point', 'woo-pakettikauppa'); ?></b></br>
              <?php echo esc_html($pickpoint_requested); ?>
            </p>
          </div>
        <?php endif; ?>
      </fieldset>
      <?php
    }

    public function get_pickup_points_html( $id ) {
      $order = wc_get_order($id);

      if ( $order === null ) {
        return;
      }

      $all_additional_services = $this->shipment->get_additional_services();

      if ( empty($all_additional_services) ) {
        $all_additional_services = array();
      }
      $all_shipment_additional_services = array();
      if ( ! empty($all_additional_services) && ! empty($service_id) ) {
        $all_shipment_additional_services = $all_additional_services[$service_id];
      }

      if ( ! empty($all_shipment_additional_services) ) {
        foreach ( $all_shipment_additional_services as $additional_service ) {
          $additional_service_names[(string) $additional_service->service_code] = $additional_service->name;
        }
      }

      $order_postcode = $order->get_shipping_postcode();
      $order_address  = $order->get_shipping_address_1() . ' ' . $order->get_shipping_city();
      $order_country  = $order->get_shipping_country();
      $address_override_field_name = $this->core->params_prefix . 'merchant_override_custom_pickup_point_address';
      $custom_address = get_post_meta($order->get_id(), $address_override_field_name, true);
      $custom_address = empty($custom_address) ? "$order_address, $order_postcode, $order_country" : $custom_address;

      $service_id = '';

      $default_service_id = $this->shipment->get_service_id_from_order($order, false);
      if ( empty($service_id) ) {
        $service_id = $default_service_id;
      }

      foreach ( $all_additional_services as $method_code => $_additional_services ) {
        if ( $this->shipment->service_has_pickup_points($method_code) ) {
          $pickup_points = $this->get_pickup_points_for_method($method_code, $order_postcode, $order_address, $order_country, $custom_address);
          ?>
          <div id="pickup-changer-<?php echo $method_code; ?>" class="pakettikauppa-pickup-changer" <?php echo $service_id != $method_code ? 'style="display: none;"' : ''; ?>>
            <script>
              var btn_values_<?php echo $method_code; ?> = {
                container_id : "pickup-changer-<?php echo $method_code; ?>"
              };
            </script>
            <div class="pakettikauppa-pickup-select-block">
              <p style="margin-bottom: 5px;"><?php echo __('Select pickup point', 'woo-pakettikauppa'); ?></p>
              <select class="pakettikauppa_metabox_values pakettikauppa-pickup-select" onchange="pakettikauppa_change_selected_pickup_point(this);">
                <?php if ( is_array($pickup_points) ) : ?>
                  <?php foreach ( $pickup_points as $point ) : ?>
                    <?php
                    $point_name    = $point->provider . ': ' . $point->name;
                    $point_id      = ' (#' . $point->pickup_point_id . ')';
                    $point_address = ' (' . $point->street_address . ')';
                    ?>
                    <option value="<?php echo $point_name . $point_id; ?>" data-id="<?php echo $point->pickup_point_id; ?>"><?php echo $point_name . $point_address; ?></option>
                  <?php endforeach; ?>
                <?php else : ?>
                  <option>---</option>
                <?php endif; ?>
              </select>
            </div>
          </div>
          <?php
        }
      }
    }
  }
}
