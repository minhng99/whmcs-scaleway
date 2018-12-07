<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly  _|_");
}


//     _    ____ ___     ____    _    _     _     ____
//    / \  |  _ \_ _|   / ___|  / \  | |   | |   / ___|
//   / _ \ | |_) | |   | |     / _ \ | |   | |   \___ \
//  / ___ \|  __/| |   | |___ / ___ \| |___| |___ ___) |
// /_/   \_\_|  |___|   \____/_/   \_\_____|_____|____/
class ScalewayApi
{
    private $token = "";
    private $org_id = "";
    private $callUrl = "";

    // Status codes returned by scaleway
    public $statusCodes =
                [
                    "200" => "Scaleway API - OK",
                    "400" => "Scaleway API - Error 400: bad request. Missing or invalid parameter?",
                    "201" => "Scaleway API - Error 201: This is not an error but you should not be here!",
                    "204" => "Scaleway API - Error 204: A delete action performed successfully! You should not be here however!",
                    "401" => "Scaleway API - Error 401: auth error. No valid API key provided!",
                    "402" => "Scaleway API - Error 402: request failed. Parameters were valid but request failed!",
                    "403" => "Scaleway API - Error 403: forbidden. Insufficient privileges to access requested resource or the caller IP may be blacklisted!",
                    "404" => "Scaleway API - Error 404: not found 404 not found 404 not found 404 not found, what are you looking for?",
                    "50x" => "Scaleway API - Error 50x: means server error. Dude, this is bad..:(",
                    //Custom
                    "123" => "Error 123: means new volume creation failed. This error appear when try to allocate new volume for the new server!"
                ];
    
    public static $commercialTypes =
                [
                    // Type => processor_cores D[dedicated]/S[hared]C_RAM
                    "START1-XS"        => "JIFFY_TINY",
                    "START1-S"       => "JIFFY_SMALL",
                    "START1-M"       => "JIFFY_MEDIUM",
                    "START1-L"       => "JIFFY_BIG",
                ];
    public static $availableLocations =
                [
                    "Paris"     => "par1",
                    "Amsterdam" => "ams1",
                ];


    public function __construct($tokenStr, $org_idStr, $location)
    {
        $this->token = $tokenStr;
        $this->org_id = $org_idStr;
        //We have to build call url with the right location (par1 or ams1)
        //Example: https://cp-par1.scaleway.com
        $this->callUrl = "https://cp-" . ScalewayApi::$availableLocations[$location] . ".scaleway.com";
    }
    // ____       _            _
    //|  _ \ _ __(_)_   ____ _| |_ ___  ___
    //| |_) | '__| \ \ / / _` | __/ _ \/ __|
    //|  __/| |  | |\ V / (_| | ||  __/\__ \
    //|_|   |_|  |_| \_/ \__,_|\__\___||___/
    //
    //This is function used to call Scaleway API
    private function call_scaleway_api($token, $http_method, $endpoint, $get = array(), $post = array())
    {
        $loggingData = "";


        if (!empty($get)) {
            $endpoint .= '?' . http_build_query($get);
        }
     
        $call = curl_init();
        

        curl_setopt($call, CURLOPT_URL, $this->callUrl . $endpoint);


        
                    
        
        curl_setopt($call, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        $headers = [
                    "X-Auth-Token: " . $token,
                    "Content-Type: application/json"
                   ];
        curl_setopt($call, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($call, CURLOPT_RETURNTRANSFER, true);
     
        if ($http_method == 'POST') {
            $post=json_encode($post);
            curl_setopt($call, CURLOPT_POST, true);
            curl_setopt($call, CURLOPT_POSTFIELDS, $post);
        } else {
            $post=http_build_query($post);
            curl_setopt($call, CURLOPT_POST, true);
            curl_setopt($call, CURLOPT_CUSTOMREQUEST, $http_method);
            curl_setopt($call, CURLOPT_POSTFIELDS, $post);
        }
        
        $result = curl_exec($call);

        $loggingData .= "HEADER: " . json_encode($headers) . "\n\n";
        $loggingData .= "GET: " . $this->callUrl .$endpoint . "\n\n";
        $loggingData .= "POST: " .$post . "\n\n";
        

        $resultHttpCode = curl_getinfo($call, CURLINFO_HTTP_CODE);
        curl_close($call);
        if ($resultHttpCode == "") {
            $tmpArr = array("message" => "CURL to Backend failed!");
            $result = json_encode($tmpArr);
        }


        //Send error to Utilities >> Log >> Module Log
        logModuleCall('Scaleway', __FUNCTION__, $loggingData, '', $result);

        //Return an arry with HTTP_CODE returned and the JSON content writen by server.
        return array(
                    "httpCode" => $resultHttpCode,
                    "json" => $result
                    );
    }
    
    //Server actions are: power on, power off, reboot
    private function execute_server_action($action, $server_id)
    {
        if ($action != "poweron" && $action != "poweroff" && $action != "reboot" && $action != "terminate") {
            $resp =
                [
                    "httpCode" => "400",
                    "json" => "{\"error\" : \"error\"}"
                ];
            return $resp;
        }
        $http_method = "POST";
        $endpoint = "/servers/" . $server_id . "/action";
        $postParams =
            [
                "action" => $action
            ];
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);
        return $result;
    }
    // ____        _     _ _
    //|  _ \ _   _| |__ | (_) ___ ___
    //| |_) | | | | '_ \| | |/ __/ __|
    //|  __/| |_| | |_) | | | (__\__ \
    //|_|    \__,_|_.__/|_|_|\___|___/
    //

    // Get all avilable volumes created by API owner
    public function retrieve_volumes()
    {
        $http_method = "GET";
        $endpoint = "/volumes";
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }
    //Create a new volume in order to be able to create a new server
    public function create_new_volume($name, $size)
    {
        $http_method = "POST";
        $endpoint = "/volumes";
        $volumeType = "l_ssd";
        $postParams =
            [
                "name" => $name,
                "organization" => $this->org_id,
                "volume_type" => $volumeType,
                "size" => $size
            ];
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);
        return $result;
    }
    //Get volume info by ID
    public function retrieve_volume_info($id)
    {
        $http_method = "GET";
        $endpoint = "/volumes/" . $id;
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }
    //Delete a volume by it's id
    public function delete_volume($id)
    {
        $http_method = "DELETE";
        $endpoint = "/volumes/" . $id;
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }
    //Function to instantiate a new server
    public function create_new_server($name, $image, $commercial_type, $tags)
    {
        $http_method = "POST";
        $endpoint = "/servers";

        $postParams =
            [
                "organization" => $this->org_id,
                "name"         => $name,
                "image"        => $image,
                "commercial_type" => $commercial_type,
                "tags"         => $tags,
                "boot_type"  => "local"
                //"volumes"      => $volumes
            ];
        $server_creation_result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);
        if ($server_creation_result['httpCode'] != 210) {
            //If created more than one volumes consider removing it as server creation failed
            if (isset($vol_id)) {
                $this->delete_volume($vol_id);
            }
        }
        //Dirty code, sorry.
        $srv_id = json_decode($server_creation_result["json"], true)["server"]["id"];
        $this->execute_server_action("poweron", $srv_id);
        return $server_creation_result;
    }
    //Function which return server info
    public function retrieve_server_info($server_id)
    {
        if ($server_id == "" || strpos($server_id, 'terminate')) { //We have to prevent endpoint becaming /servers/{NULL}, it will print all servers and we don't want this!
            $server_id = "00000000-0000-0000-0000-000000000000";
        }
        $http_method = "GET";
        $endpoint = "/servers/" . $server_id;
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }
    //Delete an IPv4 Address
    public function delete_ip_address($ip_id)
    {
        $http_method = "DELETE";
        $endpoint = "/ips/" . $ip_id;
        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }
    //Delete a server by ID. This include IP and Volumes removal
    public function server_terminate($server_id)
    {
        //Retrieve volume id and server state
        $call = $this->retrieve_server_info($server_id);
        if ($call["httpCode"] != 200) {
            return $call;
        }
        $serverState = (json_decode($call["json"], true)["server"]["state"]);
        $vol_id_attached = (json_decode($call["json"], true)["server"]["volumes"]["0"]["id"]);
        $ip_id_attached = (json_decode($call["json"], true)["server"]["public_ip"]["id"]);
        //Easy way
        if ($serverState == "running") {
            $response = $this->execute_server_action("terminate", $server_id);
            return $response;
        } elseif ($serverState == "stopped") { //Hard wa
            $http_method = "DELETE";
            $endpoint = "/servers/" . $server_id;
            $response = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
            if ($response["httpCode"] == 204) {
                //We have deleted the server and now we have to delete volume and IP manually
                $delVolRes  = $this->delete_volume($vol_id_attached);
                $delIpRes = $this->delete_ip_address($ip_id_attached);
                if ($delVolRes["httpCode"] == 204) {
                    if ($delIpRes["httpCode"] == 204) {
                        return $response;
                    } else {
                        return $delIpRes;
                    }
                } else {
                    return $delVolRes;
                }
            }
            return $response;
        } else {
            //Server may be booting or pending for an action. In this case we have to try after server get into a valid state
            return array(
                            "httpCode" => "123",
                            "json" => json_encode(array("message" => "Server is into an intermediate state! Wait for power on/off then try again!"))
                         );
        }
    }
    //Function used to suspend the server
    public function server_poweroff($server_id)
    {
        //Suspension mean power off and storing volume offline
        $result = $this->execute_server_action("poweroff", $server_id);
        return $result;
    }
    //Function to hard reboot the server
    public function server_reboot($server_id)
    {
        $result = $this->execute_server_action("reboot", $server_id);
        return $result;
    }
    //Function to power on an server
    public function server_poweron($server_id)
    {
        $result = $this->execute_server_action("poweron", $server_id);
        return $result;
    }
}
// ____  _____ ______     _______ ____  ____     __  __    _    _   _    _    ____ _____ __  __ _____ _   _ _____
/// ___|| ____|  _ \ \   / / ____|  _ \/ ___|   |  \/  |  / \  | \ | |  / \  / ___| ____|  \/  | ____| \ | |_   _|
//\___ \|  _| | |_) \ \ / /|  _| | |_) \___ \   | |\/| | / _ \ |  \| | / _ \| |  _|  _| | |\/| |  _| |  \| | | |
// ___) | |___|  _ < \ V / | |___|  _ < ___) |  | |  | |/ ___ \| |\  |/ ___ \ |_| | |___| |  | | |___| |\  | | |
//|____/|_____|_| \_\ \_/  |_____|_| \_\____/   |_|  |_/_/   \_\_| \_/_/   \_\____|_____|_|  |_|_____|_| \_| |_|
// This class is designed to work with servers as it is more easy to use than the API class.
// If an action is not implemented in this class then you'll have to use the main class and implement by yourself.
// It's a kind of wrapper for the main ScalewayAPI class which return JSON.
// It will make your life easier as it already check the response for errors and returns the right message.
class ScalewayServer
{
    protected $api = "";
    protected $srvLoc = "par1"; //let's set a default value.
    public $server_id = "";
    //This store the API result. Usefull in case of error.
    public $queryInfo = "";
    public $image = array(
            //There are a lot more details, we keep onle those below:
            "name"        => "",
            "arch"        => "",
            "id"          => "",
            "root_volume" => array(
                                    "size"        => "",
                                    "id"          => "",
                                    "volume_type" => "",
                                    "name"        => ""
                                )
        );
    public $creation_date = "";
    public $public_ip = array(
                "dynamic" => false,
                "id"      => "",
                "address" => ""
            );
    public $private_ip = "";
    public $id = "";
    public $dynamic_ip_required = false;
    public $modification_date = "";
    public $hostname = "";
    public $state = "";
    public $commercial_type = "";
    public $tags = array();
    public $arch = "";
    public $extra_networks = array();
    public $name = "";
    public $volumes = array();
    public $security_group = array(
                "id"   => "",
                "name" => ""
            );
    public function __construct($token, $org_id, $location)
    {
        $this->srvLoc = $location;
        $this->api = new ScalewayApi($token, $org_id, $this->srvLoc);
    }
    public function setServerId($srv_id)
    {
        $this->server_id = $srv_id;
    }
    public function retrieveDetails()
    {
        $serverInfoResp = $this->api->retrieve_server_info($this->server_id);
        if ($serverInfoResp["httpCode"] == 200) {
            $serverInfoResp = json_decode($serverInfoResp["json"], true);
            $serverInfoResp = $serverInfoResp["server"];
            $this->image["name"] = $serverInfoResp["image"]["name"];
            $this->image["arch"] = $serverInfoResp["image"]["arch"];
            $this->image["id"] = $serverInfoResp["image"]["id"];
            $this->image["root_volume"]["size"] = $serverInfoResp["image"]["root_volume"]["size"];
            $this->image["root_volume"]["id"] = $serverInfoResp["image"]["root_volume"]["id"];
            $this->image["root_volume"]["volume_type"] = $serverInfoResp["image"]["root_volume"]["volume_type"];
            $this->image["root_volume"]["name"] = $serverInfoResp["image"]["root_volume"]["name"];
            $this->creation_date = $serverInfoResp["creation_date"];
            $this->public_ip["dynamic"] = $serverInfoResp["public_ip"]["dynamic"];
            $this->public_ip["id"] = $serverInfoResp["public_ip"]["id"];
            $this->public_ip["address"] = $serverInfoResp["public_ip"]["address"];
            $this->private_ip = $serverInfoResp["private_ip"];
            $this->id = $serverInfoResp["id"];
            $this->dynamic_ip_required = $serverInfoResp["dynamic_ip_required"];
            $this->modification_date = $serverInfoResp["modification_date"];
            $this->hostname = $serverInfoResp["hostname"];
            $this->state = $serverInfoResp["state"];
            $this->commercial_type = $serverInfoResp["commercial_type"];
            $this->tags = $serverInfoResp["tags"];
            $this->arch = $serverInfoResp["arch"];
            $this->extra_networks = $serverInfoResp["extra_networks"];
            $this->volumes = $serverInfoResp["volumes"];
            $this->security_group["id"] = $serverInfoResp["security_group"]["id"];
            $this->security_group["name"] = $serverInfoResp["security_group"]["name"];
            $this->organization = $serverInfoResp["organization"];
            $this->queryInfo = "Success!";
            return true;
        } else {
            $this->queryInfo = $this->api->statusCodes[$serverInfoResp["httpCode"]];
            return false;
        }
    }
    public function create_new_server($name, $image_id, $commercial_type, $tags = array())
    {
        $createServerResult = $this->api->create_new_server($name, $image_id, $commercial_type, $tags);
        if ($createServerResult["httpCode"] == 201) {
            $serverInfo = json_decode($createServerResult["json"], true);
            $serverInfo = $serverInfo["server"];
            $this->server_id = $serverInfo["id"];
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo =  $this->api->statusCodes[$createServerResult["httpCode"]];
            return false;
        }
    }
    public function delete_server()
    {
        $deleteServerResponse = $this->api->server_terminate($this->server_id);
        if ($deleteServerResponse["httpCode"] == 202) {
            return true;
        } else {
            $this->queryInfo = json_decode($deleteServerResponse["json"], true)["message"];
            return false;
        }
    }
    public function poweroff_server()
    {
        $poweroff_result = $this->api->server_poweroff($this->server_id);
        if ($poweroff_result["httpCode"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($poweroff_result["json"], true)["message"];
            return false;
        }
    }
    public function poweron_server()
    {
        $poweron_result = $this->api->server_poweron($this->server_id);
        if ($poweron_result["httpCode"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($poweron_result["json"], true)["message"];
            return false;
        }
    }
    public function reboot_server()
    {
        $reboot_result = $this->api->server_reboot($this->server_id);
        if ($reboot_result["httpCode"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($reboot_result["json"], true)["message"];
            return false;
        }
    }

    public static function getArchByCommercialType($cType)
    {
        $len = strlen($cType);
        if ($len < 2) {  //to make sure we don't return default arch for null strings
            return "unknown";
        }
        //We have two possible architectures to return: arm | x86_64
        $cType = strtolower($cType);
        if (strpos($cType, 'arm') !== false || strpos($cType, 'c1') !== false) {   //if contains arm/c1 in name, it is ARM architecture
            return "arm";
        } else {
            return "x86_64";
        }
    }

    public static function getImageIDByName($image_name)
    {

        $image_id = array(
            "Ubuntu 18.04 LTS x86_64 25GB" => "baac099f-2536-4d9e-b9ae-b5c4268a07c5"
        );

        return $image_id[$image_name];
    }
}

//__        ___   _ __  __  ____ ____     ____  _   _ _     _     _____
//\ \      / / | | |  \/  |/ ___/ ___|   |  _ \| | | | |   | |   |__  /
// \ \ /\ / /| |_| | |\/| | |   \___ \   | |_) | | | | |   | |     / /
//  \ V  V / |  _  | |  | | |___ ___) |  |  _ <| |_| | |___| |___ / /_
//   \_/\_/  |_| |_|_|  |_|\____|____/   |_| \_\\___/|_____|_____/____|
//All WHMCS required functions are bellow
//    _    ____  __  __ ___ _   _ ___ ____ _____ ____      _  _____ ___  ____
//   / \  |  _ \|  \/  |_ _| \ | |_ _/ ___|_   _|  _ \    / \|_   _/ _ \|  _ \
//  / _ \ | | | | |\/| || ||  \| || |\___ \ | | | |_) |  / _ \ | || | | | |_) |
// / ___ \| |_| | |  | || || |\  || | ___) || | |  _ <  / ___ \| || |_| |  _ <
///_/   \_\____/|_|  |_|___|_| \_|___|____/ |_| |_| \_\/_/   \_\_| \___/|_| \_\
function Scaleway_MetaData()
{
    return array(
        'DisplayName' => 'Scaleway',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}
function Scaleway_ConfigOptions()
{
    $commercial_types = array();
    foreach (ScalewayApi::$commercialTypes as $ctype => $cval) {
        array_push($commercial_types, ($ctype . " - " . $cval));
    }
    return array(
        // a password field type allows for masked text input
        'Token' => array(
            'Type' => 'textarea',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway secret token - used to access your account',
        ),
        'Organization ID' => array(
            'Type' => 'textarea',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway Organization ID - used to access your account',
        ),
        // the dropdown field type renders a select menu of options
        'Commercial type' => array(
            'Type' => 'dropdown',
            'Options' => $commercial_types,
            'Description' => 'Choose one',
        ),
        // a text field type allows for single line text input
        'Admin username' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter an username of an WHMCS Administrator',
        ),
    );
}
function Scaleway_CreateAccount(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $commercial_type = array_keys(ScalewayApi::$commercialTypes)[$params["configoption3"]]; //it provide only index of commercial type so we fetch full name from predefined array
        $arch = ScalewayServer::getArchByCommercialType($commercial_type);
        $service_id = $params["serviceid"];
        $user_id = $params["userid"];
        $productid = $params["pid"];
        $hostname = explode(".", $params["domain"])[0];
        $password = $params["password"];
        $os_name = $params["customfields"]["Operating system"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        }
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $image_id = ScalewayServer::getImageIDByName($os_name);
        if (strlen($image_id) < 25) {
            return "Invalid image and/or designated architecture";
        }
        $tags = array("uid:" . $user_id, "pid:" . $productid, "service_id:" . $service_id, "serverid:" . $params["serverid"]);
        //Check if the current server were terminated
        if ($scwServer->retrieveDetails() == true) {
            return "Error! Please terminate current server then create another server again!";
        }
        //Now we have to create the new server and update Server ID field
        if ($scwServer->create_new_server($hostname, $image_id, $commercial_type, $tags)) {
            //If server grated, retrive his id and insert to Server ID field, so next time we know his ID.
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $service_id;
            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["customfields"] = base64_encode(serialize(array("Server ID"=> $scwServer->server_id )));
            localAPI($command, $values, $adminuser);
        } else {
            //Log request to understand why it failed
            $request = "";
            //User and service info
            $request .= "Service ID: " . $service_id . "\n";
            $request .= "User ID: " . $user_id . "\n";
            $request .= "Product ID: " . $productid . "\n";
            $request .= "ORG ID: " . $org_id . "\n";
            //Config info
            $request .= "Commercial type: " . $commercial_type . "\n";
            $request .= "Location: " . $location . "\n";
            //And finally server info
            $request .= "Hostname: " . $hostname . "\n";
            $request .= "Password: " . $password . "\n";
            $request .= "OS Name: " . $os_name . "\n";
            $request .= "Arch: " . $arch . "\n";
            $request .= "Curr server ID: " . $curr_server_id . "\n";
            //Response
            $response = $scwServer->queryInfo;
            //Send error to Utilities >> Log >> Module Log
            logModuleCall('Scaleway', __FUNCTION__, $request, "blabla", $response);
            return "Failed to create server! Check Utilites >> Log >> Module log. Details: " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_SuspendAccount(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return "Invalid server id!";
        }
        if ($scwServer->poweroff_server()) {
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $params["serviceid"];
            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["status"] = "Suspended";
            localAPI($command, $values, $adminuser);
        } else {
            return "Failed to suspend server! " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_UnsuspendAccount(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return "Invalid server id!";
        }
        if ($scwServer->poweron_server()) {
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $params["serviceid"];
            $values["status"] = "Active";
            localAPI($command, $values, $adminuser);
        } else {
            return "Failed to unsuspend server! " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_RebootServer(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return "Invalid server id!";
        }
        if ($scwServer->reboot_server()) {
            return "success";
        } else {
            return "Failed to reboot server! " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_TerminateAccount(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return "Invalid server id!";
        }
        if ($scwServer->delete_server()) {
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $params["serviceid"];
            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["status"] = "Terminated";
            $values["customfields"] = base64_encode(serialize(array("Server ID"=> "terminated-" . $curr_server_id )));  //keep history of server ID in case client made nasty things from your server
            localAPI($command, $values, $adminuser);
        } else {
            return "Failed to terminate server server! " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_AdminCustomButtonArray()
{
    return array(
        "Reboot server"=> "RebootServer",
        "Update stats" => "updateStats",
    );
}
function Scaleway_updateStats(array $params)
{
    try {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        $scwServer->setServerId($server_id);
        if (!$scwServer->retrieveDetails()) {
            return "Can't get server info! " . $scwServer->queryInfo;
        }
        //Updating fields with data returned from Scaleway.
        $command = "updateclientproduct";
        $adminuser = $params["configoption4"];
        $values["serviceid" ] = $params["serviceid"];
        $values["customfields"] = base64_encode(serialize(
            array(
                //Those are just custom fields!
                "Operating system"=>$scwServer->image["name"]
            )
        ));
        $values["dedicatedip"] = $scwServer->public_ip["address"];
        //$values["serviceusername"] = "administrator";
        $values["domain"] = $scwServer->hostname;
        localAPI($command, $values, $adminuser);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
function Scaleway_AdminServicesTabFields(array $params)
{
    try {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        $scwServer->setServerId($server_id);
        if (!$scwServer->retrieveDetails()) {
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $params["serviceid"];
            $values["dedicatedip"] = "unknown";
            localAPI($command, $values, $adminuser);
            return array("Server error" => $scwServer->queryInfo);
        }

        //Updating fields with data returned from Scaleway.
        $command = "updateclientproduct";
        $adminuser = $params["configoption4"];
        $values["serviceid"] = $params["serviceid"];
        $values["customfields"] = base64_encode(serialize(array( "Operating system"=>$scwServer->image["name"] )));
        $values["dedicatedip"] = $scwServer->public_ip["address"];
        $values["domain"] = $scwServer->hostname;
        //Need to analyze. It make same variables become undefined...
        localAPI($command, $values, $adminuser);
        // Return an array based on the function's response.
        return array(
            'Server name' => $scwServer->hostname,
            'Server state' => $scwServer->state,
            'Root volume' => $scwServer->image["root_volume"]["name"] . " (" . $scwServer->image["root_volume"]["size"] . ") [" . $scwServer->image["root_volume"]["id"] . "] -- Type: " . $scwServer->image["root_volume"]["volume_type"],
            'Image' => $scwServer->image["name"] . " [" . $scwServer->image["id"] . "]",
            'Creation date' =>$scwServer->creation_date,
            'Modification date' => $scwServer->modification_date,
            'Public IP v4' => $scwServer->public_ip["address"] . " [" . $scwServer->public_ip["id"] . "]",
            'Private IP v4' => $scwServer->private_ip ,
            'Dynamic IP Required' => $scwServer->dynamic_ip_required,
            'Location' => $location,
            'Commercial type' => $scwServer->commercial_type,
            'Tags' => implode(",", $scwServer->tags),
            'Architecture' => $scwServer->arch,
            'Extra networks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
            'Volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
            'Security group' => $scwServer->security_group["name"] . " [" . $scwServer->security_group["id"] . "]",
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
    return array();
}

//  ____ _     ___ _____ _   _ _____
// / ___| |   |_ _| ____| \ | |_   _|
//| |   | |    | ||  _| |  \| | | |
//| |___| |___ | || |___| |\  | | |
// \____|_____|___|_____|_| \_| |_|
function Scaleway_ClientAreaCustomButtonArray()
{
    return array(
        "Update stats" => "ClientUpdateStatsFunction",
        "Reboot server" => "ClientRebootServer",
        "Power OFF" => "ClientPowerOffServer",
        "Power ON" => "ClientPowerOnServer"
    );
}
function Scaleway_ClientRebootServer(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return " - Error details: invalid server id!";
        }
        if ($scwServer->reboot_server()) {
            return "success";
        } else {
            return "- Failed to reboot server! Error details: " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}
function Scaleway_ClientPowerOffServer(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return " - Error details: invalid server id!";
        }
        if ($scwServer->poweroff_server()) {
            return "success";
        } else {
            return "- Failed to poweroff server! Error details: " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}
function Scaleway_ClientPowerOnServer(array $params)
{
    try {
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        if (strlen($curr_server_id) == 36) {
            $scwServer->setServerId($curr_server_id);
        } else {
            return " - Error details: invalid server id!";
        }
        if ($scwServer->poweron_server()) {
            return "success";
        } else {
            return "- Failed to poweron server! Error details: " . $scwServer->queryInfo;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}
function Scaleway_ClientArea(array $params)
{
    try {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        $scwServer->setServerId($server_id);
        $scwServer->retrieveDetails();

        return array(
            'templateVariables' => array(
                'sid' =>$scwServer->server_id,
                'sname' => $scwServer->hostname,
                'sstate' => $scwServer->state,
                'rootvolume' => "Size: " . $scwServer->image["root_volume"]["size"]/1000000000 . "GB" . " Type: " . $scwServer->image["root_volume"]["volume_type"],
                'image' => $scwServer->image["name"],
                'creationdate' =>$scwServer->creation_date,
                'publicipv4' => "Address: " . $scwServer->public_ip["address"],
                'privateipv4' => "Address: " . $scwServer->private_ip ,
                'dynamiciprequired' => $scwServer->dynamic_ip_required,
                'modificationdate' => $scwServer->modification_date,
                'location' => $location,
                'commercialtype' => $scwServer->commercial_type,
                'tags' => implode(",", $scwServer->tags),
                'architecture' => $scwServer->arch,
                'extranetworks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
                'volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
                'securitygroup' => "Name:" . $scwServer->security_group["name"] . " -- ID: " . $scwServer->security_group["id"],
            ));
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ));
    }
}
function Scaleway_ClientUpdateStatsFunction(array $params)
{
    try {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $org_id = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($token, $org_id, $location);
        $scwServer->setServerId($server_id);
        $scwServer->retrieveDetails();

        return array(
            'templateVariables' => array(
                'sid' =>$scwServer->server_id,
                'sname' => $scwServer->hostname,
                'sstate' => $scwServer->state,
                'rootvolume' => "Size: " . $scwServer->image["root_volume"]["size"]/1000000000 . "GB" . " Type: " . $scwServer->image["root_volume"]["volume_type"],
                'image' => $scwServer->image["name"],
                'creationdate' =>$scwServer->creation_date,
                'publicipv4' => "Address: " . $scwServer->public_ip["address"],
                'privateipv4' => "Address: " . $scwServer->private_ip ,
                'dynamiciprequired' => $scwServer->dynamic_ip_required,
                'modificationdate' => $scwServer->modification_date,
                'location' => $location,
                'commercialtype' => $scwServer->commercial_type,
                'tags' => implode(",", $scwServer->tags),
                'architecture' => $scwServer->arch,
                'extranetworks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
                'volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
                'securitygroup' => "Name:" . $scwServer->security_group["name"] . " -- ID: " . $scwServer->security_group["id"],
            ));
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ));
    }
}
