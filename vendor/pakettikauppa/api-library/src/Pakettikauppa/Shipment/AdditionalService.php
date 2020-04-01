<?php

namespace Pakettikauppa\Shipment;

/**
 * Class AdditionalService
 *
 * Possible additional services include:
 *
 * 2106 = Alternative pickip point, specifiers: [pickup_point_id]
 * 3101 = Cash on delivery, specifiers: [amount, account, reference, codbic]
 * 3102 = Multiple parcel shipment, specifiers [count]
 * 3104 = Fragile
 * 3174 = Large
 *
 * For a complete list of possible additional services use listAdditionalServices() in the Client class.
 * Also note that available additional services depend on the shipping method selected.
 *
 * @package Pakettikauppa\Shipment
 */
class AdditionalService
{
    /**
     * @var integer
     */
    protected $service_code;
    /**
     * @var array
     */
    protected $specifiers;

    public function __construct()
    {
        $this->specifiers = array();
    }

    /**
     * @return int
     */
    public function getServiceCode()
    {
        return $this->service_code;
    }

    /**
     * @param int $service_code
     */
    public function setServiceCode($service_code)
    {
        $this->service_code = $service_code;
    }

    /**
     * @return array
     */
    public function getSpecifiers()
    {
        return $this->specifiers;
    }

    public function getSpecifier($name)
    {
        foreach($this->specifiers as $specifier) {
            if($specifier[0] == $name) {
                return $specifier[1];
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $value
     */
    public function addSpecifier($name, $value)
    {
        $this->specifiers[] = [$name, $value];
    }

    /**
     * Checks that service_code is numeric and that expected specifiers exist with certain service_code values.
     *
     * @return bool
     */
    public function isValid()
    {
        // alternative pickup point
        if($this->service_code == 2106) {
            if($this->getSpecifier('pickup_point_id') === null)
                return false;
        }

        // cash on delivery (Postiennakko/Bussiennakko)
        if($this->service_code == 3101) {
            $expected_params = array('amount', 'account', 'reference', 'codbic');

            foreach($expected_params as $param)
            {
                if($this->getSpecifier($param) === null)
                    return false;
            }
        }

        // multipacket shipment requires package count
        if($this->service_code == 3102) {
            if($this->getSpecifier('count') === null)
                return false;

            if(!is_numeric($this->getSpecifier('count')))
                return false;
        }

        if($this->service_code == 3111) {
            if($this->getSpecifier('insurancevalue') === null)
                return false;
        }

        if($this->service_code == 3120) {
            if($this->getSpecifier('deliverytime'))
                return false;
        }

        if($this->service_code == 3143) {
            if($this->getSpecifier('lqweight') === null or $this->getSpecifier('lqcount') === null)
                return false;
        }

        if(!is_numeric($this->service_code)) {
            return false;
        }

        return true;
    }


}
