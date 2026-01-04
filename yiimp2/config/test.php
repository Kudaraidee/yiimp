<?php
/**
 * Test configuration
 * 
 * This configuration is used for running tests
 */

// Check if running in Docker test environment
$isDockerTest = getenv('DB_HOST') !== false;

if ($isDockerTest) {
    // Use environment variables for Docker test environment
    define('YIIMP_DBHOST', getenv('DB_HOST'));
    define('YIIMP_DBNAME', getenv('DB_NAME'));
    define('YIIMP_DBUSER', getenv('DB_USER'));
    define('YIIMP_DBPASSWORD', getenv('DB_PASSWORD'));
    define('YIIMP_LOGS', '/app/test-results');
} else {
    // Load production serverconfig for tests (database is remote)
    if (file_exists('/etc/yiimp2/serverconfig.php')) {
        require_once('/etc/yiimp2/serverconfig.php');
    } else {
        require_once(__DIR__ . '/../../config/serverconfig.php');
    }

    // Define YIIMP constants if not already defined (backward compatibility)
    if (!defined('YIIMP_DBHOST')) define('YIIMP_DBHOST', YAAMP_DBHOST);
    if (!defined('YIIMP_DBNAME')) define('YIIMP_DBNAME', YAAMP_DBNAME);
    if (!defined('YIIMP_DBUSER')) define('YIIMP_DBUSER', YAAMP_DBUSER);
    if (!defined('YIIMP_DBPASSWORD')) define('YIIMP_DBPASSWORD', YAAMP_DBPASSWORD);
    if (!defined('YIIMP_LOGS')) define('YIIMP_LOGS', YAAMP_LOGS);
}

if (defined('YIIMP_DEBUG') && (YIIMP_DEBUG === true)) {
    define('YII_DEBUG', true);
    define('YII_ENV', 'test');
}
else {
    define('YII_DEBUG', true);
    define('YII_ENV', 'test');
}

$params = require __DIR__ . '/params.php';

// Use test database configuration
// Force TCP/IP for remote hosts
$isRemoteHost = !in_array(YIIMP_DBHOST, ['localhost', '127.0.0.1', '::1']);
$dsn = $isRemoteHost 
    ? 'mysql:host='.YIIMP_DBHOST.';port=3306;dbname='.YIIMP_DBNAME
    : 'mysql:host='.YIIMP_DBHOST.';dbname='.YIIMP_DBNAME;

$db = [
    'class' => 'yii\db\Connection',
    'dsn' => $dsn,
    'username' => YIIMP_DBUSER,
    'password' => YIIMP_DBPASSWORD,
    'charset' => 'utf8',
    'enableSchemaCache' => false,
    'enableLogging' => true,
    'enableProfiling' => true,
];

// Use file cache for testing
$cache_config = [
    'class' => 'yii\caching\FileCache',
];

$config = [
    'id' => 'yiimp2-test',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'class' => 'app\components\CsrfAwareRequest',
            'cookieValidationKey' => 'test-key-for-testing-only',
            'enableCsrfValidation' => true, // Enable for CSRF tests (Requirements 1.1, 1.2, 1.3)
            'enableCsrfCookie' => false, // Store CSRF token in session, not cookie (Requirement 1.2)
            'csrfParam' => '_csrf-yiimp2',
            'secureHeaders' => ['X-Forwarded-Proto', 'X-Forwarded-SSL'], // Support proxy HTTPS detection
        ],
        'session' => [
            'class' => 'yii\web\Session', // Use file-based session for tests
            'name' => 'YIIMP2SESSID_TEST',
            'timeout' => 3600,
            'useCookies' => true,
            'cookieParams' => [
                'httponly' => true,
            ],
        ],
        'cache' => $cache_config,
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'class' => 'app\components\EnhancedErrorHandler',
            'errorAction' => 'site/error',
            'maxSourceLines' => 20,
            'maxTraceSourceLines' => 15,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => 3,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/test-error.log',
                    'logVars' => [],
                    'except' => ['yii\db\*', 'app\components\RpcClient'],
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'logFile' => '@runtime/logs/test-info.log',
                    'except' => ['yii\db\*', 'app\components\RpcClient'],
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'categories' => ['yii\db\*'],
                    'logFile' => '@runtime/logs/test-db-error.log',
                    'logVars' => [],
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['app\components\RpcClient'],
                    'logFile' => '@runtime/logs/test-rpc.log',
                    'logVars' => [],
                ],
            ],
        ],
        'db' => $db,
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [],
        ],

        'csrfTokenManager' => [
            'class' => 'app\components\CsrfTokenManager',
            'tokenExpiration' => 3600, // 1 hour
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
        'RpcClient' => [
            'class' => 'app\components\RpcClient',
        ],
    ],
    'params' => $params,
];

return $config;
