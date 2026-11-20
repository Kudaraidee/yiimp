<?php

$algo = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'all');

$t = time() - 48*60*60;
$stats = \app\models\HashStats::find()
    ->where(['>', 'time', $t])
    ->andWhere(['algo' => $algo])
    ->orderBy(['time' => SORT_ASC])
    ->all();

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$data = array();

foreach($stats as $i=>$n)
{
	$m = round($n->hashrate/(1000000*$algo_unit_factor), 3);

	$d = date('Y-m-d H:i:s', $n->time);
	$data[] = array($d, $m);
}

echo json_encode($data);
