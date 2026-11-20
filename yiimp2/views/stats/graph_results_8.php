<?php

$algo = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'all');

$s = 24*60*60;
$t = time() - 60*24*60*60;
$stats = \app\models\HashStats::find()
    ->where(['>', 'time', $t])
    ->andWhere(['algo' => $algo])
    ->orderBy(['time' => SORT_ASC])
    ->all();

$res = array();
$first = 0;
foreach($stats as $n)
{
	$i = floor($n->time/$s)*$s;
	if(!$first) $first = $i;

	if(!isset($res[$i]))
		$res[$i] = 0;

	$res[$i] += $n->earnings;
}

echo '[';

foreach($res as $i=>$n)
{
	if($i != $first) echo ',';
	$d = date('Y-m-d H:i:s', $i);
	echo "[\"$d\",$n]";
}

echo ']';


