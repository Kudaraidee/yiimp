<?php

// include local serverconfig for development
require_once(__DIR__ . '/../../config/yiimp2/serverconfig.php');

// Define YIIMP constants if not already defined (backward compatibility)
if (!defined('YIIMP_DBHOST')) define('YIIMP_DBHOST', YAAMP_DBHOST);
if (!defined('YIIMP_DBNAME')) define('YIIMP_DBNAME', YAAMP_DBNAME);
if (!defined('YIIMP_DBUSER')) define('YIIMP_DBUSER', YAAMP_DBUSER);
if (!defined('YIIMP_DBPASSWORD')) define('YIIMP_DBPASSWORD', YAAMP_DBPASSWORD);
if (!defined('YIIMP_LOGS')) define('YIIMP_LOGS', YAAMP_LOGS);

// Force development environment
define('YII_DEBUG', true);
define('YII_ENV', 'dev');

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

// Simple cache configuration for development
$cache_config = [
    'class' => 'yii\caching\FileCache',
];

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => 'dev-key-' . bin2hex(random_bytes(16)),
            'enableCsrfValidation' => false, // Disable for asset publishing
        ],
        'session' => [
            'name' => 'YIIMP2SESSID',
            'timeout' => 3600,
        ],
        'cache' => $cache_config,
        'assetManager' => [
            'appendTimestamp' => true,
            'linkAssets' => true, // Use symlinks in dev
            'forceCopy' => true, // Force republish
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => false,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => false,
            'showScriptName' => true,
        ],
    ],
    'params' => $params,
];

return $config;