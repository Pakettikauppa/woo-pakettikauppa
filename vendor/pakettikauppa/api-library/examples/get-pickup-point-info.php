<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);
require '../vendor/autoload.php';
use Pakettikauppa\Client;

$client = new Client(array('test_mode' => true));

// Posti example

$output= $client->getPickupPointInfo('905253201','2103');
var_dump($output);

//Matkahuolto example
$output= $client->getPickupPointInfo('8246','matkahuolto');

var_dump($output);

// Db Schenker example

$output= $client->getPickupPointInfo('5061','80010');

var_dump($output);
