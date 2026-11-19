<?php

use yii\helpers\Html;
use app\models\Coins;
use app\models\Mining;
use app\models\Payouts;

$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;
$util        = Yii::$app->YiimpUtils;
$cache       = Yii::$app->cache;

$mining      = Mining::find()->one();
$defaultAlgo = Yii::$app->session->get('yaamp-algo');
$showDetails = Yii::$app->getRequest()->getQueryParam('showdetails');

$user = $util->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if (!$user) return;

// ── Reference coin ────────────────────────────────────────────────────────────
$refcoin = Coins::findOne((int) $user->coinid);
if (!$refcoin) {
    $invalidMsg = $user->coinid !== null
        ? 'This wallet address is not valid. You will not receive payments using this address.'
        : null;
    $refcoin = Coins::find()->where(['symbol' => 'BTC'])->one();
} else {
    $invalidMsg = null;
}

if (!YIIMP_ALLOW_EXCHANGE && $user->coinid == 6 && $defaultAlgo !== 'sha256') {
    $errMsg = 'This pool does not convert/trade currencies. You will not receive payments using this BTC address.';
    if ($isLegacy) {
        echo "<div style='color:red;padding:10px;'>{$errMsg}</div>";
    } elseif (!$isTailwind) {
        echo "<div class='alert alert-danger small'>{$errMsg}</div>";
    } else {
        echo "<div class='px-4 py-3 mb-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm'>{$errMsg}</div>";
    }
    return;
}

// ── Balance data ──────────────────────────────────────────────────────────────
$confirmed_raw   = $util->convert_earnings_user($user, 'status=1');
$unconfirmed_raw = $util->convert_earnings_user($user, 'status=0');
$total_unsold    = $confirmed_raw + $unconfirmed_raw;

$total_paid_raw  = (float) $cache->getOrSet("wallet_total_paid-{$user->id}", function () use ($user) {
    return (new \yii\db\Query())->select(['SUM(amount)'])->from('payouts')->where(['account_id' => $user->id])->scalar() ?: 0.0;
}, 120);

$balance      = (float) $user->balance;
$total_unpaid = $balance + $total_unsold;
$total_earned = $total_unsold + $balance + $total_paid_raw;

// ── Last 24h payouts ──────────────────────────────────────────────────────────
$t24h    = time() - 86400;
$payouts = Payouts::find()
    ->where(['account_id' => $user->id])
    ->andWhere(['>', 'time', $t24h])
    ->orderBy('time DESC')
    ->all();

$payout24h = array_sum(array_map(fn($p) => (float) $p->amount, $payouts));

// ── Extra payouts ─────────────────────────────────────────────────────────────
$firstId = empty($payouts) ? 999999999 : min(array_map(fn($p) => $p->id, $payouts));
$extraPayouts = Payouts::find()
    ->where(['account_id' => $user->id])
    ->andWhere(['>', 'id', $firstId])
    ->andWhere(['>', 'fee', '0.0'])
    ->orderBy('time DESC')
    ->all();
$extraTotal = array_sum(array_map(fn($p) => (float) $p->amount, $extraPayouts));

// ── Fees notice ───────────────────────────────────────────────────────────────
$feesNotice = '';
if ($user->donation > 0)       $feesNotice = "Currently donating {$user->donation}% of rewards.";
elseif ($user->no_fees == 1)   $feesNotice = 'Currently mining without pool fees.';

// ── Payout minimum note ───────────────────────────────────────────────────────
$payoutMinNote = $refcoin && $refcoin->payout_min
    ? "Minimum payout: {$refcoin->payout_min} " . Html::encode($refcoin->symbol)
    : '';

// ── Summary rows for the balance table ───────────────────────────────────────
$summaryRows = [
    ['Pending (immature)',  $conv->bitcoinvaluetoa($unconfirmed_raw), false],
    ['Pending (confirmed)', $conv->bitcoinvaluetoa($confirmed_raw),   false],
    ['Balance',             $conv->bitcoinvaluetoa($balance),          true],
    ['Total Unpaid',        $conv->bitcoinvaluetoa($total_unpaid),     true],
    ['Total Paid',          $conv->bitcoinvaluetoa($total_paid_raw),   true,  'tx'],
    ['Total Earned',        $conv->bitcoinvaluetoa($total_earned),     true],
];

$sym = $refcoin ? Html::encode($refcoin->symbol) : 'BTC';

?>

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<div class="main-left-box">
<div class="main-left-title">Wallet: <?= Html::encode($user->username) ?></div>
<div class="main-left-inner">

<?php if ($invalidMsg): ?>
<div style="color:red;padding:10px;"><?= Html::encode($invalidMsg) ?></div>
<?php endif ?>

<?php if ($showDetails): ?>
<?= $this->render('_wallet_earnings_detail', ['user' => $user, 'refcoin' => $refcoin]) ?>
<?php else: ?>
<tr><td colspan="6" align="right">
    <label style="font-size:.8em;">
        <input type="checkbox" onclick="main_wallet_refresh_details()"> Show Details
    </label>
</td></tr>
<?php endif ?>

<table class="dataGrid2">
<tbody>
<?php foreach ($summaryRows as [$label, $val, $bold, $action = null]): ?>
<tr class="ssrow" style="border-top:1px solid #eee;">
    <td><img width="16" src="<?= Html::encode($refcoin->image ?? '') ?>"></td>
    <td colspan="4"><b><?= Html::encode($label) ?></b></td>
    <td align="right" style="font-size:.9em;">
        <?php if ($action === 'tx'): ?>
            <a href="javascript:main_wallet_tx()"><?= $bold ? "<b>{$val}</b>" : $val ?> <?= $sym ?></a>
        <?php else: ?>
            <?= $bold ? "<b>{$val}</b>" : $val ?> <?= $sym ?>
        <?php endif ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?php if ($feesNotice): ?>
<p style="font-size:.8em;"><b><?= Html::encode($feesNotice) ?></b></p>
<?php endif ?>
<?php if ($refcoin && $refcoin->symbol === 'BTC' && $mining): ?>
<p style="font-size:.8em;">* approximate from current exchange rates<br>
** bitstamp <b><?= number_format($mining->usdbtc, 2, '.', ' ') ?></b> USD/BTC</p>
<?php endif ?>
<?php if ($payoutMinNote): ?><p style="font-size:.8em;"><b>Note:</b> <?= $payoutMinNote ?></p><?php endif ?>

</div></div><br>

<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Payouts: <?= Html::encode($user->username) ?></div>
<div class="main-left-inner">
<table class="dataGrid2">
<thead><tr>
    <th align="right">Time</th><th align="right">Amount</th><th>Tx</th>
</tr></thead>
<tbody>
<?php foreach ($payouts as $p): ?>
<tr class="ssrow">
    <td align="right"><b><?= $conv->datetoa2($p->time) ?> ago</b></td>
    <td align="right"><b><?= $conv->bitcoinvaluetoa($p->amount) ?></b></td>
    <td style="font-family:monospace;"><?= $refcoin->createExplorerLink(substr($p->tx, 0, 36) . '...', ['txid' => $p->tx], [], true) ?></td>
</tr>
<?php endforeach ?>
<tr class="ssrow">
    <td align="right">Total:</td>
    <td align="right"><b><?= $conv->bitcoinvaluetoa($payout24h) ?></b></td>
    <td></td>
</tr>
</tbody>
</table>
<?php if (!empty($extraPayouts)): ?>
<table class="dataGrid2">
<tr class="ssrow" style="color:darkred;"><th colspan="3"><b>Extra payouts in last 24h</b></th></tr>
<tr class="ssrow"><th align="right">Time</th><th align="right">Amount</th><th>Tx</th></tr>
<?php foreach ($extraPayouts as $p): ?>
<tr class="ssrow"><td align="right"><b><?= $conv->datetoa2($p->time) ?> ago</b></td>
<td align="right"><b><?= $conv->bitcoinvaluetoa($p->amount) ?></b></td>
<td style="font-family:monospace;"><?= $refcoin->createExplorerLink(substr($p->tx, 0, 36) . '...', ['txid' => $p->tx], [], true) ?></td></tr>
<?php endforeach ?>
<tr class="ssrow" style="color:darkred;"><td align="right">Total:</td>
<td align="right"><b><?= $conv->bitcoinvaluetoa($extraTotal) ?></b></td><td></td></tr>
</table>
<?php endif ?>
<br></div></div><br>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<?php if ($invalidMsg): ?>
<div class="alert alert-warning small mb-3"><?= Html::encode($invalidMsg) ?></div>
<?php endif ?>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?php if ($refcoin && !empty($refcoin->image)): ?>
            <img src="<?= Html::encode($refcoin->image) ?>" width="20" alt="" style="object-fit:contain">
        <?php endif ?>
        <strong class="small"><?= Html::encode($user->username) ?></strong>
        <?php if ($feesNotice): ?>
            <span class="badge bg-info text-dark ms-auto small"><?= Html::encode($feesNotice) ?></span>
        <?php endif ?>
        <?php if (!$showDetails && $total_unsold > 0): ?>
            <div class="ms-auto">
                <div class="form-check form-check-inline small mb-0">
                    <input class="form-check-input" type="checkbox" onclick="main_wallet_refresh_details()">
                    <label class="form-check-label text-muted">Details</label>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm mb-0">
    <colgroup><col style="width:24px"><col><col style="width:160px"></colgroup>
    <?php foreach ($summaryRows as [$label, $val, $bold, $action = null]): ?>
    <tr>
        <td class="ps-3 py-1"><?php if ($refcoin && !empty($refcoin->image)): ?>
            <img src="<?= Html::encode($refcoin->image) ?>" width="16" alt="" style="object-fit:contain">
        <?php endif ?></td>
        <td class="small py-1 <?= $bold ? 'fw-semibold' : 'text-muted' ?>"><?= Html::encode($label) ?></td>
        <td class="text-end pe-3 py-1 small font-monospace <?= $bold ? 'fw-semibold' : '' ?>">
            <?php if ($action === 'tx'): ?>
                <a href="javascript:main_wallet_tx()"><?= $val ?> <?= $sym ?></a>
            <?php else: ?>
                <?= $val ?> <span class="text-muted"><?= $sym ?></span>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </table>
    </div>
    <?php if ($payoutMinNote || ($refcoin && $refcoin->symbol === 'BTC' && $mining)): ?>
    <div class="card-footer text-muted small py-1">
        <?= $payoutMinNote ? Html::encode($payoutMinNote) . '<br>' : '' ?>
        <?php if ($refcoin && $refcoin->symbol === 'BTC' && $mining): ?>
            Rate: <strong><?= number_format($mining->usdbtc, 2, '.', ' ') ?></strong> USD/BTC
        <?php endif ?>
    </div>
    <?php endif ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-credit-card text-secondary"></i>
        <strong class="small">Last 24h Payouts</strong>
        <span class="badge bg-secondary ms-1"><?= count($payouts) ?></span>
        <span class="ms-auto small font-monospace text-muted"><?= $conv->bitcoinvaluetoa($payout24h) ?> <?= $sym ?></span>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm table-bordered mb-0">
    <thead class="table-light"><tr>
        <th class="text-end">Time</th><th class="text-end">Amount</th><th>Tx</th>
    </tr></thead>
    <tbody>
    <?php foreach ($payouts as $p): ?>
    <tr>
        <td class="text-end small text-muted"><?= $conv->datetoa2($p->time) ?> ago</td>
        <td class="text-end small font-monospace fw-semibold"><?= $conv->bitcoinvaluetoa($p->amount) ?></td>
        <td class="small font-monospace"><?php if ($refcoin): ?>
            <?= $refcoin->createExplorerLink(Html::encode(substr($p->tx, 0, 36)) . '…', ['txid' => $p->tx], [], true) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    <?php if (!empty($extraPayouts)): ?>
    <div class="px-3 py-2 bg-danger bg-opacity-10 border-top border-danger small text-danger">
        <strong>Extra payouts detected in last 24h</strong> — some wallets re-sent transactions.
    </div>
    <table class="table table-sm table-bordered mb-0">
    <thead class="table-danger text-danger"><tr>
        <th class="text-end">Time</th><th class="text-end">Amount</th><th>Tx</th>
    </tr></thead>
    <tbody>
    <?php foreach ($extraPayouts as $p): ?>
    <tr>
        <td class="text-end small text-muted"><?= $conv->datetoa2($p->time) ?> ago</td>
        <td class="text-end small font-monospace text-danger fw-semibold"><?= $conv->bitcoinvaluetoa($p->amount) ?></td>
        <td class="small font-monospace"><?php if ($refcoin): ?>
            <?= $refcoin->createExplorerLink(Html::encode(substr($p->tx, 0, 36)) . '…', ['txid' => $p->tx], [], true) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    <?php endif ?>
    </div>
</div>


<?php else: ?>
<!-- TAILWIND ─────────────────────────────────────────────────────────────── -->
<?php if ($invalidMsg): ?>
<div class="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl
            bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 text-sm">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <?= Html::encode($invalidMsg) ?>
</div>
<?php endif ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <?php if ($refcoin && !empty($refcoin->image)): ?>
            <img src="<?= Html::encode($refcoin->image) ?>" width="22" height="22"
                 class="rounded object-contain" onerror="this.style.display='none'" alt="">
        <?php endif ?>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 font-mono">
            <?= Html::encode($user->username) ?>
        </span>
        <?php if ($feesNotice): ?>
            <span class="px-2 py-0.5 text-xs rounded-full bg-blue-50 dark:bg-blue-900/30
                         text-blue-700 dark:text-blue-300 ml-auto">
                <?= Html::encode($feesNotice) ?>
            </span>
        <?php endif ?>
        <?php if (!$showDetails && $total_unsold > 0): ?>
            <label class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 cursor-pointer <?= $feesNotice ? '' : 'ml-auto' ?>">
                <input type="checkbox" class="rounded" onclick="main_wallet_refresh_details()">
                Details
            </label>
        <?php endif ?>
    </div>

    <table class="w-full text-xs">
    <?php foreach ($summaryRows as $i => [$label, $val, $bold, $action = null]):
        $sep = in_array($i, [1, 3, 4]) ? 'border-t-2 border-gray-200 dark:border-gray-700' : 'border-t border-gray-100 dark:border-gray-700/50';
    ?>
    <tr class="<?= $i === 0 ? '' : $sep ?>">
        <td class="px-4 py-2 text-gray-400 dark:text-gray-500 <?= $bold ? 'font-medium' : '' ?>"><?= Html::encode($label) ?></td>
        <td class="px-4 py-2 text-right font-mono tabular-nums
                   <?= $bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' ?>">
            <?php if ($action === 'tx'): ?>
                <a href="javascript:main_wallet_tx()"
                   class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                    <?= $val ?> <span class="text-gray-400"><?= $sym ?></span>
                </a>
            <?php else: ?>
                <?= $val ?> <span class="text-gray-400 dark:text-gray-500"><?= $sym ?></span>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </table>

    <?php if ($payoutMinNote || ($refcoin && $refcoin->symbol === 'BTC' && $mining)): ?>
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
        <?php if ($payoutMinNote): ?><div><?= Html::encode($payoutMinNote) ?></div><?php endif ?>
        <?php if ($refcoin && $refcoin->symbol === 'BTC' && $mining): ?>
        <div>Rate: <strong class="text-gray-600 dark:text-gray-400"><?= number_format($mining->usdbtc, 2, '.', ' ') ?></strong> USD/BTC</div>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-2">
        <i data-lucide="credit-card" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Last 24h Payouts</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 ml-auto">
            <?= count($payouts) ?>
        </span>
        <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
            <?= $conv->bitcoinvaluetoa($payout24h) ?> <?= $sym ?>
        </span>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php if (empty($payouts)): ?>
    <div class="px-4 py-4 text-xs text-gray-400 dark:text-gray-500 text-center">No payouts in the last 24 hours.</div>
    <?php else: ?>
    <?php foreach ($payouts as $p): ?>
    <div class="flex items-center gap-3 px-4 py-2 text-xs">
        <span class="text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $conv->datetoa2($p->time) ?> ago
        </span>
        <span class="font-mono font-semibold text-gray-800 dark:text-gray-200 shrink-0">
            <?= $conv->bitcoinvaluetoa($p->amount) ?>
        </span>
        <span class="font-mono text-gray-400 dark:text-gray-500 truncate min-w-0">
            <?php if ($refcoin): ?>
                <?= $refcoin->createExplorerLink(Html::encode(substr($p->tx, 0, 32)) . '…', ['txid' => $p->tx], [], true) ?>
            <?php endif ?>
        </span>
    </div>
    <?php endforeach ?>
    <?php endif ?>
    </div>

    <?php if (!empty($extraPayouts)): ?>
    <div class="border-t-2 border-red-200 dark:border-red-800
                bg-red-50/40 dark:bg-red-900/10 px-4 py-2
                text-xs text-red-600 dark:text-red-400 font-semibold">
        Extra payouts detected in last 24h — some wallets re-sent transactions.
    </div>
    <div class="divide-y divide-red-100 dark:divide-red-900/30">
    <?php foreach ($extraPayouts as $p): ?>
    <div class="flex items-center gap-3 px-4 py-2 text-xs">
        <span class="text-gray-400 dark:text-gray-500 whitespace-nowrap"><?= $conv->datetoa2($p->time) ?> ago</span>
        <span class="font-mono font-semibold text-red-600 dark:text-red-400 shrink-0"><?= $conv->bitcoinvaluetoa($p->amount) ?></span>
        <span class="font-mono text-gray-400 dark:text-gray-500 truncate min-w-0">
            <?php if ($refcoin): ?>
                <?= $refcoin->createExplorerLink(Html::encode(substr($p->tx, 0, 32)) . '…', ['txid' => $p->tx], [], true) ?>
            <?php endif ?>
        </span>
    </div>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<?php endif ?>
