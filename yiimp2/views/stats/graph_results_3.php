<?php

use app\models\Hashstats;
use Yii;

// Set JSON response header
Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

$algo = Yii::$app->YiimpUtils->getCurrentAlgo();
$t = time() - 48*60*60;

$algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$data = Hashstats::getBtcPerUnitChartData($algo, $t, $algo_unit_factor);

return $data;

