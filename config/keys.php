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
