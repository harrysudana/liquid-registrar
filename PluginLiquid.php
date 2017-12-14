<?php
/**
 * Liquid Registrar
 *
 * @category Plugin
 * @package  ClientExec
 * @author   harrysudana <harrysudana@gmail.com>
 */

require_once 'modules/admin/models/RegistrarPlugin.php';
require_once 'library/CE/NE_Network.php';
require_once 'modules/domains/models/ICanImportDomains.php';
include dirname(__FILE__).'/inc/LiquidClient/autoload.php';

class PluginLiquid extends RegistrarPlugin implements ICanImportDomains
{
    function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array (
                                'type'          =>'hidden',
                                'description'   =>lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                                'value'         =>lang('Liquid')
                               ),
            lang('Use testing server') => array(
                                'type'          =>'yesno',
                                'description'   =>lang('Select Yes if you wish to use the Liquid testing environment, so that transactions are not actually made.'),
                                'value'         =>0
                               ),
            lang('Reseller ID') => array(
                                'type'          =>'text',
                                'description'   =>lang('Enter your Liquid Reseller ID.  This can be found in your Liquid account by going to your profile link, in the top right corner.'),
                                'value'         =>''
                               ),
            lang('API Key') => array(
                                'type'          =>'text',
                                'description'   =>lang('Enter your API Key for your Liquid reseller account.  You should use this instead of your password, however you still may use your password instead.'),
                                'value'         =>''
                               ),
            lang('Supported Features')  => array(
                                'type'          => 'label',
                                'description'   => '* '.lang('TLD Lookup').'<br>* '.lang('Domain Registration').' <br>* '.lang('Existing Domain Importing').' <br>* '.lang('Get / Set Nameserver Records').' <br>* '.lang('Get / Set Contact Information').' <br>* '.lang('Get / Set Registrar Lock').' <br>* '.lang('Initiate Domain Transfer').' <br>* ' . lang('Automatically Renew Domain'),
                                'value'         => ''
                                ),
            lang('Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain isn\'t registered)'),
                                'value'         => 'Register'
                                ),
            lang('Registered Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain is registered)'),
                                'value'         => 'Renew (Renew Domain),DomainTransferWithPopup (Initiate Transfer),SendTransferKey (Send Auth Info),Cancel',
                                ),
             lang('Registered Actions For Customer') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain is registered)'),
                                'value'         => 'SendTransferKey (Send Auth Info)',
            )
        );
        return $variables;
    }

    // returns array(code [,message]), where code is:
    // 0:       Domain available
    // 1:       Domain already registered
    // 2:       Registrar Error, domain extension not recognized or supported
    // 3:       Domain invalid
    // 5:       Could not contact registry to lookup domain
    function checkDomain($params){
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);

    	$arguments = array(
            'queryParams' => array('domain'=>$domain),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        
        $domains = array();
        $response = $this->_makeRequest('/domains/availability', $arguments);

        if ($response == false){
            $status = 5;
        }else{
        	$results = $response->ResponseBody;
        	foreach ($results as $result) {
        		if (isset($result->message)) {
	        		CE_Lib::log(4, 'ERROR: Liquid check domain failed with error: ' . $result->message);
	            	$status = 2;
	        	}elseif ($result->$domain->status == 'regthroughus' || $result->$domain->status == 'regthroughothers') {
		            CE_Lib::log(4, 'Liquid check domain result for domain ' . $domain . ': Registered');
		            $status = 1;
		        }elseif ($result->$domain->status == 'available') {
		            CE_Lib::log(4, 'Liquid check domain result for domain ' . $domain . ': Available');
		            $status = 0;
		        } else {
		            CE_Lib::log(4, 'ERROR: Liquid check domain failed.');
		            $status = 5;
		        }
		        $domains[] = array('tld' => $params['tld'], 'domain' => $params['sld'], 'status' => $status);
        	}
        	
        }

        if ( $params['enableNamesuggest'] == true ) {
        	$arguments = array(
	            'queryParams' => array(
					            		'keyword'=>$params['sld'],
					            		'tld'=>implode(',', $params['allAvailableTLDs']),
					            		'hyphen_allowed'=>true,
					            		'add_related'=>true
					            	),
			    'formParams' => array(),
			    'headerParams' => array(),
	        );
	        $response = $this->_makeRequest('/domains/suggestion', $arguments);
	        if ($response <> false) {
	        	foreach ( $response->ResponseBody as $domain ) {
	        		foreach ( $domain as $domainTlds ){
	        			$domains[] = array('tld' => '{$domainTlds}', 'domain' => '{$domain}', 'status' => 0);
	        		}
	        	}
	        }
        }

        return array("result"=>$domains);
    }

    /**
     * Register domain name
     *
     * @param array $params
     */
    function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->registerDomain($this->buildRegisterParams($userPackage,$params));
        if(is_array($orderid))
            return "Error : ".$orderid['message'];
        $userPackage->setCustomField("Registrar Order Id",$userPackage->getCustomField("Registrar").'-'.$orderid);
        return $userPackage->getCustomField('Domain Name') . ' has been registered.';
    }

    // possible return values: array(code [,message])
    // -1:  error trying to purchase domain
    // 0:   domain not available
    // >0:  Operation successfull, returns orderid
    function registerDomain($params)
    {
        $apiClient = $this->_constructApiClient();
        $domain_name = strtolower($params['sld'] . '.' . $params['tld']);
        $customer_id = $this->_getCustomer($params['RegistrantEmailAddress']);
        
        $company = $params["RegistrantOrganizationName"];
        $name = $params["RegistrantFirstName"]." ".$params["RegistrantLastName"];
        $address_line_1 = $params["RegistrantAddress1"];
        $address_line_2 = null;
        $address_line_3 = null;
        $city = $params["RegistrantCity"];
        $state = $params["RegistrantStateProvince"];
        $country = $this->_getCountryName($params["RegistrantCountry"]);
        $country_code = $params["RegistrantCountry"];
        $zipcode = $params["RegistrantPostalCode"];
        $email = $params["RegistrantEmailAddress"];
        $tel_cc_no = $this->_getPhoneCode($country_code);
        $tel_no = $this->_validatePhone($params["RegistrantPhone"]);
        $fax_cc_no = $this->_getPhoneCode($country_code);
        $fax_no = null;
        $alt_tel_cc_no = null;
        $alt_tel_no=null; 
        $mobile_cc_no=null;
        $mobile_no=null;

        if(!isset($customer_id)){
            list($response, $header) = $customer->createCustomer(
                $email, $name, $password, $company, $address_line_1, $city, $state, $country_code, $zipcode, $tel_cc_no, $tel_no, $address_line_2, $address_line_3, $alt_tel_cc_no, $alt_tel_no, $mobile_cc_no, $mobile_no, $fax_cc_no, $fax_no
            );
            $customer_id = $response->customer_id;
        }

        $contact_ids = array(
            'Registrant'=>null,
            'Admin'=>null, 
            'Tech'=>null,
            'Billing'=>null
        );
        
        foreach (array('Registrant','Admin','Tech','Billing') as $type) {
            $eligibility_criteria = null;
            $extra = null;
            $contact = new \Liquid\Client\Api\ContactsApi($apiClient);
            
            list($response, $header) = $contact->contacts(
                $customer_id, $name, $company, $email, $address_line_1, $city, $country_code, $zipcode, $tel_cc_no, $tel_no, $address_line_2, $address_line_3, $state, $fax_cc_no, $fax_no, $eligibility_criteria, $extra
            );
            $contact_ids[$type]=$response->contact_id;
        }

        $ns = null;
        if (isset($params['NS1'])) {
            $nameServer=array();
            for ($i = 1; $i <= 10; $i++) {
                if (isset($params["NS$i"])) {
                    $nameServer[] = $params["NS$i"]['hostname'];
                } else {
                    break;
                }
            }
            $ns = implode(",", $nameServer);
        }

        $registrant_contact_id=$contact_ids['Registrant'];
        $billing_contact_id=$contact_ids['Billing'];
        $admin_contact_id=$contact_ids['Admin'];
        $tech_contact_id=$contact_ids['Tech'];
        $invoice_option='no_invoice';
        $years=$params['NumYears'];
        $purchase_privacy_protection=null;
        $privacy_protection_enabled=null;
        $extra=null;

        $domain = new \Liquid\Client\Api\DomainsApi($apiClient);        
        try{
            list($response, $header) = $domain->create($domain_name, $customer_id, $registrant_contact_id, $billing_contact_id, $admin_contact_id, $tech_contact_id, $invoice_option, $years, $ns);

            if(isset($response->message))
                return $response->message;

            return $response->order_id;

        } catch (Liquid\Client\ApiException $e) {
            $message = 'Caught exception: '. $e->getMessage(). "\n";
            $message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
            $message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
            $message .= '<br>HTTP status code: '. $e->getCode(). "\n";
            CE_Lib::log(4, 'ERROR: Liquid request failed with error: ' . $message);
            return json_decode($e->getResponseBody(),true);
        }

    }

    /**
     * Renew domain name
     *
     * @param array $params
     */
    function doRenew($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->renewDomain($this->buildRenewParams($userPackage,$params));
        if(is_array($orderid))
            return "Error : ".$orderid['message'];
        $userPackage->setCustomField("Registrar Order Id",$userPackage->getCustomField("Registrar").'-'.$orderid);
        return $userPackage->getCustomField('Domain Name') . ' has been renewed.';
    }

    // possible return values: array(code [,message])
    // -1:  error trying to renew domain
    // 0:   domain not available
    // >0:  Operation successfull, returns orderid
    function renewDomain($params)
    {
        $domain_name = strtolower($params['sld'] . '.' . $params['tld']);
        $domainDetail = $this->_getDomainDetail($domain);
        $apiClient = $this->_constructApiClient();

        $domain_id=$domainDetail->domain_id;
        $years=$params['NumYears'];
        $current_date=$domainDetail->expiry_date;
        $invoice_option='no_invoice';
        $purchase_privacy_protection=null;
        $customer_id=null;

        $domain = new \Liquid\Client\Api\DomainsApi($apiClient);
        try{
            list($response, $header) = $domain->renew($domain_id, $years, $current_date, $invoice_option, $purchase_privacy_protection, $customer_id);

            return $response->order_id;

        } catch (Liquid\Client\ApiException $e) {
            $message = 'Caught exception: '. $e->getMessage(). "\n";
            $message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
            $message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
            $message .= '<br>HTTP status code: '. $e->getCode(). "\n";
            CE_Lib::log(4, 'ERROR: Liquid request failed with error: ' . $message);
            return json_decode($e->getResponseBody(),true);
        }
        

    }

    function getTransferStatus($params)
    {

    }

    /**
     * Initiate a domain transfer
     *
     * @param array $params
     */
    function doDomainTransferWithPopup($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferid = $this->initiateTransfer($this->buildTransferParams($userPackage,$params));
        if(is_array($transferid))
            return "Error : ".$transferid['message'];

        $userPackage->setCustomField("Registrar Order Id",$userPackage->getCustomField("Registrar").'-'.$transferid);
        $userPackage->setCustomField('Transfer Status', $transferid);
        return "Transfer of has been initiated.";
    }

    // possible return values: array(code [,message])
    // -1:  error trying to transfer domain
    // 0:   domain not available
    // >0:  Operation successfull, returns orderid
    function initiateTransfer($params)
    {
        $apiClient = $this->_constructApiClient();
        $domain_name = strtolower($params['sld'] . '.' . $params['tld']);
        $customer_id = $this->_getCustomer($params['RegistrantEmailAddress']);
        
        $company = $params["RegistrantOrganizationName"];
        $name = $params["RegistrantFirstName"]." ".$params["RegistrantLastName"];
        $address_line_1 = $params["RegistrantAddress1"];
        $address_line_2 = null;
        $address_line_3 = null;
        $city = $params["RegistrantCity"];
        $state = $params["RegistrantStateProvince"];
        $country = $this->_getCountryName($params["RegistrantCountry"]);
        $country_code = $params["RegistrantCountry"];
        $zipcode = $params["RegistrantPostalCode"];
        $email = $params["RegistrantEmailAddress"];
        $tel_cc_no = $this->_getPhoneCode($country_code);
        $tel_no = $this->_validatePhone($params["RegistrantPhone"]);
        $fax_cc_no = $this->_getPhoneCode($country_code);
        $fax_no = null;
        $alt_tel_cc_no = null;
        $alt_tel_no=null; 
        $mobile_cc_no=null;
        $mobile_no=null;

        if(!isset($customer_id)){
            list($response, $header) = $customer->createCustomer(
                $email, $name, $password, $company, $address_line_1, $city, $state, $country_code, $zipcode, $tel_cc_no, $tel_no, $address_line_2, $address_line_3, $alt_tel_cc_no, $alt_tel_no, $mobile_cc_no, $mobile_no, $fax_cc_no, $fax_no
            );
            $customer_id = $response->customer_id;
        }

        $contact_ids = array(
            'Registrant'=>null,
            'Admin'=>null, 
            'Tech'=>null,
            'Billing'=>null
        );
        
        foreach (array('Registrant','Admin','Tech','Billing') as $type) {
            $eligibility_criteria = null;
            $extra = null;
            $contact = new \Liquid\Client\Api\ContactsApi($apiClient);
            list($response, $header) = $contact->contacts(
                $customer_id, $name, $company, $email, $address_line_1, $city, $country_code, $zipcode, $tel_cc_no, $tel_no, $address_line_2, $address_line_3, $state, $fax_cc_no, $fax_no, $eligibility_criteria, $extra
            );
            $contact_ids[$type]=$response->contact_id;
        }

        $registrant_contact_id=$contact_ids['Registrant'];
        $admin_contact_id=$contact_ids['Admin'];
        $billing_contact_id=$contact_ids['Tech'];
        $tech_contact_id=$contact_ids['Billing'];
        $invoice_option="no_invoice";
        $auth_code = $params['eppCode'];
        $years=1;
        $ns=null;
        $extra=null;

        if (isset($params['NS1'])) {
            $nameServer=array();
            for ($i = 1; $i <= 10; $i++) {
                if (isset($params["NS$i"])) {
                    $nameServer[] = $params["NS$i"]['hostname'];
                } else {
                    break;
                }
            }
            $ns = implode(",", $nameServer);
        }

        $domain = new \Liquid\Client\Api\DomainsApi($apiClient);
        try{
            list($response, $header) = $domain->transfer($domain_name, $customer_id, $registrant_contact_id, $admin_contact_id, $billing_contact_id, $tech_contact_id, $invoice_option, $auth_code, $years, $ns, $extra);

            return $response->order_id;
        } catch (Liquid\Client\ApiException $e) {
            $message = 'Caught exception: '. $e->getMessage(). "\n";
            $message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
            $message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
            $message .= '<br>HTTP status code: '. $e->getCode(). "\n";
            CE_Lib::log(4, 'ERROR: Liquid request failed with error: ' . $message);
            return json_decode($e->getResponseBody(),true);
        }

    }    

    // called from outside CE once in a while
    function _plugin_enom_updateRegistrarsTable($params)
    {

    }

    function getContactInformation($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);

    	$contact_ids = array(
    		'Customer'=>$domainDetail->customer_id, 
    		'Registrant'=>$domainDetail->registrant_contact_id,
    		'Admin'=>$domainDetail->admin_contact_id, 
    		'Tech'=>$domainDetail->tech_contact_id
    	);

    	$info = array();
        foreach (array('Registrant', 'Admin', 'Tech') as $type) {
        	$arguments = array(
	            'queryParams' => array(),
			    'formParams' => array(),
			    'headerParams' => array(),
	        );
        	$response = $this->_makeRequest('/customers/'.$contact_ids['Customer'].'/contacts/'.$contact_ids[$type], $arguments);
        	$result = $response->ResponseBody;

            if (sizeof($result)>0) {
            	$name = explode(' ', $result->name, 2);

                $info[$type]['OrganizationName']  = array($this->user->lang('Organization'), $result->company);
                $info[$type]['FirstName'] = array($this->user->lang('First Name'), $name[0]);
                $info[$type]['LastName']  = array($this->user->lang('Last Name'), $name[1]);
                $info[$type]['Address1']  = array($this->user->lang('Address').' 1', $result->address_line_1);
                $info[$type]['Address2']  = array($this->user->lang('Address').' 2', $result->address_line_2);
                $info[$type]['Address3']  = array($this->user->lang('Address').' 3', $result->address_line_3);
                $info[$type]['City']      = array($this->user->lang('City'), $result->city);
                $info[$type]['StateProvince']  = array($this->user->lang('Province').'/'.$this->user->lang('State'), $result->state);
                $info[$type]['Country']   = array($this->user->lang('Country'), $result->country);
                $info[$type]['CountryCode']   = array($this->user->lang('Country Code'), $result->country_code);
                $info[$type]['PostalCode']  = array($this->user->lang('Postal Code'), $result->zipcode);
                $info[$type]['EmailAddress']     = array($this->user->lang('E-mail'), $result->email);
                $info[$type]['Phone']  = array($this->user->lang('Phone'), $result->tel_no);
                $info[$type]['Fax']       = array($this->user->lang('Fax'), $result->fax_no);
            } else {
                $info[$type] = array(
                    'OrganizationName'  => array($this->user->lang('Organization'), ''),
                    'FirstName'         => array($this->user->lang('First Name'), ''),
                    'LastName'          => array($this->user->lang('Last Name'), ''),
                    'Address1'          => array($this->user->lang('Address').' 1', ''),
                    'Address2'          => array($this->user->lang('Address').' 2', ''),
                    'City'              => array($this->user->lang('City'), ''),
                    'StateProvince'         => array($this->user->lang('Province').'/'.$this->user->lang('State'), ''),
                    'Country'           => array($this->user->lang('Country'), ''),
                    'CountryCode'           => array($this->user->lang('Country Code'), ''),
                    'PostalCode'        => array($this->user->lang('Postal Code'), ''),
                    'EmailAddress'      => array($this->user->lang('E-mail'), ''),
                    'Phone'             => array($this->user->lang('Phone'), ''),
                    'Fax'               => array($this->user->lang('Fax'), ''),
                );
            }
        }
        return $info;

    }

    function setContactInformation($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);

    	$contact_ids = array(
    		'Customer'=>$domainDetail->customer_id, 
    		'Registrant'=>$domainDetail->registrant_contact_id,
    		'Admin'=>$domainDetail->admin_contact_id, 
    		'Tech'=>$domainDetail->tech_contact_id
    	);
    	
    	foreach (array('Registrant') as $type) {

            $customer_id = $domainDetail->customer_id; 
            $contact_id = $contact_ids[$type];
            $company = $params[$type."_OrganizationName"];
            $name = $params[$type."_FirstName"]." ".$params[$type."_LastName"];
            $address_line_1 = $params[$type."_Address1"];
            $address_line_2 = $params[$type."_Address2"];
            $address_line_3 = $params[$type."_Address3"];
            $city = $params[$type."_City"];
            $state = $params[$type."_StateProvince"];
            $country = $params[$type."_Country"];
            $country_code = $params[$type."_CountryCode"];
            $zipcode = $params[$type."_PostalCode"];
            $email = $params[$type."_EmailAddress"];
            $tel_cc_no = $this->_getPhoneCode($params[$type."_CountryCode"]);
            $tel_no = $this->_validatePhone($params[$type."_Phone"]);
            $fax_cc_no = $this->_getPhoneCode($params[$type."_CountryCode"]);
            $fax_no = $params[$type."_Fax"];

            $apiClient = $this->_constructApiClient();
            $contact = new \Liquid\Client\Api\ContactsApi($apiClient);
            
            try{
                list($response, $header) = $contact->contacts_2(
                    $customer_id, 
                    $contact_id,
                    $name, 
                    $company, 
                    $email, 
                    $address_line_1, 
                    $city, 
                    $country_code, 
                    $zipcode, 
                    $tel_cc_no, 
                    $tel_no, 
                    $address_line_2, 
                    $address_line_3, 
                    $state, 
                    $fax_cc_no, 
                    $fax_no
                );
                if(isset($response->message))
                    return $response->message;

            } catch (Liquid\Client\ApiException $e) {
                $message = 'Caught exception: '. $e->getMessage(). "\n";
                $message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
                $message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
                $message .= '<br>HTTP status code: '. $e->getCode(). "\n";
                CE_Lib::log(1, 'ERROR: Liquid request failed with error: ' . $message);
                return false;
            }
        }

        return $this->user->lang('Contact Information updated successfully.');
    }

    function getNameServers($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);

    	$arguments = array(
            'queryParams' => array(),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        
        $response = $this->_makeRequest('/domains/'.$domainDetail->domain_id.'/ns', $arguments);
        $results = $response->ResponseBody;
        $info = array();
        if(!isset($results->message)){
        	foreach ($response->ResponseBody as $key=>$value) {
        		$info[] = $value;
        	}
        }
        return $info;
    }

    function setNameServers($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);

    	$arguments = array(
            'queryParams' => array(),
		    'formParams' => array('ns'=>implode(",", $params['ns'])),
		    'headerParams' => array(),
        );
        
        $response = $this->_makeRequest('/domains/'.$domainDetail->domain_id.'/ns', $arguments, 'PUT');
        if(isset($response->ResponseBody->message))
        	return $response->ResponseBody->message;
        else
        	return "Name Servers Updated";
    }

    function checkNSStatus($params)
    {
    	throw new MethodNotImplemented('Method checkNSStatus() has not been implemented yet.');
    }

    function registerNS($params)
    {
    	throw new MethodNotImplemented('Method registerNS() has not been implemented yet.');
    }

    function editNS($params)
    {
    	throw new MethodNotImplemented('Method editNS() has not been implemented yet.');
    }

    function deleteNS($params)
    {
    	throw new MethodNotImplemented('Method deleteNS() has not been implemented yet.');
    }

    function getGeneralInfo($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);
    	//CE_Lib::log(1, 'Test: ' . json_encode($domainDetail).' '.strtotime($result->expiry_date));
    	$data = array();
    	if($domainDetail<>false){
    		$data['endtime'] = strtotime($domainDetail->expiry_date);
	        $data['expiration'] = $domainDetail->expiry_date;
	        $data['domain'] = $domainDetail->domain_name;
	        $data['id'] = $domainDetail->domain_id;
	        $data['registrationstatus'] = $domainDetail->suspend ? "Suspend" : $this->user->lang('Registered');
	        $data['purchasestatus'] = $domainDetail->order_status;
	        $data['autorenew'] = false;
    	}
        return $data;
    }

    function fetchDomains($params)
    {
        $apiClient = $this->_constructApiClient();
        $domain = new \Liquid\Client\Api\DomainsApi($apiClient);
        try{
            //$limit=null, $page_no=null, $domain_id=null, $reseller_id=null, $customer_id=null, $show_child_orders=null, $tld=null, $status=null, $domain_name=null, $privacy_protection_enabled=null, $creation_time_start=null, $creation_time_end=null, $expiry_date_start=null, $expiry_date_end=null, $reseller_email=null, $customer_email=null, $exact_domain_name=null
            list($response, $header) = $domain->retrieve(100);

            $domainsList = array();
            if (!isset($response->message)) {
                foreach ($response as $domain) {
                    //$domain = $domain['#'];
                    $domain_name = explode('.',$domain->domain_name,2);
                    $data['id'] = $domain->domain_id;
                    $data['sld'] = $domain_name[0];
                    $data['tld'] = $domain_name[1];
                    $data['exp'] = $domain->expiry_date;
                    $domainsList[] = $data;
                }
            }

            $metaData = array();
            //$metaData['total'] = $response['interface-response']['#']['GetDomains'][0]['#']['DomainCount'][0]['#'];
            //$metaData['next'] = $response['interface-response']['#']['GetDomains'][0]['#']['NextRecords'][0]['#'];
            //$metaData['start'] = $response['interface-response']['#']['GetDomains'][0]['#']['StartPosition'][0]['#'];
            //$metaData['end'] = $response['interface-response']['#']['GetDomains'][0]['#']['EndPosition'][0]['#'];
            $metaData['numPerPage'] = 25;
            return array($domainsList, $metaData);

        } catch (Liquid\Client\ApiException $e) {
            $message = 'Caught exception: '. $e->getMessage(). "\n";
            $message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
            $message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
            $message .= '<br>HTTP status code: '. $e->getCode(). "\n";
            CE_Lib::log(1, 'ERROR: Liquid request failed with error: ' . $message);
            return false;
        }

        /*
    	$arguments = array(
            'queryParams' => array('limit'=>100, 'page_no'=>$params['next'], 'exact_domain_name'=>0),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        $result = $this->_makeRequest('/domains', $arguments);
        if ($result == false) {
            $status = 5;
        }else{
        	$domainsList = array();
        	if($result->ResponseHeader->X-Total-Count){

        	}
        }
        
        $arguments = array(
            'command'       => 'GetDomains',
            'uid'           => $params['Login'],
            'pw'            => $params['Password'],
            'Display'       => '100',
            'Start'         => $params['next']
        );
        $response = $this->_makeRequest($params, $arguments);
        */
    }

    function disablePrivateRegistration($params)
    {
    	throw new MethodNotImplemented('Method disablePrivateRegistration has not been implemented yet.');
    }

    function setAutorenew($params)
    {
    	throw new MethodNotImplemented('Method setAutorenew() has not been implemented yet.');
    }

    function getRegistrarLock($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);
    	if($domainDetail<>false){
    		return $domainDetail->locked;
    	}
    }

    function doSetRegistrarLock($params)
    {
    	$userPackage = new UserPackage($params['userPackageId']);
        $this->setRegistrarLock($this->buildLockParams($userPackage,$params));
        return "Updated Registrar Lock.";
    }

    function setRegistrarLock($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);

    	$method = ($params['lock']==0) ? "DELETE" : "PUT";

    	$arguments = array(
            'queryParams' => array('reason'=>'CE Lock Action'),
		    'formParams' => array(),
		    'headerParams' => array(),
        );

        $response = $this->_makeRequest('/domains/'.$domainDetail->domain_id.'/locked', $arguments, $method);
    }

    function doSendTransferKey($params)
    {
    	$userPackage = new UserPackage($params['userPackageId']);
        $this->sendTransferKey($this->buildRegisterParams($userPackage,$params));
        return 'Successfully sent auth info for ' . $userPackage->getCustomField('Domain Name');
    }

    function sendTransferKey($params)
    {
    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);
    	
		$arguments = array(
            'queryParams' => array(),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        $response = $this->_makeRequest('/domains/'.$domainDetail->domain_id.'/auth_code', $arguments);
        $EPPCode = $response->ResponseBody;

        $response = $this->_makeRequest('/customers/'.$contact_ids['Customer'].'/contacts/'.$domainDetail->registrant_contact_id, $arguments);
        $result = $response->ResponseBody;
        $receipt = $result->email;
        $body = "Your EPPCode is : ".$EPPCode;

        include_once 'library/CE/NE_MailGateway.php';
        $mailGateway = new NE_MailGateway();
        $mailerResult = $mailGateway->mailMessageEmail(
                                    $body,
                                    $this->settings->get('Support E-mail'),
                                    $this->settings->get('Company Name'),
                                    $receipt,
                                    "",
                                    "EPP Code"
                                );
    }

    function getDNS($params)
    {
    	throw new CE_Exception('Method getDNS() has not been implemented yet.');

    	$domain = strtolower($params['sld'] . '.' . $params['tld']);
    	$domainDetail = $this->_getDomainDetail($domain);
    	
		$arguments = array(
            'queryParams' => array('fields'=>'dns'),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        $response = $this->_makeRequest('/domains/'.$domainDetail->domain_id, $arguments);

        if($response<>false){
        	$results = $response->ResponseBody;
        	$records = array();
        	foreach ($results as $key => $value) {
        		$id = strtolower($key."_id");
        		foreach ($value as $key2 => $value2) {
	        		$records[]=array(
	        			'id'=>$value2->{$id},
	        			'hostname'=>$value2->hostname,
	        			'address'=>$value2->val,
	        			'type'=>$key,
	        		);
        		}
        	}
        }

        $types = array('A', 'MXE', 'MX', 'CNAME', 'URL', 'FRAME', 'TXT');
        $default = false;
        return array('records' => $records, 'types' => $types, 'default' => $default);
    }

    function setDNS($params)
    {
    	throw new CE_Exception('Method setDNS() has not been implemented yet.');
    }

    /**
     * Internal recursive function for iterating over the name server status array.
     *
     * @param mixed $arr The data to iterate over
     * @return String The stringified version of the status.
     */
    function _traverseStatus($arr)
    {

    }

    function _makeRequest($resourcePath, $arguments, $method = 'GET')
    {
    	$apiClient = $this->_constructApiClient();

		try {
	    	$resourcePath   = $resourcePath;
			$method         = $method;
			$formParams     = $arguments['formParams']; //array();
			$headerParams   = $arguments['headerParams'];//array();
			$queryParams = $arguments['queryParams'];//'example.com';

			list($response, $header) = $apiClient->callApi(
			    $resourcePath,
			    $method,
			    $queryParams,
			    $formParams,
			    $headerParams
			);
			//CE_Lib::log(1, 'INFO: ' . $resourcePath . ">>" . $method . ">>" .json_encode($response));
			return (object) array('ResponseBody'=>$response,'ResponseHeader'=>$header);
		} catch (Liquid\Client\ApiException $e) {
			$message = 'Caught exception: '. $e->getMessage(). "\n";
	    	$message .= '<br>HTTP response headers: '. $e->getResponseHeaders(). "\n";
	    	$message .= '<br>HTTP response body: '. $e->getResponseBody(). "\n";
	    	$message .= '<br>HTTP status code: '. $e->getCode(). "\n";
			CE_Lib::log(1, 'ERROR: Liquid request failed with error: ' . $message);
			return false;
		}
    }

    function _constructApiClient(){
        $apiClient = new \Liquid\Client\ApiClient();
        // set API host, default: 'https://api.liqu.id/v1'
        if(@$this->settings->get('plugin_liquid_Use testing server'))
            $apiClient->getConfig()->setHost('https://api.domainsas.com/v1');
        else
            $apiClient->getConfig()->setHost('https://api.liqu.id/v1');

        // set Reseller ID
        $apiClient->getConfig()->setUsername($this->settings->get('plugin_liquid_Reseller ID'));

        // set Reseller API Key
        $apiClient->getConfig()->setPassword($this->settings->get('plugin_liquid_API Key'));

        // set to true to debug script
        $apiClient->getConfig()->setDebug(false);
        return $apiClient;
    }

    function _getDomainDetail($domain)
    {
    	$arguments = array(
            'queryParams' => array('exact_domain_name'=>1,'domain_name'=>$domain),
		    'formParams' => array(),
		    'headerParams' => array(),
        );
        $response = $this->_makeRequest('/domains', $arguments);
        
        if ($response == false){
            return false;
        }else{
        	$domainDetail = false;
        	$results = $response->ResponseBody;
        	foreach ($results as $result) {
        		$domainDetail = $result;
        	}
        	return $domainDetail;
        }
    }

    function _validatePhone($phone)
    {
    	// strip all non numerical values
        return preg_replace('/[^\d]/', '', $phone);
    }

    function _getPhoneCode($country)
    {
    	$phone_code = 62;
    	$query = "SELECT phone_code FROM country WHERE iso=? AND phone_code != ''";
        $result = $this->db->query($query, $country);
        if (!$row = $result->fetch()) {
            return $phone_code;
        }
        return $row['phone_code'];
    }

    function _getCountryName($country)
    {
        $country_name = 'Indonesia';
        $query = "SELECT name FROM country WHERE iso=? AND name != ''";
        $result = $this->db->query($query, $country);
        if (!$row = $result->fetch()) {
            return $country_name;
        }
        return $row['name'];
    }

    function _getCustomer($email)
    {
        $apiClient = $this->_constructApiClient();
        $customer = new \Liquid\Client\Api\CustomersApi($apiClient);
        list($response, $header) = $customer->allCustomer(null, null, null, $email);
        $customer_id = null;
        foreach ($response as $result) {
            $customer_id = $result->customer_id;
        }
        return $customer_id;
    }
}