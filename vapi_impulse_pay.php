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
	$vnc_url = $paths_config->vnc_url;
	$device_adb_vnc_port = $paths_config->device_adb_vnc_port;
	$pvnc_port = $paths_config->pvnc_port;
	$controller_ssh_port = $paths_config->controller_ssh_port;
	$vpn_ip = $paths_config->vpn_ip;
	$controller_web_port = $paths_config->controller_web_port;
	$device_adb_id = $paths_config->device_adb_id;

	include($working_dir."classes/android.vinter.driver.php");
	
	while(1)
	{
		
		
	
	$script_start_time = time();

	$obj_driver = new Android_Vinter_Driver();
	
	$obj_driver->isEcho = 1;

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
	
	//echo $configs->device_vnc_port;
	//exit;

	$obj_driver->setGlobalVars($configs, $adbPath, $androidViewClient_path,
							   $working_dir, $app_dir, $sql_dir, $device_logs_dir,
							   $imagesLocation, $http_imagesLocation,
							   $isEcho, $tcpipMaxLimit, $maxWhileExecs);

	$script_processed_time = time();
	$time_ellapsed = $script_processed_time - $script_start_time;
	
	$session_id = time();
	
	$json_location = "/opt/impulse_test_json/";
	
	
	echo "opening home \n ";
	 $obj_driver->executeOnDevice("shell input keyevent KEYCODE_HOME");
	 
	 $obj_driver->waitfor(10);
	
	
	 $url = "http://secure.impulsepay.com/channel?ThreeTest=true";
		//$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".base64_decode($argv[2]));
		
		echo "opening browser";
		$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".$url);
		
		echo "waiting 10 seconds  \n ";
		
		 $obj_driver->waitfor(10);
		 
		 echo "taking first image   \n ";
		 
		$image_url_1 =  $obj_driver->takeSaveScreenshot();
		
		
		
	
		
		
		/*echo "opening url again  ";
		
		$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".$url);
		
		
		 $obj_driver->waitfor(10);
		 
		 echo "taking second  image   \n ";
		 
		$image_url_1_2 =  $obj_driver->takeSaveScreenshot();
		
		*/
		
	
	
	opening_sim2($obj_driver);
	
		aero_mode($obj_driver);
		
	
	echo "opening url again  ";
		
	$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".$url);
		
		echo "waiting 10 seconds  \n ";
		
		 $obj_driver->waitfor(10);
		 
		 echo "taking first image   \n ";
		 
		$image_url_2 =  $obj_driver->takeSaveScreenshot();
		
		
		
		/*aero_mode($obj_driver);
		
		
		echo "opening url again  ";
		
		$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".$url);
		
		
		 $obj_driver->waitfor(10);
		 
		 echo "taking second  image   \n ";
		 
		$image_url_2_2 =  $obj_driver->takeSaveScreenshot();*/
		
		
	
	echo  "\n" ;
echo "closing crontab \n";
$obj_driver->closeChromeTabs();

	
	opening_sim1($obj_driver);
	
	
	$obj_driver->executeCMD("rsync --remove-source-files -avp -e ssh /var/www/app_ad_images/* -e ssh root@79.99.65.139:/var/www/app_ad_images/");
	
	
	echo $image_url_1;
	
	 echo "\n ";
	
	
	echo $image_url_2;
	 echo "\n ";
	
	
	echo $session_id;
	 echo "\n ";
	
	
	 
	 	$impulse_array_array =array(
	"image_url_1"=> $image_url_1 , 
	"image_url_2" =>$image_url_2 ,
	"session_id" => $session_id , 
	"record_date" => date("Y-m-d H:i:s"),
	); 
	
	
	
	

$file_name =$json_location.$session_id.".json";

	###### writing file ##############3

$myfile = fopen($file_name, "w");
$txt = json_encode($impulse_array_array);
fwrite($myfile, $txt);
fclose($myfile);


###### writing file ##############3



$obj_driver->executeCMD("rsync --remove-source-files -avp -e ssh /opt/impulse_test_json/* -e ssh root@79.99.65.139:/opt/impulse_test/");

$obj_driver->waitfor(3600); // an hour




	}


	 
	 //exit;
	 
	 function opening_sim1($obj_driver)
	 {
	 	
			echo "opening setting \n ";
	 $obj_driver->executeOnDevice("shell am start -n com.android.settings/.NetworkManagement");
	 
	 
	 $obj_driver->waitfor(20);
	 
	 echo "clicking tap \n ";
	 $obj_driver->executeAction("TAP 425,168");
	 
	
	
	
	 
	  $obj_driver->waitfor(10);
	  
	  echo "closing sim 2 \n ";
	
	 $obj_driver->executeAction("TAP 417,282");
	 
	 
	   $obj_driver->waitfor(10);
	   
	  
	  echo "checking labels Data service network  \n ";
	
	$obj_driver->executeAction('BUTTON Label="Data service network" Action="click"');
	
	
	   echo "waitng 10 seconds  \n ";
	$obj_driver->waitfor(10);
	
	
	   echo "Clicking Sim 1 \n ";
	
		$obj_driver->executeAction('BUTTON Label="SIM 1" Action="click"');
		
		
		   echo " waitng 30 seconds  \n ";
		   
		$obj_driver->waitfor(30);
	
	
	   echo "Clicking yes \n  ";
			 
		$obj_driver->executeAction('BUTTON Label="Yes" Action="click"');
		
				
	$obj_driver->waitfor(10);
	
	
	 }
	 
	 
	 
	 function opening_sim2($obj_driver)
	 
	  {
	 	
			echo "opening setting \n ";
	 $obj_driver->executeOnDevice("shell am start -n com.android.settings/.NetworkManagement");
	 
	 
	 $obj_driver->waitfor(20);
	 
	 echo "opening  sim 2 \n ";
	
	 $obj_driver->executeAction("TAP 417,282");
	 
	 
	  $obj_driver->waitfor(10);
	  
	   echo "closing sim 1  \n ";
	 $obj_driver->executeAction("TAP 425,168");
	 
	 
	 
	   $obj_driver->waitfor(10);
	   
	  
	  echo "checking labels Data service network  \n ";
	
	$obj_driver->executeAction('BUTTON Label="Data service network" Action="click"');
	
	
	   echo "waitng 10 seconds  \n ";
	$obj_driver->waitfor(10);
	
	
	   echo "Clicking Sim 2 \n ";
	
		$obj_driver->executeAction('BUTTON Label="Sim 2" Action="click"');
		
		
		   echo " waitng 30 seconds  \n ";
		   
		$obj_driver->waitfor(30);
	
	
	   echo "Clicking yes \n  ";
			 
		$obj_driver->executeAction('BUTTON Label="Yes" Action="click"');
		
				
	$obj_driver->waitfor(10);
	
	
	 }
	 
	 
	 
	 function aero_mode($obj_driver)
	 {
	 		echo "enabling aero plane mode  \n " ;
			 
			 $obj_driver->executeOnDevice("shell settings put global airplane_mode_on 1");
			 
			 
			 
			 $obj_driver->executeOnDevice("shell am broadcast -a android.intent.action.AIRPLANE_MODE");


			$obj_driver->waitfor(10);

			echo "disabling aero plane mode  \n " ;
			 
			 $obj_driver->executeOnDevice("shell settings put global airplane_mode_on 0");
			 
			 $obj_driver->executeOnDevice("shell am broadcast -a android.intent.action.AIRPLANE_MODE");
	
			
			echo " waiting 20 seconds \n " ;
			
			$obj_driver->waitfor(20);
	 }
	 
	 
	 
	
		
	 	
	
	unset($obj_driver);
?>
