<?php
/**
 * Class TestWCPakettikauppa
 *
 * @package Woocommerce_Pakettikauppa
 * @version  1.0.0
  * @since 1.0.0
  * @package  woocommerce-pakettikauppa
  * @author Seravo
 */
class TestWCPakettikauppa extends WP_UnitTestCase {

	/**
	 * Test that the id is set correctly and return an WC_Pakettikauppa object.
	 */
	function test_init() {
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
  function test_wc_pakettikauppa_get_status_text($pakettikauppa) {
		$status = WC_Pakettikauppa::get_status_text(13);
		$this->assertEquals( "Item is collected from sender - picked up", $status);
		$input = "abcdefg";
		$status = WC_Pakettikauppa::get_status_text($input);
		$this->assertEquals( "Unknown status: " . $input, $status );
	}

	/**
	 * @depends test_init
	 */
  function test_wc_pakettikauppa_tracking_url($pakettikauppa) {
		$inputs = array( 0 => 90080, 1 => 'seurantakoodi' );
		$output = WC_Pakettikauppa::tracking_url($inputs[0], $inputs[1]);
		$this->assertEquals( "https://pakettikauppa.fi/seuranta/?seurantakoodi", $output);
	}

	/**
	 * @depends test_init
	 */
	function test_get_pickup_points($pakettikauppa) {
		$pickups = $pakettikauppa->get_pickup_points(00100);
		$wc_pakettikauppa_client = new Pakettikauppa\Client( array( 'test_mode' => true ) );
		$pickup_point_data = $wc_pakettikauppa_client->searchPickupPoints( 00100 );
		$this->assertEquals( $pickup_point_data, $pickups);
	}
}
