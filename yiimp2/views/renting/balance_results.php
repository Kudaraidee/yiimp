<?php
/**
 * @var yii\web\View       $this
 * @var app\models\Renters $renter
 * @var bool               $isAdmin
 */
use yii\helpers\Html;
use app\models\RenterTxs;

$cu          = Yii::$app->ConversionUtils;
$balance     = $cu->bitcoinvaluetoa($renter->balance);
$unconfirmed = $cu->bitcoinvaluetoa($renter->unconfirmed);
$spent       = $cu->bitcoinvaluetoa($renter->spent);
?>
<div class="main-left-box">
<div class="main-left-title">Deposit <?= Html::encode($renter->address) ?></div>
<div class="main-left-inner">

<?php if (defined('YIIMP_RENTAL') && !YIIMP_RENTAL): ?>
<p style="font-size:1.2em; font-weight:bold; color:red;">Renting is temporarily disabled.</p>
<?php endif; ?>

<table cellspacing="10">
<tr>
  <td>Deposit Address</td>
  <td colspan="2"><code style="background:#eee;"><?= Html::encode($renter->address) ?></code></td>
</tr>
<tr>
  <td>Balance</td>
  <td><a href="javascript:main_renter_tx()"><?= $balance ?> BTC</a></td>
  <td>
    <?php if ($renter->balance >= 0.001): ?>
    <input type="button" value="Withdraw" class="main-submit-button" onclick="yaamp_withdraw()">
    <?php elseif ($renter->balance > 0): ?>
    <span style="font-size:.8em;">(withdraw minimum 0.001)</span>
    <?php endif; ?>
  </td>
</tr>
<?php if ($unconfirmed > 0): ?>
<tr>
  <td>Unconfirmed</td>
  <td><?= $unconfirmed ?> BTC <span style="font-size:.8em;">(waiting for 1 confirmation)</span></td>
</tr>
<?php endif; ?>
<?php if ($isAdmin): ?>
<tr>
  <td>Spent</td>
  <td><?= $spent ?> BTC</td>
  <td><input type="button" value="Reset" class="main-submit-button" onclick="reset_spent()"></td>
</tr>
<?php endif; ?>
</table><br>

<?= Html::a('<b>Settings</b>', ['/renting/settings'], ['style' => 'margin:10px;']) ?>
<?= Html::a('<b>Logout</b>', ['/renting/logout']) ?>

<br>
</div>
</div><br>

<?php
$txs = RenterTxs::find()
    ->where(['renterid' => $renter->id])
    ->orderBy(['time' => SORT_DESC])
    ->limit(5)
    ->all();
if (!$txs) return;
?>

<div class="main-left-box">
<div class="main-left-title">Last 5 transactions</div>
<div class="main-left-inner">
<table class="dataGrid2">
<thead><tr><th>Time</th><th>Type</th><th>Amount</th><th>Tx</th></tr></thead>
<tbody>
<?php foreach ($txs as $tx):
    $txAmt = $cu->bitcoinvaluetoa($tx->amount);
    $txAge = $cu->datetoa2($tx->time) . ' ago';
    $txHash = Html::encode($tx->tx ?? '');
    $txUrl  = strlen($tx->tx ?? '') > 32
        ? Html::a(substr($tx->tx, 0, 36) . '…', "https://blockchain.info/tx/{$tx->tx}", ['target' => '_blank'])
        : $txHash;
?>
<tr class="ssrow">
  <td><b><?= $txAge ?></b></td>
  <td title="<?= Html::encode($tx->address ?? '') ?>"><?= Html::encode($tx->type) ?></td>
  <td><b><?= $txAmt ?></b></td>
  <td style="font-family:monospace;"><?= $txUrl ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div><br>
