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

// This class making contact directly to Scaleway and return array with "ERROR" containing translated error
// message and "DATA" containing the actual respond from backend.
// Don't use this class directly, it's only meant to be use by ScalewayServer class

class ScalewayAPI
{

    //  ██████╗ ██████╗ ██╗██╗   ██╗ █████╗ ████████╗███████╗███████╗
    //  ██╔══██╗██╔══██╗██║██║   ██║██╔══██╗╚══██╔══╝██╔════╝██╔════╝
    //  ██████╔╝██████╔╝██║██║   ██║███████║   ██║   █████╗  ███████╗
    //  ██╔═══╝ ██╔══██╗██║╚██╗ ██╔╝██╔══██║   ██║   ██╔══╝  ╚════██║
    //  ██║     ██║  ██║██║ ╚████╔╝ ██║  ██║   ██║   ███████╗███████║
    //  ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝  ╚═╝  ╚═╝   ╚═╝   ╚══════╝╚══════╝

    private $Token = "";
    private $OrgID = "";
    private $APIURL = "";

    // Status codes returned by scaleway
    // Notice: Do not put 2XX in here, it will be mistaken as an error
    // also 5XX has already been handled, don't put it here.
    private $HTTPStatus =
        [
            "400" => "400: Bad Request - Missing or invalid parameter.",
            "401" => "401: Auth Error - Invalid Token/Organization_ID.",
            "402" => "402: Request Failed - Parameters were valid but request failed.",
            "403" => "403: Forbidden - Access to resource is prohibited.",
            "404" => "404: Not found - API Failed or object does not exist."
        ];

    /**
     * Call Scaleway's API
     * 
     * @param   string          $Token              Scaleway account Token Secret Key.
     * @param   string          $HTTP_Method        HTTP Request method (POST, GET, PATCH, ...).
     * @param   string          $Endpoint           API Endpoint (/servers, /ips, ...).
     * @param   string|null     $POST_Data          (optional) Method's Data.
     * @param   string|null     $POST_Content_Type  (optional) Content Type of Data (application/json, text/plain, ...).
     * @return  array           ["ERROR"]           Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]            Actual Respond Data from backend, usually JSON encoded or empty.
     */
    private function Call_Scaleway($Token, $HTTP_Method, $Endpoint, $POST_Data = null, $POST_Content_Type = null)
    {
        if(!isValidUUID($Token) || empty($HTTP_Method) || empty($Endpoint)) {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );

            $Input_Data .= "Token: " . PHP_EOL . $Token . PHP_EOL . PHP_EOL;
            $Input_Data .= "HTTP_Method: " . PHP_EOL . $HTTP_Method . PHP_EOL . PHP_EOL;
            $Input_Data .= "Endpoint: " . PHP_EOL . $Endpoint . PHP_EOL . PHP_EOL;

        } else {

            if ($POST_Content_Type != "") {
                $POST_Content_Type = "Content-Type: " . $POST_Content_Type;
            }

            $CURL_Header = array(
                "X-Auth-Token: " . $Token,
                $POST_Content_Type
            );

            $CURL = curl_init();
            curl_setopt($CURL, CURLOPT_URL, $this->APIURL . $Endpoint);
            curl_setopt($CURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            curl_setopt($CURL, CURLOPT_POST, true);
            curl_setopt($CURL, CURLOPT_CUSTOMREQUEST, $HTTP_Method);
            curl_setopt($CURL, CURLOPT_POSTFIELDS, $POST_Data);

            curl_setopt($CURL, CURLOPT_HTTPHEADER, $CURL_Header);
            curl_setopt($CURL, CURLOPT_RETURNTRANSFER, true);

            $CURL_Reply = curl_exec($CURL);
            $CURL_Reply_Code = curl_getinfo($CURL, CURLINFO_HTTP_CODE);

            curl_close($CURL);

            if (empty($CURL_Reply_Code)) {
                $Return_ERROR = "Failed while connecting to backend!";
            } elseif ($CURL_Reply_Code >= 200 && $CURL_Reply_Code <= 299) {
                $Return_ERROR = "";
            } elseif (in_array($CURL_Reply_Code, $this->HTTPStatus, true)) {
                $Return_ERROR = $this->HTTPStatus[$CURL_Reply_Code];
            } elseif ($CURL_Reply_Code >= 500 && $CURL_Reply_Code <= 599) {
                $Return_ERROR = $CURL_Reply_Code . ": Backend is on fire.";
            } else {
                $Return_ERROR = $CURL_Reply_Code . ": Unknown status code.";
            }

            $Return = array(
                "ERROR" => $Return_ERROR,
                "DATA" => $CURL_Reply
            );

            // Logging debug data
            $Input_Data .= "HEADER: " . PHP_EOL . print_r($CURL_Header, true) . PHP_EOL . PHP_EOL;
            $Input_Data .= "URL: " . PHP_EOL . $this->APIURL . $Endpoint . PHP_EOL . PHP_EOL;
            $Input_Data .= "METHOD: " . PHP_EOL . $HTTP_Method . PHP_EOL . PHP_EOL;
            $Input_Data .= "METHOD DATA: " . PHP_EOL . $POST_Data . PHP_EOL . PHP_EOL;
            $Input_Data .= "REPLY: " .  PHP_EOL . print_r($CURL_Reply, true) . PHP_EOL . PHP_EOL;

        }
        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);

        return $Return;
    }
    


    //  ██████╗ ██╗   ██╗██████╗ ██╗     ██╗ ██████╗███████╗
    //  ██╔══██╗██║   ██║██╔══██╗██║     ██║██╔════╝██╔════╝
    //  ██████╔╝██║   ██║██████╔╝██║     ██║██║     ███████╗
    //  ██╔═══╝ ██║   ██║██╔══██╗██║     ██║██║     ╚════██║
    //  ██║     ╚██████╔╝██████╔╝███████╗██║╚██████╗███████║
    //  ╚═╝      ╚═════╝ ╚═════╝ ╚══════╝╚═╝ ╚═════╝╚══════╝

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

    /**
     * Class Initialize
     * 
     * @param   string          $Token      Scaleway account Token Secret Key.
     * @param   string          $OrgID      Scaleway account Organization ID.
     * @param   string          $Location   Region (Paris, Amsterdam).
     */
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

    //   __     __          _                                 __  ____                                  _               _
    //   \ \   / /   ___   | |  _   _   _ __ ___     ___     / / / ___|   _ __     __ _   _ __    ___  | |__     ___   | |_
    //    \ \ / /   / _ \  | | | | | | | '_ ` _ \   / _ \   / /  \___ \  | '_ \   / _` | | '_ \  / __| | '_ \   / _ \  | __|
    //     \ V /   | (_) | | | | |_| | | | | | | | |  __/  / /    ___) | | | | | | (_| | | |_) | \__ \ | | | | | (_) | | |_ 
    //      \_/     \___/  |_|  \__,_| |_| |_| |_|  \___| /_/    |____/  |_| |_|  \__,_| | .__/  |___/ |_| |_|  \___/   \__|
    //                                                                                   |_|

    /**
     * Delete a Volume
     * 
     * @param   string          $Volume_UUID    Volume's UUID.
     * @return  array           ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Delete_Volume($Volume_UUID)
    {
        if(!isValidUUID($Volume_UUID)){
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/volumes/" . $Volume_UUID;
            $Return = $this->Call_Scaleway($this->Token, "DELETE", $Endpoint);
        }

        $Input_Data .= "Volume_UUID:"  . PHP_EOL . $Volume_UUID;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Create a new Scaleway Volume from Snapshot UUID
     * 
     * @param   string  $Snapshot_UUID      UUID of existing Snapshot to create a Volume from.
     * @param   string  $New_Volume_Name    Name of the newly created Volume.
     * @return  array                       Returned data from Call_Scaleway().
     */
    public function Scw_Volume_from_Snapshot($Snapshot_UUID, $New_Volume_Name)
    {

        if(!isValidUUID($Snapshot_UUID) || !is_string($New_Volume_Name) || empty($New_Volume_Name))
        {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/volumes";
            $Data = array(
              "base_snapshot"   => $Snapshot_UUID,
              "name"            => $New_Volume_Name,
              "organization"    => $this->OrgID,
              "volume_type"     => "l_ssd"
            );
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "GET", $Endpoint);
        }

        $Input_Data .= "Snapshot_UUID:"     . PHP_EOL . $Snapshot_UUID      . PHP_EOL . PHP_EOL;
        $Input_Data .= "New_Volume_Name:"   . PHP_EOL . $New_Volume_Name    . PHP_EOL . PHP_EOL;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Retrieve List of Snapshot, including their info
     * 
     * @return  array                   Returned data from Call_Scaleway().
     */
    public function Scw_Retrieve_Snapshots()
    {
        $Endpoint = "/snapshots";
        $Return = $this->Call_Scaleway($this->Token, "GET", $Endpoint);

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, "", $Output_Data);
        return $Return;
    }

    //    ____
    //   / ___|    ___   _ __  __   __   ___   _ __
    //   \___ \   / _ \ | '__| \ \ / /  / _ \ | '__|
    //    ___) | |  __/ | |     \ V /  |  __/ | |
    //   |____/   \___| |_|      \_/    \___| |_|

    /**
     * Create a new Scaleway Server
     * 
     * @param   string  $Server_Hostname    Hostname (server name) of the new server.
     * @param   string  $Snapshot_UUID      UUID of existing Snapshot to create a Server from.
     * @param   string  $CommercialType     Server CommercialType.
     * @param   string  $Tags               1 dimmension array of Tags.
     * @param   string  $Server_Username    New server's SSH Username.
     * @param   string  $Server_Password    New server's SSH Password.
     * @return  array                       Returned data from Call_Scaleway().
     */
    public function Scw_Create_New_Server($Server_Hostname, $Snapshot_UUID, $CommercialType, $Tags, $Server_Username, $Server_Password)
    {
        if(empty($Server_Hostname) || !isValidUUID($Snapshot_UUID) || !array_key_exists($CommercialType, $this->CommercialTypes) || empty($Tags) || empty($Server_Username) || empty($Server_Password)){
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
            $Log_Stage = "Validation";
        } else {

            ////////////////////////
            // Volume calculation //
            ////////////////////////

            // Get bytes of needed extra volumes by get the required Server Disk size then substitute the RootFS_Volume (25GB)
            $ExtraVolumeSizes = ($this->CommercialTypes[$CommercialType]["Disk"] * 1000000000) - 25000000000;

            // Initilize empty Volume array
            $VolumesArray = array();

            // RootFS_Volume is the one cloned from Snapshot
            $RootFS_Volume = array(
                "base_snapshot" => $Snapshot_UUID,
                "name" => $Server_Hostname . "-rootfs",
                "volume_type" => "l_ssd",
                "organization" => $this->OrgID
            );

            // Push RootFS_Volume into VolumesArray
            array_push($VolumesArray, $RootFS_Volume);
            
            // Scaleway don't allow a single volume to have over 150GB, this will be an issue with START1-L
            $MaxAllowedSize = 150000000000;
            $TotalCreatedVolumeCount = 0;
            
            // Create AdditionalVolumeArray contain an array of volumes depend on required maximum size
            while ($ExtraVolumeSizes) {
                if ($ExtraVolumeSizes > $MaxAllowedSize) {
                    // Force create a volume 150GB if the NewVolumeSize is over 150GB
                    $NewVolumeSize = $MaxAllowedSize;
                } else {
                    // Proceed to create the required volume size if otherwise
                    $NewVolumeSize = $ExtraVolumeSizes;
                }
            
                // JSON of Extra volume(s)
                $AdditionalVolumeArray[$TotalCreatedVolumeCount] =
                array(
                    "name" => $Server_Hostname . "-extra-" . $TotalCreatedVolumeCount,
                    "volume_type" => "l_ssd",
                    "size" => $NewVolumeSize,
                    "organization" => $this->OrgID
                );

                // Count how many volumes has been created
                $TotalCreatedVolumeCount++;

                // Substitute the Volume has created
                $ExtraVolumeSizes = $ExtraVolumeSizes - $NewVolumeSize;
            }

            // Push each created Extra volume(s) into VolumesArray
            for ($i = 0; $i < $TotalCreatedVolumeCount; $i++)
            {
                array_push($VolumesArray, $AdditionalVolumeArray[$i]);
            }


            // Reserve an IP address
            $Return = $this->Scw_New_Reserved_IP();

            // Abort if failed
            $Log_Stage = "Scw_New_Reserved_IP";
            if(empty($Return["ERROR"])) {

                $Reserved_IP_UUID = json_decode($Return["DATA"], true)["ip"]["id"];

                // Modify PTR of Reserved IP
                $Return = $this->Scw_Modify_IP_PTR($Reserved_IP_UUID, "customer.jiffy.host");

                // Abort if failed
                $Log_Stage = "Scw_Modify_IP_PTR";
                if (empty($Return["ERROR"])) {

                    // Finally create the server
                    $Data =
                    array(
                        "organization"      => $this->OrgID,
                        "name"              => $Server_Hostname,
                        "commercial_type"   => $CommercialType,
                        "tags"              => $Tags,
                        "boot_type"         => "local",
                        "public_ip"         => $Reserved_IP_UUID,
                        "enable_ipv6"       => true,
                        "volumes"           =>  (object) $VolumesArray
                    );
        
        
                    $Endpoint = "/servers";
                    $Data = json_encode($Data, JSON_PRETTY_PRINT);
                    $Return = $this->Call_Scaleway($this->Token, "POST", $Endpoint, $Data, "application/json");

                    // Abort if failed
                    $Log_Stage = "Scw_Create_New_Server";
                    if (empty($Return["ERROR"])) {
                        $Server_UUID = json_decode($Return["DATA"], true)["server"]["id"];
            
                        // Set User:Pass to CloudInit data, there will be a script running on first boot to do the password setup
                        $Temp_Return = $this->Scw_Modify_CloudInit($Server_UUID, $Server_Username . ":" . $Server_Password);

                        // Abort if failed
                        $Log_Stage = "Scw_Modify_CloudInit";
                        if (empty($Temp_Return["ERROR"])) {

                            // Power ON server
                            $Temp_Return = $this->Scw_Server_Action($Server_UUID, "poweron");

                            $Log_Stage = "Scw_Server_Action";
                            if (!empty($Temp_Return["ERROR"])) {
                                $Return = $Temp_Return;
                            }

                        } else {
                            $Return = $Temp_Return;
                        }
                    }
                }
            }
        }

        $Input_Data .= "Server_Hostname:"   . PHP_EOL . $Server_Hostname        . PHP_EOL . PHP_EOL;
        $Input_Data .= "Snapshot_UUID:"     . PHP_EOL . $Snapshot_UUID          . PHP_EOL . PHP_EOL;
        $Input_Data .= "CommercialType:"    . PHP_EOL . $CommercialType         . PHP_EOL . PHP_EOL;
        $Input_Data .= "Tags:"              . PHP_EOL . print_r($Tags, true)    . PHP_EOL . PHP_EOL;
        $Input_Data .= "Server_Username:"   . PHP_EOL . $Server_Username        . PHP_EOL . PHP_EOL;
        $Input_Data .= "Server_Password:"   . PHP_EOL . $Server_Password        . PHP_EOL . PHP_EOL;

        $Output_Data = "Stage: " . $Log_Stage . PHP_EOL . PHP_EOL . print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Do server action
     * 
     * @param   string  $Server_UUID    UUID of existing Server.
     * @param   string  $Action         Action to do on server, only allow "poweron", "stop_in_place", "reboot", "poweroff", "terminate".
     * @return  array                   Returned data from Call_Scaleway().
     */
    public function Scw_Server_Action($Server_UUID, $Action)
    {
        $Allowed_Action = array(
            "poweron", "stop_in_place", "reboot", "poweroff", "terminate"
        );

        if(!isValidUUID($Server_UUID) || !in_array($Action, $Allowed_Action, true) || empty($Action))
        {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/servers/" . $Server_UUID . "/action";
            $Data = array(
                "action" => $Action
            );
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "POST", $Endpoint, $Data, "application/json");
        }
        return $Return;
    }

    /**
     * Retrieve server info
     * 
     * @param   string  $Server_UUID    UUID of existing Server.
     * @return  array                   Returned data from Call_Scaleway().
     */
    public function Scw_Retrieve_Server_Info($Server_UUID)
    {
        if(!isValidUUID($Server_UUID))
        {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/servers/" . $Server_UUID;
            $Return = $this->Call_Scaleway($this->Token, "GET", $Endpoint);
        }

        $Input_Data .= "Server_UUID:"   . PHP_EOL . $Server_UUID;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Modify Server's Hostname
     * 
     * @param   string          $Server_UUID    Server UUID.
     * @param   string          $Hostname       New hostname (server name).
     * @return  array           ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Modify_Hostname($Server_UUID, $Hostname)
    {
        if(!isValidUUID($Server_UUID) || empty($Hostname)) {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/servers/" . $Server_UUID ;
            $Data = array("name" => $Hostname);
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "PATCH", $Endpoint, $Data, "application/json");
        }

        $Input_Data .= "Server_UUID:" . PHP_EOL . $Server_UUID . PHP_EOL . PHP_EOL;
        $Input_Data .= "Hostname:" . PHP_EOL . $Hostname;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Modify Server's CloudInit
     * 
     * @param   string          $Server_UUID    Server UUID.
     * @param   string          $Data|null      CloudInit Data in plain text, leave empty to delete CloudInit Data.
     * @return  array           ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Modify_CloudInit($Server_UUID, $Data = null)
    {
        if(!isValidUUID($Server_UUID)) {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/servers/" . $Server_UUID . "/user_data/cloud-init";
            $Return = $this->Call_Scaleway($this->Token, "PATCH", $Endpoint, $Data, "text/plain");
        }

        $Input_Data .= "Server_UUID:"   . PHP_EOL . $Server_UUID . PHP_EOL . PHP_EOL;
        $Input_Data .= "Data:"          . PHP_EOL . $Data;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }


    //    ___   ____         _          _       _                           
    //   |_ _| |  _ \       / \      __| |   __| |  _ __    ___   ___   ___ 
    //    | |  | |_) |     / _ \    / _` |  / _` | | '__|  / _ \ / __| / __|
    //    | |  |  __/     / ___ \  | (_| | | (_| | | |    |  __/ \__ \ \__ \
    //   |___| |_|       /_/   \_\  \__,_|  \__,_| |_|     \___| |___/ |___/

    /**
     * Modify IP's PTR
     * 
     * @param   string          $IP_UUID    IP address UUID.
     * @param   string          $PTR|null   New PTR (must be resolvable).
     * @return  array           ["ERROR"]   Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]    Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Modify_IP_PTR($IP_UUID, $PTR = null)
    {
        if(!isValidUUID($IP_UUID)){
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/ips/" . $IP_UUID;
            $Data = array(
                "reverse" => $PTR
            );
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "PATCH", $Endpoint, $Data, "application/json");
        }

        $Input_Data .= "IP_UUID:"  . PHP_EOL . $IP_UUID . PHP_EOL . PHP_EOL;
        $Input_Data .= "PTR:"      . PHP_EOL . $PTR;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }
    
    /**
     * Reserve a new IP address
     * 
     * @return  array           ["ERROR"]   Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]    Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_New_Reserved_IP()
    {
        $Endpoint = "/ips" ;
        $Data = array("organization" => $this->OrgID);
        $Data = json_encode($Data, JSON_PRETTY_PRINT);
        $Return = $this->Call_Scaleway($this->Token, "POST", $Endpoint, $Data, "application/json");

        $Output_Data = print_r($Return, true);
        logModuleCall('Scaleway', __FUNCTION__, "", $Output_Data);

        return $Return;
    }

    /**
     * Attach/Detach a reserved IP Address to a Server
     * 
     * @param   string          $IP_UUID            IP address UUID.
     * @param   string          $Server_UUID|null   Server UUID you want the IP to be attached (NULL for detatch the IP)
     * @return  array           ["ERROR"]           Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]            Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Attach_Reserved_IP($IP_UUID, $Server_UUID = null)
    {
        if(!isValidUUID($IP_UUID) || ($Server_UUID != null && !isValidUUID($Server_UUID))){
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/ips/" . $IP_UUID;
            $Data = array(
                "server" => $Server_UUID
            );
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "PATCH", $Endpoint, $Data, "application/json");
        }

        $Input_Data .= "IP_UUID:"      . PHP_EOL . $IP_UUID . PHP_EOL . PHP_EOL;
        $Input_Data .= "Server_UUID:"  . PHP_EOL . $Server_UUID;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
    }

    /**
     * Delete a reserved IP Address
     * 
     * @param   string          $IP_UUID    IP address UUID.
     * @return  array           ["ERROR"]   Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]    Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Delete_Reserved_IP($IP_UUID)
    {
        if(!isValidUUID($IP_UUID)){
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/ips/" . $IP_UUID;
            $Return = $this->Call_Scaleway($this->Token, "DELETE", $Endpoint);
        }

        $Input_Data .= "IP_UUID:"  . PHP_EOL . $IP_UUID;

        $Output_Data = print_r($Return, true);

        logModuleCall('Scaleway', __FUNCTION__, $Input_Data, $Output_Data);
        return $Return;
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
        $API_Request = $this->ScwAPI->Scw_Retrieve_Server_Info($this->ServerID);

        if (empty($API_Request["ERROR"])) {
            $API_Request = json_decode($API_Request["DATA"], true);
            $API_Request = $API_Request["server"];

            // Remove ServerPrefix from name
            $this->hostname = substr($API_Request["hostname"], strlen($this->ServerPrefix));

            if ($API_Request["state"] == "stopped in place") {
                $this->state = '<span class="label label-danger">NOT RUNNING</span>';
            } elseif ($API_Request["state"] == "stopping") {
                $this->state = '<span class="label label-warning">SHUTTING DOWN</span>';
            } elseif ($API_Request["state"] == "stopped") {
                $this->state = '<span class="label label-default">ARCHIVED</span>';
            } elseif ($API_Request["state"] == "running") {
                $this->state = '<span class="label label-success">RUNNING</span>';
            } elseif ($API_Request["state"] == "starting") {
                $this->state = '<span class="label label-primary">STARTING</span>';
            } else {
                $this->state = $API_Request["state"];
            }
            
            $this->commercial_type = $API_Request["commercial_type"];
            $this->tags = $API_Request["tags"];
            $this->security_group = $API_Request["security_group"]["name"];
            $this->organization = $API_Request["organization"];

            $this->Info_Disk = $this->ScwAPI->CommercialTypes[$this->commercial_type]["Disk"];
            $this->Info_Core = $this->ScwAPI->CommercialTypes[$this->commercial_type]["Core"];
            $this->Info_RAM = $this->ScwAPI->CommercialTypes[$this->commercial_type]["RAM"];


            $this->public_ip["id"] = $API_Request["public_ip"]["id"];
            $this->public_ip["address"] = $API_Request["public_ip"]["address"];
            $this->ipv6 = $API_Request["ipv6"]["address"];
            $this->private_ip = $API_Request["private_ip"];

            $this->creation_date = prettyPrintDate($API_Request["creation_date"]);
            $this->modification_date = prettyPrintDate($API_Request["modification_date"]);

            $this->queryInfo = "Success!";

            $Return = true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            $Return = false;
        }

        logModuleCall('Scaleway', __FUNCTION__, '[NO INPUT]' . PHP_EOL . PHP_EOL . "API_Request:" . print_r($API_Request, true), $Return);

        return $Return;
    }



    public function create_new_server($Server_Name, $OS_ImageID, $commercial_type, $tags = array(), $Server_Username, $ServerPassword)
    {
        $API_Request = $this->ScwAPI->Scw_Create_New_Server( $this->ServerPrefix . $Server_Name, $OS_ImageID, $commercial_type, $tags, $Server_Username, $ServerPassword);
        if (empty($API_Request["ERROR"])) {
            $serverInfo = json_decode($API_Request["DATA"], true);
            $serverInfo = $serverInfo["server"];
            $this->ServerID = $serverInfo["id"];
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }



    public function delete_server()
    {
        // Getting the infos before deletion
        $API_Request = $this->ScwAPI->Scw_Retrieve_Server_Info($this->ServerID);

        if(!empty($API_Request["ERROR"])){
            goto Abort;
        }

        // Getting its reserved IPv4 UUID
        $AttachedIP_ID = json_decode($API_Request["DATA"], true)["server"]["public_ip"]["id"];

        // Detach its reserved IP
        $API_Request = $this->ScwAPI->Scw_Attach_Reserved_IP($AttachedIP_ID);

        if(!empty($API_Request["ERROR"])){
            goto Abort;
        }

        // Delete its reserved IP
        $API_Request = $this->ScwAPI->Scw_Delete_Reserved_IP($AttachedIP_ID);
    
        if(!empty($API_Request["ERROR"])){
            goto Abort;
        }

        $API_Request = $this->ScwAPI->Scw_Server_Action($this->ServerID, "terminate");

        if (empty($API_Request["ERROR"])) {
            return true;
        }

        Abort:
        $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
        $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
        return false;
        
    }

    public function stop_server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->ServerID, "stop_in_place");
        if (empty($API_Request["ERROR"])) {
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }



    public function poweron_server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->ServerID, "poweron");
        if (empty($API_Request["ERROR"])) {
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }




    public function reboot_server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->ServerID, "reboot");
        if (empty($API_Request["ERROR"])) {
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }



    public function archive_server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->ServerID, "poweroff");
        if (empty($API_Request["ERROR"])) {
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    public function retrieve_snapshot_id($snapshot_name)
    {

        $API_Request = $this->ScwAPI->Scw_Retrieve_Snapshots();
        if (empty($API_Request["ERROR"])) {

            $json = json_decode($API_Request["DATA"]);
            foreach ($json->snapshots as $item) {
                if ($item->name == $snapshot_name) {
                    return $item->id;
                }
            }
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return "";
        }

    }

    public function Modify_Hostname($New_Hostname)
    {

        $API_Request = $this->ScwAPI->Scw_Modify_Hostname($this->ServerID, $this->$ServerPrefix . $New_Hostname);
        if (empty($API_Request["ERROR"])) {
            $this->retrieveDetails();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
        
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

        if ($ScalewayServer->create_new_server($ServerName, $OSImageID, $CommercialType, $ServerTag, $ServerUsername , $ServerPassword)) {

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

    
    $Service_Status = $params["status"];
    if($Service_Status != "Active"){
        $Return = array('tabOverviewReplacementTemplate' => 'error.tpl',
                        'templateVariables' => array(
                                                    'usefulErrorHelper' => "Sorry, your service is currently " . $Service_Status,
                                                    )
                        );
        
        goto Abort;
    }

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
            if ($ScalewayServer->Modify_Hostname($New_Hostname)){
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