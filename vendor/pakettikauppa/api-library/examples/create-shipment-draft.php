<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;

use Pakettikauppa\Client;

$shipment = new Shipment();
$sender = new Sender();

$sender->setName1('Stuff from the internet Ltd');
$sender->setAddr1('Somestreet 123');
$sender->setPostcode('33100');
$sender->setCity('Tampere');
$sender->setCountry('FI');

$receiver = new Receiver();
$receiver->setName1('John Doe');
$receiver->setAddr1('Some other street 321');
$receiver->setPostcode('39530');
$receiver->setCity('Kilvakkala');
$receiver->setCountry('FI');
$receiver->setEmail('john@doe.com');
$receiver->setPhone('358 123 4567890');

$info = new Info();
$info->setReference('12344');

$parcel = new Parcel();
$parcel->setReference('1234456');
$parcel->setWeight(1.5); // kg
$parcel->setVolume(0.001); // m3
$parcel->setContents('Stuff and thingies');
$shipment->addParcel($parcel);

$shipment->setShippingMethod(2103); // shipping_method_code that you can get by using listShippingMethods()
$shipment->setSender($sender);
$shipment->setReceiver($receiver);
$shipment->setShipmentInfo($info);

try {
    $client = new Client(array('test_mode' => true));

    $uuid = $client->createShipmentDraft($shipment);

    $tracking_code = $client->confirmShipmentDraft($uuid);

    $response_xml = $client->fetchShippingLabels($tracking_code);

    file_put_contents($tracking_code . '.pdf', base64_decode($response_xml->{'response.file'}));
} catch (\Exception $ex)  {
    echo $ex->getMessage();
}
