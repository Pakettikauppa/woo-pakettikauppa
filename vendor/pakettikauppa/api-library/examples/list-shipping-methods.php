<?php
/**
 * Lists currently available shipping methods with Pakettikauppa.
 *
 * Available options don't change often so saving and updating the result periodically is a good idea.
 */
error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Pakettikauppa\Client;

$client = new Client(array('test_mode' => true));


$result = $client->listShippingMethods();

var_dump($result);