<?php

ini_set('date.timezone', 'UTC');

// ── Database ──────────────────────────────────────────────────────────────
define('YIIMP_DBHOST',     'localhost');
define('YIIMP_DBNAME',     'yaamp');
define('YIIMP_DBUSER',     'root');
define('YIIMP_DBPASSWORD', 'password');

// ── Cache ─────────────────────────────────────────────────────────────────
define('YIIMP_MEMCACHE_HOST', '127.0.0.1');
define('YIIMP_MEMCACHE_PORT', 11211);

// ── Paths (inside the container) ──────────────────────────────────────────
define('YIIMP_LOGS',   '/var/www/log');
define('YIIMP_HTDOCS', '/var/www');
define('YIIMP_BIN',    '/var/www/bin');

// ── Site ──────────────────────────────────────────────────────────────────
define('YIIMP_SITE_URL',         'pool.example.com');
define('YIIMP_STRATUM_URL',      YIIMP_SITE_URL);   // change if stratum is on a different host
define('YIIMP_SITE_NAME',        'YiiMP');
define('YIIMP_DEFAULT_ALGO',     'x11');
define('YIIMP_FIAT_ALTERNATIVE', 'EUR');             // base fiat currency shown next to BTC prices
define('YIIMP_PRODUCTION',       true);
define('YIIMP_USE_NGINX',        false);

// ── Admin ─────────────────────────────────────────────────────────────────
define('YIIMP_ADMIN_EMAIL',      'admin@example.com');
define('YIIMP_ADMIN_USER',       'admin');
define('YIIMP_ADMIN_PASS',       'change-me');
define('YIIMP_ADMIN_IP',         '');               // restrict admin to IP(s): "1.2.3.4" or "10.0.0.0/8"
define('YIIMP_ADMIN_WEBCONSOLE', true);
define('YIIMP_ADMIN_LOGIN',      false);

// ── Payments ──────────────────────────────────────────────────────────────
define('YIIMP_FEES_SOLO',          1);               // % fee on solo-mined blocks
define('YIIMP_FEES_MINING',        0.5);             // % fee on shared-pool blocks
define('YIIMP_FEES_EXCHANGE',      2);               // % fee deducted when setting coin price
define('YIIMP_PAYMENTS_FREQ',      3*60*60);         // seconds between payout cycles
define('YIIMP_PAYMENTS_MINI',      0.001);           // minimum payout amount (BTC)

// ── Features ─────────────────────────────────────────────────────────────
define('YIIMP_ALLOW_EXCHANGE',   false);             // enable multi-coin auto-exchange
define('YIIMP_RENTAL',           false);             // enable hash-power renting (pool rents OUT to external renters)
define('YIIMP_LIMIT_ESTIMATE',   false);             // cap estimate at 1.5× 24h average

// ── Renting (only relevant when YIIMP_RENTAL = true) ─────────────────────
define('YIIMP_FEES_RENTING',       2);               // % fee charged to renters per share
define('YIIMP_TXFEE_RENTING_WD',   0.002);           // BTC tx fee deducted on renter withdrawal
define('YIIMP_CREATE_NEW_COINS', true);              // auto-create coin records from market data
define('YIIMP_NOTIFY_NEW_COINS', false);
define('YIIMP_PUBLIC_EXPLORER',  true);
define('YIIMP_PUBLIC_BENCHMARK', false);

// Optional: wallet API extensions
// define('YIIMP_API_PAYOUTS',        true);          // expose payouts in /api/walletEx
// define('YIIMP_API_PAYOUTS_PERIOD', 24*60*60);      // payout history window (seconds)

// ── Pool BTC address (used in cold-wallet distribution) ───────────────────
define('YIIMP_BTCADDRESS', '');

// ── NiceHash (pool buys hash power FROM NiceHash) ─────────────────────────
// Uses NiceHash REST API v2 — three credentials required.
// See docs/migration_plan_yii2.md for NicehashService configuration.
define('YIIMP_USE_NICEHASH_API',    false);
// define('NICEHASH_API_KEY',        'your-api-key-uuid');      // v2 API key UUID
// define('NICEHASH_API_SECRET',     'your-hmac-signing-secret'); // v2 — different from API key
// define('NICEHASH_ORG_ID',         'your-organisation-uuid');   // v2 — from account settings
// define('NICEHASH_DEPOSIT',        '');          // BTC address that receives mining rewards
// define('NICEHASH_DEPOSIT_AMOUNT', '0.01');      // BTC per order
// define('NICEHASH_MARKET',         'EU');        // EU | EU_N | USA | USA_E
// define('NICEHASH_POOL_HOST',      YIIMP_SITE_URL); // stratum hostname sent to NiceHash

// ── Database backup ───────────────────────────────────────────────────────
define('YIIMP_MYSQLDUMP_USER', 'root');
define('YIIMP_MYSQLDUMP_PASS', '');

// ── GitHub (coin wallet version scanning) ────────────────────────────────
define('GITHUB_ACCESSTOKEN', '<github-username>:<personal-access-token>');

// ─────────────────────────────────────────────────────────────────────────
// Backward-compatible aliases for web/ (Yii 1.1 legacy code).
// The Yii1 codebase uses YAAMP_ names; these shims keep it working.
// Edit the YIIMP_ values above — do not change anything below this line.
// ─────────────────────────────────────────────────────────────────────────
define('YAAMP_LOGS',             YIIMP_LOGS);
define('YAAMP_HTDOCS',           YIIMP_HTDOCS);
define('YAAMP_BIN',              YIIMP_BIN);
define('YAAMP_DBHOST',           YIIMP_DBHOST);
define('YAAMP_DBNAME',           YIIMP_DBNAME);
define('YAAMP_DBUSER',           YIIMP_DBUSER);
define('YAAMP_DBPASSWORD',       YIIMP_DBPASSWORD);
define('YAAMP_SITE_URL',         YIIMP_SITE_URL);
define('YAAMP_STRATUM_URL',      YIIMP_STRATUM_URL);
define('YAAMP_SITE_NAME',        YIIMP_SITE_NAME);
define('YAAMP_PRODUCTION',       YIIMP_PRODUCTION);
define('YAAMP_RENTAL',           YIIMP_RENTAL);
define('YAAMP_LIMIT_ESTIMATE',   YIIMP_LIMIT_ESTIMATE);
define('YAAMP_FEES_SOLO',        YIIMP_FEES_SOLO);
define('YAAMP_FEES_MINING',      YIIMP_FEES_MINING);
define('YAAMP_FEES_EXCHANGE',    YIIMP_FEES_EXCHANGE);
define('YAAMP_FEES_RENTING',     YIIMP_FEES_RENTING);
define('YAAMP_TXFEE_RENTING_WD', YIIMP_TXFEE_RENTING_WD);
define('YAAMP_PAYMENTS_FREQ',    YIIMP_PAYMENTS_FREQ);
define('YAAMP_PAYMENTS_MINI',    YIIMP_PAYMENTS_MINI);
define('YAAMP_ALLOW_EXCHANGE',   YIIMP_ALLOW_EXCHANGE);
define('YAAMP_BTCADDRESS',       YIIMP_BTCADDRESS);
define('YAAMP_ADMIN_EMAIL',      YIIMP_ADMIN_EMAIL);
define('YAAMP_ADMIN_USER',       YIIMP_ADMIN_USER);
define('YAAMP_ADMIN_PASS',       YIIMP_ADMIN_PASS);
define('YAAMP_ADMIN_IP',         YIIMP_ADMIN_IP);
define('YAAMP_ADMIN_WEBCONSOLE', YIIMP_ADMIN_WEBCONSOLE);
define('YAAMP_CREATE_NEW_COINS', YIIMP_CREATE_NEW_COINS);
define('YAAMP_NOTIFY_NEW_COINS', YIIMP_NOTIFY_NEW_COINS);
define('YAAMP_DEFAULT_ALGO',     YIIMP_DEFAULT_ALGO);
define('YAAMP_USE_NGINX',        YIIMP_USE_NGINX);
define('YAAMP_USE_NICEHASH_API', YIIMP_USE_NICEHASH_API);

// ─────────────────────────────────────────────────────────────────────────
// SMTP (for admin notifications and payout alerts)
// ─────────────────────────────────────────────────────────────────────────
define('SMTP_HOST',         'mail.example.com');
define('SMTP_PORT',         587);
define('SMTP_USEAUTH',      true);
define('SMTP_USERNAME',     'pool@example.com');
define('SMTP_PASSWORD',     'smtp-password');
define('SMTP_DEFAULT_FROM', 'pool@example.com');
define('SMTP_DEFAULT_HELO', 'pool.example.com');

// ─────────────────────────────────────────────────────────────────────────
// Exchange API keys (leave empty on public-facing instances)
// Required only for auto-exchange and coin-selling features.
// ─────────────────────────────────────────────────────────────────────────
define('EXCH_BINANCE_KEY',     '');
define('EXCH_BINANCE_SECRET',  '');
define('EXCH_CEXIO_SECRET',    '');
define('EXCH_EXBITRON_KEY',    '');
define('EXCH_HITBTC_KEY',      '');
define('EXCH_HITBTC_SECRET',   '');
define('EXCH_KRAKEN_KEY',      '');
define('EXCH_KRAKEN_SECRET',   '');
define('EXCH_KUCOIN_SECRET',   '');
define('EXCH_POLONIEX_KEY',    '');
define('EXCH_POLONIEX_SECRET', '');
define('EXCH_SAFETRADE_KEY',   '');
define('EXCH_SAFETRADE_SECRET','');
define('EXCH_YOBIT_KEY',       '');
define('EXCH_YOBIT_SECRET',    '');
define('EXCH_NONKYC_KEY',      '');
define('EXCH_NONKYC_SECRET',   '');
define('EXCH_NESTEX_KEY',      '');
define('EXCH_NESTEX_SECRET',   '');

define('EXCH_AUTO_WITHDRAW', 0.3);   // auto-withdraw to cold wallet when BTC balance exceeds this

// ─────────────────────────────────────────────────────────────────────────
// Advanced / optional
// ─────────────────────────────────────────────────────────────────────────

// Cold-wallet distribution on each BTC payout cycle (wallet address => share %)
$cold_wallet_table = [
//  '1YourColdWalletAddressHere' => 0.10,   // 10%
];

// Per-algo pool fee overrides (% — overrides YIIMP_FEES_MINING for specific algos)
$configFixedPoolFees = [
//  'scrypt' => 1.0,
//  'sha256' => 0.5,
];

// Per-algo solo fee overrides (% — overrides YIIMP_FEES_SOLO)
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
