#!/usr/bin/php
<?php


//Including Art of Wifi's controller API client (https://artofwifi.net/portfolio/unifi-controller-api-client-class/)
//Seriously, it's really good. The API browser is too (https://artofwifi.net/portfolio/unifi-api-browser/)
require_once('Client.php');

/* Use standard argv strings instead of options
if (count($argv) >=4) {

    $controllerurl = "https://" . $argv[1];
    $controlleruser = $argv[2];
    $controllerpassword = $argv[3];
    $controllerversion = $argv[4];

} else {
    echo "Not enough arguments passed";
	exit(2);
}
*/



$debug = false;
$debugcode = false;

$longoptions = array(
    "portminimum:",
    "poeW:",
    "poeC:",
    "help:",
    "verbose:",
);
$options = getopt("h:c:u:p:s:t:v:m:d:",$longoptions);



//Help
if ($argv[1] == "help"){
    help();
} else {
    //Verify required information exists and set dummy variables if needed
    //Username
    if (isset($options["u"])){
        $controlleruser = $options["u"];
    } else {
        echo "Valid username is required (option \"-u\")\n";
        exit (2);
    }
    //Password
    if (isset($options["p"])){
        $controllerpassword = $options["p"];
    } else {
        echo "Valid password is required (option \"-p\")\n";
        exit (2);
    }
    //Controller IP - port 8443 or 443 may be required depending on a) the device hosting the controller software and b) the controller version
    if (isset($options["c"])){
        $controllerurl = "https://" . $options["c"];
    } else {
        echo "Controller IP address or FQDN is required (option \"-c\")\n";
        exit (2);
    }
    //Controller Version
    if (isset($options["v"])){
        $controllerversion = $options["v"];
    } else {
        $controllerversion = "5.12.67"; //Arbitrary version to continue
    }
    //Device Types
    if (isset($options["t"])){
        $typematch = $options["t"];
        $typematcharray = array($typematch);
    } else {
        $typematcharray = array("ugw","udm","usw","uap");
    }
    //Target Device
    if (isset($options["d"])){
        $hostip = $options["d"];
    } else {
        $hostip = '192.168.1.1';
    }
    //Site ID
    if (isset($options["s"])){
        $site_id = $options["s"];
        $namearray[] = $site_id;
        $descriptionarray[]='';
    } else {
        $site_id = "default";
        $descriptionarray[]='';
        if ($debugcode == "true"){
            echo "\nDEBUG: site_id: " . $site_id;
        }
        list($descriptionarray,$namearray) = returnlistofsites($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$debugcode);
        if ($debugcode == "true"){
            echo "\nDEBUG: site_id: " . $site_id;
            print_r($descriptionarray);
            print_r($namearray);
        }
        //echo var_dump($descriptionarray);
        //echo var_dump($namearray);
    }
}

if (strcasecmp($options["m"],'check_all_devices') == 0){
    $run = 'check_all_devices';
} else if (strcasecmp($options["m"],'check_device') == 0){
    $run = 'check_device';
    } else if (strcasecmp($options["m"],'check_switch') == 0){
        $run = 'check_switch';
    }else if (strcasecmp($options["m"],'check_ap') == 0){
        $run = 'check_ap';
    }else if (strcasecmp($options["m"],'check_alarms') == 0){
        $run = 'check_alarms';
    }else {
    echo "No check defined. Define a check with -m . \n";
    exit(3);
    //$run = 'check_devices';
}

if (isset($options["poeC"])){
    $poeC = $options["poeC"];
}
if (isset($options["poeW"])){
    $poeW = $options["poeW"];
}
if (isset($options["portminimum"])){
    $portminimum = $options["portminimum"];
}



$errors = 0;
//check_devices
function check_allthe_devices($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    if ($debugcode == "true"){
        echo "\nDEBUG: Running \"check_all_devices\" function\n";
    }
    
    /**
    * initialize the UniFi API connection class and log in to the controller and do our thing
     */
    $performancedata[]= '';
    foreach($namearray as $key => $value){ //For each site
        $site_id = $value;
        $devicesdown = 0;
        $numberofdevices = 0;
        $sitedescription = $descriptionarray[$key];
        $devicestring[]= $sitedescription;
        if ($debugcode == "true"){
            echo "\nDEBUG: site_id: " . $site_id;
            echo "\nDEBUG: " . $controllerurl;
            echo "\nDEBUG: " . $controlleruser;
            echo "\nDEBUG: " . $controllerpassword;
            echo "\nDEBUG: " . $controllerversion;
            print_r($namearray);
        }
        $unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
        $set_debug_mode   = $unifi_connection->set_debug($debug);
        $loginresults     = $unifi_connection->login();
        $evenmoredata     = $unifi_connection->list_devices();

    /**
    * provide feedback in json format
    **/

        $jsontext = json_encode($evenmoredata, JSON_FORCE_OBJECT);
        //echo $jsontext;
        $jsontext = json_decode($jsontext, true);

        foreach($typematcharray as $value){ //For each device type specified
            $typematch = $value; //Because I like to copy and paste lines a lot
            foreach($jsontext as $key => $value) { //Examine each device
                if(strcmp($jsontext[$key]["type"], $typematch) == 0){ //Does the device match the device type?
                    //Reset Variables
                    $model = '';
                    $type = '';
                    $registered = '';
                    $ipaddress = '';
                    $mac = '';
                    $name = '';
                    //Get Data
                    $model = $jsontext[$key]["model"]; // i.e. U7NHD
                    $type =  $jsontext[$key]["type"]; // i.e. uap
                    $ipaddress =  $jsontext[$key]["ip"]; // i.e. 192.168.1.7
                    $mac =  $jsontext[$key]["mac"]; // i.e. e0:63:da:21:79:ba
                    if(isset($jsontext[$key]["name"])){
                        $name =  $jsontext[$key]["name"]; // i.e. the Alias
                    }   
                    $registered =  $jsontext[$key]["state"]; // i.e. 1 or 0
                    if ($registered == 1){
                        $registered = "registered    ";
                    } else {
                        $registered = "not registered";
                        $errors++;
                        $devicesdown++;
                    }
                    //echo $type . " | " . $registered . " | " . $ipaddress . " | " . $mac . " | " . $model . " | " . $name . "\n";
                    $devicestring[]= "" . $type . " " . $registered . " " . $ipaddress . " " . $mac . " " . $model . " " . $name;
                } else {
                 };
            };

        };
        $numberofdevices = $key + 1;
        $sitedescription = strtr($sitedescription,[' '=>'']);
        $performancedata[] = "DevicesOffline:" . $sitedescription . "=" . $devicesdown . ";1" . ";2" . ";;" . $numberofdevices;

        //$performancedata = '';

    }
    if ($errors == 0){
        $initialline[] = 'OKAY - All devices are currently up.';
        $content = $devicestring;
    } else {
        $initialline[] = 'CRITICAL - There are ' . $errors . ' devices not connected.';
    }
    return array($initialline,$errors,$content,$performancedata,$jsontext);
};


function check_device_common($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    if ($debugcode == "true"){
        echo "\nDEBUG: Running \"check_device\" function\n";
    }

    if ($debugcode == "true"){
        echo "\nDEBUG: site_id: " . $site_id;
        echo "\nDEBUG: " . $controllerurl;
        echo "\nDEBUG: " . $controlleruser;
        echo "\nDEBUG: " . $controllerpassword;
        echo "\nDEBUG: " . $controllerversion;
        print_r($namearray);
    }
    $unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
    $set_debug_mode   = $unifi_connection->set_debug($debug);
    $loginresults     = $unifi_connection->login();
    $evenmoredata     = $unifi_connection->list_devices();
    $jsontext = json_encode($evenmoredata, JSON_FORCE_OBJECT);
   //echo $jsontext;
    $jsontext = json_decode($jsontext, true);
    foreach($jsontext as $key => $value) { //Examine each device
    $errors = 0;
    $cpuWthreshold = 90;
    $cpuCthreshold = 95;
    $memWthreshold = 80;
    $memCthreshold = 90;
    //$valuetable = array();
    //$porttableset = isset($jsontext[$key]["port_table"]["0"]["ip"]);
        if(strcmp($jsontext[$key]["ip"], $hostip) == 0){ //Does the device match the device type?
           // echo "Found " . $hostip . "\n";
            //Get Data
            if(isset($jsontext[$key]["type"])){
                $type =  $jsontext[$key]["type"]; // i.e. uap
                $DEVICEINFO[] = "Type: " . $type;
            }  
            if(isset($jsontext[$key]["name"])){
                $name =  $jsontext[$key]["name"]; // i.e. the Alias
                $DEVICEINFO[] = "Alias: " . $name;
            }  
            if(isset($jsontext[$key]["model"])){
                $model =  $jsontext[$key]["model"]; // i.e. U7NHD
                $DEVICEINFO[] = "Model: " . $model;
            }   
            if(isset($jsontext[$key]["state"])){ 
                $registered =  $jsontext[$key]["state"]; // i.e. 1 or 0
                if ($registered == 1){
                    $registered = "UP";
                } else {
                    $registered = "DOWN";
                    $errors++;
                    $errorlog[] = "CRITICAL - Device is " . $DOWN . "!";
                }
                $DEVICEINFO[] = "State: " . $registered;
            }
            if(isset($jsontext[$key]["upgradable"])){ 
                $upgradeable =  $jsontext[$key]["upgradable"]; // i.e. 1 or 0
                if ($upgradeable == false){
                    $upgradeable = "No";
                } else {
                    $upgradeable = "Yes";
                }
                $DEVICEINFO[] = "Upgrade Available: " . $upgradeable;
            }
            if(isset($jsontext[$key]["ip"])){
                $ipaddress =  $jsontext[$key]["ip"]; // i.e. 192.168.1.7
                $DEVICEINFO[] = "IP Address: " . $ipaddress;
            } 
            if(isset($jsontext[$key]["mac"])){
                $mac =  $jsontext[$key]["mac"]; // i.e. e0:63:da:21:79:ba
                $DEVICEINFO[] = "MAC Address:: " . $mac;
            } 
            //echo $type . " | " . $registered . " | " . $ipaddress . " | " . $mac . " | " . $model . " | " . $name . "\n";
            //$DEVICEINFO = "\nAlias: " . $name . "Type: " . $type . "\nModel: " . $model . "\nState: " . $registered . "\nIP Address: " . $ipaddress . "\nMAC Address: " . $mac;
            if (isset($jsontext[$key]["system-stats"]["cpu"])){
                $cpu = $jsontext[$key]["system-stats"]["cpu"];
                if ($cpu > $cpuCthreshold){
                    $errors = $errors + 2;
                    $valuetable[] = "CPU=" . $cpu . "%;" . $cpuWthreshold . ";" . $cpuCthreshold ;
                    $cpu = sprintf("%.2f%%", $cpu);
                    $errorlog[] = "CRITICAL - CPU Utilization is " . $cpu . "!";
                    
                } else if($cpu > $cpuWthreshold){
                    $errors = $errors + 1;
                    $valuetable[] = "CPU=" . $cpu . "%;" . $cpuWthreshold . ";" . $cpuCthreshold ;
                    $cpu = sprintf("%.2f%%", $cpu);
                    $errorlog[] = "WARNING - CPU Utilization is " . $cpu . "!";
                } else {
                    $valuetable[] = "CPU=" . $cpu . "%;" . $cpuWthreshold . ";" . $cpuCthreshold ;
                }
            }
            if (isset($jsontext[$key]["system-stats"]["mem"])){
                $mem = $jsontext[$key]["system-stats"]["mem"];
                if ($mem > $memCthreshold){
                    $errors = $errors + 2;
                    $valuetable[] = "MEM=" . $mem . "%;" . $memWthreshold . ";" . $memCthreshold;
                    $mem = sprintf("%.2f%%", $mem);
                    $errorlog[] = "CRITICAL - MEM Utilization is " . $mem . "!";
                    
                } else if($mem > $memWthreshold){
                    $errors = $errors + 1;
                    $valuetable[] = "MEM=" . $mem . "%;" . $memWthreshold . ";" . $memCthreshold;
                    $mem = sprintf("%.2f%%", $mem);
                    $errorlog[] = "WARNING - MEM Utilization is " . $mem . "!";
                } else {
                    $valuetable[] = "MEM=" . $mem . "%;" . $memWthreshold . ";" . $memCthreshold;
                }
                //echo sprintf("%.2f%%", $mem);
            }

            //exit (0);            
        } 
    };
    if($errors == 0){
        $initialline[] = 'OKAY - No problems found';
    }else{
        $initialline = $errorlog;
    }

    $content = $DEVICEINFO;
    $performancedata = $valuetable;
    //pause();
    //returndata($initialline,$errors,$content,$performancedata);
    return array($initialline,$errors,$content,$performancedata,$jsontext);
   // exit(1);
};

function check_switch_stats($options,$jsontext,$initialline,$errors,$content,$performancedata,$hostip){
    //Check if device is a switch
    if (strpos($content[0], 'usw') === false) {
        $errors = 1;
        unset ($initialline);
        $initialline[]='WARNING - This device is not a Unifi switch.';
        unset($performancedata);
        $performancedata[]='';
        unset($content);
        $content[]='';
    }
    else{
        $poewattagedefault = 220;
        if (isset($options["poeC"])){
            $poeC = $options["poeC"];
        }else{
            $poeC = $poewattagedefault * .95;
        }
        if (isset($options["poeW"])){
            $poeW = $options["poeW"];
        }else{
            $poeW = $poewattagedefault * .85;
        }
        if (isset($options["portminimum"])){
            $portminimum = $options["portminimum"];
        }else{
            $initialline[]='WARNING - option portminimum is not properly defined. This may skewe performance data. The default for a US-24-250W-Gen1 will be used (220)';
            $portminimum = 48;
        }
        foreach($jsontext as $key => $value) {
            if(strcmp($jsontext[$key]["ip"], $hostip) == 0){ 
                if(isset($jsontext[$key]["stp_version"])){
                    $temp_var =  $jsontext[$key]["stp_version"]; // i.e. rstp
                    $content[] = "STP Version: " . $temp_var;
                }
                if(isset($jsontext[$key]["stp_priority"])){
                    $temp_var =  $jsontext[$key]["stp_priority"]; // i.e. 32768
                    $content[] = "STP Priority: " . $temp_var;
                }
                //if(isset($jsontext[$key]["jumboframe_enabled"])){
                //    $temp_var =  $jsontext[$key]["jumboframe_enabled"]; // i.e. true
                //    $content[] = "Jumbo Frames Enabled: " . $temp_var;
                //}
                //if(isset($jsontext[$key]["flowctrl_enabled"])){
                //    $temp_var =  $jsontext[$key]["flowctrl_enabled"]; // i.e. false
                //    $content[] = "Flow Control Enabled: " . $temp_var;
                //}
                if(isset($jsontext[$key]["port_table"])){
                    $temp_var =  $jsontext[$key]["port_table"]; // i.e. 32768
                    $port_id = "#";
                    $linkstatus = "Link";
                    $protocolstatus = "Prot";
                    $port_poe = "PoE";
                    $autoneg = "Auto/Man";
                    $full_duplex = "Duplex";
                    $speed = "Speed";
                    $name = "Name";
                    $lldp_name = "LLDP Name";
                    $poewattageused = 0;
                    $downports = 0;
                    $upports = 0;
                    //echo "\n" . str_pad($port_id,3) . " " . str_pad($linkstatus,4) . " " . str_pad($protocolstatus,4) . " " . str_pad($port_poe,4) . " " . str_pad($autoneg,8) . " " . str_pad($full_duplex,7) . " " . str_pad($speed,6) . " " . str_pad($name,15) . " " . str_pad($lldp_name,15);
                    //echo "\n";
                    $content[] = str_pad($port_id,3) . " " . str_pad($linkstatus,4) . " " . str_pad($protocolstatus,4) . " " . str_pad($port_poe,4) . " " . str_pad($autoneg,8) . " " . str_pad($full_duplex,7) . " " . str_pad($speed,6) . " " . str_pad($name,15) . " " . str_pad($lldp_name,15);
                    foreach($jsontext[$key]["port_table"] as $key2 => $value){

//Insert IF/else statement for identifying if port is actually being used or not

                        if(isset($jsontext[$key]["port_table"][$key2]["port_idx"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["port_idx"]; // i.e. 1
                            $port[] = $temp_var;
                            $port_id = $temp_var;
                        } else {
                            $port_id = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["enable"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["enable"]; // i.e. 1
                            $port[] = $temp_var;
                            if($temp_var == 1){
                                $temp_var = "Up";
                            } else {
                                $temp_var = "Down";
                            }
                            $linkstatus = $temp_var;
                        } else {
                            $linkstatus = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["up"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["up"]; // i.e. 1
                            if($temp_var == 1){
                                $temp_var = "Up";
                            } else {
                                $temp_var = "Down";
                            }
                            $protocolstatus = $temp_var;
                        } else {
                           //$protocolstatus = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["port_poe"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["port_poe"]; // i.e. 1
                            $port[] = $temp_var;
                            if($temp_var == 1){
                                $temp_var = "Good";
                            } else {
                                $temp_var = "";
                            }
                            $port_poe = $temp_var;
                        } else {
                            $port_poe = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["autoneg"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["autoneg"]; // i.e. 1
                            $port[] = $temp_var;
                            if($temp_var == 1){
                                $temp_var = "Auto";
                            } else {
                                $temp_var = $temp_var;
                            }
                            $autoneg = $temp_var;
                        } else {
                            $autoneg = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["full_duplex"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["full_duplex"]; // i.e. 1
                            $port[] = $temp_var;
                            if($temp_var == 1){
                                $temp_var = "Full";
                            } else {
                                $temp_var = "HALF";
                            }
                            $full_duplex = $temp_var;
                        } else {
                            $full_duplex = "???";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["speed"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["speed"]; // i.e. 1
                            $port[] = $temp_var;
                            $speed = $temp_var;
                        } else {
                            $speed = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["name"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["name"]; // i.e. 1
                            $temp_var_len = strlen($temp_var);
                            if ($temp_var_len < 15){
                                $temp_var = str_pad($temp_var,15);
                            } else {
                                $temp_var = substr($temp_var,-15,15);
                            }
                            $port[] = $temp_var;
                            $name = $temp_var;
                        } else {
                            $name = "";
                        }
                        if(isset($jsontext[$key]["port_table"][$key2]["lldp_table"][0]["lldp_system_name"])){
                            $temp_var = $jsontext[$key]["port_table"][$key2]["lldp_table"][0]["lldp_system_name"]; // i.e. 1
                            $temp_var_len = strlen($temp_var);
                            if ($temp_var_len < 15){
                                $temp_var = str_pad($temp_var,15);
                            } else {
                                $temp_var = substr($temp_var,-15,15);
                            }
                            $port[] = $temp_var;
                            $lldp_name = $temp_var;
                        } else {
                            $lldp_name = "";
                        }
                        //Logificalistically figure out stuff about the switchport
                        if($speed == "0"){
                            $full_duplex = "";
                            $linkstatus = "Down";
                            //echo $speed . $linkstatus . $protocolstatus;
                            $speed = "";
                        }
                        //echo "\n" . str_pad($port_id,3) . " " . str_pad($linkstatus,4) . " " . str_pad($protocolstatus,4) . " " . str_pad($port_poe,4) . " " . str_pad($autoneg,8) . " " . str_pad($full_duplex,7) . " " . str_pad($speed,6) . " " . str_pad($name,15) . " " . str_pad($lldp_name,15);
                        //pause();
                        if(strcmp($linkstatus,"Up") == 0){
                            if (strcmp($protocolstatus,"Down") == 0){
                                if(isset($jsontext[$key]["port_table"][$key2]["media"])){
                                    if(isset($jsontext[$key]["port_table"][$key2]["media"]) == "SFP"){
                                        if(isset($jsontext[$key]["port_table"][$key2]["sfp_found"]) != "TRUE"){
                                            $downports++;
                                        }
                                    }else if(isset($jsontext[$key]["port_table"][$key2]["media"]) == "SFP+"){
                                        if(isset($jsontext[$key]["port_table"][$key2]["sfp_found"]) != "TRUE"){
                                            $downports++;
                                        }
                                    }
                                }else{
                                    $downports++;
                                }
                                
                                //echo "\n" . $linkstatus . $protocolstatus;
                            }else{
                                $upports++;
                            }
                        }

                        if($port_poe == "Good"){
                            if(isset($jsontext[$key]["port_table"][$key2]["poe_power"])){
                                $port_poe = $jsontext[$key]["port_table"][$key2]["poe_power"];
                                if ($port_poe <= 0){
                                    $port_poe = "";
                                }else{
                                    $poewattageused = $poewattageused + $port_poe;
                                }
                            }
                        }else{
                            $port_poe = "";
                        }
                        //echo "\n" . str_pad($port_id,3) . " " . str_pad($linkstatus,4) . " " . str_pad($protocolstatus,4) . " " . str_pad($port_poe,4) . " " . str_pad($autoneg,8) . " " . str_pad($full_duplex,7) . " " . str_pad($speed,6) . " " . str_pad($name,15) . " " . str_pad($lldp_name,15);
                        $content[] = str_pad($port_id,3) . " " . str_pad($linkstatus,4) . " " . str_pad($protocolstatus,4) . " " . str_pad($port_poe,4) . " " . str_pad($autoneg,8) . " " . str_pad($full_duplex,7) . " " . str_pad($speed,6) . " " . str_pad($name,15) . " " . str_pad($lldp_name,15);
                    }
                }
            }
        } 
    //echo $key2;
    //echo $downports;
    $portsWthreshold = $portminimum - 1;
    $portsCthreshold = $portminimum - 2;
    $downports = $portminimum - $upports;
    $performancedata[] = "PortsUp=" . $upports . ";;;" . "1" . ";" . "$port_id";
    $performancedata[] = "PortsDown=" . $downports . ";1;2;" . "1" . ";" . "$port_id";
    $performancedata[] = "WattageUsed=" . $poewattageused . "W;" . $poeW . ";" . $poeC;
    }
    return array($initialline,$errors,$content,$performancedata);
}

function check_ap_stats($options,$jsontext,$initialline,$errors,$content,$performancedata,$hostip){
    if (strpos($content[0], 'uap') === false) {
        $errors = 1;
        unset ($initialline);
        $initialline[]='WARNING - This device is not a Unifi Access Point.';
        unset($performancedata);
        $performancedata[]='';
        unset($content);
        $content[]='';
    }else{





    }

    return array($initialline,$errors,$content,$performancedata);

}

function analyze_alarms ($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode,$options){
    if ($debugcode == "true"){
        echo "\nDEBUG: Running \"analyze_alarms\" function\n";
    }
    if (isset($options["verbose"])){
        $verbose = $options["verbose"];
    }else{
        $verbose = false;
    }
    $totalalarms = 0;
    $content[]='';
    /**
    * initialize the UniFi API connection class and log in to the controller and do our thing
     */
    $errors = 0;
    $performancedata[]= '';
    foreach($namearray as $key => $value){ //For each site
        $totalalarms = 0;
        $site_id = $value;
        $sitedescription = $descriptionarray[$key];
        if ($verbose == "true"){
            $content[]= $sitedescription;
        }
        $devicestring[]= $sitedescription;
        if ($debugcode == "true"){
            echo "\nDEBUG: site_id: " . $site_id;
            echo "\nDEBUG: " . $controllerurl;
            echo "\nDEBUG: " . $controlleruser;
            echo "\nDEBUG: " . $controllerpassword;
            echo "\nDEBUG: " . $controllerversion;
            print_r($namearray);
        }
        $unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
        $set_debug_mode   = $unifi_connection->set_debug($debug);
        $loginresults     = $unifi_connection->login();
        $evenmoredata     = $unifi_connection->list_alarms();

    /**
    * provide feedback in json format
    **/

        $jsontext = json_encode($evenmoredata, JSON_FORCE_OBJECT);
        //echo $jsontext;
        $jsontext = json_decode($jsontext, true);
        foreach($jsontext as $key => $value) {
            if(isset($jsontext[$key]["archived"])){
                $archived = $jsontext[$key]["archived"];
                if ($archived == false){
                    $alarmmessage =  $jsontext[$key]["msg"];
                    $alarmtime = $jsontext[$key]["datetime"];
                    $alarmtime = $jsontext[$key]["datetime"];
                    if ($verbose == true){
                        $content[]= $alarmtime . $alarmmessage;
                    }
                    $totalalarms++;
                    $errors++;
                }else{
                   
                }
            }
        }
        $performancedata[] = $sitedescription . "Alarms=" . $totalalarms . ";;;" . "0";
    }
    //echo $errors;
    //pause();
    if ($errors == 1){
        $initialline[]='WARNING - There is currently ' . $errors . ' unacknowledged Unifi alarm.';
    }else if($errors >= 2){
        $initialline[]='CRITICAL -  There are multiple unacknowledged alarms (' . $errors . ')';
    }else{
        $initialline[]='There are no alarms! Yay!!!';
    }
    return array($initialline,$errors,$content,$performancedata);
}



    
//Return list of all sites on controller
function returnlistofsites($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$debugcode){
    if ($debugcode == "true"){
        echo "\nDEBUG: Running \"returnlistofsites\" function\n";
    }
    $unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
    $set_debug_mode   = $unifi_connection->set_debug($debug);
    $loginresults     = $unifi_connection->login();
    $sitelistjson     = $unifi_connection->list_sites();
    $jsontext = json_encode($sitelistjson, JSON_FORCE_OBJECT);
    $jsontext = json_decode($jsontext, true);
    foreach($jsontext as $key => $value) {
        $desc = $jsontext[$key]["desc"]; //Manually entered description
        $descriptionarray[] = $desc;
        $name = $jsontext[$key]["name"]; //Short description name (automatically configured)
        $namearray[] = $name;
    }
    return array($descriptionarray,$namearray);
}

//Pauses Script (used for troubleshooting)
function pause(){
    echo "Paused. Continue?";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line) != 'y'){
        echo "ABORTING!\n";
        exit;
    }
    fclose($handle);
    echo "\n"; 
    echo "Continuing...\n";
}

//Outputs Data in NEMS
function returndata($initialline,$errors,$content,$performancedata){
 
    //foreach($initialline as $value){
    //    echo $value;
    //    //echo "\n";
    //}
    echo $initialline[0];

    echo "\n";
    foreach($content as $value){
        echo $value;
        echo "\n";
    }
    echo "\n |";
    foreach($performancedata as $key => $value){
        echo $value;
        echo "\n";
    }
    
    if($errors == 0){
        exit(0);
    }else if($errors == 1){
        exit(1);
    }else{
        exit(2);
    }
    exit(3);
};



//This line executes the correct check_function based on the -m switch
$run($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode);


function check_all_devices($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    list($initialline,$errors,$content,$performancedata,$jsontext) = check_allthe_devices($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode);
    returndata($initialline,$errors,$content,$performancedata);
    exit(3);
}

function check_device($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    list($initialline,$errors,$content,$performancedata,$jsontext) = check_device_common($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode);
    returndata($initialline,$errors,$content,$performancedata);
    exit(3);
}

function check_switch($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    list($initialline,$errors,$content,$performancedata,$jsontext) = check_device_common($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode);
    list($initialline,$errors,$content,$performancedata) = check_switch_stats($options,$jsontext,$initialline,$errors,$content,$performancedata,$hostip);
    returndata($initialline,$errors,$content,$performancedata);
    exit(3);
}

function check_ap($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    list($initialline,$errors,$content,$performancedata,$jsontext) = check_device_common($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode);
    list($initialline,$errors,$content,$performancedata) = check_ap_stats($options,$jsontext,$initialline,$errors,$content,$performancedata,$hostip);
    returndata($initialline,$errors,$content,$performancedata);
    exit(3);
}

function check_alarms($options,$descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode){
    list($initialline,$errors,$content,$performancedata) = analyze_alarms($descriptionarray,$namearray,$controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, $debug,$typematcharray,$errors, $hostip,$debugcode,$options);
    returndata($initialline,$errors,$content,$performancedata);
    exit(3);
}

function help(){
    echo "\n
    check_unifi
    v1.0
    
    Description: 
    Based off of the Art Of Wifi's Unifi API Client (Client.php). Used to access the Unifi API to check and parse multiple pieces of information for use in Nagios
    monitoring systems.

    Requirements: Requires Art Of Wifi's Client.php in the same directory to work

    Options:
        help            |   You're here!
        --help          |   Also the above.
        -c              |   Controller IP or FQDN. Do not include https://. 
                        |       Based on the controller version or hosting device, you may need to include the port (i.e. :8443 or :443)
        -u              |   Valid Unifi controller username
        -p              |   Valid Unifi controller password (it'd be nice if it pairs with the username...just saying)
        -m              |   The check to be performed
        [-s]            |   The site to look in. If left blank, the \"default\" site will be used initially 
                        |       and any sites located on the same controller will also be searched.
        [-t]            |   Device type to look for. Default will look at all of the devices listed below:
                        |       ugw Unifi Gateway (includes all USGs)
                        |       udm Unifi Dream Machine (includes UDMP and UDM)
                        |       usw Unifi Switches (includes all switches including gen2 and Pro)
                        |       uap Unifi APs (all models)
        [-v]            |   Controller version. Defaults to a random, modern controller. We're not searching for anything oddly 
                        |       controller specific here but if you feel the need to add your specific version, go for it! 
        [-d]            |   The target node.
        [--verbose]     |   Output large amounts of data in the alerts (check_alarms)
        [--portminimum] |   The minimum number of ports which should be up (check_switch)
        [--poeW]        |   The warning threshold (Watts) for PoE (check_switch)
        [--poeC]        |   The critical threshold (Watts) for PoE (check_switch)


        Current Check Commands:

        check_all_devices
        check_device
        check_switch
        check_ap
        check_alarms

          
    Usage Example:
    
    ~> check_unifi_devices.php -c 192.168.1.1:443 -u username -p pa33w0rd -m check_all_devices -t uap

    Returned Information Example:

    SiteName
    type| registered     | IP address   | Mac Address       | Model    | Alias
    uap | not registered | 192.168.1.56 | ab:cd:ef:12:34:56 | U7PG2    | AP3 - Hall North

    If any devices return as not registered, the script will exit in a critical state.

    Happy Monitoring!
    ~~~sqkysqnt~~~
    
    \n";
    exit(0);
}

?>