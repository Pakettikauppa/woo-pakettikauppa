<?php
/**
 * Class WcPkInitTest
 *
 * @package Woocommerce_Pakettikauppa
 */

/**
 * Init test case.
 */
class WcPkInitTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	function test_init() {
		// Replace this with some actual testing code.
		$pakettikauppa = new WC_Pakettikauppa();
		$this->assertEquals( 'wc_pakettikauppa', $pakettikauppa->id );
	  $pakettikauppa->load();

		return $pakettikauppa;
	}

	/**
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
		$this->assertEquals( "https://www.matkahuolto.fi/seuranta/tilanne/?package_code=seurantakoodi", $output);

		$inputs = array( 0 => 999999, 1 => 'seurantakoodi' );
		$output = WC_Pakettikauppa::tracking_url($inputs[0], $inputs[1]);
		$this->assertEquals( "", $output);

		$inputs = array( 0 => 2103, 1 => 'seurantakoodi' );
		$output = WC_Pakettikauppa::tracking_url($inputs[0], $inputs[1]);
		$this->assertEquals( "http://www.posti.fi/yritysasiakkaat/seuranta/#/lahetys/seurantakoodi", $output);
	}

	// @TODO: Move these tests to the module that tests WC_Pakettikauppa_Admin class
	// /**
	//  * @depends test_init
	//  */
  // function test_errors_empty($pakettikauppa) {
	// 	$this->assertEmpty($pakettikauppa->get_errors());
	// 	return $pakettikauppa;
	// }
	//
	// /**
	//  * @depends test_errors_empty
	//  */
	// function test_add_error($pakettikauppa) {
	// 	$pakettikauppa->add_error("This is just a testing error");
	// 	$this->assertEquals( array("This is just a testing error"), $pakettikauppa->get_errors() );
	// 	return $pakettikauppa;
	// }
	//
	// /**
	//  * @depends test_add_error
	//  */
	// function test_clear_errors($pakettikauppa) {
	// 	$pakettikauppa->clear_errors();
	// 	$this->assertEquals( array(), $pakettikauppa->get_errors() );
	// }

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
