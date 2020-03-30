<?php

/**
 * Class Test_Shipment
 *
 * @package Woocommerce_Pakettikauppa
 */
class Test_Shipment extends WP_UnitTestCase {

  /**
   * Tests that the tracking code is set correctly
   */
  public function test_tracking_code() {
    $shipment = new Pakettikauppa\Shipment();

    $tracking_code = 00000000000;
    $shipment->setTrackingCode($tracking_code);
    $this->assertEquals($tracking_code, $shipment->getTrackingCode());
  }


  /**
   * Tests that the pdf file is set correctly
   */
  public function test_pdf() {
    $shipment = new Pakettikauppa\Shipment();
    $pdf      = 'test.pdf';
    $shipment->setPdf($pdf);
    $this->assertEquals($pdf, $shipment->getPdf());
  }


  /**
   * Tests that the shipment reference is set correctly
   */
  public function test_reference() {
    $shipment = new Pakettikauppa\Shipment();

    $reference = 'test_reference';
    $shipment->setReference($reference);
    $this->assertEquals($reference, $shipment->getReference());
  }

  /**
   * Test that the shipment xml is generated correctly
   */
  public function test_xml() {
    $sender   = new Pakettikauppa\Shipment\Sender();
    $receiver = new Pakettikauppa\Shipment\Receiver();
    $info     = new Pakettikauppa\Shipment\Info();
    $shipment = new Pakettikauppa\Shipment();

    $data = $this->generate_xml_test_data();

    $sender->setName1($data['Sender.Name1']);
    $sender->setName2($data['Sender.Name2']);
    $sender->setAddr1($data['Sender.Addr1']);
    $sender->setAddr2($data['Sender.Addr2']);
    $sender->setAddr3($data['Sender.Addr3']);
    $sender->setPostcode($data['Sender.Postcode']);
    $sender->setCity($data['Sender.City']);
    $sender->setCountry($data['Sender.Country']);
    $sender->setPhone($data['Sender.Phone']);
    $sender->setVatCode($data['Sender.Vatcode']);

    $receiver->setName1($data['Recipient.Name1']);
    $receiver->setName2($data['Recipient.Name2']);
    $receiver->setAddr1($data['Recipient.Addr1']);
    $receiver->setAddr2($data['Recipient.Addr2']);
    $receiver->setAddr3($data['Recipient.Addr3']);
    $receiver->setPostcode($data['Recipient.Postcode']);
    $receiver->setCity($data['Recipient.City']);
    $receiver->setCountry($data['Recipient.Country']);
    $receiver->setPhone($data['Recipient.Phone']);
    $receiver->setVatCode($data['Recipient.Vatcode']);
    $receiver->setEmail($data['Recipient.Email']);

    $info->setReference($data['Consignment.Reference']);
    $info->setContentCode($data['Consignment.Contentcode']);
    $info->setReturnInstruction($data['Consignment.ReturnInstruction']);
    $info->setInvoiceNumber($data['Consignment.Invoicenumber']);
    $info->setMerchandiseValue($data['Consignment.Merchandisevalue']);
    $info->setAdditionalInfoText($data['AdditionalInfo.Text']);

    foreach ( $data['AdditionalServices'] as $service ) {
      $add_service = new Pakettikauppa\Shipment\AdditionalService();
      $add_service->setServiceCode($service['AdditionalService.ServiceCode']);

      foreach ( $service['Specifiers'] as $key => $value ) {
        $add_service->addSpecifier($key, $value);
      }
      $shipment->addAdditionalService($add_service);
    }

    $shipment->setShippingMethod($data['Consignment.Product']);
    $shipment->setSender($sender);
    $shipment->setReceiver($receiver);
    $shipment->setShipmentInfo($info);
    $shipment->includeReturnLabel(true);

    foreach ( $data['Parcels'] as $parcel ) {
      $add_parcel = new Pakettikauppa\Shipment\Parcel();
      $add_parcel->setReference($parcel['Parcel.Reference']);
      $add_parcel->setPackageType($parcel['Parcel.PackageType']);
      $add_parcel->setWeight($parcel['Parcel.Weight']);
      $add_parcel->setVolume($parcel['Parcel.Volume']);
      $add_parcel->setInfocode($parcel['Parcel.Infocode']);
      $add_parcel->setContents($parcel['Parcel.Contents']);

      foreach ( $parcel['Parcel.ContentLines'] as $content_line ) {
        $add_content_line = new Pakettikauppa\Shipment\ContentLine();
        $add_content_line->setDescription($content_line['contentline.description']);
        $add_content_line->setQuantity($content_line['contentline.quantity']);
        $add_content_line->setCurrency($content_line['contentline.currency']);
        $add_content_line->setNetweight($content_line['contentline.netweight']);
        $add_content_line->setValue($content_line['contentline.value']);
        $add_content_line->setCountryOfOrigin($content_line['contentline.countryoforigin']);
        $add_content_line->setTariffCode($content_line['contentline.tariffcode']);

        $add_parcel->addContentLine($add_content_line);
      }
      $shipment->addParcel($add_parcel);
    }

    $xml           = $shipment->asSimpleXml();
    $reference_xml = $this->generate_test_xml();

    // Remove timestamp from the actual XML file before assert
    $time = $xml->ROUTING; // @codingStandardsIgnoreLine
    $dom  = dom_import_simplexml($time);
    $dom->parentNode->removeChild($dom); // @codingStandardsIgnoreLine

    $this->assertXmlStringEqualsXmlString($reference_xml->asXML(), $xml->asXML());
  }

  /**
   * Function for generating the dummy data, you can specify your custom test values here.
   */
  private function generate_xml_test_data() {
    return array(
      'Sender.Name1'                  => 'Testsender1',
      'Sender.Name2'                  => 'Testsender2',
      'Sender.Addr1'                  => 'Testsenderaddress1',
      'Sender.Addr2'                  => 'Testsenderaddress2',
      'Sender.Addr3'                  => 'Testsenderaddress3',
      'Sender.Postcode'               => '12345',
      'Sender.City'                   => 'Testsendercity',
      'Sender.Country'                => 'Testsendercountry',
      'Sender.Phone'                  => '0123456',
      'Sender.Vatcode'                => '0123456',
      'Recipient.Name1'               => 'Testrecipient1',
      'Recipient.Name2'               => 'Testrecipient1',
      'Recipient.Addr1'               => 'Testrecipientaddress1',
      'Recipient.Addr2'               => 'Testrecipientaddress2',
      'Recipient.Addr3'               => 'Testrecipientaddress3',
      'Recipient.Postcode'            => '54321',
      'Recipient.City'                => 'Testrecipientcity',
      'Recipient.Country'             => 'Testrecipientcountry',
      'Recipient.Phone'               => '654321',
      'Recipient.Vatcode'             => '654321',
      'Recipient.Email'               => 'testrecipient@testrecipient.com',
      'Consignment.Reference'         => 'Testreference',
      'Consignment.Product'           => 123,
      'Consignment.Contentcode'       => '1234567',
      'Consignment.ReturnInstruction' => 'We do not accept any returns.',
      'Consignment.Invoicenumber'     => '123456789',
      'Consignment.Merchandisevalue'  => '100',
      'AdditionalServices'            => array(
        'AdditionalService1' => array(
          'AdditionalService.ServiceCode' => '1234',
          'Specifiers'                    => array(
            'Testspecifierkey11' => 'Testspecifiervalue11',
            'Testspecifierkey12' => 'Testspecifiervalue12',
          ),
        ),
        'AdditionalService2' => array(
          'AdditionalService.ServiceCode' => '2345',
          'Specifiers'                    => array(
            'Testspecifierkey21' => 'Testspecifiervalue21',
            'Testspecifierkey22' => 'Testspecifiervalue22',
          ),
        ),
      ),
      'Parcels'                       => array(
        'Parcel1' => array(
          'Parcel.Reference'    => 'Testreference1',
          'Parcel.PackageType'  => 'Testpackagetype1',
          'Parcel.Weight'       => '100',
          'Parcel.Volume'       => '100',
          'Parcel.Infocode'     => '123456',
          'Parcel.Contents'     => 'Lots of stuff',
          'Parcel.ContentLines' => array(
            'ContentLine1' => array(
              'contentline.description'     => 'Cool Stuff',
              'contentline.quantity'        => 100,
              'contentline.currency'        => 'eur',
              'contentline.netweight'       => 123.4,
              'contentline.value'           => 145.6,
              'contentline.countryoforigin' => 'Testcountry',
              'contentline.tariffcode'      => 'Testtariffcode1',
            ),
          ),
        ),
        'Parcel2' => array(
          'Parcel.Reference'    => 'Testreference2',
          'Parcel.PackageType'  => 'Testpackagetype2',
          'Parcel.Weight'       => '200',
          'Parcel.Volume'       => '200',
          'Parcel.Infocode'     => '234567',
          'Parcel.Contents'     => 'Not so much stuff',
          'Parcel.ContentLines' => array(
            'ContentLine1' => array(
              'contentline.description'     => 'Not so cool Stuff',
              'contentline.quantity'        => 200,
              'contentline.currency'        => 'eur',
              'contentline.netweight'       => 234.5,
              'contentline.value'           => 3265.12,
              'contentline.countryoforigin' => 'Testcountry',
              'contentline.tariffcode'      => 'Testtariffcode2',
            ),
          ),
        ),
      ),
      'AdditionalInfo.Text'           => 'No additional information provided.',
    );
  }


  /**
   * Function for generating the dummy test data in XML format
   */
  private function generate_test_xml() {
    $data     = $this->generate_xml_test_data();
    $xml_test = new SimpleXmlElement('<eChannel/>');

    $shipment_xml = $xml_test->addChild('Shipment');

    $sender_xml = $shipment_xml->addChild('Shipment.Sender');
    $sender_xml->addChild('Sender.Name1', $data['Sender.Name1']);
    $sender_xml->addChild('Sender.Name2', $data['Sender.Name2']);
    $sender_xml->addChild('Sender.Addr1', $data['Sender.Addr1']);
    $sender_xml->addChild('Sender.Addr2', $data['Sender.Addr2']);
    $sender_xml->addChild('Sender.Addr3', $data['Sender.Addr3']);
    $sender_xml->addChild('Sender.Postcode', $data['Sender.Postcode']);
    $sender_xml->addChild('Sender.City', $data['Sender.City']);
    $sender_xml->addChild('Sender.Country', $data['Sender.Country']);
    $sender_xml->addChild('Sender.Phone', $data['Sender.Phone']);
    $sender_xml->addChild('Sender.Vatcode', $data['Sender.Vatcode']);
      $sender_xml->addChild('Sender.Email');

    $receiver_xml = $shipment_xml->addChild('Shipment.Recipient');
    $receiver_xml->addChild('Recipient.Name1', $data['Recipient.Name1']);
    $receiver_xml->addChild('Recipient.Name2', $data['Recipient.Name2']);
    $receiver_xml->addChild('Recipient.Addr1', $data['Recipient.Addr1']);
    $receiver_xml->addChild('Recipient.Addr2', $data['Recipient.Addr2']);
    $receiver_xml->addChild('Recipient.Addr3', $data['Recipient.Addr3']);
    $receiver_xml->addChild('Recipient.Postcode', $data['Recipient.Postcode']);
    $receiver_xml->addChild('Recipient.City', $data['Recipient.City']);
    $receiver_xml->addChild('Recipient.Country', $data['Recipient.Country']);
    $receiver_xml->addChild('Recipient.Phone', $data['Recipient.Phone']);
    $receiver_xml->addChild('Recipient.Vatcode', $data['Recipient.Vatcode']);
    $receiver_xml->addChild('Recipient.Email', $data['Recipient.Email']);

    $consignment = $shipment_xml->addChild('Shipment.Consignment');
    $consignment->addChild('Consignment.Reference', $data['Consignment.Reference']);
    $consignment->addChild('Consignment.Product', $data['Consignment.Product']);
    $consignment->addChild('Consignment.Contentcode', $data['Consignment.Contentcode']);
    $consignment->addChild('Consignment.ReturnInstruction', $data['Consignment.ReturnInstruction']);
    $consignment->addChild('Consignment.Invoicenumber', $data['Consignment.Invoicenumber']);
    $consignment->addChild('Consignment.Merchandisevalue', $data['Consignment.Merchandisevalue']);

    foreach ( $data['AdditionalServices'] as $service ) {
      $additional_service = $consignment->addChild('Consignment.AdditionalService');
      $additional_service->addChild('AdditionalService.ServiceCode', $service['AdditionalService.ServiceCode']);

      foreach ( $service['Specifiers'] as $key => $value ) {
        $specifier         = $additional_service->addChild('AdditionalService.Specifier', $value);
        $specifier['name'] = $key;
      }
    }

    $additional_info_xml = $consignment->addChild('Consignment.AdditionalInfo');
    $additional_info_xml->addChild('AdditionalInfo.Text', $data['AdditionalInfo.Text']);

    foreach ( $data['Parcels'] as $parcel ) {
      $parcel_xml = $consignment->addChild('Consignment.Parcel');
      $parcel_xml->addChild('Parcel.Reference', $parcel['Parcel.Reference']);
      $parcel_xml->addChild('Parcel.PackageType', $parcel['Parcel.PackageType']);
      $weight = $parcel_xml->addChild('Parcel.Weight', $parcel['Parcel.Weight']);
      $volume = $parcel_xml->addChild('Parcel.Volume', $parcel['Parcel.Volume']);

      $weight['unit'] = 'kg';
      $volume['unit'] = 'm3';
        $volume['x'] = '';
        $volume['y'] = '';
        $volume['z'] = '';

      $parcel_xml->addChild('Parcel.Infocode', $parcel['Parcel.Infocode']);
      $parcel_xml->addChild('Parcel.Contents', $parcel['Parcel.Contents']);

      $parcel_xml->addChild('Parcel.ReturnService', 2108);

      foreach ( $parcel['Parcel.ContentLines'] as $content_line ) {
        $content = $parcel_xml->addChild('Parcel.contentline');
        $content->addChild('contentline.description', $content_line['contentline.description']);
        $content->addChild('contentline.quantity', $content_line['contentline.quantity']);
        $content->addChild('contentline.currency', $content_line['contentline.currency']);
        $content->addChild('contentline.netweight', $content_line['contentline.netweight']);
        $content->addChild('contentline.value', $content_line['contentline.value']);
        $content->addChild('contentline.countryoforigin', $content_line['contentline.countryoforigin']);
        $content->addChild('contentline.tariffcode', $content_line['contentline.tariffcode']);
      }
    }

    return $xml_test;
  }
}
