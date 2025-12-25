<?php

use app\models\Coins;
use app\models\Mining;
use app\components\DynamicStyleManager;

// Helper function as closure to avoid redeclaration
$valuetocell = function($amount) {
	$html = $amount ? Yii::$app->ConversionUtils->bitcoinvaluetoa($amount) : '-';
	//$html = rtrim($html,'0');
	//$html = rtrim($html,'.');
	$html = preg_replace('/([0]+)$/', '<span class="eov">${1}</span>', $html);
	return $html;
};

/////////////////////////////////////////////////////////////////////////////////////

echo <<<end
<style type="text/css">
tr.ssrow.filtered { display: none; }
th.status, td.status { min-width: 28px; max-width: 48px; text-align: center; }
td.status { font-family: monospace; font-size: 9pt; letter-spacing: 3px; }
td.status span.progress { font-size: .8em; letter-spacing: 0; }
td.status span.hidden { visibility: hidden; }
span.eov { opacity: 0.5; }
</style>
end;

Yii::$app->ViewUtils->showTableSorter('maintable', '{
tableClass: "dataGrid",
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}');

echo <<<end
<thead>
<tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text" width="30" class="status"></th>

<th data-sorter="text">Name</th>
<th data-sorter="text">Server</th>
<th data-sorter="currency" align="right">Difficulty<br/>Height</th>
<th data-sorter="currency" align="right" title="mBTC profit. shown in mining status">Profit<br/>Pool Net</th>
<th data-sorter="currency" align="right">Bid Price<br/>Ask Price</th>
<!--<th data-sorter="currency" align="right">Stake<br/>BTC</th>-->
<th data-sorter="currency" align="right">Immature<br/>Cleared</th>
<th data-sorter="currency" align="right">Balance<br/>Available</th>
<th data-sorter="currency" align="right">BTC</th>
<th data-sorter="currency" align="right">USD</th>
<th data-sorter="currency" align="right">Win<br/>Market</th>

</tr>
</thead><tbody>
end;

$server = Yii::$app->getRequest()->getQueryParam('server');

$coins_query = Coins::find()
				->where(['installed' => 1,'watch' => 1])
				->orderBy('algo , index_avg DESC');

if(!empty($server)) {
	$coins = $coins_query->andWhere(['rpchost' => $server])->all();
}
else
	$coins = $coins_query->all();

$mining = Mining::find()->one();

foreach($coins as $coin)
{
	echo '<tr class="ssrow">';

	$lowsymbol = strtolower($coin->symbol);
	echo '<td><img src="'.$coin->image.'" width="24"></td>';

	$algoColorClass = DynamicStyleManager::getAlgoColorClass($coin->algo);
	echo '<td class="status ' . $algoColorClass . '">';

	if(!$coin->enable) echo '<span class="hidden" title="Coin disabled">X</span>';
	else if($coin->auto_ready) echo '<span class="green" title="Auto enable">A</span>';
	else echo '<span class="red" title="Stratum disabled">D</span>';

	if($coin->visible) echo '<span title="Visible to public">V</span>';
	else echo '<span title="Hidden">H</span>';

	if($coin->auxpow) echo '<span title="AUX PoW">X</span>';
	else echo '&nbsp;';

	echo '<br/>';

	if($coin->rpccurl) echo '<span title="RPC with Curl">C</span>';
	else echo '&nbsp;';

	if($coin->rpcssl) echo '<span title="RPC over SSL">S</span>';
	else echo '&nbsp;';

	if($coin->watch) echo '<span title="Watched (history)">W</span>';
	else echo '&nbsp;';

	if($coin->block_height < $coin->target_height) {
		$percent = round($coin->block_height*100/$coin->target_height, 1);
		echo '<br/><span class="progress">'.$percent.'%</span>';
	}

	echo "</td>";
	$version = Yii::$app->ConversionUtils->formatWalletVersion($coin);
	if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

	echo '<td><b>' . \yii\helpers\Html::a($coin->name . ' (' . $coin->symbol . ')', ['coinwallet', 'id' => $coin->id]) . '</b>
		<br><span class="text-small">' . $version . '</span></td>';

	echo "<td>$coin->rpchost:$coin->rpcport";
	if($coin->connections) echo " ($coin->connections)";
	echo "<br><span class='text-small'>$coin->rpcencoding <span class='{$algoColorClass}'>&nbsp; ($coin->algo) &nbsp;</span></span></td>";

	$difficulty = Yii::$app->ConversionUtils->Itoa2($coin->difficulty, 3);
	if ($coin->difficulty > 1e20) $difficulty = '&nbsp;';

	if(!empty($coin->errors))
		echo '<td align="right" class="text-smaller red" title="'.$coin->errors.'"><b>'.$difficulty.'</b><br/>'.$coin->block_height.'</td>';
	else
		echo '<td align="right" class="text-smaller"><b>'.$difficulty.'</b><br>'.$coin->block_height.'</td>';

	$btcmhd = Yii::$app->YiimpUtils->yiimp_profitability($coin);
	$btcmhd = Yii::$app->ConversionUtils->mbitcoinvaluetoa($btcmhd);

	$h = $coin->block_height-100;
	$ss1 = (new \yii\db\Query())
				->select(['count(*)'])
				->from('blocks')
				->where(['coin_id' => $coin->id])
				->andWhere(['>=', 'height' , $h])
				->andWhere(['!=', 'category' , 'orphan'])
				->scalar();
	$ss2 = (new \yii\db\Query())
				->select(['count(*)'])
				->from('blocks')
				->where(['coin_id' => $coin->id])
				->andWhere(['>=', 'height' , $h])
				->andWhere(['=', 'category' , 'orphan'])
				->scalar();

	$percent_pool1 = $ss1? $ss1.'%': '';
	$percent_pool2 = $ss2? $ss2.'%': '';

	echo '<td align="right" class="text-smaller" title="Pool % of last 100 net blocks">';
	if($ss1 > 50)
		echo '<b>'.$btcmhd.'</b><br/><span class="blue">'.$percent_pool1.'</span>';
	else
		echo '<b>'.$btcmhd.'</b><br/>'.$percent_pool1;
	echo '<span class="red" title="orphans"> '.$percent_pool2.'</span></td>';

	$price = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price);
	$price2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price2);

	if($coin->dontsell && YAAMP_ALLOW_EXCHANGE)
		echo "<td align=right class='text-smaller price-warning'>$price<br>$price2</td>";
	else
		echo "<td align=right class='text-smaller'>$price<br>$price2</td>";

	$cell = $valuetocell($coin->mint).'<br/>'.$valuetocell($coin->cleared);

	if($coin->balance+$coin->mint < $coin->cleared)
		echo '<td align="right" class="text-smaller"><span class="red">'.$cell.'</span></td>';
	else
		echo '<td align="right" class="text-smaller">'.$cell.'</td>';

	$cell = $valuetocell($coin->balance).'<br/>'.$valuetocell($coin->available);
	echo '<td align="right" class="text-smaller">'.$cell.'</td>';

	$btc = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->balance * $coin->price);
	$available = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->available * $coin->price);
	echo '<td align="right" class="text-smaller">'.$btc.'<br/>'.$available.'</td>';

	$fiat = round($coin->balance * $coin->price * $mining->usdbtc, 2). ' $';
	$available = round($coin->available * $coin->price * $mining->usdbtc, 2). ' $';
	echo '<td align="right" class="text-smaller">'.$fiat.'<br/>'.$available.'</td>';

	$marketname = '';
//	$bestmarket = Yii::$app->YiimpUtils->getBestMarket($coin);
//	if($bestmarket)	$marketname = $bestmarket->name;

	echo "<td align=right class='text-smaller'>$coin->reward<br>$marketname</td>";

	echo "</tr>";
}

$total = count($coins);
echo '</tbody>';

echo '<tr><th colspan="12">'.$total.' wallets</th></tr>';

echo '</table>';

//////////////////////////////////////////

echo "<br/>";
