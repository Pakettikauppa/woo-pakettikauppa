<?php

namespace Pakettikauppa\Shipment;

/**
 * Class ContentLine
 *
 * Describes contents of parcels, used with international packages for customs clearance.
 *
 * @package Pakettikauppa\Shipment
 */
class ContentLine
{

    /**
     * @var string
     */
    public $description;
    /**
     * @var integer
     */
    public $quantity;
    /**
     * @var string
     */
    public $currency;
    /**
     * @var double
     */
    public $netweight;
    /**
     * @var double
     */
    public $value;
    /**
     * @var string
     */
    public $country_of_origin;
    /**
     * @var string
     */
    public $tariff_code;

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @return float
     */
    public function getNetweight()
    {
        return $this->netweight;
    }

    /**
     * @param float $netweight
     */
    public function setNetweight($netweight)
    {
        $this->netweight = $netweight;
    }

    /**
     * @return float
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param float $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getCountryOfOrigin()
    {
        return $this->country_of_origin;
    }

    /**
     * @param string $country_of_origin
     */
    public function setCountryOfOrigin($country_of_origin)
    {
        $this->country_of_origin = $country_of_origin;
    }

    /**
     * @return string
     */
    public function getTariffCode()
    {
        return $this->tariff_code;
    }

    /**
     * @param string $tariff_code
     */
    public function setTariffCode($tariff_code)
    {
        $this->tariff_code = $tariff_code;
    }


}