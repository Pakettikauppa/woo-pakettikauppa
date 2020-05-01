<?php

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
  return basename(get_plugin_directory(true)) . '.php';
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
    require get_plugin_directory() . '/' . get_plugin_main_filename();
    // phpcs:enable

    $plugin = $instance;
  }

  return $plugin;
}
