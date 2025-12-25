<?php

/** @var yii\web\View $this */

use app\components\rpc\WalletRPC;
use app\models\Coins;
use app\components\CspHelper;

$id = (int) Yii::$app->getRequest()->getQueryParam('id');
$coin = Coins::findOne($id);

if (!$coin) {
	return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));
}

$this->title = 'Wallet - '.$coin->symbol;

// force a refresh after 10mn to prevent memory leaks in chrome
$this->registerMetaTag(['http-equiv' => 'refresh', 'content' => '600']);

if (!empty($coin->algo) && $coin->algo != 'PoS')
	Yii::$app->session->set('yaamp-algo', $coin->algo);

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

$sellamount = $coin->balance;
//if ($info) $sellamount = floatval($sellamount) - arraySafeVal($info, "paytxfee") * 3;

echo Yii::$app->ViewUtils->getAdminSideBarLinks().'<br/><br/>';
echo Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet');

$maxrows = Yii::$app->ConversionUtils->arraySafeVal($_REQUEST,'rows', 500);
$since = Yii::$app->ConversionUtils->arraySafeVal($_REQUEST,'since', time() - (7*24*3600)); // one week

echo '<div id="main_actions">';

//app()->clientScript->registerCoreScript('jquery.ui'); // dialog

echo '<br/>' . Html::a('<b>DELETE EARNINGS</b>', ['delete-earnings', 'id' => $coin->id], ['class' => 'red']);
echo '<br/>' . Html::a('<b>CLEAR EARNINGS</b>', ['clear-earnings', 'id' => $coin->id]);
echo '<br/>' . Html::a('<b>UPDATE BLOCKS</b>', ['check-blocks', 'id' => $coin->id]);
echo '<br/>' . Html::a('<b>DO PAYMENTS</b>', ['pay-users-coin', 'id' => $coin->id]);
echo '<br/>';
echo '</div>';

<style type="text/css">
table.dataGrid a.red, table.dataGrid a.red:visited, a.red { color: darkred; }
div#main_actions {
	position: absolute; top: 60px; right: 16px; width: 280px; text-align: right;
}
div#markets {
	overflow-x: hidden; overflow-y: scroll; max-height: 156px;
}
div#transactions {
	overflow-x: hidden; overflow-y: scroll; min-height: 200px; max-height: 360px;
	margin-bottom: 8px;
}
div#sums {
	overflow-x: hidden; overflow-y: scroll; min-height: 250px; max-height: 600px;
	width: 380px; float: left; margin-top: 16px; margin-bottom: 8px; margin-right: 16px;
}
.page .footer { clear: both; width: auto; margin-top: 16px; }
tr.ssrow.bestmarket { background-color: #dfd; }
tr.ssrow.disabled { background-color: #fdd; color: darkred; }
tr.ssrow.orphan { color: darkred; }
</style>

<div id="main_results"></div>

<?= CspHelper::beginScript() ?>

function uninstall_coin()
{
	if(!confirm("Uninstall this coin?"))
		return;

	window.location.href = '<?= \yii\helpers\Url::to(['uninstall-coin', 'id' => $coin->id]) ?>';
}

var main_delay=30000;
var main_timeout;

function main_refresh()
{
	var url = "<?= \yii\helpers\Url::to(['coinwallet-details', 'id' => $id, 'rows' => $maxrows, 'since' => $since]) ?>";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready);
}

function main_ready(data)
{
	$('#main_results').html(data);
	$(window).trigger('resize'); // will draw graph
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function showSellAmountDialog(marketname, address, marketid, bookmarkid)
{
	$("#dlgaddr").html(address);
	$("#sell-amount-dialog").dialog(
	{
    	autoOpen: true,
		width: 400,
		height: 240,
		modal: true,
		title: 'Send $coin->symbol to '+marketname,

		buttons:
		{
			"Send / Sell": function()
			{
				amount = $('#input_sell_amount').val();
				if (marketid > 0)
					window.location.href = '/market/sellto?id='+marketid+'&amount='+amount;
				else
					window.location.href = '<?= \yii\helpers\Url::to(['bookmark-send']) ?>?id='+bookmarkid+'&amount='+amount;
			},
		}
	});
	return false;
}

<?= CspHelper::endScript() ?>

<div id="sell-amount-dialog" class="d-none">
<br>
Address: <span id="dlgaddr">xxxxxxxxxxxx</span><br><br>
Amount: <input type=text id="input_sell_amount" value="$sellamount">
<br>
</div>

END;

Yii::$app->view->registerJs("main_refresh();");

if ($coin->watch) {
	echo Yii::$app->controller->renderPartial('coin_market_graph', array('coin'=>$coin));
//	echo $this->renderPartial('coin_market_graph', array('coin'=>$coin));
	Yii::$app->view->registerJs("$(window).resize(graph_resized);");
}

//////////////////////////////////////////////////////////////////////////////////////
