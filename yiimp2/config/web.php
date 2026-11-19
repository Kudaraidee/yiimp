<?php

// include serverconfig
require_once('/etc/yiimp/serverconfig.php');

// yiimp2 views print this constant verbatim (no scheme), unlike YAAMP_SITE_URL.
if (!defined('YIIMP_API_URL')) {
    define('YIIMP_API_URL', preg_replace('#^https?://#', '', YAAMP_SITE_URL));
}
// Older Yiimp configs do not define these; yiimp2's api view expects them.
if (!defined('YIIMP_API_PAYOUTS')) define('YIIMP_API_PAYOUTS', false);
if (!defined('YIIMP_API_PAYOUTS_PERIOD')) define('YIIMP_API_PAYOUTS_PERIOD', 24 * 3600);
if (!defined('YIIMP_RENTAL')) define('YIIMP_RENTAL', false);

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
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'YlmPlD1AbsvqCV3LLXoSvOJNhBcIAZEq',
        ],
        'cache' => $cache_config,
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => YIIMP_LOGS.'/yiimp2.log',
                ],
            ],
        ],
        'db' => $db,
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],

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
    ],
    'params' => $params,
];

if (YII_ENV === 'dev') {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
