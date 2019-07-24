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
set_error_handler(
  function( $errno, $errstr, $errfile, $errline ) {
    throw new RuntimeException($errstr . ' on line ' . $errline . ' in file ' . $errfile);
  }
);

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
  require dirname(dirname(__FILE__)) . '/wc-pakettikauppa.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
