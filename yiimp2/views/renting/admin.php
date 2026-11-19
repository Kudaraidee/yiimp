<?php
use yii\helpers\Html;
use app\models\Renters;
use app\models\RenterTxs;
use app\models\Jobs;
use app\services\StatsService;

$this->title = 'Renting Admin';
$cu    = Yii::$app->ConversionUtils;
$stats = new StatsService();
$myDeposit = Yii::$app->session->get('renting-deposit', '');
?>
<?= Html::a('refresh', ['/renting/admin']) ?><br>

<table class="dataGrid">
<thead><tr>
  <th>ID</th><th>Address</th><th>Time</th><th>Type</th><th>Amount</th><th>Tx</th>
</tr></thead>
<?php foreach (RenterTxs::find()->orderBy(['time' => SORT_DESC])->limit(10)->all() as $tx):
    $renter = Renters::findOne($tx->renterid);
    if (!$renter) continue;
    $d      = $cu->datetoa2($tx->time) . ' ago';
    $amount = $cu->bitcoinvaluetoa($tx->amount);
    $txHash = strlen($tx->tx ?? '') > 32
        ? Html::a(substr($tx->tx, 0, 36) . '…', "https://blockchain.info/tx/{$tx->tx}", ['target' => '_blank'])
        : Html::encode($tx->tx ?? '');
?>
<tr class="ssrow">
  <td><?= $renter->id ?></td>
  <td><?= Html::a(Html::encode($renter->address), ['/renting', 'address' => $renter->address]) ?></td>
  <td><b><?= $d ?></b></td>
  <td title="<?= Html::encode($tx->address ?? '') ?>"><?= Html::encode($tx->type) ?></td>
  <td><b><?= $amount ?></b></td>
  <td style="font-family:monospace;"><?= $txHash ?></td>
</tr>
<?php endforeach; ?>
</table><br>

<table class="dataGrid">
<thead><tr>
  <th>ID</th><th>Address</th><th>Email</th>
  <th>Spent</th><th>Balance</th><th>Unconfirmed</th><th>Jobs</th><th>Active</th>
</tr></thead><tbody>
<?php foreach (Renters::find()->where(['>', 'balance', 0])->orderBy(['balance' => SORT_DESC])->all() as $renter):
    $count  = Jobs::find()->where(['renterid' => $renter->id])->count();
    $active = Jobs::find()->where(['renterid' => $renter->id, 'active' => 1])->count();
?>
<tr class="ssrow"<?= $myDeposit === $renter->address ? ' style="background-color:#dfd;"' : '' ?>>
  <td><?= $renter->id ?></td>
  <td><?= Html::a(Html::encode($renter->address), ['/renting', 'address' => $renter->address]) ?></td>
  <td><?= Html::encode($renter->email ?? '') ?></td>
  <td><?= $renter->spent ?></td>
  <td><?= $renter->balance ?></td>
  <td><?= $renter->unconfirmed ?></td>
  <td><?= $count ?></td>
  <td><?= $active ?></td>
</tr>
<?php endforeach; ?>
</tbody></table><br>

<table class="dataGrid">
<thead><tr>
  <th>Renter</th><th>Job</th><th>Address</th><th>Algo</th><th>Host</th>
  <th>Max Price</th><th>Max Hash</th><th>Current Hash</th><th>Diff</th><th>Ready</th><th>Active</th>
</tr></thead><tbody>
<?php foreach (Jobs::find()->where(['ready' => 1])->all() as $job):
    $renter   = Renters::findOne($job->renterid);
    if (!$renter) continue;
    $hashrate = $stats->jobRate($job->id);
    $hashStr  = $hashrate ? $cu->Itoa2($hashrate) . 'h/s' : '';
    $speedStr = $cu->Itoa2($job->speed) . 'h/s';
?>
<tr class="ssrow"<?= $myDeposit === $renter->address ? ' style="background-color:#dfd;"' : '' ?>>
  <td><?= $job->renterid ?></td>
  <td><?= $job->id ?></td>
  <td><?= Html::a(Html::encode($renter->address), ['/renting', 'address' => $renter->address]) ?></td>
  <td><?= Html::a(Html::encode($job->algo), ['/site/mining', 'algo' => $job->algo]) ?></td>
  <td><?= Html::encode("{$job->host}:{$job->port}") ?></td>
  <td><?= $job->price ?></td>
  <td><?= $speedStr ?></td>
  <td><?= $hashStr ?></td>
  <td><?= $job->difficulty ?></td>
  <td><?= $job->ready ?></td>
  <td><?= $job->active ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
