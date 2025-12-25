<?php

/** @var yii\web\View $this */
/** @var string $algo */
/** @var string $homeUrl */

use app\assets\ChartHelperAsset;

// Register Chart.js assets for CSP-compliant charting
ChartHelperAsset::register($this);

$height = '240px';

?>

<div id='resume_update_button' class='resume-update-button'>
	<b>Auto refresh is paused - Click to resume</b>
</div>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<div id='mining_results'>
</div>

<?php
if($algo != 'all')
echo <<<end
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Estimate ($algo)</div>
<div class="main-left-inner"><br>
<div id='graph_results_price' class='chart-container-240'></div><br>
</div></div><br>

<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Hashrate ($algo)</div>
<div class="main-left-inner"><br>
<div id='pool_hashrate_results' class='chart-container-240'></div><br>
</div></div><br>
end;

$algo_unit = 'Mh';
$algo_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
if ($algo_factor == 0.001) $algo_unit = 'Kh';
if ($algo_factor == 1000) $algo_unit = 'Gh';
if ($algo_factor == 1000000) $algo_unit = 'Th';
if ($algo_factor == 1000000000) $algo_unit = 'Ph';

// $homeUrl is passed from controller
echo <<<end
</td><td valign=top>

<div id='pool_current_results'>
</div>

<div id='found_results'>
</div>

</td></tr></table>

end;
?>

<?php
// Register ALL JavaScript functions before auto_refresh.js loads
$js = <<<JS
var global_algo = '$algo';
var querystring = '?algo=$algo';
if (querystring=='?algo=') querystring = '';

function select_algo(algo)
{
	window.location.href = '{$homeUrl}site/algo?algo='+algo+'&r={$homeUrl}site/mining';
}

function page_refresh()
{
	pool_current_refresh();
	mining_refresh();
	found_refresh();

	if(global_algo != 'all')
	{
		pool_hashrate_refresh();
		main_refresh_price();
	}
}

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
}

function pool_current_refresh()
{
	var url = "{$homeUrl}site/current_results"+querystring;
	$.get(url, '', pool_current_ready);
}

function mining_ready(data)
{
	$('#mining_results').html(data);
}

function mining_refresh()
{
	var url = "{$homeUrl}site/mining_results"+querystring;
	$.get(url, '', mining_ready);
}

function found_ready(data)
{
	$('#found_results').html(data);
}

function found_refresh()
{
	var url = "{$homeUrl}site/found_results"+querystring;
	$.get(url, '', found_ready);
}

function main_ready_price(data)
{
	graph_init_price(data);
}

function main_refresh_price()
{
	var url = "{$homeUrl}site/graph_price_results"+querystring;
	$.get(url, '', main_ready_price);
}

function graph_init_price(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_price', t, {
		title: 'Estimate (mBTC/{$algo_unit}/day)',
		xAxisFormat: 'HH:mm',
		yAxisMin: 0
	});
}

function pool_hashrate_ready(data)
{
	pool_hashrate_graph_init(data);
}

function pool_hashrate_refresh()
{
	var url = "{$homeUrl}site/graph_hashrate_results"+querystring;
	$.get(url, '', pool_hashrate_ready);
}

function pool_hashrate_graph_init(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('pool_hashrate_results', t, {
		title: 'Pool Hashrate ($algo_unit/s)',
		xAxisFormat: 'HH:mm',
		yAxisMin: 0
	});
}

// Event listener for resume button
document.addEventListener('DOMContentLoaded', function() {
	var resumeBtn = document.getElementById('resume_update_button');
	if (resumeBtn) {
		resumeBtn.addEventListener('click', function() {
			auto_page_resume();
		});
	}
});
JS;

$this->registerJs($js, \yii\web\View::POS_HEAD);

// Now register auto_refresh.js which depends on all the above functions
$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);
?>