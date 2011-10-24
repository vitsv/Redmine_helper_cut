<?php
/**
 * W zasadzie to wszystko jest robione w index.php, ale dla wygody niech będzie tutaj też
 */
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
// Define path to application directory
defined('APPLICATION_PATH')
		|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
		|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
			realpath(APPLICATION_PATH . '/../library'),
			get_include_path(),
		)));

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
				APPLICATION_ENV,
				APPLICATION_PATH . '/configs/application.ini'
);

$application->bootstrap();


$user = new Application_Model_User();
if ($user->authorize("cron", "cron")) {
	$updater = new External_Updater();
	$result = $updater->updateAll();
	if (!empty($result)) {
		echo "ok!";
	}else{
		echo "error!";
	}
}
?>
