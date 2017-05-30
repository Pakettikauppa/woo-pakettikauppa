<?php

namespace Pakettikauppa\Shipment;

/**
 * Class Info
 *
 * General information about the shipment and settings
 *
 * @package Pakettikauppa\Shipment
 */
class Info
{

    /**
     * @var string A reference number that is printed on the shipping label, an order id is a good choice.
     */
    public $reference;
    /**
     * @var string Content code for international shipments. M = Merchandise
     */
    public $content_code = 'M';
    /**
     * @var string Return instruction for international shipments. E = Return by most economical route.
     */
    public $return_instruction = 'E';
    /**
     * @var string
     */
    public $invoice_number;
    /**
     * @var double Value of merchandise, used in international shipments
     */
    public $merchandise_value;
    /**
     * @var string Currency for value of merchandise
     */
    public $currency = 'EUR';
    /**
     * @var string
     */
    public $additional_info_text;

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
     *
     * @return string
     */
    public function getContentCode()
    {
        return $this->content_code;
    }

    /**
     * Set content code for international shipments. Possible values: D, S, G, M, E
     *
     * D = Documents
     * S = Sample
     * G = Gift
     * M = Merchandise
     * E = Envelope
     *
     * @param string $content_code
     */
    public function setContentCode($content_code)
    {
        $this->content_code = $content_code;
    }

    /**
     * @return string
     */
    public function getReturnInstruction()
    {
        return $this->return_instruction;
    }

    /**
     * Sets return instruction for international shipments. Possible values: H, L, E
     *
     * H = Treat as abandoned
     * L = Return immediately by air
     * E = Return by most economical route
     *
     * @param string $return_instruction
     */
    public function setReturnInstruction($return_instruction)
    {
        $this->return_instruction = $return_instruction;
    }

    /**
     * @return string
     */
    public function getInvoiceNumber()
    {
        return $this->invoice_number;
    }

    /**
     * @param string $invoice_number
     */
    public function setInvoiceNumber($invoice_number)
    {
        $this->invoice_number = $invoice_number;
    }

    /**
     * @return float
     */
    public function getMerchandiseValue()
    {
        return $this->merchandise_value;
    }

    /**
     * @param float $merchandise_value
     */
    public function setMerchandiseValue($merchandise_value)
    {
        $this->merchandise_value = $merchandise_value;
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
     * @return string
     */
    public function getAdditionalInfoText()
    {
        return $this->additional_info_text;
    }

    /**
     * @param string $additional_info_text
     */
    public function setAdditionalInfoText($additional_info_text)
    {
        $this->additional_info_text = $additional_info_text;
    }



}