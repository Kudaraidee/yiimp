<?php
/**
 * Constant compatibility bridge.
 *
 * If /etc/yiimp/serverconfig.php is an older version that only defines
 * YAAMP_* constants, this file promotes them to YIIMP_* so that the Yii2
 * application works without requiring an immediate serverconfig update.
 *
 * Pools that have already updated their serverconfig.php to define YIIMP_*
 * will have the `defined()` guard short-circuit every entry — no overhead.
 */
$_bridge = [
    'LOGS', 'HTDOCS', 'BIN',
    'DBHOST', 'DBNAME', 'DBUSER', 'DBPASSWORD',
    'SITE_URL', 'STRATUM_URL', 'SITE_NAME',
    'PRODUCTION', 'RENTAL', 'LIMIT_ESTIMATE',
    'FEES_SOLO', 'FEES_MINING', 'FEES_EXCHANGE', 'FEES_RENTING',
    'TXFEE_RENTING_WD', 'PAYMENTS_FREQ', 'PAYMENTS_MINI',
    'ALLOW_EXCHANGE', 'BTCADDRESS',
    'ADMIN_EMAIL', 'ADMIN_USER', 'ADMIN_PASS',
    'ADMIN_IP', 'ADMIN_WEBCONSOLE',
    'CREATE_NEW_COINS', 'NOTIFY_NEW_COINS', 'DEFAULT_ALGO',
    'USE_NGINX', 'USE_NICEHASH_API', 'PUBLIC_BENCHMARK',
];
foreach ($_bridge as $_k) {
    if (!defined("YIIMP_{$_k}") && defined("YAAMP_{$_k}")) {
        define("YIIMP_{$_k}", constant("YAAMP_{$_k}"));
    }
}
unset($_bridge, $_k);

if (!defined('YIIMP_KWH_USD_PRICE'))        define('YIIMP_KWH_USD_PRICE',        0.25);
if (!defined('YIIMP_CLI_ALLOW_TXS'))        define('YIIMP_CLI_ALLOW_TXS',        false);
if (!defined('YIIMP_CLI_ALLOW_DISTCLEAN'))  define('YIIMP_CLI_ALLOW_DISTCLEAN',  false);
if (!defined('YIIMP_MAINTENANCE_MODE'))     define('YIIMP_MAINTENANCE_MODE',     false);
if (!defined('YIIMP_LAYOUT'))               define('YIIMP_LAYOUT',               'legacy');
if (!defined('YIIMP_TAILWIND_CDN'))         define('YIIMP_TAILWIND_CDN',         true);
