<?php
	error_reporting(E_ALL&~E_NOTICE&~E_DEPRECATED);
	ini_set("display_errors", 1);

	$working_dir = "/opt/vinter/php/";
	$app_dir = "/opt/vinter/apps/";
	$sch_dir = "/opt/vinter/schedules/";
	include($working_dir."classes/browseWeb.class.php");
	//include($working_dir."classes/dbase.php");
## dbase class
######## dcrypt class function ############
function decrypt($text){
	$salt ='MCP'.date("YmdddmY")."MCP";
	return trim(
					mcrypt_decrypt(
						MCRYPT_RIJNDAEL_256, $salt, base64_decode($text),MCRYPT_MODE_ECB,
						mcrypt_create_iv(
							mcrypt_get_iv_size(
								MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
								MCRYPT_RAND)
							)
						);
}
	$str = file_get_contents("http://phastapi.monitoringservice.co/get.dbase.class.php?type=ini&db=Vinter");
	$db = json_decode(decrypt($str), true);
	######## dcrypt class function ############
	global $isEcho;
	$isEcho = 1;

	$obj_browse = new BrowseWeb();
	$obj_browse->isEcho = $isEcho;
	$obj_browse->setAdbPath($argv[2]);

	$obj_db = new Dbase($db['db'], $db['host'], $db['user'], $db['password']);

	$rowsSchedular = $obj_db->select(array("id", "app_sequence_id", "device_config_id"), "tblsequence_schedular", "device_config_id=".$argv[1]." and status = 1 ", "", "execution_order ASC");

        print_r($obj_db);
	print_r($obj_browse);
	print_r($rowsSchedular);
	//exit;


	$script_start_time = time();

	echo "Deleting All FIles in Schedules Folder\n";
	exec("rm -f ".$sch_dir."*");
	// Start Schedular
	if (count($rowsSchedular)>0) {
		foreach ($rowsSchedular as $rowSchedular) {
			$rowAppSequences = $obj_db->selectSRow(array("id",
														  "app_id",
														  "name AS sequence_name",
														  "date_added"),
													"tblapp_sequences",
													"id = ".$rowSchedular->app_sequence_id);

			$app_id = $rowAppSequences["app_id"];

			$rowApp = $obj_db->selectSRow(array("id",
												  "name",
												  "package",
												  "path",
												  "app_url",
												  "date_added"),
											"tblapps",
											"id = ".$app_id);

			$rowDeviceConfigs = $obj_db->selectSRow(array("id",
														  "role_id",
														  "device_adb_id",
														  "device_web_url",
														  "device_tcpip_port",
														  "device_vnc_port",
														  "device_pvnc_port",
														  "device_pi_ssh",
														  "browser_loadTImeout",
														  "date_added"),
													"tbldevice_configs",
													"id = ".$rowSchedular->device_config_id);

			$ex_str = str_replace("http://", "", $rowDeviceConfigs["device_web_url"]);
			$ex_str = str_replace("/", "", $ex_str);
			$ex = explode(":", $ex_str);
			$vpn_ip = $ex[0];
			$device_web_port = $ex[1];

			$rowsActions = $obj_db->select(array("id",
												  "action_cmd",
												  "date_added"),
											"tblapp_actions",
											"app_sequence_id = ".$rowSchedular->app_sequence_id,
											"",
											"execution_order ASC");

			// Start Actions
			$actions = array();
			if (count($rowsActions)>0) {
				foreach ($rowsActions as $rowAction) {
					$actions[] = trim(str_replace("\n", "", str_replace("\r", "", $rowAction->action_cmd)));

				}
			}

			// End Actions
			$configs = array("app_id" => $app_id,
							 "device_config_id" => $rowSchedular->device_config_id,
							 "app_sequence_id" => $rowSchedular->app_sequence_id,
							 "app_sequence_log_id" => "",
							 "appium_session_id" => "",
							 "app_name" => $rowApp["name"],
							 "app_package" => $rowApp["package"],
							 "app_path" => $rowApp["path"],
							 "app_url" => $rowApp["app_url"],
							 "app_dir" => $app_dir,
							 "role_id" => $rowDeviceConfigs["role_id"],
							 "device_adb_id" => $rowDeviceConfigs["device_adb_id"],
							 "device_web_url" => $rowDeviceConfigs["device_web_url"],
							 "device_tcpip_port" => $rowDeviceConfigs["device_tcpip_port"],
							 "device_vnc_port" => $rowDeviceConfigs["device_vnc_port"],
							 "device_pvnc_port" => $rowDeviceConfigs["device_pvnc_port"],
							 "device_pi_ssh" => $rowDeviceConfigs["device_pi_ssh"],
							 "device_vpn_ip" => $vpn_ip,
							 "browser_loadTImeout" => $rowDeviceConfigs["browser_loadTImeout"],
							 "device_web_port" => $device_web_port,
							 "msisdn" => "",
							 "video_url" => "",
							 "actions" => $actions);

			$json_config = json_encode($configs);

			$filename = $sch_dir."app_".$app_id."_seq_".$rowSchedular->app_sequence_id.".json";
			file_put_contents($filename, $json_config);

			/*// creating object of SimpleXMLElement
			$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');

			// function call to convert array to xml
			array_to_xml($configs,$xml_data);

			//saving generated xml file;
			$result = $xml_data->asXML('/var/www/html/test.xml');
			exit; */
		}
	}
	// End Schedular

	function array_to_xml( $data, &$xml_data ) {
	    foreach( $data as $key => $value ) {
	        if( is_array($value) ) {
	            if( is_numeric($key) ){
	                $key = 'item'.$key; //dealing with <0/>..<n/> issues
	            }
	            $subnode = $xml_data->addChild($key);
	            array_to_xml($value, $subnode);
	        } else {
	            $xml_data->addChild("$key",htmlspecialchars("$value"));
	        }
	     }
	}


?>
