<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Product') ) {
  class Product {
    /**
     * @var Core
     */
    public $core = null;

    /**
     * Global variables
     */
    private $tabs = array();

    /**
     * Constructor
     */
    public function __construct( Core $plugin ) {
      $this->core = $plugin;
      $this->tabs = $this->get_tabs();
    }

    /**
     * Load hooks
     */
    public function load() {
      add_filter('woocommerce_product_data_tabs', array( $this, 'add_product_tabs' ));
      add_filter('woocommerce_product_data_panels', array( $this, 'tabs_content' ));

      add_action('admin_head', array( $this, 'tabs_styles' ));
      add_action('woocommerce_process_product_meta_simple', array( $this, 'save_tabs_fields' ));
      add_action('woocommerce_process_product_meta_variable', array( $this, 'save_tabs_fields' ));

      foreach ( $this->tabs as $tab_id => $tab_params ) {
        if ( $this->is_new_tab($tab_id) ) {
          continue;
        }
        add_action(
          'woocommerce_product_options_' . $tab_id,
          function() use ( $tab_id ) {
            $this->get_group_content($tab_id);
          }
        );
      }
    }

    /**
     * Add a custom product tab
     *
     * @param array $tabs - Product current tabs
     */
    public function add_product_tabs( $tabs ) {
      /*$tabs[$this->core->prefix] = array( //Disabled, because not required
        'label'   => sprintf(__('%s options', 'woo-pakettikauppa'), $this->core->vendor_name),
        'target'  => $this->core->prefix . '_options',
        'class'   => array( 'show_if_simple', 'show_if_variable' ),
      );*/

      return $tabs;
    }

    /**
     * Tabs styles
     */
    public function tabs_styles() {
      ?>
      <style>
        <?php foreach ( $this->tabs as $tab_id => $tab_params ) : ?>
          <?php
          if ( ! $this->is_new_tab($tab_id) || empty($tab_params['icon']) ) {
            continue;
          }
          ?>
          
          #woocommerce-product-data ul.wc-tabs li.<?php echo $tab_id; ?>_options a:before {
            font-family: WooCommerce;
            content: "<?php echo $tab_params['icon']; ?>";
          }
        <?php endforeach; ?>
      </style>
      <?php
    }

    /**
     * New tabs content
     */
    public function tabs_content() {
      foreach ( $this->tabs as $tab_id => $tab_params ) :
        if ( ! $this->is_new_tab($tab_id) ) {
          continue;
        }
        ?>
        <div id="<?php echo $tab_id; ?>" class="panel woocommerce_options_panel">
          <?php $this->get_group_content($tab_id); ?>
        </div>
        <?php
      endforeach;
    }

    /**
     * Get all fields html for specific group
     *
     * @param string $tab_id - Tab (group) ID from get_tabs() function
     */
    public function get_group_content( $tab_id ) {
      foreach ( $this->tabs[$tab_id]['fields'] as $fields_group ) {
        ob_start();
        foreach ( $fields_group as $field ) {
          if ( $field['type'] == 'select' ) {
            woocommerce_wp_select($field);
          } else {
            woocommerce_wp_text_input($field);
          }
        }
        $all_fields_html = ob_get_clean();

        if ( $this->is_new_tab($tab_id) ) {
          echo '<div class="options_group">' . $all_fields_html . '</div>';
        } else {
          echo $all_fields_html;
        }
      }
    }

    /**
     * Save the custom fields
     *
     * @param integer $post_id - Post ID
     */
    public function save_tabs_fields( $post_id ) {
      foreach ( $this->tabs as $tab_id => $tab_params ) {
        foreach ( $tab_params['fields'] as $fields_group ) {
          foreach ( $fields_group as $field ) {
            switch ( $field['type'] ) {
              case 'checkbox':
                $value = isset($_POST[$field['id']]) ? 'yes' : 'no';
                break;
              case 'text':
                $value = isset($_POST[$field['id']]) ? $_POST[$field['id']] : '';
                break;
              case 'number':
                $value = isset($_POST[$field['id']]) ? abs($_POST[$field['id']]) : '';
                break;
              case 'select':
                $value = isset($_POST[$field['id']]) ? $_POST[$field['id']] : '';
                break;
              default:
                $value = '';
            }
            update_post_meta($post_id, $field['id'], $value);
          }
        }
      }
    }

    /**
     * Get product meta values added from product tabs
     *
     * @param integer $product_id - Post (product) ID
     */
    public function get_tabs_fields_values( $product_id ) {
      $fields_values = array();

      foreach ( $this->tabs as $tab_params ) {
        foreach ( $tab_params['fields'] as $fields_group ) {
          foreach ( $fields_group as $field ) {
            $fields_values[$field['id']] = get_post_meta($product_id, $field['id'], true);
          }
        }
      }

      return $fields_values;
    }

    public function calc_order_dangerous_goods( $order, $weight_unit = 'g' ) {
      $dangerous_goods = array(
        'weight' => 0,
        'count' => 0,
      );

      foreach ( $order->get_items() as $item ) {
        $dg_weight = $this->get_product_dg_weight($item->get_product_id());
        if ( $dg_weight > 0 ) {
          $dangerous_goods['weight'] += $dg_weight * $item->get_quantity();
          $dangerous_goods['count'] += $item->get_quantity();
        }
      }

      $dangerous_goods['weight'] = $this->change_number_unit($dangerous_goods['weight'], 'g', $weight_unit);

      return $dangerous_goods;
    }

    public function calc_selected_dangerous_goods( $selected_products, $weight_unit = 'g' ) {
      $dangerous_goods = array(
        'weight' => 0,
        'count' => 0,
      );

      foreach ( $selected_products as $product ) {
        $item_tabs_data = $this->get_tabs_fields_values($product['prod']);
        if ( ! empty($item_tabs_data[$this->core->params_prefix . 'dangerous_lqweight']) ) {
          $dangerous_goods['weight'] += $item_tabs_data[$this->core->params_prefix . 'dangerous_lqweight'] * $product['qty'];
          $dangerous_goods['count'] += $product['qty'];
        }
      }

      $dangerous_goods['weight'] = $this->change_number_unit($dangerous_goods['weight'], 'g', $weight_unit);

      return $dangerous_goods;
    }

    public function get_product_dg_weight( $product_id, $weight_unit = 'g' ) {
      $item_tabs_data = $this->get_tabs_fields_values($product_id);
      $item_data_key = $this->core->params_prefix . 'dangerous_lqweight';
      if ( ! empty($item_tabs_data[$item_data_key]) ) {
        return $this->change_number_unit($item_tabs_data[$item_data_key], 'g', $weight_unit);
      }

      return 0;
    }

    public function change_number_unit( $value, $current_unit, $new_unit ) {
      $to_kg = array(
        'mg' => 0.000001,
        'g' => 0.001,
        'kg' => 1,
        't' => 1000,
        'gr' => 0.0000648,
        'k' => 0.0002,
        'oz' => 0.02835,
        'lb' => 0.45359,
        'cnt' => 100,
      );

      if ( isset($to_kg[$current_unit]) && isset($to_kg[$new_unit]) ) {
        $current_kg = $value * $to_kg[$current_unit];
        return $current_kg / $to_kg[$new_unit];
      }

      return $value;
    }

    /**
     * Check if tab is creating or trying use something from existing
     *
     * @param string $tab_id - Tab ID from get_tabs() function
     */
    private function is_new_tab( $tab_id ) {
      $new_tabs = $this->add_product_tabs(array());

      return (isset($new_tabs[$tab_id])) ? true : false;
    }

    /**
     * Prepare tabs data
     */
    private function get_tabs() {
      $tabs = array();

      $wc_countries = new \WC_Countries();
      $all_countries = $wc_countries->get_countries();

      //Tab: Shipping
      $tabs['shipping'] = array(
        'fields' => array(
          array(
            array(
              'id' => $this->core->params_prefix . 'tariff_codes',
              'label' => __('HS tariff number', 'woo-pakettikauppa'),
              'type' => 'text',
              'desc_tip' => true,
              'description' => __('The HS tariff number must be based on the Harmonized Commodity Description and Coding System developed by the World Customs Organization.', 'woo-pakettikauppa'),
            ),
            array(
              'id' => $this->core->params_prefix . 'country_of_origin',
              'label' => __('Country of origin', 'woo-pakettikauppa'),
              'type' => 'select',
              'options' => array( '' => '— ' . __('Unknown', 'woo-pakettikauppa') . ' —' ) + $all_countries,
              'desc_tip' => true,
              'description' => __('The country where the goods originated, e.g. were produced/manufactured or assembled.', 'woo-pakettikauppa'),
            ),
            array(
              'id' => $this->core->params_prefix . 'dangerous_lqweight',
              'label' => __('DG weight (g)', 'woo-pakettikauppa'),
              'type' => 'number',
              'custom_attributes' => array(
                'min' => '0',
                'max' => '',
                'step'  => '1',
              ),
              'desc_tip' => true,
              'description' => __('Amount of hazardous subtances in the product as grams.', 'woo-pakettikauppa'),
            ),
          ),
        ),
      );

      return $tabs;
    }
  }
}
