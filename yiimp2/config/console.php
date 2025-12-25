<?php

// include serverconfig
require_once('/etc/yiimp2/serverconfig.php');

// Define YIIMP constants if not already defined (backward compatibility)
if (!defined('YIIMP_DBHOST')) define('YIIMP_DBHOST', YAAMP_DBHOST);
if (!defined('YIIMP_DBNAME')) define('YIIMP_DBNAME', YAAMP_DBNAME);
if (!defined('YIIMP_DBUSER')) define('YIIMP_DBUSER', YAAMP_DBUSER);
if (!defined('YIIMP_DBPASSWORD')) define('YIIMP_DBPASSWORD', YAAMP_DBPASSWORD);
if (!defined('YIIMP_LOGS')) define('YIIMP_LOGS', YAAMP_LOGS);

if (defined('YIIMP_DEBUG') && (YIIMP_DEBUG === true)) {
    define('YII_DEBUG', true);
    define('YII_ENV', 'dev');
}
else {
    define('YII_DEBUG', false);
    define('YII_ENV', 'prod');
}

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

// Configure cache based on memcache availability
if ((defined('YIIMP_MEMCACHE_HOST')) && (YIIMP_MEMCACHE_HOST != '')) {
    $cache_config = [
            'class' => 'yii\caching\MemCache',
            'servers' => [
                [
                    'host' => YIIMP_MEMCACHE_HOST,
                    'port' => YIIMP_MEMCACHE_PORT,
                    'weight' => 60,
                ],
            ],
        ];
}
else {
    $cache_config = [
            'class' => 'yii\caching\FileCache',
        ];
}

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => $cache_config,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-console-error.log' : '@runtime/logs/yiimp2-console-error.log',
                    'logVars' => [],
                    'maxFileSize' => 10240, // 10MB
                    'maxLogFiles' => 5,
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-console-info.log' : '@runtime/logs/yiimp2-console-info.log',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                ],
                // Database query logging
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'categories' => ['yii\db\*'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-console-db.log' : '@runtime/logs/yiimp2-console-db.log',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                ],
            ],
        ],
        'db' => $db,
        
        // Add all the same components that web application has
        // so backend scripts can use them
        'YiimpUtils' => [
            'class' => 'app\components\YiimpUtils',
        ],
        'ViewUtils' => [
            'class' => 'app\components\ViewUtils',
        ],
        'ConversionUtils' => [
            'class' => 'app\components\ConversionUtils',
        ],
        'ExplorerUtils' => [
            'class' => 'app\components\ExplorerUtils',
        ],
        'RpcClient' => [
            'class' => 'app\components\RpcClient',
        ],
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV === 'dev') {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
