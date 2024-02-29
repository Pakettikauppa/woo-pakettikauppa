<?php

function is_file_contains_text($path_to_file, $text) {
  if ( empty($path_to_file) ) {
    return false;
  }

  $found = false;

  $file = fopen($path_to_file, 'r');
  while (($buffer = fgets($file)) !== false) {
    if (strpos($buffer, $text) !== false) {
      $found = true;
      break;
    }
  }
  fclose($file);

  return $found;
}

function get_plugin_class() {
  $dir = basename(get_plugin_directory(true));
  $class = '';

  $parts = explode('-', $dir);

  foreach ( $parts as $k => $part ) {
    $parts[$k] = ucfirst($part);
  }

  return join('_', $parts);
}

function get_plugin_directory( $replace = false ) {
  $dir = dirname(__DIR__);

  if ( ! $replace ) {
    return $dir;
  }

  // Hack to get tests working without deactivating the plugin for old users
  return str_replace('woo-pakettikauppa', 'wc-pakettikauppa', dirname(__DIR__));
}

function get_plugin_main_filename() {
  $main_file = basename(get_plugin_directory(true)) . '.php';
  $files = glob(get_plugin_directory(true) . '/*.php');
  foreach ( $files as $file ) {
    if ( is_file_contains_text($file, 'Plugin Name:') ) {
      $main_file = basename($file);
      break;
    }
  }
  
  return $main_file;
}

function get_plugin_config() {
  $file = get_plugin_directory() . '/' . get_plugin_main_filename();
  return array(
    'root' => $file,
    'version' => get_file_data($file, array( 'Version' ), 'plugin')[0],
    'shipping_method_name' => 'pakettikauppa_shipping_method',
    'vendor_name' => 'Pakettikauppa',
    'vendor_url' => 'https://www.pakettikauppa.fi/',
    'vendor_logo' => 'assets/img/pakettikauppa-logo.png',
    'setup_background' => 'assets/img/pakettikauppa-background.jpg',
  );
}

function get_instance() {
  static $plugin = null;

  if ( ! $plugin ) {
    // phpcs:disable
    // phpcs:ignore
    require get_plugin_directory() . '/' . get_plugin_main_filename(); // @codingStandardsIgnoreLine
    // phpcs:enable

    $plugin = $instance;
  }

  return $plugin;
}
