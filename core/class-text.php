<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Text') ) {
  /**
   * Class used to hold all translatable strings of the plugin.
   * Allows whitelabel plugins to redefine some methods to change text displayed to the user.
   * @todo Add rest of the strings and remove default arguments
   */
  class Text {
    /**
     * @var Core
     */
    private $core = null;

    public function __construct( Core $plugin ) {
      $this->core = $plugin;
    }

    public function setup_title() {
      return esc_html__('WooCommerce Pakettikauppa &rsaquo; Setup Wizard', 'woo-pakettikauppa');
    }

    public function setup_button_text() {
      return __('Start the setup wizard', 'woo-pakettikauppa');
    }

    public function shipping_method_name() {
      return __('Pakettikauppa', 'woo-pakettikauppa');
    }

    public function shipping_method_desc() {
      return __('Edit to select shipping company and shipping prices.', 'woo-pakettikauppa');
    }

    public function selected_shipping_method( $method ) {
      return sprintf(
        /* translators: %s: shipping method */
        __('Selected shipping method: %s', 'woo-pakettikauppa'),
        $method
      );
    }

    public function no_shipping() {
      return esc_html__('No shipping', 'woo-pakettikauppa');
    }

    public function includes_pickup_points() {
      return esc_html__('includes pickup points', 'woo-pakettikauppa');
    }

    public function select_one_shipping_method() {
      return __('Select one shipping method', 'woo-pakettikauppa');
    }

    public function unable_connect_to_vendor_server() {
      return __('Can not connect to Pakettikauppa server - please check Pakettikauppa API credentials, servers error log and firewall settings.', 'woo-pakettikauppa');
    }

    public function legacy_shipping_method_desc( $vendor_name = null ) {
      if ( ! $vendor_name ) {
        $vendor_name = \esc_html($this->core->vendor_name);
      }

      return sprintf(
        /* translators: Vendor name, not translatable */
        __(
          'Only use this shipping method if no other shipping methods are available and suitable. Using this shipping method is not required to be able to use %s plugin.',
          'woo-pakettikauppa'
        ),
        $vendor_name
      );
    }

    public function shipping_methods() {
      return __('Shipping methods', 'woo-pakettikauppa');
    }

    public function shipping_method() {
      return __('Shipping method', 'woo-pakettikauppa');
    }

    public function price_vat_included() {
      return __('Price (vat included)', 'woo-pakettikauppa');
    }

    public function shipping_cost() {
      return __('Shipping cost', 'woo-pakettikauppa');
    }

    public function shipping_cost_vat_included() {
      return __('Shipping cost  (vat included)', 'woo-pakettikauppa');
    }

    public function free_shipping_tier() {
      return __('Free shipping tier', 'woo-pakettikauppa');
    }

    public function free_shipping_tier_desc() {
      return __('After which amount shipping is free.', 'woo-pakettikauppa');
    }

    public function default_shipping_class_cost() {
      return __('Default shipping class cost', 'woo-pakettikauppa');
    }

    public function no_shipping_class_cost() {
      return __('No shipping class cost (vat included)', 'woo-pakettikauppa');
    }


    public function vendor_website_link_label( $vendor_name = null ) {
      if ( ! $vendor_name ) {
        $vendor_name = $this->core->vendor_name;
      }

      /* translators: Vendor name, not translatable */
      return sprintf(esc_html__('Link to %s website', 'woo-pakettikauppa'), $vendor_name);
    }

    public function not_now() {
      return esc_html__('Not now', 'woo-pakettikauppa');
    }

    public function skip_this_step() {
      return esc_html__('Skip this step', 'woo-pakettikauppa');
    }

    public function lets_start() {
      return esc_html__('Let\'s start!', 'woo-pakettikauppa');
    }

    public function btn_continue() {
      return esc_html__('Continue', 'woo-pakettikauppa');
    }

    public function btn_exit() {
      return esc_html__('Exit', 'woo-pakettikauppa');
    }

    public function setup_intro() {
      return esc_html__('Thank you for installing WooCommerce Pakettikauppa! This wizard will guide you through the setup process to get you started.', 'woo-pakettikauppa');
    }

    public function setup_credential_info( $vendor_name = null, $vendor_url = null ) {
      if ( ! $vendor_name ) {
        $vendor_name = \esc_html($this->core->vendor_name);
      }

      if ( ! $vendor_url ) {
        $vendor_url = \esc_attr($this->core->vendor_url);
      }

      return sprintf(
        /*
         * translators:
         * %1$s: Vendor name, not translateable
         * %2$s: Vendor url, not translateable
         */
        __(
          'If you have already registered with %1$s, please choose "Production mode" and enter the credentials you received from %1$s. If you have not yet registered, please register at <a target="_blank" rel="noopener noreferrer" href="%2$s">%2$s</a>. If you wish to test the plugin before making a contract with %1$s, please choose "Test mode" and leave the API secret/key fields empty.',
          'woo-pakettikauppa'
        ),
        $vendor_name,
        $vendor_url
      );
    }

    public function setup_merchant_info() {
      return esc_html__(
        'Please fill the details of the merchant below. The information provided here will be used as the sender in shipping labels.',
        'woo-pakettikauppa'
      );
    }

    public function setup_shipping_info() {
      return sprintf(
        /*
         * translators:
         * %1$s: link to WooCommerce shipping zone setting page
         * %2$s: link to external WooCommerce documentation
         */
        __('Please configure the shipping methods of the currently active shipping zones to use Pakettikauppa shipping. Note that this plugin requires WooCommerce shipping zones and methods to be preconfigured in <a href="%1$s">WooCommerce > Settings > Shipping > Shipping zones</a>. For more information, visit <a target="_blank" href="%2$s">%2$s</a>.', 'woo-pakettikauppa'),
        esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')),
        esc_attr('https://docs.woocommerce.com/document/setting-up-shipping-zones/')
      );
    }

    public function setup_processing_info() {
      return esc_html__('Customize the order processing phase.', 'woo-pakettikauppa');
    }

    public function setup_ready_info() {
      return esc_html__('Congratulations, everything is now set up and you are now ready to start using the plugin!', 'woo-pakettikauppa');
    }

    public function setup_credentials() {
      return __('Credentials', 'woo-pakettikauppa');
    }

    public function setup_merchant() {
      return __('Merchant', 'woo-pakettikauppa');
    }

    public function setup_shipping() {
      return __('Shipping', 'woo-pakettikauppa');
    }

    public function setup_order_processing() {
      return __('Order Processing', 'woo-pakettikauppa');
    }

    public function setup_ready() {
      return __('Ready!', 'woo-pakettikauppa');
    }

    public function note() {
      return __('Note', 'woo-pakettikauppa');
    }

    public function mode() {
      return __('Mode', 'woo-pakettikauppa');
    }

    public function testing_environment() {
      return __('Testing environment', 'woo-pakettikauppa');
    }

    public function production_environment() {
      return __('Production environment', 'woo-pakettikauppa');
    }

    public function api_key_title() {
      return __('API key', 'woo-pakettikauppa');
    }

    public function api_key_desc( $vendor_name ) {
      return sprintf(
        /*
         * translators:
         * %1$s: Vendor name, not translatable
         */
        __('API key provided by %1$s', 'woo-pakettikauppa'),
        \esc_html($vendor_name)
      );
    }

    public function api_secret_title() {
      return __('API secret', 'woo-pakettikauppa');
    }

    public function api_secret_desc( $vendor_name ) {
      return sprintf(
        /*
         * translators:
         * %1$s: Vendor name, not translatable
         */
        __('API secret provided by %1$s', 'woo-pakettikauppa'),
        \esc_html($vendor_name)
      );
    }

    public function pickup_points_title() {
      return __('Shipping methods mapping', 'woo-pakettikauppa');
    }

    public function shipping_settings_title() {
      return __('Shipping settings', 'woo-pakettikauppa');
    }

    public function shipping_settings_desc() {
      return sprintf(
        /*
         * translators:
         * %1$s: WooCommerce URL, not translatable
         */
        __('You can activate new shipping method to checkout in <b>WooCommerce > Settings > Shipping > Shipping zones</b>. For more information, see <a target="_blank" href="%1$s">%1$s</a>', 'woo-pakettikauppa'),
        'https://docs.woocommerce.com/document/setting-up-shipping-zones/'
      );
    }

    public function add_tracking_link_to_email() {
      return __('Add tracking link to the order completed email', 'woo-pakettikauppa');
    }

    public function add_pickup_point_to_email() {
      return __('Add selected pickup point information to the order completed email', 'woo-pakettikauppa');
    }

    public function change_order_status_to() {
      return __('When creating shipping label change order status to', 'woo-pakettikauppa');
    }

    public function no_order_status_change() {
      return __('No order status change', 'woo-pakettikauppa');
    }

    public function create_shipments_automatically() {
      return __('Create shipping labels automatically', 'woo-pakettikauppa');
    }

    public function no_automatic_creation_of_labels() {
      return __('No automatic creation of shipping labels', 'woo-pakettikauppa');
    }

    public function when_order_status_is( $status ) {
      /* translators: order status, pretranslated */
      return sprintf(__('When order status is "%s"', 'woo-pakettikauppa'), $status);
    }

    public function download_type_of_labels_title() {
      return __('Print labels', 'woo-pakettikauppa');
    }

    public function download_type_of_labels_option_browser() {
      return __('Browser', 'woo-pakettikauppa');
    }

    public function download_type_of_labels_option_download() {
      return __('Download', 'woo-pakettikauppa');
    }

    public function post_shipping_label_to_url_title() {
      return __('Post shipping label to URL', 'woo-pakettikauppa');
    }

    public function post_shipping_label_to_url_desc() {
      return __('Plugin can upload shipping label to an URL when creating shipping label. Define URL if you want to upload PDF.', 'woo-pakettikauppa');
    }

    public function pickup_points_search_limit_title() {
      return __('Pickup point search limit', 'woo-pakettikauppa');
    }

    public function pickup_points_search_limit_desc() {
      return __('Limit the amount of nearest pickup points shown.', 'woo-pakettikauppa');
    }

    public function pickup_point_list_type_title() {
      return __('Show pickup points as', 'woo-pakettikauppa');
    }

    public function pickup_point_list_type_option_menu() {
      return __('Menu', 'woo-pakettikauppa');
    }

    public function pickup_point_list_type_option_list() {
      return __('List', 'woo-pakettikauppa');
    }

    public function store_owner_information() {
      return __('Store owner information', 'woo-pakettikauppa');
    }

    public function sender_name() {
      return __('Sender name', 'woo-pakettikauppa');
    }

    public function sender_address() {
      return __('Sender address', 'woo-pakettikauppa');
    }

    public function sender_postal_code() {
      return __('Sender postal code', 'woo-pakettikauppa');
    }

    public function sender_city() {
      return __('Sender city', 'woo-pakettikauppa');
    }

    public function info_code() {
      return __('Info-code for shipments', 'woo-pakettikauppa');
    }

    public function cod_settings() {
      return __('Cash on Delivery (COD) Settings', 'woo-pakettikauppa');
    }

    public function cod_iban() {
      return __('Bank account number for Cash on Delivery (IBAN)', 'woo-pakettikauppa');
    }

    public function cod_bic() {
      return __('BIC code for Cash on Delivery', 'woo-pakettikauppa');
    }

    public function advanced_settings() {
      return __('Advanced settings', 'woo-pakettikauppa');
    }

    public function show_shipping_method() {
      return __('Show Pakettikauppa shipping method', 'woo-pakettikauppa');
    }

    public function no_woo_error() {
      return __('WooCommerce Pakettikauppa requires WooCommerce to be installed and activated!', 'woo-pakettikauppa');
    }

    public function no_pickup_points_error() {
      return __('No pickup points were found. Check the address.', 'woo-pakettikauppa');
    }

    public function something_went_wrong_while_searching_pickup_points_error() {
      return __('An error occurred while searching for pickup points.', 'woo-pakettikauppa');
    }

    public function custom_pickup_point_desc() {
      return __('If none of your preferred pickup points are listed, fill in a custom address above and select another pickup point.', 'woo-pakettikauppa');
    }

    public function custom_pickup_point_title() {
      return __('Custom pickup address', 'woo-pakettikauppa');
    }

    public function pickup_point_title() {
      return __('Pickup address', 'woo-pakettikauppa');
    }

    public function fill_pickup_address_above() {
      return __('Search pickup points near you by typing your address above.', 'woo-pakettikauppa');
    }

    public function show_pickup_point_override_query() {
      return __('Show pickup point override in checkout', 'woo-pakettikauppa');
    }

    public function confirm_private_pickup_selection() {
      return __('The pickup point you\'ve chosen is not available for public access. Are you sure that you can retrieve the package?', 'woo-pakettikauppa');
    }
  }
}
