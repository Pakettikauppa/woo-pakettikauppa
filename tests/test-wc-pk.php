<?php


/**
 * Class Test_Frontend
 *
 * @package woo-pakettikauppa
 * @since 1.0.0
 * @author Seravo
 */
class Test_Frontend extends WP_UnitTestCase {

  /**
   * Test that the main plugin class is accessible and return an Frontend object.
   */
  public function test_init() {
    $plugin = get_instance();
    $plugin->load();

    $frontend = $plugin->frontend;
    $frontend->load();

    $this->assertEquals($plugin, $frontend->core);

    return $frontend;
  }

  /**
   * Check that the shipment status texts can be set correctly.
   *
   * @depends test_init
   */
  public function test_wc_pakettikauppa_get_status_text( $frontend ) {

    $status = call_user_func(array( $frontend->core->shipment, 'get_status_text' ), 13);
    $this->assertEquals('Item is collected from sender - picked up', $status);
    $input  = 'abcdefg';
    $status = call_user_func(array( $frontend->core->shipment, 'get_status_text' ), $input);
    $this->assertEquals('Unknown status: ' . $input, $status);
  }

  /**
   * @depends test_init
   */
  public function test_wc_pakettikauppa_tracking_url( $frontend ) {
    $inputs = array(
      0 => 90080,
      1 => 'seurantakoodi',
    );
    $output = call_user_func(array( $frontend->core->shipment, 'tracking_url' ), $inputs[1]);
    $this->assertEquals('https://www.pakettikauppa.fi/seuranta/?seurantakoodi', $output);
  }

  /**
   * @depends test_init
   */
  public function test_get_pickup_points( $frontend ) {
    $pickups = $frontend->core->shipment->get_pickup_points('00180', 'Abrahaminkatu 5', 'FI', '2103');
    $wc_pakettikauppa_client = new Pakettikauppa\Client();
    $pickup_point_data       = $wc_pakettikauppa_client->searchPickupPoints('00180', 'Abrahaminkatu 5', 'FI', '2103');
    $this->assertEquals($pickup_point_data, $pickups);
  }
}
