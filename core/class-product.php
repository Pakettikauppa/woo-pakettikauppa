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
      $tabs['pk_dangerous'] = array(
        'label'   => __('Dangerous goods', 'woo-pakettikauppa'),
        'target'  => 'pk_dangerous',
        'class'   => array( 'show_if_simple', 'show_if_variable' ),
      );

      return $tabs;
    }

    /**
     * Tabs styles
     */
    public function tabs_styles() {
      ?>
      <style>
        <?php foreach ( $this->tabs as $tab_id => $tab_params ) : ?>
          #woocommerce-product-data ul.wc-tabs li.<?php echo $tab_id; ?>_options a:before {
            font-family: WooCommerce;
            content: "<?php echo $tab_params['icon']; ?>";
          }
        <?php endforeach; ?>
      </style>
      <?php
    }

    /**
     * Tabs content
     */
    public function tabs_content() {
      foreach ( $this->tabs as $tab_id => $tab_params ) :
        ?>
        <div id="<?php echo $tab_id; ?>" class="panel woocommerce_options_panel">
          <?php foreach ( $tab_params['fields'] as $fields_group ) : ?>
            <div class='options_group'>
              <?php foreach ( $fields_group as $field ) : ?>
                <?php woocommerce_wp_text_input($field); ?>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php
      endforeach;
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

    /**
     * Prepare tabs data
     */
    private function get_tabs() {
      $tabs = array();

      // Tab: Options
      $tabs['pk_dangerous'] = array(
        'icon' => '\e016',
        'fields' => array(
          array(
            array(
              'id' => 'pk_dangerous_lqweight',
              'label' => __('Weight (kg)', 'woo-pakettikauppa'),
              'type' => 'number',
              'custom_attributes' => array(
                'min' => '0',
                'max' => '',
                'step'  => '0.001',
              ),
              'desc_tip' => true,
              'description' => __('Content of hazardous substances in the product', 'woo-pakettikauppa'),
            ),
          ),
        ),
      );

      return $tabs;
    }
  }
}
