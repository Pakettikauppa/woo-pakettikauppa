<?php

require_once WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-admin.php';

/**
 * Class Test_WC_Pakettikauppa_Admin
 *
 * @package woocommerce-pakettikauppa
 * @since 1.0.0
 * @author Seravo
 */
class Test_WC_Pakettikauppa_Admin extends WP_UnitTestCase {

  private $module_config = array(
    'text_domain' => 'wc-pakettikauppa',
    'admin' => 'wc_pakettikauppa_admin',
    'url' => 'https://www.pakettikauppa.fi/',
    'shipping_method' => 'pakettikauppa_shipping_method',
  );

  /**
   * Test that the id is set correctly and return an WC_Pakettikauppa_Admin object
   */
  public function test_admin_init() {
    $pakettikauppa_admin = new WC_Pakettikauppa_Admin($this->module_config);
    $this->assertEquals('wc_pakettikauppa_admin', $pakettikauppa_admin->id);
    $pakettikauppa_admin->load();

    return $pakettikauppa_admin;
  }

  /**
   * @depends test_admin_init
   */
  public function test_admin_errors_empty( $pakettikauppa_admin ) {
    $this->assertEmpty($pakettikauppa_admin->get_errors());
    return $pakettikauppa_admin;
  }

  /**
   * @depends test_admin_errors_empty
   */
  public function test_admin_add_error( $pakettikauppa_admin ) {
    $error = 'This is an admin testing error.';
    $pakettikauppa_admin->add_error($error);
    $this->assertEquals(array( $error ), $pakettikauppa_admin->get_errors());
    return $pakettikauppa_admin;
  }

  /**
   * @depends test_admin_add_error
   */
  public function test_admin_clear_errors( $pakettikauppa_admin ) {
    $pakettikauppa_admin->clear_errors();
    $this->assertEquals(array(), $pakettikauppa_admin->get_errors());
  }

}
