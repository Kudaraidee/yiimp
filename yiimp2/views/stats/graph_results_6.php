<?php

use app\models\Hashstats;
use Yii;

// Set JSON response header
Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

$algo = Yii::$app->YiimpUtils->getCurrentAlgo();
$t = time() - 7*24*60*60;
$interval = 4*60*60; // 4 hours

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$data = Hashstats::getAggregatedBtcPerUnitData($algo, $t, $interval, $algo_unit_factor);

return $data;


