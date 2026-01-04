<?php
/**
 * Test server configuration
 * This file is used for running tests and doesn't require actual database connection
 */

// Database configuration
// Use Unix socket for MySQL connection in container
if (!defined('YAAMP_DBHOST')) define('YAAMP_DBHOST', 'localhost:/var/run/mysqld/mysqld.sock');
if (!defined('YAAMP_DBNAME')) define('YAAMP_DBNAME', 'yaamp');
if (!defined('YAAMP_DBUSER')) define('YAAMP_DBUSER', 'root');
if (!defined('YAAMP_DBPASSWORD')) define('YAAMP_DBPASSWORD', '');

// Site configuration
define('YAAMP_SITE_URL', 'http://localhost');
define('YAAMP_SITE_NAME', 'Test Pool');
define('YAAMP_ADMIN_EMAIL', 'admin@test.local');
define('YAAMP_ADMIN_IP', '127.0.0.1');
define('YAAMP_ADMIN_WEBCONSOLE', true);
define('YAAMP_ADMIN_USER', 'admin');
define('YAAMP_ADMIN_PASS', 'testpass123');

// Fees
define('YAAMP_FEES_MINING', 0.5);
define('YAAMP_FEES_EXCHANGE', 0.2);
define('YAAMP_FEES_RENTING', 0.5);

// Payments
define('YAAMP_PAYMENTS_FREQ', 3600);
define('YAAMP_PAYMENTS_MINI', 0.001);

// Memcache (disabled for tests)
define('YIIMP_MEMCACHE_HOST', '');
define('YIIMP_MEMCACHE_PORT', 11211);

// Logs
if (!defined('YIIMP_LOGS')) define('YIIMP_LOGS', __DIR__ . '/../_output');
if (!defined('YAAMP_LOGS')) define('YAAMP_LOGS', __DIR__ . '/../_output');

// Debug
define('YIIMP_DEBUG', true);

// Stratum
define('YAAMP_STRATUM_URL', 'stratum+tcp://localhost');
define('YAAMP_STRATUM_PORT', 3333);

// Rental
define('YAAMP_RENTAL', false);
define('YAAMP_RENTAL_FEE', 0.5);

// Allow web server
define('YAAMP_ALLOW_EXCHANGE', true);
define('YAAMP_ALLOW_MINING', true);
define('YAAMP_ALLOW_RENTING', true);

// Production
define('YAAMP_PRODUCTION', false);

// Use SSL
define('YAAMP_USE_SSL', false);

// Nicehash
define('YAAMP_NICEHASH_FEE', 0.02);

// Benchmarks
define('YAAMP_BENCHMARKS_HASH_POWER', 1000);

// Difficulty
define('YAAMP_DIFFICULTY_ALGO', 'scrypt');

// Auto exchange
define('YAAMP_AUTO_EXCHANGE', false);

// Payout
define('YAAMP_PAYOUT_THRESHOLD', 0.001);

// API Configuration
define('YIIMP_API_URL', YAAMP_SITE_URL); // Base URL for API documentation examples
define('YIIMP_API_PAYOUTS', false); // Enable/disable payout history in walletEx endpoint
define('YIIMP_API_PAYOUTS_PERIOD', 24*60*60); // Time period in seconds for payout history (default: 24 hours)
