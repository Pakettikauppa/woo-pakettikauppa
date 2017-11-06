![Pakettikauppa](https://www.pakettikauppa.fi/load/pakettikauppa-logo.png)

# Pakettikauppa WordPress plugin for WooCommerce

[![Build Status](https://travis-ci.org/Seravo/woo-pakettikauppa.svg?branch=master)](https://travis-ci.org/Seravo/woo-pakettikauppa) [![Latest Stable Version](https://poser.pugx.org/seravo/woo-pakettikauppa/v/stable)](https://packagist.org/packages/seravo/woo-pakettikauppa) [![Total Downloads](https://poser.pugx.org/seravo/woo-pakettikauppa/downloads)](https://packagist.org/packages/seravo/woo-pakettikauppa) [![Latest Unstable Version](https://poser.pugx.org/seravo/woo-pakettikauppa/v/unstable)](https://packagist.org/packages/seravo/woo-pakettikauppa) [![License](https://poser.pugx.org/seravo/woo-pakettikauppa/license)](https://packagist.org/packages/seravo/woo-pakettikauppa)

# Maturity

> This software is now available for General Availability.

# Installation

This plugin can be installed via [WordPress.org plugin directory](https://wordpress.org/plugins/woo-pakettikauppa/), WP-CLI or Composer:

```sh
wp plugin install --activate woo-pakettikauppa
# OR
wp plugin install --activate https://github.com/Seravo/woo-pakettikauppa/archive/master.zip
# OR
composer require seravo/woo-pakettikauppa
```

The plugin requires WooCommerce to be installed, with shipping zones configured and this plugin activated and settings set.

# Features

* Integrates [Pakettikauppa](https://www.pakettikauppa.fi/) with WooCommerce
* Based on the official [Pakettikauppa API library](https://github.com/Pakettikauppa/api-library)
* Supports WooCommerce shipping zones (though Pakettikauppa is currently only available in Finland)
* Store owners can specify themselves any fixed rate for a shipping or have free shipping if the order value is above a certain limit
* Customers can choose to ship products to an address or to any pickup point available from the Pakettikauppa shipping methods
* Store owner can generate the shipping label in one click
* Store owners and customers get tracking code links and status information
* Test mode available that uses the testing API

# Screenshots

![Screenshots](screenshot.png)

# Changelog

See git history.

# For developers

Pull requests are welcome!

Before submitting your patch, please make sure it is of high quality:

* Follow the [WordPress Codex plugin writing recommendations](https://codex.wordpress.org/Writing_a_Plugin) and also check the [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
* Follow the specific [WooCommmerce plugin recommendations](https://docs.woocommerce.com/document/create-a-plugin/)
* Test the code on a clean WordPress/WooCommmerce installation with standard [dummy data](https://docs.woocommerce.com/document/importing-woocommerce-dummy-data/)
* Make sure the test suite passes locally and on Travis-CI
* Check that the code style is valid when tested with the phpcs.xml included in this project

## Developer docs

Please note that the official docs at https://docs.woocommerce.com/document/shipping-method-api/ contain partially outdated information. For more information, see wiki at https://github.com/woocommerce/woocommerce/wiki/Shipping-Method-API or dive directly into the source using [GitHub search](https://github.com/woocommerce/woocommerce/search?utf8=%E2%9C%93&q=extends+WC_Shipping_Method&type=) to find up-to-date examples on how to extend the shipping method class.
