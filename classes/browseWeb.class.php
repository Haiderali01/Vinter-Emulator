<?php
	// netstat to check if its listening at tcpip port
	// tcpip [port]
	// Restart VNC
	// forward tcp:[port] tcp:[port]
	//CURRENT NETWORK: dumpsys connectivity|grep -A 1 "Active network"
	//CURRENT ACTIVITY: dumpsys window windows | grep -E 'mCurrentFocus|mFocusedApp'
	$working_dir = "/opt/vinter/php/";
	include($working_dir."classes/dbase.php");

	class BrowseWeb {
		var $capabilities;
		var $driver; 
		var $isEcho = 1;
		var $alertMessage = "";
		var $serverIPAddress = "";
		var $sys_path = "/var/www/images/";
		var $http_path = "http://79.99.65.140/images/";
		var $host = 'http://localhost:4723/wd/hub'; 
		var $adbPath;
		var $finalRedirectedImg = "";
		var $final_redirected_url = "";
		var $final_source = ""; 
		var $log_datetime;
		var $traceout;
		var $site_id;	
		var $agent_id;
		var $platform;
		var $transcaion_table_id = 0;
		var $unique_id = 1;
		
		var $configs;
		var $params;
		
		var $execution_log;
		var $tcpipMaxLimit = 20;
		
		function setHost($host) {
			$this->host = $host;
		}
		
		function setSysPath($sys_path) {
			$this->sys_path = $sys_path;
		}
		
		function setConfigs($configs) {
			$this->configs = $configs;
		}
		
		function setAdbPath($path) {
			$this->adbPath = $path;	
		}
		
		function setTcpipMaxLimit($limit) {
			$this->tcpipMaxLimit = $limit;	
		}
		
		function setAPIParams() {
			$params = "controller_web_port=".$this->configs["device_web_port"]."&".
					  "device_adb_id=".$this->configs["device_adb_id"]."&".
					  "device_adb_vnc_port=".$this->configs["device_vnc_port"]."&".
					  "device_port=".$this->configs["device_pvnc_port"]."&".
					  "device_url=".$this->configs["device_web_url"];

			$this->params = $params;			  
		}
		
		function connectDevice() {
			$output = $this->executeCMD($this->adbPath." connect localhost:".$this->configs["device_tcpip_port"]." 2>&1");

			$output = $this->executeCMD($this->adbPath." devices 2>&1");
			for ($i=0; $i<count($output); $i++) {
				if (strpos($output[$i], "localhost:".$this->configs["device_tcpip_port"])!==false) {
					$ex = explode("localhost:".$this->configs["device_tcpip_port"], $output[$i]);
					
					$connected_status = trim($ex[1]);
					$this->logIt("Status: ".$connected_status);
					
					break;
				}	
			}
			
			if ($connected_status!="device") {
				$this->logIt("ERROR: Device NOT Connected!");
				
				$this->executePiCMD("/home/pi/adb/adb-linuxARMv6 forward tcp:".$this->configs["device_tcpip_port"]." tcp:".$this->configs["device_tcpip_port"]);
				$this->executePiCMD("netstat -nltop");
				$output = $this->executeCMD($this->adbPath." connect localhost:".$this->configs["device_tcpip_port"]." 2>&1");
			}
		}
		
		function call_deviceAPI($action, $params) {
			if ($action=="shellCMD_device") {
				$ex = explode("shell_cmd=", $params);
				
				$cmd = $ex[1];
				$output = $this->executeDeviceShellCMD($cmd);
			} else if ($action=="deviceCMD_device") {
				$ex = explode("device_cmd=", $params);
				
				$cmd = $ex[1];
				$output = $this->executeDeviceCMD($cmd);
			} else if ("restart_vnc") {
				$output = $this->executeDeviceShellCMD("/data/local/tmp/vmlitevncserver --stop");
				$output = $this->executeDeviceShellCMD("am force-stop com.vmlite.vncserver");
				$output = $this->executeDeviceShellCMD("am start -a android.intent.action.MAIN -n com.vmlite.vncserver/.MainActivity");
			
				$output = $this->executeDeviceCMD("forward tcp:".$device_adb_vnc_port." tcp:".$device_adb_vnc_port);
				$output = $this->executeDeviceShellCMD("input keyevent 4");
			
				$output = $this->executeDeviceShellCMD("/data/local/tmp/vmlitevncserver &");
			} else {
				$output = $this->get_data("http://monitoringservice.co/workflow/ajax_checkDeviceStatus.php?action=".$action."&".$params);
			}

			$this->logIt("Output: ".$output);
			return $output;
		}
			
		function waitfor($seconds) {
			if ($this->isEcho) echo "Waiting ";
			for ($i=0; $i<$seconds; $i++) {
				sleep(1);	
				if ($this->isEcho) {
					echo ".";
					if ($i%10==0) {
						echo "\n";
					}
				}
			}
		}
	
		function setTCPIPTunnel() {
			$this->logIt("Check Device Web Connection");

			$vpn_ip = $this->configs["device_vpn_ip"];
			$device_web_port = $this->configs["device_web_port"];

			$fp = fsockopen($vpn_ip, $device_web_port, $errno, $errstr, 3);
			if (!$fp) {
				$this->logIt("ERROR: Device Web Connection Failed!");
				waitfor(120); // wait for 2 min
				$this->setTCPIPTunnel();
			} else {
				$this->logIt("Web Connection OK");
				$this->logIt("Check TCPIP listener on device");
				$output = $this->executeDeviceShellCMD("netstat |grep ".$this->configs["device_tcpip_port"]);

				if (strpos($output, $this->configs["device_tcpip_port"])!==false) {
					$this->logIt("TCPIP listener is OK");
					
					$this->logIt("Check TCPIP MAX Connections Limit (".$this->tcpipMaxLimit.")");
					if (count(explode("\n", $output))>$this->tcpipMaxLimit) {
						$this->restartTCPIP();
					}
				} else {
					$this->logIt("TCPIP listener is NOT OK!");
					
					$this->restartTCPIP();
				}
				
				$this->forwardTCPIP();
				
				$this->logIt("Checking TCPIP Tunnel existance");
				$fp_tcpip = fsockopen("localhost", $this->configs["device_tcpip_port"], $errno, $errstr, 3);
				if (!$fp_tcpip) {
					$this->logIt("TCPIP Tunnel doesnt exist!");
					
					$this->logIt("Create TCPIP Tunnel");
					
					$localloop = 'localhost';
					
					$cmd = "sshpass -p 'bluebird' ".
						   "ssh -L ".$this->configs["device_tcpip_port"].":".$localloop.":".$this->configs["device_tcpip_port"].
						   " ".$this->configs["device_pi_ssh"]." -N > /dev/null 2>&1 & echo $!";
					$this->executeCMD($cmd);
					waitfor(3);
					
					while (!$fp_tcpip1 = fsockopen($localloop, $this->configs["device_tcpip_port"], $errno, $errstr, 3)) {
						$this->logIt("Still Creating TCPIP Tunnel!");
					}
				}

				$this->forwardTCPIP();
				
				if (strpos($this->executePiCMD("netstat -nltop"), $this->configs["device_tcpip_port"])===false) {
					$this->logIt("Pi Tunnel Error!");	
					waitfor(120); // wait for 2 min
					$this->setTCPIPTunnel();
				}
				
				$this->logIt("TCPIP Tunnel OK");
			}
		}
		
		function restartTCPIP() {
			$this->logIt("Restarting device in TCPIP mode");
			$output = $this->executeDeviceCMD("tcpip ".$this->configs["device_tcpip_port"]);
			waitfor(10); // explicit wait to let the device restart in tcpip mode
			
			$this->logIt("Restarting VNC");
			$this->call_deviceAPI("restart_vnc", $this->params);
			waitfor(10);
		}
		
		function forwardTCPIP() {
			$this->logIt("Forwarding TCPIP port to Pi\n"."forward tcp:".$this->configs["device_tcpip_port"]." tcp:".$this->configs["device_tcpip_port"]);
			$output = $this->executeDeviceCMD("forward tcp:".$this->configs["device_tcpip_port"]." tcp:".$this->configs["device_tcpip_port"]);
		}
		
		function setMySQLTunnel($port, $mysql_ip="") {
			if ($mysql_ip=="") {
				$mysql_ip = "79.99.65.132";
			}
			$output = $this->executeCMD("netstat |grep ".$port);

			if (strpos(implode("\n", $output), $port)!==false) {
				$this->logIt("MySQL Listener listener is OK on ".$port);
			} else {
				$this->logIt("MySQL listener on ".$port." is NOT OK!");
				$this->logIt("Creating MySQL Tunnel");
				
				$localloop = '127.0.0.1';
				
				$cmd = "sshpass -p 'guniev999D' ".
					   "ssh -L ".$port.":".$localloop.":".$port.
					   " root@".$mysql_ip." -N > /dev/null 2>&1 & echo $!";
				$this->executeCMD($cmd);
				waitfor(3);
				
				$connectAfterIndex = 10;
				$max_threshold = 100;
				
				$i = 0; 
				while (!$fp_tcpip1 = fsockopen($localloop, $port, $errno, $errstr, 3)) {
					$this->logIt("Still Creating MySQL Tunnel!");
					
					if ($i%$connectAfterIndex==0) {
						$cmd = "sshpass -p 'guniev999D' ".
							   "ssh -L ".$port.":".$localloop.":".$port.
							   " root@".$mysql_ip." -N > /dev/null 2>&1 & echo $!";
						$this->executeCMD($cmd);
					}
					
					$i++;
					if ($i>=$max_threshold) {
						exit("Cant Create MySQL Tunnel!!!");	
						waitfor(60); // wait for 1 min
						$this->setMySQLTunnel($port);
					}
				}
			
				$this->logIt("MySQL Tunnel OK");
			}
		}
		
		function setupTunnel($ssh_ip, $ssh_port, $ssh_user, $ssh_password, $port, $localloop='localhost', $tunel_name="") {
			$output = $this->executeCMD("netstat |grep ".$port);

			if (strpos(implode("\n", $output), $port)!==false) {
				$this->logIt($tunel_name." Listener listener is OK on ".$port);
			} else {
				$this->logIt($tunel_name." listener on ".$port." is NOT OK!");
				$this->logIt("Creating ".$tunel_name." Tunnel");
								
				$cmd = "sshpass -p '".$ssh_password."' ".
					   "ssh -L ".$port.":".$localloop.":".$port.
					   " ".$ssh_user."@".$ssh_ip." -p ".$ssh_port." -N > /dev/null 2>&1 & echo $!";
				$this->executeCMD($cmd);
				waitfor(3);
				
				$connectAfterIndex = 10;
				$max_threshold = 100;
				
				$i = 0; 
				while (!$fp_tcpip1 = fsockopen($localloop, $port, $errno, $errstr, 3)) {
					$this->logIt("Still Creating MySQL Tunnel!");
					
					if ($i%$connectAfterIndex==0) {
						$this->executeCMD($cmd);
					}
					
					$i++;
					if ($i>=$max_threshold) {
						$this->logIt("Cant Create ".$tunel_name." Tunnel!!!");	
						waitfor(300); // wait for 5 min
						$this->setupTunnel($ssh_ip, $ssh_port, $ssh_user, $ssh_password, $port, $localloop, $tunel_name);
					}
				}
			
				$this->logIt($tunel_name." Tunnel OK");
			}
		}
		
		function executeCMD($cmd) {
			$this->logIt($cmd);
			$last_line = exec($cmd, $output);
			
			$this->logIt($output, true);
			
			file_put_contents("/opt/appium_workspace/php/tmp.log", implode("\n", $output));
			return $output;
		}
        
		function executePiCMD($cmd) {
			$this->logIt("CMD -> ".$cmd);
			$device_web_url = $this->configs["device_web_url"];
			$device_adb_id = $this->configs["device_adb_id"];
			
			$device_api_url = $device_web_url."devices.php?";

			$action = "action=cmd_no_id";
			$cmd = "cmd=".base64_encode($cmd);

			$device_api_url .= $action."&".$cmd;

			$output = $this->get_data($device_api_url);
			$this->logIt("Output: ".$output);
			return $output;	
		}
		
		function executeDeviceShellCMD($cmd) {
			$this->logIt("cmd: ".$cmd);
			$device_web_url = $this->configs["device_web_url"];
			$device_adb_id = $this->configs["device_adb_id"];
			
			$device_api_url = $device_web_url."devices.php?id=".$device_adb_id;

			$action = "action=shellCMD_device";
			$cmd = "shell_cmd=".base64_encode($cmd);

			$device_api_url .= "&".$action."&".$cmd;

			$output = $this->get_data($device_api_url);
			$this->logIt("Output: ".$output);
			return $output;			
		}
		
		function getUIAutomatorDump() {
			$device_web_url = $this->configs["device_web_url"];
			$device_adb_id = $this->configs["device_adb_id"];
			
			$device_api_url = $device_web_url."devices.php?id=".$device_adb_id;

			$action = "action=uiautomator_dump";

			$device_api_url .= "&".$action;

			$output = $this->get_data($device_api_url);
			$this->logIt("Output: ".$output);
			return $output;			
		}
		
		function executeDeviceCMD($cmd) {
			$device_web_url = $this->configs["device_web_url"];
			$device_adb_id = $this->configs["device_adb_id"];
			
			$device_api_url = $device_web_url."devices.php?id=".$device_adb_id;

			$action = "action=deviceCMD_device";
			$cmd = "device_cmd=".base64_encode($cmd);

			$device_api_url .= "&".$action."&".$cmd;

			$output = $this->get_data($device_api_url);
			$this->logIt("Output: ".$output);
			return $output;			
		}
		
		function get_data($url) {
			$this->logIt($url);
			$ch = curl_init();
			$timeout = 5;
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_TIMEOUT_MS, 30000);
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
		}
		
		function getAppPath() {
			$flag_upload_app = 0;
			if ($this->configs["app_path"]=="") {
				$flag_upload_app = 1;
			} else if (!(is_file($this->configs["app_path"]))) {
				$flag_upload_app = 1;
			}

			if ($flag_upload_app==1) {
				$this->logIt("Uploading App on Remote server......");
				$this->logIt($this->configs, true);
				if (strpos($this->configs["app_url"], "79.99.65.132")!==false) {
					$this->configs["app_url"] = str_replace("79.99.65.132", "10.10.1.73", $this->configs["app_url"]);	
				} else if (strpos($this->configs["app_url"], "http://monitoringservice.co/")!==false) {
					$this->configs["app_url"] = str_replace("http://monitoringservice.co/", "http://10.10.1.73/", $this->configs["app_url"]);	
				}

				$file_content = file_get_contents($this->configs["app_url"]);

				$path_parts = pathinfo($this->configs["app_url"]);

				$this->configs["app_path"] = $this->configs["app_dir"].$path_parts['basename'];
				if (!file_put_contents($this->configs["app_path"], $file_content)) {
					$this->logIt("IO Error! ".$this->configs["app_path"]);
					exit;
				} else {
					$this->logIt("App Uploaded\n");
				}
			}

			$obj_db = new Dbase();
			$data = array("path" => $this->configs["app_path"]);
						  
			$obj_db->updateCondition("tblapps", 
									 "id = ".$this->configs["app_id"],
									 $data);

			return $this->configs["app_path"];
		}

		function setAndroidCapabilities($app_path) {
			$this->capabilities = array(
					 "app" => $app_path,
					 'browserName' => '',
					 'platformName' => 'Android',
					 'platformVersion' => '4.4',
					 'deviceName' => 'Android Emulator'
					);	
		}
		
		function initApp($host=""){
			if ($host!="") {
				$this->host = $host;
			}

			$this->logIt("Going to launch the App");

			$this->driver = RemoteWebDriver::create($this->host, $this->capabilities);

			$this->logIt("App launched");
		}
		
		function getAllWindowHandles() {
			$this->logIt("Wait 5 Seconds");
			waitfor(5);
			$this->logIt("Done Wait 5 Clicking Cancel");
			$el = $this->findElement(WebDriverBy::name("Cancel"))->click();
			waitfor(5);
			$this->logIt("Show ELement");
			print_r($el);
			$this->logIt("Done Showing");
		}
		function acceptAlert() {
			
			try {
				$alert = $this->switchTo()->alert();
				
				//$this->alertMessage = $alert->getText();
				
				//$alert->dismiss();
				
				$alert->accept();
				
			} catch (Exception $e) {
				
			
				$this->logIt("No Alert Detected");
			}
			
		}
		
		function browseSite($url, $master_iteration_id,$sites_table) {
			
			$this->logIt("Getting URL .........\n");
			
			$this->get($url);
			
			/*
			* Dismiss alert and get the text of alert message
			*/
			
			try {
				$alert = $this->switchTo()->alert();
				
				//$this->alertMessage = $alert->getText();
				
				$alert->dismiss();
				
				$alert->accept();
				
			} catch (Exception $e) {
				
			
				$this->logIt("No Alert Detected");
			}
			
			try {
				if($this->findElement(WebDriverBy::className("close"))){
					$this->findElement(WebDriverBy::className("close"))->click();
				}
			} catch(Exception $b) {
				$this->logIt("No Popup Detected");
			}
			
			/*
			* Taking Screen Shot
			*/
			$file_name = "home_".rand().'_'.uniqid().'.png';
			
			$this->sleepBrowser(5);
			
			$this->takeScreenshot($this->sys_path.$file_name);
			
			$this->finalRedirectedImg = $this->http_path.$file_name;
			
			$this->logIt($this->finalRedirectedImg);
			
			$current_url = $this->getCurrentURL();
			
			//$this->manage()->timeouts()->pageLoadTimeout(15);
			
			$this->final_redirected_url = $current_url;
			
			$pageSoure = $this->getPageSource();
			
			//$this->final_source = $pageSoure; 
			$this->final_source = ""; 
			
			$this->log_datetime = date("Y-m-d H:i:s");
			
			//$this->traceout = file_get_contents('/var/www/my.log');
			
			$currentWindow = $this->getWindowHandle();

			$db_obj = new Dbase();
			
			$insertVeriLog  = array('site_id' => $this->site_id,
									'master_iteration_id' => $master_iteration_id,
									'userAgent_id' => $this->agent_id,
									'platform_id' => $this->platform,
									'serverIPAddress' => $this->serverIPAddress,
									'final_redirected_url' => $this->final_redirected_url,
									'finalRedirectedImg' => $this->finalRedirectedImg,
									'alertMessage' => $this->alertMessage,
									'log_datetime' => $this->log_datetime,
									'traceout' => $this->traceout);
									
			$iteration_id = $db_obj->insert($insertVeriLog, "tbliterations");
									
			/*
			* Find ads which are out of iframes start
			*/
			
			$this->executeScript(" var change = document.getElementsByTagName('a');
											for (var i = 0; i < change.length; i++) {
												  change[i].setAttribute('target', '_blank');
											}");
			
			$this->pareseThirdPartyHref($url,$iteration_id,$currentWindow);
			/*
			* Find ads which are out of iframes End
			*/
			
			/*
			* Iframe logics start here
			*/
			
			$this->parseIframeAds($url,$iteration_id,$currentWindow);
			
			/*
			* Iframe logics end here
			*/
			
			return $iteration_id;
		}
		
		function pareseThirdPartyHref($url,$iteration_id,$currentWindow){
			
			$db_obj = new Dbase();
			
			$thirdparty_hrefs = array();
			
			$thirdparty_href = $this->findElements(WebDriverBy::tagName('a'));
			
			$this->logIt("Total Href : ".count($thirdparty_href));
			
			if ($thirdparty_href) {
			
				foreach ($thirdparty_href as $hrf) {
			
					$main_link = $hrf->getAttribute('href');
					
					/*
					* Built logic here to identify that this is thrid party ad
					*/
					
					$ad_url = $this->getMainDomain($main_link);
					
					$main_site_url = $this->getMainDomain($url);
					
					if($ad_url!=$main_site_url){
						
						$innerHTML = $hrf->getAttribute('innerHTML');
				
						$main_img = '';
				
						try {
							$html = new simple_html_dom();
							$html->load($innerHTML);
							foreach ($html->find('img') as $element) {
								if(!empty($element->src)) {
									$main_img = $element->src;
								}
							}
						} catch(Exception $ac) {
							$this->logIt("No Image found");	
						}
						
						$this->logIt("--- Href : ".$main_link);
						
						$this->logIt("--- Img : ".$main_img);
						
						$this->logIt("Link End : ".substr($main_link, -4));
						
						if(strpos($main_link,"mailto:")===false and strpos($main_link,"whatsapp://send?")===false and strpos($main_link,"twitter.com")===false and strpos($main_link,"facebook.com")===false and strpos($main_link,".mp4")===false and substr($main_link, -4)!=='.apk'){
	
							$this->logIt("Dig In");
						
							if (!empty($main_link) and !empty($main_img)) {
							
							$ads_ext_path_2 = pathinfo($main_img);
												
							$ad_ext_2 = $ads_ext_path_2['extension'];
													
							if(!empty($ad_ext_2)){
								$img = explode("?",$ad_ext_2);
								$ad_ext_2 = strtolower($img[0]);
								$ext_array = array("jpg","png","gif","jpeg","tif");
								if(!in_array($ad_ext_2,$ext_array)){
									$ad_ext_2 = 'png';
								}
								
								$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.'.$ad_ext_2;
							}else{
								$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.png';
							}
												
							$ad_image_contents = file_get_contents($main_img);
							
							if (file_put_contents($this->sys_path.$ad_local_ad_img, $ad_image_contents)) {
								$local_ad_imag = $ad_local_ad_img;
								$ad_image = $this->http_path.$ad_local_ad_img;
							} else {
								$local_ad_imag = '';
								$ad_image = $main_img;
							}
							
							
							
							$main_img_att = getimagesize($this->sys_path.$ad_local_ad_img);
							
							$this->logIt("--- Local Img : ".$this->http_path.$ad_local_ad_img);
							
							$this->logIt("--- Width : ".$main_img_att[0]);
							
							$this->logIt("--- Height : ".$main_img_att[1]);
							
							if($main_img_att[0]>30 and $main_img_att[1]>30){				
												
								$ad_record_added = date("Y-m-d H:i:s");
						
								$iteration_Ads = array("iteration_id" => $iteration_id,
													   "ad_screenShot" => $main_img,
													   "ad_screenShot_local" => $local_ad_imag,
													   "recored_added" => $ad_record_added);
								
								$db_obj->insert($iteration_Ads,"tbliterations_ads_tmp");
								
								$transcation_ads_log = $db_obj->selectSRow(array("*"),"tbliterations_ads_tmp","recored_added='".$ad_record_added."' and iteration_id='".$iteration_id."'");
							
								$iteration_Ads_Id = $transcation_ads_log["id"];
							
								if($sites_table==1){
									$transcation_type = 'alexa';
									//$site_full_url = $this->getAlexaSiteUrl($site_id);
								}else{
									$transcation_type = 'auto';
									//$site_full_url = $this->getSiteUrl($site_id);
								}
													
								$ad_url_id = $this->getAdUrlId($main_link);
							
								$recored_added = date("Y-m-d H:i:s");
								
								$this->unique_id = $transcation_unqiue_id = $iteration_id.uniqid();
					
								$transcations_data = array("site_id" => $url,
														   "home_page_image" => $this->finalRedirectedImg,
														   "ad_image_tmp" => $iteration_Ads_Id,
														   "transcation_type" => $transcation_type,
														   "recored_added" => $recored_added,
														   "iteration_id" => $iteration_id,
														   "ad_url" => $ad_url_id,
														   "unique_id" => $transcation_unqiue_id);
											
								$db_obj->insert($transcations_data,"tbltranscations");
								
								$transcation_log = $db_obj->selectSRow(array("*"),"tbltranscations","recored_added='".$recored_added."' and iteration_id=$iteration_id");
								
								$iterations_table_id = $tracstion_table_id = $transcation_log["id"];
								
								$this->logIt("Iteration ID : ".$iterations_table_id);
								
								$this->logIt("Starting tshark");
								
								$tracstion_table_id = $iterations_table_id;
								
								exec("pkill tcpdump");
								
								exec("/usr/sbin/tcpdump -i any -A -s 0 'tcp port 80 and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)' > /var/www/images/logs/".$iterations_table_id."_log.log 2>&1  & echo $!",$shark_output);
								
								$sharkID = (int)$shark_output[0];
								
								$this->logIt("Shark Output : ".$sharkID);
								
								$this->logIt("tshark started");
								
								//exit;
							
								if (!in_array($main_link, $thirdparty_hrefs)) {
									
									$status_array_2 = get_headers($main_link);
													
									$src_header_2 = "";
										
									for($si = 0; $si<sizeof($status_array_2); $si++){
										
										if(strpos($status_array_2[$si], "HTTP/") !== false){
											
											$src_header_2.='<p>'.$status_array_2[$si].'</p>';
											
										}
										if(strpos($status_array_2[$si], "Location") !== false){
											
											$src_header_2.='<p>'.$status_array_2[$si].'</p>';
											
										}
										
									}
												
									$status_2 = $src_header_2;
									
									if(strpos($status_2,"market://details")!==false){
										
										$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Play Store")); 	
										
									}
									if(strpos($status_2,"https://play.google")!==false){
										
										$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Play Store")); 	
																
									}
									if(strpos($status_2,"https://itunes.")!==false){
										
										$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Apple Store")); 	
										
									}
									
									$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("http_status"=>$status_2)); 
									
									//$this->logIt($db_obj->sql);
									
									$this->logIt("Iteration ID : ".$iterations_table_id);
									
									$thirdparty_hrefs[] = $main_link;
								
									$this->logIt("Going to click main link");
									
									$hrf->getLocationOnScreenOnceScrolledIntoView();
									
									$this->logIt("Scrolled to new position");
									
									$new_home_with_ad = "scrolled_1_".rand().'_'.uniqid().'.png';
									
									$this->sleepBrowser(5);
									
									$this->takeScreenshot($this->sys_path.$new_home_with_ad);
									
									$this->logIt("After screens shot");
									
									if(file_exists($this->sys_path.$new_home_with_ad)){
										
										$transcations_update_home = array("home_page_image" => $this->http_path.$new_home_with_ad);
									
										$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, $transcations_update_home); 
										
										$this->logIt("Iteration ID : ".$iterations_table_id);
									
									}
									
									$this->logIt("Before click");
									
									$hrf->click();
									
									$this->logIt("Link clicked");
								
									$allHandles = $this->getWindowHandles();
								
									$xa = 0;
									
									$js_counter = 0;
									
									try {
										$this->switchTo()->window(end($this->getWindowHandles()));
												
										$this->sleepBrowser(10);
												
										$adSource = $this->getPageSource();
												
										$adURL = $this->getCurrentURL();
												
										//$this->manage()->timeouts()->pageLoadTimeout(15);
										
										$this->logIt("Ad URL ".$this->getMainDomain($adURL));
										
										$this->logIt("Site URL ".$this->getMainDomain($url));
										
										if ($this->getMainDomain($adURL)!=$this->getMainDomain($url)) {
											
											$ad_name_0 = 'nonjs_landing_'.uniqid().'.png';
											
											$this->takeScreenshot($this->sys_path.$ad_name_0);
											
											$this->logIt("Taking Screen Shot Landing");
											
											$this->logIt($this->http_path.$ad_name_0);
											
											//exit;
											
											$insertVeriLog_level1  = array('site_id'=>$this->site_id,
																			'master_iteration_id' => $iteration_id,
																			'iteration_ads_id' => $iteration_Ads_Id,
																			'userAgent_id' => $this->agent_id,
																			'platform_id' => $this->platform,
																			'serverIPAddress' => $this->serverIPAddress,
																			'final_redirected_url' => $adURL,
																			'finalRedirectedImg' => $this->http_path.$ad_name_0,
																			'alertMessage' => $this->alertMessage,
																			'log_datetime' => date("Y-m-d H:i:s"),
																			'traceout' => $this->traceout);
											
											$level1_iteration_id = $db_obj->insert($insertVeriLog_level1, "tbliterations");
											
											$transcations_update_data = array("landing_page_image" => $this->http_path.$ad_name_0,
																			  "final_url" => $adURL);
											
											exec("kill -9 $sharkID");
											
											if(empty($adSource)){
												
												$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"White Page"));
													
											}
																
											$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, $transcations_update_data); 
											$this->logIt("Iteration ID : ".$iterations_table_id);
											
										}
										
										$this->executeScript("window.close();");
									
										$this->logIt("Clicking process completed");
										
										$this->switchTo()->Window($currentWindow);
										
									} catch (Exception $e) {
										
										$this->switchTo()->Window($currentWindow);
										
										$this->logIt("Exception Thrown while parsing the element one");
										
										$this->logIt("------------------------------------------");
										
										$this->logIt("Caught the error: ".$e->getMessage());
										
										$this->logIt("------------------------------------------");
									}
									
									
									/*
									foreach ($allHandles as $winHandle) {
										
										if (strpos($main_link,'javascript:')===false) {
											
											$this->logIt("Out of JS Link");
											
											try {
												$this->switchTo()->window($winHandle);
												
												$this->sleepBrowser(10);
												
												$adSource = $this->getPageSource();
												
												$adURL = $this->getCurrentURL();
												
												//$this->manage()->timeouts()->pageLoadTimeout(15);
												
												if ($adURL!=$site_url) {
													
													$ad_name_0 = 'nonjs_landing_'.uniqid().'.png';
													
													$this->takeScreenshot($this->sys_path.$ad_name_0);
													
													$this->logIt("Taking Screen Shot Landing");
													
													$this->logIt($this->http_path.$ad_name_0);
													
													//exit;
													
													$insertVeriLog_level1  = array('site_id'=>$this->site_id,
																					'master_iteration_id' => $iteration_id,
																					'iteration_ads_id' => $iteration_Ads_Id,
																					'userAgent_id' => $this->agent_id,
																					'platform_id' => $this->platform,
																					'serverIPAddress' => $this->serverIPAddress,
																					'final_redirected_url' => $adURL,
																					'finalRedirectedImg' => $this->http_path.$ad_name_0,
																					'alertMessage' => $this->alertMessage,
																					'log_datetime' => date("Y-m-d H:i:s"),
																					'traceout' => $this->traceout);
													
													$level1_iteration_id = $db_obj->insert($insertVeriLog_level1, "tbliterations");
													
													$transcations_update_data = array("landing_page_image" => $this->http_path.$ad_name_0,
																					  "final_url" => $adURL);
													
													exec("kill -9 $sharkID");
													
													if(empty($adSource)){
														
														$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"White Page"));
															
													}
																		
													$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, $transcations_update_data); 
													$this->logIt("Iteration ID : ".$iterations_table_id);
													
												}
												
												$this->executeScript("window.close();");
											
												$this->logIt("Clicking process completed");
												
												$this->switchTo()->Window($currentWindow);
												
											} catch (Exception $e) {
												
												$this->switchTo()->Window($currentWindow);
												
												$this->logIt("Exception Thrown while parsing the element one");
												
												$this->logIt("------------------------------------------");
												
												$this->logIt("Caught the error: ".$e->getMessage());
												
												$this->logIt("------------------------------------------");
											}
										}else{
											$this->logIt("In JS Link");
											
											if($js_counter==0){
												
												$status_array_2 = get_headers($main_img);
													
												$src_header_2 = "";
													
												for($si = 0; $si<sizeof($status_array_2); $si++){
													
													if(strpos($status_array_2[$si], "HTTP/") !== false){
														
														$src_header_2.='<p>'.$status_array_2[$si].'</p>';
														
													}
													if(strpos($status_array_2[$si], "Location") !== false){
														
														$src_header_2.='<p>'.$status_array_2[$si].'</p>';
														
													}
													
												}
												
															
												$status_2 = $src_header_2;
												
												if(strpos($status_2,"market://details")!==false){
										
													$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Play Store")); 	
													
												}
												
												if(strpos($status_2,"https://play.google")!==false){
													
													$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Play Store")); 	
																			
												}
												
												if(strpos($status_2,"https://itunes.")!==false){
													
													$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"Apple Store")); 	
													
												}
												
												$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("http_status"=>$status_2)); 
												
												$this->logIt("Iteration ID : ".$iterations_table_id);
												
												
											try {
												$this->switchTo()->window($winHandle);
												
												$this->sleepBrowser(10);
												
												$adSource = $this->getPageSource();
												
												$adURL = $this->getCurrentURL();
												
												//$this->manage()->timeouts()->pageLoadTimeout(15);
												
												if ($adURL!=$site_url) {
													
													$ad_name_0 = 'js_landing_'.uniqid().'.png';
													
													$this->takeScreenshot($this->sys_path.$ad_name_0);
													
													$this->logIt("Taking Screen Shot Landing");
													
													$this->logIt($this->http_path.$ad_name_0);
													
													//exit;
													
													$insertVeriLog_level1  = array('site_id'=>$this->site_id,
																					'master_iteration_id' => $iteration_id,
																					'iteration_ads_id' => $iteration_Ads_Id,
																					'userAgent_id' => $this->agent_id,
																					'platform_id' => $this->platform,
																					'serverIPAddress' => $this->serverIPAddress,
																					'final_redirected_url' => $adURL,
																					'finalRedirectedImg' => $this->http_path.$ad_name_0,
																					'alertMessage' => $this->alertMessage,
																					'log_datetime' => date("Y-m-d H:i:s"),
																					'traceout' => $this->traceout);
													
													$level1_iteration_id = $db_obj->insert($insertVeriLog_level1, "tbliterations");
													
													//$log_tshar_headers = file_get_contents('http://79.99.65.139/logs/'.$transcationsID_landing.'_log.log');
													
													$transcations_update_data = array("landing_page_image" => $this->http_path.$ad_name_0,
																					  "final_url" => $adURL);
													//$transcationsID = $this->transcaion_table_id;
													
													exec("kill -9 $sharkID");
													
													
													if(empty($adSource)){
															
														$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, array("landing_status"=>"White Page"));
															
													}
																		
													$db_obj->updateCondition("tbltranscations", "id=".$iterations_table_id, $transcations_update_data); 
													
													//$this->logIt($db_obj->sql);
												}
												
												$this->executeScript("window.close();");
												
												$this->logIt("Iteration ID : ".$iterations_table_id);
											
												$this->logIt("Clicking process completed");
												
												$this->switchTo()->Window($currentWindow);
												
											} catch (Exception $e) {
												
												$this->switchTo()->Window($currentWindow);
												
												$this->logIt("Exception Thrown while parsing the element one");
												
												$this->logIt("------------------------------------------");
												
												$this->logIt("Caught the error: ".$e->getMessage());
												
												$this->logIt("------------------------------------------");
											}
											$js_counter++;
											}
											
										}
									}
									*/
								}
								
								}
							}
						
						}// close not mailto,whatsapp and facebook if
						
					}// close the if where the link is not the domain link
					
				} //close foreach loop
				
			}// close main if
			
		
		
		}
		
		function parseIframeAds($url,$iteration_id,$currentWindow){
			
			$db_obj = new Dbase();
		
			$iFrames = $this->findElements(WebDriverBy::tagName('iframe'));
			
			$if = $as = 0;
			
			$ads_urls = array();
			
			$this->logIt("\nTotal iFrames : ".count($iFrames));
			
			foreach($iFrames as $iframe) {
				try {
					$this->switchTo()->frame($iframe);
					
					$this->logIt("-------------------------------------------------------------\nSwitching to iFrame : ".$if++);
					
					/*
					* checking sub frame in the main frame
					*/
					$subiFrames = $this->findElements(WebDriverBy::tagName('iframe'));
					if (count($subiFrames)>0) {
						$this->logIt("Total Sub iFrame : ".count($subiFrames));
						
						/*
						*if sub iFrames are there then do the following things
						*/
						
						$sub_iframe_counter = 0;
						try {
							foreach ($subiFrames as $subiframe) {
								try {
									$this->switchTo()->frame($subiframe);
									$this->logIt("Switching to Sub iFrame :".$sub_iframe_counter++);
									
									$sub_hrefs = $this->findElements(WebDriverBy::tagName('a'));
									$this->logIt("Total Sub iFrame hrefs : ".count($sub_hrefs));
									
									
									$this->executeScript(" var change = document.getElementsByTagName('a');
											for (var i = 0; i < change.length; i++) {
												  change[i].setAttribute('target', '_blank');
											}");
									
									
									
									if ($sub_hrefs) {
										$this->logIt("Dig into the sub iframe hrefs");
										
										$sub_iframes_hrefs = 0;
										foreach ($sub_hrefs as $s_hrf) {
											$this->logIt("Gong to parse the href of sub iframe :".$sub_iframes_hrefs++);
											$s_link = $s_hrf->getAttribute('href');
									
											$s_innerHTML = $s_hrf->getAttribute('innerHTML');
											$s_value = '';
											try {
												$s_html = new simple_html_dom();
												$s_html->load($s_innerHTML);
												
												foreach($s_html->find('img') as $s_element){
													if(!empty($s_element->src)) {
														$s_value = $s_element->src;
													}
												}
											} catch (Exception $s_img_catch) {
												$this->logIt("No Image found");	
											}
											
											$this->logIt("--- Sub Href : ".$s_link);
											
											$this->logIt("--- Sub Img : ".$s_value);
											
											if(strpos($s_link,"mailto:")===false and strpos($s_link,"whatsapp://send?")===false and strpos($s_link,"twitter.com")===false and strpos($s_link,"facebook.com")===false and substr($s_link, -4)!=='.apk'){
												/*
												* If link and img src is not empty start
												*/
												if (!empty($s_link) and !empty($s_value)) {
													
													$main_img_att_2 = getimagesize($s_value);
							
													$ads_ext_path = pathinfo($s_value);
													
													$ad_ext = $ads_ext_path['extension'];
													
													if(!empty($ad_ext)){
														$img = explode("?",$ad_ext);
														$ad_ext = strtolower($img[0]);
														$ext_array = array("jpg","png","gif","jpeg","tif");
														if(!in_array($ad_ext,$ext_array)){
															$ad_ext = 'png';
														}
														
														$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.'.$ad_ext;
													}else{
														$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.png';
													}
													
													
													$s_ad_image_contents = file_get_contents($value);
									
													if (file_put_contents($this->sys_path.$ad_local_ad_img, $s_ad_image_contents)) {
														
														$local_ad_imag = $ad_local_ad_img;
														
														$ad_image = $this->http_path.$ad_local_ad_img;
														
													} else {
														$local_ad_imag = '';
														$ad_image = $s_value;
													}
													
													$main_img_att_2 = getimagesize($this->sys_path.$local_ad_imag);
							
													$this->logIt("--- Local Img : ".$this->http_path.$ad_local_ad_img);
													
													$this->logIt("--- Width : ".$main_img_att_2[0]);
													
													$this->logIt("--- Height : ".$main_img_att_2[1]);
													
													if($main_img_att_2[0]>50 and $main_img_att_2[1]>30){
													
													$s_record_added = date("Y-m-d H:i:s");
													
													$s_iteration_Ads = array("iteration_id" => $iteration_id,
																			 "ad_screenShot" => $s_value,
																			 "ad_screenShot_local" => $local_ad_imag,
																			 "recored_added" => $s_record_added);
													
													$s_value = '';
																	
													$db_obj->insert($s_iteration_Ads,"tbliterations_ads_tmp");
													
													$transcation_ads_log = $db_obj->selectSRow(array("*"),"tbliterations_ads_tmp","recored_added='".$s_record_added."' and iteration_id='".$iteration_id."'");
							
													$iteration_Ads_Id = $transcation_ads_log["id"];
													
													if($sites_table==1){
														$transcation_type = 'alexa';
														//$site_full_url = $this->getAlexaSiteUrl($this->site_id);
													}else{
														$transcation_type = 'auto';
														//$site_full_url = $this->getSiteUrl($this->site_id);
													}
													
													$s_ad_url_id = $this->getAdUrlId($s_link);
													
													$this->unique_id = $transcation_unqiue_id_2 = $iteration_id.uniqid();
													
													$transcations_data = array("site_id" => $url, 
																			   "home_page_image" => $this->finalRedirectedImg,
																			   "ad_image_tmp" => $iteration_Ads_Id,
																			   "transcation_type" => $transcation_type,
																			   "recored_added" => $s_record_added,
																			   "iteration_id" => $iteration_id,
																			   "ad_url" => $s_ad_url_id,
																			   "unique_id" => $transcation_unqiue_id_2);
													
													$db_obj->insert($transcations_data,"tbltranscations");
													
													$transcation_log = $db_obj->selectSRow(array("*"),"tbltranscations","recored_added='".$s_record_added."' and iteration_id=$iteration_id");
								
													$transcaion_table_id = $transcation_log["id"];
													
													exec("pkill tcpdump");
													
													exec("/usr/sbin/tcpdump -i any -A -s 0 'tcp port 80 and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)' > /var/www/images/logs/".$transcaion_table_id."_log.log 2>&1  & echo $!",$shark_output);
							
													$sharkID = (int)$shark_output[0];
													
													$this->logIt("Shark Output : ".$sharkID);
													
													$this->logIt("http://79.99.65.139/logs/".$transcaion_table_id."_log.log");
													
													$this->logIt("tshark started");
													
													 /*
													 * If the link is not in array or appear first time
													 */
													if (!in_array($s_link, $ads_urls)) {
														
														$status_array = get_headers($s_link);
														
														$src_header = "";
															
														for($si = 0; $si<sizeof($status_array); $si++){
															
															if(strpos($status_array[$si], "HTTP/") !== false){
																
																$src_header.='<p>'.$status_array[$si].'</p>';
																
															}
															if(strpos($status_array[$si], "Location") !== false){

																
																$src_header.='<p>'.$status_array[$si].'</p>';
																
															}
															
														}
														
														//$src_header.='<p>- - - - - - - - - - - - - </p>';
														
														$status = $src_header;
														
														if(strpos($status_2,"market://details")!==false){
									
															$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Play Store")); 	
															
														}
														if(strpos($status_2,"https://play.google")!==false){
									
															$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Play Store")); 	
															
														}
														
														if(strpos($status_2,"https://itunes.")!==false){
															
															$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Apple Store")); 	
															
														}
														
														$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("http_status"=>$status)); 
														
														$this->logIt($db_obj->sql);
														
														$ads_urls[] = $s_link;
														
														
														$s_hrf->getLocationOnScreenOnceScrolledIntoView();
									
														$new_home_with_ad_2 = "scrolled_2_".rand().'_'.uniqid().'.png';
														
														$this->takeScreenshot($this->sys_path.$new_home_with_ad_2);
														
														if(file_exists($this->sys_path.$new_home_with_ad_2)){
														
															$transcations_update_home_2 = array("home_page_image" => $this->http_path.$new_home_with_ad_2);
																			
															$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, $transcations_update_home_2); 
														
														}
														
														$s_hrf->click();
														
														$this->logIt("Going to click sub iframe link");
										
														$sub_xa = 0;
														
														$subHandle = $this->getWindowHandles();
															
														foreach ($subHandle as $winSubHandle) {
															
															if ($winSubHandle === end($subHandle)) {
																
																try {
																	
																	$this->switchTo()->window($winSubHandle);
																	
																	$this->sleepBrowser(10);
																	
																	$adSource = $this->getPageSource();
																	
																	$adURL = $this->getCurrentURL();
																	
																	//$this->manage()->timeouts()->pageLoadTimeout(15);
																	
																	if ($this->getMainDomain($adURL)!=$this->getMainDomain($url)) {
																		
																		$ad_name_2 = '2_landing_'.uniqid().'.png';
																		
																		$this->takeScreenshot($this->sys_path.$ad_name_2);
																		
																		$insertVeriLog_level1  = array('site_id'=>$this->site_id,
																										'master_iteration_id' => $iteration_id,
																										'iteration_ads_id' => $iteration_Ads_Id,
																										'userAgent_id' => $this->agent_id,
																										'platform_id' => $this->platform,
																										'serverIPAddress' => $this->serverIPAddress,
																										'final_redirected_url' => $adURL,
																										'finalRedirectedImg' => $this->http_path.$ad_name_2,
																										'deviceScreenShot' =>'',
																										'video_url' => '',
																										'alertMessage' => $this->alertMessage,
																										'log_datetime' => date("Y-m-d H:i:s"),
																										'traceout' => $this->traceout);
																		
																		$level1_iteration_id = $db_obj->insert($insertVeriLog_level1, "tbliterations");
																		
																		$transcations_update_data = array("landing_page_image" => $this->http_path.$ad_name_2,
																										  "final_url" => $adURL);
																		//$transcationsID = $this->transcaion_table_id;
																		
																		exec("kill -9 $sharkID");
																		
																		if(empty($adSource)){
														
																			$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"White Page"));
																				
																		}
																		
																		$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, $transcations_update_data); 
																		
																		//$this->logIt($db_obj->sql);
																	}
													
																	$this->switchTo()->Window($currentWindow);
																} catch (Exception $e) {
																	$this->logIt("------------------------------------------");
																	$this->logIt("Caught the error: ".$e->getMessage());
																	$this->logIt("------------------------------------------");
																	
																	$this->switchTo()->Window($currentWindow);
																	
																	$this->logIt("Exception Thrown while parsing the element");
																}
															}
														}
													}
													
													}
												
												}
												/*
												* If link and img src is not empty end here 
												*/
												
											}
										}
									}
									
									$this->switchTo()->Window($currentWindow);
									$this->switchTo()->frame($iframe);
									
									$this->logIt("Switching back to main iFrame");
								} catch(Exception $sif) {
									$this->logIt("Exception thrown while parsing sub iFrame");
									
									$this->logIt("------------------------------------------");
									$this->logIt("Caught the error: ".$sif->getMessage());
									$this->logIt("------------------------------------------");

									
									$this->switchTo()->Window($currentWindow);
									$this->switchTo()->frame($iframe);
								
								}
							}
						} catch(Exception $s_exp) {
							$this->switchTo()->Window($currentWindow);
							$this->switchTo()->frame($iframe);
							
							$this->logIt("Exception thrown here");
							$this->logIt("------------------------------------------");
							$this->logIt("Caught the error: ".$s_exp->getMessage());
							$this->logIt("------------------------------------------");
						}
					}
					
					$ads_urls_2 = array();
					
					$hrefs_2 = $this->findElements(WebDriverBy::tagName('a'));
					
					if ($hrefs_) {
						foreach ($hrefs as $hrf_2) {
							$this->logIt("-----Found Href : ".$as++."-----");
							
							$link_2 = $hrf_2->getAttribute('href');
							
							$innerHTML_2 = $hrf_2->getAttribute('innerHTML');
							
							$this->executeScript(" var change = document.getElementsByTagName('a');
											for (var i = 0; i < change.length; i++) {
												  change[i].setAttribute('target', '_blank');
											}");
							
							$value_2 = '';
							
							try {
								$html = new simple_html_dom();
								
								$html->load($innerHTML_2);
								
								foreach ($html->find('img') as $element) {
									if(!empty($element->src)) {
										$value_2 = $element->src;
									}
								}
							} catch(Exception $ac) {
								$this->logIt("No Image found");	
							}
							
							$this->logIt("--- Href : ".$link_2);
							
							$this->logIt("--- Img : ".$value_2);
							
							if(strpos($link_2,"mailto:")===false and strpos($link_2,"whatsapp://send?")===false and strpos($link_2,"twitter.com")===false and strpos($link_2,"facebook.com")===false and substr($link_2, -4)!=='.apk'){
							
								if (!empty($link_2) and !empty($value_2)) {
										
									
								$ads_ext_path_2 = pathinfo($value_2);
								
								$ad_ext_2 = $ads_ext_path_2['extension'];
								
								if(!empty($ad_ext_2)){
									$img = explode("?",$ad_ext_2);
									$ad_ext_2 = strtolower($img[0]);
									$ext_array = array("jpg","png","gif","jpeg","tif");
									if(!in_array($ad_ext_2,$ext_array)){
										$ad_ext_2 = 'png';
									}
									
									$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.'.$ad_ext_2;
								}else{
									$ad_local_ad_img = $iteration_id.'_'.uniqid().'_ad_screenShot.png';
								}
								
								$ad_image_contents = file_get_contents($value_2);
								
								if (file_put_contents($this->sys_path.$ad_local_ad_img, $ad_image_contents)) {
									$local_ad_imag = $ad_local_ad_img;
									$ad_image = $this->http_path.$ad_local_ad_img;
								} else {
									$local_ad_imag = '';
									$ad_image = $value_2;
								}
								$main_img_att_3 = getimagesize($this->sys_path.$ad_local_ad_img);
							
								$this->logIt("--- Local Img : ".$this->http_path.$ad_local_ad_img);
								
								$this->logIt("--- Width : ".$main_img_att_3[0]);
								
								$this->logIt("--- Height : ".$main_img_att_3[1]);
								
								if($main_img_att_3[0]>30 and $main_img_att_3[1]>30){
								
								$ad_record_added = date("Y-m-d H:i:s");
							
								$iteration_Ads_2 = array("iteration_id" => $iteration_id,
													   "ad_screenShot" => $value_2,
													   "ad_screenShot_local" => $local_ad_imag,
													   "recored_added" => $ad_record_added);
							
								$value_2 = '';
												
								$db_obj->insert($iteration_Ads_2,"tbliterations_ads_tmp");
								
								$transcation_ads_log = $db_obj->selectSRow(array("*"),"tbliterations_ads_tmp","recored_added='".$ad_record_added."' and iteration_id='".$iteration_id."'");
							
								$iteration_Ads_Id = $transcation_ads_log["id"];
								
								if($sites_table==1){
									$transcation_type = 'alexa';
									//$site_full_url = $this->getAlexaSiteUrl($site_id);
								}else{
									$transcation_type = 'auto';
									//$site_full_url = $this->getSiteUrl($site_id);
								}
								
								$ad_url_id = $this->getAdUrlId($link_2);
								
								$recored_added_3 = date("Y-m-d H:i:s");
								
								$this->unique_id = $transcation_unqiue_id_3 = $iteration_id.uniqid();
		
								$transcations_data_2 = array("site_id" => $url,
														   "home_page_image" => $this->finalRedirectedImg,
														   "ad_image_tmp" => $iteration_Ads_Id,
														   "transcation_type" => $transcation_type,
														   "recored_added" => $recored_added_3,
														   "iteration_id" => $iteration_id,
															"ad_url" => $ad_url_id,
															"unique_id" => $transcation_unqiue_id_3);
												
								$db_obj->insert($transcations_data_2,"tbltranscations");
								
								$transcation_log = $db_obj->selectSRow(array("*"),"tbltranscations","recored_added='".$recored_added_3."' and iteration_id=$iteration_id");
								
								$transcaion_table_id = $transcation_log["id"];
								
								exec("pkill tcpdump");
								
								exec("/usr/sbin/tcpdump -i any -A -s 0 'tcp port 80 and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)' > /var/www/images/logs/".$transcaion_table_id."_log.log 2>&1  & echo $!",$shark_output);
							
								$sharkID = (int)$shark_output[0];
								
								$this->logIt("Shark Output : ".$sharkID);
								
								$this->logIt("tshark started");
								
								
								if (!in_array($link_2, $ads_urls_2)) {
									
									$status_array_2 = get_headers($link_2);
													
									$src_header_2 = "";
										
									for($si = 0; $si<sizeof($status_array_2); $si++){
										
										if(strpos($status_array_2[$si], "HTTP/") !== false){
											
											$src_header_2.='<p>'.$status_array_2[$si].'</p>';
											
										}
										if(strpos($status_array_2[$si], "Location") !== false){
											
											$src_header_2.='<p>'.$status_array_2[$si].'</p>';
											
										}
										
									}
									
												
									$status_2 = $src_header_2;
									
									if(strpos($status_2,"market://details")!==false){
									
										$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Play Store")); 	
										
									}
									
									if(strpos($status_2,"https://play.google")!==false){
										
										$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Play Store")); 	
																
									}
									
									if(strpos($status_2,"https://itunes.")!==false){
										
										$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"Apple Store")); 	
										
									}
									
									$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("http_status"=>$status_2)); 
									
									//$this->logIt($db_obj->sql);
									
									$ads_urls_2[] = $link_2;
								
									$this->logIt("Going to click main iframe link");
									
									$hrf_2->getLocationOnScreenOnceScrolledIntoView();
								
									$new_home_with_ad_3 = "scrolled_3_".rand().'_'.uniqid().'.png';
									
									$this->takeScreenshot($this->sys_path.$new_home_with_ad_3);
									
									if(file_exists($this->sys_path.$new_home_with_ad_3)){
									
										$transcations_update_home_3 = array("home_page_image" => $this->http_path.$new_home_with_ad_3);
														
										$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, $transcations_update_home_3); 
									
									}
									
									$hrf_2->click();
								
									$allHandles_2 = $this->getWindowHandles();
								
									$xa = 0;
									
									foreach ($allHandles_2 as $winHandle) {
										
										if ($winHandle === end($allHandles)) {
										
											try {
										
												$this->switchTo()->window($winHandle);
												
												$this->sleepBrowser(10);
												
												$adSource = $this->getPageSource();
												
												$adURL = $this->getCurrentURL();
												
												//$this->manage()->timeouts()->pageLoadTimeout(15);
												
												if ($this->getMainDomain($adURL)!=$this->getMainDomain($url)) {
													
													$ad_name_3 = '3_landing_'.uniqid().'.png';
													
													$this->takeScreenshot($this->sys_path.$ad_name_3);
													
													$insertVeriLog_level1  = array('site_id'=>$this->site_id,
																					'master_iteration_id' => $iteration_id,
																					'iteration_ads_id' => $iteration_Ads_Id,
																					'userAgent_id' => $this->agent_id,
																					'platform_id' => $this->platform,
																					'serverIPAddress' => $this->serverIPAddress,
																					'final_redirected_url' => $adURL,
																					'finalRedirectedImg' => $this->http_path.$ad_name_3,
																					'deviceScreenShot' =>'',
																					'video_url' => '',
																					'alertMessage' => $this->alertMessage,
																					'log_datetime' => date("Y-m-d H:i:s"),
																					'traceout' => $this->traceout);
													
													$level1_iteration_id = $db_obj->insert($insertVeriLog_level1, "tbliterations");
													
													//$log_tshar_headers = file_get_contents('http://79.99.65.139/logs/'.$transcations_rec_ID_3.'_log.log');
													
													$transcations_update_data = array("landing_page_image" => $this->http_path.$ad_name_3,
																					  "final_url" => $adURL);
													//$transcationsID = $this->transcaion_table_id;
													
													exec("kill -9 $sharkID");
																		
													$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, $transcations_update_data);
													
													if(empty($adSource)){
														
															$db_obj->updateCondition("tbltranscations", "id=".$transcaion_table_id, array("landing_status"=>"White Page"));
														
													}
													
													//$this->logIt($db_obj->sql);
												}
												
												$this->executeScript("window.close();");
											
												$this->logIt("Clicking process completed");
												
												$this->switchTo()->Window($currentWindow);
												
											} catch (Exception $e) {
												
												$this->switchTo()->Window($currentWindow);
												
												$this->logIt("Exception Thrown while parsing the element one");
												
												$this->logIt("------------------------------------------");
												
												$this->logIt("Caught the error: ".$e->getMessage());
												
												$this->logIt("------------------------------------------");
											}
										}
									}
								}
								}
							
							
							
								}
							
							}
						}
						$as = 0;
					}
					
					$this->switchTo()->defaultContent();
				} catch (Exception $e) {
					$this->logIt("Element not visible main iFrame");
					
					$this->logIt("------------------------------------------");
					
					$this->logIt("Caught the error: ".$e->getMessage());
					
					$this->logIt("------------------------------------------");
					
					$this->switchTo()->Window($currentWindow);
				}
			}
		
		}
		
		function browseAdsSites($iteration_id,$sites_table) {
			
			$db_obj = new Dbase();
			
			$rows = $db_obj->select(array("a.iteration_id,b.iteration_id,c.ad_url as site_url"), "tbliterations_ads as a LEFT JOIN tbltranscations as b ON b.ad_image=a.id LEFT JOIN tbliterations_ads_url as c ON c.id=b.ad_url", "a.iteration_id =".$iteration_id);
			
			$this->logIt($db_obj->sql);
			
			if (count($rows)>0) {
				foreach($rows as $row) {
					if(!empty($row->site_url)){
						$ad_iteration_id[] = $this->browseSite($row->site_url, $iteration_id,$sites_table);
					}
				}	
			}
			
			return $ad_iteration_id;
		}
		
		function logIt($obj, $is_array=false) {
			$tmpStr = "";
			if ($this->isEcho) {
				if ($is_array) {
					//echo "<pre";
					print_r($obj);
					//echo "<pre>";
					
					$tmpStr = implode("\n", $obj);
				} else {
					echo "\n-----------------------------------\n".
						 $obj.
						 "\n-----------------------------------\n";	
						 
					$tmpStr = $obj;
				}	 
			}
			
			$this->execution_log .= $tmpStr;
		}
		
		function setSiteId($site_id) {
			$this->site_id = $site_id;	
		}
		
		function setPlateformId($plateform_id) {
			$this->platform = $plateform_id;	
		}
		
		function setAgentId($agent_id) {
			$this->agent_id = $agent_id;	
		}
		
		function getSiteUrl() {
			$db_obj = new Dbase();

			$this->logIt("Getting site_url from tblsites........");
			
			$sites = $db_obj->selectSRow(array("*"),"tblsites","id=".$this->site_id);
			
			$site_url = $this->addhttp($sites["url"]);
			
			$this->logIt("URL : ".$site_url);
			
			return $site_url;
		}
		
		function getAdUrlId($url){
			
			$db_obj = new Dbase();
		
			$ad_url = $db_obj->selectSRow(array("*"),"tbliterations_ads_url","ad_url='".$url."'");
			
			if(!empty($ad_url["id"])){
			
				return $ad_url["id"];
			
			}else{
			
				$url_data = array("ad_url"=>$url,"date_added"=>date("Y-m-d H:i:s"));

				$ad_url_id = $db_obj->insert($url_data,"tbliterations_ads_url");
				
				return $ad_url_id;
			
			}
		
		}
		
		function getAlexaSiteUrl() {
			$db_obj = new Dbase();
			
			$this->logIt("Getting site_url from tblsites........");
			
			$sites = $db_obj->selectSRow(array("*"),"tbl_url","id=".$this->site_id);
			
			$site_url = $this->addhttp($sites["url"]);
			
			$this->logIt("URL : ".$site_url);
			
			return $site_url;
		}
		
		function addhttp($url) {
			$url = preg_replace("/[\\n\\r]+/", "", $url);
			if (!preg_match("@^https?://@i", $url) && !preg_match("@^ftps?://@i", $url)) {
				$url = "http://" . $url;
			}
			return $url;
		}		
		
		function getAgent() {
			$db_obj = new Dbase();

			$this->logIt("Getting Agent ID from tbluserAgent");
			
			$agents = $db_obj->selectSRow(array("*"),"tbluseragent","id=".$this->agent_id);
			
			$agent = $agents["value"];
			
			$this->logIt("Agent : ".$agent);
			
			return $agent;
		}
		
		function setServerIPAddress() {
			$this->serverIPAddress = file_get_contents('http://monitoringservice.co/workflow/s.php?variable=REMOTE_ADDR');
		}
		
		function quitBrowser() {
			
			$this->logIt("Going to quit the browser");
			
			$this->quit();	
		}
		
		function sleepBrowser($str){
			waitfor($str);
		}
		
		
		function files_are_equal($a, $b){
		  // Check if filesize is different
		  if(filesize($a) !== filesize($b))
		   return false;
		  
		  // Check if content is different
		  $ah = fopen($a, 'rb');
		  $bh = fopen($b, 'rb');
		  
		  $result = true;
		  while(!feof($ah)) {
		   if(fread($ah, 8192) != fread($bh, 8192)) {
			$result = false;
			break;
		   }
		  }
		  
		  fclose($ah);
		  fclose($bh);
		  
		  return $result;
		 }
		
		
		function startSeleniumServer(){
			
			 
	
			//exec("export DISPLAY=:15.0");
			
			//exec("java -Xmx1024M -Dwebdriver.chrome.driver=/opt/selenium/selenium_server/chromedriver -jar /opt/selenium/selenium_server/selenium-server-standalone-2.46.0.jar  -port 4445 > /var/www/my.log 2>&1 & ", $output);
			
			exec("java -Xmx1024M -Dwebdriver.chrome.driver=/opt/selenium/selenium_server/chromedriver -jar /opt/selenium/selenium_server/selenium-server-standalone-2.46.0.jar  -port 4445 2>&1", $output);
			
			echo "Server Running\n";
			
			print_r($output);
			
		}
		
	   function stopSeleniumServer(){
		   
		   echo "Server Stoppped\n";
		   
		   /*
		   exec("ps -eaf|grep selenium",$output);
		   
		   $str = preg_replace('/\n+|\t+|\s+/','_',$output[0]);
		   
		   //print_r($str);
		   
		   $cm = explode("_",$str);
			
		   //print_r($cm);
		   
		   if(is_numeric($cm[1])){
			
				exec("kill -9 ".$cm[1]);
			
			}*/
			exec("pkill java");
		   //exec("kill -9 ".$cm[5]);
			
		}
		
		
		function getMainDomain($url = SITE_URL)
		{
			preg_match("/[a-z0-9\-]{1,63}\.[a-z\.]{2,6}$/", parse_url($url, PHP_URL_HOST), $_domain_tld);
			return $_domain_tld[0];
		}
		
		
	}
?>
