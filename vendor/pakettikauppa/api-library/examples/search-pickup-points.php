<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Pakettikauppa\Client;

$client = new Client(array('test_mode' => true));

$result = $client->searchPickupPoints('00100');

var_dump(json_decode($result));