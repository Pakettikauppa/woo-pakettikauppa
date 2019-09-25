<?php

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists('WC_Pakettikauppa_Setup_Wizard') ) {

  class WC_Pakettikauppa_Setup_Wizard {
    private $steps = array();
    private $step = '';
    private $shipping_method = null;

    public function __construct() {
      if ( apply_filters('wc_pakettikauppa_enable_setup_wizard', true) && current_user_can('manage_woocommerce') ) {
        add_action('admin_menu', array( $this, 'admin_page' ));
        add_action('admin_init', array( $this, 'setup_wizard' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));
      }
    }

    public function admin_page() {
      add_dashboard_page('', '', 'manage_options', 'wcpk-setup', '');
    }

    public function setup_wizard() {
      $this->steps = array(
        'pakettikauppa_credentials' => array(
          'name' => __('Credentials', 'wc-pakettikauppa'),
          'view' => array( $this, 'credentials_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'mode',
            'account_number',
            'secret_key',
          ),
        ),
        'pakettikauppa_merchant_details' => array(
          'name' => __('Merchant', 'wc-pakettikauppa'),
          'view' => array( $this, 'merchant_details_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'sender_name',
            'sender_address',
            'sender_postal_code',
            'sender_city',
          ),
        ),
        'pakettikauppa_shipping_details' => array(
          'name' => __('Shipping', 'wc-pakettikauppa'),
          'view' => array( $this, 'shipping_details_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'pickup_points',
          ),
        ),
        'pakettikauppa_order_processing' => array(
          'name' => __('Order Processing', 'wc-pakettikauppa'),
          'view' => array( $this, 'order_processing_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'add_tracking_to_email',
            'add_pickup_point_to_email',
          ),
        ),
        'pakettikauppa_ready' => array(
          'name' => __('Ready!', 'wc-pakettikauppa'),
          'view' => array( $this, 'setup_ready' ),
          'handler' => '',
        ),
      );

      // Show the first step by default
      $this->step = 'pakettikauppa_credentials';
      if ( isset($_GET['wcpk-setup-step']) && in_array(sanitize_key($_GET['wcpk-setup-step']), array_keys($this->steps), true) ) {
        $this->step = sanitize_key($_GET['wcpk-setup-step']);
      }
      $shipping_methods = WC()->shipping()->get_shipping_methods();
      if ( isset($shipping_methods['pakettikauppa_shipping_method']) ) {
        $this->shipping_method = $shipping_methods['pakettikauppa_shipping_method'];
      }

      // Check if there is a save currently in progress, call handler if necessary
      $save_step = filter_input(INPUT_POST, 'save_step', FILTER_SANITIZE_SPECIAL_CHARS);
      if ( $save_step && isset($this->steps[$this->step]['handler']) ) {
        call_user_func($this->steps[$this->step]['handler'], $this);
      }

      $this->run();
    }

    public function run() {
      $this->print_header();
      $this->print_step_list();
      $this->print_step_content();
      $this->print_footer();
      exit();
    }

    public function enqueue_scripts() {
      wp_enqueue_style('wc_pakettikauppa_admin_setup', plugin_dir_url(__FILE__) . '../assets/css/wc-pakettikauppa-admin-setup.css', array(), WC_PAKETTIKAUPPA_VERSION);
      wp_enqueue_style('wp-admin');
      wp_enqueue_style('buttons');
    }

    public function print_header() {
      set_current_screen();
      ?>
      <!DOCTYPE html>
      <html <?php language_attributes(); ?>>
      <head>
        <meta name="viewport" content="width=device-width" />
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php esc_html_e('WooCommerce Pakettikauppa &rsaquo; Setup Wizard', 'wc-pakettikauppa'); ?></title>
        <?php do_action('admin_enqueue_scripts'); ?>
        <?php do_action('admin_print_styles'); ?>
        <?php do_action('admin_head'); ?>
      </head>
      <body class="wcpk-setup-body wp-core-ui" style="background-image: url(<?php echo plugin_dir_url(__FILE__) . '../assets/img/pakettikauppa-background.jpg'; ?>)">
        <div class="wcpk-setup">
          <h1 id="pakettikauppa-logo">
            <a href="https://www.pakettikauppa.fi/" target="_blank" rel="noreferrer noopener" aria-label="<?php esc_html_e('Link to Pakettikauppa website', 'wc-pakettikauppa'); ?>">
              <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/pakettikauppa-logo.png'; ?>" alt="<?php esc_attr_e('Pakettikauppa', 'wc-pakettikauppa'); ?>" />
            </a>
          </h1>
          <?php
    }

    public function print_step_list() {
      echo '<ol class="wcpk-setup-steps">';
      $completed = true;
      foreach ( $this->steps as $step_key => $step ) {
        if ( $this->step === $step_key ) {
          echo '<li class="active">' . esc_html($step['name']) . '</li>';
          $completed = false;
        } elseif ( $completed ) {
          // Allow to return to previous steps
          $step_return_link = esc_url(add_query_arg('wcpk-setup-step', $step_key));
          echo '<li class="completed"><a href="' . $step_return_link . '">' . esc_html($step['name']) . '</a></li>';
        } else {
          echo '<li>' . esc_html($step['name']) . '</li>';
        }
      }
      echo '</ol>';
    }

    public function print_footer() {
      echo '<div class="wcpk-setup-footer-links">';
      // Provide an option to skip the whole wizard on the first step
      if ( $this->step === array_keys($this->steps)[0] ) {
        echo '<a href="' . esc_url(admin_url()) . '">';
        echo esc_html_e('Not now', 'wc-pakettikauppa');
        echo '</a>';
      } elseif ( $this->step !== array_keys($this->steps)[count($this->steps) - 1] ) {
        // For skipping individual steps
        echo '<a href="' . esc_url($this->get_next_step_link()) . '">';
        echo esc_html_e('Skip this step', 'wc-pakettikauppa');
        echo '</a>';
      }

      echo '</div></body></html>';
    }

    public function print_step_content() {
      echo '<div class="wcpk-setup-wizard-content">';
      $view = $this->steps[$this->step]['view'];
      if ( ! empty($view) ) {
        call_user_func($view, $this);
      }
      echo '</div>';
    }

    public function credentials_setup() {
      ?>
      <p class="wcpk-setup-welcome">
        <?php esc_html_e('Thank you for installing WooCommerce Pakettikauppa! This wizard will guide you through the setup process to get you started.', 'wc-pakettikauppa'); ?>
      </p>
      <p class="wcpk-setup-info">
        <?php _e('If you have already registered with Pakettikauppa, please choose "Production mode" and enter the credentials you received from Pakettikauppa. If you have not yet registered, please register at <a target="_blank" rel="noopener noreferrer" href="https://www.pakettikauppa.fi">www.pakettikauppa.fi</a>. If you wish to test the plugin before making a contract with Pakettikauppa, please choose "Test mode" and leave the API secret/key fields empty.', 'wc-pakettikauppa'); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps['pakettikauppa_credentials']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_credentials" name="save_step">
              <?php esc_html_e('Let\'s start!', 'wc-pakettikauppa'); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function merchant_details_setup() {
      ?>
      <p class="wcpk-setup-info">
        <?php esc_html_e('Please fill the details of the merchant below. The information provided here will be used as the sender in shipping labels.', 'wc-pakettikauppa'); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps['pakettikauppa_merchant_details']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_merchant_details" name="save_step">
              <?php esc_html_e('Continue', 'wc-pakettikauppa'); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function shipping_details_setup() {
      ?>
      <p class="wcpk-setup-info">
        <?php
        echo sprintf(
          /*
           * translators:
           * %1$s: link to WooCommerce shipping zone setting page
           * %2$s: link to external WooCommerce documentation
           */
          __('Please configure the shipping methods of the currently active shipping zones to use Pakettikauppa shipping. Note that this plugin requires WooCommerce shipping zones and methods to be preconfigured in <a href="%1$s">WooCommerce > Settings > Shipping > Shipping zones</a>. For more information, visit <a target="_blank" href="%2$s">%2$s</a>.', 'wc-pakettikauppa'),
          esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')),
          'https://docs.woocommerce.com/document/setting-up-shipping-zones/'
        );
        ?>
      </p>
      <div class="wcpk-setup-settings-wrapper wcpk-shipping-setup">
        <form method="post">
          <table>
            <?php
            wp_nonce_field('wcpk-setup');
            $this->print_setting_fields($this->steps['pakettikauppa_shipping_details']['fields']);
            ?>
          </table>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_shipping_details" name="save_step">
              <?php esc_html_e('Continue', 'wc-pakettikauppa'); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function order_processing_setup() {
      ?>
      <p class="wcpk-setup-info">
        <?php esc_html_e('Customize the order processing phase.', 'wc-pakettikauppa'); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps['pakettikauppa_order_processing']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_order_processing" name="save_step">
              <?php esc_html_e('Continue', 'wc-pakettikauppa'); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function setup_ready() {
      ?>
      <p class="wcpk-setup-info">
        <?php esc_html_e('Congratulations, everything is now set up and you are now ready to start using the plugin!', 'wc-pakettikauppa'); ?>
      </p>
      <p class="wcpk-setup-actions step">
        <a href="<?php echo esc_url(admin_url()); ?>">
          <button class="button-primary button button-large button-next">
            <?php esc_html_e('Exit', 'wc-pakettikauppa'); ?>
          </button>
        </a>
      </p>
      <?php
    }

    public function save_options() {
      check_admin_referer('wcpk-setup');

      if ( isset($this->steps[$this->step]['fields']) ) {
        $form_fields = $this->shipping_method->get_form_fields();
        foreach ( $this->steps[$this->step]['fields'] as $field_name ) {
          if ( in_array($field_name, array_keys($form_fields), true) ) {

            // We can't use the value directly, because WC uses a special format
            // for storing the option data. By using WC_Shipping_Method::get_field_value(),
            // we will get the sanitized value.
            $field_value = $this->shipping_method->get_field_value($field_name, $form_fields[$field_name]);
            $this->shipping_method->update_option($field_name, $field_value);
          }
        }
      }

      wp_safe_redirect(esc_url_raw($this->get_next_step_link()));
      exit();
    }

    private function print_setting_fields( $fields = array() ) {
      $form_fields = $this->shipping_method->get_form_fields();
      $desired_fields = array_intersect_key($form_fields, array_flip($fields));
      $this->shipping_method->generate_settings_html($desired_fields);
    }

    private function get_next_step_link() {
      $keys = array_keys($this->steps);
      if ( end($keys) === $this->step ) {
        return admin_url();
      }
      $step_index = array_search($this->step, $keys, true);
      if ( false === $step_index ) {
        return '';
      }
      return add_query_arg('wcpk-setup-step', $keys[$step_index + 1]);
    }
  }
}

new WC_Pakettikauppa_Setup_Wizard();
