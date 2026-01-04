<?php

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use app\components\CspHelper;
use app\assets\ChartHelperAsset;

/** @var yii\web\View $this */

// Register Chart.js assets for CSP-compliant charting
ChartHelperAsset::register($this);

$algo = Yii::$app->session->get('yaamp-algo');
$algo_unit = 'Mh';
$algo_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
if ($algo_factor == 0.001) $algo_unit = 'Kh';
if ($algo_factor == 1000) $algo_unit = 'Gh';
if ($algo_factor == 1000000) $algo_unit = 'Th';
if ($algo_factor == 1000000000) $algo_unit = 'Ph';

$hour = 60 * 60;
$days = 24 * $hour;

$dbMax = Yii::$app->cache->get("stats_maxt-$algo");
if (!$dbMax) {
	$dbMax = (new \yii\db\Query())
			->select(['(MAX(time)-30*60)'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',(time()-2*$hour)])
			->scalar();
	Yii::$app->cache->set("stats_maxt-$algo", $dbMax);
}

$dtMax = max(time()-$hour, $dbMax);

$t1 = $dtMax - 2*$days;
$t2 = $dtMax - 7*$days;
$t3 = $dtMax - 30*$days;

$row1 = Yii::$app->cache->get("stats_col1-$algo");
if (!$row1) {
	$row1 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t1])
			->one();
	Yii::$app->cache->set("stats_col1-$algo", $row1);
}
$row2 = Yii::$app->cache->get("stats_col2-$algo");
if (!$row2) {
	$row2 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t2])
			->one();
	Yii::$app->cache->set("stats_col2-$algo", $row2);
}
$row3 = Yii::$app->cache->get("stats_col3-$algo");
if (!$row3) {
	$row3 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t3])
			->one();
	Yii::$app->cache->set("stats_col3-$algo", $row3);
}

if($row1['a']>0 && $row2['a']>0 && $row3['a']>0)
{
	$a1 = max(1., (double) $row1['a']);
	$a2 = max(1., (double) $row2['a']);
	$a3 = max(1., (double) $row3['a']);

	$btcmhday1 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row1['b'] / 2)  * $algo_factor * (1000000 / $a1));
	$btcmhday2 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row2['b'] / 7)  * $algo_factor * (1000000 / $a2));
	$btcmhday3 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row3['b'] / 30) * $algo_factor * (1000000 / $a3));
}
else
{
	$btcmhday1 = 0;
	$btcmhday2 = 0;
	$btcmhday3 = 0;
}

$hashrate1 = Yii::$app->ConversionUtils->Itoa2($row1['a']);
$hashrate2 = Yii::$app->ConversionUtils->Itoa2($row2['a']);
$hashrate3 = Yii::$app->ConversionUtils->Itoa2($row3['a']);

$total1 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row1['b']);
$total2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row2['b']);
$total3 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row3['b']);

$height = '240px';

//$algos = yaamp_get_algos();
$algos = array();
$enabled = (new \yii\db\Query())
			->select(['algo','count(id) as count'])
			->from('coins')
			->where(['enable' => 1,'visible' => 1])
			->groupBy('algo')
			->orderBy('algo')
			->all();
foreach ($enabled as $row) {
	$algos[$row['algo']] = $row['count'];
}

$string = '';
foreach($algos as $a => $count)
{
	if($a == $algo)
		$string .= "<option value='$a' selected>$a</option>";
	else
		$string .= "<option value='$a'>$a</option>";
}

// to fill the graphs on right edges (big tick interval of 4 days)
$dtMin1 = $t1 + $hour;
$dtMax1 = $dtMax;

$dtMin2 = $t2 - 2*$hour;
$dtMax2 = $dtMin2 + 7 * $days;

$dtMin3 = $dtMax1 - (8*4+1)*$days;
$dtMax3 = $dtMin3 + (8*4) * $days;

?>

<div id="resume_update_button" class="resume-update-button d-none" align="center">
	<b>Auto refresh is paused - Click to resume</b></div>

<div align="right">
Select Algo: <select id="algo_select"><?= $string ?></select>&nbsp;
</div>

<?= CspHelper::beginScript() ?>

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
	var resumeBtn = document.getElementById('resume_update_button');
	if (resumeBtn) {
		resumeBtn.addEventListener('click', function() {
			auto_page_resume();
		});
	}
	
	var algoSelect = document.getElementById('algo_select');
	if (algoSelect) {
		algoSelect.addEventListener('change', function(event) {
			var algo = this.value;
			window.location.href = '/site/algo?algo='+algo+'&r=/stats';
		});
	}
});

<?= CspHelper::endScript() ?>

<table width=100%><tr><td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 48 Hours</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate1}h/s</b></li>
<li>BTC Value: <b>$total1</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday1</b></li>
</ul>

<br>
<div id='graph_results_1' class='chart-container-240'></div><br><br>
<div id='graph_results_2' class='chart-container-240'></div><br><br>
<div id='graph_results_3' class='chart-container-240'></div><br><br>

</div></div><br>

</td>
<td></td>
<td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 7 Days</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate2}h/s</b></li>
<li>BTC Value: <b>$total2</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday2</b></li>
</ul>

<br>
<div id='graph_results_4' class='chart-container-240'></div><br><br>
<div id='graph_results_5' class='chart-container-240'></div><br><br>
<div id='graph_results_6' class='chart-container-240'></div><br><br>

</div></div><br>

</td>
<td></td>
<td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 30 Days</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate3}h/s</b></li>
<li>BTC Value: <b>$total3</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday3</b></li>
</ul>

<br>
<div id='graph_results_7' class='chart-container-240'></div><br><br>
<div id='graph_results_8' class='chart-container-240'></div><br><br>
<div id='graph_results_9' class='chart-container-240'></div><br><br>

</div></div><br>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<?= CspHelper::beginScript() ?>

// Time range boundaries for charts
var dtMin1 = <?= $dtMin1 ?> * 1000;
var dtMax1 = <?= $dtMax1 ?> * 1000;

var dtMin2 = <?= $dtMin2 ?> * 1000;
var dtMax2 = <?= $dtMax2 ?> * 1000;

var dtMin3 = <?= $dtMin3 ?> * 1000;
var dtMax3 = <?= $dtMax3 ?> * 1000;

function page_refresh()
{
	main_refresh_1();
	main_refresh_2();
	main_refresh_3();
	main_refresh_4();
	main_refresh_5();
	main_refresh_6();
	main_refresh_7();
	main_refresh_8();
	main_refresh_9();
}

// Chart refresh functions
function main_ready_1(data) { graph_init_1(data); }
function main_refresh_1() { $.get("/stats/graph_results_1", '', main_ready_1); }

function main_ready_2(data) { graph_init_2(data); }
function main_refresh_2() { $.get("/stats/graph_results_2", '', main_ready_2); }

function main_ready_3(data) { graph_init_3(data); }
function main_refresh_3() { $.get("/stats/graph_results_3", '', main_ready_3); }

function main_ready_4(data) { graph_init_4(data); }
function main_refresh_4() { $.get("/stats/graph_results_4", '', main_ready_4); }

function main_ready_5(data) { graph_init_5(data); }
function main_refresh_5() { $.get("/stats/graph_results_5", '', main_ready_5); }

function main_ready_6(data) { graph_init_6(data); }
function main_refresh_6() { $.get("/stats/graph_results_6", '', main_ready_6); }

function main_ready_7(data) { graph_init_7(data); }
function main_refresh_7() { $.get("/stats/graph_results_7", '', main_ready_7); }

function main_ready_8(data) { graph_init_8(data); }
function main_refresh_8() { $.get("/stats/graph_results_8", '', main_ready_8); }

function main_ready_9(data) { graph_init_9(data); }
function main_refresh_9() { $.get("/stats/graph_results_9", '', main_ready_9); }

// Chart initialization functions using Chart.js
// Last 48 Hours - Hashrate (Line Chart)
function graph_init_1(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_1', t, {
		title: 'Hashrate (<?= $algo_unit ?>/s)',
		xAxisMin: dtMin1,
		xAxisMax: dtMax1,
		xAxisFormat: 'HH:mm',
		yAxisMin: 0,
		fill: true,
		backgroundColor: 'rgba(78, 180, 180, 0.3)',
		borderColor: 'rgba(78, 180, 180, 0.8)'
	});
}

// Last 48 Hours - BTC/Day (Bar Chart)
function graph_init_2(data)
{
	var t = JSON.parse(data);
	ChartHelper.createBarChart('graph_results_2', t, {
		title: 'BTC/Day',
		xAxisMin: dtMin1,
		xAxisMax: dtMax1,
		xAxisFormat: 'HH:mm',
		yAxisMin: 0
	});
}

// Last 48 Hours - BTC/unit/d (Bar Chart)
function graph_init_3(data)
{
	var t = JSON.parse(data);
	ChartHelper.createBarChart('graph_results_3', t, {
		title: 'BTC/<?= $algo_unit ?>/d',
		xAxisMin: dtMin1,
		xAxisMax: dtMax1,
		xAxisFormat: 'HH:mm',
		yAxisMin: 0
	});
}

// Last 7 Days - Hashrate (Line Chart)
function graph_init_4(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_4', t, {
		title: 'Hashrate (<?= $algo_unit ?>/s)',
		xAxisMin: dtMin2,
		xAxisMax: dtMax2,
		xAxisFormat: 'MMM d',
		yAxisMin: 0,
		fill: true,
		backgroundColor: 'rgba(78, 180, 180, 0.3)',
		borderColor: 'rgba(78, 180, 180, 0.8)'
	});
}

// Last 7 Days - BTC/Day (Bar Chart)
function graph_init_5(data)
{
	var t = JSON.parse(data);
	ChartHelper.createBarChart('graph_results_5', t, {
		title: 'BTC/Day',
		xAxisMin: dtMin2,
		xAxisMax: dtMax2,
		xAxisFormat: 'MMM d',
		yAxisMin: 0
	});
}

// Last 7 Days - BTC/unit/d (Bar Chart)
function graph_init_6(data)
{
	var t = JSON.parse(data);
	ChartHelper.createBarChart('graph_results_6', t, {
		title: 'BTC/<?= $algo_unit ?>/d',
		xAxisMin: dtMin2,
		xAxisMax: dtMax2,
		xAxisFormat: 'MMM d',
		yAxisMin: 0
	});
}

// Last 30 Days - Hashrate (Line Chart)
function graph_init_7(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_7', t, {
		title: 'Hashrate (<?= $algo_unit ?>/s)',
		xAxisMin: dtMin3,
		xAxisMax: dtMax3,
		xAxisFormat: 'MM/dd',
		yAxisMin: 0,
		fill: true,
		backgroundColor: 'rgba(78, 180, 180, 0.3)',
		borderColor: 'rgba(78, 180, 180, 0.8)'
	});
}

// Last 30 Days - BTC/Day (Line Chart - smooth for 30 day view)
function graph_init_8(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_8', t, {
		title: 'BTC/Day',
		xAxisMin: dtMin3,
		xAxisMax: dtMax3,
		xAxisFormat: 'MM/dd',
		yAxisMin: 0
	});
}

// Last 30 Days - BTC/unit/d (Line Chart - smooth for 30 day view)
function graph_init_9(data)
{
	var t = JSON.parse(data);
	ChartHelper.createLineChart('graph_results_9', t, {
		title: 'BTC/<?= $algo_unit ?>/d',
		xAxisMin: dtMin3,
		xAxisMax: dtMax3,
		xAxisFormat: 'MM/dd',
		yAxisMin: 0
	});
}

<?= CspHelper::endScript() ?>


