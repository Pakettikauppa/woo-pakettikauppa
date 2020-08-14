<?php

namespace Pakettikauppa;

class Client
{
    /**
     * @var array|null
     */
    private $configs            = null;

    /**
     * @var string|null
     */
    private $api_key            = null;
    /**
     * @var string|null
     */
    private $secret             = null;
    /**
     * @var string|null
     */
    private $base_uri           = null;
    /**
     * @var string
     */
    private $user_agent         = 'pk-client-lib/2.0';
    /**
     * @var null
     */
    private $comment            = null;
    /**
     * @var null
     */
    private $response           = null;

    /**
     * @var null
     */
    private $use_posti_auth     = null;
    /**
     * @var null
     */
    private $posti_auth_url     = null;
    /**
     * @var null
     */
    private $access_token       = null;

    private $http_response_code;
    private $http_error;

    /**
     * Client constructor.
     *
     * ```php
     *      $client = new Client(array('test_mode' => true)); // Test mode
     *
     *      $client = new Client(array('api_key' => '123ABC...', 'secret' => 'ABC321...')); // Production mode
     *
     *      $client = new Client(array(
     *              'test_config' => array('api_key' => '123ABC...', 'secret' => 'ABC321...', 'base_uri' => 'https://testserver'),
     *              'staging_config' => array('api_key' => '123ABC...', 'secret' => 'ABC321...', 'base_uri' => 'https://apitest.pakettikauppa.fi'),
     *          ),
     *          'test_config'
     *      );
     *
     *      $client = new Client(array(
     *              'posti_configs' => array(
     *                  'use_posti_auth' => true    // Changes authentication method to Posti OAuth 2.0 token
     *                  'api_key' => 'ABC123',      // Posti OAuth username
     *                  'secret' => 'ABC321...',    // Posti OAuth secret
     *                  'base_uri' => ''            // Url to Posti server
     *                  'posti_auth_url' => ''      // Url to Posti OAuth server
     *              )
     *          ),
     *          'posti_configs'
     *      );
     * ```
     *
     * @param array $configs Accepted params are api_key, secret & base_uri.
     * @param string $use_config If configs contains more then one possible configuration, $use_config defines which to use
     * @throws \Exception
     */
    public function __construct(array $configs = null, $use_config = null)
    {
        $this->configs = $configs;

        if( (isset($configs['test_mode']) and $configs['test_mode'] === true) or empty($configs))
        {
            $this->api_key      = '00000000-0000-0000-0000-000000000000';
            $this->secret       = '1234567890ABCDEF';
            $this->base_uri     = 'https://apitest.pakettikauppa.fi';
        }
        else
        {

            if(isset($configs['api_key'])) {
                $this->api_key  = $configs['api_key'];
            }

            if(isset($configs['secret'])) {
                $this->secret   = $configs['secret'];
            }

            if(isset($configs['base_uri'])) {
                $this->base_uri = $configs['base_uri'];
            } else {
                $this->base_uri = 'https://api.pakettikauppa.fi';
            }
        }

        if($use_config and isset($configs[$use_config]))
        {
            if(isset($this->configs[$use_config]))
            {
                foreach ($this->configs[$use_config] as $key => $value) {
                    if(property_exists($this, $key)) {
                        $this->{$key} = $value;
                    }
                }
            }
        }
    }

    /**
     * Fetches access token from Posti authentication service, if class param $posti_auth_url is not defined
     * in constructor uses test server. Cache the result until it expires.
     *
     * @see https://api.posti.fi/api-authentication.html
     * @return array
     */
    public function getToken()
    {
        if(empty($this->posti_auth_url)) {
            $this->posti_auth_url = 'https://oauth.barium.posti.com';
        }

        return json_decode($this->getPostiToken($this->posti_auth_url ."/oauth/token?grant_type=client_credentials", $this->api_key, $this->secret));
    }

    /**
     * Set the access token
     *
     * @param string $access_token
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * Sets comment for the request. You can set there information for Pakettikauppa. Like
     * "Generated from Foobar platform"
     *
     * @param string $comment
     */
    public function setComment($comment) {
        $this->comment = $comment;
    }
    /**
     * Posts shipment data to Pakettikauppa, if request was successful
     * sets $reference and $tracking_code params to given shipment.
     *
     * @param Shipment $shipment
     * @return string
     * @throws \Exception
     */
    public function createTrackingCode(Shipment &$shipment, $language = "fi")
    {
        return $this->createShipment($shipment, false, $language);
    }

    /**
     * @param Shipment $shipment
     * @param bool     $draft
     *
     * @throws \Exception
     */
    private function createShipment(Shipment &$shipment, $draft = false, $language = "fi")
    {
        $id             = str_replace('.', '', microtime(true));
        $shipment_xml   = $shipment->asSimpleXml();

        $shipment_xml->{"ROUTING"}->{"Routing.Id"}          = $id;

        if($this->use_posti_auth === true)
        {
            if(empty($this->access_token)) {
                throw new \Exception("Access token must be set");

            }

            $shipment_xml->{"ROUTING"}->{"Routing.Token"}       = $this->access_token;
        } else {
            $shipment_xml->{"ROUTING"}->{"Routing.Account"}     = $this->api_key;
            $shipment_xml->{"ROUTING"}->{"Routing.Version"}     = 2;
            $shipment_xml->{"ROUTING"}->{"Routing.Key"}         = hash_hmac('sha256',"{$this->api_key}{$id}", $this->secret);
        }

        if($this->comment != null) {
            $shipment_xml->{"ROUTING"}->{"Routing.Comment"} = $this->comment;
        }
        if (!$draft) {
            $response = $this->doPost("/prinetti/create-shipment?lang={$language}", null, $shipment_xml->asXML());
        } else {
            $response = $this->doPost('/prinetti/create-shipment-draft', null, $shipment_xml->asXML());
        }

        $response_xml = simplexml_load_string($response);

        if(!$response_xml) {
            throw new \Exception("Failed to load response xml: " .var_export($response, true));
        }

        $this->response = $response_xml;

        if($response_xml->{'response.status'} != 0) {
            throw new \Exception("Error: {$response_xml->{'response.status'}}, {$response_xml->{'response.message'}}");
        }

        $response_xml = $this->response;

        $shipment->setReference($response_xml->{'response.reference'});
        $shipment->setTrackingCode($response_xml->{'response.trackingcode'});

        return $response_xml->{'response.trackingcode'};
    }

    /**
     * Returns latest response as XML
     * 
     * @return \SimpleXMLElement
     */
    public function getResponse() {
        return $this->response;
    }

    /**a
     * Fetches the shipping label pdf for a given Shipment and
     * saves it as base64 encoded string to $pdf parameter on the Shipment.
     * The shipment must have $tracking_code and $reference set.
     *
     * @param Shipment $shipment
     * @return bool
     * @throws \Exception
     */
    public function fetchShippingLabel(Shipment &$shipment)
    {
        $response_xml = $this->fetchShippingLabels(array($shipment->getTrackingCode()));

        $shipment->setPdf($response_xml->{'response.file'});

        return $response_xml->{'response.file'};
    }

    /**
     * Fetches the shipping labels in one pdf for a given tracking_codes and
     * saves it as base64 encoded string inside XML.
     *
     * @param array $trackingCodes
     * @return xml
     * @throws \Exception
     */
    public function fetchShippingLabels($trackingCodes)
    {
        $id     = str_replace('.', '', microtime(true));
        $xml    = new \SimpleXMLElement('<eChannel/>');

        $routing = $xml->addChild('ROUTING');

        $routing->addChild('Routing.Id', $id);

        if($this->use_posti_auth === true) {
            if(empty($this->access_token)) {
                throw new \Exception("Access token must be set");
            }

            $routing->addChild('Routing.Token', $this->access_token);
        } else {
            $routing->addChild('Routing.Account', $this->api_key);
            $routing->addChild("Routing.Version", 2);
            $routing->addChild("Routing.Key", hash_hmac('sha256',"{$this->api_key}{$id}", $this->secret));
        }

        $label = $xml->addChild('PrintLabel');
        $label['responseFormat'] = 'File';

        if (!is_array($trackingCodes)) {
            $trackingCodes = [$trackingCodes];
        }
        foreach($trackingCodes as $trackingCode) {
            $label->addChild('TrackingCode', $trackingCode);
        }

        $response = $this->doPost('/prinetti/get-shipping-label', null, $xml->asXML());

        $response_xml = simplexml_load_string($response);

        if(!$response_xml) {
            throw new \Exception("Failed to load response xml: " . var_export($response, true));
        }

        $this->response = $response_xml;

        if($response_xml->{'response.status'} != 0) {
            throw new \Exception("Error: {$response_xml->{'response.status'}}, {$response_xml->{'response.message'}}");
        }

        return $response_xml;
    }
    /**
     *  Fetches a cost estimation for a shipment from Pakettikauppa.
     *  To get an estimation for a parcel the shipment must have shipping
     *  method set and at least 1 parcel with weight. When estimating cargo sender
     *  and receiver info should also be set.
     *
     *
     */
    public function estimateShippingCost(Shipment &$shipment)
    {
        $sender                 = $shipment->getSender();
        $receiver               = $shipment->getReceiver();
        $parcels                = $shipment->getParcels();
        $additional_services    = $shipment->getAdditionalServices();

        $shipment_data = array(
            'sender' => array(
                'postcode'  => $sender->getPostcode(),
                'country'   => $sender->getCountry(),
            ),
            'receiver' => array(
                'postcode'  => $receiver->getPostcode(),
                'country'   => $receiver->getCountry(),
            ),

            'product_code'          => $shipment->getShippingMethod(),
            'parcels'               => array(),
            'additional_services'   => array()
        );

        foreach ($parcels as $parcel)
        {
            $shipment_data['parcels'][] = array(
                'weight'    => $parcel->getWeight(),
                'volume'    => $parcel->getVolume(),
                'type'      => $parcel->getPackageType(),
                'x_dimension' => $parcel->getX(),
                'y_dimension' => $parcel->getY(),
                'z_dimension' => $parcel->getZ(),
            );
        }

        foreach ($additional_services as $service)
        {
            $shipment_data['additional_services'][] = $service->getServiceCode();
        }


        $response =  $this->doPost('/shipment/estimate-price', ['shipment' => json_encode($shipment_data)]);

        return json_decode($response);
    }


    /**
     * @param $tracking_code
     * @return array
     * @param string $lang
     * @return mixed
     */
    public function getShipmentStatus($tracking_code, $lang = 'fi')
    {
        return json_decode($this->doPost('/shipment/status', array('tracking_code' => $tracking_code, 'language' => $lang)));
    }

    /**
     * @return array
     */
    public function listAdditionalServices()
    {
        return json_decode($this->doPost('/additional-services/list', array()));
    }

    /**
     * @param $postcode
     *
     * @return bool|string
     */
    public function findCityByPostcode($postcode, $country) {
        return json_decode($this->doPost('/info/find-city', array('postcode' => $postcode, 'country' => $country)));
    }

    /**
     * @return array
     */
    public function listShippingMethods()
    {
        return json_decode($this->doPost('/shipping-methods/list', array()));
    }

    /**
     * Search pickup points.
     *
     * @param int $postcode
     * @param string $street_address
     * @param string $country
     * @param string $service_provider Limits results for to certain providers possible values are packet service codes (like 2103 for Postipaketti. Use listShippingMethods to get service codes).
     * @param int $limit 1 - 15
     * @return array
     */
    public function searchPickupPoints($postcode = null, $street_address = null, $country = null, $service_provider = null, $limit = 5)
    {
        if ( ($postcode == null && $street_address == null) || (trim($postcode) == '' && trim($street_address) == '') ) {
            return array();
        }

        $post_params = array(
            'postcode'          => (string) $postcode,
            'address'           => (string) $street_address,
            'country'           => (string) $country,
            'service_provider'  => (string) $service_provider,
            'limit'             => (int) $limit
        );

        return json_decode($this->doPost('/pickup-points/search', $post_params));
    }

    /**
     * Searches pickup points with a text query. For best results the query should contain a full address
     *
     * @param $query_text Text containing the full address, for example: "Keskustori 1, 33100 Tampere"
     * @param string $service_provider $service_provider Limits results for to certain providers possible values: Posti, Matkahuolto, Db Schenker.
     * @param int $limit 1 - 15
     * @return array
     */
    public function searchPickupPointsByText($query_text, $service_provider = null, $limit = 5)
    {
        if ( $query_text == null || trim($query_text) == '' ) {
            return array();
        }

        $post_params = array(
            'query'             => (string) $query_text,
            'service_provider'  => (string) $service_provider,
            'limit'             => (int) $limit
        );

        return json_decode($this->doPost('/pickup-points/search', $post_params));
    }

    /**
     *
     * Searches info about a single pickup point.
     *
     * @param string $point_id  is an id for a single pickup point. For example: 905253201
     * @param  $service is used to identify service provider. It can shipping method code like '2103'
     *          or name of the service provider: "Posti", "Matkahuolto" or "Db Schenker".
     * @return string|null
     */
    public function getPickupPointInfo($point_id, $service)
    {
        if (empty($service) or empty($point_id))
        {
            return null;
        }

        $post_params = array(
            'point_id'  => (string) $point_id,
            'timestamp' => time()
        );

        if(is_numeric($service))
        {
            $post_params['service_code'] = $service;
        }else {
            $post_params['service_provider'] = $service;
        }

        return $this->doPost('/pickup-point/info', $post_params);
    }

    /**
     * Creates an activation code (Helposti-koodi, aktivointikoodi) to shipment.
     * Only Posti shipments are supported for now.
     *
     * @param string $tracking_code
     *
     * @return mixed
     */
    public function createActivationCode($tracking_code)
    {
        if (empty($tracking_code)) {
            return null;
        }

        $post_params = array('tracking_code' => $tracking_code);

        return $this->doPost('/shipment/create-activation-code', $post_params);
    }

    /**
     * Creates draft shipment that can be created as real shipment later.
     *
     * @param Shipment $shipment
     *
     * @return string uuid
     * @throws \Exception
     */
    public function createShipmentDraft(Shipment &$shipment) {
        $this->createShipment($shipment, true);

        return $this->response->{'response.reference'}['uuid']->__toString();
    }

    /**
     * Creates real shipment from the draft shipment.
     *
     * @param $uuid
     *
     * @return string tracking code
     * @throws \Exception
     */
    public function confirmShipmentDraft($uuid)
    {
        $id     = str_replace('.', '', microtime(true));
        $xml    = new \SimpleXMLElement('<eChannel/>');

        $routing = $xml->addChild('ROUTING');
        $routing->addChild('Routing.Account', $this->api_key);
        $routing->addChild('Routing.Id', $id);
        $routing->addChild('Routing.Key', md5("{$this->api_key}{$id}{$this->secret}"));

        $label = $xml->addChild('ConfirmLabel');
        $label->addChild('Reference');
        $label->Reference['uuid'] = $uuid;

        $response = $this->doPost('/prinetti/confirm-shipment-draft', null, $xml->asXML());

        $response_xml = @simplexml_load_string($response);

        if(!$response_xml) {
            throw new \Exception("Failed to load response xml");
        }

        $this->response = $response_xml;

        if($response_xml->{'response.status'} != 0) {
            throw new \Exception("Error: {$response_xml->{'response.status'}}, {$response_xml->{'response.message'}}");
        }

        return $response_xml->{'response.trackingcode'}->__toString();
    }

    /**
     * @param $url_action
     * @param null $post_params
     * @param null $body
     * @return bool|string
     */
    private function doPost($url_action, $post_params = null, $body = null)
    {
        $headers = array();

        if(is_array($post_params))
        {
            if(!isset($post_params['api_key']))
                $post_params['api_key'] = $this->api_key;

            if(!isset($post_params['timestamp']))
                $post_params['timestamp'] = time();

            ksort($post_params);

            $post_params['hash'] = hash_hmac('sha256', join('&', $post_params), $this->secret);

            $post_data = http_build_query($post_params);
        }

        if(!is_null($body)) {
            $headers[] = 'Content-type: text/xml; charset=utf-8';
            $post_data = $body;
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $options = array(
                CURLOPT_POST            =>  1,
                CURLOPT_HEADER          =>  0,
                CURLOPT_URL             =>  $this->base_uri.$url_action,
                CURLOPT_FRESH_CONNECT   =>  1,
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_FORBID_REUSE    =>  1,
                CURLOPT_USERAGENT       =>  $this->user_agent,
                CURLOPT_TIMEOUT         =>  30,
                CURLOPT_HTTPHEADER      =>  $headers,
                CURLOPT_POSTFIELDS      =>  $post_data
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $this->http_response_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->http_error           = curl_errno($ch);
        $response = curl_exec($ch);

        return $response;
    }

    /**
     *
     * @param $url
     * @param $user
     * @param $secret
     * @return bool|string
     */
    private function getPostiToken($url, $user, $secret)
    {
        $headers = array();

        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Basic ' .base64_encode("$user:$secret");

        $options = array(
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_URL             => $url,
            CURLOPT_FRESH_CONNECT   => 1,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_FORBID_REUSE    => 1,
            CURLOPT_USERAGENT       => $this->user_agent,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTPHEADER      => $headers,

        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response                   = curl_exec($ch);

        curl_close($ch);

        return $response;
    }
}
