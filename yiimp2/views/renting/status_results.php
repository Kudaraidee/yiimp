<?php
use yii\helpers\Html;
use app\models\Jobs;

$cu       = Yii::$app->ConversionUtils;
$utils    = Yii::$app->YiimpUtils;
$algo     = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'x11');
$fee      = defined('YIIMP_FEES_RENTING') ? (float) YIIMP_FEES_RENTING : 0.0;
$db       = Yii::$app->db;

$algos = [];
foreach ($utils->get_algos() as $algoName) {
    $price    = (float) $db->createCommand(
        "SELECT price FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $algoName]
    )->queryScalar();
    $algoNorm = (float) $utils->get_algo_norm($algoName);
    $norm     = $price * $algoNorm * (1.0 - $fee / 100.0);
    $algos[]  = [$norm, $algoName];
}

usort($algos, fn($a, $b) => $b[0] <=> $a[0]);

Yii::$app->ViewUtils->showTableSorter('maintable3');
?>
<thead><tr>
  <th>Algo</th>
  <th style="text-align:right">Jobs</th>
  <th style="text-align:right">Total</th>
  <th style="text-align:right">Rented</th>
  <th></th>
  <th style="text-align:right">Available</th>
  <th style="text-align:right">Current Price</th>
</tr></thead>
<?php foreach ($algos as [$norm, $algoName]):
    $count1 = (int) Jobs::find()->where(['algo' => $algoName, 'ready' => 1, 'active' => 1])->count();
    $count2 = (int) Jobs::find()->where(['algo' => $algoName, 'ready' => 1])->count();

    $total       = $utils->pool_rate($algoName);
    $rentable    = $utils->pool_rate_rentable($algoName);
    $rented      = $utils->rented_rate($algoName);
    $rentable    = min($total, $rentable);
    $rented      = min($rentable, $rented);
    $available   = $rentable - $rented;
    $percent     = ($rented && $rentable) ? '(' . round($rented / $rentable * 100, 1) . '%)' : '';

    $rentedStr    = $rented    > 0 ? $cu->Itoa2($rented)    . 'h/s' : '';
    $availableStr = $available > 0 ? $cu->Itoa2($available) . 'h/s' : '';
    $hashStr      = $rentable  > 0 ? $cu->Itoa2($rentable)  . 'h/s' : '';

    $renting = $cu->mbitcoinvaluetoa((float) Yii::$app->db->createCommand(
        "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $algoName]
    )->queryScalar());
?>
<tr class="ssrow" style="cursor:pointer<?= $algo === $algoName ? '; background-color:#e0d3e8;' : '' ?>"
    onclick="window.location.href='/site/algo?algo=<?= Html::encode($algoName) ?>'">
  <td><b><?= Html::encode($algoName) ?></b></td>
  <td style="text-align:right; font-size:.9em;"><?= $count1 ?> / <?= $count2 ?></td>
  <td style="text-align:right; font-size:.9em;"><?= $hashStr ?></td>
  <td style="text-align:right; font-size:.9em;"><?= $rentedStr ?></td>
  <td style="text-align:right; font-size:.8em;"><?= $percent ?></td>
  <td style="text-align:right; font-size:.9em;"><?= $availableStr ?></td>
  <td style="text-align:right; font-size:.9em;"><b><?= $renting ?></b></td>
</tr>
<?php endforeach; ?>
</table>
<p style="font-size:.8em;">* values in mBTC/MH/day (GH/day for sha and blake algos)<br>
** only hashpower with extranonce.subscribe or reconnect support can be rented</p>
