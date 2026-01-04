<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Coins;
use app\models\Blocks;
use app\components\CspHelper;

$memcache = Yii::$app->cache;
$cachetime=30;

$algo = Yii::$app->session->get('yaamp-algo');
$algo_unit = 'Mh';
$algo_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
if ($algo_factor == 0.001) $algo_unit = 'Kh';
if ($algo_factor == 1000) $algo_unit = 'Gh';
if ($algo_factor == 1000000) $algo_unit = 'Th';
if ($algo_factor == 1000000000) $algo_unit = 'Ph';
if($algo == 'all') return;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Block Stats ($algo)</div>";
echo "<div class='main-left-inner'>";

echo CspHelper::style('
td.symb, th.symb {
    width: 50px;
    max-width: 50px;
    text-align: right;
}
td.symb {
    font-size: .8em;
}
');

echo <<<END
<table class="dataGrid2">
<thead>
<tr>
<th></th>
<th>Name</th>
<th class="symb">Symbol</th>
<th align=right>Last Hour</th>
<th align=right>Last 24 Hours</th>
<th align=right>Last 7 Days</th>
<th align=right>Last 30 Days</th>
</tr>
</thead>

END;

$t1 = time() - 60*60;
$t2 = time() - 24*60*60;
$t3 = time() - 7*24*60*60;
$t4 = time() - 30*24*60*60;

$total1 = 0;
$total2 = 0;
$total3 = 0;
$total4 = 0;

$main_ids = array();

$coins_subquery = (new \yii\db\Query())
            ->select(['id'])
            ->from('coins')
            ->where(['visible' => 1, 'enable' => 1, 'algo' => $algo]);

$list = Blocks::find()
    ->where(['not in','category', ['orphan','stake','generated']])
    ->andWhere(['>', 'time', $t4])
    ->andWhere(['in', 'coin_id', $coins_subquery])
	->groupBy('coin_id')
	->orderBy('coin_id DESC')
    ->all();

foreach($list as $item)
{
	$coin = Coins::find()->where(['id' => $item['coin_id']])->one();

	$id = $coin->id;
	$main_ids[$id] = $coin->symbol;

	if($coin->symbol == 'BTC') continue;

	$res1 = $memcache->get("history_item1-$id-$algo");
	if (empty($res1)) {
		$res1 = (new \yii\db\Query())
                ->select(['COUNT(id) as a','SUM(amount*price) as b'])
                ->from('blocks')
                ->where(['coin_id' => $id, 'algo' => $algo])
				->andWhere(['not in','category', ['orphan','stake','generated']])
    			->andWhere(['>', 'time', $t1])
                ->one();
		$memcache->set("history_item1-$id-$algo", $res1, $cachetime);
	}

	$res2 = $memcache->get("history_item2-$id-$algo");
	if (empty($res2)) {
		$res2 = (new \yii\db\Query())
                ->select(['COUNT(id) as a','SUM(amount*price) as b'])
                ->from('blocks')
                ->where(['coin_id' => $id, 'algo' => $algo])
				->andWhere(['not in','category', ['orphan','stake','generated']])
    			->andWhere(['>', 'time', $t2])
                ->one();
		$memcache->set("history_item2-$id-$algo", $res2, $cachetime);
	}

	$res3 = $memcache->get("history_item3-$id-$algo");
	if (empty($res3)) {
		$res3 = (new \yii\db\Query())
                ->select(['COUNT(id) as a','SUM(amount*price) as b','MIN(time) as t'])
                ->from('blocks')
                ->where(['coin_id' => $id, 'algo' => $algo])
				->andWhere(['not in','category', ['orphan','stake','generated']])
    			->andWhere(['>', 'time', $t3])
                ->one();
		$memcache->set("history_item3-$id-$algo", $res3, $cachetime);
	}

	$res4 = $memcache->get("history_item4-$id-$algo");
	if (empty($res4)) {
		$res4 = (new \yii\db\Query())
                ->select(['COUNT(id) as a','SUM(amount*price) as b','MIN(time) as t'])
                ->from('blocks')
                ->where(['coin_id' => $id, 'algo' => $algo])
				->andWhere(['not in','category', ['orphan','stake','generated']])
    			->andWhere(['>', 'time', $t4])
                ->one();
		$memcache->set("history_item4-$id-$algo", $res4, $cachetime);
	}

	$total1 += $res1['b'];
	$total2 += $res2['b'];
	$total3 += $res3['b'];
	$total4 += $res4['b'];

	if ($res3['a'] == $res2['a'] || count($list) == 1) {
		// blocks table may be purged before 7 days, so use same source as stat graphs
		// TODO: add block count in hashstats or keep longer cleared blocks
		if ($res3['t'] > ($t3 + 24*60*60)) $res3['a'] = '-';
		
		$cacheKey = "history_item3-$id-$algo-btc";
		$total3 = $memcache->get($cacheKey);
		if ($total3 === false) {
			$total3 = (new \yii\db\Query())
				->select(['SUM(earnings) as b'])
				->from('hashstats')
				->where(['algo' => $algo])
				->andWhere(['>', 'time', $t3])
				->scalar();
			$memcache->set($cacheKey, $total3, $cachetime);
		}
	}

	if ($res4['a'] == $res3['a'] || count($list) == 1) {
		$res4['a'] = '-';
		
		$cacheKey = "history_item4-$id-$algo-btc";
		$total4 = $memcache->get($cacheKey);
		if ($total4 === false) {
			$total4 = (new \yii\db\Query())
				->select(['SUM(earnings) as b'])
				->from('hashstats')
				->where(['algo' => $algo])
				->andWhere(['>', 'time', $t4])
				->scalar();
			$memcache->set($cacheKey, $total4, $cachetime);
		}
	}

	$name = substr($coin->name, 0, 12);

	echo '<tr class="ssrow">';

	echo '<td width=18><img width=16 src="'.$coin->image.'"></td>';
	echo '<td><b><a href="/site/block?id='.$id.'">'.$name.'</a></b></td>';
	echo '<td class="symb">'.$coin->symbol.'</td>';

	echo '<td align="right" class="text-smaller">'.$res1['a'].'</td>';
	echo '<td align="right" class="text-smaller">'.$res2['a'].'</td>';
	echo '<td align="right" class="text-smaller">'.$res3['a'].'</td>';
	echo '<td align="right" class="text-smaller">'.$res4['a'].'</td>';

	echo '</tr>';
}

$others = (new \yii\db\Query())
                ->select(['id','image','symbol','name'])
                ->from('coins')
                ->where(['installed' => 1, 'enable' => 1, 'auto_ready' => 1, 'algo' => $algo])
				->orderBy('symbol ASC')
                ->all();

foreach($others as $item)
{
	if (array_key_exists($item['id'], $main_ids))
		continue;
	echo '<tr class="ssrow">';
	echo '<td width="18px"><img width="16px" src="'.$item['image'].'"></td>';
	echo '<td><b><a href="/site/block?id='.$item['id'].'">'.$item['name'].'</a></b></td>';
	echo '<td class="symb">'.$item['symbol'].'</td>';
	echo '<td colspan="4"></td>';
	echo '</tr>';
}

///////////////////////////////////////////////////////////////////////

$hashrate1 = $memcache->get("history_hashrate1-$algo");
if (empty($hashrate1)) {
	$hashrate1 = (new \yii\db\Query())
			->select(['AVG(hashrate)'])
			->from('hashrate')
			->where(['algo' => $algo])
			->andWhere(['>', 'time', $t1])
			->scalar();
	$memcache->set("history_hashrate1-$algo", $hashrate1, $cachetime);
}

$hashrate2 = $memcache->get("history_hashrate2-$algo");
if (empty($hashrate2)) {
	$hashrate2 = (new \yii\db\Query())
			->select(['AVG(hashrate)'])
			->from('hashrate')
			->where(['algo' => $algo])
			->andWhere(['>', 'time', $t2])
			->scalar();
	$memcache->set("history_hashrate2-$algo", $hashrate2, $cachetime);
}

$hashrate3 = $memcache->get("history_hashrate3-$algo");
if (empty($hashrate3)) {
	$hashrate3 = (new \yii\db\Query())
			->select(['AVG(hashrate)'])
			->from('hashrate')
			->where(['algo' => $algo])
			->andWhere(['>', 'time', $t3])
			->scalar();
	$memcache->set("history_hashrate3-$algo", $hashrate3, $cachetime);
}

$hashrate4 = $memcache->get("history_hashrate4-$algo");
if (empty($hashrate4)) {
	$hashrate4 = (new \yii\db\Query())
			->select(['AVG(hashrate)'])
			->from('hashrate')
			->where(['algo' => $algo])
			->andWhere(['>', 'time', $t4])
			->scalar();
	$memcache->set("history_hashrate4-$algo", $hashrate4, $cachetime);
}

$hashrate1 = max($hashrate1 , 1);
$hashrate2 = max($hashrate2 , 1);
$hashrate3 = max($hashrate3 , 1);
$hashrate4 = max($hashrate4 , 1);

$btcmhday1 = Yii::$app->ConversionUtils->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 24 * 1000);
$btcmhday2 = Yii::$app->ConversionUtils->mbitcoinvaluetoa($total2 / $hashrate2 * 1000000 * 1 * 1000);
$btcmhday3 = Yii::$app->ConversionUtils->mbitcoinvaluetoa($total3 / $hashrate3 * 1000000 / 7 * 1000);
$btcmhday4 = Yii::$app->ConversionUtils->mbitcoinvaluetoa($total4 / $hashrate4 * 1000000 / 30 * 1000);

$hashrate1 = Yii::$app->ConversionUtils->Itoa2($hashrate1);
$hashrate2 = Yii::$app->ConversionUtils->Itoa2($hashrate2);
$hashrate3 = Yii::$app->ConversionUtils->Itoa2($hashrate3);
$hashrate4 = Yii::$app->ConversionUtils->Itoa2($hashrate4);

$total1 = Yii::$app->ConversionUtils->bitcoinvaluetoa($total1);
$total2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($total2);
$total3 = Yii::$app->ConversionUtils->bitcoinvaluetoa($total3);
$total4 = Yii::$app->ConversionUtils->bitcoinvaluetoa($total4);

echo '<tr class="ssrow" class="border-top-2">';
echo '<td width="18px"><img width="16px" src="/images/btc.png"></td>';
echo '<td colspan="2"><b>BTC Value</b></td>';

echo '<td align="right" class="text-smaller">'.$total1.'</td>';
echo '<td align="right" class="text-smaller">'.$total2.'</td>';
echo '<td align="right" class="text-smaller">'.$total3.'</td>';
echo '<td align="right" class="text-smaller">'.$total4.'</td>';

echo "</tr>";

///////////////////////////////////////////////////////////////////////

echo '<tr class="ssrow" class="border-top-2">';
echo '<td width="18px"></td>';
echo '<td colspan="2"><b>Avg Hashrate</b></td>';

echo '<td align="right" class="text-smaller">'.$hashrate1.'h/s</td>';
echo '<td align="right" class="text-smaller">'.$hashrate2.'h/s</td>';
echo '<td align="right" class="text-smaller">'.$hashrate3.'h/s</td>';
echo '<td align="right" class="text-smaller">'.$hashrate4.'h/s</td>';

echo '</tr>';

///////////////////////////////////////////////////////////////////////

echo '<tr class="ssrow" class="border-top-2">';
echo '<td width="18px"></td>';
echo '<td colspan="2"><b>mBTC/'.$algo_unit.'/d</b></td>';

echo '<td align="right" class="text-smaller">'.$btcmhday1.'</td>';
echo '<td align="right" class="text-smaller">'.$btcmhday2.'</td>';
echo '<td align="right" class="text-smaller">'.$btcmhday3.'</td>';
echo '<td align="right" class="text-smaller">'.$btcmhday4.'</td>';

echo '</tr>';

echo '</table>';

echo "</div></div><br />";