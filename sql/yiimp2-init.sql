-- ============================================================================
-- Yiimp2 Database Initialization Script
-- Generated automatically by bin/generate-yiimp2-schema.sh
-- 
-- This file contains:
-- 1. Base schema from sql/2024-03-06-complete_export.sql.gz
-- 2. All migration files applied in chronological order
-- 3. Decimal column addition to coins table from sql/old-yiimp.sql
--
-- Usage: mysql -u <user> -p <database> < sql/yiimp2-init.sql
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- SECTION 1: Base Schema
-- Source: sql/2024-03-06-complete_export.sql.gz
-- ============================================================================

DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `algos`;
DROP TABLE IF EXISTS `balances`;
DROP TABLE IF EXISTS `balanceuser`;
DROP TABLE IF EXISTS `bench_chips`;
DROP TABLE IF EXISTS `bench_suffixes`;
DROP TABLE IF EXISTS `benchmarks`;
DROP TABLE IF EXISTS `blocks`;
DROP TABLE IF EXISTS `bookmarks`;
DROP TABLE IF EXISTS `coins`;
DROP TABLE IF EXISTS `connections`;
DROP TABLE IF EXISTS `earnings`;
DROP TABLE IF EXISTS `exchange`;
DROP TABLE IF EXISTS `hashrate`;
DROP TABLE IF EXISTS `hashrenter`;
DROP TABLE IF EXISTS `hashstats`;
DROP TABLE IF EXISTS `hashuser`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `jobsubmits`;
DROP TABLE IF EXISTS `market_history`;
DROP TABLE IF EXISTS `markets`;
DROP TABLE IF EXISTS `mining`;
DROP TABLE IF EXISTS `nicehash`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `payouts`;
DROP TABLE IF EXISTS `rawcoins`;
DROP TABLE IF EXISTS `renters`;
DROP TABLE IF EXISTS `rentertxs`;
DROP TABLE IF EXISTS `servers`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `shares`;
DROP TABLE IF EXISTS `stats`;
DROP TABLE IF EXISTS `stratums`;
DROP TABLE IF EXISTS `withdraws`;
DROP TABLE IF EXISTS `workers`;

-- Base schema tables


CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coinid` int DEFAULT NULL,
  `last_earning` int DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT '0',
  `no_fees` tinyint(1) DEFAULT NULL,
  `donation` tinyint unsigned NOT NULL DEFAULT '0',
  `logtraffic` tinyint(1) DEFAULT NULL,
  `balance` double DEFAULT '0',
  `username` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `coinsymbol` varchar(16) DEFAULT NULL,
  `swap_time` int unsigned DEFAULT NULL,
  `login` varchar(45) DEFAULT NULL,
  `hostaddr` varchar(39) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `coin` (`coinid`),
  KEY `balance` (`balance`),
  KEY `earning` (`last_earning`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `algos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(16) DEFAULT NULL,
  `profit` double DEFAULT NULL,
  `rent` double DEFAULT NULL,
  `factor` double DEFAULT NULL,
  `overflow` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3;

INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(1, 'scrypt', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(2, 'scryptn', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(3, 'neoscrypt', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(4, 'quark', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(5, 'lyra2', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(6, 'x11', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(7, 'x13', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(8, 'x14', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(9, 'x15', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(10, 'fresh', 0, 0, 5, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(11, 'sha256', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(12, 'qubit', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(13, 'skein', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(14, 'groestl', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(15, 'blake', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(16, 'keccak', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(17, 'nist5', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(18, 'zr5', 0, 0, 1, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(19, 'c11', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(20, 'drop', 0, 0, 1.5, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(21, 'skein2', 0, 0, 1, 0);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(22, 'bmw', 0, 0, 100, 1);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(23, 'argon2', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(24, 'blake2s', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(25, 'decred', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(26, 'luffa', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(27, 'lyra2v2', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(28, 'penta', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(29, 'dmd-gr', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(30, 'myr-gr', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(31, 'm7m', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(32, 'sib', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(33, 'vanilla', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(34, 'velvet', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(35, 'yescrypt', 0, 0, 1, NULL);
INSERT INTO `algos` (`id`, `name`, `profit`, `rent`, `factor`, `overflow`) VALUES
	(36, 'whirlpool', 0, 0, 1, NULL);

CREATE TABLE IF NOT EXISTS `balances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(16) DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `onsell` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;

INSERT INTO `balances` (`id`, `name`, `balance`, `onsell`) VALUES
	(1, 'exbitron', 0, NULL);
INSERT INTO `balances` (`id`, `name`, `balance`, `onsell`) VALUES
	(2, 'nestex', 0, NULL);
INSERT INTO `balances` (`id`, `name`, `balance`, `onsell`) VALUES
	(3, 'nonkyc', 0, NULL);
INSERT INTO `balances` (`id`, `name`, `balance`, `onsell`) VALUES
	(4, 'safetrade', 0, NULL);

CREATE TABLE IF NOT EXISTS `balanceuser` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `pending` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `benchmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `algo` varchar(16) NOT NULL,
  `type` varchar(8) NOT NULL,
  `khps` double DEFAULT NULL,
  `device` varchar(80) DEFAULT NULL,
  `vendorid` varchar(12) DEFAULT NULL,
  `chip` varchar(32) DEFAULT NULL,
  `idchip` int DEFAULT NULL,
  `arch` varchar(8) DEFAULT NULL,
  `power` int unsigned DEFAULT NULL,
  `plimit` int unsigned DEFAULT NULL,
  `freq` int unsigned DEFAULT NULL,
  `realfreq` int unsigned DEFAULT NULL,
  `memf` int unsigned DEFAULT NULL,
  `realmemf` int unsigned DEFAULT NULL,
  `client` varchar(48) DEFAULT NULL,
  `os` varchar(8) DEFAULT NULL,
  `driver` varchar(32) DEFAULT NULL,
  `intensity` double DEFAULT NULL,
  `throughput` int unsigned DEFAULT NULL,
  `userid` int DEFAULT NULL,
  `time` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bench_userid` (`userid`),
  KEY `ndx_type` (`type`),
  KEY `ndx_algo` (`algo`),
  KEY `ndx_time` (`time` DESC),
  KEY `ndx_chip` (`idchip`),
  CONSTRAINT `fk_bench_chip` FOREIGN KEY (`idchip`) REFERENCES `bench_chips` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `bench_chips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devicetype` varchar(8) DEFAULT NULL,
  `vendorid` varchar(12) DEFAULT NULL,
  `chip` varchar(32) DEFAULT NULL,
  `year` int unsigned DEFAULT NULL,
  `maxtdp` double DEFAULT NULL,
  `blake_rate` double DEFAULT NULL,
  `blake_power` double DEFAULT NULL,
  `x11_rate` double DEFAULT NULL,
  `x11_power` double DEFAULT NULL,
  `sha_rate` double DEFAULT NULL,
  `sha_power` double DEFAULT NULL,
  `scrypt_rate` double DEFAULT NULL,
  `scrypt_power` double DEFAULT NULL,
  `dag_rate` double DEFAULT NULL,
  `dag_power` double DEFAULT NULL,
  `lyra_rate` double DEFAULT NULL,
  `lyra_power` double DEFAULT NULL,
  `neo_rate` double DEFAULT NULL,
  `neo_power` double DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `features` varchar(255) DEFAULT NULL,
  `perfdata` text,
  PRIMARY KEY (`id`),
  KEY `ndx_chip_type` (`devicetype`),
  KEY `ndx_chip_name` (`chip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `bench_suffixes` (
  `vendorid` varchar(12) NOT NULL,
  `chip` varchar(32) DEFAULT NULL,
  `suffix` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`vendorid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `blocks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coin_id` int DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `confirmations` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `userid` int DEFAULT NULL,
  `workerid` int DEFAULT NULL,
  `difficulty_user` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `category` varchar(16) DEFAULT NULL,
  `solo` tinyint(1) DEFAULT NULL,
  `effort` double DEFAULT NULL,
  `algo` varchar(16) DEFAULT 'scrypt',
  `blockhash` varchar(128) DEFAULT NULL,
  `txhash` varchar(128) DEFAULT NULL,
  `segwit` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `algo1` (`algo`),
  KEY `coin` (`coin_id`),
  KEY `category` (`category`),
  KEY `user1` (`userid`),
  KEY `height1` (`height`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Discovered blocks persisted from Litecoin Service';


CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idcoin` int NOT NULL,
  `label` varchar(32) DEFAULT NULL,
  `address` varchar(128) NOT NULL,
  `lastused` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookmarks_coin` (`idcoin`),
  CONSTRAINT `fk_bookmarks_coin` FOREIGN KEY (`idcoin`) REFERENCES `coins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `coins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  `symbol` varchar(16) DEFAULT NULL,
  `symbol2` varchar(16) DEFAULT NULL,
  `algo` varchar(16) DEFAULT NULL,
  `version` varchar(32) DEFAULT NULL,
  `image` varchar(1024) DEFAULT NULL,
  `market` varchar(64) DEFAULT NULL,
  `marketid` int DEFAULT NULL,
  `master_wallet` varchar(1024) DEFAULT NULL,
  `charity_address` varchar(1024) DEFAULT NULL,
  `charity_amount` double DEFAULT NULL,
  `charity_percent` double DEFAULT NULL,
  `deposit_address` varchar(1024) DEFAULT NULL,
  `deposit_minimum` double DEFAULT '1',
  `sellonbid` tinyint(1) DEFAULT NULL,
  `dontsell` tinyint(1) DEFAULT '1',
  `block_explorer` varchar(1024) DEFAULT NULL,
  `index_avg` double DEFAULT NULL,
  `connections` int DEFAULT NULL,
  `errors` varchar(1024) DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `immature` double DEFAULT NULL,
  `cleared` double DEFAULT NULL,
  `available` double DEFAULT NULL,
  `stake` double DEFAULT NULL,
  `mint` double DEFAULT NULL,
  `txfee` double DEFAULT NULL,
  `payout_min` double DEFAULT NULL,
  `payout_max` double DEFAULT NULL,
  `block_time` int DEFAULT NULL,
  `difficulty` double DEFAULT '1',
  `difficulty_pos` double DEFAULT NULL,
  `block_height` int DEFAULT NULL,
  `target_height` int DEFAULT NULL,
  `powend_height` int DEFAULT NULL,
  `network_hash` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `price2` double DEFAULT NULL,
  `reward` double DEFAULT '1',
  `reward_mul` double DEFAULT '1',
  `mature_blocks` int DEFAULT NULL,
  `enable` tinyint(1) DEFAULT '0',
  `auto_ready` tinyint(1) DEFAULT '0',
  `visible` tinyint(1) DEFAULT NULL,
  `no_explorer` tinyint unsigned NOT NULL DEFAULT '0',
  `max_miners` int DEFAULT NULL,
  `max_shares` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `action` int DEFAULT NULL,
  `conf_folder` varchar(128) DEFAULT NULL,
  `program` varchar(128) DEFAULT NULL,
  `rpcuser` varchar(128) DEFAULT NULL,
  `rpcpasswd` varchar(128) DEFAULT NULL,
  `serveruser` varchar(45) DEFAULT NULL,
  `rpchost` varchar(128) DEFAULT NULL,
  `rpcport` int DEFAULT NULL,
  `dedicatedport` int DEFAULT NULL,
  `rpccurl` tinyint(1) NOT NULL DEFAULT '0',
  `rpcssl` tinyint(1) NOT NULL DEFAULT '0',
  `rpccert` varchar(255) DEFAULT NULL,
  `rpcencoding` varchar(16) DEFAULT NULL,
  `account` varchar(64) NOT NULL DEFAULT '',
  `hasgetinfo` tinyint unsigned NOT NULL DEFAULT '1',
  `hassubmitblock` tinyint unsigned NOT NULL DEFAULT '1',
  `hasmasternodes` tinyint(1) NOT NULL DEFAULT '0',
  `usememorypool` tinyint(1) DEFAULT NULL,
  `usesegwit` tinyint unsigned NOT NULL DEFAULT '0',
  `txmessage` tinyint(1) DEFAULT NULL,
  `auxpow` tinyint(1) DEFAULT NULL,
  `multialgos` tinyint(1) NOT NULL DEFAULT '0',
  `lastblock` varchar(128) DEFAULT NULL,
  `network_ttf` int DEFAULT NULL,
  `actual_ttf` int DEFAULT NULL,
  `pool_ttf` int DEFAULT NULL,
  `last_network_found` int DEFAULT NULL,
  `installed` tinyint(1) DEFAULT NULL,
  `watch` tinyint(1) NOT NULL DEFAULT '0',
  `link_site` varchar(1024) DEFAULT NULL,
  `link_exchange` varchar(1024) DEFAULT NULL,
  `link_bitcointalk` varchar(1024) DEFAULT NULL,
  `link_github` varchar(1024) DEFAULT NULL,
  `link_explorer` varchar(1024) DEFAULT NULL,
  `link_twitter` varchar(1024) DEFAULT NULL,
  `link_discord` varchar(1024) DEFAULT NULL,
  `link_facebook` varchar(1024) DEFAULT NULL,
  `donation_address` varchar(1024) DEFAULT NULL,
  `usefaucet` tinyint unsigned NOT NULL DEFAULT '0',
  `specifications` blob,
  PRIMARY KEY (`id`),
  KEY `auto_ready` (`auto_ready`),
  KEY `enable` (`enable`),
  KEY `algo` (`algo`),
  KEY `symbol` (`symbol`),
  KEY `index_avg` (`index_avg`),
  KEY `created` (`created`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;

INSERT INTO `coins` (`id`, `name`, `symbol`, `symbol2`, `algo`, `version`, `image`, `market`, `marketid`, `master_wallet`, `charity_address`, `charity_amount`, `charity_percent`, `deposit_address`, `deposit_minimum`, `sellonbid`, `dontsell`, `block_explorer`, `index_avg`, `connections`, `errors`, `balance`, `immature`, `cleared`, `available`, `stake`, `mint`, `txfee`, `payout_min`, `payout_max`, `block_time`, `difficulty`, `difficulty_pos`, `block_height`, `target_height`, `powend_height`, `network_hash`, `price`, `price2`, `reward`, `reward_mul`, `mature_blocks`, `enable`, `auto_ready`, `visible`, `no_explorer`, `max_miners`, `max_shares`, `created`, `action`, `conf_folder`, `program`, `rpcuser`, `rpcpasswd`, `serveruser`, `rpchost`, `rpcport`, `dedicatedport`, `rpccurl`, `rpcssl`, `rpccert`, `rpcencoding`, `account`, `hasgetinfo`, `hassubmitblock`, `hasmasternodes`, `usememorypool`, `usesegwit`, `txmessage`, `auxpow`, `multialgos`, `lastblock`, `network_ttf`, `actual_ttf`, `pool_ttf`, `last_network_found`, `installed`, `watch`, `link_site`, `link_exchange`, `link_bitcointalk`, `link_github`, `link_explorer`, `link_twitter`, `link_discord`, `link_facebook`, `donation_address`, `usefaucet`, `specifications`) VALUES
	(6, 'Bitcoin', 'BTC', '', 'sha256', '109900', '/images/coin-6.png', '', 0, NULL, NULL, NULL, NULL, NULL, 0.005, 0, 1, '', 0.0000049361618444422, 0, 'This is a pre-release test build - use at your own risk - do not use for mining or merchant applications', 0, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 51076366303.482, NULL, 364900, 349481, NULL, 80.81, 1, 1, 25.21212105, 1, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', NULL, NULL, NULL, 'yaamp1', 10301, NULL, 0, 0, NULL, 'POW', '', 1, 1, 0, NULL, 0, 1, 0, 0, '00000000000000000da2a64a9a8e32623575ba19c3125077d1715c1ba2d3b90c', 2147483647, 596, 2147483647, 1436648004, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL);

CREATE TABLE IF NOT EXISTS `connections` (
  `id` int NOT NULL,
  `user` varchar(64) DEFAULT NULL,
  `host` varchar(64) DEFAULT NULL,
  `db` varchar(64) DEFAULT NULL,
  `created` int DEFAULT NULL,
  `idle` int DEFAULT NULL,
  `last` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(24634, 'root', 'localhost', 'yaamp', 1459693738, 239, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25098, 'root', 'localhost', 'yaamp', 1459693738, 308, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25099, 'root', 'localhost', 'yaamp', 1459693738, 301, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25100, 'root', 'localhost', 'yaamp', 1459693738, 308, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25101, 'root', 'localhost', 'yaamp', 1459693738, 240, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25102, 'root', 'localhost', 'yaamp', 1459693738, 300, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25103, 'root', 'localhost', 'yaamp', 1459693738, 242, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25104, 'root', 'localhost', 'yaamp', 1459693738, 303, 1459693758);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25186, 'root', 'localhost', 'yaamp', 1459693738, 10, 1459693738);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25187, 'root', 'localhost', 'yaamp', 1459693738, 0, 1459693738);
INSERT INTO `connections` (`id`, `user`, `host`, `db`, `created`, `idle`, `last`) VALUES
	(25189, 'root', 'localhost', 'yaamp', 1459693758, 0, 1459693758);

CREATE TABLE IF NOT EXISTS `earnings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `coinid` int DEFAULT NULL,
  `blockid` int DEFAULT NULL,
  `create_time` int DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `status` int DEFAULT NULL,
  `mature_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ndx_user_block` (`userid`,`blockid`),
  KEY `user` (`userid`),
  KEY `coin` (`coinid`),
  KEY `block` (`blockid`),
  KEY `create1` (`create_time`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `exchange` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coinid` int DEFAULT NULL,
  `send_time` int DEFAULT NULL,
  `receive_time` int DEFAULT NULL,
  `price` double DEFAULT NULL,
  `price_estimate` double DEFAULT NULL,
  `quantity` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `status` varchar(16) DEFAULT NULL,
  `market` varchar(16) DEFAULT NULL,
  `tx` varchar(65) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coinid` (`coinid`),
  KEY `status` (`status`),
  KEY `market` (`market`),
  KEY `send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `hashrate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `time` int DEFAULT NULL,
  `hashrate` bigint DEFAULT NULL,
  `hashrate_bad` bigint DEFAULT NULL,
  `price` double DEFAULT NULL,
  `rent` double DEFAULT NULL,
  `earnings` double DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `algo` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `t1` (`time`),
  KEY `a1` (`algo`)
) ENGINE=InnoDB AUTO_INCREMENT=12607 DEFAULT CHARSET=utf8mb3;

INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12574, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'sha256');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12575, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'scrypt');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12576, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'scryptn');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12577, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'argon2');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12578, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'blake');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12579, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'blake2s');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12580, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'decred');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12581, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'keccak');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12582, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'luffa');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12583, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'lyra2');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12584, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'lyra2v2');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12585, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'neoscrypt');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12586, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'nist5');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12587, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'penta');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12588, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'quark');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12589, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'qubit');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12590, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'c11');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12591, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'x11');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12592, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'x13');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12593, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'x14');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12594, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'x15');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12595, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'groestl');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12596, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'dmd-gr');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12597, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'myr-gr');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12598, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'm7m');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12599, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'sib');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12600, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'skein');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12601, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'skein2');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12602, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'vanilla');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12603, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'velvet');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12604, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'yescrypt');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12605, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'whirlpool');
INSERT INTO `hashrate` (`id`, `time`, `hashrate`, `hashrate_bad`, `price`, `rent`, `earnings`, `difficulty`, `algo`) VALUES
	(12606, 1459692900, 0, NULL, 0, 0, NULL, NULL, 'zr5');

CREATE TABLE IF NOT EXISTS `hashrenter` (
  `id` int NOT NULL AUTO_INCREMENT,
  `renterid` int DEFAULT NULL,
  `jobid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `hashrate` double DEFAULT NULL,
  `hashrate_bad` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `hashstats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `time` int DEFAULT NULL,
  `hashrate` bigint DEFAULT NULL,
  `earnings` double DEFAULT NULL,
  `algo` varchar(16) DEFAULT 'scrypt',
  PRIMARY KEY (`id`),
  KEY `algo1` (`algo`),
  KEY `time1` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `hashuser` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `hashrate` bigint DEFAULT NULL,
  `hashrate_bad` bigint DEFAULT NULL,
  `algo` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `u1` (`userid`),
  KEY `t1` (`time`),
  KEY `a1` (`algo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `renterid` int DEFAULT NULL,
  `ready` tinyint(1) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `time` int DEFAULT NULL,
  `price` double DEFAULT NULL,
  `speed` double DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `algo` varchar(16) DEFAULT NULL,
  `host` varchar(1024) DEFAULT NULL,
  `port` int DEFAULT NULL,
  `username` varchar(1024) DEFAULT NULL,
  `password` varchar(1024) DEFAULT NULL,
  `percent` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `renterid` (`renterid`),
  KEY `ready` (`ready`),
  KEY `active` (`active`),
  KEY `algo` (`algo`),
  KEY `price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `jobsubmits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jobid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `valid` tinyint(1) DEFAULT NULL,
  `status` int DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `algo` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `markets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coinid` int DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT '0',
  `marketid` int DEFAULT NULL,
  `priority` tinyint(1) NOT NULL DEFAULT '0',
  `lastsent` int DEFAULT NULL,
  `lasttraded` int DEFAULT '0',
  `balancetime` int DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT NULL,
  `txfee` double DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `ontrade` double NOT NULL DEFAULT '0',
  `price` double DEFAULT NULL,
  `price2` double DEFAULT NULL,
  `pricetime` int DEFAULT NULL,
  `deposit_address` varchar(1024) DEFAULT NULL,
  `message` varchar(2048) DEFAULT NULL,
  `name` varchar(16) DEFAULT NULL,
  `base_coin` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coinid` (`coinid`),
  KEY `name` (`name`),
  KEY `lastsent` (`lastsent`),
  KEY `lasttraded` (`lasttraded`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

INSERT INTO `markets` (`id`, `coinid`, `disabled`, `marketid`, `priority`, `lastsent`, `lasttraded`, `balancetime`, `deleted`, `txfee`, `balance`, `ontrade`, `price`, `price2`, `pricetime`, `deposit_address`, `message`, `name`, `base_coin`) VALUES
	(1, 155, 0, NULL, 0, NULL, 0, NULL, 0, 0.002, NULL, 0, 0.020691404922548, 0.020814137099131, NULL, NULL, NULL, 'bittrex', NULL);

CREATE TABLE IF NOT EXISTS `market_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `time` int NOT NULL,
  `idcoin` int NOT NULL,
  `price` double DEFAULT NULL,
  `price2` double DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `idmarket` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idcoin` (`idcoin`),
  KEY `idmarket` (`idmarket`),
  KEY `time` (`time` DESC),
  CONSTRAINT `fk_mh_coin` FOREIGN KEY (`idcoin`) REFERENCES `coins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mh_market` FOREIGN KEY (`idmarket`) REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `mining` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usdbtc` double DEFAULT NULL,
  `last_monitor_exchange` int DEFAULT NULL,
  `last_update_price` int DEFAULT NULL,
  `last_payout` int DEFAULT NULL,
  `stratumids` varchar(1024) DEFAULT NULL,
  `best_algo` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

INSERT INTO `mining` (`id`, `usdbtc`, `last_monitor_exchange`, `last_update_price`, `last_payout`, `stratumids`, `best_algo`) VALUES
	(1, 418.82, 1422830048, 1422829644, 1459683363, '', 'lyra2');

CREATE TABLE IF NOT EXISTS `nicehash` (
  `id` int NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) DEFAULT NULL,
  `orderid` int DEFAULT NULL,
  `last_decrease` int DEFAULT NULL,
  `algo` varchar(32) DEFAULT NULL,
  `btc` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `speed` double DEFAULT NULL,
  `workers` int DEFAULT NULL,
  `accepted` double DEFAULT NULL,
  `rejected` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(1, 0, NULL, NULL, 'x11', NULL, NULL, NULL, 0, 0, 0);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(2, 0, NULL, NULL, 'scrypt', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(3, 0, NULL, NULL, 'sha256', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(4, 0, NULL, NULL, 'scryptn', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(5, 0, NULL, NULL, 'x13', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(6, 0, NULL, NULL, 'x15', NULL, NULL, NULL, 0, 0, 0);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(7, 0, NULL, NULL, 'nist5', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(8, 0, NULL, NULL, 'neoscrypt', NULL, NULL, NULL, 0, 0, 0);
INSERT INTO `nicehash` (`id`, `active`, `orderid`, `last_decrease`, `algo`, `btc`, `price`, `speed`, `workers`, `accepted`, `rejected`) VALUES
	(9, 0, NULL, NULL, 'lyra2', NULL, NULL, NULL, 0, 0, 0);

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idcoin` int NOT NULL,
  `enabled` int NOT NULL DEFAULT '0',
  `description` varchar(128) DEFAULT NULL,
  `conditiontype` varchar(32) DEFAULT NULL,
  `conditionvalue` double DEFAULT NULL,
  `notifytype` varchar(32) DEFAULT NULL,
  `notifycmd` varchar(512) DEFAULT NULL,
  `lastchecked` int unsigned DEFAULT NULL,
  `lasttriggered` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notif_coin` (`idcoin`),
  KEY `notif_checked` (`lastchecked`),
  CONSTRAINT `fk_notif_coin` FOREIGN KEY (`idcoin`) REFERENCES `coins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coinid` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `price` double DEFAULT NULL,
  `ask` double DEFAULT NULL,
  `bid` double DEFAULT NULL,
  `market` varchar(16) DEFAULT NULL,
  `uuid` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coinid` (`coinid`),
  KEY `created` (`created`),
  KEY `market` (`market`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `payouts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `idcoin` int DEFAULT NULL,
  `time` int NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `amount` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `tx` varchar(128) DEFAULT NULL,
  `memoid` varchar(128) DEFAULT NULL,
  `errmsg` text,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`,`completed`),
  KEY `payouts_coin` (`idcoin`),
  CONSTRAINT `fk_payouts_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payouts_coin` FOREIGN KEY (`idcoin`) REFERENCES `coins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `rawcoins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  `symbol` varchar(32) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

INSERT INTO `rawcoins` (`id`, `name`, `symbol`, `active`) VALUES
	(1, 'Bitcoin', 'BTC', 1);

CREATE TABLE IF NOT EXISTS `renters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  `address` varchar(1024) DEFAULT NULL,
  `email` varchar(1024) DEFAULT NULL,
  `password` varchar(64) DEFAULT NULL,
  `apikey` varbinary(1024) DEFAULT NULL,
  `received` double DEFAULT NULL,
  `balance` double DEFAULT NULL,
  `unconfirmed` double DEFAULT NULL,
  `spent` double DEFAULT NULL,
  `custom_start` double DEFAULT NULL,
  `custom_balance` double DEFAULT NULL,
  `custom_accept` double DEFAULT NULL,
  `custom_reject` double DEFAULT NULL,
  `custom_address` varchar(1024) DEFAULT NULL,
  `custom_server` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `rentertxs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `renterid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `address` varchar(1024) DEFAULT NULL,
  `tx` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `renterid` (`renterid`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `servers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(32) DEFAULT NULL,
  `maxcoins` int DEFAULT NULL,
  `uptime` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name1` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  `algo` varchar(64) DEFAULT NULL,
  `price` double DEFAULT NULL,
  `speed` bigint DEFAULT NULL,
  `custom_balance` double DEFAULT NULL,
  `custom_accept` double DEFAULT NULL,
  `custom_reject` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;

INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(1, 'Nicehash', 'scrypt', 0.0003646, 20628000000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(2, 'Nicehash', 'x11', 0.0004524, 15616000000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(3, 'Nicehash', 'x13', 0.0003273, 185100000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(4, 'Nicehash', 'x15', 0.0004079, 7200000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(5, 'Nicehash', 'nist5', 0.001, 21900000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(6, 'Nicehash', 'sha256', 0.0000098, 2310347791200000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(7, 'Nicehash', 'scryptn', 0.0005521, 1200000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(8, 'Nicehash', 'neoscrypt', 0.0073366, 13600000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(9, 'Nicehash', 'lyra2', 0.0006123, 181400000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(16, 'Nicehash', 'qubit', 0.0001968, 72200000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(17, 'Nicehash', 'quark', 0.0004536, 65978400000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(18, 'Nicehash', 'zr5', 0.0001, 61865000000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(19, 'Nicehash', 'c11', 0.0003403, 11823800000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(20, 'Nicehash', 'keccak', 0.0000027, 153200000, 0, 0, 0);
INSERT INTO `services` (`id`, `name`, `algo`, `price`, `speed`, `custom_balance`, `custom_accept`, `custom_reject`) VALUES
	(21, 'Nicehash', 'whirlx', 0.0000091, 1100700000, 0, 0, 0);

CREATE TABLE IF NOT EXISTS `settings` (
  `param` varchar(128) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `type` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`param`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `shares` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `workerid` int DEFAULT NULL,
  `coinid` int DEFAULT NULL,
  `jobid` int DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `error` int DEFAULT NULL,
  `valid` tinyint(1) DEFAULT NULL,
  `extranonce1` tinyint(1) DEFAULT NULL,
  `difficulty` double NOT NULL DEFAULT '0',
  `share_diff` double NOT NULL DEFAULT '0',
  `algo` varchar(16) DEFAULT 'x11',
  `solo` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `algo1` (`algo`),
  KEY `valid1` (`valid`),
  KEY `user1` (`userid`),
  KEY `worker1` (`workerid`),
  KEY `coin1` (`coinid`),
  KEY `jobid` (`jobid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `time` int DEFAULT NULL,
  `profit` double DEFAULT NULL,
  `wallet` double DEFAULT NULL,
  `wallets` double DEFAULT NULL,
  `immature` double DEFAULT NULL,
  `margin` double DEFAULT NULL,
  `waiting` double DEFAULT NULL,
  `balances` double DEFAULT NULL,
  `onsell` double DEFAULT NULL,
  `renters` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`)
) ENGINE=InnoDB AUTO_INCREMENT=383 DEFAULT CHARSET=utf8mb3;

INSERT INTO `stats` (`id`, `time`, `profit`, `wallet`, `wallets`, `immature`, `margin`, `waiting`, `balances`, `onsell`, `renters`) VALUES
	(382, 1459692900, 0, 0, NULL, NULL, 0, NULL, 0, NULL, NULL);

CREATE TABLE IF NOT EXISTS `stratums` (
  `pid` int NOT NULL,
  `time` int DEFAULT NULL,
  `started` int unsigned DEFAULT NULL,
  `algo` varchar(64) DEFAULT NULL,
  `workers` int unsigned NOT NULL DEFAULT '0',
  `port` int unsigned DEFAULT NULL,
  `symbol` varchar(16) DEFAULT NULL,
  `url` varchar(128) DEFAULT NULL,
  `fds` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `withdraws` (
  `id` int NOT NULL AUTO_INCREMENT,
  `market` varchar(1024) DEFAULT NULL,
  `address` varchar(1024) DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `time` int DEFAULT NULL,
  `uuid` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `workers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `subscribe` tinyint(1) DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `dns` varchar(1024) DEFAULT NULL,
  `name` varchar(52) DEFAULT NULL,
  `nonce1` varchar(64) DEFAULT NULL,
  `version` varchar(64) DEFAULT NULL,
  `password` varchar(64) DEFAULT NULL,
  `worker` varchar(64) DEFAULT NULL,
  `algo` varchar(16) DEFAULT 'scrypt',
  PRIMARY KEY (`id`),
  KEY `algo1` (`algo`),
  KEY `name1` (`name`),
  KEY `userid` (`userid`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



-- ============================================================================
-- SECTION 2: Migration Files (Applied in Chronological Order)
-- ============================================================================


-- ----------------------------------------------------------------------------
-- Migration: 2024-03-18-add_aurum_algo.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `profit`, `rent`, `factor`) VALUES ('aurum', 0, 0, 1);

-- ----------------------------------------------------------------------------
-- Migration: 2024-03-29-add_github_version.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `coins` ADD `version_github` VARCHAR(1024) NULL DEFAULT NULL AFTER `specifications`;
ALTER TABLE `coins` ADD `version_installed` VARCHAR(1024) NULL DEFAULT NULL AFTER `version_github`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-03-31-add_payout_threshold.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `accounts` ADD `payout_threshold` DOUBLE NULL DEFAULT NULL AFTER `hostaddr`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-01-add_auto_exchange.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `coins` ADD `auto_exchange` TINYINT(1) NOT NULL DEFAULT '0' AFTER `version_installed`;
ALTER TABLE `coins` ADD `enable_rpcdebug` TINYINT(1) NOT NULL DEFAULT '0' AFTER `auto_exchange`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-01-shares_blocknumber.sql
-- ----------------------------------------------------------------------------


ALTER TABLE `shares` ADD `blocknumber` INT(10) NULL DEFAULT NULL AFTER `solo`;
ALTER TABLE `shares` ADD `blockrewarded` INT(10) NULL DEFAULT NULL AFTER `blocknumber`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-05-algos_port_color.sql
-- ----------------------------------------------------------------------------


ALTER TABLE `algos` ADD `color` VARCHAR(32) NOT NULL DEFAULT '#ffffff' AFTER `overflow`;
ALTER TABLE `algos` ADD `speedfactor` DOUBLE NOT NULL DEFAULT '1' AFTER `color`;
ALTER TABLE `algos` ADD `port` INT(10) NOT NULL DEFAULT '3033' AFTER `speedfactor`;
ALTER TABLE `algos` ADD `visible` TINYINT(3) NOT NULL DEFAULT '0' AFTER `port`;
ALTER TABLE `algos` ADD `powlimit_bits` TINYINT(3) NULL DEFAULT NULL AFTER `visible`;

UPDATE `algos` SET `color` = '#aba0e0', `speedfactor` = 1, `port` = 8335, `visible` = 1 WHERE `name` = '0x10';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 1, `port` = 8633, `visible` = 1 WHERE `name` = 'a5a';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 3691, `visible` = 1 WHERE `name` = 'aergo';
UPDATE `algos` SET `color` = '#80a0d0', `speedfactor` = 1, `port` = 4443, `visible` = 1 WHERE `name` = 'allium';
UPDATE `algos` SET `color` = '#80a0d0', `speedfactor` = 1, `port` = 4230, `visible` = 1 WHERE `name` = 'anime';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 4234, `visible` = 1 WHERE `name` = 'argon2';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 4239, `visible` = 1 WHERE `name` = 'argon2d-dyn';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 4241, `visible` = 1 WHERE `name` = 'argon2d16000';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 4238, `visible` = 1 WHERE `name` = 'argon2d250';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 4240, `visible` = 1 WHERE `name` = 'argon2d4096';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 8640, `visible` = 1 WHERE `name` = 'astralhash';
UPDATE `algos` SET `color` = '#e2e81f', `speedfactor` = 1, `port` = 6434, `visible` = 1 WHERE `name` = 'aurum';
UPDATE `algos` SET `color` = '#e0b0b0', `speedfactor` = 1, `port` = 5100, `visible` = 1 WHERE `name` = 'balloon';
UPDATE `algos` SET `color` = '#e0b0b0', `speedfactor` = 1, `port` = 6433, `visible` = 1 WHERE `name` = 'bastion';
UPDATE `algos` SET `color` = '#ffd880', `speedfactor` = 1, `port` = 3643, `visible` = 1 WHERE `name` = 'bcd';
UPDATE `algos` SET `color` = '#f790c0', `speedfactor` = 1, `port` = 3556, `visible` = 1 WHERE `name` = 'bitcore';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 0.001, `port` = 5733, `visible` = 1 WHERE `name` = 'blake';
UPDATE `algos` SET `color` = '#f2c81f', `speedfactor` = 0.001, `port` = 5766, `visible` = 1 WHERE `name` = 'blake2s';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 0.001, `port` = 5743, `visible` = 1 WHERE `name` = 'blakecoin';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 1, `port` = 5787, `visible` = 1 WHERE `name` = 'bmw512';
UPDATE `algos` SET `color` = '#a0a0d0', `speedfactor` = 1, `port` = 3573, `visible` = 1 WHERE `name` = 'c11';
UPDATE `algos` SET `color` = '#a0a0d0', `speedfactor` = 1, `port` = 3574, `visible` = 1 WHERE `name` = 'cosa';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 4250, `visible` = 1 WHERE `name` = 'cpupower';
UPDATE `algos` SET `color` = '#d0a0a0', `speedfactor` = 1, `port` = 3343, `visible` = 1 WHERE `name` = 'curvehash';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 0.001, `port` = 3252, `visible` = 1 WHERE `name` = 'decred';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 1, `port` = 8833, `visible` = 1 WHERE `name` = 'dedal';
UPDATE `algos` SET `color` = '#e0ffff', `speedfactor` = 1, `port` = 3535, `visible` = 1 WHERE `name` = 'deep';
UPDATE `algos` SET `color` = '#a0c0f0', `speedfactor` = 1, `port` = 5333, `visible` = 1 WHERE `name` = 'dmd-gr';
UPDATE `algos` SET `color` = '#d0a0a0', `speedfactor` = 1, `port` = 3692, `visible` = 1 WHERE `name` = 'geek';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 8650, `visible` = 1 WHERE `name` = 'globalhash';
UPDATE `algos` SET `color` = '#80a0d0', `speedfactor` = 0.001, `port` = 7070, `visible` = 1 WHERE `name` = 'gr';
UPDATE `algos` SET `color` = '#d0a0a0', `speedfactor` = 1, `port` = 5333, `visible` = 1 WHERE `name` = 'groestl';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 1000, `port` = 5136, `visible` = 1 WHERE `name` = 'heavyhash';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 1, `port` = 5135, `visible` = 1 WHERE `name` = 'hex';
UPDATE `algos` SET `color` = '#ffa0a0', `speedfactor` = 1, `port` = 3747, `visible` = 1 WHERE `name` = 'hmq1725';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 1, `port` = 7777, `visible` = 1 WHERE `name` = 'honeycomb';
UPDATE `algos` SET `color` = '#aa70ff', `speedfactor` = 1, `port` = 7433, `visible` = 1 WHERE `name` = 'hsr';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 8660, `visible` = 1 WHERE `name` = 'jeonghash';
UPDATE `algos` SET `color` = '#a0d0c0', `speedfactor` = 1, `port` = 4633, `visible` = 1 WHERE `name` = 'jha';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 0.001, `port` = 5133, `visible` = 1 WHERE `name` = 'keccak';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 0.001, `port` = 5134, `visible` = 1 WHERE `name` = 'keccakc';
UPDATE `algos` SET `color` = '#809aef', `speedfactor` = 1, `port` = 5522, `visible` = 1 WHERE `name` = 'lbk3';
UPDATE `algos` SET `color` = '#b0d0e0', `speedfactor` = 0.001, `port` = 3334, `visible` = 1 WHERE `name` = 'lbry';
UPDATE `algos` SET `color` = '#a0c0c0', `speedfactor` = 1, `port` = 5933, `visible` = 1 WHERE `name` = 'luffa';
UPDATE `algos` SET `color` = '#80a0f0', `speedfactor` = 1, `port` = 4433, `visible` = 1 WHERE `name` = 'lyra2';
UPDATE `algos` SET `color` = '#80a0f0', `speedfactor` = 1, `port` = 4434, `visible` = 1 WHERE `name` = 'lyra2TDC';
UPDATE `algos` SET `color` = '#80c0f0', `speedfactor` = 1, `port` = 4533, `visible` = 1 WHERE `name` = 'lyra2v2';
UPDATE `algos` SET `color` = '#80c0f0', `speedfactor` = 1, `port` = 4550, `visible` = 1 WHERE `name` = 'lyra2v3';
UPDATE `algos` SET `color` = '#80c0f0', `speedfactor` = 1, `port` = 4563, `visible` = 1 WHERE `name` = 'lyra2vc0ban';
UPDATE `algos` SET `color` = '#80b0f0', `speedfactor` = 1, `port` = 4553, `visible` = 1 WHERE `name` = 'lyra2z';
UPDATE `algos` SET `color` = '#80b0f0', `speedfactor` = 1, `port` = 4555, `visible` = 1 WHERE `name` = 'lyra2z330';
UPDATE `algos` SET `color` = '#d0a0a0', `speedfactor` = 1, `port` = 6033, `visible` = 1 WHERE `name` = 'm7m';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7066, `visible` = 1 WHERE `name` = 'megabtx';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7067, `visible` = 1 WHERE `name` = 'megamec';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7020, `visible` = 1 WHERE `name` = 'memehash';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 0.001, `port` = 7079, `visible` = 1 WHERE `name` = 'mike';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7018, `visible` = 1 WHERE `name` = 'minotaur';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7019, `visible` = 1 WHERE `name` = 'minotaurx';
UPDATE `algos` SET `color` = '#a0c0f0', `speedfactor` = 1, `port` = 5433, `visible` = 1 WHERE `name` = 'myr-gr';
UPDATE `algos` SET `color` = '#a0d0f0', `speedfactor` = 1, `port` = 4233, `visible` = 1 WHERE `name` = 'neoscrypt';
UPDATE `algos` SET `color` = '#c0e0e0', `speedfactor` = 1, `port` = 3833, `visible` = 1 WHERE `name` = 'nist5';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 8670, `visible` = 1 WHERE `name` = 'padihash';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 8680, `visible` = 1 WHERE `name` = 'pawelhash';
UPDATE `algos` SET `color` = '#80c0c0', `speedfactor` = 1, `port` = 5833, `visible` = 1 WHERE `name` = 'penta';
UPDATE `algos` SET `color` = '#a0a0e0', `speedfactor` = 1, `port` = 8333, `visible` = 1 WHERE `name` = 'phi';
UPDATE `algos` SET `color` = '#a0a0e0', `speedfactor` = 1, `port` = 8332, `visible` = 1 WHERE `name` = 'phi2';
UPDATE `algos` SET `color` = '#aba0e0', `speedfactor` = 1, `port` = 8334, `visible` = 1 WHERE `name` = 'phi5';
UPDATE `algos` SET `color` = '#a0a0e0', `speedfactor` = 1, `port` = 9393, `visible` = 1 WHERE `name` = 'pipe';
UPDATE `algos` SET `color` = '#dedefe', `speedfactor` = 1, `port` = 8463, `visible` = 1 WHERE `name` = 'polytimos';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 0.001, `port` = 7445, `visible` = 1 WHERE `name` = 'power2b';
UPDATE `algos` SET `color` = '#c0c0c0', `speedfactor` = 1, `port` = 4033, `visible` = 1 WHERE `name` = 'quark';
UPDATE `algos` SET `color` = '#d0a0f0', `speedfactor` = 1, `port` = 4733, `visible` = 1 WHERE `name` = 'qubit';
UPDATE `algos` SET `color` = '#d0f0a0', `speedfactor` = 1, `port` = 7443, `visible` = 1 WHERE `name` = 'rainforest';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 5252, `visible` = 1 WHERE `name` = 'renesis';
UPDATE `algos` SET `color` = '#c0c0e0', `speedfactor` = 1, `port` = 3433, `visible` = 1 WHERE `name` = 'scrypt';
UPDATE `algos` SET `color` = '#d0d0d0', `speedfactor` = 1, `port` = 4333, `visible` = 1 WHERE `name` = 'scryptn';
UPDATE `algos` SET `color` = '#d0d0a0', `speedfactor` = 0.001, `port` = 3333, `visible` = 1 WHERE `name` = 'sha256';
UPDATE `algos` SET `color` = '#d0d0a0', `speedfactor` = 1, `port` = 3340, `visible` = 1 WHERE `name` = 'sha256csm';
UPDATE `algos` SET `color` = '#d0d0f0', `speedfactor` = 1, `port` = 3338, `visible` = 1 WHERE `name` = 'sha256dt';
UPDATE `algos` SET `color` = '#d0d0f0', `speedfactor` = 0.001, `port` = 3339, `visible` = 1 WHERE `name` = 'sha256t';
UPDATE `algos` SET `color` = '#d0d0f0', `speedfactor` = 1, `port` = 3335, `visible` = 1 WHERE `name` = 'sha3d';
UPDATE `algos` SET `color` = '#d0d0a0', `speedfactor` = 1000, `port` = 7086, `visible` = 1 WHERE `name` = 'sha512256d';
UPDATE `algos` SET `color` = '#a0a0c0', `speedfactor` = 1, `port` = 5033, `visible` = 1 WHERE `name` = 'sib';
UPDATE `algos` SET `color` = '#80a0a0', `speedfactor` = 1, `port` = 4933, `visible` = 1 WHERE `name` = 'skein';
UPDATE `algos` SET `color` = '#c8a060', `speedfactor` = 1, `port` = 5233, `visible` = 1 WHERE `name` = 'skein2';
UPDATE `algos` SET `color` = '#dedefe', `speedfactor` = 1, `port` = 8433, `visible` = 1 WHERE `name` = 'skunk';
UPDATE `algos` SET `color` = '#d0a0a0', `speedfactor` = 1, `port` = 7091, `visible` = 1 WHERE `name` = 'skydoge';
UPDATE `algos` SET `color` = '#c8a060', `speedfactor` = 1, `port` = 8733, `visible` = 1 WHERE `name` = 'sonoa';
UPDATE `algos` SET `color` = '#f0b0d0', `speedfactor` = 1, `port` = 3555, `visible` = 1 WHERE `name` = 'timetravel';
UPDATE `algos` SET `color` = '#c0d0d0', `speedfactor` = 1, `port` = 8533, `visible` = 1 WHERE `name` = 'tribus';
UPDATE `algos` SET `color` = '#f0f0f0', `speedfactor` = 1000, `port` = 5755, `visible` = 1 WHERE `name` = 'vanilla';
UPDATE `algos` SET `color` = '#ffffff', `speedfactor` = 1, `port` = 5034, `visible` = 1 WHERE `name` = 'veltor';
UPDATE `algos` SET `color` = '#aac0cc', `speedfactor` = 1, `port` = 6133, `visible` = 1 WHERE `name` = 'velvet';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 3233, `visible` = 1 WHERE `name` = 'vitalium';
UPDATE `algos` SET `color` = '#d0e0e0', `speedfactor` = 1, `port` = 4133, `visible` = 1 WHERE `name` = 'whirlpool';
UPDATE `algos` SET `color` = '#f0f0a0', `speedfactor` = 1, `port` = 3533, `visible` = 1 WHERE `name` = 'x11';
UPDATE `algos` SET `color` = '#c0f0c0', `speedfactor` = 1, `port` = 3553, `visible` = 1 WHERE `name` = 'x11evo';
UPDATE `algos` SET `color` = '#f0f0a0', `speedfactor` = 1, `port` = 3534, `visible` = 1 WHERE `name` = 'x11k';
UPDATE `algos` SET `color` = '#f0f0a0', `speedfactor` = 1, `port` = 3536, `visible` = 1 WHERE `name` = 'x11kvs';
UPDATE `algos` SET `color` = '#ffe090', `speedfactor` = 1, `port` = 3233, `visible` = 1 WHERE `name` = 'x12';
UPDATE `algos` SET `color` = '#ffd880', `speedfactor` = 1, `port` = 3633, `visible` = 1 WHERE `name` = 'x13';
UPDATE `algos` SET `color` = '#f0c080', `speedfactor` = 1, `port` = 3933, `visible` = 1 WHERE `name` = 'x14';
UPDATE `algos` SET `color` = '#f0b080', `speedfactor` = 1, `port` = 3733, `visible` = 1 WHERE `name` = 'x15';
UPDATE `algos` SET `color` = '#f0b080', `speedfactor` = 1, `port` = 3636, `visible` = 1 WHERE `name` = 'x16r';
UPDATE `algos` SET `color` = '#f0b080', `speedfactor` = 1, `port` = 7220, `visible` = 1 WHERE `name` = 'x16rt';
UPDATE `algos` SET `color` = '#f0b080', `speedfactor` = 1, `port` = 3637, `visible` = 1 WHERE `name` = 'x16rv2';
UPDATE `algos` SET `color` = '#f0b080', `speedfactor` = 1, `port` = 3663, `visible` = 1 WHERE `name` = 'x16s';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 3737, `visible` = 1 WHERE `name` = 'x17';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 3738, `visible` = 1 WHERE `name` = 'x18';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 4300, `visible` = 1 WHERE `name` = 'x20r';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 3323, `visible` = 1 WHERE `name` = 'x21s';
UPDATE `algos` SET `color` = '#f0f0a0', `speedfactor` = 1, `port` = 4200, `visible` = 1 WHERE `name` = 'x22i';
UPDATE `algos` SET `color` = '#f0f0a0', `speedfactor` = 1, `port` = 4210, `visible` = 1 WHERE `name` = 'x25x';
UPDATE `algos` SET `color` = '#f0b0a0', `speedfactor` = 1, `port` = 3739, `visible` = 1 WHERE `name` = 'xevan';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 6233, `visible` = 1 WHERE `name` = 'yescrypt';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 6333, `visible` = 1 WHERE `name` = 'yescryptR16';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 6343, `visible` = 1 WHERE `name` = 'yescryptR32';
UPDATE `algos` SET `color` = '#e0d0e0', `speedfactor` = 1, `port` = 6353, `visible` = 1 WHERE `name` = 'yescryptR8';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6234, `visible` = 1 WHERE `name` = 'yespower';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 0.001, `port` = 6245, `visible` = 1 WHERE `name` = 'yespowerARWN';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6235, `visible` = 1 WHERE `name` = 'yespowerIC';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6240, `visible` = 1 WHERE `name` = 'yespowerIOTS';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6242, `visible` = 1 WHERE `name` = 'yespowerLITB';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6241, `visible` = 1 WHERE `name` = 'yespowerLTNCG';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6244, `visible` = 1 WHERE `name` = 'yespowerMGPC';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6236, `visible` = 1 WHERE `name` = 'yespowerR16';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6237, `visible` = 1 WHERE `name` = 'yespowerRES';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6238, `visible` = 1 WHERE `name` = 'yespowerSUGAR';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6243, `visible` = 1 WHERE `name` = 'yespowerTIDE';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 6239, `visible` = 1 WHERE `name` = 'yespowerURX';
UPDATE `algos` SET `color` = '#d0b0d0', `speedfactor` = 1, `port` = 5533, `visible` = 1 WHERE `name` = 'zr5';
UPDATE `algos` SET `color` = '#e2d0d2', `speedfactor` = 1, `port` = 7092, `visible` = 1 WHERE `name` = 'xelisv2-pepew';

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-22-add_equihash_algos.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('equihash', 1, '#d0b0d0', 2142, 1);
INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('equihash125', 1, '#d0b0d0', 2150, 0.001);
INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('equihash144', 1, '#d0b0d0', 2146, 0.001);
INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('equihash192', 1, '#d0b0d0', 2144, 0.001);
INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('equihash96', 1, '#d0b0d0', 2148, 0.001);

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-23-add_pers_string.sql
-- ----------------------------------------------------------------------------

	
ALTER TABLE `coins` ADD `wallet_zaddress` VARCHAR(1024) NULL DEFAULT NULL AFTER `master_wallet`;

ALTER TABLE `coins` ADD `powlimit_bits` TINYINT(3) NULL DEFAULT NULL AFTER `enable_rpcdebug`;
ALTER TABLE `coins` ADD `personalization` VARCHAR(1024) NULL DEFAULT NULL AFTER `powlimit_bits`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-04-29-add_sellthreshold.sql
-- ----------------------------------------------------------------------------


ALTER TABLE `coins` ADD `sellthreshold` DOUBLE NULL DEFAULT '10000' AFTER `dontsell`;

-- ----------------------------------------------------------------------------
-- Migration: 2024-05-04-add_neoscrypt_xaya_algo.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `factor`, `color`, `port`, `visible`) VALUES ('neoscrypt-xaya', 1, '#a0d0f0', 4238, 1);

-- ----------------------------------------------------------------------------
-- Migration: 2025-02-06-add_usemweb.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `coins` ADD `usemweb` TINYINT(1) NOT NULL DEFAULT '0' AFTER `usesegwit`;


-- ----------------------------------------------------------------------------
-- Migration: 2025-02-13-add_xelisv2-pepew.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('xelisv2-pepew', '#e2d0d2', 1, 7092, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-02-23-add_algo_kawpow.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('kawpow', '#e2d0d2', 1, 3638, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-03-31-rename_table_exchange.sql
-- ----------------------------------------------------------------------------

RENAME TABLE `exchange` TO `exchange_deposit`;

-- ----------------------------------------------------------------------------
-- Migration: 2025-10-05-add_argon2d1000.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('argon2d1000', '#e2d0d2', 1, 4242, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-10-07-add_yespowerADVC.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('yespowerADVC', '#e2d0d2', 1, 6248, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-10-27-add_flex.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('flex', '#e2d0d2', 1, 3341, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-10-27-add_rinhash.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('rinhash', '#e2d0d2', 1, 7444, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-10-28-add_algo_phihash.sql
-- ----------------------------------------------------------------------------

INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('phihash', '#e2d0d2', 1, 1329, 1, NULL);

-- ----------------------------------------------------------------------------
-- Migration: 2025-11-28-add_performance_indexes.sql
-- ----------------------------------------------------------------------------

-- Workers table indexes
ALTER TABLE `workers` ADD INDEX `idx_time` (`time`);
ALTER TABLE `workers` ADD INDEX `idx_userid_time` (`userid`, `time`);
ALTER TABLE `workers` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- Payouts table indexes
ALTER TABLE `payouts` ADD INDEX `idx_time` (`time`);
ALTER TABLE `payouts` ADD INDEX `idx_completed_time` (`completed`, `time`);

-- Earnings table indexes
ALTER TABLE `earnings` ADD INDEX `idx_mature_time` (`mature_time`);
ALTER TABLE `earnings` ADD INDEX `idx_coinid_create_time` (`coinid`, `create_time`);

-- Accounts table indexes
ALTER TABLE `accounts` ADD INDEX `idx_is_locked` (`is_locked`);
ALTER TABLE `accounts` ADD INDEX `idx_coinid_balance` (`coinid`, `balance`);

-- Blocks table indexes
ALTER TABLE `blocks` ADD INDEX `idx_time_category` (`time`, `category`);
ALTER TABLE `blocks` ADD INDEX `idx_coin_id_time` (`coin_id`, `time`);
ALTER TABLE `blocks` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Shares table indexes
ALTER TABLE `shares` ADD INDEX `idx_time_valid` (`time`, `valid`);
ALTER TABLE `shares` ADD INDEX `idx_userid_time` (`userid`, `time`);
ALTER TABLE `shares` ADD INDEX `idx_coinid_time` (`coinid`, `time`);
ALTER TABLE `shares` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- Markets table indexes
ALTER TABLE `markets` ADD INDEX `idx_coinid_lasttraded` (`coinid`, `lasttraded`);

-- Balanceuser table indexes
ALTER TABLE `balanceuser` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Hashuser table indexes
ALTER TABLE `hashuser` ADD INDEX `idx_userid_time_algo` (`userid`, `time`, `algo`);

-- Hashrate table indexes
ALTER TABLE `hashrate` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- ----------------------------------------------------------------------------
-- Migration: 2025-11-29-add_rpcwallet.sql
-- ----------------------------------------------------------------------------

-- Add rpcwallet field for Bitcoin Core 0.17+ compatibility
-- Coins like Briskcoin 3.0 require wallet name in RPC path

ALTER TABLE `coins` ADD `rpcwallet` varchar(64) DEFAULT NULL AFTER `rpcport`;

-- ============================================================================
-- SECTION 3: Decimal Column Addition to Coins Table
-- Source: sql/old-yiimp.sql
-- ============================================================================

-- Add decimals column to coins table if it doesn't exist
-- This column stores the number of decimal places for each coin

ALTER TABLE `coins` ADD COLUMN   `decimals` tinyint(3) UNSIGNED NOT NULL DEFAULT 8 AFTER `symbol`;


-- ============================================================================
-- Schema Generation Complete
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
