<?php

use app\models\Hashstats;
use Yii;

// Set JSON response header
Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

$algo = Yii::$app->YiimpUtils->getCurrentAlgo();
$t = time() - 7*24*60*60;
$interval = 4*60*60; // 4 hours

$data = Hashstats::getAggregatedEarningsData($algo, $t, $interval, 8);

return $data;


