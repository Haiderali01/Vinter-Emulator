<?php
	error_reporting(E_ALL&~E_NOTICE&~E_DEPRECATED);
	//error_reporting(E_ALL);
	ini_set("display_errors", 1);

	$isEcho = 1;
	$maxWhileExecs = 10;
	$tcpipMaxLimit = 20;

	$working_dir = "/opt/vinter/php/";

	$paths_config = json_decode(file_get_contents($working_dir."config.json"));

	$app_dir = $paths_config->app_dir;
	$adbPath = $paths_config->adbPath;
	$androidViewClient_path = $paths_config->androidViewClient_path;
	$sch_dir = $paths_config->sch_dir;
	$sql_dir = $paths_config->log_dir;
	$device_logs_dir = $paths_config->device_logs_dir;
	$imagesLocation = $paths_config->imagesLocation;
	$http_imagesLocation = $paths_config->http_imagesLocation;

	include($working_dir."classes/android.vinter.driver.php");

	$script_start_time = time();
	while (1) {
		/*
		 * read schedule files
		 */
		$schedule_files = array();
		if ($handle = opendir($sch_dir)) {
		    while (false !== ($entry = readdir($handle))) {
		        if ($entry != "." && $entry != "..") {
		            $schedule_files[] = $entry;
		        }
		    }
		    closedir($handle);
		}
		/*
		 * START Schedular
		 */

		for ($i=0; $i<count($schedule_files); $i++) {
			$obj_driver = new Android_Vinter_Driver();
			$json_config = file_get_contents($sch_dir.$schedule_files[$i]);

			$configs = json_decode($json_config);


			run_script($configs->device_config_id);

			$obj_driver->setGlobalVars($configs, $adbPath, $androidViewClient_path,
									   $working_dir, $app_dir, $sql_dir, $device_logs_dir,
									   $imagesLocation, $http_imagesLocation,
									   $isEcho, $tcpipMaxLimit, $maxWhileExecs);

										   $obj_driver->logIt("Vinter is started now");
											 func_start_time();

											 $obj_driver->logIt("removing screen lock ");

 											$obj_driver->executeOnDevice("shell input keyevent 82");

												$obj_driver->waitfor(10);

											 $obj_driver->logIt("Opening veri-browser at begining");

											$obj_driver->executeOnDevice("shell am start -n acr.browser.barebones/acr.browser.lightning.activity.MainActivity");

											$obj_driver->waitfor(10);

											$obj_driver->logIt("closing tabs");

											$obj_driver->closeVeriBrowseTabs();

											$obj_driver->waitfor(10);

									   echo "opening home \n ";
			$obj_driver->executeOnDevice("shell input keyevent KEYCODE_HOME");

			$obj_driver->waitfor(10);

			$script_processed_time = time();
			$time_ellapsed = $script_processed_time - $script_start_time;

			$hours = $time_ellapsed/3600;

			$battery_output = $obj_driver->executeOnDevice("shell dumpsys battery|grep level");
			$battery = trim(str_replace("level: ", "", $battery_output))*1;

			$obj_driver->logIt("battery -> ".$battery);
			if ($battery<10) {
				$obj_driver->waitfor(3600); // an hour
			}

			exec("bash /opt/vinter/php/check_time.sh" ,$uptime_output );
		 ## for string 5:12 hours:minutes
		 //echo $uptime_output;

		 if (strpos($uptime_output[0], 'day') !== false) {

		 $uptime_output2 =explode(",",$uptime_output[0]);
		 print_r($uptime_output2);
		 {
		 if (strpos($uptime_output2[1], ':') !== false) {
		 $hours_array  = explode(":",$uptime_output2[1]);
		 print_r($hours_array);
		 $hours = $hours_array[0];
		 echo "\n hours found\n";
		 }
		 else {
		 $hours = 0;
		 echo "\n hours not found\n";
		 }

		 }

		 } else    if (strpos($uptime_output[0], ':') !== false) {
		    $hours_array  = explode(":",$uptime_output[0]);
		    //print_r($hours_array);
		    $hours = $hours_array[0];
		    echo "\n hours found\n";
		 }
		 else {
		    $hours = 0;
		   echo "\n hours not found\n";
		 }
		// echo $hours;
		 //exit;
			$obj_driver->logIt("Uptime: ".$hours);
			if ($hours>=3) {
				$obj_driver->logIt("REBOOT");
				$uptime_output = $obj_driver->executeOnDevice("reboot");

				$obj_driver->waitfor(60);
				//$obj_driver->logIt("Restart VNC");
				//$obj_driver->restartVNC();

				if ($obj_driver->configs->device_tcpip_port!=0) {
					$obj_driver->waitfor(5);
					$obj_driver->logIt("Setting TCPIP Port Tunnel");
					$obj_driver->setTCPIPTunnel();

					$obj_driver->waitfor(5);
					//$obj_driver->logIt("Restart VNC");
					//$obj_driver->restartVNC();
				}

				$obj_driver->logIt("Connecting Device");
				$obj_driver->connectDevice();

				$script_start_time = time();
			}

			$obj_driver->logIt("Setting App Path");
			$obj_driver->getAppPath();

			if ($obj_driver->configs->device_tcpip_port!=0) {
				$obj_driver->logIt("Setting TCPIP Port Tunnel");
				$obj_driver->setTCPIPTunnel();
			}

			$obj_driver->logIt("Connecting Device");
			$obj_driver->connectDevice();

			for ($j=0; $j<count($configs->actions); $j++) {
				$obj_driver->executeAction($configs->actions[$j]);
			}

			$obj_driver->executeCMD("rsync --remove-source-files -avp /opt/vinter/logs/* root@79.99.65.139:/opt/vinter/ct_logs/");
			$obj_driver->waitfor(5);
			$obj_driver->executeCMD("rsync --remove-source-files -avz -e 'sshpass -f /opt/ssh_pass ssh -p 2300' /var/www/app_ad_images/ root@social.ext.monitoringservice.co:/var/www/vinter_temp_images/");

			$obj_driver->waitfor(5);
			unset($obj_driver);
		}
		/*
		 * END Schedular
		 */
	}

	function run_script($config_id)
	{
		if(trim($config_id)=="")
		{
			return false;

		}
		else {


		$status_url = "http://social.ext.monitoringservice.co/social/script_status.php?conf_device_id=".$config_id;
			echo $status_url;

			$status_decode =  json_decode(file_get_contents($status_url));
			// echo $status_decode;
			print_r($status_decode);

			 if($status_decode->status=="1")
			 {
				 return true;
			 }
			 else
			 {

				  file_put_contents("/opt/vinter/php/tmp_log.txt", "\n script is stopped by publisher \n ", FILE_APP);

				 echo "sleep for 1 minute";
				 echo "\n";

				 //exec("pkill php");
				 //exit;
				 for($i=0;$i<60;$i++)
				 {
				 	echo "$i";
					 sleep(1);

				 }

			 }

			 run_script($config_id);
		 }

	}

	function func_start_time()
	{

		$file_start_time ="/var/www/start_time.txt";
		$start_time = date("Y-m-d H:i:s");
		echo "\n Start Time:".$start_time;
		file_put_contents($file_start_time,$start_time);
		return true;

	}

?>
