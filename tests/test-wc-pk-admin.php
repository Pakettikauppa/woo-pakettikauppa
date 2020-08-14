<?php

/**
 * Class Test_Admin
 *
 * @package woo-pakettikauppa
 * @since 1.0.0
 * @author Seravo
 */
class Test_Admin extends WP_UnitTestCase {

  /**
   * Test that the main plugin class is accessible and return an Admin object
   */
  public function test_admin_init() {
    $plugin = get_instance();
    $plugin->load();

    $admin = $plugin->admin;
    $admin->load();

    $this->assertEquals($plugin, $admin->core);

    return $admin;
  }

  /**
   * @depends test_admin_init
   */
  public function test_admin_errors_empty( $admin ) {
    $this->assertEmpty($admin->get_errors());
    return $admin;
  }

  /**
   * @depends test_admin_errors_empty
   */
  public function test_admin_add_error( $admin ) {
    $error = 'This is an admin testing error.';
    $admin->add_error($error);
    $this->assertEquals(array( $error ), $admin->get_errors());
    return $admin;
  }

  /**
   * @depends test_admin_add_error
   */
  public function test_admin_clear_errors( $admin ) {
    $admin->clear_errors();
    $this->assertEquals(array(), $admin->get_errors());
  }

  public function setUp() {
    parent::setUp();

    // Set result of is_admin() to true
    set_current_screen('wcpk-setup');
  }

  public function tearDown() {
    parent::tearDown();
  }
}
