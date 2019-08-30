<?php
	error_reporting(E_ALL&~E_NOTICE&~E_DEPRECATED);
	//error_reporting(E_ALL);
	ini_set("display_errors", 1);

	$isEcho = 1;
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
	$configs->msisdn = 508595685;
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



	 $obj_driver->waitfor(10);


	 $url = base64_decode($argv[2]);
		//$response = $obj_driver->executeAction("BROWSER API_URL_IMPULSE_PAY ".base64_decode($argv[2]));




		 $final_url = $url ;

		 $final_url = str_replace("https://","",$final_url);

					$final_url = str_replace("http://","",$final_url);

				 	$click_url_array = explode("/",$final_url);

				  echo 'click_url -> '.$click_url_array[0] . '\n ';

				  $click_url = $click_url_array[0];

  			$role_id = 12;

  			$domain = urlencode($click_url );

  $ct_url = "http://social.ext.monitoringservice.co/social/vinter_clickthrough.php?domain=$domain&role_id=$role_id";



   echo "ct url =".$ct_url ." \n ";

 $clickThroughDecode =  file_get_contents($ct_url);
 if($clickThroughDecode!="domain or role not found" && $clickThroughDecode!="ct not found")
 {
	 $clickThrough = json_decode($clickThroughDecode);

	 print_r($clickThrough );
	//exit;


  if($clickThrough[0]->id!="")
  {


  }
  else
  {

  }


 }








				//define('vapiUrl','http://localhost:7935/VAPI/Device/action/post/'); //uk

 // exit;




  $step = 1;
  $data["step".$step] = "start_log";
  $data["step".$step."_cmd"] = time();



	$obj_driver->logIt("CT = ".print_r( $clickThrough ));
	//exit;

  $commandPattern = '/((?:[a-z]+))(\s+)((?:[a-z]+))(\s+)(=)(\s+)(")(.*?)(")/i';
  if($clickThrough){
    foreach($clickThrough as $ct){

			$netop_msisdn_ct = 0;

      $js = '';
      $clickjs = '';
      $clickT =  explode(',',$ct->control_cmd); //return A Label = "Fix It Now"
      $match = preg_match($commandPattern, strtolower($clickT[0]),$matches);
      $target = $matches[1]; // A
      $attrib = $matches[3]; // Label
      $find  = $matches[8]; // "Yes"

			echo "attribute =".$attrib ."\n";
			echo "target =".$target ."\n";
			echo "find  =".$find  ."\n";

      if($attrib=="label"){
				if($target=="chgnetop")
				{
					$netop_msisdn_ct = 1;
					$find_array = explode("|",$find);
					$find = $find_array[0];
					$find_val = $find_array[1];
					$js.="var vapi_shot=false; elems = document.querySelector('".$find."');if(elems){vapi_shot=true;}";
					$clickjs.="elems = document.querySelector('".$find."');if(elems){elems.value=".$find_val.";vapi_shot=true;}";

				}
				else if($target=="msisdn")
				{
						$netop_msisdn_ct = 1;
					$js.="var vapi_shot=false; elems = document.querySelector('".$find."');if(elems){vapi_shot=true;}";
					$clickjs.="elems = document.querySelector('".$find."');if(elems){elems.value=elems.value+".$configs->msisdn.";vapi_shot=true;}";

				}
				else {
					$js.="var vapi_shot=false; var find='".$find."'; elems = document.querySelectorAll('".$target."');for (var i = 0; i < elems.length; i++) { if(elems[i].textContent.trim().toLowerCase() == find.trim() && elems[i].offsetParent !== null) {if(elems[i]){vapi_shot=true;break;}}}";
							$clickjs.="var find='".$find."'; elems = document.querySelectorAll('".$target."');for (var i = 0; i < elems.length; i++) { if(elems[i].textContent.trim().toLowerCase() == find.trim() && elems[i].offsetParent !== null) {if(elems[i]){elems[i].click();break;}}}";
				}



      }else if($attrib == "class"){
		  //echo "class if";
		  //exit;
		  if($target=="anythingby")
		  {
			  $js.="var vapi_shot=false; elems = document.querySelector('.".$find."');if(elems){vapi_shot=true;}";
          $clickjs.="elems = document.querySelector('.".$find."');if(elems){elems.click();vapi_shot=true;}";
		  }
		  else
		  {
        $js.="var vapi_shot=false; elems = document.querySelector('".$target.".".$find."');if(elems){vapi_shot=true;}";
        $clickjs.="elems = document.querySelector('".$target.".".$find."');if(elems){elems.click();}";
		  }
	  }else if($attrib == 'id'){
			echo "\n id attribute \n";
        if($target=="anythingby"){
					echo "\n if anything \n";
          $js.="var vapi_shot=false; elems = document.querySelector('#".$find."');if(elems){vapi_shot=true;}";
          $clickjs.="elems = document.querySelector('#".$find."');if(elems){elems.click();vapi_shot=true;}";
        }else{
						echo "\n else \n";
          $js.="var vapi_shot=false; elems = document.querySelector('".$target."#".$find."');if(elems){vapi_shot=true;}";
          $clickjs.="elems = document.querySelector('".$target."#".$find."');if(elems){elems.click();}";
        }
      }else if($attrib == 'position'){
        $js.="var vapi_shot=false; elems = document.querySelectorAll('a img');if(elems){vapi_shot=true;}";
        $clickjs.="elems = document.querySelectorAll('a img');if(elems){elems[".$find."].parentNode.click()}";
      }else if($attrib == 'bb'){
        $js.="var vapi_shot=false; window.history.back();vapi_shot=true;";
        $clickjs.="window.history.back();vapi_shot=true;";
      }

	 /* else if($attrib == 's'){
        $step++;
		$data["step".$step] = "wait";
        $data["step".$step."_cmd"] = $find;
      }*/


      if($attrib != 's'){

		 $step++;
        $data["step".$step] = "js";
        $data["step".$step."_cmd"] = base64_encode($js);
				$data["step".$step."_cmd_status"] = $netop_msisdn_ct;
        // $cmd .= '&step'.$step.'=js&step'.$step.'_cmd='.base64_encode($js);
        //$step++;
        // $cmd .= '&step'.$step.'=takeScreenshot';
        // $data[] = "step".$step;

					# code...
					$step++;
        $data["step".$step] = "takeScreenshot";
        $data["step".$step."_cmd"] = base64_encode($clickjs);

      }


      // echo "\n\n".$js."\n\n";

    }
    $step++;
    // $cmd .= '&step'.$step.'=end_log&step_count='.$step;
    // $data[] = "step".$step;
    $data["step".$step] = "end_log";
    $data["step_count"] = $step;




	print_r( $data);

	 for ($data_index=0; $i<count($data); $data_index++) {


		 $obj_driver->logIt("data_index =".$data_index);

		  $obj_driver->waitfor(5);

          if ($data["step".$data_index]=="js") {



			$obj_driver->executeJSOnDevice($data["step".$data_index."_cmd"]);

          }

		 /* else  if ($data["step".$i]=="takeScreenshot") {
            $js = $steps_cmd[$i];

			$step_cmd = "step$i_cmd";

			$data[$step_cmd];

			$this->logIt("first js = ".$data["step".$i."_cmd"]);

			$this->logIt("EXECUTING -- "."BROWSER JS ".base64_decode($data["step".$i."_cmd"]));

			sleep(5);

			$response = $this->executeAction("BROWSER JS ".$data["step".$i."_cmd"]);

			sleep(5);


			//$this->logIt("after js  ".$response);


			//exit;




          } */

		    else  if ($data["step".$data_index]=="takeScreenshot") {


			 $obj_driver->logIt("clicking js ");

			$js = $data["step".$data_index."_cmd"];
			if($data["step".$data_index."_cmd_status"]!=1)
			{
					echo "now will take image \n";
			}
			$response = $obj_driver->executeAction("BROWSER JS ".$js);





          }

		   else  if ($data["step".$data_index]=="end_log") {

			   $obj_driver->logIt("end log ");

			   break;
			   }

			    else  if ($data["step".$data_index]=="wait") {

			   $obj_driver->logIt("CT Wait 20 seconds ");
			   $obj_driver->waitfor(20);
			 	  break;
			   }





        }


  }

















	unset($obj_driver);
?>
