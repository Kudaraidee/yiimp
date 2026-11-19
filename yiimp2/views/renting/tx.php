<?php
/**
 * @var yii\web\View       $this
 * @var app\models\Renters $renter
 * @var array              $txs    from WalletRPC::listtransactions()
 */
use yii\helpers\Html;

$this->title = $renter->address . ' | transactions';
$cu    = Yii::$app->ConversionUtils;
$total = 0;

$res = [];
foreach ($txs as $tx) {
    $ts = $tx['time'] ?? 0;
    if ($ts < $renter->created) continue;
    $res[$ts] = $tx;
}
krsort($res);
?>
<div class="main-left-box">
<div class="main-left-title">Transactions from <?= Html::encode($renter->address) ?></div>
<div class="main-left-inner">
<table class="dataGrid2">
<thead><tr><th>Time</th><th>Amount</th><th>Confirmations</th><th>Tx</th></tr></thead>
<tbody>
<?php foreach ($res as $transaction):
    if (($transaction['category'] ?? '') !== 'receive') continue;
    $d = $cu->datetoa2($transaction['time']);
    $total += $transaction['amount'] ?? 0;
?>
<tr class="ssrow">
  <td><b><?= $d ?></b></td>
  <td><?= Html::encode($transaction['amount'] ?? '') ?></td>
  <td><?= Html::encode($transaction['confirmations'] ?? '') ?></td>
  <td style="font-family:monospace;">
    <?php if (!empty($transaction['txid'])): ?>
    <?= Html::a(Html::encode($transaction['txid']), "https://blockchain.info/tx/{$transaction['txid']}", ['target' => '_blank']) ?>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
<tr><td>total</td><td colspan="3"><?= $total ?></td></tr>
</table>
</div>
</div>
