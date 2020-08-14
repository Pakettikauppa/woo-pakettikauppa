<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Woocommerce_Pakettikauppa
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if ( ! $_tests_dir ) {
  $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Set custom error handler that fails on PHP warnings and notices
// set_error_handler(
//   function( $errno, $errstr, $errfile, $errline ) {
//     throw new RuntimeException($errstr . ' on line ' . $errline . ' in file ' . $errfile);
//   }
// );

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
tests_add_filter(
  'muplugins_loaded',
  function() {
    require_once 'functions.php';

    require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    get_instance();
  }
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
