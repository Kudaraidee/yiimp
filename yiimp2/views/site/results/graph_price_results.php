<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */
/** @var string[] $algos */

use app\models\Hashrate;

/* Graph shown in Pool tab, last 24h estimate (hashrate-weighted in All scope) */

$percent = 16;
$algo    = (string) Yii::$app->session->get('yaamp-algo', 'all');
$algos   = (!empty($algos) && is_array($algos)) ? $algos : [$algo];
$isAll   = ($algo === 'all');
$conv    = Yii::$app->ConversionUtils;

$step  = 15 * 60;
$end   = intval(time() / $step) * $step;
$start = $end - 94 * $step; // 95 buckets

$stats = Hashrate::find()
    ->select(['time', 'price', 'hashrate'])
    ->where(['algo' => $algos])
    ->andWhere(['>=', 'time', $start])
    ->orderBy('time')
    ->all();

$sums    = [];
$weights = [];
foreach ($stats as $n) {
    $ts = intval($n->time / $step) * $step;
    if ($isAll) {
        $hr = (float) $n->hashrate;
        $sums[$ts]    = ($sums[$ts] ?? 0.0) + (float) $n->price * $hr;
        $weights[$ts] = ($weights[$ts] ?? 0.0) + $hr;
    } else {
        $sums[$ts]    = (float) $n->price;
        $weights[$ts] = 1.0;
    }
}
ksort($sums);

$firstVal = 0.0;
if ($sums) {
    $firstTs  = array_key_first($sums);
    $firstVal = $weights[$firstTs] > 0 ? $sums[$firstTs] / $weights[$firstTs] : 0.0;
}

$last     = $sums ? max(array_keys($sums)) : $start;
$averages = [];
for ($ts = $start; $ts <= $last; $ts += $step) {
    if (isset($sums[$ts]) && $weights[$ts] > 0) {
        $value = $sums[$ts] / $weights[$ts];
    } else {
        $value = $firstVal;
    }
    $m = (float) $conv->altcoinvaluetoa($value);
    $averages[] = [date('Y-m-d H:i:s', $ts), $m];
}

if (count($averages) < 2) {
    $averages[] = $averages[0];
}

$avg2    = [];
$average = $averages[0][1];
foreach ($averages as $n) {
    $average = ($average * (100 - $percent) + $n[1] * $percent) / 100;
    $avg2[]  = [$n[0], round($average, 5)];
}

echo '[' . json_encode($averages) . ",\n" . json_encode($avg2) . ']';
