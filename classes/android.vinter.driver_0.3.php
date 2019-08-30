<?php
	// netstat to check if its listening at tcpip port
	// tcpip [port]
	// Restart VNC
	// forward tcp:[port] tcp:[port]
	//CURRENT NETWORK: dumpsys connectivity|grep -A 1 "Active network"
	//CURRENT ACTIVITY: dumpsys window windows | grep -E 'mCurrentFocus|mFocusedApp'

	class Android_Vinter_Driver {
		var $isEcho;
		var $working_dir;
		var $app_dir;
		var $adbPath;
		var $androidViewClient_path;
		var $sql_dir;
		var $device_logs_dir;
		var $configs;
		var $execution_log;
		var $sql_log;
		var $session_xml_file;
		var $tcpipMaxLimit;
		var $imagesLocation;
		var $http_imagesLocation;
		var $maxWhileExecs;
		var $maxWhileExecIndex;
		var $img2txt;
		var $final_url ;
		var $ct_data ;
		var $maxScriptCall = 5;
		var $scriptCallIndex = 1;
		var $past_browser_activity ;

		var $veribrowse_log_file ;


		function setGlobalVars($configs, $adbPath, $androidViewClient_path,
							   $working_dir, $app_dir, $sql_dir, $device_logs_dir,
							   $imagesLocation, $http_imagesLocation,
							   $isEcho, $tcpipMaxLimit, $maxWhileExecs) {
			$this->configs = $configs;
			$this->working_dir = $working_dir;
			$this->app_dir = $app_dir;
			$this->adbPath = $adbPath;
			$this->androidViewClient_path = $androidViewClient_path;
			$this->sql_dir = $sql_dir;
			$this->device_logs_dir = $device_logs_dir;
			$this->imagesLocation = $imagesLocation;
			$this->http_imagesLocation = $http_imagesLocation;
			$this->isEcho = $isEcho;
			$this->tcpipMaxLimit = $tcpipMaxLimit;
			$this->maxWhileExecs = $maxWhileExecs;
			$this->logIt($this, true);
		}

		function executeAction($action) {
			$action = trim(str_replace("\n", "", str_replace("\r", "", $action)));

			$tokens = explode(" ", $action);
			$response = $this->checkTokenSwitch($tokens);

			return $response;
		}

		function checkTokenSwitch($tokens) {
			switch (trim($tokens[0])) {
				case "APP":
					$this->executeAPP($tokens);
					break;
				case "DEVICE":
					$this->executeDevice($tokens);
					break;
				case "WAIT":
					$this->wait($tokens);
					break;
				case "BUTTON":
					$this->executeAppium($tokens);
					break;
				case "TAP":
					$this->executeAppium($tokens);
					break;
				case "FIND":
					$this->executeAppium($tokens);
					break;
				case "TAKE_SCREENSHOT":
					$this->executeAppium($tokens);
					break;
				case "BROWSER":
					return $this->executeBrowser($tokens);
					break;
				case "WHILE":
					$this->executeLang($tokens);
					break;
				case "LOOP":
					$this->executeLang($tokens);
					break;
				case "IF":
					$this->executeLang($tokens);
					break;
				case "FUNCTION":
					$this->executeLang($tokens);
					break;
				case "EXIT":
					$this->closeApp();
					exit("Exit Action called!");
				case "HOLD":
					$this->logIt("This event is created to for debugging the Interpreter!");
					$this->logIt($this->getCurrentViews(), true);
					break;
				case "IMG2TXT":
					// would take screenshot send to img2txt api and return text in $this->img2txt
					$this->logIt("It would take screenshot send to img2txt api and return text!");
					$this->executeOnVM($tokens);
					break;
				case "VM_CMD":
					// would run command on VM
					$this->logIt("It would run command on VM!");
					$this->executeOnVM($tokens);
					break;
				case "SEARCH":
					// search in $this->img2txt
					$this->logIt('It would search in $this->img2txt api and return 1 or 0 accordingly!');
					$this->searchKeyword($tokens);
					break;
				default:
					exit("Action not found: Syntax Error!");
			}
		}

		function searchKeyword($tokens) {
			if (trim($tokens[1])=="IMG2TXT") {
				$this->executeOnVM($tokens);
				$keyword = trim(str_replace("keyword=", "", $tokens[2]));
				if (strpos($this->img2txt, $keyword)!==false) {
					return 1;
				} else {
					return 0;
				}
			}
		}

		function executeOnVM($tokens) {
			if (trim($tokens[0])=="IMG2TXT") {
				$img_srouce = str_replace("http://79.99.65.139", "/var/www", $this->takeSaveScreenshot());
				$this->executeCMD("rsync --remove-source-files -avp -e ssh ".$img_srouce." -e ssh root@79.99.65.139:".$img_srouce);

				$text =implode("\n",  $this->executeCMD("ssh root@79.99.65.139 php /var/www/img2txt/vinter.php ".$img_srouce));

				$this->img2txt = $text;
			} else if (trim($tokens[0])=="VM_CMD") {
				$text_str = str_replace("<", "",
										str_replace(">", "",
										str_replace("VM_CMD ", "",
											implode(" ", $tokens))));

				$text = implode("\n",  $this->executeCMD("ssh root@79.99.65.139 ".$text_str));

				$this->logIt($text);
			}
		}

		function executeAPP($tokens) {
			if (trim($tokens[1])=="LAUNCH") {
				if ($this->configs->app_package=="") {
					$output = $this->executeOnDevice("install ".$this->configs->app_path);

					$app_name_apk = trim(str_replace($this->app_dir, "", $this->configs->app_path));
					$output = $this->executeOnDevice("shell dumpsys package packages|grep -A 18 ".$app_name_apk." |grep applicationInfo| awk '{print $2}'");

					$app_package_name = str_replace("}", "", trim($output));
					$this->configs->app_package = $app_package_name;
					$this->logIt($app_package_name);

					$sql_str = "UPDATE tblapps SET ".
							   "package = '".$app_package_name."'".
							   " WHERE ".
							   "id = ".$this->configs->app_id;

					$this->saveSQL($sql_str, "APP Package Updated");
				} else {
					$output = $this->executeOnDevice("shell am force-stop ".$this->configs->app_package);
				}

				$output = $this->executeOnDevice("shell monkey -p ".$this->configs->app_package." ".
												 "-c android.intent.category.LAUNCHER 1");

				$sql_str = "INSERT INTO tblapp_sequence_logs ".
						   "(device_config_id, appium_session_id, app_sequence_id, date_added)".
						   " VALUES ".
						   "('".$this->configs->device_config_id."', '".$this->configs->appium_session_id."', '".$this->configs->app_sequence_id."', '".date("Y-m-d H:i:s")."')";

				$this->saveSQL($sql_str, "APP Launch - Session id inserted");

				$output_hostname = implode("\n", $this->executeCMD("hostname"));

				$date_folder = $this->device_logs_dir.date("Y-m-d")."/";

				if (!file_exists($date_folder)) {
					mkdir($date_folder, 0777);
					if ($echo) {
						$this->logIt("Date Folder ".$date_folder." CREATED!\n");
					}
				} else {
					if ($echo) {
						$this->logIt("Date Folder ".$date_folder." Already Exsists!\n");
					}
				}

				$hour_folder = $date_folder.date("H")."/";

				if (!file_exists($hour_folder)) {
					mkdir($hour_folder, 0777);
					if ($echo) {
						$this->logIt("Hour Fodler ".$hour_folder." CREATED!\n");
					}
				} else {
					if ($echo) {
						$this->logIt("Hour Fodler ".$hour_folder." Already Exsists!\n");
					}
				}

				$this->logcat_file = $hour_folder."logcat_".$output_hostname."_".uniqid().".log";

				$this->veribrowse_log_file = $hour_folder."veribrowse_".$output_hostname."_".uniqid().".log";

				$output = $this->executeCMD("touch ".$this->logcat_file);

				$output = chmod($this->logcat_file, 0777);

				//$output = $this->executeOnDevice("shell logcat |grep http|grep -v 'W/System.err'|grep -v 'W/GeoLookout'|grep -v 'Application Cache Checking event'|grep -v 'Document was loaded from Application Cache with manifest'|grep -v 'Application Cache NoUpdate event'|grep -v 'www.googletagmanager.com/gtm.js' > ".$this->logcat_file." &");
				$output = $this->executeOnDevice("shell logcat |grep -i \"https://\|http://\"|grep -v \"VeriBrowse_\" > ".$this->logcat_file." &");
			} else if (trim($tokens[1])=="CLEAR_DATA") {
				$output = $this->executeOnDevice("shell pm clear ".$this->configs->app_package);
			} else if (trim($tokens[1])=="CLOSE") {
				$kill_logcat = $this->executeOnDevice("shell ps |grep logcat");

				$ex = explode("\n", $kill_logcat);
				for ($i=0; $i<count($ex); $i++) {
					$ex1 = explode(" ", $ex[$i]);
					$ps = array();
					for ($j=0; $j<count($ex1); $j++) {
						if ($ex1[$j]!="") {
							$ps[] = $ex1[$j];
						}
					}

					$this->logIt($ps, true);
					$output = $this->executeOnDevice("shell kill -9 ".$ps[1]);
				}
				//$output = $this->executeOnDevice("kill-server");

				$logcat_output = file_get_contents($this->logcat_file);

				$this->logIt("past activity was");

				$this->logIt($this->past_browser_activity);

				$log_response ="";

				if($this->past_browser_activity=="acr.browser.lightning.activity.MainActivity")
				{

					//$this->logIt("under log if");

						$vs_log_response = $this->executeOnDevice("shell cat /data/local/tmp/veri/browser.log");

						//$this->logIt("log response 1");

						//$this->logIt($log_response);



						$vs_log_response = str_replace("Hello world!", "", $vs_log_response);

						file_put_contents( $this->veribrowse_log_file , $vs_log_response);

						$this->waitfor(10);

						$log_response = str_replace($this->device_logs_dir, $this->http_imagesLocation, $this->veribrowse_log_file);




						$this->executeOnDevice("shell rm /data/local/tmp/veri/browser.log");

						$this->executeOnDevice("shell touch /data/local/tmp/veri/browser.log");

						$this->executeOnDevice("shell chmod 777 /data/local/tmp/veri/browser.log");




				}
				else
				{

					$log_response = str_replace($this->device_logs_dir, $this->http_imagesLocation, $this->logcat_file);

				}


					$this->logIt("log files link");


				$this->logIt("log response");

				$this->logIt($log_response);


				$this->logIt("log cat file");

				$this->logIt($this->logcat_file);


				//exit;



			/*	$sql_str = "UPDATE tblapp_sequence_logs SET ".
						   "execution_log = '".str_replace($this->device_logs_dir, $this->http_imagesLocation, $this->logcat_file)."' ".
						   "WHERE id = [app_sequence_log_id]";*/

						   	$sql_str = "UPDATE tblapp_sequence_logs SET ".
						   "execution_log = '".$log_response."' ".
						   "WHERE id = [app_sequence_log_id]";

				$this->saveSQL($sql_str, "APP Close - Execution Log Updated");

				//unlink($this->logcat_file);

				//$output = $this->executeOnDevice("start-server");
				$output = $this->executeOnDevice("forward tcp:".$this->configs->device_vnc_port." tcp:".$this->configs->device_vnc_port."");
				$output = $this->executeOnDevice("shell am force-stop com.android.chrome");
				$output = $this->executeOnDevice("shell am force-stop com.android.browser.provider");

				$output = $this->executeOnDevice("shell am force-stop ".$this->configs->app_package);
			}
		}

		function executeDevice($tokens) {
			switch (trim($tokens[1])) {
				case "ip_conection_type":
					$this->logIt("Check Device IP Connection Type");

					$output = $this->executeOnDevice('shell dumpsys connectivity|grep -A 1 "Active network"');

					$ex = explode("\n", $output);
					$active_network = trim(str_replace("Active network:", "", $ex[0]));
					$network_info = str_replace(",", ",\n", trim(str_replace("NetworkInfo:", "", $ex[1])));

					$this->logIt("Active Network: ".$active_network);
					$this->logIt("Network Info:\n ".$network_info);

					$sql_str = "UPDATE tblapp_sequence_logs SET ".
							   "active_network = '".$active_network."', ".
							   "network_info = '".$network_info."' ".
							   "WHERE id = [app_sequence_log_id]";
					$this->saveSQL($sql_str, "DEVICE ip_conection_type - Data Updated");
					break;
				case "CLEAR_BROWSER_CACHE":
					$this->logIt("DEVICE CLEAR_BROWSER_CACHE");

					$output = $this->executeOnDevice('shell pm clear com.android.chrome');

					$output = $this->executeOnDevice('shell pm clear com.sec.android.app.sbrowser');

					break;
				case "CLEAR_APP_CACHE":
					$this->logIt("DEVICE CLEAR_APP_CACHE");

					$output = $this->executeOnDevice('shell pm clear '.$this->configs->app_package);

					break;
				case "SHELL":
					$this->logIt("DEVICE SHELL <shell_command>");
					$text_str = str_replace("<", "",
											str_replace(">", "",
											str_replace("DEVICE SHELL ", "",
												implode(" ", $tokens))));

					$output = $this->executeOnDevice('shell '.$text_str);

					break;
				case "CMD":
					$this->logIt("DEVICE CMD <command>");
					$text_str = str_replace("<", "",
											str_replace(">", "",
											str_replace("DEVICE CMD ", "",
												implode(" ", $tokens))));

					$output = $this->executeOnDevice($text_str);

					break;
					case "BACK_BUTTON":
						$this->logIt("Clicking Device Back Button");

						$output = $this->executeOnDevice('shell input keyevent 4');
						break;
					case "START_LOG":
						$this->logIt("Start logging ON Device");

						$output_hostname = implode("\n", $this->executeCMD("hostname"));

						$date_folder = $this->device_logs_dir.date("Y-m-d")."/";

						if (!file_exists($date_folder)) {
							mkdir($date_folder, 0777);
							if ($echo) {
								$this->logIt("Date Folder ".$date_folder." CREATED!\n");
							}
						} else {
							if ($echo) {
								$this->logIt("Date Folder ".$date_folder." Already Exsists!\n");
							}
						}

						$hour_folder = $date_folder.date("H")."/";

						if (!file_exists($hour_folder)) {
							mkdir($hour_folder, 0777);
							if ($echo) {
								$this->logIt("Hour Fodler ".$hour_folder." CREATED!\n");
							}
						} else {
							if ($echo) {
								$this->logIt("Hour Fodler ".$hour_folder." Already Exsists!\n");
							}
						}

						$this->logcat_file = $hour_folder."logcat_".$output_hostname."_".$tokens[2].".log";
						$output = $this->executeCMD("touch ".$this->logcat_file);

						$output = chmod($this->logcat_file, 0777);

						$output = $this->executeOnDevice("shell logcat |grep http|grep -v 'W/System.err'|grep -v 'W/GeoLookout'|grep -v 'Application Cache Checking event'|grep -v 'Document was loaded from Application Cache with manifest'|grep -v 'Application Cache NoUpdate event'|grep -v 'www.googletagmanager.com/gtm.js' > ".$this->logcat_file." &");

						return $this->logcat_file;
						break;
				case "END_LOG":
						$kill_logcat = $this->executeOnDevice("shell ps |grep logcat");

						$ex = explode("\n", $kill_logcat);
						for ($i=0; $i<count($ex); $i++) {
							$ex1 = explode(" ", $ex[$i]);
							$ps = array();
							for ($j=0; $j<count($ex1); $j++) {
								if ($ex1[$j]!="") {
									$ps[] = $ex1[$j];
								}
							}

							$this->logIt($ps, true);
							$output = $this->executeOnDevice("shell kill -9 ".$ps[1]);
						}
				default:
					exit("ExecuteDevice: Syntax Error!");
			}
		}

		function wait($tokens) {
			if (is_numeric(trim($tokens[1])) && trim($tokens[2])=="seconds") {
				$this->waitfor(trim($tokens[1]));
			} else {
				exit("WAIT: Syntax Error!");
			}
		}

		function executeBrowser($tokens) {
			switch (trim($tokens[1])) {
				case "VIEW_SOURCE":
					$this->logIt('VIEW_SOURCE');

					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id);

					$ex = explode("android.widget.EditText ", implode("\n", $output));

					for ($i=0; $i<count($ex); $i++) {
						if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
							$str_url_ex = $ex[$i];

							$ex1 = explode(" ", $str_url_ex);
							$this->logIt($ex1, true);
							$edit_text_id = trim($ex1[0]);

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "acr.browser.barebones:id/search";

							$ex2 = explode($edit_text_id, $str_url_ex);
							$ex3 = explode("\n", $ex2[1]);

							$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "com.android.chrome:id/url_bar";

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						}
					}

					$this->recPythonGetSourceScrpt($edit_text_id);

					$source_dump = implode("\n", $this->executeCMD($this->androidViewClient_path.'tools/dump --all'));

					$ex_with = str_replace("content-desc=", "", $this->getTagValues("parent=android.webkit.WebView", "content-desc=", $source_dump));
					$ex_srcdmp = explode($ex_with, $source_dump);

					$source_dump1 = $ex_srcdmp[1];

					$ex_srcdmp1 = explode("View[ class=", $source_dump1);

					$ex_srcdmp2 = explode(" focusable=true focused=false uniqueId=", $ex_srcdmp1[0]);

					$source = "<html>\n".$ex_srcdmp2[0]."\n</html>";

					$this->saveFile($this->session_xml_file, "<webpage>\n\t<url>\n\t\t".$url_from_browser."\n\t</url>\n\t<source>\n\t\t".$source."\n\t</source>\n"."</webpage>\n", $msg);

					return $url_from_browser;
					break;
				case "SAVE_MARKUP":
						$this->logIt('VIEW_SOURCE');

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id);

						$ex = explode("android.widget.EditText ", implode("\n", $output));

						for ($i=0; $i<count($ex); $i++) {
							if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
								$str_url_ex = $ex[$i];

								$ex1 = explode(" ", $str_url_ex);
								$this->logIt($ex1, true);
								$edit_text_id = trim($ex1[0]);

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "acr.browser.barebones:id/search";

								$ex2 = explode($edit_text_id, $str_url_ex);
								$ex3 = explode("\n", $ex2[1]);

								$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.android.chrome:id/url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							}
						}

						$this->recPythonGetSourceScrpt($edit_text_id);

						$source_dump = implode("\n", $this->executeCMD($this->androidViewClient_path.'tools/dump --all'));

						$ex_with = str_replace("content-desc=", "", $this->getTagValues("parent=android.webkit.WebView", "content-desc=", $source_dump));
						$ex_srcdmp = explode($ex_with, $source_dump);

						$source_dump1 = $ex_srcdmp[1];

						$ex_srcdmp1 = explode("View[ class=", $source_dump1);

						$ex_srcdmp2 = explode(" focusable=true focused=false uniqueId=", $ex_srcdmp1[0]);

						$source = "<html>\n".$ex_srcdmp2[0]."\n</html>";

						$date_folder = $this->device_logs_dir.date("Y-m-d")."/";

						if (!file_exists($date_folder)) {
							mkdir($date_folder, 0777);
							if ($echo) {
								$this->logIt("Date Folder ".$date_folder." CREATED!\n");
							}
						} else {
							if ($echo) {
								$this->logIt("Date Folder ".$date_folder." Already Exsists!\n");
							}
						}

						$hour_folder = $date_folder.date("H")."/";

						if (!file_exists($hour_folder)) {
							mkdir($hour_folder, 0777);
							if ($echo) {
								$this->logIt("Hour Fodler ".$hour_folder." CREATED!\n");
							}
						} else {
							if ($echo) {
								$this->logIt("Hour Fodler ".$hour_folder." Already Exsists!\n");
							}
						}

						file_put_contents($hour_folder."markup_".$tokens[2].".html", $source);

						return $hour_folder."markup_".$output_hostname."_".$tokens[2].".html";
						break;
			case "URL":
				exec("/home/pi/adb/adb-linuxARMv6 shell input keyevent KEYCODE_POWER");
						$this->logIt('FInal URL');

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id);

						$ex = explode("android.widget.EditText ", implode("\n", $output));

						for ($i=0; $i<count($ex); $i++) {
							if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
								$str_url_ex = $ex[$i];

								$ex1 = explode(" ", $str_url_ex);
								$this->logIt($ex1, true);
								$edit_text_id = trim($ex1[0]);

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "acr.browser.barebones:id/search";

								$ex2 = explode($edit_text_id, $str_url_ex);
								$ex3 = explode("\n", $ex2[1]);

								$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.android.chrome:id/url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							}
						}
						exec("/home/pi/adb/adb-linuxARMv6 shell input keyevent KEYCODE_POWER");
						return $url_from_browser;
						break;
				case "API_URL":
					$this->logIt('API URL');

					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/browser-open-url.py '.
										$this->configs->device_adb_id." ".$tokens[2]." 2>&1	");
					$this->waitfor(10);

					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "com.android.chrome:id/url_bar"'." 2>&1");
					$this->waitfor(1);

					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id." 2>&1");

					$ex = explode("android.widget.EditText ", implode("\n", $output));

					for ($i=0; $i<count($ex); $i++) {
						if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
							$str_url_ex = $ex[$i];

							$ex1 = explode(" ", $str_url_ex);
							$this->logIt($ex1, true);
							$edit_text_id = trim($ex1[0]);

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "acr.browser.barebones:id/search";

							$ex2 = explode($edit_text_id, $str_url_ex);
							$ex3 = explode("\n", $ex2[1]);

							$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
							$str_url_ex = $ex[$i];
							$edit_text_id = "com.android.chrome:id/url_bar";

							$ex2 = explode("\n", $str_url_ex);

							$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
							$this->logIt('$url_from_browser -> '.$url_from_browser);
							break;
						}
					}

					$this->executeOnDevice("shell input keyevent KEYCODE_BACK");

					$img_path = $this->takeSaveScreenshot(false);
					$this->logIt('$img_path -> '.$img_path);
					$this->logIt('$url_from_browser -> '.$url_from_browser);

					return array("start_url" => $tokens[2], "img_path" => $img_path, "final_url" => $url_from_browser);
					break;
				case "SCREENSHOT":
						$this->logIt('SCREENSHOT');

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "com.android.chrome:id/url_bar"'." 2>&1");
						$this->waitfor(1);

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id." 2>&1");

						$ex = explode("android.widget.EditText ", implode("\n", $output));

						for ($i=0; $i<count($ex); $i++) {
							if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
								$str_url_ex = $ex[$i];

								$ex1 = explode(" ", $str_url_ex);
								$this->logIt($ex1, true);
								$edit_text_id = trim($ex1[0]);

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "acr.browser.barebones:id/search";

								$ex2 = explode($edit_text_id, $str_url_ex);
								$ex3 = explode("\n", $ex2[1]);

								$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
								$str_url_ex = $ex[$i];
								$edit_text_id = "com.android.chrome:id/url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							}
						}

						$this->executeOnDevice("shell input keyevent KEYCODE_BACK");

						$img_path = array("URL" => $url_from_browser,
														  "SCREENSHOT" => $this->takeSaveScreenshot(false));

						//$this->logIt('$img_path -> '.$img_path);

						return $img_path;
						break;
				case "JS":
						$this->logIt('Execute JS');

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id);

						$ex = explode("android.widget.EditText ", implode("\n", $output));

						for ($i=0; $i<count($ex); $i++) {
							if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {

								$package_name ="com.android.chrome";
								$activity_name="com.google.android.apps.chrome.Main";

								$str_url_ex = $ex[$i];

								$ex1 = explode(" ", $str_url_ex);
								$this->logIt($ex1, true);
								$edit_text_id = trim($ex1[0]);

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {

								$package_name ="acr.browser.barebones";
								$activity_name="acr.browser.lightning.activity.MainActivity";

								$str_url_ex = $ex[$i];
								$edit_text_id = "acr.browser.barebones:id/search";

								$ex2 = explode($edit_text_id, $str_url_ex);
								$ex3 = explode("\n", $ex2[1]);

								$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {

								$package_name ="com.sec.android.app.sbrowser";
								$activity_name="com.sec.android.app.sbrowser.SBrowserMainActivity";

								$str_url_ex = $ex[$i];
								$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {

								$package_name ="com.android.chrome";
								$activity_name="com.google.android.apps.chrome.Main";

								$str_url_ex = $ex[$i];
								$edit_text_id = "com.android.chrome:id/url_bar";

								$ex2 = explode("\n", $str_url_ex);

								$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
								$this->logIt('$url_from_browser -> '.$url_from_browser);
								break;
							}
						}

						$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'"'." 2>&1");

						$this->waitfor(1);

						## initialize  scriptCallIndex = 1
						$this->scriptCallIndex = 1;

						$this->recPythonExecJSScrpt($edit_text_id, "javascript:".base64_decode($tokens[2]),$package_name ,$activity_name);
						break;
				default:
					exit("executeBrowser: Syntax Error!");
			}
		}

		function recPythonExecJSScrpt($edit_text_id, $js , $package_name , $activity_name) {
			$output1 = implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.
															 'examples/execute_js.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'" "'.$js.'" "'.$package_name.'"  "'.$activity_name.'"'));

		 	$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py');

			$ex = explode("android.widget.EditText ", implode("\n", $output));

			for ($i=0; $i<count($ex); $i++) {
				if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
					$str_url_ex = $ex[$i];

					$ex1 = explode(" ", $str_url_ex);
					$this->logIt($ex1, true);
					$edit_text_id = trim($ex1[0]);

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
					$this->logIt('JS URL -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "acr.browser.barebones:id/search";

					$ex2 = explode($edit_text_id, $str_url_ex);
					$ex3 = explode("\n", $ex2[1]);

					$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
					$this->logIt('JS URL -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
					$this->logIt('JS URL -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "com.android.chrome:id/url_bar";

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
					$this->logIt('JS URL -> '.$url_from_browser);
					break;
				}
			}

			$js_str = $js;
			$this->logIt('RUN JS $url_from_browser -> '.$url_from_browser);

			//$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'"');
			//$this->executeOnDevice("shell input keyevent KEYCODE_DPAD_RIGHT");

			if ($url_from_browser!=$js_str) {
				if (strpos($js_str, $url_from_browser)!==false) {
					$remaining_str = str_replace($url_from_browser, "", $js_str);
					$output1 = implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/input_text.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'"'.' "'.$remaining_str.'"'));
				} else {
					$this->scriptCallIndex++;
					if ($this->scriptCallIndex<$this->maxScriptCall) {
						$this->recPythonExecJSScrpt($edit_text_id, $js , $package_name , $activity_name);
					} else {
						$this->logIt("Max SCript Limit Exausted!!!");
					}
				}
			}

			$this->executeOnDevice("shell input keyevent KEYCODE_ENTER");
			$this->waitfor(1);
		}


		function recPythonGetSourceScrpt($edit_text_id) {
			$output1 = implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/browser-view-page-source.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'"'));

			$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py');

			$ex = explode("android.widget.EditText ", implode("\n", $output));

			for ($i=0; $i<count($ex); $i++) {
				if (strpos($ex[$i], "Double tap this field to edit it. Search or type URL.")!==false) {
					$str_url_ex = $ex[$i];

					$ex1 = explode(" ", $str_url_ex);
					$this->logIt($ex1, true);
					$edit_text_id = trim($ex1[0]);

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = str_replace("Double tap this field to edit it. Search or type URL.", "", trim(str_replace($edit_text_id, "", $ex2[0])));
					$this->logIt('JS URL D -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "acr.browser.barebones:id/search ")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "acr.browser.barebones:id/search";

					$ex2 = explode($edit_text_id, $str_url_ex);
					$ex3 = explode("\n", $ex2[1]);

					$url_from_browser = trim(str_replace("acr.browser.barebones:id/search", "", $ex3[0]));
					$this->logIt('JS URL V -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "com.sec.android.app.sbrowser:id/sbrowser_url_bar")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "com.sec.android.app.sbrowser:id/sbrowser_url_bar";

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = trim(str_replace("com.sec.android.app.sbrowser:id/sbrowser_url_bar", "", $ex2[0]));
					$this->logIt('JS URL C -> '.$url_from_browser);
					break;
				} else if (strpos($ex[$i], "com.android.chrome:id/url_bar")!==false) {
					$str_url_ex = $ex[$i];
					$edit_text_id = "com.android.chrome:id/url_bar";

					$ex2 = explode("\n", $str_url_ex);

					$url_from_browser = trim(str_replace("com.android.chrome:id/url_bar", "", $ex2[0]));
					$this->logIt('JS URL C -> '.$url_from_browser);
					break;
				}
			}

			$js_str = "javascript:var ss=document.createElement('textarea');var str=document.getElementsByTagName('html')[0].innerHTML;document.body.innerHTML='';document.body.appendChild(ss);ss.innerHTML=str;alert(str);";

			if ($url_from_browser!=$js_str) {
				if (strpos($js_str, $url_from_browser)!==false) {
					$remaining_str = str_replace($url_from_browser, "", $js_str);
					$output1 = implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/input_text.py '.$this->configs->device_adb_id.' "'.$edit_text_id.'"'.' "'.$remaining_str.'"'));
				} else {
					$this->scriptCallIndex++;
					if ($this->scriptCallIndex<$this->maxScriptCall) {
						$this->recPythonGetSourceScrpt($edit_text_id);
					} else {
						$this->logIt("Max SCript Limit Exausted!!!");
					}
				}
			}

			$this->executeOnDevice("shell input keyevent KEYCODE_ENTER");
			$this->waitfor(5);
	        $output = $this->findButtonByTextNClick("OK");
			if (strpos($output, "bOK not found")!==false) {
				$this->scriptCallIndex++;
				if ($this->scriptCallIndex<$this->maxScriptCall) {
					$this->recPythonGetSourceScrpt($edit_text_id);
				} else {
					$this->logIt("Max SCript Limit Exausted!!!");
				}
			}
		}

		function getChromeAddrBarBounds() {
			$output = implode("\n", $this->executeCMD($this->androidViewClient_path.'tools/dump -b|grep EditText|grep "Double tap this field to edit it. Search or type URL."'));
			//((27, 55), (324, 105))

			$output = str_replace("(", "", $output);
			$output = str_replace(")", "", $output);
			$output = str_replace(" ", "", $output);

			$ex = explode(",", $ou);
			return $ex;
		}

		function findButtonByTextNClick($label) {
			return implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/click-button-by-text.py '.$this->configs->device_adb_id.' "'.$label.'"'));
		}

		function findBtnFromArrayNClick($json_label) {
			return implode("\n", $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/click-button-by-text-array.py '.$this->configs->device_adb_id.' \''.$json_label.'\''));
		}

		function executeAppium($tokens) {
			switch (trim($tokens[0])) {
				case "BUTTON":
					$this->logIt('BUTTON Label="????" Action="click"');
					$btn_str = str_replace("BUTTON ", "", implode(" ", $tokens));
					if (strpos($btn_str, "Label")!==false && strpos($btn_str, "Action")!==false) {
						$sub_tokens = explode("Label", trim($btn_str));
						$sub_tokens1 = explode("Action", trim($sub_tokens[1]));

						$label = str_replace("'", "", str_replace('"', "", str_replace('=', "", trim($sub_tokens1[0]))));
						$action = str_replace("'", "", str_replace('"', "", str_replace('=', "", trim($sub_tokens1[1]))));

						if ($label!="" && $action=="click") {
							$this->findButtonByTextNClick($label);
						} else {
							exit("executeAppium sub_tokens values: Syntax Error!");
						}
					} else {
						exit("executeAppium sub_tokens: Syntax Error!");
					}

					break;
				case "TAP":
					$this->logIt('TAP ???,???');

					$xy_str = str_replace("TAP ", "", implode(" ", $tokens));

					$sub_tokens = explode(",", $xy_str);
					if (is_numeric(trim($sub_tokens[0])) && is_numeric(trim($sub_tokens[1]))) {
						$x = trim($sub_tokens[0]);
						$y = trim($sub_tokens[1]);

						$this->logIt('Tapping '.$x." ".$y);
						$this->executeOnDevice("shell input tap ".$x." ".$y);
						//$this->executeOnDevice("shell input touchscreen swipe ".$x." ".$y." ".$x." ".$y." 10");
					} else {
						exit("executeAppium sub_tokens: Syntax Error!");
					}

					break;
				case "FIND":
					$this->logIt('FIND Selector=??? Name="???" Action="???"');

					$find_str = trim(str_replace("FIND ", "", implode(" ", $tokens)));

					if (strpos($find_str, "Selector")!==false &&
						strpos($find_str, "Name")!==false &&
						strpos($find_str, "Action")!==false) {

						$sub_tokens_act = explode("Action", $find_str);

						$selector_value = trim(str_replace("=", "", trim($this->getTagValues("Selector", "Name", $find_str))));
						$name_value = str_replace("'", "", str_replace('"', "", str_replace('=', "", trim($this->getTagValues("Name", "Action", $find_str)))));
						$action_value = str_replace("'", "", str_replace('"', "", str_replace('=', "", trim($sub_tokens_act[1]))));

						if ($selector_value=="VIEW" && $action_value=="click") {
							$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/viewserveractivity-new-activity.py '.$this->configs->device_adb_id.' "'.$name_value.'"');
						} else {
							exit("executeAppium FIND selector, action\n".$selector_value."\n".$action."\n".$name_value."\n: Syntax Error!");
						}
					} else {
						exit("executeAppium FIND sub_tokens: Syntax Error!");
					}

					break;
				case "TAKE_SCREENSHOT":
					$this->logIt('TAKE_SCREENSHOT');
					$screenshot_type = str_replace("'", "",
											str_replace('"', "",
												str_replace("TAKE_SCREENSHOT ", "", implode(" ", $tokens))
											)
										);

					if ($screenshot_type=="banner view") {
						$screenshot_type = 0;
					} else if ($screenshot_type=="Ad view") {
						$screenshot_type = 1;
					}

					$sql_str = "INSERT INTO tblapp_data ".
							   "(app_sequence_log_id, ".
							   " app_sequece_id,".
							   " screenshot_type,".
							   " screenshot_url,".
							   " date_added)".
							   " VALUES ".
							   "('[app_sequence_log_id]',
							     '".$this->configs->app_sequence_id."',
							     '".$screenshot_type."',
							     '".$this->takeSaveScreenshot()."',
							     '".date("Y-m-d H:i:s")."')";

					$this->saveSQL($sql_str, "APP Ad - Screenshot");
					break;
				default:
					exit("executeAppium: Syntax Error!");
			}
		}

		function takeSaveScreenshot($blackscreen=true) {

			$this->executeOnDevice("shell content insert --uri content://settings/system --bind name:s:accelerometer_rotation --bind value:i:0");

			/*if ($blackscreen) {
				$output = $this->executeOnDevice("shell dumpsys input_method | grep mScreenOn");

				$ex = explode("mScreenOn=", trim($output));

				$screenStatus = $ex[1];
				if ($screenStatus!="true") {
					$output = $this->executeOnDevice("shell input keyevent KEYCODE_POWER");
				}
			}*/
			$date_folder = $this->imagesLocation.date("Y-m-d")."/";

			if (!file_exists($date_folder)) {
				mkdir($date_folder, 0777);
				$this->logIt("Date Folder ".$date_folder." CREATED!");
			} else {
				$this->logIt("Date Folder ".$date_folder." Already Exsists!");
			}

			$hour_folder = $date_folder.date("H")."/";

			if (!file_exists($hour_folder)) {
				mkdir($hour_folder, 0777);
				$this->logIt("Hour Fodler ".$hour_folder." CREATED!");
			} else {
				$this->logIt("Hour Fodler ".$hour_folder." Already Exsists!");
			}

			$fileame = $hour_folder.uniqid().".png";

			$this->logIt($fileame);

			$this->executeOnDevice("shell screencap -p >> ".$fileame);

			if (is_file($fileame)) {
				chmod($fileame, 0777);
				$this->logIt("File Saved ".$fileame);
				return str_replace($this->imagesLocation, $this->http_imagesLocation, $fileame);
			} else {
				return "SCREENSHOT ERROR";
			}
		}

		function connectDevice() {
			if ($this->configs->device_tcpip_port!=0) {
				$output = $this->executeCMD($this->adbPath." connect localhost:".$this->configs->device_tcpip_port." 2>&1");
			}

			$output = explode("\n", $this->executeOnDevice("devices 2>&1"));
			for ($i=0; $i<count($output); $i++) {

				if ($this->configs->device_tcpip_port!=0) {
					if (strpos($output[$i], "localhost:".$this->configs->device_tcpip_port)!==false) {
						$ex = explode("localhost:".$this->configs->device_tcpip_port, $output[$i]);

						$connected_status = trim($ex[1]);
						$this->logIt("Status: ".$connected_status);

						break;
					}
				} else {
					$this->logIt($this->configs->device_adb_id);
					$this->logIt($output[$i]);
					$this->logIt("==========");
					if (strpos($output[$i], $this->configs->device_adb_id)!==false) {
						$ex = explode($this->configs->device_adb_id, $output[$i]);

						$connected_status = trim($ex[1]);
						$this->logIt("Status: ".$connected_status);

						break;
					}
				}
			}

			if ($connected_status!="device") {
				$this->logIt("ERROR: Device NOT Connected!");

				$this->executeOnDevice("forward tcp:".$this->configs->device_tcpip_port." tcp:".$this->configs->device_tcpip_port);
				$this->executeOnDevice("shell netstat -nltop");
				$output = $this->executeOnDevice("connect localhost:".$this->configs->device_tcpip_port." 2>&1");
			}
		}

		function executeCMD($cmd) {
			$this->logIt($cmd);
			$last_line = exec($cmd, $output);

			$this->logIt($output, true);

			file_put_contents("/opt/vinter/php/tmp.log", trim(implode("\n", $output)));
			return $output;
		}

		function executeOnDevice($cmd) {
			return trim(implode("\n", $this->executeCMD($this->adbPath." ".$cmd)));
		}

		function getTagValues($tag1, $tag2, $str) {
			$ex = explode($tag1, $str);
			$ex1 = explode($tag2, $ex[1]);

			return $ex1[0];
		}

		function currentActivity() {
			$output = $this->executeOnDevice('shell dumpsys window windows | grep -E "mCurrentFocus"');
			$ex = explode(" ", $output);
			$activity_name = explode("/", str_replace("}", "", $ex[count($ex)-1]));
			return str_replace($this->configs->app_package, "", $activity_name[1]);
		}

		function process_condition($condition) {
			$conditional_operators = array("==", "!=", "like", "NOT_LIKE");
			$conditional_cmd_allowed = array("currentActivity", "VIEWS.name");

			$flag_conditional_operator = 0;
			for ($i=0; $i<count($conditional_operators); $i++) {
				if (strpos($condition, $conditional_operators[$i])!==false) {
					$conditional_operator = $conditional_operators[$i];

					$flag_conditional_operator = 1;
					break;
				}
			}

			if ($flag_conditional_operator==1) {
				$conditional_cmd_ex = explode($conditional_operator, $condition);

				$conditional_cmd = trim($conditional_cmd_ex[0]);
				$conditional_cmd_value = trim($conditional_cmd_ex[1]);

				$flag_conditional_cmd = 0;
				for ($i=0; $i<count($conditional_cmd_allowed); $i++) {
					if ($conditional_cmd==$conditional_cmd_allowed[$i]) {
						$flag_conditional_cmd = 1;
						break;
					}
				}

				if ($flag_conditional_cmd==1) {
					switch ($conditional_cmd) {
						case "currentActivity":
							$currentActivity = $this->currentActivity();
							$this->logIt("currentActivity -> (".$currentActivity.")");
							if ($conditional_operator=="==" || $conditional_operator=="!=") {
								$final_if_condition = 'if ($currentActivity'.$conditional_operator.$conditional_cmd_value.') $if_condition_variable = 1;';
							} else if ($conditional_operator=="like") {
								$final_if_condition = 'if (strpos($currentActivity, '.$conditional_cmd_value.')!==false) $if_condition_variable = 1;';
							} else if ($conditional_operator=="NOT_LIKE") {
								$final_if_condition = 'if (strpos($currentActivity, '.$conditional_cmd_value.')===false) $if_condition_variable = 1;';
							}

							$this->logIt($final_if_condition);

							$if_condition_variable = 0;
							eval($final_if_condition);
							if ($if_condition_variable==1) {
								$this->logIt("Returning true");
								return 1;
							} else {
								$this->logIt("Returning false");
								return 0;
							}
							break;
						case "VIEWS.name":
							$if_condition_variable = 0;
							$view_names = $this->getCurrentViews();

							if (!is_array($view_names)) {$this->logIt("no Views found");return 1;}

							$this->logIt($view_names, true);
							if ($conditional_operator=="==") {
								$final_if_condition = 'if (in_array('.$conditional_cmd_value.', $view_names)) {$if_condition_variable = 1;}';
							} else if ($conditional_operator=="!=") {
								$final_if_condition = 'if (!(in_array('.$conditional_cmd_value.', $view_names))) {$if_condition_variable = 1;}';
							} else if ($conditional_operator=="like") {
								for ($i=0; $i<count($view_names); $i++) {
									$final_if_condition = 'if (strpos($view_names[$i], '.$conditional_cmd_value.')!==false) {$if_condition_variable = 1;}';
								}
							} else if ($conditional_operator=="NOT_LIKE") {
								for ($i=0; $i<count($view_names); $i++) {
									$final_if_condition = 'if (strpos($view_names[$i], '.$conditional_cmd_value.')===false) {$if_condition_variable = 1;}';
								}
							}

							eval($final_if_condition);
							if ($if_condition_variable==1) {
								$this->logIt("Returning true");
								return 1;
							}
							return 0;

							break;
						default:
							$this->logIt("ERROR - Process Condition - conditional_cmd: Syntax Error!");
					}
				} else {
					$this->logIt('ERROR - Process Condition - flag_conditional_cmd'."\n".$conditional_cmd."\n".': Syntax Error!');
				}
			} else {
				$this->logIt('ERROR - Process Condition - flag_conditional_operator: Syntax Error!');
			}
		}

		function executeLang($tokens) {
			switch (trim($tokens[0])) {
				case "WHILE":
					$this->logIt('WHILE');

					$str = trim(str_replace("WHILE ", "", implode(" ", $tokens)));

					if (strpos($str, ")")!==false &&
						strpos($str, ")")!==false &&
						strpos($str, "{")!==false &&
						strpos($str, "}")!==false) {

						$while_condition = trim($this->getTagValues("(", ")", $str));

						if (strpos($while_condition, "&&")!==false) {
							$while_conditions = explode("&&", $while_condition);

							for ($i=0; $i<count($while_conditions); $i++) {
								$results[] = $this->process_condition($while_conditions[$i]);
							}
						} else {
							$result = $this->process_condition($while_condition);
						}

						$flag_true = 0;
						$flag_exec_while_block = 0;
						if (count($results)>0) {
							for ($i=0; $i<count($results); $i++) {
								if ($results[$i]==1) {
									$flag_true = 1;
									break;
								}
							}

							if ($flag_true==1) {
								$this->logIt('While Condition True');
								$flag_exec_while_block = 1;
							} else {
								$this->logIt('While Condition False');
							}
						} else {
							if ($result==1) {
								$this->logIt('While Condition True');
								$flag_exec_while_block = 1;
							} else {
								$this->logIt('WHILE Condition False');
							}
						}

						if ($flag_exec_while_block==1) {
							$while_block = $this->getTagValues("{", "}", $str);

							$commands_ex = explode(";", $while_block);
							$this->logIt($commands_ex, true);

							for ($i=0; $i<count($commands_ex); $i++) {
								if (trim($commands_ex[$i])!="") {
									$ex_tokens = explode(" ", trim($commands_ex[$i]));

									$this->checkTokenSwitch($ex_tokens);
								}
							}

							$this->maxWhileExecIndex--;

							if ($this->maxWhileExecIndex>0) {
								$this->logIt('While Recall until while condition true');
								$this->executeLang($tokens);
							} else {
								$this->logIt('While maxWhileExecs Exausted!');
								$this->maxWhileExecIndex = $this->maxWhileExecs;
							}
						} else {
							$this->maxWhileExecIndex = $this->maxWhileExecs;
						}

					} else {
						$this->logIt('ERROR - WHILE: Syntax Error!');
					}
					break;
				case "LOOP":
					$this->logIt('LOOP');
					break;
				case "IF":
					$this->logIt('IF CMD -> '.implode(" ", $tokens));

					$str = trim(str_replace("IF ", "", implode(" ", $tokens)));

					if (strpos($str, ")")!==false &&
						strpos($str, ")")!==false &&
						strpos($str, "{")!==false &&
						strpos($str, "}")!==false) {

						$if_condition = trim($this->getTagValues("(", ")", $str));

						if (strpos($if_condition, "&&")!==false) {
							$if_conditions = explode("&&", $if_condition);

							for ($i=0; $i<count($if_conditions); $i++) {
								$results[] = $this->process_condition($if_conditions[$i]);
							}
						} else {
							$result = $this->process_condition($if_condition);
						}

						$flag_false = 0;
						$flag_exec_if_block = 0;
						if (count($results)>0) {
							for ($i=0; $i<count($results); $i++) {
								if ($results[$i]==0) {
									$flag_false = 1;
									break;
								}
							}

							if ($flag_false==1) {
								$this->logIt('IF Condition False');
							} else {
								$this->logIt('IF Condition True');
								$flag_exec_if_block = 1;
							}
						} else {
							if ($result==0) {
								$this->logIt('IF Condition False');
							} else {
								$this->logIt('IF Condition True');
								$flag_exec_if_block = 1;
							}
						}

						if ($flag_exec_if_block==1) {
							$if_block = $this->getTagValues("{", "}", $str);
							$this->logIt('$str -> '.$str);
							$this->logIt('$if_block -> '.$if_block);

							$commands_ex = explode(";", $if_block);

							for ($i=0; $i<count($commands_ex); $i++) {
								if (trim($commands_ex[$i])!="") {
									$ex_tokens = explode(" ", trim($commands_ex[$i]));

									$this->checkTokenSwitch($ex_tokens);
								}
							}
						}
					} else {
						$this->logIt('ERROR - IF: Syntax Error!');
					}

					break;
				case "FUNCTION":
					$this->logIt('FUNCTION ????');

					$function_name = trim($tokens[1]);
					$flag_fucntion = 0;
					if (method_exists($this, $function_name)) {
						$flag_fucntion = 1;
					} else {
						exit("ERROR: executeAppium - function not found: Syntax Error!");
					}

					eval('$this->'.$function_name."();");
					break;
				default:
					exit("executeAppium: Syntax Error!");
			}
		}

		function getCurrentViews($class_name="android.view.View") {
			$class_name = "class=".$class_name;
			$txtViews = $this->executeCMD($this->androidViewClient_path.'tools/dump -a|grep '.$class_name);

			if (count($txtViews)>0) {
				for ($i=0; $i<count($txtViews); $i++) {
					$name_cnt = str_replace("focusable=", "", $this->getTagValues("content-desc=", "focusable=", $txtViews[$i]));
					$name_txt = str_replace("long-clickable=", "", $this->getTagValues("text=", "long-clickable=", $txtViews[$i]));
					if (trim($name_cnt)!="") {
						if (!(in_array(trim($name_cnt), $view_names))) {
							$view_names[] = trim($name_cnt);
						}
					}
					if (trim($name_txt)!="") {
						if (!(in_array(trim($name_txt), $view_names))) {
							$view_names[] = trim($name_txt);
						}
					}
				}

				$this->logIt($view_names, true);
				return $view_names;
			} else {
				$this->logIt("NOT found");
			}

			return "NO VIEWS";
		}

		function getClickedAd() {
			$this->waitfor($this->configs->browser_loadTImeout);

			$currentActivity = $this->currentActivity();


			$this->logIt($currentActivity);

			//exit;


			$this->past_browser_activity = $currentActivity ;

			if ($currentActivity=="com.startapp.android.publish.adsCommon.activities.OverlayActivity")
				 {
					 		$this->app_browser();
				 }

				 $this->past_browser_activity = $currentActivity ;

			if ($currentActivity=="com.sec.android.app.sbrowser.SBrowserMainActivity")
				 {
					return 1;
				}
				// code for ammo button
				$this->logIt("current activity = ".$currentActivity);
				if ($currentActivity=="com.android.mms.ui.ConversationComposer")
					 {
						//return 1;
						$this->logIt("going for ammo functionality ");
						$screenshot_path = $this->takeSaveScreenshot();
						$sql_str = "INSERT INTO tblapp_data ".
								   "(app_sequence_log_id,
								   	 app_sequece_id,
								   	 screenshot_type,
								   	 ad_type,
								   	 ad_url,
								   	 screenshot_url,
								   	 ad_detail,
									 ct_img,
									 stage_type,
								   	 date_added)".
								   " VALUES ".
								   "('[app_sequence_log_id]',
								     '".$this->configs->app_sequence_id."',
								     '4',
								     '2',
								     '',
								     '".$screenshot_path."',
								     '',
									   '0',
									   '0',
								     '".date("Y-m-d H:i:s")."')";

										 	$this->saveSQL($sql_str, "Ammo Step");


										 $this->executeOnDevice("shell input keyevent KEYCODE_BACK");

										 $this->waitfor(5);

										 $currentActivity = $this->currentActivity();
										 if ($currentActivity=="com.android.mms.ui.ConversationComposer")
												{
														$currentActivity = $this->currentActivity();
														$this->executeOnDevice("shell input keyevent KEYCODE_BACK");

												}


					}
			//exit;
/*
			$this->waitfor(10);

			###3 colsing warning yes warnings

			$this->logIt("closing warning cancel");

			$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "android:id/button2"');

            $this->waitfor(10);


			$this->logIt("closing warning yes");

			$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "android:id/button1"');

*/
			if ($currentActivity=="com.mopub.common.MoPubBrowser") {
	            $ad_type = "AdBrowser";
		        $txtViews = $this->getCurrentViews('android.widget.TextView');

				if (count($txtViews)>0) {
		            $ad_url = $txtViews[0];
				} else {
					$this->logIt("MoPubBrowser URL not found");
				}

				$this->logIt('$ad_url -> '.$ad_url);

				$ad_type_value = 0;
		        $screenshot_path = $this->takeSaveScreenshot();
			} else if (strpos($currentActivity, "com.google.android")!==false) {
				$ad_type = "PlayStore";
	            $this->logIt("Redirected to Google Play Store");
		        $txtViews = $this->getCurrentViews('android.widget.TextView');
				if (count($txtViews)>0) {
					$this->logIt($txtViews, true);
				} else {
					$this->logIt("Play Store Title NOT found");
				}
				$ad_url = "Play Store";

				$ad_type_value = 1;
		        $screenshot_path = $this->takeSaveScreenshot();
			} else {
				$ad_type = "Browser";


				$this->logIt("getting browser url");



					$browser_url = $this->executeAction("BROWSER URL");

					$this->final_url = $browser_url;

					$this->logIt("browser url =".$this->final_url);

					$this->waitfor(2);

					if($this->final_url!="")
					{
						$this->isCT($this->final_url);
					}

					if($this->ct_data!=0 && $this->ct_data!="")
					{

						$this->clickThrough();

					}


					//exit;

				$this->findBtnFromArrayNClick('["OK","Cancel","NOPE"]');

				$screenshot_path = $this->takeSaveScreenshot();
				//$this->executeAction("BROWSER VIEW_SOURCE");

				$browser_url = $this->executeAction("BROWSER URL");
				$ad_url = $browser_url;
				//$ad_url = "[AD_URL]";
				$ad_type_value = 2;
			}

			$ct_img = 0;
			$stage_type = 1;
			$screenshot_type = 1;

			//0 = PlayStore, 1= AdBrowser, 2 = Browser

			$sql_str = "INSERT INTO tblapp_data ".
					   "(app_sequence_log_id,
					   	 app_sequece_id,
					   	 screenshot_type,
					   	 ad_type,
					   	 ad_url,
					   	 screenshot_url,
					   	 ad_detail,
						 ct_img,
						 stage_type,
					   	 date_added)".
					   " VALUES ".
					   "('[app_sequence_log_id]',
					     '".$this->configs->app_sequence_id."',
					     '".$screenshot_type."',
					     '".$ad_type_value."',
					     '".$ad_url."',
					     '".$screenshot_path."',
					     '[VIEW_SOURCE]',
						  '".$ct_img."',
						 '".$stage_type."',
					     '".date("Y-m-d H:i:s")."')";

			if ($ad_type=="Browser") {
				$comment = "BROWSER - Screenshot and source";
			} else {
				$comment = "BROWSER - Screenshot";
			}

			$this->saveSQL($sql_str, $comment);

			#### code for videos query ##########

if(trim($this->configs->video_url)!="")
{
			$sql_str = "INSERT INTO tbl_vinter_videos ".
			       "(log_id, ".
			       " video_path,".
			       " record_date)".
			       " VALUES ".
			       "('[app_sequence_log_id]',
			         '".$this->configs->video_url."',
			         '".date("Y-m-d H:i:s")."')";

			$this->saveSQL($sql_str, "APP Video - Step");
	}

#### code for videos query ##########


			if ($ad_type=="Browser") {

				//$this->executeOnDevice("shell am force-stop acr.browser.barebones");

				$this->closeVeriBrowseTabs();

				/*if ($currentActivity=="org.chromium.chrome.browser.ChromeTabbedActivity") {
					$this->logIt("if 1");
					$this->closeChromeTabs();
				} else if ($currentActivity=="acr.browser.lightning.activity.MainActivity" || $currentActivity=="acr.browser.lightning.activity.MainActivity") {
					$this->logIt("if 2");
					$this->executeOnDevice("shell am force-stop acr.browser.barebones");

				} else if ($currentActivity=="com.sec.android.app.sbrowser.SBrowserMainActivity") {
					$this->logIt("if 3");
					$this->closeNativeBrowserTabs();
					$this->executeOnDevice("shell am force-stop com.sec.android.app.sbrowser");
				}*/
			}
			//exit;
		}

		function findByClassNClick($class_name, $label) {
			$output = trim(implode("\n", $this->executeCMD($this->androidViewClient_path.'tools/dump -a|grep '.$class_name.'|grep "'.$label.'"')));

			$ex = explode("uniqueId=", $output);
			$ex1 = explode(" ", $ex[1]);

			$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "'.$ex1[0].'"');
		}

		function closeChromeTabs() {
			$this->findByClassNClick("android.widget.ImageButton", "open tabs");
			$this->waitfor(2);
			$this->executeAction('BUTTON Label="Cancel" Action="click"');
			$this->waitfor(2);
			$this->findByClassNClick("android.widget.ImageButton", "More options");
			$this->waitfor(2);
			$this->findByClassNClick("android.widget.TextView", "Close all tabs");
		}


		function closeVeriBrowseTabs() {

			$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "acr.browser.barebones:id/arrow_button"');

			$this->waitfor(10);

			$this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "acr.browser.barebones:id/tab_header_button"');

			$this->waitfor(10);

			$this->findButtonByTextNClick("Close all tabs");
			$this->logIt('witing for 10 seconds after closing all tabs');

			$this->waitfor(10);

			$this->executeOnDevice("shell am force-stop acr.browser.barebones");

			$this->waitfor(5);


		}

		function closeNativeBrowserTabs() {
			$this->findButtonByTextNClick("Tabs");
			$this->waitfor(2);
			$this->findButtonByTextNClick("More");
			$this->waitfor(2);
			$this->findButtonByTextNClick("Close all");
			$this->waitfor(1);
		}

		function restartVNC() {
			$output = $this->executeOnDevice("shell am force-stop com.vmlite.vncserver");
			$output = $this->executeOnDevice("shell /data/local/tmp/vmlitevncserver --stop");
			$output = $this->executeOnDevice("shell am start -a android.intent.action.MAIN -n com.vmlite.vncserver/.MainActivity");

			$output = $this->executeOnDevice("forward tcp:".$this->configs->device_adb_vnc_port." tcp:".$this->configs->device_adb_vnc_port);
			$output = $this->executeOnDevice("shell input keyevent 4");

			$output = $this->executeOnDevice("shell /data/local/tmp/vmlitevncserver &");
		}

		function getAppPath() {
			$flag_upload_app = 0;
			if ($this->configs->app_package=="") {
				if ($this->configs->app_path=="") {
					$flag_upload_app = 1;
				} else if (!(is_file($this->configs->app_path))) {
					$flag_upload_app = 1;
				}
			}

			$this->configs->appium_session_id = md5(microtime() . rand());
			$this->session_xml_file = $this->sql_dir.$this->configs->appium_session_id.".xml";

			if ($flag_upload_app==1) {
				$this->logIt("Uploading App on Remote server......");
				$this->logIt($this->configs, true);

				$file_content = file_get_contents($this->configs->app_url);

				$path_parts = pathinfo($this->configs->app_url);

				$this->configs->app_path = $this->configs->app_dir.$path_parts['basename'];

				$this->saveFile($this->configs->app_path, $file_content, "App Uploaded");
			}

			$sql_str = "UPDATE tblapps SET path='".$this->configs->app_path."' WHERE id = ".$this->configs->app_id;

			$this->saveSQL($sql_str, "APP PATH UPDATE SQL");
			return $this->configs->app_path;
		}

		function saveSQL($sql_str, $msg) {
			$this->sql_log = $sql_str."#".$msg."\n";

			$this->saveFile($this->session_xml_file, "<sql>\n\t<query>\n\t\t".$sql_str."\n\t</query>\n\t<comment>\n\t\t".$msg."\n\t</comment>\n"."</sql>\n", $msg);
		}

		function saveFile($name, $data, $msg) {
			if (!file_put_contents($name, $data, FILE_APPEND)) {
				$this->logIt($msg);
				$this->logIt("IO Error! ".$name);
				exit;
			} else {
				$this->logIt($msg);
				if (is_file($name)) {
					chmod($name, 0777);
				} else {
					$this->logIt($msg);
					$this->logIt("IO Error2! ".$name);
					exit;
				}
			}
		}

		function logIt($obj, $is_array=false) {
			$tmpStr = "";

			$log_str ="";
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

						 $log_str .="\n-----------------------------------\n".
	 						 $obj.
	 						 "\n-----------------------------------\n";

					$tmpStr = $obj;
				}
			}

			$this->execution_log .= $tmpStr;

			//file_put_contents("/opt/vinter/php/tmp_log.txt");
			 file_put_contents("/opt/vinter/php/tmp_log.txt", $log_str, FILE_APPEND);

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

		function waitfor($seconds) {
			if ($this->isEcho) echo "Waiting ";
			for ($i=$seconds; $i>=1; $i--) {
				sleep(1);
				if ($this->isEcho) {
					echo $i." ";
					if ($i%30==0) {
						//echo "\n";
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
				$output = $this->executeDeviceShellCMD("netstat |grep ".$this->configs->device_tcpip_port);

				if (strpos($output, $this->configs->device_tcpip_port)!==false) {
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
				$fp_tcpip = fsockopen("localhost", $this->configs->device_tcpip_port, $errno, $errstr, 3);
				if (!$fp_tcpip) {
					$this->logIt("TCPIP Tunnel doesnt exist!");

					$this->logIt("Create TCPIP Tunnel");

					$localloop = 'localhost';

					$cmd = "sshpass -p 'bluebird' ".
						   "ssh -L ".$this->configs->device_tcpip_port.":".$localloop.":".$this->configs->device_tcpip_port.
						   " ".$this->configs["device_pi_ssh"]." -N > /dev/null 2>&1 & echo $!";
					$this->executeCMD($cmd);
					waitfor(3);

					while (!$fp_tcpip1 = fsockopen($localloop, $this->configs->device_tcpip_port, $errno, $errstr, 3)) {
						$this->logIt("Still Creating TCPIP Tunnel!");
					}
				}

				$this->forwardTCPIP();

				if (strpos($this->executePiCMD("netstat -nltop"), $this->configs->device_tcpip_port)===false) {
					$this->logIt("Pi Tunnel Error!");
					waitfor(120); // wait for 2 min
					$this->setTCPIPTunnel();
				}

				$this->logIt("TCPIP Tunnel OK");
			}
		}

		function restartTCPIP() {
			$this->logIt("Restarting device in TCPIP mode");
			$output = $this->executeDeviceCMD("tcpip ".$this->configs->device_tcpip_port);
			waitfor(10); // explicit wait to let the device restart in tcpip mode

			$this->logIt("Restarting VNC");
			$this->call_deviceAPI("restart_vnc", $this->params);
			waitfor(10);
		}

		function forwardTCPIP() {
			$this->logIt("Forwarding TCPIP port to Pi\n"."forward tcp:".$this->configs->device_tcpip_port." tcp:".$this->configs->device_tcpip_port);
			$output = $this->executeDeviceCMD("forward tcp:".$this->configs->device_tcpip_port." tcp:".$this->configs->device_tcpip_port);
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

		function clickThrough(){



				//define('vapiUrl','http://localhost:7935/VAPI/Device/action/post/'); //uk

 // exit;


  if($this->ct_data!=0 && $this->ct_data!="")
  {
  		$clickThrough = $this->ct_data;
  }
  else
  {
  	return true;
  }

  $step = 1;
  $data["step".$step] = "start_log";
  $data["step".$step."_cmd"] = time();



	$this->logIt("CT = ".print_r( $clickThrough ));
	//exit;

  $commandPattern = '/((?:[a-z]+))(\s+)((?:[a-z]+))(\s+)(=)(\s+)(")(.*?)(")/i';
  if($clickThrough){
    foreach($clickThrough as $ct){
      $js = '';
      $clickjs = '';
      $clickT =  explode(',',$ct->control_cmd); //return A Label = "Fix It Now"
      $match = preg_match($commandPattern, strtolower($clickT[0]),$matches);
			// dont do lower case for find
			$match = preg_match($commandPattern, $clickT[0],$matches2);
      $target = $matches[1]; // A
      $attrib = $matches[3]; // Label
      //$find  = $matches[8]; // "Yes"
			$find  = $matches2[8]; // "Yes"
      if($attrib=="label"){
        $js.="var vapi_shot=false; var find='".$find."'; elems = document.querySelectorAll('".$target."');for (var i = 0; i < elems.length; i++) { if(elems[i].textContent.trim().toLowerCase() == find.trim() && elems[i].offsetParent !== null) {if(elems[i]){vapi_shot=true;break;}}}";
        $clickjs.="var find='".$find."'; elems = document.querySelectorAll('".$target."');for (var i = 0; i < elems.length; i++) { if(elems[i].textContent.trim().toLowerCase() == find.trim() && elems[i].offsetParent !== null) {if(elems[i]){elems[i].click();break;}}}";

      }else if($attrib == "class"){
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
        if($target=="anythingby"){
          $js.="var vapi_shot=false; elems = document.querySelector('#".$find."');if(elems){vapi_shot=true;}";
          $clickjs.="elems = document.querySelector('#".$find."');if(elems){elems.click();vapi_shot=true;}";
        }else{
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
        // $cmd .= '&step'.$step.'=js&step'.$step.'_cmd='.base64_encode($js);
        $step++;
        // $cmd .= '&step'.$step.'=takeScreenshot';
        // $data[] = "step".$step;
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




	$this->logIt(print_r( $data));
	//exit;



	 for ($data_index=0; $i<count($data); $data_index++) {


		 $this->logIt("data_index =".$data_index);

		  $this->waitfor(5);

          if ($data["step".$data_index]=="js") {



			$this->executeJSOnDevice($data["step".$data_index."_cmd"]);

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



			$this->screenshotDevice($data["step".$data_index."_cmd"]);





          }

		   else  if ($data["step".$data_index]=="end_log") {

			   $this->logIt("end log ");

			   break;
			   }

			    else  if ($data["step".$data_index]=="wait") {

			   $this->logIt("CT Wait 20 seconds ");
			   $this->waitfor(20);
			 	  break;
			   }





        }


  }




				return true;
				}

				function executeJSOnDevice($js)
				{


					$this->logIt("EXECUTING -- "."BROWSER JS ".base64_decode($js));

					$response = $this->executeAction("BROWSER JS ".$js);

			$this->waitfor(10);
			return true;

				}
				function screenshotDevice($js)
				{

					$this->logIt("screen shot if ");

			$this->executeAction("BROWSER JS ".base64_encode("alert(vapi_shot);"));

			$this->waitfor(2);


	$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id." 2>&1");

	//pppppppppp

	$this->logIt($output);



	$vapi_shot = "false";
	for ($j=0; $j<count($output); $j++) {
			if (strpos($output[$j], "android.widget.TextView com.android.chrome:id/js_modal_dialog_message")!==false ||
			strpos($output[$j], "android.widget.TextView android:id/message")!==false    ) {
					$vapi_shot = trim(str_replace("android.widget.TextView com.android.chrome:id/js_modal_dialog_message", "", $output[$j]));
					$vapi_shot = trim(str_replace("android.widget.TextView android:id/message", "", $output[$j]));

					break;
			}
	}

	//$this->executeAction("DEVICE BACK_BUTTON");

	$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id." 2>&1");
	$output_string = implode("\n",$output);
	if (strpos($output_string, 'android:id/button1') !== false) {
		echo "\n string found \n";
		$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/findViewById.py '.$this->configs->device_adb_id.' "android:id/button1"'." 2>&1");
		$this->waitfor(1);
}
else {
	echo "\n string not found\n ";
}


		$this->logIt("vapi_shot =  ".$vapi_shot);


	if ($vapi_shot=="true") {

		$this->waitfor(5);

		$screen_shot_result = $this->takeSaveScreenshot(false);

		$this->waitfor(5);

		$response = $this->executeAction("BROWSER URL");


		$this->logIt($screen_shot_result);

		$this->waitfor(2);

		$screenshot_type = 3;
$ad_type_value = 2;
$ct_img = 1;
$stage_type = 0;

//$ad_url = $response['URL'];

$ad_url = $response;

$screenshot_path = $screen_shot_result;

$comment = "BROWSER - Screenshot";


	$sql_str = "INSERT INTO tblapp_data ".
					   "(app_sequence_log_id,
					   	 app_sequece_id,
					   	 screenshot_type,
					   	 ad_type,
					   	 ad_url,
					   	 screenshot_url,
					   	 ad_detail,
						 ct_img,
						 stage_type,
					   	 date_added)".
					   " VALUES ".
					   "('[app_sequence_log_id]',
					     '".$this->configs->app_sequence_id."',
					     '".$screenshot_type."',
					     '".$ad_type_value."',
					     '".$ad_url."',
					     '".$screenshot_path."',
					     '[VIEW_SOURCE]',
						 '".$ct_img."',
						 '".$stage_type."',
					     '".date("Y-m-d H:i:s")."')";



			$this->saveSQL($sql_str, $comment);

		$response = $this->executeAction("BROWSER JS ".$js);
		$this->waitfor(20);




	} else {
		$response = false;
	}



		$this->waitfor(5);


		if ($response==false) {
		//echo "false";
		$this->logIt("response = ".$response);

	} else {

	}










		return true;



				}

				function isCT($final_url)
				{
					$this->waitfor(2);
					$this->logIt('fintal url in CT -> '.$final_url);

					//include($this->working_dir."classes/dbase.php");

					$final_url = str_replace("https://","",$final_url);

					$final_url = str_replace("http://","",$final_url);

				 	$click_url_array = explode("/",$final_url);

				  $this->logIt('click_url -> '.$click_url_array[0]);

				  $click_url = $click_url_array[0];

  			$role_id = $this->configs->role_id;

  			$domain = urlencode($click_url );

  $ct_url = "http://social.ext.monitoringservice.co/social/vinter_clickthrough.php?domain=$domain&role_id=$role_id";



   $this->logIt("ct url =".$ct_url);

 $clickThroughDecode =  file_get_contents($ct_url);
 if($clickThroughDecode!="domain or role not found" && $clickThroughDecode!="ct not found")
 {
	 $clickThrough = json_decode($clickThroughDecode);

	 $this->logIt(print_r($clickThrough ));
	//exit;


  if($clickThrough[0]->id!="")
  {
	  $this->ct_data = $clickThrough;

  }
  else
  {
 	$this->ct_data = 0;
  }

	return true;
 }
				}

				function app_browser()
				{
					$this->waitfor(5);

					$screen_shot_result = $this->takeSaveScreenshot(false);
					$this->logIt($screen_shot_result);
					$this->waitfor(2);

					$screenshot_type = 3;
			$ad_type_value = 2;
			$ct_img = 1;
			$stage_type = 0;

			//$ad_url = $response['URL'];

			$ad_url = "";

			$screenshot_path = $screen_shot_result;

			$comment = "BROWSER - AppScreenshot";


				$sql_str = "INSERT INTO tblapp_data ".
								   "(app_sequence_log_id,
								   	 app_sequece_id,
								   	 screenshot_type,
								   	 ad_type,
								   	 ad_url,
								   	 screenshot_url,
								   	 ad_detail,
									 ct_img,
									 stage_type,
								   	 date_added)".
								   " VALUES ".
								   "('[app_sequence_log_id]',
								     '".$this->configs->app_sequence_id."',
								     '".$screenshot_type."',
								     '".$ad_type_value."',
								     '".$ad_url."',
								     '".$screenshot_path."',
								     '[VIEW_SOURCE]',
									 '".$ct_img."',
									 '".$stage_type."',
								     '".date("Y-m-d H:i:s")."')";



						$this->saveSQL($sql_str, $comment);

					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/dump-simple.py '.$this->configs->device_adb_id." 2>&1");
					for($i=0;$i<$output;$i++)
					{
					if (strpos($output[$i], 'http:') !== false || strpos($output[$i], 'https:') !== false)
					{
					$url_array  = explode("android.widget.TextView", $output[$i]);
					$url = trim($url_array[1]);
					$output = $this->executeCMD('$(which python) '.$this->androidViewClient_path.'examples/veri-browser-open-url.py '.
					$this->configs->device_adb_id." ".$url." 2>&1	");
					//print_r($url);
					//exit;
					break;
					}
					}



					return true;
				}


	}
?>
