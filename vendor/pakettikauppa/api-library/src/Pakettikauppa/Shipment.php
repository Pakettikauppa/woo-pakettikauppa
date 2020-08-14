<?php

namespace Pakettikauppa;

use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;

/**
 * Class Shipment
 *
 * A container for all shipment data.
 *
 * For the API to work a shipment must have a Sender, Receiver, $shipping_method and at least 1 Parcel.
 * For shipments with more then 1 Parcel, Multi-parcel shipment (3102) additional service must be added.
 *
 * @package Pakettikauppa
 */
class Shipment
{
    /**
     * @var Sender
     */
    private $sender;
    /**
     * @var Receiver
     */
    private $receiver;
    /**
     * @var integer
     */
    private $shipping_method;
    /**
     * @var AdditionalService[]
     */
    private $additional_services;
    /**
     * @var Info
     */
    private $shipment_info;
    /**
     * @var Parcel[]
     */
    private $parcels;

    /**
     * @var string
     */
    private $tracking_code;

    /**
     * @var string
     */
    private $reference;

    /**
     * @var string base64 encoded shipping label pdf
     */
    private $pdf;

    /**
     * @var bool
     */
    private $print_return_label;

    public function __construct()
    {
        $this->additional_services  = array();
        $this->print_return_label   = false;
        $this->parcels              = array();
    }

    public function addAdditionalService(AdditionalService $additional_service)
    {
        $this->additional_services[] = $additional_service;
    }

    public function getAdditionalServices()
    {
        return $this->additional_services;
    }

    public function addParcel(Parcel $parcel)
    {
        $this->parcels[] = $parcel;
    }

    public function getParcels()
    {
        return $this->parcels;
    }

    /**
     * @param boolean $include If true a return shipping label is also generated and appended to the shipping label pdf, if the shipping method supports returns.
     */
    public function includeReturnLabel($include)
    {
        if($include)
            $this->print_return_label = true;
        else
            $this->print_return_label = false;
    }

    public function setSender(Sender $sender)
    {
        $this->sender = $sender;
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function setReceiver(Receiver $receiver)
    {
        $this->receiver = $receiver;
    }

    public function getReceiver()
    {
        return $this->receiver;
    }

    public function setShipmentInfo(Info $info)
    {
        $this->shipment_info = $info;
    }

    public function setShippingMethod($shipping_method_code)
    {
        $this->shipping_method = $shipping_method_code;
    }

    public function getShippingMethod()
    {
        return $this->shipping_method;
    }

    /**
     * @return string
     */
    public function getTrackingCode()
    {
        return $this->tracking_code;
    }

    /**
     * @param string $tracking_code
     */
    public function setTrackingCode($tracking_code)
    {
        $this->tracking_code = $tracking_code;
    }

    /**
     * Sets a pickup point for the shipment.
     *
     * @param $pickup_point_id
     */
    public function setPickupPoint($pickup_point_id)
    {
        $service = new AdditionalService();
        $service->setServiceCode(2106); // alternative pickup point
        $service->addSpecifier('pickup_point_id', $pickup_point_id);

        $this->addAdditionalService($service);
    }

    /**
     * @return string
     */
    public function getPdf()
    {
        return $this->pdf;
    }

    /**
     * @param string $pdf
     */
    public function setPdf($pdf)
    {
        $this->pdf = $pdf;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }


    /**
     * Builds the xml from given data
     *
     * @return SimpleXMLElement
     */
    private function toXml()
    {
        $xml = new SimpleXMLElement('<eChannel/>');

        $routing = $xml->addChild('ROUTING');
        $routing->addChild("Routing.Time", time());

        $shipment = $xml->addChild('Shipment');

        $sender = $shipment->addChild('Shipment.Sender');
        $sender->addChild('Sender.Name1', $this->sender->getName1());
        $sender->addChild('Sender.Name2', $this->sender->getName2());
        $sender->addChild('Sender.Addr1', $this->sender->getAddr1());
        $sender->addChild('Sender.Addr2', $this->sender->getAddr2());
        $sender->addChild('Sender.Addr3', $this->sender->getAddr3());
        $sender->addChild('Sender.Postcode', $this->sender->getPostcode());
        $sender->addChild('Sender.City', $this->sender->getCity());
        $sender->addChild('Sender.Country', $this->sender->getCountry());
        $sender->addChild('Sender.Phone', $this->sender->getPhone());
        $sender->addChild('Sender.Vatcode', $this->sender->getVatcode());
        $sender->addChild('Sender.Email', $this->sender->getEmail());

        $receiver = $shipment->addChild('Shipment.Recipient');
        $receiver->addChild('Recipient.Name1', $this->receiver->getName1());
        $receiver->addChild('Recipient.Name2', $this->receiver->getName2());
        $receiver->addChild('Recipient.Addr1', $this->receiver->getAddr1());
        $receiver->addChild('Recipient.Addr2', $this->receiver->getAddr2());
        $receiver->addChild('Recipient.Addr3', $this->receiver->getAddr3());
        $receiver->addChild('Recipient.Postcode', $this->receiver->getPostcode());
        $receiver->addChild('Recipient.City', $this->receiver->getCity());
        $receiver->addChild('Recipient.Country', $this->receiver->getCountry());
        $receiver->addChild('Recipient.Phone', $this->receiver->getPhone());
        $receiver->addChild('Recipient.Vatcode', $this->receiver->getVatcode());
        $receiver->addChild('Recipient.Email', $this->receiver->getEmail());

        $consignment = $shipment->addChild('Shipment.Consignment');
        $consignment->addChild('Consignment.Reference', $this->shipment_info->getReference());
        $consignment->addChild('Consignment.Product', $this->shipping_method);
        $consignment->addChild('Consignment.Contentcode', $this->shipment_info->getContentCode());
        $consignment->addChild('Consignment.ReturnInstruction', $this->shipment_info->getReturnInstruction());
        $consignment->addChild('Consignment.Invoicenumber', $this->shipment_info->getInvoiceNumber());
        $consignment->addChild('Consignment.Merchandisevalue', $this->shipment_info->getMerchandiseValue());

        foreach ($this->additional_services as $service)
        {
            if($service->isValid())
            {
                $additional_service = $consignment->addChild('Consignment.AdditionalService');
                $additional_service->addChild('AdditionalService.ServiceCode', $service->getServiceCode());

                foreach ($service->getSpecifiers() as $nameValue)
                {
                    $specifier          = $additional_service->addChild('AdditionalService.Specifier', $nameValue[1]);
                    $specifier['name']  = $nameValue[0];
                }
            }
        }

        $additional_info = $consignment->addChild('Consignment.AdditionalInfo');
        $additional_info->addChild('AdditionalInfo.Text', $this->shipment_info->getAdditionalInfoText());

        foreach ($this->parcels as $parcel)
        {
            $parcel_xml = $consignment->addChild('Consignment.Parcel');
            $parcel_xml->addChild('Parcel.Reference', $parcel->getReference());
            $parcel_xml->addChild('Parcel.PackageType', $parcel->getPackageType());
            $weight = $parcel_xml->addChild('Parcel.Weight', $parcel->getWeight());
            $volume = $parcel_xml->addChild('Parcel.Volume', $parcel->getVolume());

            // x, y, z parcel dimensions in cm, used by courier services

            $volume['x'] = $parcel->getX();
            $volume['y'] = $parcel->getY();
            $volume['z'] = $parcel->getZ();

            $weight['unit'] = 'kg';
            $volume['unit'] = 'm3';

            $parcel_xml->addChild('Parcel.Infocode', $parcel->getInfocode());
            $parcel_xml->addChild('Parcel.Contents', $parcel->getContents());

            if($this->print_return_label)
                $parcel_xml->addChild('Parcel.ReturnService', 2108);

            foreach ($parcel->getContentLines() as $content_line)
            {
                $content = $parcel_xml->addChild('Parcel.contentline');
                $content->addChild('contentline.description', $content_line->getDescription());
                $content->addChild('contentline.quantity', $content_line->getQuantity());
                $content->addChild('contentline.currency', $content_line->getCurrency());
                $content->addChild('contentline.netweight', $content_line->getNetweight());
                $content->addChild('contentline.value', $content_line->getValue());
                $content->addChild('contentline.countryoforigin', $content_line->getCountryOfOrigin());
                $content->addChild('contentline.tariffcode', $content_line->getTariffCode());
            }
        }

        return $xml;
    }

    /**
     * Returns the class data as an xml string
     *
     * @return string $xml
     */
    public function asXml()
    {
        return $this->toXml()->asXML();
    }

    /**
     * Returns the class data as a SimpleXmlElement
     *
     * @return \SimpleXMLElement
     */
    public function asSimpleXml()
    {
        return $this->toXml();
    }
}