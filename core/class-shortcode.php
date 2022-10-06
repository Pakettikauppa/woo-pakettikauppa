<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Shortcode') ) {
  class Shortcode {
    /**
     * @var Core
     */
    public $core = null;

    /**
     * Internal variables
     */
    private $shipment = null;

    /**
     * Constructor
     */
    public function __construct( Core $plugin ) {
      $this->core = $plugin;
    }

    /**
     * Load hooks
     */
    public function load() {
      add_shortcode($this->core->params_prefix . 'tracking', array( $this, 'tracking_info' ));

      $this->shipment = $this->core->shipment;
    }

    /**
     * Shortcode: Show tracking code or URL
     *
     * @param array $attributes Shortcode attributes
     *
     * @property int $order Order number
     * @property string $separator Separator between elements
     * @property string $show Tracking code/URL output type. Available values: code, url, link.
     * @property boolean $new_tab Open link to new tab
     *
     * @return string Shortcode content
     */
    public function tracking_info( $attributes ) {
      $default_attributes = array(
        'order' => '',
        'separator' => '<br/>',
        'show' => 'code',
        'new_tab' => 'true',
      );
      $atts = shortcode_atts($default_attributes, $attributes);

      if ( empty($atts['order']) ) {
        return '';
      }

      try {
        $order = new \WC_Order(esc_attr($atts['order']));
        $tracking_codes = $this->shipment->get_labels($order->get_id());
      } catch ( \Exception $e ) {
        return '';
      }
      if ( empty($tracking_codes) ) {
        return '';
      }

      $output = '';
      foreach ( $tracking_codes as $code_data ) {
        if ( ! empty($output) ) {
          $output .= $atts['separator'];
        }
        switch ( strtolower($atts['show']) ) {
          case 'code':
            $output .= $code_data['tracking_code'];
            break;
          case 'url':
            $output .= $code_data['tracking_url'];
            break;
          case 'link':
            $output .= '<a href="' . esc_url($code_data['tracking_url']) . '"';
            if ( filter_var($atts['new_tab'], FILTER_VALIDATE_BOOLEAN) ) {
              $output .= ' target="_blank"';
            }
            $output .= '>' . $code_data['tracking_code'] . '</a>';
            break;
        }
      }

      return $output;
    }
  }
}
