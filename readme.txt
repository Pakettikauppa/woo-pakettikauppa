=== WooCommerce Pakettikauppa ===
Contributors: joosev, ottok, leotoikka, serter
Donate link: https://seravo.com/
Tags: woocommerce, shipping, toimitustavat, smartship, pakettikauppa, posti, smartpost, prinetti, matkahuolto, schenker, seravo, gls
Requires at least: 4.6
Tested up to: 5.2
Requires PHP: 7.1
Stable tag: trunk
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin enables WooCommerce orders to ship using pretty much any shipping method available in Finland via Pakettikauppa.

== Description ==

[Pakettikauppa](https://www.pakettikauppa.fi/) is a shipping service provider in Finland. This plugin integrates their service into WooCommerce. To start shipping, all your WooCommerce needs is this plugin and a API credentials of your account registered with Pakettikauppa.

> *Note!* If you already have shipping contracts with Posti, Matkahuolto, DB Schenker, Asendia or GLS with reduced prices, you can contact the customer support of Pakettikauppa to get those contracts via Pakettikauppa so you can use the WooCommerce Pakettikauppa plugin with your current shipping contracts. Usage of own contracts is free of charge. No need to use logistics services own integrations (e.g. Posti SmartShip / Prinetti )

This plugin requires at least WooCommerce version 3.4.

== Register and start shipping ==

Register through [www.pakettikauppa.fi](https://www.pakettikauppa.fi/). Process only takes few minutes.

== Features ==

* Integrates Pakettikauppa with WooCommerce
* Based on the official [Pakettikauppa API library](https://github.com/Pakettikauppa/api-library)
* Supports WooCommerce shipping zones and classes (though Pakettikauppa is currently only available in Finland)
* Customers can choose to ship products to an address or to any pickup point available from the Pakettikauppa shipping methods
* Store owners can add pickup points to any shipping zones shipping method
* Store owners can specify themselves any fixed rate for a shipping or have free shipping if the order value is above a certain limit
* Store owners can generate the shipping label in one click
* Store owners can generate shipping lables as mass action from orders view
* Store owners and customers get tracking code links and status information
* Support for Cash-On-Delivery
* Test mode available that uses the testing API without registration

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or upload the plugin files to the `/wp-content/plugins/woo-pakettikauppa` directory.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Settings->WooCommerce Pakettikauppa screen to configure the plugin
1. The plugin requires WooCommerce to be installed, with shipping zones configured and this plugin activated and settings set.

This plugin can also be installed directly from Github or using `composer require seravo/woo-pakettikauppa`.

== Developer notes ==

= Hooks =

* pakettikauppa_prepare_create_shipment

arguments: $order, $service_id, $additional_services

* pakettikauppa_post_create_shipment

arguments: $order

= Actions =

* pakettikauppa_create_shipments

Call for example:

    $pdf = '';
    $order_ids = array (15, 16, 17);
    $args = array( $order_ids, &$pdf );
    do_action_ref_array('pakettikauppa_create_shipments', $args);"

* pakettikauppa_fetch_shipping_labels

Call for example:

    $tracking_code='';
    $args = array( $order_id, &$tracking_code );
    do_action_ref_array('pakettikauppa_fetch_tracking_code', $args);

* pakettikauppa_fetch_tracking_code

Call for example:

    $args = array( $order_id, $order_id2, ... );
    do_action('pakettikauppa_create_shipments', $args);

== Frequently Asked Questions ==

= Is this ready for production use? =

Yes! If you encounter any issues related to this plugin, please report at https://github.com/Seravo/woo-pakettikauppa/issues or to asiakaspalvelu@pakettikauppa.fi

= Can Shipipping method names be translated? =

You can use plugin (f.ex. Polylang) to translate shipping method names.

== Screenshots ==

1. Examples of settings screens

== Changelog ==

= 2.0.22 =
* New feature: Setup wizard
* Enhancements: Round weight and volume to more precise number
* Enhancements: Refactoring code

= 2.0.21 =
* Version skipped

= 2.0.20 =
* New feature: Change order status after shipping label is printed
* Enhancements: When choosing pickup point in checkout, do ajax call to save that pickup point to session as a backup.

= 2.0.19 =
* New feature: Create multiple return shipments from order view
* New feature: Option for automatic creation of shipping labels when order is complete
* Tested against woocommerce 3.7.0
* Enhancements: Show "Pakettikauppa shipping method" settings option
* Enhancements: Changing texts on settings page to make it easier to understand
* Enhancements: Make note to Pakettikauppa shipping method that it is not required to be used. Shipping methods already available to woo can do more than our own shipping method. Our own shipping method might be removed in the future as obsolete.
* Enhancements: Small refactoring of admin metabox
* Bug fixes: add more data validation

= 2.0.18.1 =
* Bug fix: bulk and quick actions now work

= 2.0.18 =
* Bug fix: Fix for MPS (Multi Parcel Shipment) creation

= 2.0.17 =
* New feature: Possibility to send shipping labels to custom URL on creation to help automations
* New feature: You can define if shipping label is to be displayed on a browser or downloaded
* New feature: add new hooks: pakettikauppa_prepare_create_shipment and pakettikauppa_post_create_shipment.
* Bug fixes: Pickup point search now uses shipping method codes instead of shipping provider names

= 2.0.16 =
* New feature: Allow creation of COD shipments from custom shipment creation
* New feature: Allow using all shipping methods and automatically display it's pickup points if available
* Enhancements: refactored COD functionality
* Enhancements: enhancements to automatic testing
* Bug fixes: misc fixes to translations, checking variables, etc

= 2.0.15.1 =
* Bug fixes

= 2.0.15 =
* New feature: Alternative show pickup points as radio button list
* New feature: New UI for creating custom shipping label
* New feature: Enable multi parcel shipments
* Enhancements: Added more data validation to admin functions, COD settings to own section in settings, admin CSS fix

= 2.0.14 =
* New feature: show label code (Helpostikoodi / aktivointikoodi) if it's present
* Enhancements: Actions from order view are now handled as Ajax -requests
* Enhancements: Added more data validation to admin functions
* Bug fix: compatibility issue with action scheduler is now fixed
* Fix: Mysql support for automatic Travis tests

= 2.0.13 =
* New feature: Add selected pickup point information to the confirmation email
* Enhancements: Added more data validation to admin functions

= 2.0.12 =
* New feature: Add HS tariff number and country of origin to products shipping options
* New feature: Send contents of the order with the shipment creation method
* Enhancements: redo functions using deprecated get_product_from_item function

= 2.0.11 =
* Enhancement: Orders action will open the PDF in the new window/tab

= 2.0.10 =
* New feature: Define shipping method for non-Pakettikauppa shipments
* New feature: Define additional services for non-Pakettikauppa shipments
* Enhancement: Don't create shipping label from orders page for local pickups
* Fixes: Don't display error in the logs, if provided shipping method in the checkout page does not have instance_id
* New feature: Implement hooks to be able to implement Pakettikauppa features in another plugins
* Bug fix: Validate pickup point selection in non-Pakettikauppa shipping methods

= 2.0.9 =
* New feature: support for free shipping coupon code
* Bug fix: Quick action printing of shipping label now prints with correct shipping service

= 2.0.8 =
* New feature: Create quick action to print shipping label for a order

= 2.0.7 =
* New feature: Create and fetch shipping labels from multiple orders

= 2.0.6 =
* Fix an error message when there is no settings available for shipping method

= 2.0.5 =
* Changed the way array is merged with another array

= 2.0.4 =
* Added possibility to add pickup point chooser to any shipping method defined as shipping zone shipping method

= 2.0.3 =
* Allow Pakettikauppa to create shipping labels even if original shipping method is not from Pakettikauppa shipping method.
* Allow shipping method to be changed (does not allow pickup point to be changed).

= 2.0.2 =
* Internal changes / fixes / improvements
* In latest woo pickup points only worked if shipping had testing on

= 2.0.1 =
* Fixed shipping method id in the settings

= 2.0.0 =
* Shipping zone and shipping class support!
* COD-support if payment method is WooCommerce COD (cash-on-delivery) payment method (or extended class)
* This update breaks pricing from 1.x branches - You have to create new prices by adding Pakettikauppa as a new shipping method to Shipping zones

= 1.1.8 =
* Alternative name for the shipping method

= 1.1.7 =
* Updated readme -file with new tags
* Travis-CI Updates
* Sort shipping methods based on shipping code

= 1.1.6 =
* Fixed printing of label

= 1.1.5 =
* Possibility to choose which shiment method to use when none is selected

= 1.1.4 =
* Fixes issues with VAT calculation with shipping costs. You have to enter shipping costs in the settings including VAT. In the Tax -settings page, when chosen option "Shipping tax class based on cart items", it will calculate the shipping taxes "backwards" from the given price correctly. For instance if you have 14% and 24% VAT products in the cart then shipping costs will have also 14% and 24% VAT in the correct proportions.
* Small internal code refactoring changes

= 1.0 =
* Initial release for General Availability.

== Upgrade Notice ==
= 2.0.0 =
Breaks compatibility with 1.x -settings

= 1.0 =
This plugin follows [semantic versioning](https://semver.org). Take it into account when updating.
