<?php

class WC_Settings_pakettikauppa extends WC_Settings_Page {
  public function __construct() {
    $this->id    = 'wc_pakettikauppa';
    $this->label = __( 'Pakettikauppa.fi', 'wc-pakettikauppa' );

    add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
    add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
    add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
    add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
  }

  /**
   * Get sections.
   *
   * @return array
   */
  public function get_sections() {
    $sections = array(
      ''         => __( 'Settings', 'wc-pakettikauppa' ),
      'methods'     => __( 'Shipping methods', 'wc-pakettikauppa' ),
    );

    return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
  }

  /**
   * Get settings array.
   *
   * @return array
   */
  public function get_settings() {
    global $current_section;

    if ( 'methods' === $current_section ) {
      $settings = apply_filters( 'woocommerce_' . $this->id . '_settings', $this->get_method_settings() );
    } else {
      $settings = apply_filters( 'woocommerce_' . $this->id . '_settings', array(
          array(
          'title' => __( 'Pakettikauppa.fi', 'wc-pakettikauppa' ),
          'type' => 'title',
          'id' => 'wc_pakettikauppa_options',
          ),

          array(
          'title'    => __( 'Mode', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_mode',
          'type'     => 'select',
          'default'  => 'test',
          'options'  => array(
            'test' => __( 'Test environment', 'wc-pakettikauppa' ),
            'production' => __( 'Production environment', 'wc-pakettikauppa' ),
          ),
          ),

          array(
          'title'    => __( 'Account number', 'wc-pakettikauppa' ),
          'desc'     => __( 'Account number provided by Pakettikauppa.fi', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_account_number',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
          ),

          array(
          'title'    => __( 'Secret key', 'wc-pakettikauppa' ),
          'desc'     => __( 'Secret key provided by Pakettikauppa.fi', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_secret_key',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
          ),

          array(
          'title'    => __( 'Sender name', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_sender_name',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'    => __( 'Sender address', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_sender_address',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'    => __( 'Sender postal code', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_sender_postal_code',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'    => __( 'Sender city', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_sender_city',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'    => __( 'Bank account number for Cash on Delivery (IBAN)', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_cod_iban',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'    => __( 'BIC code for Cash on Delivery', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_cod_bic',
          'type'     => 'text',
          'default'  => '',
          ),

          array(
          'title'     => __( 'Add tracking link to the order completed email', 'wc-pakettikauppa' ),
          'id'       => 'wc_pakettikauppa_add_tracking_to_email',
          'type'     => 'checkbox',
          'default'  => 'no',
          ),

          array( 'type' => 'sectionend', 'id' => 'wc_pakettikauppa_options' ),
      ) );
    }

    return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
  }

  public function get_method_settings() {
    $shipping_zones = array( new WC_Shipping_Zone( 0 ) );
    $shipping_zones = array_merge( $shipping_zones, WC_Shipping_Zones::get_zones() );

    $settings = array();

    $settings[] = array(
      'title' => __( 'Shipping Methods', 'wc-pakettikauppa' ),
      'desc' => __( 'You can link shipping methods to Pakettikauppa.fi services. By linking you don\'t have to select shipping service manually for order.', 'wc-pakettikauppa' ),
      'type' => 'title',
      'id' => 'wc_pakettikauppa_methods',
    );

    $options = WC_Pakettikauppa::services();
    $default = key( $options );

    foreach ( $shipping_zones as $shipping_zone ) {
      if ( is_array( $shipping_zone ) && isset( $shipping_zone['zone_id'] ) ) {
        $shipping_zone = WC_Shipping_Zones::get_zone( $shipping_zone['zone_id'] );
      } elseif ( ! is_object( $shipping_zone ) ) {
        // Skip
        continue;
      }

      $settings[] = array(
        'title' => $shipping_zone->get_zone_name(),
        'type' => 'title',
        'id' => 'wc_pakettikauppa_methods_zone_' . $shipping_zone->get_id(),
      );

      foreach ( $shipping_zone->get_shipping_methods() as $instance_id => $shipping_method ) {
        $settings[] = array(
          'title'    => $shipping_method->title,
          'id'       => 'wc_pakettikauppa_shipping_method_' . $instance_id,
          'type'     => 'select',
          'default'  => $default,
          'options'  => $options,
        );
      }

      $settings[] = array(
        'type' => 'sectionend',
        'id' => 'wc_pakettikauppa_methods_zone_' . $shipping_zone->get_id(),
      );
    }

    $settings[] = array(
      'type' => 'sectionend',
      'id' => 'wc_pakettikauppa_methods',
    );

    return $settings;
  }
}

return new WC_Settings_pakettikauppa();
