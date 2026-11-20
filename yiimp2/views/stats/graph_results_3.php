<?php

$algo = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'all');

$t = time() - 48*60*60;
$stats = \app\models\HashStats::find()
    ->where(['>', 'time', $t])
    ->andWhere(['algo' => $algo])
    ->orderBy(['time' => SORT_ASC])
    ->all();

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

echo '[';

foreach($stats as $i=>$n)
{
	$m = $n->hashrate ? Yii::$app->ConversionUtils->bitcoinvaluetoa($n->earnings * 24 * $algo_unit_factor * 1000000/$n->hashrate): 0;

	if($i) echo ',';
	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo ']';

