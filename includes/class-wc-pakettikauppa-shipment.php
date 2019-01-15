<?php
/**
 * Shipment module.
 */

// Prevent direct access to this script
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_PAKETTIKAUPPA_DIR . 'vendor/autoload.php';

use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Client;

/**
 * WC_Pakettikauppa_Shipment Class
 *
 * @class WC_Pakettikauppa_Shipment
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
class WC_Pakettikauppa_Shipment {

	/**
	 * @var Client
	 */
	private $wc_pakettikauppa_client = null;
	private $wc_pakettikauppa_settings = null;

	public function __construct() {
		$this->id = 'wc_pakettikauppa_shipment';
	}

	public function load() {
		// Use option from database directly as WC_Pakettikauppa_Shipping_Method object is not accessible here
		$settings = get_option( 'woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null );

		if ( $settings === false ) {
			throw new Exception(
				'WooCommerce Pakettikauppa: woocommerce_WC_Pakettikauppa_Shipping_Method_settings was not found in the database!'
			);
		}

		$this->wc_pakettikauppa_settings = $settings;

		$account_number = $settings['account_number'];
		$secret_key     = $settings['secret_key'];
		$mode           = $settings['mode'];
		$is_test_mode   = ( $mode === 'production' ? false : true );

		$options_array = array(
			'api_key'   => $account_number,
			'secret'    => $secret_key,
			'test_mode' => $is_test_mode,
		);

		$this->wc_pakettikauppa_client = new Pakettikauppa\Client( $options_array );
		$this->wc_pakettikauppa_client->setComment( 'From WooCommerce' );
	}

	/**
	 * Get the status of a shipment
	 *
	 * @param int $post_id The post id of the order to update status of
	 *
	 * @return int The status code of the shipment
	 */
	public function get_shipment_status( $post_id ) {
		$tracking_code = get_post_meta( $post_id, '_wc_pakettikauppa_tracking_code', true );

		if ( ! empty( $tracking_code ) ) {
			$result = $this->wc_pakettikauppa_client->getShipmentStatus( $tracking_code );

			$data = json_decode( $result );

			if ( ! empty( $data ) && isset( $data[0] ) ) {
				return $data[0]->{'status_code'};
			}

			return '';
		}
	}

	/**
	 * Create Pakettikauppa shipment from order
	 *
	 * @param WC_Order $order
	 *
	 * @return array Shipment details
	 * @throws Exception
	 */
	public function create_shipment( $order ) {
		$shipment   = new Shipment();
		$service_id = get_post_meta( $order->get_id(), '_wc_pakettikauppa_service_id', true );

		$shipment->setShippingMethod( $service_id );

		$sender = new Sender();
		$sender->setName1( $this->wc_pakettikauppa_settings['sender_name'] );
		$sender->setAddr1( $this->wc_pakettikauppa_settings['sender_address'] );
		$sender->setPostcode( $this->wc_pakettikauppa_settings['sender_postal_code'] );
		$sender->setCity( $this->wc_pakettikauppa_settings['sender_city'] );
		$sender->setCountry( 'FI' );
		$shipment->setSender( $sender );

		$receiver = new Receiver();
		$receiver->setName1( $order->get_formatted_shipping_full_name() );
		$receiver->setAddr1( $order->get_shipping_address_1() );
		$receiver->setAddr2( $order->get_shipping_address_2() );
		$receiver->setPostcode( $order->get_shipping_postcode() );
		$receiver->setCity( $order->get_shipping_city() );
		$receiver->setCountry( ( $order->get_shipping_country() == null ? 'FI' : $order->get_shipping_country() ) );
		$receiver->setEmail( $order->get_billing_email() );
		$receiver->setPhone( $order->get_billing_phone() );
		$shipment->setReceiver( $receiver );

		$info = new Info();
		$info->setReference( $order->get_order_number() );
		$info->setCurrency( get_woocommerce_currency() );
		$shipment->setShipmentInfo( $info );

		$parcel = new Parcel();
		$parcel->setWeight( self::order_weight( $order ) );
		$parcel->setVolume( self::order_volume( $order ) );

		if ( ! empty( $this->wc_pakettikauppa_settings['info_code'] ) ) {
			$parcel->setInfocode( $this->wc_pakettikauppa_settings['info_code'] );
		}
		$shipment->addParcel( $parcel );

		$pickup_point_id = $order->get_meta( '_pakettikauppa_pickup_point_id' );

		if ( 'cod' === $order->get_payment_method() ) {
			$additional_service = new AdditionalService();
			$additional_service->setServiceCode( 3101 );

			$additional_service->addSpecifier( 'amount', $order->get_total() );
			$additional_service->addSpecifier( 'account', $this->wc_pakettikauppa_settings['cod_iban'] );
			$additional_service->addSpecifier( 'codbic', $this->wc_pakettikauppa_settings['cod_bic'] );
			$additional_service->addSpecifier( 'reference', $this->calculate_reference( $order->get_id() ) );
		}

		if ( ! empty( $pickup_point_id ) ) {
			$shipment->setPickupPoint( $pickup_point_id );
		}

		try {
			$this->wc_pakettikauppa_client->createTrackingCode( $shipment );
			$tracking_code = $shipment->getTrackingCode()->__toString();
		} catch ( Exception $e ) {
			/* translators: %s: Error message */
			throw new Exception( wp_sprintf( __( 'WooCommerce Pakettikauppa: tracking code creation failed: %s', 'wc-pakettikauppa' ), $e->getMessage() ) );
		}

		return $tracking_code;

	}

	public function fetch_shipping_label( $tracking_code ) {
		return $this->wc_pakettikauppa_client->fetchShippingLabels( array( $tracking_code ) );
	}

	/**
	 * Return pickup points near a location specified by the parameters.
	 *
	 * @param int $postcode The postcode of the pickup point
	 * @param string $street_address The street address of the pickup point
	 * @param string $country The country in which the pickup point is located
	 * @param string $service_provider A service that should be provided by the pickup point
	 *
	 * @return array The pickup points based on the parameters, or empty array if none were found
	 * @throws Exception
	 */
	public function get_pickup_points( $postcode, $street_address = null, $country = null, $service_provider = null ) {
		$pickup_point_limit = 5; // Default limit value for pickup point search

		if ( isset( $this->wc_pakettikauppa_settings['pickup_points_search_limit'] ) && ! empty( $this->wc_pakettikauppa_settings['pickup_points_search_limit'] ) ) {
			$pickup_point_limit = intval( $this->wc_pakettikauppa_settings['pickup_points_search_limit'] );
		}

		$pickup_point_data = $this->wc_pakettikauppa_client->searchPickupPoints( trim( $postcode ), trim( $street_address ), trim( $country ), $service_provider, $pickup_point_limit );
		if ( $pickup_point_data === 'Bad request' ) {
			throw new Exception( __( 'WC_Pakettikauppa: An error occured when searching pickup points.', 'wc-pakettikauppa' ) );
		}

		return $pickup_point_data;
	}

	/**
	 * Get all available shipping services.
	 *
	 * @param bool $admin_page
	 *
	 * @return array Available shipping services
	 */
	public function services( $admin_page = false ) {
		$services = array();

		$all_shipping_methods = $this->get_shipping_methods();

		// List all available methods as shipping options on checkout page
		if ( $all_shipping_methods == null ) {
			// returning null seems to invalidate services cache
			return null;
		}

		foreach ( $all_shipping_methods as $shipping_method ) {
			$services[ $shipping_method->shipping_method_code ] = sprintf( '%1$s %2$s', $shipping_method->service_provider, $shipping_method->name );
		}

		ksort( $services );

		return $services;
	}

	/**
	 * Fetch shipping methods from the Pakettikauppa and returns it as objects
	 *
	 * @param boolean $fromCache should we try to fetch results from cache?
	 *
	 * @return mixed
	 */
	private function get_shipping_methods() {
		$transient_name = 'wc_pakettikauppa_shipping_methods';
		$transient_time = 86400; // 24 hours

		$all_shipping_methods = get_transient( $transient_name );

		if (empty( $all_shipping_methods ) ) {
			$all_shipping_methods = json_decode( $this->wc_pakettikauppa_client->listShippingMethods() );

			if ( ! empty( $all_shipping_methods ) ) {
				set_transient( $transient_name, $all_shipping_methods, $transient_time );
			}
		}

		if ( empty( $all_shipping_methods ) ) {
			return null;
		}

		return $all_shipping_methods;
	}

	/**
	 * Get the title of a service by providing its code.
	 *
	 * @param int $service_code The code of a service
	 *
	 * @return string The service title matching with the provided code, or false if not found
	 */
	public function service_title( $service_code ) {
		$services = $this->services();
		if ( isset( $services[ $service_code ] ) ) {
			return $services[ $service_code ];
		}

		return false;
	}

	/**
	 * Get the provider of a service by providing its code.
	 *
	 * @param int $service_code The code of a service
	 *
	 * @return string The service provider matching with the provided code, or false if not found
	 */
	public function service_provider( $service_code ) {
		$all_shipping_methods = $this->get_shipping_methods();

		if ( $all_shipping_methods == null ) {
			return false;
		}

		foreach ( $all_shipping_methods as $shipping_method ) {
			if ( $service_code == $shipping_method->shipping_method_code ) {
				return $shipping_method->service_provider;
			}
		}

		return false;
	}

	/**
	 * Get the status text of a shipment that matches a specified status code.
	 *
	 * @param int $status_code A status code
	 *
	 * @return string The status text matching the provided code, or unknown status if the
	 * code is unknown.
	 */
	public static function get_status_text( $status_code ) {
		switch ( intval( $status_code ) ) {
			case 13:
				$status = __( 'Item is collected from sender - picked up', 'wc-pakettikauppa' );
				break;
			case 20:
				$status = __( 'Exception', 'wc-pakettikauppa' );
				break;
			case 22:
				$status = __( 'Item has been handed over to the recipient', 'wc-pakettikauppa' );
				break;
			case 31:
				$status = __( 'Item is in transport', 'wc-pakettikauppa' );
				break;
			case 38:
				$status = __( 'C.O.D payment is paid to the sender', 'wc-pakettikauppa' );
				break;
			case 45:
				$status = __( 'Informed consignee of arrival', 'wc-pakettikauppa' );
				break;
			case 48:
				$status = __( 'Item is loaded onto a means of transport', 'wc-pakettikauppa' );
				break;
			case 56:
				$status = __( 'Item not delivered – delivery attempt made', 'wc-pakettikauppa' );
				break;
			case 68:
				$status = __( 'Pre-information is received from sender', 'wc-pakettikauppa' );
				break;
			case 71:
				$status = __( 'Item is ready for delivery transportation', 'wc-pakettikauppa' );
				break;
			case 77:
				$status = __( 'Item is returning to the sender', 'wc-pakettikauppa' );
				break;
			case 91:
				$status = __( 'Item is arrived to a post office', 'wc-pakettikauppa' );
				break;
			case 99:
				$status = __( 'Outbound', 'wc-pakettikauppa' );
				break;
			default:
				/* translators: %s: Status code */
				$status = wp_sprintf( __( 'Unknown status: %s', 'wc-pakettikauppa' ), $status_code );
				break;
		}

		return $status;
	}

	/**
	 * Calculate the total shipping weight of an order.
	 *
	 * @param WC_Order $order The order to calculate the weight of
	 *
	 * @return int The total weight of the order
	 */
	public static function order_weight( $order ) {
		$weight = 0;

		foreach ( $order->get_items() as $item ) {
			if ( empty( $item['product_id'] ) ) {
				continue;
			}

			$product = $order->get_product_from_item( $item );

			if ( $product->is_virtual() ) {
				continue;
			}

			if ( ! is_numeric( $product->get_weight() ) ) {
				continue;
			}

			$weight += wc_get_weight( $product->get_weight() * $item['qty'], 'kg' );
		}

		return $weight;
	}

	/**
	 * Calculate the total shipping volume of an order in cubic meters.
	 *
	 * @param WC_Order $order The order to calculate the volume of
	 *
	 * @return int The total volume of the order (m^3)
	 */
	public static function order_volume( $order ) {
		$volume = 0;

		foreach ( $order->get_items() as $item ) {
			if ( empty( $item['product_id'] ) ) {
				continue;
			}

			$product = $order->get_product_from_item( $item );

			if ( $product->is_virtual() ) {
				continue;
			}

			if ( ! is_numeric( $product->get_width() )
			     || ! is_numeric( $product->get_height() )
			     || ! is_numeric( $product->get_length() ) ) {
				continue;
			}

			// Ensure that the volume is in metres
			$woo_dim_unit = strtolower( get_option( 'woocommerce_dimension_unit' ) );
			switch ( $woo_dim_unit ) {
				case 'mm':
					$dim_multiplier = 0.001;
					break;
				case 'cm':
					$dim_multiplier = 0.01;
					break;
				case 'dm':
					$dim_multiplier = 0.1;
					break;
				default:
					$dim_multiplier = 1;
			}
			// Calculate total volume
			$volume += pow( $dim_multiplier, 3 ) * $product->get_width() * $product->get_height() * $product->get_length() * $item['qty'];
		}

		return $volume;
	}

	/**
	 * Get the full-length tracking url of a shipment by providing its service id and tracking code.
	 * Use tracking url provided by pakettikauppa.fi.
	 *
	 * @param int $service_id The id of the service that is used for the shipment
	 * @param int $tracking_code The tracking code of the shipment
	 *
	 * @return string The full tracking url for the order
	 */
	public static function tracking_url( $tracking_code ) {
		$tracking_url = 'https://www.pakettikauppa.fi/seuranta/?' . $tracking_code;

		return $tracking_url;
	}

	/**
	 * Calculate Finnish invoice reference from order ID
	 * http://tarkistusmerkit.teppovuori.fi/tarkmerk.htm#viitenumero
	 *
	 * @param string $id The id of the order to calculate the reference of
	 *
	 * @return int The reference number calculated from the id
	 */
	public static function calculate_reference( $id ) {
		$weights = array( 7, 3, 1 );
		$sum     = 0;

		$base                 = str_split( strval( ( $id ) ) );
		$reversed_base        = array_reverse( $base );
		$reversed_base_length = count( $reversed_base );

		for ( $i = 0; $i < $reversed_base_length; $i ++ ) {
			$sum += $reversed_base[ $i ] * $weights[ $i % 3 ];
		}

		$checksum = ( 10 - $sum % 10 ) % 10;

		$reference = implode( '', $base ) . $checksum;

		return $reference;
	}

	/**
	 * Return the default shipping service if none has been specified
	 *
	 * @TODO: Does this method really need $post or $order, as the default service should
	 * not be order-specific?
	 */
	public static function get_default_service() {
		// @TODO: Maybe use an option in database so the merchant can set it in settings
		$service = '2103';

		return $service;
	}

	/**
	 * Validate order details in wp-admin. Especially useful, when creating orders in wp-admin,
	 *
	 * @param WC_Order $order The order that needs its info to be validated
	 *
	 * @return True, if the details where valid, or false if not
	 */
	public static function validate_order_shipping_receiver( $order ) {
		// Check shipping info first
		$no_shipping_name     = (bool) empty( $order->get_formatted_shipping_full_name() );
		$no_shipping_address  = (bool) empty( $order->get_shipping_address_1() ) && empty( $order->get_shipping_address_2() );
		$no_shipping_postcode = (bool) empty( $order->get_shipping_postcode() );
		$no_shipping_city     = (bool) empty( $order->get_shipping_city() );

		if ( $no_shipping_name || $no_shipping_address || $no_shipping_postcode || $no_shipping_city ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns information if this shipping service supports pickup points
	 *
	 * @param $service_id
	 *
	 * @return bool
	 */
	public function service_has_pickup_points( $service_id ) {
		$all_shipping_methods = $this->get_shipping_methods();

		if ( $all_shipping_methods == null ) {
			return false;
		}

		foreach ( $all_shipping_methods as $shipping_method ) {
			if ( $shipping_method->shipping_method_code == $service_id ) {
				return $shipping_method->has_pickup_points;
			}
		}
	}
}
