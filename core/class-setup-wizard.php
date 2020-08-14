<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Setup_Wizard') ) {
  class Setup_Wizard {
    /**
     * @var Core
     */
    private $core = null;

    private $steps = array();
    private $step = '';
    private $shipping_method = null;
    public function __construct( Core $plugin ) {
      $this->core = $plugin;

      if ( apply_filters($this->core->prefix . '_enable_setup_wizard', true) && current_user_can('manage_woocommerce') ) {
        add_action('admin_menu', array( $this, 'admin_page' ));
        add_action('admin_init', array( $this, 'setup_wizard' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));
      }
    }

    public function admin_page() {
      add_dashboard_page('', '', 'manage_options', $this->core->setup_page, '');
    }

    public function setup_wizard() {
      $this->steps = array(
        str_replace('wc_', '', $this->core->prefix) . '_credentials' => array(
          'name' => $this->core->text->setup_credentials(),
          'view' => array( $this, 'credentials_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'mode',
            'account_number',
            'secret_key',
          ),
        ),
        str_replace('wc_', '', $this->core->prefix) . '_merchant_details' => array(
          'name' => $this->core->text->setup_merchant(),
          'view' => array( $this, 'merchant_details_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'sender_name',
            'sender_address',
            'sender_postal_code',
            'sender_city',
            'sender_country',
            'sender_phone',
            'sender_email',
          ),
        ),
        str_replace('wc_', '', $this->core->prefix) . '_shipping_details' => array(
          'name' => $this->core->text->setup_shipping(),
          'view' => array( $this, 'shipping_details_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'pickup_points',
            'cod_title',
            'cod_iban',
            'cod_bic',
          ),
        ),
        str_replace('wc_', '', $this->core->prefix) . '_order_processing' => array(
          'name' => $this->core->text->setup_order_processing(),
          'view' => array( $this, 'order_processing_setup' ),
          'handler' => array( $this, 'save_options' ),
          'fields' => array(
            'add_tracking_to_email',
            'add_pickup_point_to_email',
          ),
        ),
        str_replace('wc_', '', $this->core->prefix) . '_ready' => array(
          'name' => $this->core->text->setup_ready(),
          'view' => array( $this, 'setup_ready' ),
          'handler' => '',
        ),
      );

      // Show the first step by default
      $this->step = str_replace('wc_', '', $this->core->prefix) . '_credentials';
      if ( isset($_GET['wcpk-setup-step']) && in_array(sanitize_key($_GET['wcpk-setup-step']), array_keys($this->steps), true) ) {
        $this->step = sanitize_key($_GET['wcpk-setup-step']);
      }

      // I don't know if this is *the* way to do this, but this works.
      $this->shipping_method = $this->core->shipping_method_instance;

      // This doesn't work, it causes a bad gateway which is very hard to debug, especially because it only happens in
      // certain conditions. Last known trigger: `wp option delete woocommerce_pakettikauppa_shipping_method_settings`
      // $shipping_methods = WC()->shipping()->get_shipping_methods();
      // if ( isset($shipping_methods[$this->core->shippingmethod]) ) {
      //   $this->shipping_method = $shipping_methods[$this->core->shippingmethod];
      // }

      // Check if there is a save currently in progress, call handler if necessary
      $save_step = filter_input(\INPUT_POST, 'save_step', \FILTER_SANITIZE_SPECIAL_CHARS);
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
      wp_enqueue_style($this->core->prefix . '_admin_setup', $this->core->dir_url . 'assets/css/admin-setup.css', array(), $this->core->version);
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
        <title><?php echo $this->core->text->setup_title(); ?></title>
        <?php do_action('admin_enqueue_scripts'); ?>
        <?php do_action('admin_print_styles'); ?>
        <?php do_action('admin_head'); ?>
      </head>
      <body class="wcpk-setup-body wp-core-ui" style="background-image: url('<?php echo esc_attr($this->core->setup_background); ?>')">
        <div class="wcpk-setup">
          <h1 id="pakettikauppa-logo">
            <a
              href="<?php echo esc_html($this->core->vendor_url); ?>"
              target="_blank" rel="noreferrer noopener"
              aria-label="<?php echo $this->core->text->vendor_website_link_label(); ?>"
            >
              <img
                src="<?php echo esc_attr($this->core->vendor_logo); ?>"
                alt="<?php esc_attr($this->core->vendor_name); ?>"
              />
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
          // Allow returning to previous steps
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
        echo $this->core->text->not_now();
        echo '</a>';
      } elseif ( $this->step !== array_keys($this->steps)[count($this->steps) - 1] ) {
        // For skipping individual steps
        echo '<a href="' . esc_url($this->get_next_step_link()) . '">';
        echo $this->core->text->skip_this_step();
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
        <?php
          echo $this->core->text->setup_intro();
        ?>
      </p>
      <p class="wcpk-setup-info">
        <?php echo $this->core->text->setup_credential_info(); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps[str_replace('wc_', '', $this->core->prefix) . '_credentials']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_credentials" name="save_step">
              <?php echo $this->core->text->lets_start(); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function merchant_details_setup() {
      ?>
      <p class="wcpk-setup-info">
        <?php echo $this->core->text->setup_merchant_info(); ?>

      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps[str_replace('wc_', '', $this->core->prefix) . '_merchant_details']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_merchant_details" name="save_step">
              <?php echo $this->core->text->btn_continue(); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function shipping_details_setup() {
      ?>
      <p class="wcpk-setup-info">
        <?php echo $this->core->text->setup_shipping_info(); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper wcpk-shipping-setup">
        <form method="post">
          <table>
            <?php
            wp_nonce_field('wcpk-setup');
            $this->print_setting_fields($this->steps[str_replace('wc_', '', $this->core->prefix) . '_shipping_details']['fields']);
            ?>
          </table>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_shipping_details" name="save_step">
              <?php echo $this->core->text->btn_continue(); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function order_processing_setup() {
      ?>
      <p class="wcpk-setup-info">
      <?php echo $this->core->text->setup_processing_info(); ?>
      </p>
      <div class="wcpk-setup-settings-wrapper">
        <form method="post">
          <?php
          wp_nonce_field('wcpk-setup');
          $this->print_setting_fields($this->steps[str_replace('wc_', '', $this->core->prefix) . '_order_processing']['fields']);
          ?>
          <p class="wcpk-setup-actions step">
            <button type="submit" class="button-primary button button-large button-next" value="pakettikauppa_order_processing" name="save_step">
              <?php echo $this->core->text->btn_continue(); ?>
            </button>
          </p>
        </form>
      </div>
      <?php
    }

    public function setup_ready() {
      ?>
      <p class="wcpk-setup-info">
        <?php echo $this->core->text->setup_ready_info(); ?>
      </p>
      <p class="wcpk-setup-actions step">
        <a href="<?php echo esc_url(admin_url()); ?>">
          <button class="button-primary button button-large button-next">
            <?php echo $this->core->text->btn_exit(); ?>
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
