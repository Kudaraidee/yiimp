<?php
use app\models\Hashrate;

$algo   = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'x11');
$step   = 15 * 60;
$t      = time() - 86400;
$pct    = 16;

$stats  = Hashrate::find()
    ->where(['algo' => $algo])
    ->andWhere(['>', 'time', $t])
    ->orderBy('time')
    ->all();

$series1   = [];
$averages  = [];
$cursor    = $t;

// Pad with zeroes up to the first data point
for ($i = 0; $i < 95 - count($stats); $i++) {
    $d         = date('Y-m-d H:i:s', $cursor);
    $series1[] = "[\"$d\",0]";
    $averages[] = [$d, 0];
    $cursor   += $step;
}

foreach ($stats as $i => $row) {
    $m         = (float) ($row->rent ?? 0);
    $d         = date('Y-m-d H:i:s', $row->time);
    $series1[] = "[\"$d\",$m]";
    $averages[] = [$d, $m];
}

$avg     = $averages ? $averages[0][1] : 0;
$series2 = [];
foreach ($averages as $item) {
    $avg       = ($avg * (100 - $pct) + $item[1] * $pct) / 100;
    $m         = round($avg, 5);
    $series2[] = "[\"{$item[0]}\",$m]";
}

echo '[[' . implode(',', $series1) . '],[' . implode(',', $series2) . ']]';
