<?php

$algo = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'all');

$s = 24*60*60;
$t = time() - 60*24*60*60;
$stats = \app\models\HashStats::find()
    ->where(['>', 'time', $t])
    ->andWhere(['algo' => $algo])
    ->orderBy(['time' => SORT_ASC])
    ->all();

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$res = array();
foreach($stats as $n)
{
	if(!$s) continue;
	$i = floor($n->time/$s)*$s;

	if(!isset($res[$i]))
	{
		$res[$i] = array();
		$res[$i]['earnings'] = 0;
		$res[$i]['hashrate'] = 0;
	}

	$res[$i]['earnings'] += $n->earnings;
	$res[$i]['hashrate'] += $n->hashrate/24;
}

echo '[';

$started = false;
foreach($res as $i=>$n)
{
	if(!$n['hashrate']) continue;

	$m = Yii::$app->ConversionUtils->bitcoinvaluetoa($n['earnings'] * $algo_unit_factor * 1000000 / $n['hashrate']);
	$d = date('Y-m-d H:i:s', $i);

	if($started) echo ',';
	echo "[\"$d\",$m]";

	$started = true;
}

echo ']';


