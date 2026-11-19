<?php
/**
 * @var yii\web\View            $this
 * @var app\models\Renters|null $renter  current renter (may be null for guests)
 * @var bool                    $isAdmin
 */
use yii\helpers\Html;
use app\models\Jobs;
use app\services\StatsService;

$cu    = Yii::$app->ConversionUtils;
$stats = new StatsService();
$algo  = Yii::$app->session->get('yaamp-algo', defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'x11');

$rent = $cu->mbitcoinvaluetoa((float) Yii::$app->db->createCommand(
    "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $algo]
)->queryScalar());

$jobs = Jobs::find()
    ->where(['ready' => 1, 'algo' => $algo])
    ->orderBy('price DESC, time')
    ->all();
?>
<div class="main-left-box">
<div class="main-left-title">All started jobs (<?= Html::encode($algo) ?>) — Current Price <?= $rent ?></div>
<div class="main-left-inner">
<?php Yii::$app->ViewUtils->showTableSorter('maintable1'); ?>
<thead><tr>
  <th>Server</th>
  <th style="text-align:right">Max Price</th>
  <th style="text-align:right">Max Hash</th>
  <th style="text-align:right">Hash*</th>
  <th style="text-align:right">Diff</th>
</tr></thead><tbody>
<?php foreach ($jobs as $job):
    $rateBad = $stats->jobRateBad($job->id);
    $rate    = $stats->jobRate($job->id) + $rateBad;
    $titlePct = $rateBad
        ? 'Rejected ' . $cu->Itoa2($rateBad) . 'h/s (' . round($rateBad / $rate * 100, 1) . '%)'
        : '';
    $rateStr    = $rate        ? $cu->Itoa2($rate)        . 'h/s' : '';
    $maxHashStr = $job->speed  ? $cu->Itoa2($job->speed)  . 'h/s' : '';
    $priceStr   = $cu->mbitcoinvaluetoa($job->price);
    $diff       = $job->difficulty > 0 ? round($job->difficulty, 3) : '';
    $isOwn      = $renter && $renter->id == $job->renterid;
    $title      = $isAdmin ? "-o stratum+tcp://{$job->host}:{$job->port} -u {$job->username} -p {$job->password}" : '';
?>
<tr class="ssrow"<?= $job->active ? ' style="background-color:#dfd;"' : '' ?>>
  <td title="<?= Html::encode($title) ?>"><?= $isOwn ? Html::encode($job->host) : '' ?></td>
  <td style="text-align:right"><?= $priceStr ?></td>
  <td style="text-align:right"><?= $maxHashStr ?></td>
  <td style="text-align:right" title="<?= Html::encode($titlePct) ?>"><?= $rateStr ?></td>
  <td style="text-align:right"><?= Html::encode($diff) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p style="font-size:.8em;">* approximate from the last 5 minutes submitted shares</p>
</div>
</div>
