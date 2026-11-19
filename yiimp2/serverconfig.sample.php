<?php

// ─────────────────────────────────────────────────────────────────────────────
// YiiMP 2 — server configuration
//
// Copy to /etc/yiimp/serverconfig.php and edit for your deployment.
// All define() calls use defined() guards so the file is safe against
// accidental double-inclusion or evolved mixed-style configs.
//
// This file is Yii2-only.  No YAAMP_* legacy aliases are defined here;
// those live in web/serverconfig.sample.php for the Yii1 legacy layer.
// ─────────────────────────────────────────────────────────────────────────────

ini_set('date.timezone', 'UTC');

// ── Database ─────────────────────────────────────────────────────────────────
defined('YIIMP_DBHOST')     or define('YIIMP_DBHOST',     'localhost');
defined('YIIMP_DBNAME')     or define('YIIMP_DBNAME',     'yaamp');
defined('YIIMP_DBUSER')     or define('YIIMP_DBUSER',     'root');
defined('YIIMP_DBPASSWORD') or define('YIIMP_DBPASSWORD', 'password');

// ── Cache ─────────────────────────────────────────────────────────────────────
// Leave YIIMP_MEMCACHE_HOST empty to fall back to file-based cache.
defined('YIIMP_MEMCACHE_HOST') or define('YIIMP_MEMCACHE_HOST', '127.0.0.1');
defined('YIIMP_MEMCACHE_PORT') or define('YIIMP_MEMCACHE_PORT', 11211);

// ── Paths ─────────────────────────────────────────────────────────────────────
defined('YIIMP_LOGS')   or define('YIIMP_LOGS',   '/var/log/yiimp');
defined('YIIMP_HTDOCS') or define('YIIMP_HTDOCS', '/var/www/html');
defined('YIIMP_BIN')    or define('YIIMP_BIN',    '/var/www/bin');

// ── Site ──────────────────────────────────────────────────────────────────────
defined('YIIMP_SITE_URL')         or define('YIIMP_SITE_URL',         'pool.example.com');
defined('YIIMP_STRATUM_URL')      or define('YIIMP_STRATUM_URL',      YIIMP_SITE_URL);    // override if stratum runs on a separate host
defined('YIIMP_SITE_NAME')        or define('YIIMP_SITE_NAME',        'YiiMP');
defined('YIIMP_DEFAULT_ALGO')     or define('YIIMP_DEFAULT_ALGO',     'x11');
defined('YIIMP_FIAT_ALTERNATIVE') or define('YIIMP_FIAT_ALTERNATIVE', 'EUR');             // fiat currency shown alongside BTC prices
defined('YIIMP_PRODUCTION')       or define('YIIMP_PRODUCTION',       true);
defined('YIIMP_USE_NGINX')        or define('YIIMP_USE_NGINX',        false);

// ── Admin ─────────────────────────────────────────────────────────────────────
defined('YIIMP_ADMIN_EMAIL')      or define('YIIMP_ADMIN_EMAIL',      'admin@example.com');
defined('YIIMP_ADMIN_USER')       or define('YIIMP_ADMIN_USER',       'admin');
defined('YIIMP_ADMIN_PASS')       or define('YIIMP_ADMIN_PASS',       'change-me');
defined('YIIMP_ADMIN_IP')         or define('YIIMP_ADMIN_IP',         '');               // restrict admin login to IP or CIDR, e.g. "10.0.0.0/8"
defined('YIIMP_ADMIN_WEBCONSOLE') or define('YIIMP_ADMIN_WEBCONSOLE', true);
defined('YIIMP_ADMIN_LOGIN')      or define('YIIMP_ADMIN_LOGIN',      false);

// ── Fees & Payments ───────────────────────────────────────────────────────────
defined('YIIMP_FEES_SOLO')     or define('YIIMP_FEES_SOLO',     1);            // % fee on solo-mined blocks
defined('YIIMP_FEES_MINING')   or define('YIIMP_FEES_MINING',   0.5);          // % fee on shared-pool blocks
defined('YIIMP_FEES_EXCHANGE') or define('YIIMP_FEES_EXCHANGE', 2);            // % fee deducted when setting coin price
defined('YIIMP_PAYMENTS_FREQ') or define('YIIMP_PAYMENTS_FREQ', 3 * 60 * 60); // seconds between payout cycles
defined('YIIMP_PAYMENTS_MINI') or define('YIIMP_PAYMENTS_MINI', 0.001);        // minimum payout amount (BTC)

// ── Features ──────────────────────────────────────────────────────────────────
defined('YIIMP_ALLOW_EXCHANGE')  or define('YIIMP_ALLOW_EXCHANGE',  false);    // enable multi-coin auto-exchange
defined('YIIMP_RENTAL')          or define('YIIMP_RENTAL',          false);    // pool rents OUT hash power to external renters
defined('YIIMP_LIMIT_ESTIMATE')  or define('YIIMP_LIMIT_ESTIMATE',  false);    // cap profitability estimate at 1.5× the 24h average
defined('YIIMP_PUBLIC_EXPLORER') or define('YIIMP_PUBLIC_EXPLORER', true);
defined('YIIMP_PUBLIC_BENCHMARK')or define('YIIMP_PUBLIC_BENCHMARK',false);    // expose /bench benchmark browser
defined('YIIMP_CREATE_NEW_COINS')or define('YIIMP_CREATE_NEW_COINS',true);     // auto-create coin records from exchange market data
defined('YIIMP_NOTIFY_NEW_COINS')or define('YIIMP_NOTIFY_NEW_COINS',false);

// ── Renting (only when YIIMP_RENTAL = true) ───────────────────────────────────
defined('YIIMP_FEES_RENTING')     or define('YIIMP_FEES_RENTING',     2);      // % fee charged to renters per share
defined('YIIMP_TXFEE_RENTING_WD') or define('YIIMP_TXFEE_RENTING_WD', 0.002); // BTC tx fee deducted on renter withdrawal

// ── Pool BTC address ──────────────────────────────────────────────────────────
defined('YIIMP_BTCADDRESS') or define('YIIMP_BTCADDRESS', '');

// ── Maintenance mode ──────────────────────────────────────────────────────────
// When true, all web requests return HTTP 503 except for logged-in admins.
// Set to true before maintenance, back to false after.
defined('YIIMP_MAINTENANCE_MODE') or define('YIIMP_MAINTENANCE_MODE', false);

// ── CLI safety gates ──────────────────────────────────────────────────────────
// Enable destructive CLI commands explicitly; leave false in production.
defined('YIIMP_CLI_ALLOW_TXS')       or define('YIIMP_CLI_ALLOW_TXS',       false); // allow payout/redotx and shift/send
defined('YIIMP_CLI_ALLOW_DISTCLEAN') or define('YIIMP_CLI_ALLOW_DISTCLEAN', false); // allow distclean (wipes ALL user data)

// ── Power cost (for benchmark profitability calculations) ─────────────────────
defined('YIIMP_KWH_USD_PRICE') or define('YIIMP_KWH_USD_PRICE', 0.25);        // USD per kWh

// ── Optional wallet API extensions ───────────────────────────────────────────
// defined('YIIMP_API_PAYOUTS',        true);
// defined('YIIMP_API_PAYOUTS_PERIOD', 24 * 60 * 60);

// ── NiceHash (pool BUYS hash power from NiceHash) ─────────────────────────────
// Uses NiceHash REST API v2.  Set USE_NICEHASH_API = true then fill credentials.
defined('YIIMP_USE_NICEHASH_API') or define('YIIMP_USE_NICEHASH_API', false);
// defined('NICEHASH_API_KEY',        'your-api-key-uuid');
// defined('NICEHASH_API_SECRET',     'your-hmac-signing-secret');
// defined('NICEHASH_ORG_ID',         'your-organisation-uuid');
// defined('NICEHASH_DEPOSIT',        '');          // BTC address for mining rewards
// defined('NICEHASH_DEPOSIT_AMOUNT', '0.01');      // BTC per order
// defined('NICEHASH_MARKET',         'EU');        // EU | EU_N | USA | USA_E
// defined('NICEHASH_POOL_HOST',      YIIMP_SITE_URL);

// ── Database backup ───────────────────────────────────────────────────────────
defined('YIIMP_MYSQLDUMP_USER') or define('YIIMP_MYSQLDUMP_USER', 'root');
defined('YIIMP_MYSQLDUMP_PASS') or define('YIIMP_MYSQLDUMP_PASS', '');

// ── GitHub (coin wallet version scanning) ────────────────────────────────────
defined('GITHUB_ACCESSTOKEN') or define('GITHUB_ACCESSTOKEN', '<github-username>:<personal-access-token>');

// ─────────────────────────────────────────────────────────────────────────────
// SMTP (admin notifications and payout alerts)
// ─────────────────────────────────────────────────────────────────────────────
defined('SMTP_HOST')         or define('SMTP_HOST',         'mail.example.com');
defined('SMTP_PORT')         or define('SMTP_PORT',         587);
defined('SMTP_USEAUTH')      or define('SMTP_USEAUTH',      true);
defined('SMTP_USERNAME')     or define('SMTP_USERNAME',     'pool@example.com');
defined('SMTP_PASSWORD')     or define('SMTP_PASSWORD',     'smtp-password');
defined('SMTP_DEFAULT_FROM') or define('SMTP_DEFAULT_FROM', 'pool@example.com');
defined('SMTP_DEFAULT_HELO') or define('SMTP_DEFAULT_HELO', 'pool.example.com');

// ─────────────────────────────────────────────────────────────────────────────
// Exchange API keys
// Leave all values empty on public-facing instances.
// Required only for the auto-exchange and balance-sync drivers.
// ─────────────────────────────────────────────────────────────────────────────

// Bibox
defined('EXCH_BIBOX_KEY')    or define('EXCH_BIBOX_KEY',    '');
defined('EXCH_BIBOX_SECRET') or define('EXCH_BIBOX_SECRET', '');

// Binance
defined('EXCH_BINANCE_KEY')    or define('EXCH_BINANCE_KEY',    '');
defined('EXCH_BINANCE_SECRET') or define('EXCH_BINANCE_SECRET', '');

// Bitstamp  (ID = customer number, KEY = API key, SECRET = signing secret)
defined('EXCH_BITSTAMP_ID')     or define('EXCH_BITSTAMP_ID',     '');
defined('EXCH_BITSTAMP_KEY')    or define('EXCH_BITSTAMP_KEY',    '');
defined('EXCH_BITSTAMP_SECRET') or define('EXCH_BITSTAMP_SECRET', '');

// CEX.io  (ID = username/account ID)
defined('EXCH_CEXIO_ID')     or define('EXCH_CEXIO_ID',     '');
defined('EXCH_CEXIO_KEY')    or define('EXCH_CEXIO_KEY',    '');
defined('EXCH_CEXIO_SECRET') or define('EXCH_CEXIO_SECRET', '');

// Exbitron
defined('EXCH_EXBITRON_KEY')    or define('EXCH_EXBITRON_KEY',    '');
defined('EXCH_EXBITRON_SECRET') or define('EXCH_EXBITRON_SECRET', '');

// HitBTC
defined('EXCH_HITBTC_KEY')    or define('EXCH_HITBTC_KEY',    '');
defined('EXCH_HITBTC_SECRET') or define('EXCH_HITBTC_SECRET', '');

// Kraken
defined('EXCH_KRAKEN_KEY')    or define('EXCH_KRAKEN_KEY',    '');
defined('EXCH_KRAKEN_SECRET') or define('EXCH_KRAKEN_SECRET', '');

// KuCoin
defined('EXCH_KUCOIN_KEY')    or define('EXCH_KUCOIN_KEY',    '');
defined('EXCH_KUCOIN_SECRET') or define('EXCH_KUCOIN_SECRET', '');

// NonKYC
defined('EXCH_NONKYC_KEY')    or define('EXCH_NONKYC_KEY',    '');
defined('EXCH_NONKYC_SECRET') or define('EXCH_NONKYC_SECRET', '');

// Nestex
defined('EXCH_NESTEX_KEY')    or define('EXCH_NESTEX_KEY',    '');
defined('EXCH_NESTEX_SECRET') or define('EXCH_NESTEX_SECRET', '');

// Poloniex
defined('EXCH_POLONIEX_KEY')    or define('EXCH_POLONIEX_KEY',    '');
defined('EXCH_POLONIEX_SECRET') or define('EXCH_POLONIEX_SECRET', '');

// SafeTrade
defined('EXCH_SAFETRADE_KEY')    or define('EXCH_SAFETRADE_KEY',    '');
defined('EXCH_SAFETRADE_SECRET') or define('EXCH_SAFETRADE_SECRET', '');

// Yobit
defined('EXCH_YOBIT_KEY')    or define('EXCH_YOBIT_KEY',    '');
defined('EXCH_YOBIT_SECRET') or define('EXCH_YOBIT_SECRET', '');

// Auto-withdraw to cold wallet when BTC balance exceeds this amount (BTC)
defined('EXCH_AUTO_WITHDRAW') or define('EXCH_AUTO_WITHDRAW', 0.3);

// ─────────────────────────────────────────────────────────────────────────────
// Advanced / optional
// ─────────────────────────────────────────────────────────────────────────────

// Cold-wallet distribution on each BTC payout cycle (wallet address => share %)
$cold_wallet_table = [
//  '1YourColdWalletAddressHere' => 0.10,   // 10%
];

// Per-algo pool fee overrides (overrides YIIMP_FEES_MINING for specific algos)
$configFixedPoolFees = [
//  'scrypt' => 1.0,
//  'sha256' => 0.5,
];

// Per-algo solo fee overrides (overrides YIIMP_FEES_SOLO)
$configFixedPoolFeesSolo = [
//  'scrypt' => 1.0,
];

// Custom stratum port overrides (algo => port)
$configCustomPorts = [
//  'x11' => 7000,
];

// mBTC profitability normalisation coefficients (default 1.0)
$configAlgoNormCoef = [
//  'x11' => 5.0,
];
