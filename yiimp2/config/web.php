<?php

// include serverconfig
require_once('/etc/yiimp2/serverconfig.php');

// Define YIIMP constants if not already defined (backward compatibility)
if (!defined('YIIMP_DBHOST')) define('YIIMP_DBHOST', YAAMP_DBHOST);
if (!defined('YIIMP_DBNAME')) define('YIIMP_DBNAME', YAAMP_DBNAME);
if (!defined('YIIMP_DBUSER')) define('YIIMP_DBUSER', YAAMP_DBUSER);
if (!defined('YIIMP_DBPASSWORD')) define('YIIMP_DBPASSWORD', YAAMP_DBPASSWORD);
if (!defined('YIIMP_LOGS')) define('YIIMP_LOGS', YAAMP_LOGS);

// Environment detection from environment variables or serverconfig
if (!defined('YII_DEBUG')) {
    $debugMode = getenv('YIIMP_DEBUG') ?: (defined('YIIMP_DEBUG') ? YIIMP_DEBUG : false);
    if ($debugMode === 'true' || $debugMode === '1' || $debugMode === true) {
        define('YII_DEBUG', true);
        define('YII_ENV', 'dev');
    }
    else {
        define('YII_DEBUG', false);
        define('YII_ENV', 'prod');
    }
}

// Detect if running behind HTTPS proxy (for proper cookie security)
$isHttps = false;
$httpsDetectionMethod = 'none';

// Check for HTTPS indicators in order of reliability
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isHttps = true;
    $httpsDetectionMethod = 'direct HTTPS ($_SERVER[HTTPS]=' . $_SERVER['HTTPS'] . ')';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $isHttps = true;
    $httpsDetectionMethod = 'X-Forwarded-Proto header';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $isHttps = true;
    $httpsDetectionMethod = 'X-Forwarded-SSL header';
} elseif (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') {
    $isHttps = true;
    $httpsDetectionMethod = 'YIIMP_FORCE_HTTPS environment variable';
}

// Explicit HTTP detection for localhost
if (!$isHttps) {
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    
    if ($serverName === 'localhost' || 
        $serverAddr === '127.0.0.1' || 
        strpos($httpHost, 'localhost') === 0 ||
        strpos($httpHost, '127.0.0.1') === 0) {
        $httpsDetectionMethod = 'HTTP localhost detected';
    } else {
        $httpsDetectionMethod = 'HTTP (no HTTPS indicators)';
    }
}

// Comprehensive logging for protocol detection (always log, not just in debug mode)
// This is critical for diagnosing redirect loop issues
$serverVars = [
    'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
    'HTTP_X_FORWARDED_SSL' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'not set',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'not set',
    'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'not set',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
];

error_log(sprintf(
    '[Yiimp2 Protocol Detection] Protocol: %s | Method: %s | Host: %s | Port: %s | URI: %s',
    $isHttps ? 'HTTPS' : 'HTTP',
    $httpsDetectionMethod,
    $serverVars['HTTP_HOST'],
    $serverVars['SERVER_PORT'],
    $serverVars['REQUEST_URI']
));

// Log detailed server variables for troubleshooting
error_log(sprintf(
    '[Yiimp2 Protocol Detection] Server Variables: HTTPS=%s, X-Forwarded-Proto=%s, X-Forwarded-SSL=%s, SERVER_NAME=%s, SERVER_ADDR=%s',
    $serverVars['HTTPS'],
    $serverVars['HTTP_X_FORWARDED_PROTO'],
    $serverVars['HTTP_X_FORWARDED_SSL'],
    $serverVars['SERVER_NAME'],
    $serverVars['SERVER_ADDR']
));

// Log environment variable state
$forceHttpsEnv = getenv('YIIMP_FORCE_HTTPS');
error_log(sprintf(
    '[Yiimp2 Protocol Detection] Environment: YIIMP_FORCE_HTTPS=%s',
    $forceHttpsEnv !== false ? $forceHttpsEnv : 'not set'
));

// Log cookie configuration that will be applied
error_log(sprintf(
    '[Yiimp2 Cookie Configuration] Secure Flag: %s | SameSite: %s | HttpOnly: true',
    $isHttps ? 'true' : 'false',
    $isHttps ? 'Lax' : 'null'
));

// Session timeout from environment or default to 1 hour
$sessionTimeout = (int)(getenv('YIIMP_SESSION_TIMEOUT') ?: 3600);

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

// Memcached configuration from environment or serverconfig
$memcachedHost = getenv('MEMCACHED_HOST') ?: (defined('YIIMP_MEMCACHE_HOST') ? YIIMP_MEMCACHE_HOST : '');
$memcachedPort = getenv('MEMCACHED_PORT') ?: (defined('YIIMP_MEMCACHE_PORT') ? YIIMP_MEMCACHE_PORT : 11211);

if (!empty($memcachedHost)) {
    $cache_config = [
            'class' => 'yii\caching\MemCache',
            'servers' => [
                [
                    'host' => $memcachedHost,
                    'port' => $memcachedPort,
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

// Log CSRF configuration loading (Requirement 6.5)
error_log(sprintf(
    '[Yiimp2 CSRF Configuration] Loading CSRF configuration: enableCsrfValidation=true, enableCsrfCookie=false, csrfParam=_csrf-yiimp2, sessionTimeout=%d',
    $sessionTimeout
));

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
            'class' => 'yii\web\Request', // Use standard Yii2 Request instead of custom
            
            // Cookie validation key - REQUIRED for security
            // Set via YIIMP_COOKIE_VALIDATION_KEY environment variable
            // Generate with: openssl rand -hex 32
            'cookieValidationKey' => getenv('YIIMP_COOKIE_VALIDATION_KEY') 
                ?: (defined('YIIMP_COOKIE_VALIDATION_KEY') ? YIIMP_COOKIE_VALIDATION_KEY : bin2hex(random_bytes(32))),
            
            // CSRF Protection (Requirements 1.1, 1.2, 1.3)
            'enableCsrfValidation' => true,
            'enableCsrfCookie' => true, // Use cookie-based CSRF tokens for better reliability
            'csrfParam' => '_csrf-yiimp2',
            
            // Trust proxy headers for proper HTTPS detection
            'trustedHosts' => [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ],
        ],
        'session' => [
            'class' => 'yii\web\Session', // Use standard PHP session instead of cache-based
            'name' => getenv('YIIMP_SESSION_NAME') ?: 'YIIMP2SESSID',
            'timeout' => $sessionTimeout,
            'useCookies' => true,
            'cookieParams' => [
                'httponly' => true,
                'secure' => $isHttps, // Auto-detected based on HTTPS
                'sameSite' => $isHttps ? 'Lax' : null, // CSRF protection for HTTPS
            ],
        ],
        'cache' => $cache_config,
        'assetManager' => [
            'appendTimestamp' => true, // Cache busting via timestamps
            'bundles' => YII_ENV === 'prod' ? [
                // Minify and combine assets in production
                'yii\web\JqueryAsset' => [
                    'js' => ['jquery.min.js']
                ],
                'yii\bootstrap5\BootstrapAsset' => [
                    'css' => ['css/bootstrap.min.css'],
                ],
                'yii\bootstrap5\BootstrapPluginAsset' => [
                    'js' => ['js/bootstrap.bundle.min.js']
                ],
            ] : [],
            'linkAssets' => YII_ENV === 'dev', // Use symlinks in dev for faster updates
            'hashCallback' => function ($path) {
                // Custom hash for better cache busting
                return hash('crc32', $path . filemtime($path));
            },
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
            // Explicitly set loginUrl to admin login page (Requirements 1.1, 1.2, 4.1, 4.2, 4.5)
            // This ensures that when authentication is required, users are redirected to the correct login page
            // Public pages in SiteController don't require authentication, so they won't trigger this redirect
            'loginUrl' => ['admin/login'],
        ],
        'errorHandler' => [
            'class' => 'app\components\EnhancedErrorHandler',
            'errorAction' => 'site/error',
            'maxSourceLines' => 20,
            'maxTraceSourceLines' => 15,
        ],
        'cspNonce' => [
            'class' => 'app\components\CspNonceManager',
        ],
        'cspViolationAnalyzer' => [
            'class' => 'app\components\CspViolationAnalyzer',
        ],
        'cspViolationReporter' => [
            'class' => 'app\components\CspViolationReporter',
        ],
        'YiimpUtils' => [
            'class' => 'app\components\YiimpUtils',
        ],
        'ConversionUtils' => [
            'class' => 'app\components\ConversionUtils',
        ],
        'response' => [
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                // CSP header is now set by Nginx with nonce
                // We just need to ensure the nonce is available in views
                if (Yii::$app->has('view') && Yii::$app->has('cspNonce')) {
                    $cspNonce = Yii::$app->get('cspNonce');
                    Yii::$app->view->params['cspNonce'] = $cspNonce->getNonce();
                }
            },
        ],
        'view' => [
            'class' => 'app\components\CspView',
            'on beforeRender' => function ($event) {
                // Ensure nonce is available in all views
                // Nonce is generated by Nginx and passed via HTTP_X_CSP_NONCE
                if (Yii::$app->has('cspNonce')) {
                    $cspNonce = Yii::$app->get('cspNonce');
                    $event->sender->params['cspNonce'] = $cspNonce->getNonce();
                }
            },
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
                // General error and warning logging with stack traces
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-error.log' : '@runtime/logs/yiimp2-error.log',
                    'logVars' => ['_GET', '_POST', '_SERVER'],
                    'maxFileSize' => 10240, // 10MB
                    'maxLogFiles' => 5,
                    'except' => ['yii\db\*', 'app\components\RpcClient'],
                ],
                // Info level logging
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-info.log' : '@runtime/logs/yiimp2-info.log',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'except' => ['yii\db\*', 'app\components\RpcClient'],
                ],
                // Debug/trace logging (only in debug mode)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['trace'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-debug.log' : '@runtime/logs/yiimp2-debug.log',
                    'enabled' => YII_DEBUG,
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 3,
                ],
                // Database query error logging with SQL details
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'categories' => ['yii\db\*'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-db-error.log' : '@runtime/logs/yiimp2-db-error.log',
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'prefix' => function ($message) {
                        return '[DB ERROR]';
                    },
                ],
                // Database query profiling (only in debug mode)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['profile'],
                    'categories' => ['yii\db\Command::query', 'yii\db\Command::execute'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-db-profile.log' : '@runtime/logs/yiimp2-db-profile.log',
                    'enabled' => YII_DEBUG,
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 3,
                ],
                // RPC call error logging
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['app\components\RpcClient'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-rpc.log' : '@runtime/logs/yiimp2-rpc.log',
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'prefix' => function ($message) {
                        if ($message[0] === 'error') {
                            return '[RPC ERROR]';
                        } elseif ($message[0] === 'warning') {
                            return '[RPC WARNING]';
                        }
                        return '[RPC]';
                    },
                ],
                // CSRF validation logging (Requirements 6.1, 6.2, 6.3)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['csrf.*', 'app\components\CsrfValidationBehavior'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-csrf.log' : '@runtime/logs/yiimp2-csrf.log',
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'prefix' => function ($message) {
                        if ($message[0] === 'error') {
                            return '[CSRF ERROR]';
                        } elseif ($message[0] === 'warning') {
                            return '[CSRF WARNING]';
                        }
                        return '[CSRF]';
                    },
                ],
                // Session lifecycle logging (Requirements 6.4, 6.5)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['session.*'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-session.log' : '@runtime/logs/yiimp2-session.log',
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'prefix' => function ($message) {
                        if ($message[0] === 'error') {
                            return '[SESSION ERROR]';
                        } elseif ($message[0] === 'warning') {
                            return '[SESSION WARNING]';
                        }
                        return '[SESSION]';
                    },
                ],
                // CSP violation logging (Requirements 2.1, 3.3)
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['csp.*'],
                    'logFile' => defined('YIIMP_LOGS') ? YIIMP_LOGS.'/yiimp2-csp.log' : '@runtime/logs/yiimp2-csp.log',
                    'logVars' => [],
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 5,
                    'prefix' => function ($message) {
                        if ($message[0] === 'error') {
                            return '[CSP ERROR]';
                        } elseif ($message[0] === 'warning') {
                            return '[CSP WARNING]';
                        }
                        return '[CSP]';
                    },
                ],
            ],
        ],
        'db' => $db,
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // ============================================================
                // SITE ROUTES
                // ============================================================
                // Public-facing pool pages accessible to all users
                
                '' => 'site/index',                          // Homepage with pool statistics
                'mining' => 'site/mining',                   // Mining instructions and setup
                'miners' => 'site/miners',                   // Active miners listing
                'diff' => 'site/diff',                       // Difficulty charts
                'multialgo' => 'site/multialgo',            // Multi-algorithm statistics
                'api' => 'site/api',                        // API documentation page
                'block/<id:\d+>' => 'site/block',           // Block details by ID
                'tx/<hash:[a-f0-9]+>' => 'site/tx',         // Transaction details by hash
                
                // ============================================================
                // EXPLORER ROUTES
                // ============================================================
                // Blockchain explorer for viewing coin data
                
                'explorer' => 'explorer/index',                      // Explorer home with coin list
                'explorer/<symbol:\w+>' => 'explorer/coin',          // Coin-specific explorer
                'explorer/<id:\d+>/block' => 'explorer/block',       // Block explorer for coin
                'explorer/<id:\d+>/tx' => 'explorer/tx',             // Transaction explorer
                'explorer/<id:\d+>/search' => 'explorer/search',     // Search within coin blockchain
                'explorer/<id:\d+>/peers' => 'explorer/peers',       // Network peers for coin
                'explorer/<id:\d+>/graph' => 'explorer/graph',       // Blockchain visualization
                
                // ============================================================
                // ADMIN ROUTES
                // ============================================================
                // Administrative interface for pool management
                // Requires authentication and admin privileges
                //
                // URL ROUTING CONVENTION:
                // - Yii2 automatically converts kebab-case URLs to camelCase action names
                // - Example: /admin/coin-wallets → AdminController::actionCoinWallets()
                // - Example: /admin/coin-update/5 → AdminController::actionCoinUpdate($id = 5)
                //
                // PATTERN EXPLANATION:
                // - <action:\w+> matches any word characters (letters, numbers, underscore)
                // - Yii2 converts hyphens in URLs to camelCase for action method names
                // - <id:\d+> matches numeric IDs only (one or more digits)
                //
                // AVAILABLE ADMIN ACTIONS:
                // Authentication:
                //   - login              : Admin login page
                //   - logout             : Admin logout (POST only)
                //
                // Dashboard:
                //   - dashboard          : Main admin dashboard
                //   - dashboard-results  : AJAX endpoint for dashboard data
                //
                // Coin Management:
                //   - coinwallets        : List all coins (main coin management page)
                //   - coin-create        : Create new coin
                //   - coin-update        : Update existing coin (requires ID)
                //   - coin               : View coin details (requires ID)
                //   - coin-console       : RPC console for coin (requires ID)
                //   - coin-peers         : Manage coin network peers (requires ID)
                //   - add-peer           : Add peer to coin (requires ID, POST)
                //   - remove-peer        : Remove peer from coin (requires ID, POST)
                //   - coin-triggers      : Blockchain trigger management (requires ID)
                //   - enable-trigger     : Enable specific trigger (requires ID, POST)
                //   - disable-trigger    : Disable specific trigger (requires ID, POST)
                //   - reset-trigger      : Reset trigger state (requires ID, POST)
                //   - start-coin         : Start coin daemon (requires ID)
                //   - stop-coin          : Stop coin daemon (requires ID)
                //   - restart-coin       : Restart coin daemon (requires ID)
                //   - reset-blockchain   : Reset blockchain data (requires ID, POST)
                //   - uninstall-coin     : Uninstall coin completely (requires ID, POST)
                //
                // User Management:
                //   - user               : User management page
                //   - user-results       : AJAX endpoint for user listing
                //   - ban-user           : Ban user account (requires ID, POST)
                //   - unban-user         : Unban user account (requires ID, POST)
                //
                // Worker Monitoring:
                //   - worker             : Worker monitoring page
                //   - worker-results     : AJAX endpoint for worker listing
                //
                // Payment Management:
                //   - payments           : Payment monitoring page
                //   - payments-results   : AJAX endpoint for payment listing
                //   - cancel-payment     : Cancel pending payment (requires ID, POST)
                //
                // Earnings Management:
                //   - earning            : Earnings monitoring page
                //   - earning-results    : AJAX endpoint for earnings listing
                //   - delete-earning     : Delete earning record (requires ID, POST)
                //
                // Exchange Management:
                //   - exchange           : Exchange status page
                //   - exchange-results   : AJAX endpoint for exchange data
                //   - balances           : Exchange balances page
                //   - balances-results   : AJAX endpoint for balance data
                //
                // Network Monitoring:
                //   - connections        : Active connections monitoring
                //   - connections-results: AJAX endpoint for connection data
                //   - botnets            : Botnet detection page
                //   - monsters           : High hashrate anomaly detection
                //   - block-pattern      : Block suspicious IP/pattern (POST)
                //
                // System Management:
                //   - memcached          : Memcached statistics and management
                //   - clear-cache        : Clear all cache (POST)
                //   - version            : Coin daemon version monitoring
                //   - version-results    : AJAX endpoint for version data
                //
                // ROUTE PATTERNS:
                'admin' => 'admin/dashboard',                        // Default admin page
                'admin/<action:\w+>' => 'admin/<action>',           // Admin actions without ID
                'admin/<action:\w+>/<id:\d+>' => 'admin/<action>',  // Admin actions with numeric ID
                
                // ============================================================
                // RENTAL ROUTES
                // ============================================================
                // Hashrate rental marketplace
                
                'renting' => 'renting/index',                // Rental marketplace home
                'renting/<action:\w+>' => 'renting/<action>', // Rental actions
                
                // ============================================================
                // TRADING ROUTES
                // ============================================================
                // Internal exchange/trading interface
                
                'trading' => 'trading/index',                // Trading interface home
                'trading/<action:\w+>' => 'trading/<action>', // Trading actions
                
                // ============================================================
                // BENCHMARK ROUTES
                // ============================================================
                // Mining hardware benchmark database
                
                'bench' => 'bench/index',                    // Benchmark database home
                'bench/submit' => 'bench/submit',            // Submit new benchmark
                'bench/devices' => 'bench/devices',          // Device listing
                'bench/<algo:\w+>' => 'bench/algo',          // Algorithm-specific benchmarks
                
                // ============================================================
                // NICEHASH ROUTES
                // ============================================================
                // NiceHash integration and monitoring
                
                'nicehash' => 'nicehash/index',              // NiceHash status page
                
                // ============================================================
                // STATS ROUTES
                // ============================================================
                // Statistics and chart data endpoints
                
                'stats' => 'stats/index',                    // Statistics page
                'stats/graph_results_<id:\d+>' => 'stats/graph_results_<id>', // Chart data endpoints
                
                // ============================================================
                // API ROUTES
                // ============================================================
                // RESTful API endpoints for external integrations
                // These routes provide JSON responses for pool data
                
                'api/status' => 'api/status',                // Pool status and statistics
                'api/walletEx' => 'api/wallet-ex',           // Extended wallet information
                'api/wallet' => 'api/wallet',                // Basic wallet information
                'api/currency' => 'api/currency',            // Currency/coin information
                'api/blocks' => 'api/blocks',                // Recent blocks found
            ],
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
