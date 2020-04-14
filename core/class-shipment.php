<?php

namespace Woo_Pakettikauppa_Core;

/**
 * Shipment module.
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use Pakettikauppa\Shipment as PK_Shipment;
use Pakettikauppa\Shipment\ContentLine;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Client;

if ( ! class_exists(__NAMESPACE__ . '\Shipment') ) {
  /**
   * Shipment Class
   *
   * @class Shipment
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Shipment {
    /**
     * @var Core
     */
    public $core = null;

    /**
     * @var Client
     */
    private $client = null;
    protected $settings = null;

    private $errors = array();

    public function __construct( Core $plugin ) {
      $this->core = $plugin;

      $this->id = $this->core->prefix . '_shipment';
    }

    /**
     * Add an error with a specified error message.
     *
     * @param string $message A message containing details about the error.
     */
    public function add_error( $message ) {
      if ( ! empty($message) ) {
        array_push($this->errors, $message);
      }
    }

    /**
     * Return all errors that have been added via add_error().
     *
     * @return array Errors
     */
    public function get_errors() {
      return $this->errors;
    }

    /**
     * Clear all existing errors that have been added via add_error().
     */
    public function clear_errors() {
      unset($this->errors);
      $this->errors = array();
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
      switch ( intval($status_code) ) {
        case 13:
          $status = __('Item is collected from sender - picked up', 'woo-pakettikauppa');
          break;
        case 20:
          $status = __('Exception', 'woo-pakettikauppa');
          break;
        case 22:
          $status = __('Item has been handed over to the recipient', 'woo-pakettikauppa');
          break;
        case 31:
          $status = __('Item is in transport', 'woo-pakettikauppa');
          break;
        case 38:
          $status = __('C.O.D payment is paid to the sender', 'woo-pakettikauppa');
          break;
        case 45:
          $status = __('Informed consignee of arrival', 'woo-pakettikauppa');
          break;
        case 48:
          $status = __('Item is loaded onto a means of transport', 'woo-pakettikauppa');
          break;
        case 56:
          $status = __('Item not delivered – delivery attempt made', 'woo-pakettikauppa');
          break;
        case 68:
          $status = __('Pre-information is received from sender', 'woo-pakettikauppa');
          break;
        case 71:
          $status = __('Item is ready for delivery transportation', 'woo-pakettikauppa');
          break;
        case 77:
          $status = __('Item is returning to the sender', 'woo-pakettikauppa');
          break;
        case 91:
          $status = __('Item is arrived to a post office', 'woo-pakettikauppa');
          break;
        case 99:
          $status = __('Outbound', 'woo-pakettikauppa');
          break;
        default:
          /* translators: %s: Status code */
          $status = wp_sprintf(__('Unknown status: %s', 'woo-pakettikauppa'), $status_code);
          break;
      }

      return $status;
    }


    public function get_pickup_point_methods() {
      $methods = array(
        '2103'  => 'Posti',
        '90080' => 'Matkahuolto',
        '80010' => 'DB Schenker',
        '2711'  => 'Posti International',
      );

      return $methods;
    }

    /**
     * @param WC_Order $order
     * @param null $service_id
     * @param array $additional_services
     *
     * @return string|null
     */
    public function create_shipment( \WC_Order $order, $service_id = null, $additional_services = null ) {
      do_action(str_replace('wc_', '', $this->core->prefix) . '_prepare_create_shipment', $order, $service_id, $additional_services);

      if ( $service_id === null ) {
        $service_id = $this->get_service_id_from_order($order);
      }

      if ( empty($service_id) || $service_id === '__NULL__' || $service_id === '__PICKUPPOINTS__' ) {
        $this->add_error('error');
        $order->add_order_note(esc_attr__('The shipping label was not created because the order does not contain valid shipping method.', 'woo-pakettikauppa'));

        return null;
      }

      // Bail out if the receiver has not been properly configured
      if ( ! self::validate_order_shipping_receiver($order) ) {
        $this->add_error('error');
        add_action(
          'admin_notices',
          function() {
            echo '<div class="update-nag notice">' .
                esc_attr__('The shipping label was not created because the order does not contain valid shipping details.', 'woo-pakettikauppa') .
                '</div>';
          }
        );

        return null;
      }

      if ( $additional_services === null ) {
        $additional_services = $this->get_additional_services_from_order($order);

        $pickup_point_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');

        if ( ! empty($pickup_point_id) ) {
          $additional_services[] = array(
            '2106' => array(
              'pickup_point_id' => $pickup_point_id,
            ),
          );
        }
      }

      try {
        $shipment = $this->create_shipment_from_order($order, $service_id, $additional_services);
        $tracking_code = $shipment->{'response.trackingcode'}->__toString();
      } catch ( \Exception $e ) {
        $this->add_error($e->getMessage());

        /* translators: %s: Error message */
        $order->add_order_note(sprintf(esc_attr__('Failed to create Pakettikauppa shipment. Errors: %s', 'woo-pakettikauppa'), $e->getMessage()));
        add_action(
          'admin_notices',
          function() use ( $e ) {
            /* translators: %s: Error message */
            $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
          }
        );

        return null;
      }

      if ( $tracking_code === null ) {
        $this->add_error('error');
        $order->add_order_note(esc_attr__('Failed to create Pakettikauppa shipment.', 'woo-pakettikauppa'));
        add_action(
          'admin_notices',
          function() {
            /* translators: %s: Error message */
            $this->add_error_notice(esc_attr__('Failed to create Pakettikauppa shipment.', 'woo-pakettikauppa'));
          }
        );

        return null;
      }

      update_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_code', $tracking_code);
      update_post_meta($order->get_id(), '_' . $this->core->prefix . '_custom_service_id', $service_id);

      do_action(str_replace('wc_', '', $this->core->prefix) . '_post_create_shipment', $order);

      $document_url = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);
      $tracking_url = (string) $shipment->{'response.trackingcode'}['tracking_url'];

      update_post_meta($order->get_id(), '_' . $this->core->prefix . '_tracking_url', $tracking_url);

      $label_code = (string) $shipment->{'response.trackingcode'}['labelcode'];

      if ( ! empty($label_code) ) {
        update_post_meta($order->get_id(), '_' . $this->core->prefix . '_label_code', $label_code);
      }

      // Add order note
      $dl_link       = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $document_url, esc_attr__('Print document', 'woo-pakettikauppa'));
      $tracking_link = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $tracking_url, __('Track', 'woo-pakettikauppa'));

      $service_id = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_service_id', true);

      $order->add_order_note(
        sprintf(
          /* translators: 1: Shipping service title 2: Shipment tracking code 3: Shipping label URL 4: Shipment tracking URL */
          __('Created Pakettikauppa %1$s shipment.<br>%2$s<br>%1$s - %3$s<br>%4$s', 'woo-pakettikauppa'),
          $this->service_title($service_id),
          $tracking_code,
          $dl_link,
          $tracking_link
        )
      );

      $settings = $this->get_settings();

      if ( ! empty($settings['post_label_to_url']) ) {
        if ( $this->post_label_to_url($settings['post_label_to_url'], $tracking_code) === false ) {
          $this->add_error('error');
          $order->add_order_note(__('Posting label to URL failed!', 'woo-pakettikauppa'));

          return null;
        } else {
          $order->add_order_note(__('Label posted to URL successfully.', 'woo-pakettikauppa'));
        }
      }

      if ( ! empty($settings['change_order_status_to']) ) {
        if ( $order->get_status() !== $settings['change_order_status_to'] ) {
          $order->update_status($settings['change_order_status_to']);
        }
      }

      return $tracking_code;
    }

    private function post_label_to_url( $url, $tracking_code ) {
      $contents = $this->shipment->fetch_shipping_label($tracking_code);

      $label = base64_decode( $contents->{'response.file'} ); // @codingStandardsIgnoreLine

      $postdata = http_build_query(array( 'label' => $label ));

      $opts = array(
        'http' => array(
          'method'  => 'POST',
          'header'  => 'Content-Type: application/x-www-form-urlencoded',
          'content' => $postdata,
        ),
        'ssl' => array(
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed'=> true,
        ),
      );

      $context  = stream_context_create($opts);

      $result = file_get_contents($url, false, $context);

      if ( $result === false ) {
        return false;
      }

      return $result;
    }

    public function get_service_id_from_order( \WC_Order $order, $return_default_shipping_method = true ) {
      if ( $order === null ) {
        return null;
      }

      $service_id = get_post_meta($order->get_id(), '_' . $this->core->prefix . '_service_id', true);

      if ( empty($service_id) ) {
        $shipping_methods = $order->get_shipping_methods();

        $shipping_method = array_pop($shipping_methods);

        if ( ! empty($shipping_method) ) {
          $service_id = $shipping_method->get_meta('service_code');
        }
      }

      if ( empty($service_id) ) {
        $service_id = get_post_meta($order->get_id(), '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_provider_id', true);
      }

      if ( empty($service_id) ) {
        $shipping_methods = $order->get_shipping_methods();

        $chosen_shipping_method = array_pop($shipping_methods);

        if ( ! empty($chosen_shipping_method) ) {
          $method_id = $chosen_shipping_method->get_method_id();

          if ( $method_id === 'local_pickup' ) {
            return null;
          }

          $instance_id = $chosen_shipping_method->get_instance_id();

          $settings = $this->get_settings();

          $pickup_points = json_decode($settings['pickup_points'], true);

          if ( ! empty($pickup_points[ $instance_id ]['service']) ) {
            $service_id = $pickup_points[ $instance_id ]['service'];
          }
        }
      }

      if ( $service_id === '__NULL__' ) {
        return null;
      }

      if ( empty($service_id) && $return_default_shipping_method ) {
        $service_id = self::get_default_service();
      }

      if ( $service_id === '__PICKUPPOINTS__' ) {
          // This might be a bug or a version update problem
          $pickup_point = get_post_meta($order->get_id(), '_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', true);

          $provider = explode(':', $pickup_point, 2);

          if ( ! empty($provider) ) {
              $methods = array_flip($this->core->shipment->get_pickup_point_methods());
              $service_id = $methods[$provider[0]];
          } else {
              $service_id = null;
          }
      }
      return $service_id;
    }

    /**
     * Get the full-length tracking url of a shipment by providing its service id and tracking code.
     * Use tracking url provided by pakettikauppa.fi.
     *
     * @param string $tracking_code The tracking code of the shipment
     *
     * @return string The full tracking url for the order
     */
    public static function tracking_url( $tracking_code ) {

      if ( empty($tracking_code) ) {
        return '';
      }
      $tracking_url = 'https://www.pakettikauppa.fi/seuranta/?' . $tracking_code;

      return $tracking_url;
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
      $no_shipping_name     = (bool) empty($order->get_formatted_shipping_full_name());
      $no_shipping_address  = (bool) empty($order->get_shipping_address_1()) && empty($order->get_shipping_address_2());
      $no_shipping_postcode = (bool) empty($order->get_shipping_postcode());
      $no_shipping_city     = (bool) empty($order->get_shipping_city());

      if ( $no_shipping_name || $no_shipping_address || $no_shipping_postcode || $no_shipping_city ) {
        return false;
      }

      return true;
    }

    public function load() {
      $settings = $this->get_settings();

      $account_number = isset($settings['account_number']) ? $settings['account_number'] : '';
      $secret_key     = isset($settings['secret_key']) ? $settings['secret_key'] : '';
      $mode           = isset($settings['mode']) ? $settings['mode'] : 'test';

      if ( empty($this->config[$mode]) ) {
          $this->config[$mode] = array();
      }

      $configs = $this->core->api_config;

      $configs[$mode] = array_merge(
        array(
          'api_key'   => $account_number,
          'secret'    => $secret_key,
          'use_posti_auth' => false,
        ),
        $this->core->api_config[$mode]
      );

      $this->client = new \Pakettikauppa\Client($configs, $mode);
      $this->client->setComment($this->core->api_comment);

      if ( $configs[$mode]['use_posti_auth'] ) {
        $transient_name = $this->core->prefix . '_access_token';

        $token = get_transient($transient_name);

        /**
         * TODO locking
         *
         * in case there are multiple simultanous requests to this part of the code, it will create multiple
         * getToken() requests to the authentication server. So this needs to be eather distributedly locked or
         * moved to a background process and run from the cron
         */
        if ( empty($token) ) {
          $token = $this->client->getToken();

          // let's remove 100 seconds from expires_in time so in case of a network lag, requests will still be valid on server side
          set_transient($transient_name, $token, $token->expires_in - 100);
        }

        $this->client->setAccessToken($token->access_token);
      }
    }

    /**
     * Get the status of a shipment
     *
     * @param int $post_id The post id of the order to update status of
     *
     * @return int The status code of the shipment
     */
    public function get_shipment_status( $post_id ) {
      $tracking_code = get_post_meta($post_id, '_' . $this->core->prefix . '_tracking_code', true);

      if ( ! empty($tracking_code) ) {
        $data = $this->client->getShipmentStatus($tracking_code);

        if ( ! empty($data) && isset($data[0]) ) {
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
     * @param null $service_id
     * @param array $additional_services
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function create_shipment_from_order( $order, $service_id = null, $additional_services = array() ) {
      $shipment   = new PK_Shipment();

      $shipment->setShippingMethod($service_id);

      $id = $order->get_id();
      $shipping_phone = get_post_meta($id, '_shipping_phone', true);
      $shipping_email = get_post_meta($id, '_shipping_email', true);

      $sender = new Sender();
      $sender->setName1($this->settings['sender_name']);
      $sender->setAddr1($this->settings['sender_address']);
      $sender->setPostcode($this->settings['sender_postal_code']);
      $sender->setCity($this->settings['sender_city']);
      $sender->setPhone($this->settings['sender_phone']);
      // $sender->setEmail($this->settings['sender_email']);
      $sender->setCountry((empty($this->settings['sender_country']) ? 'FI' : $this->settings['sender_country']));
      $shipment->setSender($sender);

      $receiver = new Receiver();
      $receiver->setName1($order->get_formatted_shipping_full_name());
      $receiver->setAddr1($order->get_shipping_address_1());
      $receiver->setAddr2($order->get_shipping_address_2());
      $receiver->setPostcode($order->get_shipping_postcode());
      $receiver->setCity($order->get_shipping_city());
      $receiver->setCountry(($order->get_shipping_country() === null ? 'FI' : $order->get_shipping_country()));
      $receiver->setEmail(! empty($shipping_email) ? $shipping_email : $order->get_billing_email());
      $receiver->setPhone(! empty($shipping_phone) ? $shipping_phone : $order->get_billing_phone());
      $shipment->setReceiver($receiver);

      $info = new Info();
      $info->setReference($order->get_order_number());
      $info->setCurrency(get_woocommerce_currency());
      $shipment->setShipmentInfo($info);

      $parcel_total_count = 1;

      foreach ( $additional_services as $_additional_service ) {
        $additional_service = new AdditionalService();
        $additional_service_code = strval(key($_additional_service));
        $additional_service->setServiceCode($additional_service_code);

        foreach ( $_additional_service as $_additional_service_key => $_additional_service_config ) {
          if ( ! empty($_additional_service_config) ) {
            foreach ( $_additional_service_config as $_name => $_value ) {
              $additional_service->addSpecifier($_name, $_value);

              if ( $additional_service_code === '3102' ) {
                $parcel_total_count = $_value;
              }
            }
          }
        }

        $shipment->addAdditionalService($additional_service);
      }

      $order_total_weight = self::order_weight($order);
      $order_total_volume = self::order_volume($order);

      for ( $i = 0; $i < $parcel_total_count; $i++ ) {
        $parcel = new Parcel();
        $parcel->setWeight(round($order_total_weight / $parcel_total_count, 2));
        $parcel->setVolume(round($order_total_volume / $parcel_total_count, 4));

        if ( ! empty($this->settings['info_code']) ) {
          $parcel->setInfocode($this->settings['info_code']);
        }

        $shipment->addParcel($parcel);
      }

      $items = $order->get_items();

      $wcpf = new \WC_Product_Factory();

      if ( ! empty($items) ) {
        foreach ( $items as $item ) {
          $item_data = $item->get_data();

          if ( empty($item_data) ) {
            continue;
          }

          $product = $wcpf->get_product($item_data['product_id']);

          if ( empty($product) ) {
            continue;
          }

          if ( $product->is_virtual() ) {
            continue;
          }

          $tariff_code       = $product->get_meta(str_replace('wc_', '', $this->core->prefix) . '_tariff_codes', true);
          $country_of_origin = $product->get_meta(str_replace('wc_', '', $this->core->prefix) . '_country_of_origin', true);

          $content_line                    = new ContentLine();
          $content_line->currency          = 'EUR';
          $content_line->country_of_origin = $country_of_origin;
          $content_line->tariff_code       = $tariff_code;
          $content_line->description       = $product->get_name();
          $content_line->quantity          = $item->get_quantity();

          if ( ! empty($product->get_weight()) ) {
            $content_line->netweight = wc_get_weight($product->get_weight() * $item->get_quantity(), 'g');
          }

          $content_line->value = round($item_data['total'] + $item_data['total_tax'], 2);

          $parcel->addContentLine($content_line);
        }
      }

      try {
        $this->client->createTrackingCode($shipment);
      } catch ( \Exception $e ) {
        /* translators: %s: Error message */
        throw new \Exception(wp_sprintf(__('WooCommerce Pakettikauppa: tracking code creation failed: %s', 'woo-pakettikauppa'), $e->getMessage()));
      }

      return $this->client->getResponse();
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

      $wcpf = new \WC_Product_Factory();

      foreach ( $order->get_items() as $item ) {
        if ( empty($item['product_id']) ) {
          continue;
        }

        $product = $wcpf->get_product($item['product_id']);

        if ( $product->is_virtual() ) {
          continue;
        }

        if ( ! is_numeric($product->get_weight()) ) {
          continue;
        }

        $weight += wc_get_weight($product->get_weight() * $item->get_quantity(), 'kg');
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

      $wcpf = new \WC_Product_Factory();

      foreach ( $order->get_items() as $item ) {
        if ( empty($item['product_id']) ) {
          continue;
        }

        $product = $wcpf->get_product($item['product_id']);

        if ( $product->is_virtual() ) {
          continue;
        }

        if ( ! is_numeric($product->get_width())
            || ! is_numeric($product->get_height())
            || ! is_numeric($product->get_length()) ) {
          continue;
        }

        // Ensure that the volume is in metres
        $woo_dim_unit = strtolower(get_option('woocommerce_dimension_unit'));
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
        $volume += pow($dim_multiplier, 3) * $product->get_width() * $product->get_height() * $product->get_length() * $item['qty'];
      }

      return $volume;
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

      $base                 = str_split(strval(($id)));
      $reversed_base        = array_reverse($base);
      $reversed_base_length = count($reversed_base);

      for ( $i = 0; $i < $reversed_base_length; $i ++ ) {
        $sum += $reversed_base[ $i ] * $weights[ $i % 3 ];
      }

      $checksum = (10 - $sum % 10) % 10;

      $reference = implode('', $base) . $checksum;

      return $reference;
    }

    /**
     * @param array $tracking_codes
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function fetch_shipping_labels( $tracking_codes ) {
      return $this->client->fetchShippingLabels($tracking_codes);
    }

    /**
     * @param string $tracking_code
     *
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function fetch_shipping_label( $tracking_code ) {
      return $this->fetch_shipping_labels(array( $tracking_code ));
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

      if ( isset($this->settings['pickup_points_search_limit']) && ! empty($this->settings['pickup_points_search_limit']) ) {
        $pickup_point_limit = intval($this->settings['pickup_points_search_limit']);
      }

      $pickup_point_data = $this->client->searchPickupPoints(trim($postcode), trim($street_address), trim($country), $service_provider, $pickup_point_limit);

      if ( $pickup_point_data === 'Bad request' ) {
        throw new \Exception($this->core->text->something_went_wrong_while_searching_pickup_points_error());
      }

      // This makes zero sense unless you read this issue:
      // https://github.com/Pakettikauppa/api-library/issues/11
      if ( empty($pickup_point_data) ) {
        throw new \Exception($this->core->text->no_pickup_points_error());
      }

      return $pickup_point_data;
    }

    public function get_pickup_points_by_free_input( $input, $service_provider = null ) {
      $pickup_point_limit = 5; // Default limit value for pickup point search

      if ( isset($this->settings['pickup_points_search_limit']) && ! empty($this->settings['pickup_points_search_limit']) ) {
        $pickup_point_limit = intval($this->settings['pickup_points_search_limit']);
      }

      $pickup_point_data = $this->client->searchPickupPointsByText(trim($input), $service_provider, $pickup_point_limit);

      if ( $pickup_point_data === 'Bad request' ) {
        throw new \Exception($this->core->text->something_went_wrong_while_searching_pickup_points_error());
      }

      // This makes zero sense unless you read this issue:
      // https://github.com/Pakettikauppa/api-library/issues/11
      if ( empty($pickup_point_data) ) {
        throw new \Exception($this->core->text->no_pickup_points_error());
      }

      return $pickup_point_data;
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
      if ( isset($services[ $service_code ]) ) {
        return $services[ $service_code ];
      }

      return false;
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
      if ( $all_shipping_methods === null ) {
        // returning null seems to invalidate services cache
        return null;
      }

      foreach ( $all_shipping_methods as $shipping_method ) {
        $services[ strval($shipping_method->shipping_method_code) ] = sprintf('%1$s: %2$s', $shipping_method->service_provider, $shipping_method->name);
      }

      ksort($services);

      return $services;
    }

    public function get_additional_services_from_order( \WC_Order $order ) {
      $additional_services = array();

      $settings = $this->get_settings();

      $shipping_methods = $order->get_shipping_methods();

      $chosen_shipping_method = array_pop($shipping_methods);

      $add_cod_to_additional_services = 'cod' === $order->get_payment_method();

      if ( ! empty($chosen_shipping_method) ) {
        $method_id = $chosen_shipping_method->get_method_id();

        if ( $method_id === 'local_pickup' ) {
          return $additional_services;
        }

        $instance_id = $chosen_shipping_method->get_instance_id();

        $pickup_points = json_decode($settings['pickup_points'], true);

        if ( ! empty($pickup_points[ $instance_id ]['service']) ) {
          $service_id = $pickup_points[ $instance_id ]['service'];

          $services = array();

          if ( ! empty($pickup_points[ $instance_id ][ $service_id ]) && isset($pickup_points[ $instance_id ][ $service_id ]['additional_services']) ) {
            $services = $pickup_points[ $instance_id ][ $service_id ]['additional_services'];
          }

          if ( ! empty($services) ) {
            foreach ( $services as $service_code => $service ) {
              if ( $service === 'yes' && $service_code !== '3101' ) {
                $additional_services[] = array( $service_code => null );
              } elseif ( $service === 'yes' && $service_code === '3101' ) {
                $add_cod_to_additional_services = true;
              }
            }
          }
        }
      }

      if ( $add_cod_to_additional_services ) {
        $additional_services[] = array(
          '3101' => array(
            'amount' => $order->get_total(),
            'account' => $settings['cod_iban'],
            'codbic' => $settings['cod_bic'],
            'reference' => $this->calculate_reference($order->get_id()),
          ),
        );
      }

      return $additional_services;
    }


    public function get_additional_services() {
      $all_shipping_methods = $this->get_shipping_methods();

      if ( $all_shipping_methods === null ) {
        return null;
      }

      $additional_services = array();
      foreach ( $all_shipping_methods as $shipping_method ) {
        $additional_services[ strval($shipping_method->shipping_method_code) ] = $shipping_method->additional_services;
      }

      return $additional_services;
    }

    /**
     * Fetch shipping methods from the Pakettikauppa and returns it as objects
     *
     * @param boolean $fromCache should we try to fetch results from cache?
     *
     * @return mixed
     */
    private function get_shipping_methods() {
      $transient_name = $this->core->prefix . '_shipping_methods';
      $transient_time = 86400; // 24 hours

      $all_shipping_methods = get_transient($transient_name);

      if ( empty($all_shipping_methods) ) {
        try {
          $all_shipping_methods = $this->client->listShippingMethods();
        } catch ( \Exception $ex ) {
          $all_shipping_methods = null;
        }

        if ( ! empty($all_shipping_methods) ) {
          set_transient($transient_name, $all_shipping_methods, $transient_time);
        }
      }

      if ( empty($all_shipping_methods) ) {
        return null;
      }

      return $all_shipping_methods;
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

      if ( $all_shipping_methods === null ) {
        return false;
      }

      foreach ( $all_shipping_methods as $shipping_method ) {
        if ( strval($service_code) === strval($shipping_method->shipping_method_code) ) {
          return $shipping_method->service_provider;
        }
      }

      return false;
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

      if ( $all_shipping_methods === null ) {
        return false;
      }

      foreach ( $all_shipping_methods as $shipping_method ) {
        if ( strval($shipping_method->shipping_method_code) === strval($service_id) ) {
          return $shipping_method->has_pickup_points;
        }
      }

      return false;
    }

    /**
     * Returns global settings
     * @return array
     */
    public function get_settings() {
      if ( ! $this->settings ) {
        // TODO: Get to the bottom of whatever the comment below means and is it true anymore
        // Use option from database directly as Shipping_Method object is not accessible here

        // The key *has* to be in the following format: woocommerce_SHIPPING_METHOD_ID_settings, else
        // WooCommerce breaks the entire site.
        $this->settings = get_option('woocommerce_' . $this->core->shippingmethod . '_settings', array());
        // $this->settings = get_option('woocommerce_pakettikauppa_shipping_method_settings', array());
      }

      return $this->settings;
    }

    public function save_settings() {
      // The key *has* to be in the following format: woocommerce_SHIPPING_METHOD_ID_settings, else
      // WooCommerce breaks the entire site.
      // return update_option('woocommerce_' . 'woocommerce_pakettikauppa_shipping_method_settings', $this->get_settings(), 'yes');
      return update_option('woocommerce_' . $this->core->shippingmethod . '_settings', $this->get_settings(), 'yes');
    }

    public function update_setting( $name, $value ) {
      $this->settings[$name] = $value;
    }

    public function can_create_shipment_automatically( \WC_Order $order ) {
      $settings = $this->get_settings();

      if ( ! empty($settings['create_shipments_automatically']) ) {
        if ( $order->get_status() === $settings['create_shipments_automatically'] ) {
          return true;
        }
      }

      return false;
    }
  }
}
