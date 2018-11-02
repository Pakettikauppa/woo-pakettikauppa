<?php
/**
 * Class Test_WC_Pakettikauppa
 *
 * @package woocommerce-pakettikauppa
 * @since 1.0.0
 * @author Seravo
 */
class Test_WC_Pakettikauppa extends WP_UnitTestCase {

  /**
   * Test that the id is set correctly and return an WC_Pakettikauppa object.
   */
  public function test_init() {
    $pakettikauppa = new WC_Pakettikauppa();
    $this->assertEquals( 'wc_pakettikauppa', $pakettikauppa->id );
    $pakettikauppa->load();

    return $pakettikauppa;
  }

  /**
   * Check that the shipment status texts can be set correctly.
   *
   * @depends test_init
   */
  public function test_wc_pakettikauppa_get_status_text( $pakettikauppa ) {
    $status = WC_Pakettikauppa_Shipment::get_status_text(13);
    $this->assertEquals( 'Item is collected from sender - picked up', $status);
    $input  = 'abcdefg';
    $status = WC_Pakettikauppa_Shipment::get_status_text($input);
    $this->assertEquals( 'Unknown status: ' . $input, $status );
  }

  /**
   * @depends test_init
   */
  public function test_wc_pakettikauppa_tracking_url( $pakettikauppa ) {
    $inputs = array(
      0 => 90080,
      1 => 'seurantakoodi',
    );
    $output = WC_Pakettikauppa_Shipment::tracking_url($inputs[1]);
    $this->assertEquals( 'https://www.pakettikauppa.fi/seuranta/?seurantakoodi', $output);
  }

  /**
   * @depends test_init
   */
  public function test_get_pickup_points( $pakettikauppa ) {
    $shipment = new WC_Pakettikauppa_Shipment();
    $shipment->load();
    $pickups                 = $shipment->get_pickup_points(00100);
    $wc_pakettikauppa_client = new Pakettikauppa\Client( array( 'test_mode' => true ) );
    $pickup_point_data       = $wc_pakettikauppa_client->searchPickupPoints( 00100 );
    $this->assertEquals( $pickup_point_data, $pickups);
  }
}
