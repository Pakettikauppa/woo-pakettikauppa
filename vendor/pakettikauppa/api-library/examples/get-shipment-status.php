<?php
/**
 * Lists tracking and general info about a shipment.
 *
 */
error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Pakettikauppa\Client;


$tracking_code  = 'JJFITESTLABEL100';

$client = new Client(array('test_mode' => true));

$result = $client->getShipmentStatus($tracking_code);

var_dump($result);