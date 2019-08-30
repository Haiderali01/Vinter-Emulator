<?php
	$file_start_time ="/var/www/start_time.txt";
	$start_time_str = trim(file_get_contents($file_start_time));

	$current_date_time = date("Y-m-d H:i:s");
	$start_date = new DateTime($start_time_str);
    $end_date = new DateTime($current_date_time);
    $interval = $start_date->diff($end_date);
		$hours   = $interval->format('%h');
    $minutes =  $interval->format('%i');
		$minutes = ($hours * 60) + $minutes;
		echo "\n$minutes\n";

$kill_command ="screen -X -S appTesting quit";
	if($start_time_str=="")
	{
			echo "\ngoing to kill screen\n";
			echo "\n".$kill_command."\n";
			exec($kill_command);
	}
	else {
		echo "\nchecking time\n";
		echo " \n minutes are $minutes \n";
		if($minutes > 30)
		{
				echo "\ngoing to kill screen\n";
				echo "\n".$kill_command."\n";
				exec($kill_command);
		}
	}
	exit;
?>
