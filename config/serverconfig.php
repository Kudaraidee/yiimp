<?php
/**
 * Yiimp Server Configuration Template
 * 
 * Copy this file to /etc/yiimp/serverconfig.php and customize for your installation.
 * DO NOT commit your actual configuration with secrets to version control.
 */

ini_set('date.timezone', 'UTC');

// ============================================================
// PATH CONFIGURATION
// ============================================================
define('YAAMP_LOGS', '/var/www/yaamp/runtime');
define('YAAMP_HTDOCS', '/var/www');
define('YAAMP_BIN', '/var/www/bin');

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('YAAMP_DBHOST', 'localhost');
define('YAAMP_DBNAME', 'yaamp');
define('YAAMP_DBUSER', 'yaamp');
define('YAAMP_DBPASSWORD', 'change_this_password');

// ============================================================
// SITE CONFIGURATION
// ============================================================
define('YAAMP_SITE_URL', 'your-pool.example.com');
define('YAAMP_STRATUM_URL', YAAMP_SITE_URL); // change if your stratum server is on a different host
define('YAAMP_SITE_NAME', 'Your Pool Name');

define('YAAMP_PRODUCTION', true);

// ============================================================
// FEATURE FLAGS
// ============================================================
define('YIIMP_PUBLIC_EXPLORER', true);
define('YIIMP_PUBLIC_BENCHMARK', false);

define('YAAMP_RENTAL', false);
define('YAAMP_LIMIT_ESTIMATE', false);

// ============================================================
// POOL FEES
// ============================================================
define('YAAMP_FEES_SOLO', 1);
define('YAAMP_FEES_MINING', 0.5);
define('YAAMP_FEES_EXCHANGE', 2);
define('YAAMP_FEES_RENTING', 2);
define('YAAMP_TXFEE_RENTING_WD', 0.002);
define('YAAMP_PAYMENTS_FREQ', 3*60*60);
define('YAAMP_PAYMENTS_MINI', 0.1);

// ============================================================
// EXCHANGE CONFIGURATION
// ============================================================
define('YAAMP_ALLOW_EXCHANGE', false);
define('YIIMP_FIAT_ALTERNATIVE', 'EUR'); // USD is main

define('YAAMP_USE_NICEHASH_API', false);

// Pool BTC address for exchange deposits
define('YAAMP_BTCADDRESS', '');

// ============================================================
// ADMIN CONFIGURATION
// ============================================================
define('YIIMP_ADMIN_LOGIN', true);
define('YAAMP_ADMIN_EMAIL', 'admin@your-pool.example.com');
define('YAAMP_ADMIN_USER', 'admin');
define('YAAMP_ADMIN_PASS', 'change_this_password');
define('YAAMP_ADMIN_IP', '127.0.0.1'); // comma-separated IPs or CIDR: "1.2.3.4,5.6.7.8" or "10.0.0.0/8"
define('YAAMP_ADMIN_WEBCONSOLE', true);
define('YAAMP_CREATE_NEW_COINS', false);
define('YAAMP_NOTIFY_NEW_COINS', false);
define('YAAMP_DEFAULT_ALGO', 'sha256');

// ============================================================
// GITHUB API (for coin version checking)
// ============================================================
define('GITHUB_ACCESSTOKEN', ''); // format: username:token

// ============================================================
// SMTP CONFIGURATION (optional)
// ============================================================
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_USEAUTH', false);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_DEFAULT_FROM', 'noreply@your-pool.example.com');
define('SMTP_DEFAULT_HELO', 'your-pool.example.com');

// ============================================================
// WEB SERVER
// ============================================================
define('YAAMP_USE_NGINX', false);

// ============================================================
// BACKUP CONFIGURATION
// ============================================================
define('YIIMP_MYSQLDUMP_USER', 'backup');
define('YIIMP_MYSQLDUMP_PASS', 'change_this_password');

// ============================================================
// EXCHANGE API KEYS
// Leave empty for public frontend, configure in /etc/yiimp/keys.php for backend
// ============================================================
define('EXCH_BINANCE_KEY', '');
define('EXCH_BINANCE_SECRET', '');
define('EXCH_CEXIO_SECRET', '');
define('EXCH_EXBITRON_KEY', '');
define('EXCH_HITBTC_SECRET', '');
define('EXCH_HITBTC_KEY', '');
define('EXCH_KRAKEN_KEY', '');
define('EXCH_KRAKEN_SECRET', '');
define('EXCH_KUCOIN_SECRET', '');
define('EXCH_POLONIEX_KEY', '');
define('EXCH_POLONIEX_SECRET', '');
define('EXCH_SAFETRADE_KEY', '');
define('EXCH_SAFETRADE_SECRET', '');
define('EXCH_TRADEOGRE_KEY', '');
define('EXCH_TRADEOGRE_SECRET', '');
define('EXCH_YOBIT_KEY', '');
define('EXCH_YOBIT_SECRET', '');
define('EXCH_NONKYC_KEY', '');
define('EXCH_NONKYC_SECRET', '');
define('EXCH_XEGGEX_KEY', '');
define('EXCH_XEGGEX_SECRET', '');

// Automatic withdraw threshold (BTC)
define('EXCH_AUTO_WITHDRAW', 0.3);

// ============================================================
// NICEHASH CONFIGURATION (optional)
// ============================================================
define('NICEHASH_API_KEY', '');
define('NICEHASH_API_ID', '');
define('NICEHASH_DEPOSIT', '');
define('NICEHASH_DEPOSIT_AMOUNT', '0.01');

// ============================================================
// COLD WALLET CONFIGURATION
// ============================================================
$cold_wallet_table = array(
    // 'btc_address' => threshold,
);

// ============================================================
// CUSTOM POOL FEES PER ALGORITHM
// ============================================================
$configFixedPoolFees = array(
    // 'algo' => fee_percent,
);

$configFixedPoolFeesSolo = array(
    // 'algo' => fee_percent,
);

// ============================================================
// CUSTOM STRATUM PORTS
// ============================================================
$configCustomPorts = array(
    // 'algo' => port,
);

// ============================================================
// ALGORITHM NORMALIZATION COEFFICIENTS
// ============================================================
$configAlgoNormCoef = array(
    // 'algo' => coefficient,
);
