<?php
/**
 * PrivacyprotectionApi
 * PHP version 5
 *
 * @category Class
 * @package  Liquid\Client
 * @author   http://github.com/liquidregistrar/liquid-php
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache Licene v2
 * @link     https://github.com/liquidregistrar/liquid-php
 */
/**
 *  Copyright 2015 SmartBear Software
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

/**
 * NOTE: This class is auto generated by the liquid code generator program.
 * https://github.com/liquidregistrar/liquid-php
 * Do not edit the class manually.
 */

namespace Liquid\Client\Api;

use \Liquid\Client\Configuration;
use \Liquid\Client\ApiClient;
use \Liquid\Client\ApiException;
use \Liquid\Client\ObjectSerializer;

/**
 * PrivacyprotectionApi Class Doc Comment
 *
 * @category Class
 * @package  Liquid\Client
 * @author   http://github.com/liquidregistrar/liquid-php
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache Licene v2
 * @link     https://github.com/liquidregistrar/liquid-php
 */
class PrivacyprotectionApi
{

    /**
     * API Client
     * @var \Liquid\Client\ApiClient instance of the ApiClient
     */
    protected $apiClient;

    /**
     * Constructor
     * @param \Liquid\Client\ApiClient|null $apiClient The api client to use
     */
    function __construct($apiClient = null)
    {
        if ($apiClient == null) {
            $apiClient = new ApiClient();
            $apiClient->getConfig()->setHost('https://api.liqu.id/v1');
        }

        $this->apiClient = $apiClient;
    }

    /**
     * Get API client
     * @return \Liquid\Client\ApiClient get the API client
     */
    public function getApiClient()
    {
        return $this->apiClient;
    }

    /**
     * Set the API client
     * @param \Liquid\Client\ApiClient $apiClient set the API client
     * @return PrivacyprotectionApi
     */
    public function setApiClient(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        return $this;
    }


    /**
     * retrievePrivacyProtection
     *
     * retrieve privacy protection status of a domain
     *
     * @param int $domain_id Domain ID (required)
     * @param int $customer_id Customer Id. (optional)
     * @return void
     * @throws \Liquid\Client\ApiException on non-2xx response
     */
    public function retrievePrivacyProtection($domain_id, $customer_id=null)
    {

        // verify the required parameter 'domain_id' is set
        if ($domain_id === null) {
            throw new \InvalidArgumentException('Missing the required parameter $domain_id when calling retrievePrivacyProtection');
        }

        // parse inputs
        $resourcePath = "/domains/{domain_id}/privacy_protection";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "GET";
        $httpBody = '';
        $queryParams = array();
        $headerParams = array();
        $formParams = array();
        $_header_accept = ApiClient::selectHeaderAccept(array());
        if (!is_null($_header_accept)) {
            $headerParams['Accept'] = $_header_accept;
        }
        $headerParams['Content-Type'] = ApiClient::selectHeaderContentType(array());

        // query params
        if ($customer_id !== null) {
            $queryParams['customer_id'] = $this->apiClient->getSerializer()->toQueryValue($customer_id);
        }

        // path params
        if ($domain_id !== null) {
            $resourcePath = str_replace(
                "{" . "domain_id" . "}",
                $this->apiClient->getSerializer()->toPathValue($domain_id),
                $resourcePath
            );
        }



        // for model (json/xml)
        if (isset($_tempBody)) {
            $httpBody = $_tempBody; // $_tempBody is the method argument, if present
        } else if (count($formParams) > 0) {
            $httpBody = $formParams; // for HTTP post (form)
        }

        // make the API Call
        try
        {
            list($response, $httpHeader) = $this->apiClient->callApi(
                $resourcePath, $method,
                $queryParams, $httpBody,
                $headerParams
            );

            return array($response, $httpHeader);
        } catch (ApiException $e) {
            switch ($e->getCode()) {
            }

            throw $e;
        }

    }

    /**
     * enablePrivacyProtection
     *
     * enable privacy protection on a domain
     *
     * @param int $domain_id Domain ID (required)
     * @param int $customer_id Customer Id. (optional)
     * @return void
     * @throws \Liquid\Client\ApiException on non-2xx response
     */
    public function enablePrivacyProtection($domain_id, $customer_id=null)
    {

        // verify the required parameter 'domain_id' is set
        if ($domain_id === null) {
            throw new \InvalidArgumentException('Missing the required parameter $domain_id when calling enablePrivacyProtection');
        }

        // parse inputs
        $resourcePath = "/domains/{domain_id}/privacy_protection";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "PUT";
        $httpBody = '';
        $queryParams = array();
        $headerParams = array();
        $formParams = array();
        $_header_accept = ApiClient::selectHeaderAccept(array());
        if (!is_null($_header_accept)) {
            $headerParams['Accept'] = $_header_accept;
        }
        $headerParams['Content-Type'] = ApiClient::selectHeaderContentType(array());



        // path params
        if ($domain_id !== null) {
            $resourcePath = str_replace(
                "{" . "domain_id" . "}",
                $this->apiClient->getSerializer()->toPathValue($domain_id),
                $resourcePath
            );
        }
        // form params
        if ($customer_id !== null) {
            $formParams['customer_id'] = $this->apiClient->getSerializer()->toFormValue($customer_id);
        }


        // for model (json/xml)
        if (isset($_tempBody)) {
            $httpBody = $_tempBody; // $_tempBody is the method argument, if present
        } else if (count($formParams) > 0) {
            $httpBody = $formParams; // for HTTP post (form)
        }

        // make the API Call
        try
        {
            list($response, $httpHeader) = $this->apiClient->callApi(
                $resourcePath, $method,
                $queryParams, $httpBody,
                $headerParams
            );

            return array($response, $httpHeader);
        } catch (ApiException $e) {
            switch ($e->getCode()) {
            }

            throw $e;
        }

    }

    /**
     * disablePrivacyProtection
     *
     * disable privacy protection on a domain
     *
     * @param int $domain_id Domain ID (required)
     * @param int $customer_id Customer Id. (optional)
     * @return void
     * @throws \Liquid\Client\ApiException on non-2xx response
     */
    public function disablePrivacyProtection($domain_id, $customer_id=null)
    {

        // verify the required parameter 'domain_id' is set
        if ($domain_id === null) {
            throw new \InvalidArgumentException('Missing the required parameter $domain_id when calling disablePrivacyProtection');
        }

        // parse inputs
        $resourcePath = "/domains/{domain_id}/privacy_protection";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "DELETE";
        $httpBody = '';
        $queryParams = array();
        $headerParams = array();
        $formParams = array();
        $_header_accept = ApiClient::selectHeaderAccept(array());
        if (!is_null($_header_accept)) {
            $headerParams['Accept'] = $_header_accept;
        }
        $headerParams['Content-Type'] = ApiClient::selectHeaderContentType(array());

        // query params
        if ($customer_id !== null) {
            $queryParams['customer_id'] = $this->apiClient->getSerializer()->toQueryValue($customer_id);
        }

        // path params
        if ($domain_id !== null) {
            $resourcePath = str_replace(
                "{" . "domain_id" . "}",
                $this->apiClient->getSerializer()->toPathValue($domain_id),
                $resourcePath
            );
        }



        // for model (json/xml)
        if (isset($_tempBody)) {
            $httpBody = $_tempBody; // $_tempBody is the method argument, if present
        } else if (count($formParams) > 0) {
            $httpBody = $formParams; // for HTTP post (form)
        }

        // make the API Call
        try
        {
            list($response, $httpHeader) = $this->apiClient->callApi(
                $resourcePath, $method,
                $queryParams, $httpBody,
                $headerParams
            );

            return array($response, $httpHeader);
        } catch (ApiException $e) {
            switch ($e->getCode()) {
            }

            throw $e;
        }

    }

    /**
     * buyPrivacyProtection
     *
     * buy privacy protection service for a domain
     *
     * @param int $domain_id Domain ID (required)
     * @param string $invoice_option Invoice Option, example keep_invoice, pay_invoice, no_invoice, only_add (required)
     * @param int $customer_id Customer Id. (optional)
     * @return void
     * @throws \Liquid\Client\ApiException on non-2xx response
     */
    public function buyPrivacyProtection($domain_id, $invoice_option, $customer_id=null)
    {

        // verify the required parameter 'domain_id' is set
        if ($domain_id === null) {
            throw new \InvalidArgumentException('Missing the required parameter $domain_id when calling buyPrivacyProtection');
        }
        // verify the required parameter 'invoice_option' is set
        if ($invoice_option === null) {
            throw new \InvalidArgumentException('Missing the required parameter $invoice_option when calling buyPrivacyProtection');
        }

        // parse inputs
        $resourcePath = "/domains/{domain_id}/privacy_protection/buy";
        $resourcePath = str_replace("{format}", "json", $resourcePath);
        $method = "POST";
        $httpBody = '';
        $queryParams = array();
        $headerParams = array();
        $formParams = array();
        $_header_accept = ApiClient::selectHeaderAccept(array());
        if (!is_null($_header_accept)) {
            $headerParams['Accept'] = $_header_accept;
        }
        $headerParams['Content-Type'] = ApiClient::selectHeaderContentType(array());



        // path params
        if ($domain_id !== null) {
            $resourcePath = str_replace(
                "{" . "domain_id" . "}",
                $this->apiClient->getSerializer()->toPathValue($domain_id),
                $resourcePath
            );
        }
        // form params
        if ($invoice_option !== null) {
            $formParams['invoice_option'] = $this->apiClient->getSerializer()->toFormValue($invoice_option);
        }// form params
        if ($customer_id !== null) {
            $formParams['customer_id'] = $this->apiClient->getSerializer()->toFormValue($customer_id);
        }


        // for model (json/xml)
        if (isset($_tempBody)) {
            $httpBody = $_tempBody; // $_tempBody is the method argument, if present
        } else if (count($formParams) > 0) {
            $httpBody = $formParams; // for HTTP post (form)
        }

        // make the API Call
        try
        {
            list($response, $httpHeader) = $this->apiClient->callApi(
                $resourcePath, $method,
                $queryParams, $httpBody,
                $headerParams
            );

            return array($response, $httpHeader);
        } catch (ApiException $e) {
            switch ($e->getCode()) {
            }

            throw $e;
        }

    }

}
