<?php
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

check_screen_lock();
?>
