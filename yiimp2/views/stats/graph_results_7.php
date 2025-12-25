<?php

use app\models\Hashstats;
use Yii;

// Set JSON response header
Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

$algo = Yii::$app->YiimpUtils->getCurrentAlgo();
$t = time() - 60*24*60*60;
$interval = 24*60*60; // 24 hours (daily)

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$data = Hashstats::getAggregatedHashrateData($algo, $t, $interval, 1000000 * $algo_unit_factor);

return $data;
