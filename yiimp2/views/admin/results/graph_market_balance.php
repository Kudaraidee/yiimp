<?php

use app\models\Coins;
use app\models\Markets;
use app\models\Market_history;

if (!$id) return;

$coin = Coins::findOne(['id' => $id]);
if (!$coin) return;

$t = time() - 7*24*60*60;

$markets = (new \yii\db\Query())
				->select(['markets.id', 'markets.name', 'markets.priority, MIN(market_history.balance) AS min, MAX(market_history.balance) AS max'])
				->from('market_history')
                ->innerJoin('markets', 'markets.id = market_history.idmarket')
				->where(['market_history.idcoin' => $id, 'markets.disabled' => 0])
				->andWhere(['>', 'market_history.time' , $t])
				->groupBy('markets.id, markets.name, markets.priority')
				->having('max > 0')
				->orderBy('markets.priority DESC, markets.name')
				->all();

$stackedMax = (double) 0;

$series = array();
foreach ($markets as $m) {

	$market = Markets::findOne(['id' => $m['id']]);

	$stats = Market_history::find()
				->where(['idmarket' => $market->id])
				->andWhere(['>', 'time' , $t])
				->orderBy('time')
				->all();

	$max = 0;
	foreach($stats as $histo) {
		$d = date('Y-m-d H:i', $histo->time);
		$series[$m['name']][] = array($d, (double) Yii::$app->ConversionUtils->bitcoinvaluetoa($histo->balance));

		$max = max($max, $histo->balance);
	}

	$stackedMax += $max;
}

// "yiimp" balance

$stats = Market_history::find()
				->where(['idcoin' => $id, 'idmarket' => null])
				->andWhere(['>', 'time' , $t])
				->orderBy('time')
				->all();
//getdbolist('db_market_history', "time>$t AND idcoin={$id} AND idmarket IS NULL ORDER BY time");

$max = 0;
foreach($stats as $histo) {
	$d = date('Y-m-d H:i', $histo->time);
	$series[YIIMP_SITE_NAME][] = array($d, (double) Yii::$app->ConversionUtils->bitcoinvaluetoa($histo->balance));
	$max = max($max, $histo->balance);
}
$stackedMax += $max;

// Stacked graph specific : seems to require same amount of points :/
$max = 0; $seriefull = '';
foreach ($series as $name => $serie) {
	if (count($serie) > $max) $seriefull = $name;
	$max = max($max, count($serie));
}
foreach ($series as $name => $serie) {
	if ($seriefull && count($serie) < $max) {
		$first_dt = $serie[0][0];
		$fill_start = ($first_dt > $series[$seriefull][0][0]);
	}
	for ($i = count($serie), $n = 0; $i < $max; $i++, $n++) {
		if ($seriefull == '') {
			$dt = $serie[0][0];
			array_unshift($series[$name], array($dt, 0));
			continue;
		}
		if ($fill_start) {
			if ($series[$seriefull][$n][0] >= $first_dt) {
				array_unshift($series[$name], array($dt, 0));
				$fill_start = false;
			} else {
				$dt = $series[$seriefull][$n][0];
				array_unshift($series[$name], array($dt, 0));
			}
		} else {
			$dt = $series[$seriefull][$i][0];
			$last = end($series[$name]);
			$series[$name][] = array($dt, $last[1]);
		}
	}
}

echo json_encode(array(
	'data'=>array_values($series),
	'labels'=>array_keys($series),
	'rangeMin'=> (double) 0.0,
	'rangeMax'=> ($stackedMax * 1.10),
));
