<?php

// JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
// JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");
use Yii;
use app\components\CspHelper;

// Define API URL constant with backward compatibility
if (!defined('YIIMP_API_URL')) {
    if (defined('YAAMP_SITE_URL')) {
        define('YIIMP_API_URL', YAAMP_SITE_URL);
    } else {
        define('YIIMP_API_URL', $_SERVER['HTTP_HOST']);
    }
}

// Define API payouts constant with backward compatibility
if (!defined('YIIMP_API_PAYOUTS')) {
    define('YIIMP_API_PAYOUTS', false);
}

// Define API payouts period constant with backward compatibility (24 hours default)
if (!defined('YIIMP_API_PAYOUTS_PERIOD')) {
    define('YIIMP_API_PAYOUTS_PERIOD', 24 * 3600);
}

// Define rental constant with backward compatibility
if (!defined('YIIMP_RENTAL')) {
    if (defined('YAAMP_RENTAL')) {
        define('YIIMP_RENTAL', YAAMP_RENTAL);
    } else {
        define('YIIMP_RENTAL', false);
    }
}
?>
<br>

<div class="main-left-box">
<div class="main-left-title">YiiMP API</div>
<div class="main-left-inner">

<p>Simple REST API.</p>

<p><b>Wallet Status</b></p>

<p>Get basic wallet information including balance and earnings.</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/wallet?address=<b>WALLET_ADDRESS</b></p>

result:
<pre class="main-left-box api-code-block">
{
	"currency": "BTC",
	"unsold": 0.00050362,
	"balance": 0.00000000,
	"unpaid": 0.00050362,
	"paid24h": 0.00000000,
	"total": 0.00050362,
	"hashrate": 1234567890,
	"workers": 2
}
</pre>

<p><b>Extended Wallet Status</b></p>

<p>Get detailed wallet information including worker details and payout history.</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/walletEx?address=<b>WALLET_ADDRESS</b></p>

result:
<pre class="main-left-box api-code-block">
{
	"currency": "BTC",
	"unsold": 0.00050362,
	"balance": 0.00000000,
	"unpaid": 0.00050362,
	"paid24h": 0.00000000,
	"total": 0.00050362,
	"miners": [{
		"version": "ccminer\/1.8.2",
		"password": "d=96",
		"ID": "worker1",
		"algo": "sha256",
		"difficulty": 96,
		"subscribe": 1,
		"accepted": 82463372.083,
		"rejected": 0
	}]
<?php if (YIIMP_API_PAYOUTS) : ?>
	,"payouts": [{
		"time": 1529860641,
		"amount": "0.00100000",
		"tx": "transaction_id_of_the_payout"
	}]
<?php endif; ?>
}
</pre>
<?php
if (YIIMP_API_PAYOUTS) {
	$hours = round(YIIMP_API_PAYOUTS_PERIOD / 3600, 1);
	echo "<p><i>Note: Payouts from the last {$hours} hours are displayed. Please use a block explorer to see all historical payouts.</i></p>";
}
?>
<p><b>Pool Status</b></p>

<p>Get real-time statistics for all mining algorithms.</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/status</p>

result:
<pre class="main-left-box api-code-block">
{
	"sha256": {
		"name": "sha256",
		"port": 3333,
		"coins": 5,
		"fees": 0.5,
		"fees_solo": 1,
		"hashrate": 269473938000,
		"hashrate_shared": 269473938000,
		"workers": 15,
		"workers_shared": 12,
		"workers_solo": 3,
		"estimate_current": "0.00053653",
		"estimate_last24h": "0.00036408",
		"actual_last24h": "0.00035620",
		"mbtc_mh_factor": 1.0,
		"hashrate_last24h": 269473000000
	},

	...
}
</pre>

<p><b>Currency Information</b></p>

<p>Get per-coin statistics including blocks found, hashrate, and profitability.</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/currency</p>

result:
<pre class="main-left-box api-code-block">
{
	"BTC": {
		"name": "Bitcoin",
		"algo": "sha256",
		"port": 3333,
		"reward": 6.25,
		"blocktime": 600,
		"height": 750000,
		"difficulty": 25046487590083.27,
		"autotrade": false,
		"fees": 0.5,
		"fees_solo": 1,
		"miners": 15,
		"workers": 20,
		"hashrate": 7267227499000,
		"network_hashrate": 250000000000000,
		"estimate": "0.00000123",
		"24h_blocks": 12,
		"24h_btc": 0.54471295,
		"lastblock": 750001,
		"timesincelast": 450
	},

	...
}
</pre>

<p><b>Recent Blocks</b></p>

<p>Get information about recently found blocks (limit: 1-100, default: 50).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/blocks?limit=10</p>

result:
<pre class="main-left-box api-code-block">
[
	{
		"height": 750001,
		"blockhash": "00000000000000000007e9b6d3f4c5a2b1e8d9c7f6a5b4c3d2e1f0a9b8c7d6e5",
		"coin": "BTC",
		"algo": "sha256",
		"amount": 6.25000000,
		"difficulty": 25046487590083.27,
		"time": 1609459200,
		"timestamp": "2021-01-01 00:00:00",
		"confirmations": 100,
		"txhash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
		"reward": "6.25000000"
	},

	...
]
</pre>

<?php if (YIIMP_RENTAL) : ?>

<p><b>Rental Status</b></p>

<p>Get rental account balance and active jobs (requires API key).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/rental?key=<b>API_KEY</b></p>

result:
<pre class="main-left-box api-code-block">
{
	"balance": 0.00000000,
	"unconfirmed": 0.00000000,
	"jobs": [
		{
			"jobid": "19",
			"algo": "sha256",
			"price": "1.00000000",
			"hashrate": "1000000000",
			"server": "stratum.<?=YIIMP_API_URL?>",
			"port": "3333",
			"username": "1A5pAdfWLUFXoqcUb6N9Fre2EApr5QLNdG",
			"password": "c=BTC",
			"started": "1",
			"active": "1",
			"accepted": "586406.2014805333",
			"rejected": "0",
			"diff": "0.04"
		}
	]
}
</pre>

<p><b>Rental Price</b></p>

<p>Set the rental price of a job (requires API key).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/rental_price?key=<b>API_KEY</b>&amp;jobid=<b>JOB_ID</b>&amp;price=<b>PRICE</b></p>

<p><b>Rental Hashrate</b></p>

<p>Set the rental max hashrate of a job (requires API key).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/rental_hashrate?key=<b>API_KEY</b>&amp;jobid=<b>JOB_ID</b>&amp;hashrate=<b>HASHRATE</b></p>

<p><b>Start Rental Job</b></p>

<p>Start a rental job (requires API key).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/rental_start?key=<b>API_KEY</b>&amp;jobid=<b>JOB_ID</b></p>

<p><b>Stop Rental Job</b></p>

<p>Stop a rental job (requires API key).</p>

request:
<p class="main-left-box api-code-block">
	http://<?=YIIMP_API_URL?>/api/rental_stop?key=<b>API_KEY</b>&amp;jobid=<b>JOB_ID</b></p>

<?php endif; /* RENTAL */ ?>

<br><br>

</div></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<?= CspHelper::beginScript() ?>


<?= CspHelper::endScript() ?>


