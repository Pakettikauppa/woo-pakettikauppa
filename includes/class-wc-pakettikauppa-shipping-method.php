<?php

// Prevent direct access to the script
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/class-wc-pakettikauppa.php';
require_once plugin_dir_path( __FILE__ ) . '/class-wc-pakettikauppa-shipment.php';

/**
 * Pakettikauppa_Shipping_Method Class
 *
 * @class Pakettikauppa_Shipping_Method
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
function wc_pakettikauppa_shipping_method_init() {

	if ( ! class_exists( 'WC_Pakettikauppa_Shipping_Method' ) ) {

		class WC_Pakettikauppa_Shipping_Method extends WC_Shipping_Method {
			/**
			 * Required to access Pakettikauppa client
			 * @var WC_Pakettikauppa_Shipment $wc_pakettikauppa_shipment
			 */
			private $wc_pakettikauppa_shipment = null;

			/**
			 * Default shipping fee
			 *
			 * @var string
			 */
			public $fee = 5.95;

			/**
			 * Constructor for Pakettikauppa shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct( $instance_id = 0 ) {
				parent::__construct( $instance_id );

				$this->id = 'pakettikauppa_shipping_method'; // ID for your shipping method. Should be unique.

				$this->method_title       = 'Pakettikauppa'; // Title shown in admin
				$this->method_description = __( 'All shipping methods with one contract. For more information visit <a href="https://www.pakettikauppa.fi/">Pakettikauppa</a>.', 'wc-pakettikauppa' ); // Description shown in admin

				$this->supports = array(
					'shipping-zones',
					'instance-settings',
					'settings',
					'instance-settings-modal',
				);

				// Make Pakettikauppa API accessible via WC_Pakettikauppa_Shipment
				$this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
				$this->wc_pakettikauppa_shipment->load();


				$this->init();

				// Save settings in admin if you have any defined
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array(
					$this,
					'process_admin_options'
				) );

			}

			/**
			 * Initialize Pakettikauppa shipping
			 */
			public function init() {
				$this->instance_form_fields = $this->my_instance_form_fields();
				$this->form_fields          = $this->my_global_form_fields();
				$this->title                = $this->get_option( 'title' );
			}

			/**
			 * Initialize form fields
			 */
			private function my_instance_form_fields() {

				$all_shipping_methods = $this->wc_pakettikauppa_shipment->services();

				if ( empty( $all_shipping_methods ) ) {
					$fields = array(
						'title' => array(
							'title'       => __( 'Title', 'woocommerce' ),
							'type'        => 'text',
							'description' => __( 'Can not connect to Pakettikauppa server - please check Pakettikauppa API credentials, servers error log and firewall settings.', 'wc-pakettikauppa' ),
							'default'     => 'Pakettikauppa',
							'desc_tip'    => true,
						)
					);

					return $fields;
				}
				$fields = array(
					'title' => array(
						'title'       => __( 'Title', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
						'default'     => 'Pakettikauppa',
						'desc_tip'    => true,
					),
					/* Start new section */
					array(
						'title' => __( 'Shipping methods', 'wc-pakettikauppa' ),
						'type'  => 'title',
					),

					'shipping_method' => array(
						'title'   => __( 'Shipping method', 'wc-pakettikauppa' ),
						'type'    => 'select',
						'options' => $all_shipping_methods,
					),


					array(
						'title'       => __( 'Shipping class costs', 'woocommerce' ),
						'type'        => 'title',
						'default'     => '',
						/* translators: %s: URL for link. */
						'description' => sprintf( __( 'These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ),
					),
				);

				$shipping_classes = WC()->shipping->get_shipping_classes();

				if ( ! empty( $shipping_classes ) ) {
					foreach ( $shipping_classes as $shipping_class ) {
						if ( ! isset( $shipping_class->term_id ) ) {
							continue;
						}

						$fields[] = array(
							'title'   => sprintf( __( '"%s" shipping class cost', 'woocommerce' ), esc_html( $shipping_class->name ) ),
							'type'    => 'title',
							'default' => '',
						);

						$fields[ 'class_cost_' . $shipping_class->term_id . '_price' ] = array(
							/* translators: %s: shipping class name */
							'title'       => __( 'Price (vat included)', 'wc-pakettikauppa' ),
							'type'        => 'number',
							'default'     => null,
							'placeholder' => __( 'N/A', 'woocommerce' ),
							'description' => __( 'Shipping cost', 'wc-pakettikauppa' ),
							'desc_tip'    => true,
						);

						$fields[ 'class_cost_' . $shipping_class->term_id . '_price_free' ] = array(
							'title'       => __( 'Free shipping tier', 'wc-pakettikauppa' ),
							'type'        => 'number',
							'default'     => null,
							'description' => __( 'After which amount shipping is free.', 'wc-pakettikauppa' ),
							'desc_tip'    => true,
						);
					}

					$fields['type'] = array(
						'title'   => __( 'Calculation type', 'woocommerce' ),
						'type'    => 'select',
						'class'   => 'wc-enhanced-select',
						'default' => 'class',
						'options' => array(
							'class' => __( 'Per class: Charge shipping for each shipping class individually', 'woocommerce' ),
							'order' => __( 'Per order: Charge shipping for the most expensive shipping class', 'woocommerce' ),
						),
					);

				}

				$fields[] = array(
					'title'   => __( 'Default shipping class cost', 'wc-pakettikauppa' ),
					'type'    => 'title',
					'default' => '',
				);

				$fields['price'] = array(
					'title'       => __( 'No shipping class cost', 'woocommerce' ),
					'type'        => 'number',
					'default'     => $this->fee,
					'placeholder' => __( 'N/A', 'woocommerce' ),
					'description' => __( 'Shipping cost  (vat included)', 'wc-pakettikauppa' ),
					'desc_tip'    => true,
				);

				$fields['price_free'] = array(
					'title'       => __( 'Free shipping tier', 'wc-pakettikauppa' ),
					'type'        => 'number',
					'default'     => '',
					'description' => __( 'After which amount shipping is free.', 'wc-pakettikauppa' ),
					'desc_tip'    => true,
				);


				return $fields;
			}

			private function my_global_form_fields() {
				return array(
					'mode' => array(
						'title'   => __( 'Mode', 'wc-pakettikauppa' ),
						'type'    => 'select',
						'default' => 'test',
						'options' => array(
							'test'       => __( 'Testing environment', 'wc-pakettikauppa' ),
							'production' => __( 'Production environment', 'wc-pakettikauppa' ),
						),
					),

					'account_number' => array(
						'title'    => __( 'API key', 'wc-pakettikauppa' ),
						'desc'     => __( 'API key provided by Pakettikauppa', 'wc-pakettikauppa' ),
						'type'     => 'text',
						'default'  => '',
						'desc_tip' => true,
					),

					'secret_key' => array(
						'title'    => __( 'API secret', 'wc-pakettikauppa' ),
						'desc'     => __( 'API Secret provided by Pakettikauppa', 'wc-pakettikauppa' ),
						'type'     => 'text',
						'default'  => '',
						'desc_tip' => true,
					),

					/* Start new section */
					array(
						'title' => __( 'Shipping settings', 'wc-pakettikauppa' ),
						'type'  => 'title',
					),

					'add_tracking_to_email' => array(
						'title'   => __( 'Add tracking link to the order completed email', 'wc-pakettikauppa' ),
						'type'    => 'checkbox',
						'default' => 'no',
					),

					'pickup_points_search_limit' => array(
						'title'       => __( 'Pickup point search limit', 'wc-pakettikauppa' ),
						'type'        => 'number',
						'default'     => 5,
						'description' => __( 'Limit the amount of nearest pickup points shown.', 'wc-pakettikauppa' ),
						'desc_tip'    => true,
					),

					array(
						'title' => __( 'Store owner information', 'wc-pakettikauppa' ),
						'type'  => 'title',
					),

					'sender_name' => array(
						'title'   => __( 'Sender name', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'sender_address' => array(
						'title'   => __( 'Sender address', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'sender_postal_code' => array(
						'title'   => __( 'Sender postal code', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'sender_city' => array(
						'title'   => __( 'Sender city', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'cod_iban' => array(
						'title'   => __( 'Bank account number for Cash on Delivery (IBAN)', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'cod_bic' => array(
						'title'   => __( 'BIC code for Cash on Delivery', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),

					'info_code' => array(
						'title'   => __( 'Info-code for shipments', 'wc-pakettikauppa' ),
						'type'    => 'text',
						'default' => '',
					),
				);
			}

			/**
			 * Mostly copy-pasted from WooCommerce:
			 *   woocommerce/includes/abstracts/abstract-wc-shipping-method.php
			 *   protected function get_taxes_per_item( $costs ) and edited it A LOT.
			 *
			 * @param $shippingCost
			 *
			 * @return array
			 */
			private function calculate_shipping_tax( $shippingCost ) {
				$taxes = array();

				$taxesTotal = 0;
				$cartObj    = WC()->cart;
				$cart_total = $cartObj->get_cart_contents_total();

				$cart = $cartObj->get_cart();

				foreach ( $cart as $item ) {
					$cost_key = $item['key'];

					$costItem = $shippingCost * $item['line_total'] / $cart_total;

					$taxObj = WC_Tax::get_shipping_tax_rates( $cart[ $cost_key ]['data']->get_tax_class() );

					foreach ( $taxObj as $key => $value ) {
						if ( ! isset( $taxes[ $key ] ) ) {
							$taxes[ $key ] = 0.0;
						}
						$taxes[ $key ] += round( $costItem - $costItem / ( 1 + $value['rate'] / 100.0 ), 2 );
					}
				}

				foreach ( $taxes as $_tax ) {
					$taxesTotal += $_tax;
				}

				return array(
					'total' => $taxesTotal,
					'taxes' => $taxes,
				);
			}

			/**
			 * Finds and returns shipping classes and the products with said class.
			 *
			 * @param mixed $package Package of items from cart.
			 *
			 * @return array
			 */
			private function find_shipping_classes( $package ) {
				$found_shipping_classes = array();

				foreach ( $package['contents'] as $item_id => $values ) {
					if ( $values['data']->needs_shipping() ) {
						$found_class = $values['data']->get_shipping_class();

						if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
							$found_shipping_classes[ $found_class ] = array();
						}

						$found_shipping_classes[ $found_class ][ $item_id ] = $values;
					}
				}

				return $found_shipping_classes;
			}

			private function get_shipping_cost( $cart_total, $key_base = '' ) {
				if ( $key_base != '' ) {
					$key_base = "class_cost_{$key_base}_";
				}

				$shipping_cost = $this->get_option( $key_base . 'price', -1 );

				if ( $shipping_cost < 0 ) {
					$shipping_cost = null;
				}

				if ( $this->get_option( $key_base . 'price_free', 0 ) <= $cart_total && $this->get_option( $key_base . 'price_free', 0 ) > 0 ) {
					$shipping_cost = 0;
				}

				return $shipping_cost;
			}

			/**
			 * Call to calculate shipping rates for this method.
			 * Rates can be added using the add_rate() method.
			 * Return only active shipping methods.
			 *
			 * Part doing the calculation of shipping classes is copied from flatrate shipping module and edited to
			 * fit and work with this code.
			 *
			 * @uses WC_Shipping_Method::add_rate()
			 *
			 * @param array $package Shipping package.
			 */
			public function calculate_shipping( $package = array() ) {
				$cart = WC()->cart;

				$cart_total = $cart->get_cart_contents_total() + $cart->get_cart_contents_tax();

				$service_code = $this->get_option( 'shipping_method' );

				$shipping_cost = null;

				$shipping_classes = WC()->shipping->get_shipping_classes();

				if ( ! empty( $shipping_classes ) ) {
					$found_shipping_classes = $this->find_shipping_classes( $package );
					$highest_class_cost     = 0;

					foreach ( $found_shipping_classes as $shipping_class => $products ) {
						$shipping_zone = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );

						$class_shipping_cost = $this->get_shipping_cost( $cart_total, $shipping_zone->term_id );

						if ( $class_shipping_cost !== null ) {
							if ( $shipping_cost === null ) {
								$shipping_cost = 0;
							}

							if ( 'class' === $this->get_option( 'type' ) ) {
								$shipping_cost += $class_shipping_cost;
							} else {
								$highest_class_cost = $class_shipping_cost > $highest_class_cost ? $class_shipping_cost : $highest_class_cost;
							}
						}
					}

					if ( 'order' === $this->get_option( 'type' ) && $highest_class_cost ) {
						$shipping_cost += $highest_class_cost;
					}
				}

				if ( $shipping_cost === null ) {
					$shipping_cost = $this->get_shipping_cost( $cart_total );
				}

				$taxes = $this->calculate_shipping_tax( $shipping_cost );

				$shipping_cost = $shipping_cost - $taxes['total'];

				$service_title = $this->get_option( 'title' );

				$this->add_rate(
					array(
						'meta_data' => [ 'service_code' => $service_code ],
						'label'     => $service_title,
						'cost'      => (string) $shipping_cost,
						'taxes'     => $taxes['taxes'],
						'package'   => $package,
					)
				);
			}

			public function process_admin_options() {
				if ( ! $this->instance_id ) {
					delete_transient( 'wc_pakettikauppa_shipping_methods' );
				}

				return parent::process_admin_options();
			}
		}
	}
}

add_action( 'woocommerce_shipping_init', 'wc_pakettikauppa_shipping_method_init' );

function add_wc_pakettikauppa_shipping_method( $methods ) {
	$methods['pakettikauppa_shipping_method'] = 'WC_Pakettikauppa_Shipping_Method';

	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_wc_pakettikauppa_shipping_method' );
