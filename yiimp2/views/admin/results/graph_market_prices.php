<?php

use app\models\Coins;
use app\models\Markets;
use app\models\Market_history;

if (!$id) return;

$coin = Coins::findOne(['id' => $id]);
if (!$coin) return;

$t = time() - 7*24*60*60;

$markets = (new \yii\db\Query())
				->select(['markets.id', 'markets.name', 'markets.priority, MIN(market_history.price) AS min, MAX(market_history.price) AS max'])
				->from('market_history')
                ->innerJoin('markets', 'markets.id = market_history.idmarket')
				->where(['market_history.idcoin' => $id, 'markets.disabled' => 0])
				->andWhere(['>', 'market_history.time' , $t])
				->andWhere(['!=', 'markets.name' , 'stake'])
				->groupBy('markets.id, markets.name, markets.priority')
				->having('max > 0')
				->orderBy('markets.priority DESC, markets.name')
				->all();


$min = 999999999;
$max = 0;

$series = array();
foreach ($markets as $m) {

	$market = Markets::findOne(['id' => $m['id']]);

	$stats = Market_history::find()
				->where(['idmarket' => $market->id])
				->andWhere(['>', 'time' , $t])
				->orderBy('time')
				->all();

	foreach($stats as $histo)
	{
		$d = date('Y-m-d H:i', $histo->time);
		$series[$m['name']][] = array($d, (double) Yii::$app->ConversionUtils->bitcoinvaluetoa($histo->price));
	}

	if ($histo && $market->pricetime && $market->pricetime > $histo->time) {
		$d = date('Y-m-d H:i', $market->pricetime);
		$series[$m['name']][] = array($d, (double) Yii::$app->ConversionUtils->bitcoinvaluetoa($market->price));
	}

	$min = min($min, (double) $m['min']);
	$max = max($max, (double) $m['max']);
}

if ($min == 999999999) {
	// empty
	$min = 0;
}

// "yiimp" price

$stats = $stats = Market_history::find()
				->where(['idcoin' => $id, 'idmarket' => null])
				->andWhere(['>', 'time' , $t])
				->orderBy('time')
				->all();
foreach($stats as $histo) {
	$d = date('Y-m-d H:i', $histo->time);
	$series[YIIMP_SITE_NAME][] = array($d, (double) Yii::$app->ConversionUtils->bitcoinvaluetoa($histo->price));
	$max = max($max, $histo->price);
}

echo json_encode(array(
	'data'=>array_values($series),
	'labels'=>array_keys($series),
	'rangeMin'=> (double) ($min * 0.95),
	'rangeMax'=> (double) ($max * 1.05),
));
