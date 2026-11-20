<?php

$algo = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'all');

$s = 4*60*60;
$t = time() - 7*24*60*60;
$stats = \app\models\HashStats::find()
    ->where(['>', 'time', $t])
    ->andWhere(['algo' => $algo])
    ->orderBy(['time' => SORT_ASC])
    ->all();

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$res = array();
$first = 0;
foreach($stats as $n)
{
	$i = floor($n->time/$s)*$s;
	if(!$first) $first = $i;

	if(!isset($res[$i]))
		$res[$i] = 0;

	$res[$i] += $n->hashrate/4;
}

$data = array();

foreach($res as $i=>$n)
{
	$m = round($n/(1000000*$algo_unit_factor), 3);
	$d = date('Y-m-d H:i:s', $i);
	$data[] = array($d, $m);
}

echo json_encode($data);
