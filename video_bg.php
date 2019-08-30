<?php
	error_reporting(E_ALL&~E_NOTICE&~E_DEPRECATED);
	//error_reporting(E_ALL);
	ini_set("display_errors", 1);

	$adbd_location = "/home/pi/adb/adb-linuxARMv6";

	$mobile_video_location = "/data/local/tmp/veri_video/";

	$video_command = "screenrecord --bit-rate 1000000 --size 858x480 --time-limit 60 ";


	$s_id =$argv[1];

	//mkdir /var/www/videos/

	$session_dir = $mobile_video_location.$s_id."/";

	/*$cmd ="mkdir $session_dir";

	execute_on_device($cmd);

	$cmd ="chmod -R 777 $session_dir";

	execute_on_device($cmd);
*/
	$i =0;

	while(1)
	{

		$video_name = $s_id."_".$i.".mp4";

		$cmd =$video_command.$session_dir.$video_name;
		echo $cmd."\n";
		execute_on_device($cmd);



		$i++;
	}

	function execute_on_device($cmd){
		global $adbd_location;
		$shall_cmd = $adbd_location." shell $cmd";
		echo $shall_cmd."\n";
		exec($shall_cmd);

	}


?>
