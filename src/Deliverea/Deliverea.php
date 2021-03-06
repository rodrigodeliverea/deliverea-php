<?php
namespace Deliverea;

use Deliverea\Exception\CurlException;
use Deliverea\Exception\ErrorResponseException;
use Deliverea\Exception\UnexpectedResponseException;
use Deliverea\Model\Collection;
use Deliverea\Model\Address;
use Deliverea\Model\Shipment;
use Deliverea\Request\GetAddressesRequest;
use Deliverea\Request\GetCollectionCutoffHour;
use Deliverea\Request\GetCollectionsRequest;
use Deliverea\Request\GetShipmentLabelRequest;
use Deliverea\Request\GetShipmentRequest;
use Deliverea\Request\GetShipmentsRequest;
use Deliverea\Request\GetShipmentTrackingRequest;
use Deliverea\Request\NewCollectionRequest;
use Deliverea\Request\NewShipmentRequest;
use Deliverea\Response\AbstractResponse;
use Deliverea\Response\GetAddressesResponse;
use Deliverea\Response\GetCollectionCutoffHourResponse;
use Deliverea\Response\GetCollectionsResponse;
use Deliverea\Response\GetShipmentLabelResponse;
use Deliverea\Response\GetShipmentResponse;
use Deliverea\Response\GetShipmentsResponse;
use Deliverea\Response\GetShipmentTrackingResponse;
use Deliverea\Response\NewCollectionResponse;
use Deliverea\Response\NewShipmentResponse;

class Deliverea
{
    private $username = '';
    private $password = '';

    private $isSandbox = false;

    private $baseEndpoint = 'https://dlvrapi.com/v1';
    private $baseEndpointSandbox = 'https://sandbox.dlvrapi.com/v1';

    /**
     * @param $username
     * @param $password
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param Shipment $shipment
     * @param $from_address_id
     * @param Address $to
     * @return NewShipmentResponse
     */
    public function newShipment(Shipment $shipment, $from_address_id, Address $to)
    {
        return $this->post('new-shipment', new NewShipmentRequest($shipment, $from_address_id, $to),
            new NewShipmentResponse());
    }

    /**
     * @param Collection $collection
     * @param $from_address_id
     * @param Address $to
     * @return NewCollectionResponse
     */
    public function newCollection(Collection $collection, $from_address_id, Address $to)
    {
        return $this->post('new-collection', new NewCollectionRequest($collection, $from_address_id, $to),
            new NewCollectionResponse());
    }

    /**
     * @param $dlvrReference
     * @return GetShipmentLabelResponse
     */
    public function getShipmentLabel($dlvrReference)
    {
        return $this->get('get-shipment-label', new GetShipmentLabelRequest($dlvrReference),
            new GetShipmentLabelResponse());
    }

    /**
     * @param array $filters
     * @return array
     */
    public function getShipments($filters = [])
    {
        return $this->get('get-shipments', new GetShipmentsRequest($filters), new GetShipmentsResponse());
    }

    /**
     * @param array $filters
     * @return array
     */
    public function getCollections($filters = [])
    {
        return $this->get('get-collections', new GetCollectionsRequest($filters), new GetCollectionsResponse());
    }

    /**
     * @param $dlvrReference
     * @return GetShipmentResponse
     */
    public function getShipment($dlvrReference)
    {
        return $this->get('get-shipment', new GetShipmentRequest($dlvrReference), new GetShipmentResponse());
    }

    /**
     * @param $dlvrReference
     * @return GetShipmentTrackingResponse
     */
    public function getShipmentTracking($dlvrReference)
    {
        return $this->get('get-shipment-tracking', new GetShipmentTrackingRequest($dlvrReference),
            new GetShipmentTrackingResponse());
    }

    /**
     * @return GetAddressesResponse
     */
    public function getAddresses()
    {
        return $this->get('get-addresses', new GetAddressesRequest(),
            new GetAddressesResponse());
    }

    /**
     * @param $carrierCode
     * @param $fromAddressId
     * @return GetCollectionCutoffHourResponse
     */
    public function getCollectionCutoffHour($carrierCode, $fromAddressId)
    {
        return $this->get('get-collection-cutoff-hour', new GetCollectionCutoffHour($carrierCode, $fromAddressId),
            new GetCollectionCutoffHourResponse());
    }

    /**
     * @param bool $sandbox
     */
    public function setSandbox($sandbox)
    {
        $this->isSandbox = $sandbox;
    }

    /**
     * @return bool
     */
    public function getSandbox()
    {
        return $this->isSandbox;
    }

    private function get($url, $request, AbstractResponse $response)
    {
        return $this->request($url, $request, $response, 'GET');
    }

    private function post($url, $request, AbstractResponse $response)
    {
        return $this->request($url, $request, $response, 'POST');
    }

    private function request($url, $request, AbstractResponse $response, $type)
    {
        // TODO: Test invalid credentials

        if (empty($this->username) || empty($this->password)) {
            throw new \Exception("Please provide your API credentials");
        }

        $params = http_build_query($request);

        $endpoint = $this->baseEndpoint;

        if ($this->getSandbox()) {
            $endpoint = $this->baseEndpointSandbox;
        }

        $url = $endpoint . '/' . $url;

        if ($type == 'GET') {
            $url .= '?' . $params;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($type == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new CurlException($url);
        }

        curl_close($ch);

        $result = json_decode($result);

        if ($result === null) {
            throw new UnexpectedResponseException($url);
        }

        if ($result->status === 'err') {
            if (is_object($result->data) && property_exists($result->data, 'errorCode')) {
                throw new ErrorResponseException($result->data->errorCode, $result->data->errorMessage);
            } else {
                throw new ErrorResponseException(-1, $result->data[0]);
            }
        }

        return $response->map($result->data);
    }
}