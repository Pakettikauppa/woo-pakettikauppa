<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);
require '../vendor/autoload.php';
use Pakettikauppa\Client;

$client = new Client(array('test_mode' => true));

$output = $client->findCityByPostcode('33100', 'FI');

var_dump($output);
