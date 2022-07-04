<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Pakettikauppa\Client;

$client = new Client(array('test_mode' => true));
$client->setLogClosure(static function($message, $level){
  file_put_contents(__DIR__ . '/api-requests.log', $message, FILE_APPEND);
});

$result = $client->searchPickupPointsByText('Keskustori 1, 33100 Tampere');

var_dump($result);