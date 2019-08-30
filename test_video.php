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
	$videosLocation = $paths_config->videosLocation;
	$mobileVideosLocation = $paths_config->mobileVideosLocation;
	$http_imagesLocation = $paths_config->http_imagesLocation;

	include($working_dir."classes/android.vinter.driver_0.2.php");

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



//########33 code for visoe session id ##########
$s_id= $configs->device_config_id."_".time();
$date_folder = $videosLocation.date("Y-m-d")."/";
$hour_folder = $date_folder.date("H")."/";
$s_folder = $hour_folder.$s_id."/";
$configs->video_url =$s_folder ;
//########33 code for visoe session id ##########
			$obj_driver->setGlobalVars($configs, $adbPath, $androidViewClient_path,
									   $working_dir, $app_dir, $sql_dir, $device_logs_dir,
									   $imagesLocation, $http_imagesLocation,
									   $isEcho, $tcpipMaxLimit, $maxWhileExecs);

										 //### code for video ############



										 	### killing background process ###########333
											$obj_driver->logIt("killing background process");
											$kill_cmd ="kill -9 $(pgrep -f '/opt/vinter/php/video_bg.php')";
											$obj_driver->logIt($kill_cmd );
											exec($kill_cmd);
											### killing background process ###########333

							 			if (!file_exists($date_folder)) {
							 				mkdir($date_folder, 0777);

							 				$obj_driver->logIt("Date Folder ".$date_folder." CREATED!");
							 			} else {

							 				$obj_driver->logIt("Date Folder ".$date_folder." Already Exsists!");
							 			}



							 			if (!file_exists($hour_folder)) {
							 				mkdir($hour_folder, 0777);
							 				$obj_driver->logIt("Hour Fodler ".$hour_folder." CREATED!");
							 			} else {
							 				$obj_driver->logIt("Hour Fodler ".$hour_folder." Already Exsists!");
							 			}

										/*
							 			if (!file_exists($s_folder)) {
							 				mkdir($s_folder, 0777);
							 				$obj_driver->logIt("Session Fodler ".$s_folder." CREATED!");
							 			} else {
							 				$obj_driver->logIt("Session Fodler ".$s_folder." Already Exsists!");
							 			}
*/
										$obj_driver->logIt("shell rm -rf $mobileVideosLocation");
										$obj_driver->executeOnDevice("shell rm -rf $mobileVideosLocation");

							 			$obj_driver->logIt("making folder in mobile ");

							 		 $obj_driver->executeOnDevice("shell mkdir ".$mobileVideosLocation);

							 		$obj_driver->executeOnDevice("shell chmod -R 777 ".$mobileVideosLocation);

									 $obj_driver->executeOnDevice("shell mkdir ".$mobileVideosLocation.$s_id);

									$obj_driver->executeOnDevice("shell chmod -R 777 ".$mobileVideosLocation.$s_id);

							 			### code for video ############
							 			//exit;

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
			if ($hours>=6) {
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

			## call video function in background
			$obj_driver->logIt("running video background process");
			$command =  'php /opt/vinter/php/video_bg.php '.$s_id.'' . ' > /opt/vinter_video_output >&1 & echo $!; ';
			$obj_driver->logIt($command);
			$pid = exec($command, $output);
			for ($j=0; $j<count($configs->actions); $j++) {
				$obj_driver->executeAction($configs->actions[$j]);
			}

			$obj_driver->logIt("killing background process");
			$kill_cmd ="kill -9 $pid";
			$obj_driver->logIt($kill_cmd );
			exec($kill_cmd);

			$obj_driver->logIt("pulling video data");
			$pull_command =" pull $mobileVideosLocation $hour_folder";
			$obj_driver->logIt($pull_command);
			$obj_driver->executeOnDevice($pull_command);
			$obj_driver->logIt("shell rm -rf $mobileVideosLocation");
			$obj_driver->executeOnDevice("shell rm -rf $mobileVideosLocation");

			//exit;

			$obj_driver->executeCMD("rsync --remove-source-files -avz -e 'sshpass -f /opt/ssh_pass ssh -p 2300' /var/www/videos/ root@social.ext.monitoringservice.co:/var/www/vinter_temp_videos/");
			$obj_driver->waitfor(5);
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
