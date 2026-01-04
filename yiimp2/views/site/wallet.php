<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Accounts;
use app\components\CspHelper;
use app\assets\ChartHelperAsset;

// Register Chart.js assets for CSP-compliant charting
ChartHelperAsset::register($this);

$homeUrl = Yii::$app->homeUrl;

// Register ALL JavaScript functions before auto_refresh.js loads
$js = <<<JS
var last_graph_update = 0;

function page_refresh()
{
	pool_current_refresh();
	found_refresh();

	if('$username' != '')
	{
		main_wallet_refresh();
		main_miners_refresh();

		main_graphs_refresh();
		main_title_refresh();
		
		main_found_refresh();
	}
}

function select_algo(algo)
{
	window.location.href = '{$homeUrl}site/algo?algo='+algo+'&r=/site/mining';
}

function main_wallet_ready(data)
{
	$('#main_wallet_results').html(data);
}

function main_wallet_refresh()
{
	var url = "{$homeUrl}site/wallet_results?address=$username";
	$.get(url, '', main_wallet_ready);
}

function main_found_ready(data)
{
	$('#main_found_results').html(data);
}

function main_found_refresh()
{
	var url = "{$homeUrl}site/wallet_found_results?address=$username";
	$.get(url, '', main_found_ready);
}

function main_wallet_refresh_details()
{
	var url = "{$homeUrl}site/wallet_results?address=$username&showdetails=1";
	$.get(url, '', main_wallet_ready);
}

function main_miners_ready(data)
{
	$('#main_miners_results').html(data);
}

function main_miners_refresh()
{
	var url = "{$homeUrl}site/wallet_miners_results?address=$username";
	$.get(url, '', main_miners_ready);
}

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
}

function pool_current_refresh()
{
	var url = "{$homeUrl}site/current_results";
	$.get(url, '', pool_current_ready);
}

function main_title_ready(data)
{
	document.title = data;
}

function main_title_refresh()
{
	var url = "{$homeUrl}site/title_results?address=$username";
	$.get(url, '', main_title_ready);
}

function found_ready(data)
{
	$('#found_results').html(data);
}

function found_refresh()
{
	var url = "{$homeUrl}site/user_earning_results?address=$username";
	$.get(url, '', found_ready);
}

function main_graphs_ready(data)
{
	$('#main_graphs_results').html(data);
	$('.graph_algo').each(function()
	{
		var algo = $(this).attr('id');
		main_refresh_hashrate(algo);
	});
}

function main_graphs_refresh()
{
	var now = Date.now()/1000;

	if(now < last_graph_update + 900) return;
	last_graph_update = now;

	var url = "{$homeUrl}site/wallet_graphs_results?address=$username";
	$.get(url, '', main_graphs_ready);

	graph_earnings_refresh();
}

function main_refresh_hashrate(algo)
{
	var url = "{$homeUrl}site/graph_user_results?address=$username&algo="+algo;
	$.get(url, '', function(data)
	{
		graph_init_hashrate(data, algo);
	});
}

function graph_init_hashrate(data, algo)
{
	var t = JSON.parse(data);
	var chartData = t[0];
	var chartTitle = t[1];
	
	ChartHelper.createLineChart('graph_results_'+algo, chartData, {
		title: chartTitle,
		xAxisFormat: 'HH:mm',
		yAxisMin: 0
	});
}

function graph_earnings_ready(data)
{
	graph_earnings_init(data);
}

function graph_earnings_refresh()
{
	var url = "{$homeUrl}site/graph_earnings_results?address=$username";
	$.get(url, '', graph_earnings_ready);
}

function graph_earnings_init(data)
{
	var t = JSON.parse(data);
	
	ChartHelper.createStackedAreaChart('graph_earnings_results', t, {
		xAxisFormat: 'HH:mm',
		yAxisMin: 0,
		labels: ['Balance', 'Pending']
	});
}

function main_wallet_tx()
{
	var w = window.open("{$homeUrl}site/tx?address=$username", "yaamp_tx",
		"width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no");
}

function drop_cookie(el)
{
	var addr = $(el).closest('tr').find('td a.address').text();
	window.location.href = '?address={$address}&drop=' + addr;
}
JS;

$this->registerJs($js, \yii\web\View::POS_HEAD);

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);
$this->registerJsFile('@web/js/bookmarks.js', ['depends' => [yii\web\JqueryAsset::className()]]);
$this->registerJsFile('@web/js/bookmark-autoload.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$recents = array();
$raw_recents = isset($_COOKIE['wallets'])? explode("|", $_COOKIE['wallets']): array();
// make it unique
foreach($raw_recents as $addr) {
	$recents[$addr] = $addr;
}

$address = Yii::$app->getRequest()->getQueryParam('address');
if (!empty($address) && preg_match('/[^A-Za-z0-9]/', $address)) {
	// Just to make happy XSS seekers who can hack their own browser html...
	die;
}

$drop_address = Yii::$app->getRequest()->getQueryParam('drop');
if (!empty($drop_address)) {
	// to clean cookies
	foreach($recents as $k=>$addr) {
		if ($addr == $drop_address) {
			unset($recents[$k]);
			if (!is_null(Yii::$app->user->identity))
				setcookie('wallets', implode("|", $recents), time()+60*60*24*30, '/');
			break;
		}
	}
}

$user = null;

$address = trim(substr($address, 0, 52));
if (!empty($address)) {
    $user = Accounts::find()->where(['username' => $address])->one();
}

if(!is_null($user))
{
    Yii::$app->session->set('yaamp-wallet', $user->username);
	$recents[$user->username] = $user->username;

    $coin = Coins::find()->where(['id' => $user->coinid])->one();
	if($coin) 
		$this->registerJs(
			"$(function() {
		$('#favicon').remove();
		$('head').append('<link href=\"{$coin->image}\" id=\"favicon\" rel=\"shortcut icon\">');
	});",
\yii\web\View::POS_READY,
	'favicon-handler'
		);

	if(empty($user->hostaddr) && is_null(Yii::$app->user->identity)) {
		$user->hostaddr = $_SERVER['REMOTE_ADDR'];
		$user->save();
	}
}

$username = $user? $user->username: '';

if(!is_null(Yii::$app->user->identity))
	setcookie('wallets', implode("|", $recents), time()+60*60*24*30, '/');

echo <<<END
<div id='resume_update_button' class='resume-update-button'>
	<b>Auto refresh is paused - Click to resume</b>
</div>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>
END;

if($user) {
    $isBookmarked = false; // Will be checked by JavaScript
    echo <<<END
<div id="bookmark-messages"></div>
<div class="main-left-box">
<div class="main-left-title">
    Wallet: $user->username
    <button class="btn btn-sm btn-outline-primary float-end bookmark-toggle-btn bookmark-add-btn" 
            data-address="$user->username" 
            data-label="$user->username"
            class="text-small">
        <i class="fa fa-star-o"></i> Add Bookmark
    </button>
</div>
</div>
<div id='main_wallet_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;
}

if($user) echo <<<END
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Balance: $user->username</div>
<div class="main-left-inner"><br>
<div id='graph_earnings_results' class='chart-container-240'></div>
<div class='float-end'>
<span class='text-small coin-chart-1'>Balance</span>
<span class='text-small coin-chart-2'>Pending</span>
</div>
<br>
</div></div><br>
END;

if($user) echo <<<END
<div id='main_graphs_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div id='main_miners_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div id='main_found_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

echo <<<END
<div class="main-left-box">
<div class="main-left-title">Search Wallet:</div>
<div class="main-left-inner">
<form action="{$homeUrl}" method="get" class="p-10">
<input type="text" name="address" class="main-text-input" placeholder="Wallet Address">
<input type="submit" value="Submit" class="main-submit-button" ><br><br>
END;

echo "<table class='dataGrid2'>";
foreach($recents as $addr)
{
	if(empty($addr)) continue;

    $user = Accounts::find()->where(['username' => $addr])->one();
	if(!$user) continue;

    $coin = Coins::find()->where(['id' => $user->coinid])->one();

	if($user->username == $username)
		echo "<tr class='row-selected'><td width=24>";
	else
		echo "<tr class='ssrow'><td width=24>";

	if($coin)
		echo '<img width="16px" src="'.$coin->image.'">';

	echo '</td><td><a class="address" href="'.$homeUrl.'?address='.$addr.'" class="font-mono-large">'.
		$addr.'</a></td>';

	$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance); 

	if($coin)
		$balance = $balance>0? "$balance $coin->symbol": '';
	else
		$balance = $balance>0? "$balance BTC": '';

	echo '<td align="right">'.$balance.'</td>';
	
	$delicon = $address == $addr ? '' : '<img src="/images/base/delete.png" class="drop-cookie-btn" class="cursor-pointer"/>';
	echo '<td class="w-16">'.$delicon.'</td>';
	
	echo '</tr>';
}

echo "</table></form></div></div><br>";

echo "</td><td valign=top>";

echo <<<END
<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div id='found_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

echo <<<END

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

END;

// Register event listeners via Yii's JS registration (CSP-compliant with nonce)
$walletEventJs = <<<JS
// Event listeners
document.addEventListener('DOMContentLoaded', function() {
	var resumeBtn = document.getElementById('resume_update_button');
	if (resumeBtn) {
		resumeBtn.addEventListener('click', function() {
			auto_page_resume();
		});
	}
	
	// Add event listeners to all drop cookie buttons
	document.addEventListener('click', function(e) {
		if (e.target && e.target.classList.contains('drop-cookie-btn')) {
			drop_cookie(e.target);
		}
	});
});

// Initialize bookmark button state
$(document).ready(function() {
	var address = '$username';
	if (address && typeof BookmarkManager !== 'undefined') {
		var isBookmarked = BookmarkManager.isBookmarked(address);
		var btn = $('.bookmark-toggle-btn[data-address="' + address + '"]');
		
		if (isBookmarked) {
			btn.removeClass('bookmark-add-btn').addClass('bookmark-remove-btn');
			btn.html('<i class="fa fa-star"></i> Remove Bookmark');
		} else {
			btn.removeClass('bookmark-remove-btn').addClass('bookmark-add-btn');
			btn.html('<i class="fa fa-star-o"></i> Add Bookmark');
		}
	}
});
JS;

$this->registerJs($walletEventJs, \yii\web\View::POS_END);
