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
	$sch_dir = "/opt/vinter/schedules4/";
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
			check_screen_lock();

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

			$uptime_output = $obj_driver->executeOnDevice("shell uptime");
			$ex_uptime = explode(",", $uptime_output);
			$uptime_formated = str_replace("up time: ", "", $ex_uptime[0]);

			$ex_uptime_formated = explode(":", $uptime_formated);

			$hours = $ex_uptime_formated[0]*1;
			$obj_driver->logIt("Uptime: ".$hours);

			//to check reboot time, if hours > 6 then return 1 else return 0;
            /*$isreboot = check_reboot_time();

			//if ($hours>=6) {
			if ($isreboot == 1) {
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
			}*/

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

			//$obj_driver->executeCMD("rsync --remove-source-files -avp /opt/vinter/logs/* root@79.99.65.139:/opt/vinter/ct_logs/");

			$obj_driver->executeCMD("rsync --remove-source-files -avp -e 'ssh -o StrictHostKeyChecking=no -p 2300' /opt/vinter/logs/* root@social.ext.monitoringservice.co:/var/www/vinter_files/");

			$obj_driver->waitfor(5);
			//$obj_driver->executeCMD("rsync --remove-source-files -avp -e 'ssh -o StrictHostKeyChecking=no -p 2300' /var/www/app_ad_images/ root@social.ext.monitoringservice.co:/var/www/vinter_temp_images/");

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

				 // file_put_contents("/opt/vinter/php/tmp_log.txt", "\n script is stopped by publisher \n ", FILE_APP);

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

function check_reboot_time(){
    $file_start_time ="/var/www/reboot_time.txt";
    $start_time_str = trim(file_get_contents($file_start_time));

    $current_date_time = date("Y-m-d H:i:s");
    $start_date = new DateTime($start_time_str);
    $end_date = new DateTime($current_date_time);
    $interval = $start_date->diff($end_date);
    $hours   = $interval->format('%h');

    if($start_time_str=="")
    {
    	echo "REBOOT File NOT FOUND... | Current Time: ".$current_date_time;
        file_put_contents("/var/www/reboot_time.txt", date("Y-m-d H:i:s"));
        return 0;
    }
    else {
        echo "\nchecking time\n";
        if($hours > 6)
        {
            echo "GOING TO REBOOT | Current Time: ".$current_date_time;
            file_put_contents("/var/www/reboot_time.txt", date("Y-m-d H:i:s"));
            return 1;
        }else{
            return 0;
        }
    }
}

function check_screen_lock()
{
		exec("/home/pi/adb/adb-linuxARMv6 shell dumpsys window|grep mShowingLockscreen" , $output);
		$output_string = $output[0];

		exec("/home/pi/adb/adb-linuxARMv6 shell dumpsys display|grep mScreenState" , $output);
		$output_string2 = $output[0];
		echo "\n $output_string \n";
			echo "\n $output_string2 \n";
		if (strpos($output_string, 'mShowingLockscreen=true') !== false || strpos($output_string2, 'mScreenState=OFF') !== false) {
    echo "\n  screen is locked \n ";
		exec("/home/pi/adb/adb-linuxARMv6 shell input keyevent 26 && /home/pi/adb/adb-linuxARMv6 shell input keyevent 82 && /home/pi/adb/adb-linuxARMv6 shell input keyevent 82 && /home/pi/adb/adb-linuxARMv6 shell input keyevent 82 && /home/pi/adb/adb-linuxARMv6 shell input keyevent 66 && width=$(/home/pi/adb/adb-linuxARMv6 shell dumpsys display|grep mDisplayWidth|cut -d'=' -f2) && hieght=$(/home/pi/adb/adb-linuxARMv6 shell dumpsys display|grep mDisplayHeight|cut -d'=' -f2) && /home/pi/adb/adb-linuxARMv6 shell input touchscreen swipe $((width/2)) $((hieght*3/4)) $((width/2+20)) 40 && /home/pi/adb/adb-linuxARMv6 shell input keyevent 66");
			}
			else {
			  echo "\n  screen is un locked \n ";
			}
			sleep(2);
		return true;
}

?>
