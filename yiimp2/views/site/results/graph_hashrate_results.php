<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */
/** @var string[] $algos */

use app\models\Hashrate;

/* Graph shown in Pool Tab, Last 24 Hours algo (or all-algo) hashrate */

$percent = 16;
$algo    = (string) Yii::$app->session->get('yaamp-algo', 'all');
$algos   = (!empty($algos) && is_array($algos)) ? $algos : [$algo];
$isAll   = ($algo === 'all');
$factor  = $isAll ? 1 : Yii::$app->YiimpUtils->algo_mBTC_factor($algo);

$step  = 15 * 60;
$end   = intval(time() / $step) * $step;
$start = $end - 94 * $step; // 95 buckets

$stats = Hashrate::find()
    ->select(['time', 'hashrate'])
    ->where(['algo' => $algos])
    ->andWhere(['>=', 'time', $start])
    ->orderBy('time')
    ->all();

// In All scope sum raw H/s across algos. Normal scope keeps its per-algo unit.
$buckets = [];
foreach ($stats as $n) {
    $ts = intval($n->time / $step) * $step;
    if ($isAll) {
        $buckets[$ts] = ($buckets[$ts] ?? 0.0) + (float) $n->hashrate;
    } else {
        $buckets[$ts] = (float) $n->hashrate;
    }
}
ksort($buckets);

$last     = $buckets ? max(array_keys($buckets)) : $start;
$averages = [];
for ($ts = $start; $ts <= $last; $ts += $step) {
    $raw = $buckets[$ts] ?? 0.0;
    $m   = round(($raw / 1000000) / $factor, 3);
    $averages[] = [date('Y-m-d H:i:s', $ts), $m];
}

if (count($averages) < 2) {
    $averages[] = $averages[0];
}

$avg2    = [];
$average = $averages[0][1];
foreach ($averages as $n) {
    $average   = ($average * (100 - $percent) + $n[1] * $percent) / 100;
    $avg2[]    = [$n[0], round($average, 3)];
}

echo '[' . json_encode($averages) . ",\n" . json_encode($avg2) . ']';
