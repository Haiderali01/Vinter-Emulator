<?php
	require_once('lib/__init__.php');
	require_once('simple_html_dom.php');

	class SeleniumDriver {
		var $host;
		var $capabilities;
		var $driver; 
		
		function getCapabilities() {
			/*$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox',
							  WebDriverCapabilityType::PROXY => array('proxyType' => 'manual',
							  'httpProxy' => 'localhost:3128', 'sslProxy' => 'localhost:3128'));*/
							  
			/*
			* Empty the squid log
			*/
			
			//if ($isEcho) echo exec('cp /dev/null /var/log/squid3/access.log');
			return DesiredCapabilities::firefox();
		}
		
		function initBrowserWindow($host){
			$this->host = $host;  
			$this->driver = RemoteWebDriver::create($this->host, $this->capabilities);
			$this->driver->manage()->deleteAllCookies();
			$this->driver->manage()->window()->maximize();
		}
	}
?>