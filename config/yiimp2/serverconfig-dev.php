<?php
/**
 * Yiimp2 Development Environment Configuration
 * 
 * This configuration is specifically for the development environment.
 * It uses hardcoded values that match the docker-compose.dev.yml setup.
 */

ini_set('date.timezone', 'UTC');

define('YAAMP_LOGS', '/var/log/yiimp2');
define('YAAMP_HTDOCS', '/var/www');
define('YAAMP_BIN', '/var/www/bin');

// Yiimp2 Development Database Configuration
// Hardcoded for dev environment to avoid PHP-FPM environment variable issues
define('YAAMP_DBHOST', 'yiimp2-db-dev');
define('YAAMP_DBNAME', 'yiimp2_dev');
define('YAAMP_DBUSER', 'yiimp2_dev');
define('YAAMP_DBPASSWORD', 'yiimp2_dev');

// Memcache Configuration for Development
define('YIIMP_MEMCACHE_HOST', 'yiimp2-memcached-dev');
define('YIIMP_MEMCACHE_PORT', 11211);

define('YAAMP_SITE_URL', 'localhost:8090');
define('YAAMP_STRATUM_URL', YAAMP_SITE_URL);
define('YAAMP_SITE_NAME', 'Yiimp2 Mining Pool');

define('YAAMP_PRODUCTION', false);

define('YIIMP_PUBLIC_EXPLORER', true);
define('YIIMP_PUBLIC_BENCHMARK', false);

// API Configuration
define('YIIMP_API_URL', YAAMP_SITE_URL);
define('YIIMP_API_PAYOUTS', false);
define('YIIMP_API_PAYOUTS_PERIOD', 24*60*60);

define('YAAMP_RENTAL', false);
define('YAAMP_LIMIT_ESTIMATE', false);

define('YAAMP_FEES_SOLO', 1);
define('YAAMP_FEES_MINING', 0.5);
define('YAAMP_FEES_EXCHANGE', 2);
define('YAAMP_FEES_RENTING', 2);
define('YAAMP_TXFEE_RENTING_WD', 0.002);
define('YAAMP_PAYMENTS_FREQ', 3*60*60);
define('YAAMP_PAYMENTS_MINI', 0.1);

define('YAAMP_ALLOW_EXCHANGE', false);
define('YIIMP_FIAT_ALTERNATIVE', 'EUR');

define('YAAMP_USE_NICEHASH_API', false);

define('YAAMP_BTCADDRESS', '');

define('YIIMP_ADMIN_LOGIN', true);
define('YAAMP_ADMIN_EMAIL', 'admin@localhost');
define('YAAMP_ADMIN_USER', 'admin');
define('YAAMP_ADMIN_PASS', 'changeme');
define('YAAMP_ADMIN_IP', '127.0.0.1');
define('YAAMP_ADMIN_WEBCONSOLE', true);
define('YAAMP_CREATE_NEW_COINS', false);
define('YAAMP_NOTIFY_NEW_COINS', false);
define('YAAMP_DEFAULT_ALGO', 'sha256');

define('GITHUB_ACCESSTOKEN', '');

define('SMTP_HOST', '');
define('SMTP_PORT', 25);
define('SMTP_USEAUTH', false);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_DEFAULT_FROM', '');
define('SMTP_DEFAULT_HELO', '');

define('YAAMP_USE_NGINX', false);

define('YIIMP_MYSQLDUMP_USER', 'yiimp2_dev');
define('YIIMP_MYSQLDUMP_PASS', 'yiimp2_dev');

// Exchange keys (empty for dev)
define('EXCH_BINANCE_KEY', '');
define('EXCH_BINANCE_SECRET', '');
define('EXCH_CEXIO_SECRET', '');
define('EXCH_EXBITRON_KEY', '');
define('EXCH_HITBTC_SECRET', '');
define('EXCH_HITBTC_KEY','');
define('EXCH_KRAKEN_KEY', '');
define('EXCH_KRAKEN_SECRET','');
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

define('EXCH_AUTO_WITHDRAW', 0);

define('NICEHASH_API_KEY','');
define('NICEHASH_API_ID','');
define('NICEHASH_DEPOSIT','');
define('NICEHASH_DEPOSIT_AMOUNT','0');

$cold_wallet_table = array();

$configFixedPoolFees = array();
$configFixedPoolFeesSolo = array();
$configCustomPorts = array();
$configAlgoNormCoef = array();
