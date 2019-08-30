<?php
	error_reporting(E_ALL&~E_NOTICE&~E_DEPRECATED);
	//error_reporting(E_ALL);
	ini_set("display_errors", 1);

	$isEcho = $argv[3];
	$maxWhileExecs = 10;
	$tcpipMaxLimit = 20;

	$working_dir = "/opt/vinter/php/";

	$paths_config = json_decode(file_get_contents($working_dir."config_vm.json"));

	$app_dir = $paths_config->app_dir;
	$adbPath = $paths_config->adbPath;
	$androidViewClient_path = $paths_config->androidViewClient_path;
	$sch_dir = $paths_config->sch_dir;
	$sql_dir = $paths_config->log_dir;
	$device_logs_dir = $paths_config->device_logs_dir;
	$imagesLocation = $paths_config->imagesLocation;
	$http_imagesLocation = $paths_config->http_imagesLocation;
	$vnc_url = $paths_config->vnc_url;
	$device_adb_vnc_port = $paths_config->device_adb_vnc_port;
	$pvnc_port = $paths_config->pvnc_port;
	$controller_ssh_port = $paths_config->controller_ssh_port;
	$vpn_ip = $paths_config->vpn_ip;
	$controller_web_port = $paths_config->controller_web_port;
	$device_adb_id = $paths_config->device_adb_id;

	include($working_dir."classes/android.vinter.driver.php");

	$script_start_time = time();

	$obj_driver = new Android_Vinter_Driver();

	$configs = new stdClass();
	$configs->app_id = "";
	$configs->device_config_id = "";
	$configs->app_sequence_id = "";
	$configs->app_sequence_log_id = "";
	$configs->appium_session_id = "";
	$configs->app_name = "";
	$configs->app_package = "";
	$configs->app_path = "";
	$configs->app_url = "";
	$configs->app_dir = "";
	$configs->device_adb_id = $device_adb_id;
	$configs->device_web_url = "http://".$vpn_ip.":".$controller_web_port."/";
	$configs->device_tcpip_port = "";
	$configs->device_vnc_port = $device_adb_vnc_port;
	$configs->device_pvnc_port = $pvnc_port;
	$configs->device_pi_ssh = $controller_ssh_port;
	$configs->device_vpn_ip = $vpn_ip;
	$configs->browser_loadTImeout = "30";
	$configs->device_web_port = $controller_web_port;
	$configs->actions = "";

	$obj_driver->setGlobalVars($configs, $adbPath, $androidViewClient_path,
							   $working_dir, $app_dir, $sql_dir, $device_logs_dir,
							   $imagesLocation, $http_imagesLocation,
							   $isEcho, $tcpipMaxLimit, $maxWhileExecs);

	$script_processed_time = time();
	$time_ellapsed = $script_processed_time - $script_start_time;

	$obj_driver->logIt("EXECUTING -- "."BROWSER JS ".base64_decode($argv[2]));
	$response = $obj_driver->executeAction("BROWSER JS ".$argv[2]);

	echo json_encode($response);
	unset($obj_driver);
?>
