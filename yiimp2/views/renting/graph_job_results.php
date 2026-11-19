<?php
use app\models\HashRenter;
use app\services\StatsService;

$jobId  = (int) Yii::$app->request->get('jobid', 0);
$step   = 15 * 60;
$t      = time() - 86400;
$pct    = 16;
$stats  = new StatsService();

$rows   = HashRenter::find()
    ->where(['jobid' => $jobId])
    ->andWhere(['>', 'time', $t])
    ->orderBy('time')
    ->all();

// Build accepted series
$series1  = [];
$series2  = [];
$series3  = [];
$averages = [];
$j        = 0;

for ($i = $t + $step; $i < time(); $i += $step) {
    $comma = ($i !== $t + $step) ? ',' : '';
    $m     = 0;

    if ($i + $step >= time()) {
        $m = round($stats->jobRate($jobId) / 1_000_000, 3);
    } elseif (isset($rows[$j]) && $i > $rows[$j]->time) {
        $m = round($rows[$j]->hashrate / 1_000_000, 3);
        $j++;
    }

    $d         = date('Y-m-d H:i:s', $i);
    $series1[] = "[\"$d\",$m]";
    $averages[] = [$d, $m];
}

// Smoothed accepted series
$avg = $averages ? $averages[0][1] : 0;
foreach ($averages as $item) {
    $avg       = ($avg * (100 - $pct) + $item[1] * $pct) / 100;
    $series2[] = "[\"{$item[0]}\"," . round($avg, 3) . ']';
}

// Rejected series
$j = 0;
for ($i = $t + $step; $i < time(); $i += $step) {
    $m = 0;
    if ($i + $step >= time()) {
        $m = round($stats->jobRateBad($jobId) / 1_000_000, 3);
    } elseif (isset($rows[$j]) && $i > $rows[$j]->time) {
        $m = round($rows[$j]->hashrate_bad / 1_000_000, 3);
        $j++;
    }
    $d         = date('Y-m-d H:i:s', $i);
    $series3[] = "[\"$d\",$m]";
}

echo '[[' . implode(',', $series1) . '],[' . implode(',', $series2) . '],[' . implode(',', $series3) . ']]';
