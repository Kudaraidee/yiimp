<?php
/**
 * @var yii\web\View       $this
 * @var app\models\Renters $renter
 * @var bool               $isAdmin
 */
use yii\helpers\Html;
use app\models\Jobs;
use app\services\StatsService;

$cu      = Yii::$app->ConversionUtils;
$stats   = new StatsService();
$rental  = defined('YIIMP_RENTAL') && YIIMP_RENTAL;
$jobs    = Jobs::find()->where(['renterid' => $renter->id])->orderBy('algo, price DESC')->all();

Yii::$app->ViewUtils->showTableSorter('maintable2');
?>
<thead><tr>
  <th></th><th>Server</th><th>Algo</th>
  <th style="text-align:right">Max Price</th>
  <th style="text-align:right">Max Hash</th>
  <th style="text-align:right">Hash*</th>
  <th style="text-align:right">Diff</th>
  <th></th>
</tr></thead><tbody>
<?php
foreach ($jobs as $job):
    $rateBad  = $stats->jobRateBad($job->id);
    $rate     = $stats->jobRate($job->id) + $rateBad;
    $titlePct = '';
    if ($rateBad) {
        $pct       = $rate ? round($rateBad / $rate * 100, 1) . '%' : '100%';
        $titlePct  = 'Rejected ' . $cu->Itoa2($rateBad) . 'h/s (' . $pct . ')';
    }
    $rateStr    = $rate    ? $cu->Itoa2($rate)    . 'h/s' : '';
    $maxHashStr = $job->speed ? $cu->Itoa2($job->speed) . 'h/s' : '';
    $title      = "-o stratum+tcp://{$job->host}:{$job->port} -u {$job->username} -p {$job->password}";
    $serverName = Html::encode(substr($job->host, 0, 22));
    $price      = $cu->mbitcoinvaluetoa($job->price);
    $rent       = $cu->mbitcoinvaluetoa((float) Yii::$app->db->createCommand(
        "SELECT rent FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $job->algo]
    )->queryScalar());
    $diff       = $job->difficulty > 0 ? round($job->difficulty, 3) : '';
?>
<tr class="ssrow"<?= $job->active ? ' style="background-color:#dfd;"' : '' ?>>
  <td title="Show details">
    <a href="javascript:show_job_graph(<?= $job->id ?>)">
      <img id="graph_toggle_job-<?= $job->id ?>" width="14" src="/images/plus2-78.png">
    </a>
  </td>
  <td title="<?= Html::encode($title) ?>"><?= $serverName ?></td>
  <td><?= Html::encode($job->algo) ?></td>
  <td style="text-align:right" title="Current Price <?= $rent ?>"><?= $price ?><?= $job->percent ? " ({$job->percent}%)" : '' ?></td>
  <td style="text-align:right"><?= $maxHashStr ?></td>
  <td style="text-align:right" title="<?= Html::encode($titlePct) ?>"><?= $rateStr ?></td>
  <td style="text-align:right"><?= Html::encode($diff) ?></td>
  <td>
    <?php if ($rental): ?>
      <?php if ($job->ready): ?>
        <a title="pause" href="/renting/jobs_stop?id=<?= $job->id ?>"><img height="16" src="/images/base/pause.png"></a>
      <?php else: ?>
        <a title="start" href="/renting/jobs_start?id=<?= $job->id ?>"><img height="16" src="/images/base/play.png"></a>
      <?php endif; ?>
    <?php endif; ?>
    &nbsp;&nbsp;<a title="edit" href="javascript:order_edit(<?= $job->id ?>)"><img height="16" src="/images/base/edit.png"></a>
  </td>
</tr>
<tr id="graph_placeholder_job-<?= $job->id ?>" style="display:none;"><td colspan="8">
  <div id="graph_results_job-<?= $job->id ?>" style="height:240px;"></div>
</td></tr>
<?php endforeach; ?>
</tbody></table>

<?php if (count($jobs) <= 20 && $renter->balance > 0): ?>
<br><button class="main-submit-button" onclick="order_new()">New Job</button>
<?php elseif ($renter->balance <= 0): ?>
<p style="padding:10px;">Fund your account by sending bitcoin to your deposit address before creating jobs.</p>
<?php endif; ?>

<?php if ($rental): ?>
<button class="main-submit-button" onclick="window.location.href='/renting/jobs_startall'">Start All</button>
<button class="main-submit-button" onclick="window.location.href='/renting/jobs_stopall'">Stop All</button>
<?php endif; ?>

<br>
<div class="main-left-box"><div class="main-left-inner">
<p style="font-size:.8em;">* approximate from the last 5 minutes submitted shares</p>
<p style="font-size:.8em;">** price in mBTC/MH/day (GH/day for sha and blake algos)</p>
</div></div>
