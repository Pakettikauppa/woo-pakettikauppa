<?php

require_once( WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-admin.php' );

/**
 * Class TestWCPakettikauppaAdmin
 *
 * @package Woocommerce_Pakettikauppa
 * @version  1.0.0
  * @since 1.0.0
  * @package  woocommerce-pakettikauppa
  * @author Seravo
 */
class TestWCPakettikauppaAdmin extends WP_UnitTestCase {

  /**
  * Test that the id is set correctly and return an WC_Pakettikauppa_Admin object
  */
  function test_admin_init() {
		$pakettikauppa_admin = new WC_Pakettikauppa_Admin();
		$this->assertEquals( 'wc_pakettikauppa_admin', $pakettikauppa_admin->id );
	  $pakettikauppa_admin->load();

		return $pakettikauppa_admin;
	}

	/**
	 * @depends test_admin_init
	 */
  function test_admin_errors_empty( $pakettikauppa_admin ) {
		$this->assertEmpty( $pakettikauppa_admin->get_errors() );
		return $pakettikauppa_admin;
	}

	/**
	 * @depends test_admin_errors_empty
	 */
	function test_admin_add_error( $pakettikauppa_admin ) {
    $error = 'This is an admin testing error.';
		$pakettikauppa_admin->add_error( $error );
		$this->assertEquals( array( $error ), $pakettikauppa_admin->get_errors() );
		return $pakettikauppa_admin;
	}

	/**
	 * @depends test_admin_add_error
	 */
	function test_admin_clear_errors( $pakettikauppa_admin ) {
		$pakettikauppa_admin->clear_errors();
		$this->assertEquals( array(), $pakettikauppa_admin->get_errors() );
	}

}
