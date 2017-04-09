<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class PanoptoSessionManagementSoapClient extends SoapClient{

        //Namespace used for XML nodes for any root level variables or objects 
        const ROOT_LEVEL_NAMESPACE = "http://tempuri.org/";

        //Namespace used for XML nodes for object members
        const OBJECT_MEMBER_NAMESPACE = "http://schemas.datacontract.org/2004/07/Panopto.Server.Services.PublicAPI.V40";

        //Username of calling user.
        public $ApiUserKey;
        //Auth code generated for calling user.
        public $ApiUserAuthCode;
        //Name of Panopto server being called.
        public $Servername;
        //Password needed if provider does not have a bounce page.
        public $Password;

        // Older PHP SOAP clients fail to pass the SOAPAction header properly.
        // Store the current action so we can insert it in __doRequest.
        public $currentaction;

    public function __construct($servername,$apiuseruserkey, $apiuserauthcode, $password) {
        

        $this->ApiUserKey = $apiuseruserkey;

        $this->ApiUserAuthCode = $apiuserauthcode;

        $this->Servername = $servername;

        $this->Password = $password;

        // Instantiate SoapClient in WSDL mode.
        //Set call timeout to 5 minutes.
        
        // With proxy for fiddler.
        /*parent::__construct
        (
            "https://". $servername . "/Panopto/PublicAPI/4.6/SessionManagement.svc?wsdl", array('cache_wsdl' => WSDL_CACHE_NONE, 'proxy_host' => '192.168.1.102', 'proxy_port' => '8888')
        );*/
        
        // Without proxy.
        parent::__construct
        (
            "https://". $servername . "/Panopto/PublicAPI/4.6/SessionManagement.svc?wsdl", array('cache_wsdl' => WSDL_CACHE_NONE)
        );

    }

      /**
     *  Helper method for making a call to the Panopto API.
     *  $methodname is the case sensitive name of the API method to be called
     *  $namedparams is an associative array of the member parameters (other than authenticationinfo )
     *   required by the API method being called. Keys should be the case sensitive names of the method's 
     *   parameters as specified in the API documentation.
     *  $auth should only be set to false if the method does not require authentication info.
     */
    public function call_web_method($methodname, $namedparams = array(), $auth = true) {
        $params = array();
        
        // Include API user and auth code params unless $auth is set to false.
        if ($auth) 
        {            
            //Create SoapVars for AuthenticationInfo object members    
            $authinfo = new stdClass();


            $authinfo->AuthCode = new SoapVar(
            $this->ApiUserAuthCode, //Data
            XSD_STRING, //Encoding
            null, //type_name should be left null
            null, //type_namespace should be left null
            null, //node_name should be left null                             
            self::OBJECT_MEMBER_NAMESPACE); //Node namespace should be set to proper namespace.

            //Add the password parameter if a password is provided
            if(!empty($this->Password))
            {
                $authinfo->Password = new SoapVar($this->Password, XSD_STRING, null, null, null, self::OBJECT_MEMBER_NAMESPACE);
            }

            $authinfo->AuthCode = new SoapVar($this->ApiUserAuthCode, XSD_STRING, null, null, null, self::OBJECT_MEMBER_NAMESPACE);


            $authinfo->UserKey = new SoapVar($this->ApiUserKey, XSD_STRING, null, null, null,self::OBJECT_MEMBER_NAMESPACE);

            //Create a container for storing all of the soap vars required for the request.
            $obj = array();

            //Add auth info to $obj container
            $obj['auth'] = new SoapVar($authinfo, SOAP_ENC_OBJECT, null, null, null, self::ROOT_LEVEL_NAMESPACE);
           

            //Add the soapvars from namedparams to the container using their key as their member name.
            foreach($namedparams as $key => $value)
            {
                $obj[$key] = $value;
            }

            //Create a soap param using the obj container 
            $param = new SoapParam(new SoapVar($obj, SOAP_ENC_OBJECT), 'data');
            
            //Add the created soap param to an array to be passed to __soapCall
            $params = array($param);
        }

        //Update current action with the method being called.
        $this->currentaction = "http://tempuri.org/ISessionManagement/$methodname";

        // Make the SOAP call via SoapClient::__soapCall.
        return parent::__soapCall($methodname, $params);
    }  

     /**
     * Sample function for calling an API method. This method will call the sessionmanagement method GetSessionsList.
     * Because this method calls a method from the SessionManagement API, it should only be called by a soap client
     * that has been initialized to SessionManagement.
     * Auth parameter will be created within the soap clients calling logic.
     * $request is a soap encoded ListSessionsRequest object
     * $searchQuery is an optional string containing an custom sql query 
     */
    public function get_session_list($request, $searchQuery) 
    {
        $requestvar = new SoapVar($request, SOAP_ENC_OBJECT, null, null, null, self::ROOT_LEVEL_NAMESPACE);
        $searchQueryVar = new SoapVar($searchQuery, XSD_STRING, null, null, null, self::ROOT_LEVEL_NAMESPACE);

        return self::call_web_method("GetSessionsList", array("request" => $requestvar, "searchQuery" => $searchQueryVar));
    }
    
    public function get_sessions_by_id($sessionId)
    {
        $searchQueryVar = new SoapVar($sessionId, SOAP_ENC_OBJECT, null, null, null, self::ROOT_LEVEL_NAMESPACE);

        return self::call_web_method("GetSessionsById", array("sessionIds" => $searchQueryVar));
    }
    
      /**
    * Override SOAP action to work around bug in older PHP SOAP versions.
    */
    public function __doRequest($request, $location, $action, $version, $oneway = null) {
        error_log(var_export($request,1));
        return parent::__doRequest($request, $location, $this->currentaction, $version);
    }

}

    //The username of the calling panopto user.
    $UserKey = "";

    //The name of the panopto server to make API calls to (i.e. demo.hosted.panopto.com)
    $ServerName = "";

    //The application key from provider on the Panopto provider's page. Should be a string representation of a guid.
    $ApplicationKey = "";

    //Password of the calling user on Panopto server. Only required if external provider does not have a bounce page.
    $Password = null;

    //generate an auth code
    $AuthCode = generate_auth_code($UserKey, $ServerName, $ApplicationKey);
    

    //Create a SOAP client for the desired Panopto API class, in this cas SessionManagement
    $sessionManagementClient = new PanoptoSessionManagementSoapClient($ServerName, $UserKey, $AuthCode, $Password);
    
    //Set https endpoint in case wsdl specifies http 
    $sessionManagementClient ->__setLocation("https://". $ServerName . "/Panopto/PublicAPI/4.6/SessionManagement.svc");

    
    //$requestPagination = Create_Pagination_Object(100, 0);

    //Create a list session request object. Sample values shown here.
    /*$listSessionsRequest = Create_ListSessionsRequest_Object(
        "2017-02-27T12:12:22",
        "246ed971-f254-43d3-8422-e19bfaba69a3", 
        "00000000-0000-0000-0000-000000000000", 
        "Name", 
        true, 
        "2009-02-27T12:12:22");*/

    //Call api and get response
    //$response = $sessionManagementClient->get_session_list($listSessionsRequest, "");
    $session_ids_array = array('45c48e07-c7b7-4f0a-9530-89ffd7f517a4', 'e0f0ec10-ffd5-45b6-b169-db395ec2fe6b', '5ce80bca-0803-4342-9ffc-f5b0c4818ad9');
    $ArrayOfGuid = new ArrayOfGuid($session_ids_array);
    //echo '<pre>';
    //print_r($ArrayOfGuid);
    // Call api and get respponse
    $response_access_details = $sessionManagementClient->get_sessions_by_id($ArrayOfGuid, "");
   
    //Display response. It will be a json encoded object of type GetSessionsListResult. See API documentation for members.
    echo '<pre>';
    print_r($response_access_details);
    //print_r($response);

    /*
    *Function to create an api auth code for use when calling methods from the Panopto API.
    */
    function generate_auth_code($userkey, $servername, $applicationkey) {       
        $payload = $userkey . "@" . $servername;
        $signedpayload = $payload . "|" . $applicationkey;
        $authcode = strtoupper(sha1($signedpayload));
        return $authcode;
    }

    
     function Create_Pagination_Object($maxNumberResults, $pageNumber)
     {
         
        //Create empty object to store member data
        $pagination = new stdClass();
        $pagination->MaxNumberResults = $maxNumberResults;
        $pagination->PageNumber = $pageNumber;
        
        return $pagination;
     }
    
    //Example of creating object for use in a SOAP request.
    //This will create a ListSessionsRequest object for use as a parameter in the 
    //ISessionManagement.GetSessionsList method.
    //Refer to the API documentation on the requirements and datatypes of members.
    //Members must be created within the containing object in the same order they appear in the documentation.
    //All names are case sensitive.
    function Create_ListSessionsRequest_Object($endDate, $folderId, $remoteRecorderId, $sortBy, $sortIncreasing, $startDate)
    {

        //Create empty object to store member data
        $listSessionsRequest = new stdClass();

        $listSessionsRequest->EndDate = new SoapVar($endDate, XSD_STRING, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        $listSessionsRequest->FolderId = new SoapVar($folderId, XSD_STRING, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        $listSessionsRequest->RemoteRecorderId = new SoapVar($remoteRecorderId, XSD_STRING, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        $listSessionsRequest->SortBy = new SoapVar($sortBy, XSD_STRING, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        $listSessionsRequest->SortIncreasing = new SoapVar($sortIncreasing, XSD_BOOLEAN, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        $listSessionsRequest->StartDate = new SoapVar($startDate, XSD_STRING, null, null, null, PanoptoSessionManagementSoapClient::OBJECT_MEMBER_NAMESPACE);
        return $listSessionsRequest;
    }
    
    
    
  class ArrayOfGuid
  {
    public $guid;

    public function __construct($guid)
    {
      $this->guid = (gettype($guid) == "string") ? array($guid) : $guid;
    }

    public function getGuid() {
      return $this->guid;
    }

    public function setGuid($guid) {
      $this->guid = $guid;
    }

    public function addAGuid($guid)
    {
      //var_dump($guid);
      $this->guid[] = $guid;
    }
  }
?>