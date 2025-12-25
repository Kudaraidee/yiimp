<?php

if(php_sapi_name() != "cli") return;

// Try multiple locations for serverconfig.php with detailed error reporting
$config_paths = [
    '/etc/yiimp/serverconfig.php',
    __DIR__ . '/../config/serverconfig.php',
    __DIR__ . '/serverconfig.php',
];

$config_loaded = false;
$error_details = [];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        if (is_readable($path)) {
            try {
                require_once($path);
                $config_loaded = true;
                break;
            } catch (Exception $e) {
                $error_details[] = "Failed to load $path: " . $e->getMessage();
            }
        } else {
            $error_details[] = "File exists but not readable: $path (permissions: " . substr(sprintf('%o', fileperms($path)), -4) . ")";
        }
    } else {
        $error_details[] = "File not found: $path";
    }
}

if (!$config_loaded) {
    echo "ERROR: Could not load serverconfig.php\n\n";
    echo "Attempted paths:\n";
    foreach ($error_details as $detail) {
        echo "  - $detail\n";
    }
    echo "\nCurrent directory: " . getcwd() . "\n";
    echo "Script directory: " . __DIR__ . "\n";
    echo "Running as user: " . get_current_user() . " (UID: " . getmyuid() . ")\n";
    echo "open_basedir: " . (ini_get('open_basedir') ?: 'not set') . "\n";
    die("\n");
}

require_once('yaamp/defaultconfig.php');

require_once('framework/yii.php');
require_once('yaamp/include.php');

require_once('yaamp/components/CYiimpConsoleApp.php');

$config = require_once('yaamp/console.php');
$app = Yii::createApplication('CYiimpConsoleApp', $config);

try
{
	$app->runController($argv[1]);
}

catch(CException $e)
{
	debuglog($e, 5);

	$message = $e->getMessage();
	echo "exception: $message\n";
// 	send_email_alert('backend', "backend error", "$message");
}

