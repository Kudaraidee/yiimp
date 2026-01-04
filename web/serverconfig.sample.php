<?php

ini_set('date.timezone', 'UTC');

// add defines with YIIMP_ scheme to get rid of YAAMP_ defines over time

define('YIIMP_MEMCACHE_HOST', '127.0.0.1');
define('YIIMP_MEMCACHE_PORT', 11211);

define('YIIMP_LOGS', '/var/www/log');
define('YIIMP_HTDOCS', '/var/www');
define('YIIMP_BIN', '/var/www/bin');

define('YIIMP_DBHOST', 'localhost');
define('YIIMP_DBNAME', 'yaamp');
define('YIIMP_DBUSER', 'root');
define('YIIMP_DBPASSWORD', 'password');

// Cookie Validation Key - REQUIRED for Yii2 security
// This key is used by Yii2 to validate cookies and prevent tampering
// Set via YIIMP_COOKIE_VALIDATION_KEY environment variable or define here
// Generate with: openssl rand -hex 32
// IMPORTANT: Keep this secret and never commit it to version control
// If not set, a random key will be generated (not recommended for production)
// define('YIIMP_COOKIE_VALIDATION_KEY', 'your-secret-key-here');

define('YIIMP_SITE_URL', 'yiimp.ccminer.org');
define('YIIMP_STRATUM_URL', YIIMP_SITE_URL); // change if your stratum server is on a different host
define('YIIMP_SITE_NAME', 'YiiMP');

define('YIIMP_PRODUCTION', true);

define('YIIMP_LIMIT_ESTIMATE', false);

define('YIIMP_FEES_SOLO', 1);
define('YIIMP_FEES_MINING', 0.5);
define('YIIMP_FEES_EXCHANGE', 2);
define('YIIMP_FEES_RENTING', 2);
define('YIIMP_TXFEE_RENTING_WD', 0.002);
define('YIIMP_PAYMENTS_FREQ', 3*60*60);
define('YIIMP_PAYMENTS_MINI', 0.001);

define('YIIMP_ALLOW_EXCHANGE', false);

define('YIIMP_BTCADDRESS', '1Auhps1mHZQpoX4mCcVL8odU81VakZQ6dR');

define('YIIMP_ADMIN_EMAIL', 'yiimp@spam.la');
define('YIIMP_ADMIN_USER', 'yiimpadmin');
define('YIIMP_ADMIN_PASS', 'set-a-password');
define('YIIMP_ADMIN_IP', ''); // samples: "80.236.118.26,90.234.221.11" or "10.0.0.1/8"
define('YIIMP_ADMIN_WEBCONSOLE', true);
define('YIIMP_CREATE_NEW_COINS', true);
define('YIIMP_NOTIFY_NEW_COINS', false);
define('YIIMP_DEFAULT_ALGO', 'x11');

// old style 'YAAMP_'

define('YAAMP_LOGS', '/var/www/log');
define('YAAMP_HTDOCS', '/var/www');
define('YAAMP_BIN', '/var/www/bin');

define('YAAMP_DBHOST', 'localhost');
define('YAAMP_DBNAME', 'yaamp');
define('YAAMP_DBUSER', 'root');
define('YAAMP_DBPASSWORD', 'password');

define('YAAMP_SITE_URL', 'yiimp.ccminer.org');
define('YAAMP_STRATUM_URL', YAAMP_SITE_URL); // change if your stratum server is on a different host
define('YAAMP_SITE_NAME', 'YiiMP');

define('YAAMP_PRODUCTION', true);

define('YIIMP_PUBLIC_EXPLORER', true);
define('YIIMP_PUBLIC_BENCHMARK', false);

define('YAAMP_RENTAL', true);
define('YAAMP_LIMIT_ESTIMATE', false);

define('YAAMP_FEES_SOLO', 1);
define('YAAMP_FEES_MINING', 0.5);
define('YAAMP_FEES_EXCHANGE', 2);
define('YAAMP_FEES_RENTING', 2);
define('YAAMP_TXFEE_RENTING_WD', 0.002);
define('YAAMP_PAYMENTS_FREQ', 3*60*60);
define('YAAMP_PAYMENTS_MINI', 0.001);

define('YAAMP_ALLOW_EXCHANGE', false);
define('YIIMP_FIAT_ALTERNATIVE', 'EUR'); // USD is main

define('YAAMP_USE_NICEHASH_API', false);

define('YAAMP_BTCADDRESS', '1Auhps1mHZQpoX4mCcVL8odU81VakZQ6dR');

define('YIIMP_ADMIN_LOGIN', false);
define('YAAMP_ADMIN_EMAIL', 'yiimp@spam.la');
define('YAAMP_ADMIN_USER', 'yiimpadmin');
define('YAAMP_ADMIN_PASS', 'set-a-password');
define('YAAMP_ADMIN_IP', ''); // samples: "80.236.118.26,90.234.221.11" or "10.0.0.1/8"
define('YAAMP_ADMIN_WEBCONSOLE', true);
define('YAAMP_CREATE_NEW_COINS', true);
define('YAAMP_NOTIFY_NEW_COINS', false);
define('YAAMP_DEFAULT_ALGO', 'x11');

/* Github access token used to scan coin repos for new releases */
define('GITHUB_ACCESSTOKEN', '<username>:<api-secret>');

/* mail server access data to send mails using external mailserver */
define('SMTP_HOST', 'mail.example.com');
define('SMTP_PORT', 25);
define('SMTP_USEAUTH', true);
define('SMTP_USERNAME', 'mailuser');
define('SMTP_PASSWORD', 'mailpassword');
define('SMTP_DEFAULT_FROM', 'mailuser@example.com');
define('SMTP_DEFAULT_HELO', 'mypool-server.example.com');

define('YAAMP_USE_NGINX', false);

/* Sample config file to put in /etc/yiimp/keys.php */

define('YIIMP_MYSQLDUMP_USER', 'root');
define('YIIMP_MYSQLDUMP_PASS', '<my_mysql_password>');

/* 
 * Exchange access keys
 * for public fronted use separate container instance and leave keys unconfigured
 *
 * access tokens required to create/cancel orders and access your balances/deposit addresses
 */
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

define('EXCH_NESTEX_KEY', '');
define('EXCH_NESTEX_SECRET', '');

// Automatic withdraw to Yaamp btc wallet if btc balance > 0.3
define('EXCH_AUTO_WITHDRAW', 0.3);

// nicehash keys deposit account & amount to deposit at a time
define('NICEHASH_API_KEY','521c254d-8cc7-4319-83d2-ac6c604b5b49');
define('NICEHASH_API_ID','9205');
define('NICEHASH_DEPOSIT','3J9tapPoFCtouAZH7Th8HAPsD8aoykEHzk');
define('NICEHASH_DEPOSIT_AMOUNT','0.01');


$cold_wallet_table = array(
	'1C23KmLeCaQSLLyKVykHEUse1R7jRDv9j9' => 0.10,
);

// Sample fixed pool fees
$configFixedPoolFees = array(
        'zr5' => 2.0,
        'scrypt' => 20.0,
        'sha256' => 5.0,
);

// Sample fixed pool fees solo
$configFixedPoolFeesSolo = array(
		'zr5' => 2.0,
        'scrypt' => 2.0,
        'sha256' => 5.0,
);

// Sample custom stratum ports
$configCustomPorts = array(
//	'x11' => 7000,
);

// mBTC Coefs per algo (default is 1.0)
$configAlgoNormCoef = array(
//	'x11' => 5.0,
);

