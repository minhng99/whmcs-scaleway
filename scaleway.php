<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

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
        if (!isValidUUID($Token) || empty($HTTP_Method) || empty($Endpoint)) {
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


        $this->APIURL = "https://cp-" . $Location . ".scaleway.com";
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
        if (!isValidUUID($Volume_UUID)) {
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
        if (!isValidUUID($Snapshot_UUID) || !is_string($New_Volume_Name) || empty($New_Volume_Name)) {
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
            $Return = $this->Call_Scaleway($this->Token, "POST", $Endpoint, $Data, "application/json");
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
    public function Scw_Create_New_Server($Server_Hostname, $Snapshot_UUID, $CommercialType, $Tags, $Server_Username, $Server_Password, $Reserved_IPv4_UUID)
    {
        if (empty($Server_Hostname) || !isValidUUID($Snapshot_UUID) || !array_key_exists($CommercialType, $this->CommercialTypes) || empty($Tags) || empty($Server_Username) || empty($Server_Password) || !isValidUUID($Reserved_IPv4_UUID)) {
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
            for ($i = 0; $i < $TotalCreatedVolumeCount; $i++) {
                array_push($VolumesArray, $AdditionalVolumeArray[$i]);
            }

            // Finally create the server
            $Data =
            array(
                "organization"      => $this->OrgID,
                "name"              => $Server_Hostname,
                "commercial_type"   => $CommercialType,
                "tags"              => $Tags,
                "boot_type"         => "local",
                "public_ip"         => $Reserved_IPv4_UUID,
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

        if (!isValidUUID($Server_UUID) || !in_array($Action, $Allowed_Action, true) || empty($Action)) {
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
        if (!isValidUUID($Server_UUID)) {
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
     * @param   string          $Hostname       New Hostname (server name).
     * @return  array           ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Modify_Hostname($Server_UUID, $Hostname)
    {
        if (!isValidUUID($Server_UUID) || empty($Hostname)) {
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
        if (!isValidUUID($Server_UUID)) {
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

    /**
     * Modify Server's Volumes
     *
     * @param   string      $Server_UUID    Server's UUID.
     * @param   array       $Volumes_UUID   An Array of new Volumes UUID.
     * @return  array       ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array       ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Scw_Modify_Server_Volumes($Server_UUID, array $Volumes_UUID = null)
    {
        if (!empty($Volumes_UUID)) {
            foreach ($Volumes_UUID as $Volume_UUID) {
                if (!isValidUUID($Server_UUID)) {
                    $Server_UUID = null;
                    break;
                }
            }
        }
        if (!isValidUUID($Server_UUID)) {
            $Return = array(
                "ERROR" => "Input data is invalid",
                "DATA" => ""
            );
        } else {
            $Endpoint = "/servers/" . $Server_UUID;

            $Volumes_UUID = (object)$Volumes_UUID;

            $Data = array(
                "volumes" => $Volumes_UUID
            );
            $Data = json_encode($Data, JSON_PRETTY_PRINT);
            $Return = $this->Call_Scaleway($this->Token, "PATCH", $Endpoint, $Data, "application/json");
        }

        $Input_Data .= "Server_UUID:"   . PHP_EOL . $Server_UUID;
        $Input_Data .= "Volumes_UUID:"  . PHP_EOL . print_r($Volumes_UUID, true);

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
        if (!isValidUUID($IP_UUID)) {
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
        if (!isValidUUID($IP_UUID) || ($Server_UUID != null && !isValidUUID($Server_UUID))) {
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
        if (!isValidUUID($IP_UUID)) {
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
    protected $ScwAPI = null;

    public $Locations = array(
        "Paris"     => "par1",
        "Amsterdam" => "ams1",
    );

    // Hostname prefix, use to identify customer's server on Scaleway's panel
    public $ServerPrefix =  "CUSTOMER-";

    // Return Server's UUID
    public $Server_UUID = null;

    // Return Servevr's Hostname
    public $Hostname = null;

    // Return "message" from ScalewayAPI or translated error code
    public $queryInfo = null;

    // Return current server's specs
    public $Server_Specs = array(
        "Type" => null,
        "Disk" => null,
        "Core" => null,
        "RAM" =>  null
    );

    // Return current server's array of attached disks
    public $Server_Disks = array();

    // Return Creation Date
    public $Creation_Date = null;

    // Return Modification Date (Last Start/Stop/Archive...)
    public $Modification_Date = null;

    // Everything about the server's IP
    public $IP = array(
        "IPv4_Global" => null,
        "IPv4_Private" => null,
        "IPv4_UUID" => null,
        "IPv6_Global" => null,
        "IPv6_Netmask" => null,
        "IPv6_Gateway" => null
    );

    // HTML-styled, colored Server state (Running, Stopping, Stopped...)
    public $Server_State = null;

    // Server Tags in 1 dimmension array
    public $Server_Tags = array();

    // Name of the Security Group which this server belongs
    // You could named it something like SMTP blocked/SMTP unblocked and assign it to customer's server accordingly to requests
    public $Server_Sec_Group = null;


    /**
     * Class initializer, call this first then use other public function to interacts with Scaleway.
     *
     * @param   string          $Token              Scaleway account Token Secret Key.
     * @param   string          $OrgID              Scaleway account Organization ID.
     * @param   string          $Location           Server's location (Paris, Amsterdam)
     * @param   string          $Server_UUID|null   Server's UUID (optional)
     */
    public function __construct($Token, $OrgID, $Location, $Server_UUID = null)
    {
        $this->ScwAPI = new ScalewayAPI($Token, $OrgID, $this->Locations[$Location]);
        $this->Server_UUID = $Server_UUID;
    }

    /**
     * Get current server's info and then assign to public variables, use ->queryInfo for returned message in case of failed.
     *
     * @return  bool    TRUE for success, FALSE otherwise.
     */
    public function Retrieve_Server_Info()
    {
        $API_Request = $this->ScwAPI->Scw_Retrieve_Server_Info($this->Server_UUID);

        if (empty($API_Request["ERROR"])) {
            $API_Request = json_decode($API_Request["DATA"], true);
            $API_Request = $API_Request["server"];

            // Remove ServerPrefix from name
            $this->Hostname = substr($API_Request["hostname"], strlen($this->ServerPrefix));

            if ($API_Request["state"] == "stopped in place") {
                $this->Server_State = '<span class="label label-danger">NOT RUNNING</span>';
            } elseif ($API_Request["state"] == "stopping") {
                $this->Server_State = '<span class="label label-warning">SHUTTING DOWN</span>';
            } elseif ($API_Request["state"] == "stopped") {
                $this->Server_State = '<span class="label label-default">ARCHIVED</span>';
            } elseif ($API_Request["state"] == "running") {
                $this->Server_State = '<span class="label label-success">RUNNING</span>';
            } elseif ($API_Request["state"] == "starting") {
                $this->Server_State = '<span class="label label-primary">STARTING</span>';
            } else {
                $this->Server_State = $API_Request["state"];
            }

            $this->Server_Tags = $API_Request["tags"];
            $this->Server_Sec_Group = $API_Request["security_group"]["name"];

            $this->Server_Specs["Type"] = $API_Request["commercial_type"];
            $this->Server_Specs["Disk"] = $this->ScwAPI->CommercialTypes[$this->Server_Specs["Type"]]["Disk"];
            $this->Server_Specs["Core"] = $this->ScwAPI->CommercialTypes[$this->Server_Specs["Type"]]["Core"];
            $this->Server_Specs["RAM"] = $this->ScwAPI->CommercialTypes[$this->Server_Specs["Type"]]["RAM"];

            $this->Server_Disks = array_column($API_Request["volumes"], "id");

            $this->IP["IPv4_UUID"] = $API_Request["public_ip"]["id"];
            $this->IP["IPv4_Global"] = $API_Request["public_ip"]["address"];
            $this->IP["IPv4_Private"] = $API_Request["private_ip"];

            $this->IP["IPv6_Global"] = $API_Request["ipv6"]["address"];
            $this->IP["IPv6_Gateway"] = $API_Request["ipv6"]["gateway"];
            $this->IP["IPv6_Netmask"] = $API_Request["ipv6"]["netmask"];

            $this->Creation_Date = prettyPrintDate($API_Request["creation_date"]);
            $this->Modification_Date = prettyPrintDate($API_Request["modification_date"]);

            $this->queryInfo = "Success!";

            $Return = true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            $Return = false;
        }

        logModuleCall('Scaleway', __FUNCTION__, "API_Request:" . PHP_EOL . print_r($API_Request, true), $Return);

        return $Return;
    }


    /**
     * Create a new server, use ->queryInfo for returned message in case of failed.
     *
     * @param   string  $Hostname           Hostname (Server name).
     * @param   string  $Snapshot_UUID      UUID of Snapshot image.
     * @param   string  $Server_Type        Server Commercial Type, must be declared in ScalewayAPI->CommercialTypes (START1-XS, START1-S, ...).
     * @param   array   $Tags               Server's Tag, each array element for each entry.
     * @param   string  $Server_Username    Server's login Username.
     * @param   string  $Server_Password    Server's login Password.
     * @return  bool                        TRUE for success, FALSE otherwise.
     *
     */
    public function Create_New_Server($Hostname, $Snapshot_UUID, $Server_Type, $Tags = array(), $Server_Username, $Server_Password, $Reserved_IPv4_UUID)
    {
        $API_Request = $this->ScwAPI->Scw_Create_New_Server($this->ServerPrefix . $Hostname, $Snapshot_UUID, $Server_Type, $Tags, $Server_Username, $Server_Password, $Reserved_IPv4_UUID);
        if (empty($API_Request["ERROR"])) {
            $serverInfo = json_decode($API_Request["DATA"], true);
            $serverInfo = $serverInfo["server"];
            $this->Server_UUID = $serverInfo["id"];
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Delete current server with all of its resource (Volumes, reserved IP), use ->queryInfo for returned message in case of failed.
     *
     * @param   bool  $Delete_Reserved_IP|false     Delete the attached IPv4? Default is false
     * @return  bool                                TRUE for success, FALSE otherwise.
     *
     */
    public function Delete_Server($Delete_Reserved_IP = false)
    {
        // Getting the infos before deletion to make sure it's still valid
        $API_Request = $this->ScwAPI->Scw_Retrieve_Server_Info($this->Server_UUID);

        if (!empty($API_Request["ERROR"])) {
            goto Abort;
        }

        if ($Delete_Reserved_IP) {
            // Getting its reserved IPv4 UUID
            $Attached_IPv4_UUID = $this->IP["IPv4_UUID"];

            // Detach its reserved IP, this function will detatch if the second param is NULL
            $API_Request = $this->ScwAPI->Scw_Attach_Reserved_IP($Attached_IPv4_UUID);

            if (!empty($API_Request["ERROR"])) {
                goto Abort;
            }

            // Delete its reserved IP
            $API_Request = $this->ScwAPI->Scw_Delete_Reserved_IP($Attached_IPv4_UUID);

            if (!empty($API_Request["ERROR"])) {
                goto Abort;
            }
        }

        $API_Request = $this->ScwAPI->Scw_Server_Action($this->Server_UUID, "terminate");

        if (empty($API_Request["ERROR"])) {
            return true;
        }

        Abort:
        $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
        $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
        return false;
    }

    /**
     * Shutdown current server (stop in place, not archive), use ->queryInfo for returned message in case of failed.
     *
     * @return  bool    TRUE for success, FALSE otherwise.
     *
     */
    public function Stop_Server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->Server_UUID, "stop_in_place");
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Start current server, use ->queryInfo for returned message in case of failed.
     *
     * @return  bool    TRUE for success, FALSE otherwise.
     *
     */
    public function Start_Server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->Server_UUID, "poweron");
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Reboot current server, use ->queryInfo for returned message in case of failed.
     *
     * @return  bool    TRUE for success, FALSE otherwise.
     *
     */
    public function Reboot_Server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->Server_UUID, "reboot");
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Stop and Archive current server, use ->queryInfo for returned message in case of failed.
     * WARNING: Archived servers will lost their "dynamic" IPv4 and will definitely lost their IPv6
     *
     * @return  bool    TRUE for success, FALSE otherwise.
     *
     */
    public function Archive_Server()
    {
        $API_Request = $this->ScwAPI->Scw_Server_Action($this->Server_UUID, "poweroff");
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Get UUID from Snapshot's name, use ->queryInfo for returned message in case of failed.
     *
     * @param   string  $Snapshot_Name  Name of Snapshot.
     * @return  string                  Snapshot's UUID.
     *
     */
    public function Retrieve_Snapshot_UUID($Snapshot_Name)
    {
        if (empty($Snapshot_Name)) {
            $this->queryInfo = "ERROR: Snapshot Name cannot be empty";
            return null;
        }

        $API_Request = $this->ScwAPI->Scw_Retrieve_Snapshots();
        if (empty($API_Request["ERROR"])) {
            $JSON = json_decode($API_Request["DATA"]);
            foreach ($JSON->snapshots as $item) {
                if ($item->name == $Snapshot_Name) {
                    return $item->id;
                }
            }
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return null;
        }
    }

    /**
     * Create a new Volume from Snapshot UUID, use ->queryInfo for returned message in case of failed.
     *
     * @param   string  $Snapshot_UUID      UUID of Snapshot.
     * @param   string  $New_Volume_Name    Name of the new Volume.
     * @return  string                      New Volume's UUID.
     *
     */
    public function Volume_from_Snapshot($Snapshot_UUID, $New_Volume_Name)
    {
        if (!isValidUUID($Snapshot_UUID) || empty($New_Volume_Name)) {
            $this->queryInfo = "ERROR: Input invalid";
            return null;
        }

        $API_Request = $this->ScwAPI->Scw_Volume_from_Snapshot($Snapshot_UUID, $this->ServerPrefix . $New_Volume_Name);
        if (empty($API_Request["ERROR"])) {
            $Volume_UUID = json_decode($API_Request["DATA"], true)["volume"]["id"];
            return $Volume_UUID;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return null;
        }
    }

    /**
     * Change Server's Hostname (Server Name), use ->queryInfo for returned message in case of failed.
     *
     * @param   string  $New_Hostname   Hostname (Server name).
     * @return  bool                    TRUE for success, FALSE otherwise.
     *
     */
    public function Modify_Hostname($New_Hostname)
    {
        if (empty($New_Hostname)) {
            $this->queryInfo = "ERROR: Hostname cannot be empty";
            return false;
        }

        $API_Request = $this->ScwAPI->Scw_Modify_Hostname($this->Server_UUID, $this->ServerPrefix . $New_Hostname);
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Change Server's Volumes, use ->queryInfo for returned message in case of failed.
     *
     * @param   string  $Volumes_UUID   An Array of Volumes UUID.
     * @return  bool                    TRUE for success, FALSE otherwise.
     *
     */
    public function Modify_Server_Volumes($Volumes_UUID)
    {
        $API_Request = $this->ScwAPI->Scw_Modify_Server_Volumes($this->Server_UUID, $Volumes_UUID);
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Delete a Volume
     *
     * @param   string          $Volume_UUID    Volume's UUID.
     * @return  array           ["ERROR"]       Backend's ERROR message, translated from respond code.
     * @return  array           ["DATA"]        Actual Respond Data from backend, usually JSON encoded or empty.
     */
    public function Delete_Volume($Volume_UUID)
    {
        if (!isValidUUID($Volume_UUID)) {
            $this->queryInfo = "ERROR: Input invalid";
            return null;
        }

        $API_Request = $this->ScwAPI->Scw_Delete_Volume($Volume_UUID);
        if (empty($API_Request["ERROR"])) {
            $this->Retrieve_Server_Info();
            return true;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return false;
        }
    }

    /**
     * Create a Reserved IPv4
     *
     * @return  string  New Reserved IPv4 UUID.
     */
    public function New_Reserved_IP()
    {
        $API_Request = $this->ScwAPI->Scw_New_Reserved_IP();
        if (empty($API_Request["ERROR"])) {
            $IPv4_UUID = json_decode($API_Request["DATA"], true)["ip"]["id"];

            // Default PTR for new Reserved IP
            $this->ScwAPI->Scw_Modify_IP_PTR($IPv4_UUID, "customer.jiffy.host");

            return $IPv4_UUID;
        } else {
            $Backend_Message = json_decode($API_Request["DATA"], true)["message"];
            $this->queryInfo = !empty($Backend_Message) ? $Backend_Message : $API_Request["ERROR"];
            return null;
        }
    }

    /**
     * Attach/detatch a Reserved IPv4
     *
     * @param   string      $IP_UUID            IP's UUID.
     * @param   string      $Server_UUID|null   Server's UUID, leave empty for Detatch.
     * @return  bool                            TRUE for success, FALSE otherwise.
     */
    public function Attach_Reserved_IP($IP_UUID, $Server_UUID = null)
    {
        $API_Request = $this->ScwAPI->Scw_Attach_Reserved_IP($IP_UUID, $Server_UUID = null);
        if (empty($API_Request["ERROR"])) {
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

    $Server_Type = $params["configoption3"];
    $Hostname = $params["domain"];

    $ClientID = $params["model"]["client"]["id"];
    $OrderID = $params["model"]["orderid"];
    $ServiceID = $params["serviceid"];

    $OS_Name = $params["customfields"]["Operating System"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_Type: " . $Server_Type . PHP_EOL .
                  "Hostname: " . $Hostname . PHP_EOL .
                  "ClientID: " . $ClientID . PHP_EOL .
                  "OrderID: " . $OrderID . PHP_EOL .
                  "ServiceID: " . $ServiceID . PHP_EOL .
                  "OS_Name: " . $OS_Name . PHP_EOL .  PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && !empty($Location) && !empty($Server_Type) && !empty($Hostname) &&
        !empty($ClientID) && !empty($OrderID) && !empty($ServiceID) && !empty($OS_Name)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location);

        $Snapshot_UUID = $ScalewayServer->Retrieve_Snapshot_UUID($OS_Name);

        if (!isValidUUID($Snapshot_UUID)) {
            $Return =  "Invalid OS: " . $OS_Name;
            goto Abort;
        }

        $Server_Tags = array("ClientID: " . $ClientID,
                             "OrderID: " . $OrderID,
                             "ServiceID: " . $ServiceID);

        $Server_Username = "root";
        $Server_Password = md5($params["password"]);

        // Reserve an IPv4 address
        $Reserved_IPv4_UUID = $ScalewayServer->New_Reserved_IP();

        // Abort if failed
        if (isValidUUID($Reserved_IPv4_UUID)) {
            if ($ScalewayServer->Create_New_Server($Hostname, $Snapshot_UUID, $Server_Type, $Server_Tags, $Server_Username, $Server_Password, $Reserved_IPv4_UUID)) {
                $LocalAPI_Data["serviceid"] = $ServiceID;
                $LocalAPI_Data["serviceusername"] = $Server_Username;
                $LocalAPI_Data["servicepassword"] = $Server_Password;
                $LocalAPI_Data["customfields"] = base64_encode(serialize(array(
                                                                        "Server ID"=> $ScalewayServer->Server_UUID,
                                                                        "Operating System" => $OS_Name
                                                                        )));

                localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

                $Return = "success";
            } else {
                $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
            }
        } else {
            $Return = "Failed to New_Reserved_IP: " . $ScalewayServer->queryInfo;
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Stop_Server()) {
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

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_UnsuspendAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Start_Server()) {
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

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

function Scaleway_TerminateAccount(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Delete_Server(true)) {
            $LocalAPI_Data["serviceid"] = $params["serviceid"];
            $LocalAPI_Data["status"] = "Terminated";
            $LocalAPI_Data["customfields"] = base64_encode(serialize(array("Server ID"=> "TERMINATED-" . $Server_UUID )));
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = array("ERROR: Can't retrieve info." => "");
            goto Abort;
        }

        $Return = array();

        if (strpos($ScalewayServer->Server_State, 'NOT RUNNING') !== false ||
             strpos($ScalewayServer->Server_State, 'ARCHIVED') !== false) {
            $Return = array_merge($Return, array("Start Server" => "StartServer"));
        } elseif (strpos($ScalewayServer->Server_State, 'RUNNING') !== false) {
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = array("Failed Reason: " => $ScalewayServer->queryInfo);
            goto Abort;
        }

        // Updating fields with data returned from backend.
        $LocalAPI_Data["serviceid"] = $params["serviceid"];
        $LocalAPI_Data["dedicatedip"] = $ScalewayServer->IP["IPv4_Global"];
        $LocalAPI_Data["domain"] = $ScalewayServer->Hostname;
        localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

        // Return an array based on the function's response.
        $Return = array(
            'Server name' => $ScalewayServer->Hostname,
            'Go to Scaleway Panel' => '<a href="https://cloud.scaleway.com/#/zones/' . $ScalewayServer->Locations[$Location] . '/servers/' . $Server_UUID . '" target="_blank">Open</a>',
            'Server state' => $ScalewayServer->Server_State,
            'Disk' => $ScalewayServer->Server_Specs["Disk"] . "GB [" . implode($ScalewayServer->Server_Disks, ", ") . "]",
            'Core' => $ScalewayServer->Server_Specs["Core"],
            'RAM' => $ScalewayServer->Server_Specs["RAM"] . "GB",
            'Image' => $params["customfields"]["Operating System"],
            'Creation date' => $ScalewayServer->Creation_Date,
            'Modification date' => $ScalewayServer->Modification_Date,
            'Public IPv4' => $ScalewayServer->IP["IPv4_Global"] . " [" . $ScalewayServer->IP["IPv4_UUID"] . "]",
            'Private IPv4' => $ScalewayServer->IP["IPv4_Private"],
            'IPv6' => $ScalewayServer->IP["IPv6_Global"],
            'Location' => $Location,
            'Commercial type' => $ScalewayServer->Server_Specs["Type"],
            'Tags' => implode(", ", $ScalewayServer->Server_Tags),
            'Security group' => $ScalewayServer->Server_Sec_Group,
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " .$ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Reboot_Server()) {
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

function Scaleway_StopServer(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Stop_Server()) {
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

function Scaleway_StartServer(array $params)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR: " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        if ($ScalewayServer->Start_Server()) {
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];
    $ServiceID = $params["serviceid"];

    $RequestLog = "Token: " . $Token . PHP_EOL .
                  "OrgID: " . $OrgID . PHP_EOL .
                  "Server_UUID: " . $Server_UUID . PHP_EOL .
                  "Location: " . $Location . PHP_EOL . PHP_EOL .
                  "Raw Param: " . PHP_EOL . print_r($params, true);


    $Service_Status = $params["status"];
    if ($Service_Status != "Active") {
        $Return = array('tabOverviewReplacementTemplate' => 'error.tpl',
                        'templateVariables' => array(
                                                    'usefulErrorHelper' => "Sorry, your service is currently " . $Service_Status,
                                                    )
                        );

        goto Abort;
    }

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = array('tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                                        'usefulErrorHelper' => "ERROR: " . $ScalewayServer->queryInfo,
                                        )
            );
            goto Abort;
        }

        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $ClientAction=customAction($params);
        }

        // Get the status after action
        $ScalewayServer->Retrieve_Server_Info();

        if (strpos($ScalewayServer->Server_State, 'NOT RUNNING') !== false) {
            $Is_Running = 0;
        } elseif (strpos($ScalewayServer->Server_State, 'RUNNING') !== false) {
            $Is_Running = 1;
        } else {
            $Is_Running = -1;
        }

        foreach (getOSList($params["pid"]) as $OS_Item) {
            $Available_OS .= "<option>" . $OS_Item . "</option>" . PHP_EOL;
        }

        $Return = array('templateVariables' => array(
                                                    'Action_Result' => $ClientAction,

                                                    'Service_ID' => $ServiceID,
                                                    'Server_UUID' =>$ScalewayServer->Server_UUID,
                                                    'Hostname' => $ScalewayServer->Hostname,
                                                    'Server_State' => $ScalewayServer->Server_State,
                                                    'IPv4_Public' => $ScalewayServer->IP["IPv4_Global"],
                                                    'IPv4_Private' => $ScalewayServer->IP["IPv4_Private"],
                                                    'IPv6' => $ScalewayServer->IP["IPv6_Global"],
                                                    'Username' => $params["username"],
                                                    'Password' => $params["password"],
                                                    'CPU_Core' => $ScalewayServer->Server_Specs["Core"],
                                                    'RAM' => $ScalewayServer->Server_Specs["RAM"] . "GB",
                                                    'Disk' => $ScalewayServer->Server_Specs["Disk"] . "GB",
                                                    'OS' => $params["customfields"]["Operating System"],
                                                    'Location' => $Location,
                                                    'Security_Group' => $ScalewayServer->Server_Sec_Group,
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
function isValidUUID($UUID)
{
    if (!is_string($UUID) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $UUID) !== 1)) {
        return false;
    }
    return true;
}

/**
 * Get available OS for a ProductID from database
 *
 * @param   string  $PID    $params["pid"]
 * @return  array           OS list in an array
 */
function getOSList($PID)
{
    $Query_Result = Capsule::table('tblcustomfields')
    ->where('tblcustomfields.fieldname', "Operating System")
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
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];

    $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

    if (!empty($_POST["Power"])) { // Power action
        switch ($_POST['Power']) {
            case "Reboot":
                $Action = Scaleway_RebootServer($params);
                if ($Action == "success") {
                    $Return = "SUCCESS: Server is rebooting...";
                } else {
                    $Return = $Action;
                }
                break;
            case "Stop":
                $Action = Scaleway_StopServer($params);
                if ($Action == "success") {
                    $Return = "SUCCESS: Server is stopping...";
                } else {
                    $Return = $Action;
                }
                break;
            case "Start":
                $Action = Scaleway_StartServer($params);
                if ($Action == "success") {
                    $Return = "SUCCESS: Server is starting...";
                } else {
                    $Return = $Action;
                }
                break;
            default:
                $Return = "ERROR: Invalid Power action.";
        }
    } elseif (!empty($_POST["OS-Install"])) { // OS Install
        $Return = Server_Install_OS($params, $_POST["OS-Install"]);
    } elseif (!empty($_POST["New-Hostname"])) { // Hostname change
        $New_Hostname = $_POST["New-Hostname"];
        if (empty($New_Hostname)) {
            $Return = "ERROR: Invalid hostname.";
        } else {
            if ($ScalewayServer->Modify_Hostname($New_Hostname)) {
                $LocalAPI_Data["serviceid"] = $params["serviceid"];
                $LocalAPI_Data["domain"] = $New_Hostname;
                localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

                $Return = "SUCCESS: Hostname updated!";
            } else {
                $Return = "ERROR: Can't update Hostname, please contact customer support.";
            }
        }
    } else {
        $Return = "ERROR: No valid action.";
    }

    return $Return;
}

/**
 * Switch the Server's OS to another one.
 *
 * How it's work: Scaleway does not support OS reinstallation, so this function Detatch the old IPv4, delete the machine and create another with the old IPv4 UUID.
 *
 * @param   array   $params         WHMCS's function params
 * @param   string  $New_OS_Name    New OS name, must be a Snapshot name.
 * @return  string                  Error message
 */
function Server_Install_OS(array $params, $New_OS_Name)
{
    $Token = $params["configoption1"];
    $OrgID = $params["configoption2"];
    $Server_UUID = $params["customfields"]["Server ID"];
    $Location = $params["customfields"]["Location"];
    $Hostname = $params["domain"];

    $Server_Type = $params["configoption3"];
    $Current_Username = $params["username"];
    $Current_Password = $params["password"];

    $ClientID = $params["model"]["client"]["id"];
    $OrderID = $params["model"]["orderid"];
    $ServiceID = $params["serviceid"];

    if (empty($New_OS_Name)) {
        $Return = "ERROR: No valid OS.";
        goto Abort;
    }

    if (isValidUUID($Token) && isValidUUID($OrgID) && isValidUUID($Server_UUID) && !empty($Location)) {
        $ScalewayServer = new ScalewayServer($Token, $OrgID, $Location, $Server_UUID);

        if (!$ScalewayServer->Retrieve_Server_Info()) {
            $Return = "ERROR Retrieve_Server_Info(): " . $ScalewayServer->queryInfo;
            goto Abort;
        }

        $Old_IPv4_UUID = $ScalewayServer->IP["IPv4_UUID"];
        if (!isValidUUID($Old_IPv4_UUID)) {
            $Return = "ERROR: Not valid Old_IPv4_UUID.";
            goto Abort;
        }

        if (!$ScalewayServer->Attach_Reserved_IP($Old_IPv4_UUID)) {
            $Return = "ERROR: Can't Reinstall OS.<br>Reason: RVJSX0RFVEFUQ0hfRkFJTEVE<br>Please contact customer support."; // Base64-encoded reason, lol
            goto Abort;
        }

        if (!$ScalewayServer->Delete_Server()) {
            $Return = "ERROR: Can't Reinstall OS.<br>Reason: RVJSX0RFTEVURV9GQUlMRUQ<br>Please contact customer support.";
            goto Abort;
        }

        $Snapshot_UUID = $ScalewayServer->Retrieve_Snapshot_UUID($New_OS_Name);

        if (!isValidUUID($Snapshot_UUID)) {
            $Return =  "ERROR: Invalid OS: " . $New_OS_Name;
            goto Abort;
        }

        $Server_Tags = array("ClientID: " . $ClientID,
                             "OrderID: " . $OrderID,
                             "ServiceID: " . $ServiceID);

        if ($ScalewayServer->Create_New_Server($Hostname, $Snapshot_UUID, $Server_Type, $Server_Tags, $Current_Username, $Current_Password, $Old_IPv4_UUID)) {
            $LocalAPI_Data["serviceid"] = $ServiceID;
            $LocalAPI_Data["customfields"] = base64_encode(serialize(array(
                                                                    "Server ID"=> $ScalewayServer->Server_UUID,
                                                                    "Operating System" => $New_OS_Name
                                                                    )));

            localAPI("UpdateClientProduct", $LocalAPI_Data, getAdminUserName());

            $Return = "SUCCESS: OS installed.";
        } else {
            $Return = "Failed Reason: " . $ScalewayServer->queryInfo;
        }
    } else {
        $Return = "ERROR: Invalid function request";
        goto Abort;
    }

    Abort:

    logModuleCall('Scaleway', __FUNCTION__, $RequestLog, print_r($Return, true)) ;

    return $Return;
}

/**
 * Return Username of the first WHMCS Admin, use for LocalAPI()
 *
 * @return  string  Admin username
 */
function getAdminUserName()
{
    $adminData = Capsule::table('tbladmins')
            ->where('disabled', '=', 0)
            ->first();
    if (!empty($adminData)) {
        return $adminData->username;
    } else {
        die('No admin exist. Why So?');
    }
}

/**
 * Return a readable date from Scaleway's API Date
 *
 * @param   string  $date   Date from API.
 * @return  string          Readable date.
 */
function prettyPrintDate($date)
{
    $date = date_parse($date);
    $date = sprintf(
        "%d/%d/%d %d:%d:%d UTC",
                    $date["year"],
                    $date["month"],
                    $date["day"],
                    $date["hour"],
                    $date["minute"],
                    $date["second"]
    );
    return $date;
}
