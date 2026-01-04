<?php

/** @var yii\web\View $this */

use Yii;
use yii\helpers\Html;

use app\models\Balances;
use app\models\Blocks;
use app\models\Coins;
use app\models\Mining;
use app\models\Markets;
use app\models\Orders;
use app\models\Stats;
use app\models\Stratums;
use app\models\Workers;
use app\components\DynamicStyleManager;

$mining = Mining::find()->one();

$showrental = (bool) YAAMP_RENTAL;

echo <<<END
<style type="text/css">
</style>

<br/><table width="100%"><tr><td valign="top">
END;

///////////////////////////////////////////////////////////////////////////////////////////////////////

Yii::$app->ViewUtils->showTableSorter('maintable', '{
tableClass: "dataGrid",
widgets: ["Storage","saveSort"],
textExtraction: {
	1: function(node, table, cellIndex) { return $(node).attr("data"); },
	5: function(node, table, cellIndex) { return $(node).attr("data"); }
},
widgetOptions: {
	saveSort: true
}}');

echo <<<end
<thead>
<tr>
<th data-sorter="text" align="left">Algo</th>
<th data-sorter="numeric" align="left">Up</th>
<th data-sorter="numeric" align="right" title="Currencies">C</th>
<th data-sorter="numeric" align="right" title="Miners">M</th>
<th data-sorter="currency" align="right">Fee</th>
<th data-sorter="numeric" align="right">Rate</th>
<th data-sorter="currency" align="right" class="rental">Rent</th>
<th data-sorter="currency" align="right">Bad</th>
<th data-sorter="currency" align="right">Now</th>
<th data-sorter="currency" align="right" class="rental">Rent</th>
<th data-sorter="currency" align="right">Norm</th>
<th data-sorter="currency" align="right">24E</th>
<th data-sorter="currency" align="right">24A</th>
</tr>
</thead>
<tbody>
end;

$total_coins = 0;
$total_workers = 0;
$total_hashrate = 0;
$total_hashrate_bad = 0;

$algos = array();
foreach(Yii::$app->YiimpUtils->get_algos() as $algo)
{
	$algo_norm = Yii::$app->YiimpUtils->get_algo_norm($algo);

	$t = time() - 48*60*60;

	$price = Yii::$app->cache->get("current_price-$algo");
	if (!$price) {
		$price = (new \yii\db\Query())
				->select(['price'])
				->from('hashrate')
				->where(['algo' => $algo])
				->andWhere(['>','time',$t])
				->orderBy('time DESC')
				->limit(1)
				->scalar();
		Yii::$app->cache->set("current_price-$algo", $price);
	}

	$norm = $price*$algo_norm;
	$norm = Yii::$app->YiimpUtils->take_yiimp_fee($norm, $algo);

	$algos[] = array($norm, $algo);
}

// Use anonymous function to avoid redeclaration
usort($algos, function($a, $b) {
	return $a[0] < $b[0];
});
foreach($algos as $item)
{
	$norm = $item[0];
	$algo = $item[1];

	$algoColorClass = DynamicStyleManager::getAlgoColorClass($algo);
	$algo_norm = Yii::$app->YiimpUtils->get_algo_norm($algo);

	$coins = Coins::find()->where(['enable' => 1, 'auto_ready' => 1, 'algo' => $algo])->count();
	$count = Workers::find()->where(['algo' => $algo])->count();

	$total_coins += $coins;
	$total_workers += $count;

	$t1 = time() - 24*60*60;
	$total1 = (new \yii\db\Query())
				->select(['sum(amount*price)'])
				->from('blocks')
				->where(['algo' => $algo])
				->andWhere(['!=','category','orphan'])
				->andWhere(['>','time',$t1])
				->scalar();
	if (!$coins && !$total1) continue;

	$hashrate1 = (new \yii\db\Query())
				->select(['avg(hashrate)'])
				->from('hashrate')
				->where(['algo' => $algo])
				->andWhere(['>','time',$t1])
				->scalar();

	$hashrate = Yii::$app->cache->get("current_hashrate-$algo");
	if (!$hashrate) {
		$hashrate = (new \yii\db\Query())
				->select(['hashrate'])
				->from('hashrate')
				->where(['algo' => $algo])
				->orderBy('time DESC')
				->limit(1)
				->scalar();
		Yii::$app->cache->set("current_hashrate-$algo", $hashrate);
	}

	$hashrate_bad = (new \yii\db\Query())
				->select(['hashrate_bad'])
				->from('hashrate')
				->where(['algo' => $algo])
				->orderBy('time DESC')
				->limit(1)
				->scalar();
	
	$bad = ($hashrate+$hashrate_bad)? round($hashrate_bad * 100 / ($hashrate+$hashrate_bad), 1): '';

	$total_hashrate += $hashrate;
	$total_hashrate_bad += $hashrate_bad;

	$hashrate_sfx = $hashrate? Yii::$app->ConversionUtils->Itoa2($hashrate).'h/s': '-';
	$hashrate_bad = $hashrate_bad? Yii::$app->ConversionUtils->Itoa2($hashrate_bad).'h/s': '-';

	$hashrate_jobs = Yii::$app->YiimpUtils->rented_rate($algo);
	$hashrate_jobs = $hashrate_jobs>0? Yii::$app->ConversionUtils->Itoa2($hashrate_jobs).'h/s': '';

	$price = (new \yii\db\Query())
				->select(['price'])
				->from('hashrate')
				->where(['algo' => $algo])
				->orderBy('time DESC')
				->limit(1)
				->scalar();
	$price = $price? Yii::$app->ConversionUtils->mbitcoinvaluetoa($price): '-';

	$rent = (new \yii\db\Query())
				->select(['rent'])
				->from('hashrate')
				->where(['algo' => $algo])
				->orderBy('time DESC')
				->limit(1)
				->scalar();
	$rent = $rent? Yii::$app->ConversionUtils->mbitcoinvaluetoa($rent): '-';

	$norm = Yii::$app->ConversionUtils->mbitcoinvaluetoa($norm);

	$t = time() - 24*60*60;
	$avgprice = (new \yii\db\Query())
				->select(['avg(price)'])
				->from('hashrate')
				->where(['algo' => $algo])
				->andWhere(['>','time',$t])
				->scalar();
	$avgprice = $avgprice? Yii::$app->ConversionUtils->mbitcoinvaluetoa(Yii::$app->YiimpUtils->take_yiimp_fee($avgprice, $algo)): '-';

	$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
	$btcmhday1 = $hashrate1 != 0? Yii::$app->ConversionUtils->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor): '-';

	$fees = Yii::$app->YiimpUtils->yiimp_fee($algo);

	// todo: show per port data ?
	$stratum = Stratums::find()
					->where(['algo' => $algo])
					->orderBy('started DESC')
					->one();
	$isup = Yii::$app->ConversionUtils->Booltoa($stratum);
	$time = $isup ? Yii::$app->ConversionUtils->datetoa2($stratum->started) : '';
	$ts = $isup ? Yii::$app->ConversionUtils->datetoa2($stratum->started) : '';

	echo '<tr class="ssrow">';
	echo '<td class="' . $algoColorClass . '"><b>';
	echo Html::a($algo, ['/site/gomining', 'algo' => $algo]);
	echo '</b></td>';
	echo '<td align="left" class="text-small" data="'.$ts.'">'.$isup.'&nbsp;'.$time.'</td>';
	echo '<td align="right" class="text-small">'.(empty($coins) ? '-' : $coins).'</td>';
	echo '<td align="right" class="text-small">'.(empty($count) ? '-' : $count).'</td>';
	echo '<td align="right" class="text-small">'.(empty($fees) ? '-' : "$fees %").'</td>';
	echo '<td align="right" class="text-small" data="'.$hashrate.'">'.$hashrate_sfx.'</td>';
	echo '<td align="right" class="text-small rental">'.$hashrate_jobs.'</td>';

	if ($bad > 10)
		echo '<td align="right" class="text-small" class="badge-danger">'.$bad.'%</td>';
	else if($bad > 5)
		echo '<td align="right" class="text-small" class="badge-warning">'.$bad.'%</td>';
	else
		echo '<td align="right" class="text-small">'.(empty($bad) ? '-' : "$bad %").'</td>';

	if ($norm>0)
		echo '<td align=right class="text-small" title="normalized '.$norm.'">'.($price == 0.0 ? '-' : $price).'</td>';
	else
		echo '<td align=right class="text-small">'.($price == 0.0 ? '-' : $price).'</td>';

	echo '<td align="right" class="text-small rental">'.$rent.'</td>';

	// Norm
	echo '<td align="right" class="text-small">'.($norm == 0.0 ? '-' : $norm).'</td>';

	// 24E
	echo '<td align="right" class="text-small">'.($avgprice == 0.0 ? '-' : $avgprice).'</td>';

	// 24A
	$perfClass = '';
	if ($btcmhday1 != '-')
	{
		$avgprice = (double) $avgprice;
		$btcmhd = (double) $btcmhday1;

		if($btcmhd > $avgprice*1.1)
			$perfClass = 'perf-excellent';
		else if($btcmhd*1.3 < $avgprice)
			$perfClass = 'perf-poor';
		else if($btcmhd*1.2 < $avgprice)
			$perfClass = 'perf-bad';
		else if($btcmhd*1.1 < $avgprice)
			$perfClass = 'perf-warning';
	}
	echo '<td align="right" class="text-small ' . $perfClass . '">'.$btcmhday1.'</td>';

	echo '</tr>';
}

echo '</tbody>';

$bad = ($total_hashrate+$total_hashrate_bad)? round($total_hashrate_bad * 100 / ($total_hashrate+$total_hashrate_bad), 1): '';
$total_hashrate = Yii::$app->ConversionUtils->Itoa2($total_hashrate).'h/s';

echo '<tr class="ssfooter">';
echo '<td colspan="2"></td>';
echo '<td align="right" class="text-small">'.$total_coins.'</td>';
echo '<td align="right" class="text-small">'.$total_workers.'</td>';
echo '<td align="right" class="text-small"></td>';
echo '<td align="right" class="text-small">'.$total_hashrate.'</td>';
echo '<td align="right" class="text-small rental"></td>';
echo '<td align="right" class="text-small">'.($bad ? $bad.'%' : '').'</td>';
echo '<td align="right" class="text-small"></td>';
echo '<td align="right" class="text-small rental"></td>';
echo '<td align="right" class="text-small"></td>';
echo '<td align="right" class="text-small"></td>';
echo '</tr>';

echo '</table><br>';

///////////////////////////////////////////////////////////////////////////////////////////////////////

$markets = Balances::find()->orderBy('name')->all();
$salebalances = array(); $alt_balances = array();
$total_onsell = $total_altcoins = 0.0;
$total_usd = $total_total = $total_balance = 0.0;

echo '<table class="dataGrid">';
echo '<thead>';

echo '<tr>';
echo '<th></th>';

foreach($markets as $market)
	echo '<th align="right">' . \yii\helpers\Html::a($market->name, ['run-exchange', 'id' => $market->id]) . '</th>';

echo '<th align="right">Total</th>';

echo '</tr>';
echo '</thead>';

// ----------------------------------------------------------------------------------------------------

echo '<tr class="ssrow"><td>BTC</td>';
foreach($markets as $market)
{
	$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($market->balance);

	if($balance > 0.250)
		echo '<td align="right" class="badge-success">'.$balance.'</td>';
	else if($balance > 0.200)
		echo '<td align="right" class="badge-warning">'.$balance.'</td>';
	else if($balance == 0.0)
		echo '<td align="right">-</td>';
	else
		echo '<td align="right">'.$balance.'</td>';

	$total_balance += $balance;
}

$total_balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_balance);

echo '<td align="right" class="badge-orange">'.$total_balance.'</td>';
echo '</tr>';

// ----------------------------------------------------------------------------------------------------

echo '<tr class="ssrow"><td>orders</td>';
if (YAAMP_ALLOW_EXCHANGE) {
	// auto-exchange mode
	foreach($markets as $market) {
		$exchange = $market->name;
		$onsell_db = (new \yii\db\Query())
				->select(['sum(amount*bid)'])
				->from('orders')
				->where(['market' => $exchange])
				->scalar();
		$onsell = Yii::$app->ConversionUtils->bitcoinvaluetoa($onsell_db);
		$salebalances[$exchange] = $onsell;

		if($onsell > 0.2)
			echo '<td align="right" class="badge-danger">'.$onsell.'</td>';
		else if($onsell > 0.1)
			echo '<td align="right" class="badge-warning">'.$onsell.'</td>';
		else if($onsell == 0.0)
			echo '<td align="right">-</td>';
		else
			echo '<td align="right">'.$onsell.'</td>';

		$total_onsell += $onsell;
	}
} else {
	// direct mining mode
	$ontrade = (new \yii\db\Query())
				->select(['name','onsell'])
				->from('balances')
				->orderBy('name')
				->all();
	foreach($ontrade as $row) {
		$exchange = $row['name'];
		$onsell = Yii::$app->ConversionUtils->bitcoinvaluetoa($row['onsell']);
		$salebalances[$exchange] = $onsell;

		echo '<td align="right">'.($onsell == 0 ? '-' : $onsell).'</td>';

		$total_onsell += (double) $onsell;
	}

}
$total_onsell = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_onsell);
echo '<td align="right">'.$total_onsell.'</td>';
echo '</tr>';

// ----------------------------------------------------------------------------------------------------

$t = time() - 48*60*60;

$altmarkets = (new \yii\db\Query())
					->select(['B.name','SUM((markets.balance+markets.ontrade)*markets.price) AS balance'])
					->from('balances as B')
					->leftJoin('markets','markets.name = B.name')
					->where(['IFNULL(markets.deleted,0)' => 0])
					->andWhere(['in', "IFNULL(markets.base_coin,'BTC')", ['','BTC']])
					->groupBy('B.name')
					->orderBy('B.name')
					->all();

/*$altmarkets = dbolist("
	SELECT B.name, SUM((M.balance+M.ontrade)*M.price) AS balance
	FROM balances B LEFT JOIN markets M ON M.name = B.name
	WHERE IFNULL(M.base_coin,'BTC') IN ('','BTC') AND IFNULL(M.deleted,0)=0
	GROUP BY B.name ORDER BY B.name
");*/

echo '<tr class="ssrow"><td>other</td>';
foreach($altmarkets as $row)
{
	$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($row['balance']);
	$exchange = $row['name'];
	if($balance == 0.0) {
		echo '<td align="right">-</td>';
	} else {
		// to prevent duplicates on multi-algo coins, ignore symbols with a "-"
		$balance = (new \yii\db\Query())
						->select(['SUM((markets.balance+markets.ontrade)*markets.price)'])
						->from('markets')
						->innerJoin('coins', 'coins.id = markets.coinid')
						->where(['IFNULL(markets.deleted,0)' => 0])
						->andWhere(['markets.name' => $exchange])
						->andWhere(["INSTR(coins.symbol,'-')" => 0])
						->scalar();
		
		/*dboscalar("
			SELECT SUM((M.balance+M.ontrade)*M.price) FROM markets M INNER JOIN coins C on C.id = M.coinid
			WHERE M.name='$exchange' AND IFNULL(M.deleted,0)=0 AND INSTR(C.symbol,'-')=0
		");*/
		$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($balance);
		echo '<td align="right">' . \yii\helpers\Html::a($balance, ['balances', 'exch' => $exchange]) . '</td>';
	}
	$alt_balances[$exchange] = $balance;
	$total_altcoins += $balance;
}
$total_altcoins = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_altcoins);

echo '<td align="right">'.$total_altcoins.'</td>';
echo '</tr>';

// ----------------------------------------------------------------------------------------------------

echo '<tfoot>';
echo '<tr class="ssrow"><td><b>Total</b></td>';
foreach($markets as $market)
{
	$total = $market->balance + Yii::$app->ConversionUtils->arraySafeVal($alt_balances,$market->name,0) + Yii::$app->ConversionUtils->arraySafeVal($salebalances,$market->name,0);

	echo '<td align="right">'.($total > 0.0 ? Yii::$app->ConversionUtils->bitcoinvaluetoa($total) : '-').'</td>';
	$total_total += $total;
}

$total_total = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_total);

echo '<td align="right"><b>'.$total_total.'</b></td>';
echo '</tr>';

// ----------------------------------------------------------------------------------------------------

echo '<tr class="ssrow"><td>USD</td>';
foreach($markets as $market)
{
	$total = $market->balance + Yii::$app->ConversionUtils->arraySafeVal($alt_balances,$market->name,0) + Yii::$app->ConversionUtils->arraySafeVal($salebalances,$market->name,0);
	$usd = $total * $mining->usdbtc;

	echo '<td align="right">'.($usd > 0.0 ? round($usd,2) : '-').'</td>';
	$total_usd += $usd;
}

echo '<td align="right">'.round($total_usd,2).'&nbsp;$</td>';
echo '</tr>';

echo '</tfoot>';
echo '</table><br/>';

//////////////////////////////////////////////////////////////////////////////////////////////////

$minsent = time()-2*60*60;
$list = Markets::find()
			->where(['<','lastsent',$minsent])
			->andWhere(['>','lastsent','lasttraded'])
			->orderBy('lastsent')
			->all();

//getdbolist('db_markets', "lastsent<$minsent and lastsent>lasttraded order by lastsent");

echo '<table class="dataGrid">';
echo '<thead class="">';

echo '<tr>';
echo '<th width="20px"></th>';
echo '<th>Name</th>';
echo '<th>Exchange</th>';
echo '<th>Sent</th>';
echo '<th>Traded</th>';
echo '<th></th>';
echo '</tr>';
echo '</thead><tbody>';

foreach($list as $market)
{
	$price = Yii::$app->ConversionUtils->bitcoinvaluetoa($market->price);
	$coin = Coins::find()
				->where(['id' => $market->coinid])
				->one();

	$marketurl = Yii::$app->YiimpUtils->getMarketUrl($coin, $market->name);

	$algoColorClass = DynamicStyleManager::getAlgoColorClass($coin->algo);
	echo '<tr class="ssrow ' . $algoColorClass . '">';

	echo '<td><img width="16px" src="'.$coin->image.'"></td>';
	echo '<td><b>' . \yii\helpers\Html::a($coin->name . ' (' . $coin->symbol . ')', ['coin', 'id' => $coin->id]) . '</b></td>';

	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$market->name.'</a></b></td>';

	$sent = Yii::$app->ConversionUtils->datetoa2($market->lastsent);
	$traded = Yii::$app->ConversionUtils->datetoa2($market->lasttraded);

	echo '<td>'.$sent.' ago</td>';
	echo '<td>'.$traded.' ago</td>';

	echo '<td>' . \yii\helpers\Html::a('clear', ['clear-market', 'id' => $market->id]) . '</td>';
	echo '</tr>';
}

echo '</tbody></table><br>';

//////////////////////////////////////////////////////////////////////////////////////////////////

$orders = Orders::find()
			->orderBy('(amount*bid) desc')
			->all();
//getdbolist('db_orders', "1 order by (amount*bid) desc");

echo '<table class="dataGrid">';
//showTableSorter('maintable');
echo '<thead>';
echo '<tr>';
echo '<th width="20px"></th>';
echo '<th>Name</th>';
echo '<th>Exchange</th>';
echo '<th>Created</th>';
echo '<th>Quantity</th>';
echo '<th>Ask</th>';
echo '<th>Bid</th>';
echo '<th>Value</th>';
echo '<th></th>';
echo '</tr>';
echo '</thead><tbody>';

$totalvalue = 0;
$totalbid = 0;

foreach($orders as $order)
{
	$coin = Coins::find()
				->where(['id' => $order->coinid])
				->one();
	if(!$coin) continue;

	$marketurl = Yii::$app->YiimpUtils->getMarketUrl($coin, $order->market);

	$algoColorClass = DynamicStyleManager::getAlgoColorClass($coin->algo);
	echo '<tr class="ssrow ' . $algoColorClass . '">';

	$created = Yii::$app->ConversionUtils->datetoa2($order->created). ' ago';
	$price = $order->price? Yii::$app->ConversionUtils->bitcoinvaluetoa($order->price): '';

	$price = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->price);
	$bid = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->bid);
	$value = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->amount*$order->price);
	$bidvalue = Yii::$app->ConversionUtils->bitcoinvaluetoa($order->amount*$order->bid);
	$totalvalue += $value;
	$totalbid += $bidvalue;
	$bidpercent = $value>0? round(($value-$bidvalue)/$value*100, 1): 0;
	$amount = round($order->amount, 3);

	echo '<td><img width="16px" src="'.$coin->image.'"></td>';
	echo '<td><b>' . \yii\helpers\Html::a($coin->name, ['coin', 'id' => $coin->id]) . '</b></td>';
	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$order->market.'</a></b></td>';

	echo '<td class="text-small">'.$created.'</td>';
	echo '<td class="text-small">'.$amount.'</td>';
	echo '<td class="text-small">'.$price.'</td>';
	echo '<td class="text-small">'."$bid ({$bidpercent}%)".'</td>';
	echo $bidvalue>0.01? '<td class="text-small"><b>'.$bidvalue.'</b></td>': '<td class="text-small">'.$bidvalue.'</td>';

	echo '<td>';
	echo \yii\helpers\Html::a('cancel', ['cancel-order', 'id' => $order->id], ['title' => 'Cancel the order on the exchange!']) . ' ';
	echo \yii\helpers\Html::a('clear', ['clear-order', 'id' => $order->id], ['title' => 'Clear the order from the DB, NOT FROM THE EXCHANGE!']) . ' ';
//	echo \yii\helpers\Html::a('sell', ['sell-order', 'id' => $order->id]);
	echo '</td>';
	echo '</tr>';
}

$bidpercent = $totalvalue>0? round(($totalvalue-$totalbid)/$totalvalue*100, 1): '';

if ($totalvalue) {
echo '<tr>';
echo '<td></td>';
echo '<td>Total</td>';
echo '<td colspan="3"></td>';
echo '<td class="text-small"><b>'.$totalvalue.'</b></td>';
echo '<td class="text-small"><b>'."$totalbid ({$bidpercent}%)</b></td>";
echo '<td></td>';
echo '</tr>';
}

echo '</tbody></table><br>';

///////////////////////////////////////////////////////////////////////////////////////

echo '</td><td>&nbsp;&nbsp;</td><td valign="top">';

//////////////////////////////////////////////////////////////////////////////////

// Helper function as closure to avoid redeclaration
$cronstate2text = function($state) {
	switch($state - 1)
	{
		case 0:
			return 'new coins';
		case 1:
			return 'trade';
		case 2:
			return 'trade2';
		case 3:
			return 'prices';
		case 4:
			return 'blocks';
		case 5:
			return 'sell';
		case 6:
			return 'find2';
		case 7:
			return 'notify';
		default:
			return '';
	}
};

$state_main = (int) Yii::$app->cache->get('cronjob_main_state');
$btc = Coins::find()->where(['symbol' => 'BTC'])->one();
if (!$btc) $btc = json_decode('{"id": 6, "balance": 0}');

echo '<span class="text-bold-red">';
for($i=0; $i<10; $i++)
{
	if($i != $state_main-1 && $state_main>0)
	{
		$state = Yii::$app->cache->get("cronjob_main_state_$i");
		if($state) echo "main $i ";
	}
}

echo '</span>';

$block_time = Yii::$app->ConversionUtils->sectoa(time()-Yii::$app->cache->get("cronjob_block_time_start"));
$loop2_time = Yii::$app->ConversionUtils->sectoa(time()-Yii::$app->cache->get("cronjob_loop2_time_start"));
$main_time2 = Yii::$app->ConversionUtils->sectoa(time()-Yii::$app->cache->get("cronjob_main_time_start"));

$main_time = Yii::$app->ConversionUtils->sectoa(Yii::$app->cache->get("cronjob_main_time"));
$main_text = $cronstate2text($state_main);

echo "*** main  ($main_time) $state_main $main_text ($main_time2), loop2 ($loop2_time), block ($block_time)<br>";

//Todo: take other currencies too
$topay = (new \yii\db\Query())
				->select(["sum(balance)"])
				->from('accounts')
				->where(['coinid' => $btc->id])
				->scalar();
$topay2 = Yii::$app->ConversionUtils->bitcoinvaluetoa(
		(new \yii\db\Query())
				->select(["sum(balance)"])
				->from('accounts')
				->where(['coinid' => $btc->id])
				->andWhere(['>','balance','0.001'])
				->scalar()
	);

$renter = (new \yii\db\Query())
				->select(["sum(balance)"])
				->from('renters')
				->scalar();
$stats = Stats::find()->orderBy('time desc')->one();
$margin2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($btc->balance - $topay - $renter + $stats->balances + $stats->onsell + $stats->wallets);

$margin = Yii::$app->ConversionUtils->bitcoinvaluetoa($btc->balance - $topay - $renter);

$topay = Yii::$app->ConversionUtils->bitcoinvaluetoa($topay);
$renter = Yii::$app->ConversionUtils->bitcoinvaluetoa($renter);


$immature = (new \yii\db\Query())
				->select(["sum(amount*price)"])
				->from('earnings')
				->where(['status' => 0])
				->scalar();
$mints = $immature = (new \yii\db\Query())
				->select(["sum(mint*price)"])
				->from('coins')
				->where(['enable' => 1])
				->scalar();

$off = $mints-$immature;

$immature = Yii::$app->ConversionUtils->bitcoinvaluetoa($immature);
$mints = Yii::$app->ConversionUtils->bitcoinvaluetoa($mints);
$off = Yii::$app->ConversionUtils->bitcoinvaluetoa($off);

$btcaddr = YAAMP_BTCADDRESS;

echo '<a href="https://www.bitstamp.net/markets/btc/usd/" target="_blank">Bitstamp '.$mining->usdbtc.'</a>, ';
echo '<a href="https://blockchain.info/address/'.$btcaddr.'" target="_blank">wallet '.$btc->balance.'</a>, next payout '.$topay2.'<br/>';

echo "pay $topay, renter $renter, marg $margin, $margin2<br/>";
echo "mint $mints immature $immature off $off<br/>";

echo '<br/>';

//////////////////////////////////////////////////////////////////////////////////////////////////

echo '<div class="h-160" id="graph_results_negative"></div>';
// Removed inline style - now using chart-container-160 class
echo '<div class="h-200" id="graph_results_assets"></div>';

///////////////////////////////////////////////////////////////////////////
$db_blocks = Blocks::find()->orderBy('time desc')->limit(50)->all();

echo '<br><table class="dataGrid">';
echo '<thead>';
echo '<tr>';
echo '<th></th>';
echo '<th>Name</th>';
echo '<th align=right>Amount</th>';
echo '<th align=right>Diff</th>';
echo '<th align=right>Block</th>';
echo '<th align=right>Time</th>';
echo '<th align=right>Status</th>';
echo '</tr>';
echo '</thead>';

foreach($db_blocks as $db_block)
{
	$d = Yii::$app->ConversionUtils->datetoa2($db_block->time);
	if(!$db_block->coin_id)
	{
		if (!$showrental)
			continue;

		$reward = Yii::$app->ConversionUtils->bitcoinvaluetoa($db_block->amount);

		$algoColorClass = DynamicStyleManager::getAlgoColorClass($db_block->algo);
		echo '<tr class="' . $algoColorClass . '">';
		echo '<td width="18px"><img width="16px" src="/images/btc.png"></td>';
		echo '<td><b>Rental</b> ('.$db_block->algo.')</td>';
		echo '<td align="right" class="text-small"><b>$reward BTC</b></td>';
		echo '<td align="right" class="text-small"></td>';
		echo '<td align="right" class="text-small"></td>';
		echo '<td align="right" class="text-small">'.$d.' ago</td>';
		echo '<td align="right" class="text-small">';
		echo '<span class="badge-success">Confirmed</span>';
		echo '</td>';
		echo '</tr>';
		continue;
	}

	$coin = Coins::find()->where(['id' => $db_block->coin_id])->one();
	if(!$coin)
	{
		debuglog("coin not found {$db_block->coin_id}");
		continue;
	}

	$height = number_format($db_block->height, 0, '.', ' ');
	$diff = Yii::$app->ConversionUtils->Itoa2($db_block->difficulty, 3);

	$algoColorClass = DynamicStyleManager::getAlgoColorClass($coin->algo);
	echo '<tr class="' . $algoColorClass . '">';
	echo '<td width="18px"><img width="16px" src="'.$coin->image.'"></td>';
	$flags = $db_block->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" valign="center" title="segwit">' : '';
	echo '<td><b>' . \yii\helpers\Html::a($coin->name, ['coin', 'id' => $coin->id]) . '</b>'.$flags.'</td>';

	echo '<td align="right" class="text-small">'.$db_block->amount.' '.$coin->symbol.'</td>';
	echo '<td align="right" class="text-small" title="found '.$db_block->difficulty_user.'">'.$diff.'</td>';

	echo '<td align="right" class="text-small">'.$height.'</td>';
	echo '<td align="right" class="text-small">'.$d.' ago</td>';
	echo '<td align="right" class="text-small">';

	if($db_block->category == 'orphan')
		echo '<span class="block orphan" class="badge-danger">Orphan</span>';

	else if($db_block->category == 'immature')
		echo '<span class="block immature" class="badge-warning">Immature ('.$db_block->confirmations.')</span>';

	else if($db_block->category == 'stake')
		echo '<span class="block stake" class="badge-gray">Stake ('.$db_block->confirmations.')</span>';

	else if($db_block->category == 'generated')
		echo '<span class="block staked" class="badge-gray">Confirmed</span>';

	else if($db_block->category == 'generate')
		echo '<span class="block generate" class="badge-success">Confirmed</span>';

	else if($db_block->category == 'new')
		echo '<span class="block new" class="badge-purple">New</span>';

	echo '</td>';
	echo '</tr>';
}


echo '</table><br/>';

echo '</td></tr></table>';
exit;
?>

<?php if (!$showrental) : ?>

<style type="text/css">
.dataGrid .rental { display: none; }
</style>

<?php endif; ?>
