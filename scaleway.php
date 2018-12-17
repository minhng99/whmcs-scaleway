<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

//function logModuleCall($a, $b, $c, $d, $e)
//{
//};


//   █████╗ ██████╗ ██╗     ██████╗ █████╗ ██╗     ██╗     ███████╗
//  ██╔══██╗██╔══██╗██║    ██╔════╝██╔══██╗██║     ██║     ██╔════╝
//  ███████║██████╔╝██║    ██║     ███████║██║     ██║     ███████╗
//  ██╔══██║██╔═══╝ ██║    ██║     ██╔══██║██║     ██║     ╚════██║
//  ██║  ██║██║     ██║    ╚██████╗██║  ██║███████╗███████╗███████║
//  ╚═╝  ╚═╝╚═╝     ╚═╝     ╚═════╝╚═╝  ╚═╝╚══════╝╚══════╝╚══════╝

class ScalewayAPI
{
    private $Token = "";
    private $OrgID = "";
    private $APIURL = "";

    // Status codes returned by scaleway
    public $HTTPStatus =
        [
            "200" => "Backend - 200: OK.",
            "201" => "Backend - 201: Created.",
            "204" => "Backend - 204: No Content.",
            "400" => "Backend - 400: Bad Request - Missing or invalid parameter.",
            "401" => "Backend - 401: Auth Error - Invalid Token/Organization_ID.",
            "402" => "Backend - 402: Request Failed - Parameters were valid but request failed.",
            "403" => "Backend - 403: Forbidden - Access to resource is prohibited.",
            "404" => "Backend - 404: Not found - API Failed or object does not exist.",
            "50x" => "Backend - 50x: Backend on fire."
        ];
        

    public $CommercialTypes =
        [
            "START1-XS" => 
                [
                    "Disk" => 25,
                    "Core" => 1,
                    "RAM" => 1,
                ],
            "START1-S" => 
                [
                    "Disk" => 50,
                    "Core" => 2,
                    "RAM" => 2,
                ],
            "START1-M" => 
                [
                    "Disk" => 100,
                    "Core" => 4,
                    "RAM" => 4,
                ],
            "START1-L" => 
                [
                    "Disk" => 200,
                    "Core" => 8,
                    "RAM" => 8,
                ],
        ];


    //  ██████╗ ██████╗ ██╗██╗   ██╗ █████╗ ████████╗███████╗███████╗
    //  ██╔══██╗██╔══██╗██║██║   ██║██╔══██╗╚══██╔══╝██╔════╝██╔════╝
    //  ██████╔╝██████╔╝██║██║   ██║███████║   ██║   █████╗  ███████╗
    //  ██╔═══╝ ██╔══██╗██║╚██╗ ██╔╝██╔══██║   ██║   ██╔══╝  ╚════██║
    //  ██║     ██║  ██║██║ ╚████╔╝ ██║  ██║   ██║   ███████╗███████║
    //  ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝  ╚═╝  ╚═╝   ╚═╝   ╚══════╝╚══════╝

    //This is function used to call Scaleway API
    private function Call_Scaleway($Token, $HTTP_Method, $Endpoint, $POST_Data = "", $POST_Content_Type = "")
    {
        if($POST_Content_Type != "")
        {
            $POST_Content_Type = "Content-Type: " . $POST_Content_Type;

        }


        $CURL = curl_init();
        curl_setopt($CURL, CURLOPT_URL, $this->APIURL . $Endpoint);
        curl_setopt($CURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        curl_setopt($CURL, CURLOPT_POST, true);
        curl_setopt($CURL, CURLOPT_CUSTOMREQUEST, $HTTP_Method);
        curl_setopt($CURL, CURLOPT_POSTFIELDS, $POST_Data);
        

        $CURL_Header =
            [
                "X-Auth-Token: " . $Token,
                $POST_Content_Type
            ];
        curl_setopt($CURL, CURLOPT_HTTPHEADER, $CURL_Header);
        curl_setopt($CURL, CURLOPT_RETURNTRANSFER, true);


        $CURL_Reply = curl_exec($CURL);
        $CURL_Reply_Code = curl_getinfo($CURL, CURLINFO_HTTP_CODE);

        curl_close($CURL);

        if ($CURL_Reply_Code == "") {
            $CURL_Reply = json_encode(array("message" => "CURL to Backend FAILED!"), JSON_PRETTY_PRINT);
        }

        $Return = array(
            "STATUS" => $CURL_Reply_Code,
            "json" => $CURL_Reply
        );

        // Logging debug data
        $Debug_Data .= "HEADER: " . json_encode($CURL_Header, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
        $Debug_Data .= "URL: " . $this->APIURL . $Endpoint . PHP_EOL . PHP_EOL;
        $Debug_Data .= "METHOD: " . $HTTP_Method . PHP_EOL . PHP_EOL;
        $Debug_Data .= "METHOD DATA: " . $POST_Data . PHP_EOL . PHP_EOL;
        $Debug_Data .= "REPLY: " . json_encode(json_decode($CURL_Reply), JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

        logModuleCall('Scaleway', __FUNCTION__, $Debug_Data, print_r($Return, true));

        //Return an arry with HTTP_CODE returned and the JSON content writen by server.
        return $Return;
    }
    


    //  ██████╗ ██╗   ██╗██████╗ ██╗     ██╗ ██████╗███████╗
    //  ██╔══██╗██║   ██║██╔══██╗██║     ██║██╔════╝██╔════╝
    //  ██████╔╝██║   ██║██████╔╝██║     ██║██║     ███████╗
    //  ██╔═══╝ ██║   ██║██╔══██╗██║     ██║██║     ╚════██║
    //  ██║     ╚██████╔╝██████╔╝███████╗██║╚██████╗███████║
    //  ╚═╝      ╚═════╝ ╚═════╝ ╚══════╝╚═╝ ╚═════╝╚══════╝


    public function __construct($Token, $OrgID, $Location)
    {
        $this->Token = $Token;
        $this->OrgID = $OrgID;

        $Locations =
            [
                "Paris"     => "par1",
                "Amsterdam" => "ams1",
            ];

        $this->APIURL = "https://cp-" . $Locations[$Location] . ".scaleway.com";
    }


    // Modify Cloud-init data
    public function Modify_Cloudinit($Data, $ServerID)
    {
        $http_method = "PATCH";
        $Endpoint = "/servers/" . $ServerID . "/user_data/cloud-init";
        $Return = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, $Data, "text/plain");

        logModuleCall('Scaleway', __FUNCTION__, 'ServerID:' . PHP_EOL . $ServerID . PHP_EOL . PHP_EOL . "Data:" . PHP_EOL . print_r($Data, true) , print_r($Return, true));
        return $Return;
    }

    public function Modify_Hostname( $ServerID, $New_Hostname)
    {
        $http_method = "PATCH";
        $Endpoint = "/servers/" . $ServerID ;
        $Data = array("name" => $New_Hostname);
        $Return = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, json_encode($Data, JSON_PRETTY_PRINT), "application/json");

        logModuleCall('Scaleway', __FUNCTION__, 'ServerID:' . PHP_EOL . $ServerID . PHP_EOL . PHP_EOL . "Data:" . PHP_EOL . print_r($Data, true) , print_r($Return, true));
        return $Return;
    }


    public function modify_ip_ptr($IP_ID, $PTR = "")
    {

        $Endpoint = "/ips/" . $IP_ID;

        // Detach IP
        $Data = array(
            "reverse" => empty($PTR) ? "customer.jiffy.host" : $PTR
        );

        $http_method = "PATCH";
        $Return = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, json_encode($Data, JSON_PRETTY_PRINT), "application/json");

        return $Return;
    }

    public function create_reserved_ip()
    {
        $http_method = "POST";
        $Endpoint = "/ips" ;
        $Data = json_encode(array("organization" => $this->OrgID), JSON_PRETTY_PRINT);
        $Return = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, $Data, "application/json");
        return $Return;
    }

    public function delete_ip_address($ip_id)
    {
        $Endpoint = "/ips/" . $ip_id;

        // Detach IP
        $Data = array(
            "server" => NULL
        );

        $http_method = "PATCH";
        $result = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, json_encode($Data, JSON_PRETTY_PRINT), "application/json");

        // Failed to detatch, IDK
        if($result["STATUS"] != 200)
        {
            return $result;
        }

        // Delete IP
        $http_method = "DELETE";
        $result = $this->Call_Scaleway($this->Token, $http_method, $Endpoint);
        return $result;
    }

    //Delete a volume by it's id
    public function delete_volume($VolumeID)
    {
        $Endpoint = "/volumes/" . $VolumeID;
        $Result = $this->Call_Scaleway($this->Token, "DELETE", $Endpoint);
        return $Result;
    }

    //Function to instantiate a new server
    public function create_new_server($ServerName, $OS_ImageID, $CommercialType, $tags, $ServerPassword)
    {
        $http_method = "POST";
        $Endpoint = "/servers";

        // GB to Bytes and substitute the rootfs Snapshot size (25GB)
        $ExtraVolumeSizes = ($this->CommercialTypes[$CommercialType]["Disk"] * 1000000000) - 25000000000;

        // Initilize empty Volume array
        $VolumesArray = array();

        // Add RootFS Snapshot volume
        array_push($VolumesArray, [
            "base_snapshot" => $OS_ImageID,
            "name" => $ServerName . "-rootfs",
            "volume_type" => "l_ssd",
            "organization" => $this->OrgID
        ]);
        
        // Scaleway don't allow a single volume to have over 150GB, this will be an issue with START1-L
        $MaxAllowedSize = 150000000000;
        $TotalCreatedVolumeCount = 0;
        
        // Create AdditionalVolumeArray contain an array of volumes depend on required maximum size
        while ($ExtraVolumeSizes) {
            if ($ExtraVolumeSizes > $MaxAllowedSize) {
                // Force create a volume 150GB
                $NewVolumeSize = $MaxAllowedSize;
            } else {
                // Proceed to create the required volume size
                $NewVolumeSize = $ExtraVolumeSizes;
            }
        
            $AdditionalVolumeArray[$TotalCreatedVolumeCount] =
            [
                "name" => $ServerName . "-extra-" . $TotalCreatedVolumeCount,
                "volume_type" => "l_ssd",
                "size" => $NewVolumeSize,
                "organization" => $this->OrgID
            ];

            // Count how many volumes has been created
            $TotalCreatedVolumeCount++;

            // Substitute the Volume has created
            $ExtraVolumeSizes = $ExtraVolumeSizes - $NewVolumeSize;
        }

        // Push each created volumes into VolumesArrayObj
        for ($i = 0; $i < $TotalCreatedVolumeCount; $i++)
        {
            array_push($VolumesArray, $AdditionalVolumeArray[$i]);
        }

        $NewIP_ID = json_decode($this->create_reserved_ip()["json"], true)["ip"]["id"];
        $this->modify_ip_ptr($NewIP_ID);

        $POST_Data =
        [
            "organization" => $this->OrgID,
            "name"         => $ServerName,
            "commercial_type" => $CommercialType,
            "tags"         => $tags,
            "boot_type"  => "local",
            "public_ip" => $NewIP_ID,
            "enable_ipv6" => true,
            "volumes"      =>  (object) $VolumesArray
        ];


        $server_creation_result = $this->Call_Scaleway($this->Token, $http_method, $Endpoint, json_encode($POST_Data, JSON_PRETTY_PRINT), "application/json");

        $srv_id = json_decode($server_creation_result["json"], true)["server"]["id"];


        // Set User:Pass to Cloudinit data
        $this->Modify_Cloudinit($ServerPassword, $srv_id);
        
        // Power ON server
        $this->server_action($srv_id, "poweron" );


        return $server_creation_result;
    }

    /**
     * Create a new Scaleway Volume from Snapshot UUID
     * 
     * @param   string  $Snapshot_UUID      UUID of Snapshot to create Volume from
     * @param   string  $New_Volume_Name    Name of the newly created Volume
     * @return  array   ["STATUS"]          Integer of Replied HTTP code
     * @return  array   ["json"]            JSON data from backend
     */
    public function Volume_from_Snapshot($Snapshot_UUID, $New_Volume_Name)
    {
        $Return = array(
            "STATUS" => -1,
            "json" => ""
        )

        if(!isValidUUID($Snapshot_UUID) || !is_string($New_Volume_Name) || empty($New_Volume_Name))
        {

        }

        // Return Error when ServerID is invalid.
        if ($ServerID == "" || strpos($ServerID, "TERMINATED") !== false) {
            return array(
                "STATUS" => "400",
                "json" => "{\"error\" : \"Invalid ServerID\"}"
            );
        }
        $http_method = "GET";
        $Endpoint = "/servers/" . $ServerID;
        $result = $this->Call_Scaleway($this->Token, $http_method, $Endpoint);
        return $result;
    }

    //Function which return server info
    public function retrieve_server_info($ServerID)
    {
        // Return Error when ServerID is invalid.
        if ($ServerID == "" || strpos($ServerID, "TERMINATED") !== false) {
            return array(
                "STATUS" => "400",
                "json" => "{\"error\" : \"Invalid ServerID\"}"
            );
        }
        $http_method = "GET";
        $Endpoint = "/servers/" . $ServerID;
        $result = $this->Call_Scaleway($this->Token, $http_method, $Endpoint);
        return $result;
    }

    public function retrieve_snapshots()
    {
        $http_method = "GET";
        $Endpoint = "/snapshots";
        
        $Return = $this->Call_Scaleway($this->Token, $http_method, $Endpoint);

        logModuleCall('Scaleway', __FUNCTION__, '[NO INPUT]', print_r($Return, true));
        return $Return;
    }



    //Server actions are: power on, stop_in_place, reboot
    public function server_action( $ServerID, $Action)
    {
        if ($Action != "poweron" && $Action != "stop_in_place" && $Action != "reboot" && $Action != "terminate" && $Action !="poweroff") {
            return array(
                    "STATUS" => "400",
                    "json" => "{\"error\" : \"Invalid Action\"}"
                );
        }


        $HTTP_Method = "POST";
        $Endpoint = "/servers/" . $ServerID . "/action";
        $POST_Data =
            [
                "action" => $Action
            ];
        
        return $this->Call_Scaleway($this->Token, $HTTP_Method, $Endpoint, json_encode($POST_Data, JSON_PRETTY_PRINT), "application/json" );
    }
}






//  ███████╗███████╗██████╗ ██╗   ██╗███████╗██████╗     ███╗   ███╗ █████╗ ███╗   ██╗ █████╗  ██████╗ ███████╗███╗   ███╗███████╗███╗   ██╗████████╗
//  ██╔════╝██╔════╝██╔══██╗██║   ██║██╔════╝██╔══██╗    ████╗ ████║██╔══██╗████╗  ██║██╔══██╗██╔════╝ ██╔════╝████╗ ████║██╔════╝████╗  ██║╚══██╔══╝
//  ███████╗█████╗  ██████╔╝██║   ██║█████╗  ██████╔╝    ██╔████╔██║███████║██╔██╗ ██║███████║██║  ███╗█████╗  ██╔████╔██║█████╗  ██╔██╗ ██║   ██║
//  ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██╔══╝  ██╔══██╗    ██║╚██╔╝██║██╔══██║██║╚██╗██║██╔══██║██║   ██║██╔══╝  ██║╚██╔╝██║██╔══╝  ██║╚██╗██║   ██║
//  ███████║███████╗██║  ██║ ╚████╔╝ ███████╗██║  ██║    ██║ ╚═╝ ██║██║  ██║██║ ╚████║██║  ██║╚██████╔╝███████╗██║ ╚═╝ ██║███████╗██║ ╚████║   ██║
//  ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚══════╝╚═╝  ╚═╝    ╚═╝     ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝     ╚═╝╚══════╝╚═╝  ╚═══╝   ╚═╝


// This class is designed to work with servers as it is more easy to use than the API class.
// If an action is not implemented in this class then you'll have to use the main class and implement by yourself.
// It's a kind of wrapper for the main ScalewayAPI class which return JSON.
// It will make your life easier as it already check the response for errors and returns the right message.
class ScalewayServer
{
    protected $ScwAPI = "";

    public $ServerPrefix =  "CUSTOMER-";

    public $ServerID = "";
    public $hostname = "";
    public $queryInfo = "";



    public $Info_Disk = 0;
    public $Info_Core = 0;
    public $Info_RAM = 0;


    public $creation_date = "";
    public $modification_date = "";
    public $public_ip = array(
                "id"      => "",
                "address" => ""
            );
    public $ipv6 = "";
    public $private_ip = "";
    public $state = "";
    public $commercial_type = "";
    public $tags = array();
    public $security_group = "";


    public function __construct($Token, $OrgID, $Location, $Server_ID = "")
    {
        $this->ScwAPI = new ScalewayAPI($Token, $OrgID, $Location);
        $this->ServerID = $Server_ID;
    }

    public function retrieveDetails()
    {
        $serverInfoResp = $this->ScwAPI->retrieve_server_info($this->ServerID);
        if ($serverInfoResp["STATUS"] == 200) {
            $serverInfoResp = json_decode($serverInfoResp["json"], true);
            $serverInfoResp = $serverInfoResp["server"];

            // Remove ServerPrefix from name
            $this->hostname = substr($serverInfoResp["hostname"], strlen($this->ServerPrefix));

            if ($serverInfoResp["state"] == "stopped in place") {
                $this->state = '<span class="label label-danger">NOT RUNNING</span>';
            } elseif ($serverInfoResp["state"] == "stopping") {
                $this->state = '<span class="label label-warning">SHUTTING DOWN</span>';
            } elseif ($serverInfoResp["state"] == "stopped") {
                $this->state = '<span class="label label-default">ARCHIVED</span>';
            } elseif ($serverInfoResp["state"] == "running") {
                $this->state = '<span class="label label-success">RUNNING</span>';
            } elseif ($serverInfoResp["state"] == "starting") {
                $this->state = '<span class="label label-primary">STARTING</span>';
            } else {
                $this->state = $serverInfoResp["state"];
            }
            
            $this->commercial_type = $serverInfoResp["commercial_type"];
            $this->tags = $serverInfoResp["tags"];
            $this->security_group = $serverInfoResp["security_group"]["name"];
            $this->organization = $serverInfoResp["organization"];

            $this->Info_Disk = $this->ScwAPI->CommercialTypes[$this->commercial_type]["Disk"];
            $this->Info_Core = $this->ScwAPI->CommercialTypes[$this->commercial_type]["Core"];
            $this->Info_RAM = $this->ScwAPI->CommercialTypes[$this->commercial_type]["RAM"];


            $this->public_ip["id"] = $serverInfoResp["public_ip"]["id"];
            $this->public_ip["address"] = $serverInfoResp["public_ip"]["address"];
            $this->ipv6 = $serverInfoResp["ipv6"]["address"];
            $this->private_ip = $serverInfoResp["private_ip"];

            $this->creation_date = prettyPrintDate($serverInfoResp["creation_date"]);
            $this->modification_date = prettyPrintDate($serverInfoResp["modification_date"]);

            $this->queryInfo = "Success!";



            $Return = true;
        } else {
            $this->queryInfo = $this->ScwAPI->HTTPStatus[$serverInfoResp["STATUS"]];
            $Return = false;
        }

        logModuleCall('Scaleway', __FUNCTION__, '[NO INPUT]' . PHP_EOL . PHP_EOL . "serverInfoResp:" . print_r($serverInfoResp, true), $Return);

        return $Return;
    }
    public function create_new_server($Server_Name, $OS_ImageID, $commercial_type, $tags = array(), $ServerPassword)
    {
        $createServerResult = $this->ScwAPI->create_new_server( $this->ServerPrefix . $Server_Name, $OS_ImageID, $commercial_type, $tags, $ServerPassword);
        if ($createServerResult["STATUS"] == 201) {
            $serverInfo = json_decode($createServerResult["json"], true);
            $serverInfo = $serverInfo["server"];
            $this->ServerID = $serverInfo["id"];
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo =  $this->ScwAPI->HTTPStatus[$createServerResult["STATUS"]];
            return false;
        }
    }
    public function delete_server()
    {
        // Getting the infos before deletion
        $serverInfoResp = $this->ScwAPI->retrieve_server_info($this->ServerID);
        $AttachedIP_ID = json_decode($serverInfoResp["json"], true)["server"]["public_ip"]["id"];

        // Delete its reserved IP
        $this->ScwAPI->delete_ip_address($AttachedIP_ID);

        $deleteServerResponse = $this->ScwAPI->server_action($this->ServerID, "terminate");

        if ($deleteServerResponse["STATUS"] == 202) {
            return true;

        } else {
            $this->queryInfo = json_decode($deleteServerResponse["json"], true)["message"];
            return false;
        }
    }

    public function stop_server()
    {
        $powerofresult = $this->ScwAPI->server_action($this->ServerID, "stop_in_place");
        if ($powerofresult["STATUS"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($powerofresult["json"], true)["message"];
            return false;
        }
    }
    public function poweron_server()
    {
        $poweron_result = $this->ScwAPI->server_action($this->ServerID, "poweron");
        if ($poweron_result["STATUS"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($poweron_result["json"], true)["message"];
            return false;
        }
    }
    public function reboot_server()
    {
        $reboot_result = $this->ScwAPI->server_action($this->ServerID, "reboot");
        if ($reboot_result["STATUS"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($reboot_result["json"], true)["message"];
            return false;
        }
    }

    public function archive_server()
    {
        $archive_result = $this->ScwAPI->server_action($this->ServerID, "poweroff");
        if ($archive_result["STATUS"] == 202) {
            $this->retrieveDetails();
            return true;
        } else {
            $this->queryInfo = json_decode($archive_result["json"], true)["message"];
            return false;
        }
    }

    public function retrieve_snapshot_id($snapshot_name)
    {
        $snapshot_id = "";

        $json = json_decode($this->ScwAPI->retrieve_snapshots()["json"]);
        foreach ($json->snapshots as $item) {
            if ($item->name == $snapshot_name) {
                $snapshot_id= $item->id;
                break;
            }
        }

        logModuleCall('Scaleway', __FUNCTION__, $snapshot_name, $snapshot_id);

        return $snapshot_id;
    }

    public function Modify_Hostname($New_Hostname)
    {
        return $this->ScwAPI->Modify_Hostname($this->ServerID, $this->$ServerPrefix . $New_Hostname);
    }

}

//  ██╗    ██╗██╗  ██╗███╗   ███╗ ██████╗███████╗    ███████╗██╗   ██╗███╗   ██╗ ██████╗████████╗██╗ ██████╗ ███╗   ██╗███████╗
//  ██║    ██║██║  ██║████╗ ████║██╔════╝██╔════╝    ██╔════╝██║   ██║████╗  ██║██╔════╝╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
//  ██║ █╗ ██║███████║██╔████╔██║██║     ███████╗    █████╗  ██║   ██║██╔██╗ ██║██║        ██║   ██║██║   ██║██╔██╗ ██║███████╗
//  ██║███╗██║██╔══██║██║╚██╔╝██║██║     ╚════██║    ██╔══╝  ██║   ██║██║╚██╗██║██║        ██║   ██║██║   ██║██║╚██╗██║╚════██║
//  ╚███╔███╔╝██║  ██║██║ ╚═╝ ██║╚██████╗███████║    ██║     ╚██████╔╝██║ ╚████║╚██████╗   ██║   ██║╚██████╔╝██║ ╚████║███████║
//   ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝     ╚═╝ ╚═════╝╚══════╝    ╚═╝      ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝
function Scaleway_MetaData()
{
    return array(
        'DisplayName' => 'Scaleway',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
    );
}

//   █████╗ ██████╗ ███╗   ███╗██╗███╗   ██╗
//  ██╔══██╗██╔══██╗████╗ ████║██║████╗  ██║
//  ███████║██║  ██║██╔████╔██║██║██╔██╗ ██║
//  ██╔══██║██║  ██║██║╚██╔╝██║██║██║╚██╗██║
//  ██║  ██║██████╔╝██║ ╚═╝ ██║██║██║ ╚████║
//  ╚═╝  ╚═╝╚═════╝ ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝

function Scaleway_ConfigOptions()
{
    return array(
        'Token' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway secret token - used to access your account',
        ),
        'Organization ID' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway Organization ID - used to access your account',
        ),
        'Commercial type' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway Server range, e.g: START1-XS',
        ),
    );
}
function Scaleway_CreateAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Location = $params["customfields"]["Location"];

    $CommercialType = $params["configoption3"];
    $ServerName = $params["domain"];

    $ClientID = $params["model"]["client"]["id"];
    $OrderID = $params["model"]["orderid"];
    $ServiceID = $params["serviceid"];
    
    $OS_Name = $params["customfields"]["Operating System"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "CommercialType: " . $CommercialType . PHP_EOL . 
                  "ServerName: " . $ServerName . PHP_EOL .
                  "ClientID: " . $ClientID . PHP_EOL . 
                  "OrderID: " . $OrderID . PHP_EOL . 
                  "ServiceID: " . $ServiceID . PHP_EOL . 
                  "OS_Name: " . $OS_Name . PHP_EOL .  PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && $Location != "" && $CommercialType != "" && $ServerName != "" &&
        $ClientID != "" && $OrderID != "" && $ServiceID != "" && $OS_Name != "") {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location);

        $OSImageID = $ScalewayServer->retrieve_snapshot_id($OS_Name);
        if (strlen($OSImageID) != 36) {
            $Return =  "Invalid image: " . $OS_Name;
            goto Abort;
        }

        $ServerTag = array("ClientID: " . $ClientID, "OrderID: " . $OrderID, "ServiceID: " . $ServiceID);

        $ServerUsername = "root";
        $ServerPassword = md5($params["password"]);

        if ($ScalewayServer->create_new_server($ServerName, $OSImageID, $CommercialType, $ServerTag, $ServerUsername . ":" . $ServerPassword)) {

            $LocalAPI_Data["serviceid"] = $ServiceID;
            $LocalAPI_Data["serviceusername"] = $ServerUsername;
            $LocalAPI_Data["servicepassword"] = $ServerPassword;
            $LocalAPI_Data["customfields"] = base64_encode(serialize(array(
                                                                    "Server ID"=> $ScalewayServer->ServerID,
                                                                    "Operating System" => $OS_Name
                                                                    )));

            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}


function Scaleway_SuspendAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if ($ScalewayServer->archive_server()) {
            $LocalAPI_Data["serviceid"] = $params["serviceid"];
            $LocalAPI_Data["status"] = "Suspended";
            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_UnsuspendAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if ($ScalewayServer->poweron_server()) {
            $LocalAPI_Data["serviceid"] = $params["serviceid"];
            $LocalAPI_Data["status"] = "Active";
            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_TerminateAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);
        if (!$ScalewayServer->retrieveDetails()) {
            $Return = array("ERROR: " . $ScalewayServer->queryInfo  => "");
            goto Abort;
        }

        if ($ScalewayServer->delete_server()) {

            $LocalAPI_Data["serviceid"] = $params["serviceid"];
            $LocalAPI_Data["status"] = "Terminated";
            $LocalAPI_Data["customfields"] = base64_encode(serialize(array("Server ID"=> "TERMINATED-" . $ServerID )));
            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_AdminCustomButtonArray(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);
        if (!$ScalewayServer->retrieveDetails()) {
            $Return = array("ERROR: " . $ScalewayServer->queryInfo  => "");
            goto Abort;
        }

        $Return = array();

        if (strpos($ScalewayServer->state, 'NOT RUNNING') !== false ||
             strpos($ScalewayServer->state, 'ARCHIVED') !== false) {
            $Return = array_merge($Return, array("Start Server" => "StartServer"));

        } elseif (strpos($ScalewayServer->state, 'RUNNING') !== false) {
            $Return = array_merge($Return, array("Reboot Server" => "RebootServer",
                                                "Stop Server" => "StopServer"));
        }

    } else {
        $Return = array("INVALID FUNCTION REQUEST" => "");
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_AdminServicesTabFields(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);
        if (!$ScalewayServer->retrieveDetails()) {
            $Return = array("Failed Reason: " => $ScalewayServer->queryInfo);
            goto Abort;
        }

        // Updating fields with data returned from backend.
        $LocalAPI_Data["serviceid"] = $params["serviceid"];
        $LocalAPI_Data["dedicatedip"] = $ScalewayServer->public_ip["address"];
        $LocalAPI_Data["domain"] = $ScalewayServer->hostname;
        localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

        // Return an array based on the function's response.
        $Return = array(
            'Server name' => $ScalewayServer->hostname,
            'Server state' => $ScalewayServer->state,
            'Disk' => $ScalewayServer->Info_Disk . "GB",
            'Core' => $ScalewayServer->Info_Core,
            'RAM' => $ScalewayServer->Info_RAM . "GB",
            'Image' => $params["customfields"]["Operating System"],
            'Creation date' =>$ScalewayServer->creation_date,
            'Modification date' => $ScalewayServer->modification_date,
            'Public IPv4' => $ScalewayServer->public_ip["address"] . " [" . $ScalewayServer->public_ip["id"] . "]",
            'Private IPv4' => $ScalewayServer->private_ip,
            'IPv6' => $ScalewayServer->ipv6,
            'Location' => $Location,
            'Commercial type' => $ScalewayServer->commercial_type,
            'Tags' => implode(", ", $ScalewayServer->tags),
            'Security group' => $ScalewayServer->security_group,
        );

    } else {
        $Return = array("Failed Reason: " => "INVALID FUNCTION REQUEST");
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}


//  ███████╗███████╗██████╗ ██╗   ██╗███████╗██████╗      █████╗  ██████╗████████╗██╗ ██████╗ ███╗   ██╗
//  ██╔════╝██╔════╝██╔══██╗██║   ██║██╔════╝██╔══██╗    ██╔══██╗██╔════╝╚══██╔══╝██║██╔═══██╗████╗  ██║
//  ███████╗█████╗  ██████╔╝██║   ██║█████╗  ██████╔╝    ███████║██║        ██║   ██║██║   ██║██╔██╗ ██║
//  ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██╔══╝  ██╔══██╗    ██╔══██║██║        ██║   ██║██║   ██║██║╚██╗██║
//  ███████║███████╗██║  ██║ ╚████╔╝ ███████╗██║  ██║    ██║  ██║╚██████╗   ██║   ██║╚██████╔╝██║ ╚████║
//  ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚══════╝╚═╝  ╚═╝    ╚═╝  ╚═╝ ╚═════╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝


function Scaleway_RebootServer(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if ($ScalewayServer->reboot_server()) {
            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_StopServer(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if ($ScalewayServer->stop_server()) {
            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_StartServer(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if ($ScalewayServer->poweron_server()) {
            $Return = "success";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }

    } else {
        $Return = "INVALID FUNCTION REQUEST";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}


//   ██████╗██╗     ██╗███████╗███╗   ██╗████████╗
//  ██╔════╝██║     ██║██╔════╝████╗  ██║╚══██╔══╝
//  ██║     ██║     ██║█████╗  ██╔██╗ ██║   ██║
//  ██║     ██║     ██║██╔══╝  ██║╚██╗██║   ██║
//  ╚██████╗███████╗██║███████╗██║ ╚████║   ██║
//   ╚═════╝╚══════╝╚═╝╚══════╝╚═╝  ╚═══╝   ╚═╝

function Scaleway_ClientArea(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];
    $ServiceID = $params["serviceid"];


    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL . 
                  "ServerID: " . $ServerID . PHP_EOL . 
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if (!$ScalewayServer->retrieveDetails()) {
            $Return = array('tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                                        'usefulErrorHelper' => "ERROR: " . $ScalewayServer->queryInfo,
                                        )
            );
            goto Abort;
        }

        if($_SERVER['REQUEST_METHOD'] == "POST" &&
          strpos($ScalewayServer->state, 'ARCHIVED') == false) // Prevent user "wake" the suspended server
        {
            $ClientAction=customAction($params);
        }

        // Get the status after action
        $ScalewayServer->retrieveDetails();

        if (strpos($ScalewayServer->state, 'NOT RUNNING') !== false) {
            $Is_Running = 0;
        } elseif (strpos($ScalewayServer->state, 'RUNNING') !== false) {
            $Is_Running = 1;
        } else {
            $Is_Running = -1;
        }

        $Available_OS = "";
        foreach (getOSList($params["pid"]) as $OS_Item) {
            $Available_OS .= "<option>" . $OS_Item . "</option>" . PHP_EOL; 
        }

        $Return = array('templateVariables' => array(
                                                    'Action_Result' => $ClientAction,


                                                    'Service_ID' => $ServiceID,
                                                    'Server_UUID' =>$ScalewayServer->ServerID,
                                                    'Hostname' => $ScalewayServer->hostname,
                                                    'Server_State' => $ScalewayServer->state,
                                                    'IPv4_Public' => $ScalewayServer->public_ip["address"],
                                                    'IPv4_Private' => $ScalewayServer->private_ip,
                                                    'IPv6' => $ScalewayServer->ipv6,
                                                    'Username' => $params["username"],
                                                    'Password' => $params["password"],
                                                    'CPU_Core' => $ScalewayServer->Info_Core,
                                                    'RAM' => $ScalewayServer->Info_RAM . "GB",
                                                    'Disk' => $ScalewayServer->Info_Disk . "GB",
                                                    'OS' => $params["customfields"]["Operating System"],
                                                    'Creation_Date' =>$ScalewayServer->creation_date,
                                                    'Modification_Date' => $ScalewayServer->modification_date,
                                                    'Location' => $Location,
                                                    'Security_Group' => $ScalewayServer->security_group,

                                                    'Available_OS' => $Available_OS,


                                                    'Is_Running' => $Is_Running,
                                                    )
                            );

    } else {
        $Return = array('tabOverviewReplacementTemplate' => 'error.tpl',
                        'templateVariables' => array(
                                                    'usefulErrorHelper' => "INVALID FUNCTION REQUEST",
                                                    )
                        );
        
        goto Abort;
    }



    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}



//  ███╗   ███╗██╗███████╗ ██████╗
//  ████╗ ████║██║██╔════╝██╔════╝
//  ██╔████╔██║██║███████╗██║     
//  ██║╚██╔╝██║██║╚════██║██║     
//  ██║ ╚═╝ ██║██║███████║╚██████╗
//  ╚═╝     ╚═╝╚═╝╚══════╝ ╚═════╝

/**
 * Check if a given string is a valid UUID
 * 
 * @param   string  $uuid   The string to check
 * @return  boolean
 */
function isValidUUID ($UUID) {
    if (!is_string($UUID) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $UUID) !== 1)) {
        return false;
    }
    return true;
}


function getOSList($PID)
{

    $Query_Result = Capsule::table('tblcustomfields')
    ->where('tblcustomfields.fieldname',"Operating System")
    ->where('tblcustomfields.relid', $PID)
    ->select('fieldoptions')
    ->get();


    $RequestLog = "PID: " . $PID . PHP_EOL . PHP_EOL;
    $RequestLog .= "Query_Result: " . PHP_EOL . print_r($Query_Result, true);

    if (!empty($Query_Result)) {
        $Return = explode(",", $Query_Result[0]->fieldoptions);
    } else {
        $Return[0] = "Unable to get OS List";
    }

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function customAction(array $params)
{

    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);


    $Return = "ERROR: No valid action.";

    if(!empty($_POST["Power"]))
    {
        switch ($_POST['Power']) {
            case "Reboot":
                $Action = Scaleway_RebootServer($params);
                if ($Action == "success"){
                    $Return = "SUCCESS: Server is rebooting...";
                } else {
                    $Return = $Action;
                }
                break;
            case "Stop":
                $Action = Scaleway_StopServer($params);
                if ($Action == "success"){
                    $Return = "SUCCESS: Server is stopping...";
                } else {
                    $Return = $Action;
                }
                break;
            case "Start":
                $Action = Scaleway_StartServer($params);
                if ($Action == "success"){
                    $Return = "SUCCESS: Server is starting...";
                } else {
                    $Return = $Action;
                }
                break;
            default:
                $Return = "ERROR: Invalid Power action.";
        }

    } elseif(!empty($_POST["OS-Install"])) {
        $Return = $_POST["OS-Install"];

    } elseif(!empty($_POST["New-Hostname"])) {
        $New_Hostname = $_POST["New-Hostname"];
        if (empty($New_Hostname)) {
            $Return = "ERROR: Invalid hostname.";
        } else {
            $Action = $ScalewayServer->Modify_Hostname($New_Hostname);
            if ($Action["STATUS"] == 200){
                $LocalAPI_Data["serviceid"] = $params["serviceid"];
                $LocalAPI_Data["domain"] = $New_Hostname;
                localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

                $Return = "SUCCESS: Hostname updated!";

            } else {
                $Return = "ERROR: Can't update Hostname, please contact customer support.";
            }

        }
    }



    return $Return;
}

function str_Switch_OS(array $params, $New_Image_Name)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $ServerID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];
    $New_Volume = $params["customfields"]["New Volume"];
    $Return = "Success";

    if (strlen($Token) == 36 && strlen($OrgID) == 36 && strlen($ServerID) == 36 && $Location != "" ) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $ServerID);

        if (!$ScalewayServer->retrieveDetails()) {
            $Return = "ERROR retrieveDetails(): " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if (!empty($New_Volume)) {
            $Return = "ERROR: OS Installation is in progress.";
            goto Abort;
        }

        if ($ScalewayServer->archive_server()) {

            $LocalAPI_Data["serviceid"] = $params["serviceid"];
            $LocalAPI_Data["customfields"] = base64_encode(serialize(array("New Volume"=> "DAGADG" )));
            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "success";
        } else {
            $Return = "ERROR archive_server(): " . $ScalewayServer->queryInfo;
            goto Abort;
        }

    } else {
        $Return = "ERROR: Invalid function request";
        goto Abort;
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function getAdminUserName() {
    $adminData = Capsule::table('tbladmins')
            ->where('disabled', '=', 0)
            ->first();
    if (!empty($adminData))
        return $adminData->username;
    else
        die('No admin exist. Why So?');
}

function prettyPrintDate($date) {
    $date = date_parse($date);
    $date = sprintf("%d/%d/%d %d:%d:%d UTC",
    $date["year"],
    $date["month"],
    $date["day"],
    $date["hour"],
    $date["minute"],
    $date["second"]);
    return $date;
}