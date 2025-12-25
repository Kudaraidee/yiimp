<?php

if(php_sapi_name() != "cli") return;

// Yiimp2 Dedicated System - Load yiimp2 configuration
// This version specifically loads /etc/yiimp2/serverconfig.php for Docker Compose deployment
// Requirements: 14.2, 14.4, 14.5, 17.13

$config_paths = [
    '/etc/yiimp2/serverconfig.php',
    __DIR__ . '/../config/yiimp2/serverconfig.php',
];

$config_loaded = false;
$error_details = [];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        if (is_readable($path)) {
            try {
                require_once($path);
                $config_loaded = true;
                echo "Loaded yiimp2 config from: $path\n";
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
    echo "ERROR: Could not load yiimp2 serverconfig.php\n\n";
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

// Use absolute paths for Docker container environment
$yaamp_dir = '/var/www/yaamp';
$framework_dir = '/var/www/framework';

require_once($yaamp_dir . '/defaultconfig.php');

require_once($framework_dir . '/yii.php');
require_once($yaamp_dir . '/include.php');

require_once($yaamp_dir . '/components/CYiimpConsoleApp.php');

$config = require_once($yaamp_dir . '/console.php');
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
