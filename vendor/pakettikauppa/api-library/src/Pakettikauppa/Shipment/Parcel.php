<?php

namespace Pakettikauppa\Shipment;

/**
 * Class Parcel
 * @package Pakettikauppa\Shipment
 */
class Parcel
{
    /**
     * @var
     */
    public $reference;
    /**
     * @var string
     */
    public $package_type;
    /**
     * @var
     */
    public $weight;
    /**
     * @var
     */
    public $volume;
    /**
     * @var
     */
    public $infocode;
    /**
     * @var
     */
    public $contents;

    /**
     * @var ContentLine[]
     */
    public $content_lines;

    /**
     * Parcel constructor.
     */
    public function __construct()
    {
        $this->content_lines    = array();
        $this->package_type     = 'PC';
    }

    public function addContentLine(ContentLine $content_line)
    {
        $this->content_lines[] = $content_line;
    }

    /**
     * @return mixed
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param mixed $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    /**
     * @return string
     */
    public function getPackageType()
    {
        return $this->package_type;
    }

    /**
     * @param string $package_type
     */
    public function setPackageType($package_type)
    {
        $this->package_type = $package_type;
    }

    /**
     * @return mixed
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param mixed $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    /**
     * @return mixed
     */
    public function getVolume()
    {
        return $this->volume;
    }

    /**
     * @param mixed $volume
     */
    public function setVolume($volume)
    {
        $this->volume = $volume;
    }

    /**
     * @return mixed
     */
    public function getInfocode()
    {
        return $this->infocode;
    }

    /**
     * @param mixed $infocode
     */
    public function setInfocode($infocode)
    {
        $this->infocode = $infocode;
    }

    /**
     * @return mixed
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @param mixed $contents
     */
    public function setContents($contents)
    {
        $this->contents = $contents;
    }

    /**
     * @return ContentLine[]
     */
    public function getContentLines()
    {
        return $this->content_lines;
    }

}