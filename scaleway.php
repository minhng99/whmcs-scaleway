<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


//function logModuleCall($a, $b, $c, $d, $e)
//{
//};

//     _    ____ ___     ____    _    _     _     ____
//    / \  |  _ \_ _|   / ___|  / \  | |   | |   / ___|
//   / _ \ | |_) | |   | |     / _ \ | |   | |   \___ \
//  / ___ \|  __/| |   | |___ / ___ \| |___| |___ ___) |
// /_/   \_\_|  |___|   \____/_/   \_\_____|_____|____/
class ScalewayApi
{
    private $c_Token = "";
    private $c_OrgID = "";
    private $c_APIURL = "";

    // Status codes returned by scaleway
    public $c_HTTPStatus =
        [
            "200" => "Scaleway API - 200: OK.",
            "201" => "Scaleway API - 201: Created.",
            "204" => "Scaleway API - 204: No Content.",
            "400" => "Scaleway API - 400: Bad Request - Missing or invalid parameter.",
            "401" => "Scaleway API - 401: Auth Error - Invalid Token/Organization_ID.",
            "402" => "Scaleway API - 402: Request Failed - Parameters were valid but request failed.",
            "403" => "Scaleway API - 403: Forbidden - Access to resource is prohibited.",
            "404" => "Scaleway API - 404: Not found - API Failed or object does not exist.",
            "50x" => "Scaleway API - 50x: Backend on fire.",
            //Custom
            "123" => "Error 123: means new volume creation failed. This error appear when try to allocate new volume for the new server!"
        ];
        

    public $c_CommercialTypes =
        [
            "START1-XS" => 
                [
                    "Disk" => 25,
                    "CPU" => 1,
                    "RAM" => 1,
                ],
            "START1-S" => 
                [
                    "Disk" => 50,
                    "CPU" => 2,
                    "RAM" => 2,
                ],
            "START1-M" => 
                [
                    "Disk" => 100,
                    "CPU" => 4,
                    "RAM" => 4,
                ],
            "START1-L" => 
                [
                    "Disk" => 200,
                    "CPU" => 8,
                    "RAM" => 8,
                ],
        ];


    public function __construct($f_Token, $f_OrgID, $f_Location)
    {
        $this->c_Token = $f_Token;
        $this->c_OrgID = $f_OrgID;

        $f_Locations =
            [
                "Paris"     => "par1",
                "Amsterdam" => "ams1",
            ];

        $this->c_APIURL = "https://cp-" . $f_Locations[$f_Location] . ".scaleway.com";
    }
    // ____       _            _
    //|  _ \ _ __(_)_   ____ _| |_ ___  ___
    //| |_) | '__| \ \ / / _` | __/ _ \/ __|
    //|  __/| |  | |\ V / (_| | ||  __/\__ \
    //|_|   |_|  |_| \_/ \__,_|\__\___||___/
    //
    //This is function used to call Scaleway API
    private function c_Call_Scaleway($f_Token, $f_HTTP_Method, $f_Endpoint, $f_GET_Data = array(), $f_POST_Data = array())
    {
        if (!empty($f_GET_Data)) {
            $f_Endpoint .= '?' . http_build_query($f_GET_Data);
        }

        $f_CURL = curl_init();
        curl_setopt($f_CURL, CURLOPT_URL, $this->c_APIURL . $f_Endpoint);
        curl_setopt($f_CURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $f_CURL_Header =
            [
                "X-Auth-Token: " . $f_Token,
                "Content-Type: application/json"
            ];
        curl_setopt($f_CURL, CURLOPT_HTTPHEADER, $f_CURL_Header);
        curl_setopt($f_CURL, CURLOPT_RETURNTRANSFER, true);
     
        if ($f_HTTP_Method == 'POST') {
            $f_POST_Data=json_encode($f_POST_Data);
            curl_setopt($f_CURL, CURLOPT_POST, true);
            curl_setopt($f_CURL, CURLOPT_POSTFIELDS, $f_POST_Data);
        } else {
            $f_POST_Data=http_build_query($f_POST_Data);
            curl_setopt($f_CURL, CURLOPT_POST, true);
            curl_setopt($f_CURL, CURLOPT_CUSTOMREQUEST, $f_HTTP_Method);
            curl_setopt($f_CURL, CURLOPT_POSTFIELDS, $f_POST_Data);
        }

        $f_CURL_Reply = curl_exec($f_CURL);
        $f_CURL_Reply_Code = curl_getinfo($f_CURL, CURLINFO_HTTP_CODE);

        curl_close($f_CURL);

        if ($f_CURL_Reply_Code == "") {
            $f_CURL_Reply = json_encode(array("message" => "CURL to Backend FAILED!"));
        }

        // Logging debug data
        $f_Debug_Data .= "HEADER: " . json_encode($f_CURL_Header) . "\n\n";
        $f_Debug_Data .= "GET: " . $this->c_APIURL . $f_Endpoint . "\n\n";
        $f_Debug_Data .= "POST: " . $f_POST_Data . "\n\n";
        logModuleCall('Scaleway', __FUNCTION__, $f_Debug_Data, '', $f_CURL_Reply);

        //Return an arry with HTTP_CODE returned and the JSON content writen by server.
        return array(
                        "httpCode" => $f_CURL_Reply_Code,
                        "json" => $f_CURL_Reply
                    );
    }
    
    //Server actions are: power on, power off, reboot
    private function c_Server_Action($f_Action, $f_ServerID)
    {
        if ($f_Action != "poweron" && $f_Action != "poweroff" && $f_Action != "reboot" && $f_Action != "terminate") {
            return array(
                    "httpCode" => "400",
                    "json" => "{\"error\" : \"Invalid f_Action\"}"
                );
        }


        $f_HTTP_Method = "POST";
        $f_Endpoint = "/servers/" . $f_ServerID . "/action";
        $f_POST_Data =
            [
                "action" => $f_Action
            ];
        
        return $this->c_Call_Scaleway($this->c_Token, $f_HTTP_Method, $f_Endpoint, array(), $f_POST_Data);
    }
    // ____        _     _ _
    //|  _ \ _   _| |__ | (_) ___ ___
    //| |_) | | | | '_ \| | |/ __/ __|
    //|  __/| |_| | |_) | | | (__\__ \
    //|_|    \__,_|_.__/|_|_|\___|___/
    //


    //Delete a volume by it's id
    public function delete_volume($id)
    {
        $http_method = "DELETE";
        $f_Endpoint = "/volumes/" . $id;
        $result = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), array());
        return $result;
    }

    //Function to instantiate a new server
    public function create_new_server($name, $image_id, $f_CommercialType, $tags)
    {
        $http_method = "POST";
        $f_Endpoint = "/servers";

        // GB to Bytes and substitute the rootfs Snapshot size (25GB)
        $f_ExtraVolumeSizes = ($this->c_CommercialTypes[$f_CommercialType]["Disk"] * 1000000000) - 25000000000;

        // Initilize empty Volume array
        $f_VolumesArray = array();

        // Add RootFS Snapshot volume
        array_push($f_VolumesArray, [
            "base_snapshot" => $image_id,
            "name" => $name . "-rootfs",
            "volume_type" => "l_ssd",
            "organization" => $this->c_OrgID
        ]);
        
        // Scaleway don't allow a single volume to have over 150GB, this will be an issue with START1-L
        $f_MaxAllowedSize = 150000000000;
        $f_TotalCreatedVolumeCount = 0;
        
        // Create f_AdditionalVolumeArray contain an array of volumes depend on required maximum size
        while ($f_ExtraVolumeSizes) {
            if ($f_ExtraVolumeSizes > $f_MaxAllowedSize) {
                // Force create a volume 150GB
                $f_NewVolumeSize = $f_MaxAllowedSize;
            } else {
                // Proceed to create the required volume size
                $f_NewVolumeSize = $f_ExtraVolumeSizes;
            }
        
            $f_AdditionalVolumeArray[$f_TotalCreatedVolumeCount] =
            [
                "name" => $name . "-extra-" . $f_TotalCreatedVolumeCount,
                "volume_type" => "l_ssd",
                "size" => $f_NewVolumeSize,
                "organization" => $this->c_OrgID
            ];

            // Count how many volumes has been created
            $f_TotalCreatedVolumeCount++;

            // Substitute the Volume has created
            $f_ExtraVolumeSizes = $f_ExtraVolumeSizes - $f_NewVolumeSize;
        }

        // Push each created volumes into f_VolumesArrayObj
        for ($i = 0; $i < $f_TotalCreatedVolumeCount; $i++)
        {
            array_push($f_VolumesArray, $f_AdditionalVolumeArray[$i]);
        }

        $postParams =
        [
            "organization" => $this->c_OrgID,
            "name"         => $name,
            "commercial_type" => $f_CommercialType,
            "tags"         => $tags,
            "boot_type"  => "local",
            "volumes"      =>  (object) $f_VolumesArray
        ];


        $server_creation_result = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), $postParams);
        if ($server_creation_result['httpCode'] != 210) {
            //If created more than one volumes consider removing it as server creation failed
            if (isset($vol_id)) {
                $this->delete_volume($vol_id);
            }
        }
        //Dirty code, sorry.
        $srv_id = json_decode($server_creation_result["json"], true)["server"]["id"];
        $this->c_Server_Action("poweron", $srv_id);
        return $server_creation_result;
    }




    //Function which return server info
    public function retrieve_server_info($f_ServerID)
    {
        if ($f_ServerID == "" || strpos($f_ServerID, "terminated") !== false) { //We have to prevent endpoint becaming /servers/{NULL}, it will print all servers and we don't want this!
            return array(
                "httpCode" => "400",
                "json" => "{\"error\" : \"Invalid ServerID\"}"
            );
        }
        $http_method = "GET";
        $f_Endpoint = "/servers/" . $f_ServerID;
        $result = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), array());
        return $result;
    }

    public function retrieve_snapshots()
    {
        $http_method = "GET";
        $f_Endpoint = "/snapshots";
        
        $api_result = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), array());

        logModuleCall('Scaleway', __FUNCTION__, '', '', json_encode($api_result));
        return $api_result;
    }



    //Delete an IPv4 Address
    public function delete_ip_address($ip_id)
    {
        $http_method = "DELETE";
        $f_Endpoint = "/ips/" . $ip_id;
        $result = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), array());
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
            $response = $this->c_Server_Action("terminate", $server_id);
            return $response;
        } elseif ($serverState == "stopped") { //Hard wa
            $http_method = "DELETE";
            $f_Endpoint = "/servers/" . $server_id;
            $response = $this->c_Call_Scaleway($this->c_Token, $http_method, $f_Endpoint, array(), array());
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
                            "httpCode" => "400",
                            "json" => json_encode(array("message" => "Server is into an intermediate state! Wait for power on/off then try again!"))
                         );
        }
    }
    //Function used to suspend the server
    public function server_poweroff($server_id)
    {
        //Suspension mean power off and storing volume offline
        $result = $this->c_Server_Action("poweroff", $server_id);
        return $result;
    }
    //Function to hard reboot the server
    public function server_reboot($server_id)
    {
        $result = $this->c_Server_Action("reboot", $server_id);
        return $result;
    }
    //Function to power on an server
    public function server_poweron($server_id)
    {
        $result = $this->c_Server_Action("poweron", $server_id);
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
    protected $srvLoc = ""; 
    public $server_id = "";
    //This store the API result. Usefull in case of error.
    public $queryInfo = "";
    public $image = array(
            //There are a lot more details, we keep onle those below:
            "name"        => "",
            "root_volume" => ""
        );
    public $creation_date = "";
    public $public_ip = array(
                "id"      => "",
                "address" => ""
            );
    public $private_ip = "";
    public $modification_date = "";
    public $hostname = "";
    public $state = "";
    public $commercial_type = "";
    public $tags = array();
    public $name = "";
    public $security_group = "";


    public function __construct($f_Token, $f_OrgID, $location)
    {
        $this->srvLoc = $location;
        $this->api = new ScalewayApi($f_Token, $f_OrgID, $this->srvLoc);
    }
    public function setServerId($srv_id)
    {
        $this->server_id = $srv_id;
    }
    public function setImageName($img_name)
    {
        $this->image["name"] = $img_name;
    }
    public function retrieveDetails()
    {
        $serverInfoResp = $this->api->retrieve_server_info($this->server_id);
        if ($serverInfoResp["httpCode"] == 200) {
            $serverInfoResp = json_decode($serverInfoResp["json"], true);
            $serverInfoResp = $serverInfoResp["server"];




            // Remove JIFFYHOST- from panel display which is 10 first character
            $this->hostname = substr($serverInfoResp["hostname"], 10);
            
            $this->state = $serverInfoResp["state"];
            $this->commercial_type = $serverInfoResp["commercial_type"];
            $this->tags = $serverInfoResp["tags"];
            $this->security_group = $serverInfoResp["security_group"]["name"];
            $this->organization = $serverInfoResp["organization"];

            $this->image["name"] = $serverInfoResp["image"]["name"];
            $this->image["id"] = $serverInfoResp["image"]["id"];
            $this->image["root_volume"] = $this->api->c_CommercialTypes[$this->commercial_type]["Disk"];
            $this->creation_date = $serverInfoResp["creation_date"];
            $this->public_ip["id"] = $serverInfoResp["public_ip"]["id"];
            $this->public_ip["address"] = $serverInfoResp["public_ip"]["address"];
            $this->private_ip = $serverInfoResp["private_ip"];
            $this->modification_date = $serverInfoResp["modification_date"];

            $this->queryInfo = "Success!";

            logModuleCall('Scaleway', __FUNCTION__, '', '', json_encode($serverInfoResp));


            return true;
        } else {
            $this->queryInfo = $this->api->c_HTTPStatus[$serverInfoResp["httpCode"]];
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
            $this->queryInfo =  $this->api->c_HTTPStatus[$createServerResult["httpCode"]];
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

    public function retrieve_snapshot_id($snapshot_name)
    {
        $snapshot_id = "";

        $json = json_decode($this->api->retrieve_snapshots()["json"]);
        foreach ($json->snapshots as $item) {
            if ($item->name == $snapshot_name) {
                $snapshot_id= $item->id;
                break;
            }
        }

        logModuleCall('Scaleway', __FUNCTION__, $snapshot_name, '', $snapshot_id);

        return $snapshot_id;
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
            'Type' => 'textarea',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway Server range, e.g: START1-XS',
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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $f_CommercialTypes = $params["configoption3"];

        $f_ClientID = $params["model"]["client"]["id"];
        $f_OrderID = $params["model"]["orderid"];
        $f_ServiceID = $params["serviceid"];


        $hostname = "JIFFYHOST-" . explode(".", $params["domain"])[0];
        $password = $params["password"];
        $os_name = $params["customfields"]["Operating system"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $image_id = $scwServer->retrieve_snapshot_id($os_name);
        if (strlen($image_id) < 25) {
            return "Invalid image." . $image_id;
        }
        $tags = array("ClientID: " . $f_ClientID, "OrderID: " . $f_OrderID, "ServiceID: " . $f_ServiceID);
        //Check if the current server were terminated
        if ($scwServer->retrieveDetails() == true) {
            return "Error! Please terminate current server then create another server again!";
        }
        //Now we have to create the new server and update Server ID field
        if ($scwServer->create_new_server($hostname, $image_id, $f_CommercialTypes, $tags)) {
            //If server grated, retrive his id and insert to Server ID field, so next time we know his ID.
            $command = "updateclientproduct";
            $adminuser = $params["configoption4"];
            $values["serviceid"] = $f_ServiceID;
            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["customfields"] = base64_encode(serialize(array("Server ID"=> $scwServer->server_id )));
            localAPI($command, $values, $adminuser);
        } else {

            //
            // REQUEST LOGGING
            //

            //User and service info
            $f_Log_Request .= "ClientID: " . $f_ClientID . "\n";
            $f_Log_Request .= "OrderID: " . $f_OrderID . "\n";
            $f_Log_Request .= "Service ID: " . $f_ServiceID . "\n";
            $f_Log_Request .= "Organization ID: " . $f_OrgID . "\n";
            //Config info
            $f_Log_Request .= "Commercial type: " . $f_CommercialTypes . "\n";
            $f_Log_Request .= "Location: " . $location . "\n";
            //And finally server info
            $f_Log_Request .= "Hostname: " . $hostname . "\n";
            $f_Log_Request .= "Password: " . $password . "\n";
            $f_Log_Request .= "OS Name: " . $os_name . "\n";
            //Response
            $response = $scwServer->queryInfo;
            //Send error to Utilities >> Log >> Module Log
            logModuleCall('Scaleway', __FUNCTION__, $f_Log_Request, "blabla", $response);

            //
            // END REQUEST LOGGING
            //

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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
function Scaleway_UnsuspendAccount(array $params)
{
    try {
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
function Scaleway_RebootServer(array $params)
{
    try {
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
function Scaleway_TerminateAccount(array $params)
{
    try {
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
        $scwServer->setServerId($server_id);
        $scwServer->setImageName($params["customfields"]["Operating system"]);
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
        $values["username"] = "root";
        localAPI($command, $values, $adminuser);
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
function Scaleway_AdminServicesTabFields(array $params)
{
    try {
        $server_id = $params["customfields"]["Server ID"];
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
            'Disk' => $scwServer->image["root_volume"] . "GB",
            'Image' => $scwServer->image["name"],
            'Creation date' =>$scwServer->creation_date,
            'Modification date' => $scwServer->modification_date,
            'Public IP v4' => $scwServer->public_ip["address"] . " [" . $scwServer->public_ip["id"] . "]",
            'Private IP v4' => $scwServer->private_ip ,
            'Location' => $location,
            'Commercial type' => $scwServer->commercial_type,
            'Tags' => implode(",", $scwServer->tags),
            'Security group' => $scwServer->security_group,
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
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
        "Reboot server" => "ClientRebootServer",
        "Power OFF" => "ClientPowerOffServer",
        "Power ON" => "ClientPowerOnServer"
    );
}
function Scaleway_ClientRebootServer(array $params)
{
    try {
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
            'Scaleway',
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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
            'Scaleway',
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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
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
            'Scaleway',
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
        $f_Token = $params["configoption1"];
        $f_OrgID = $params["configoption2"];
        $location = $params["customfields"]["Location"];
        $scwServer = new ScalewayServer($f_Token, $f_OrgID, $location);
        $scwServer->setServerId($server_id);
        $scwServer->retrieveDetails();

        return array(
            'templateVariables' => array(
                'sid' =>$scwServer->server_id,
                'sname' => $scwServer->hostname,
                'sstate' => $scwServer->state,
                'rootvolume' => $scwServer->image["root_volume"] . "GB",
                'image' => $scwServer->image["name"],
                'creationdate' =>$scwServer->creation_date,
                'publicipv4' => $scwServer->public_ip["address"],
                'privateipv4' => $scwServer->private_ip ,
                'modificationdate' => $scwServer->modification_date,
                'location' => $location,
                'sec_group' => $scwServer->security_group,
            ));
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
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
