<?php

/** @var yii\web\View                    $this         */
/** @var app\models\Markets[]            $stuckMarkets */
/** @var app\models\Orders[]             $orders       */
/** @var app\models\Exchange_deposit[]   $deposits     */
/** @var app\models\Coins[]              $coins        */

use yii\helpers\Html;

$conv       = Yii::$app->ConversionUtils;
$utils      = Yii::$app->YiimpUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

// ── Shared order totals ───────────────────────────────────────────────────────
$totalValue = 0.0;
$totalBid   = 0.0;
$orderRows  = [];
foreach ($orders as $order) {
    $coin = $coins[$order->coinid] ?? null;
    if (!$coin) continue;
    $value    = (float) $order->amount * (float) $order->price;
    $bidValue = (float) $order->amount * (float) $order->bid;
    $totalValue += $value;
    $totalBid   += $bidValue;
    $orderRows[] = compact('order', 'coin', 'value', 'bidValue');
}
$totalBidPct = $totalValue > 0 ? round(($totalValue - $totalBid) / $totalValue * 100, 1) : '';

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if ($stuckMarkets): ?>
<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th><th>Exchange</th><th>Sent</th><th>Traded</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($stuckMarkets as $market):
    $coin = $coins[$market->coinid] ?? null; if (!$coin) continue;
    $marketUrl = $utils->getMarketUrl($coin, $market->name); ?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"), ['/admin/coin', 'id' => $coin->id]) ?></b></td>
    <td><b><?= Html::a(Html::encode($market->name), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($market->lastsent) ?> ago</td>
    <td><?= $conv->datetoa2($market->lasttraded) ?> ago</td>
    <td><?= Html::a('[clear]', ['/admin/clearmarket', 'id' => $market->id]) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endif ?>

<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th><th>Exchange</th><th>Created</th>
    <th>Quantity</th><th>Ask</th><th>Bid</th><th>Value</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($orderRows as $r):
    $order = $r['order']; $coin = $r['coin'];
    $bidPct = $r['value'] > 0 ? round(($r['value'] - $r['bidValue']) / $r['value'] * 100, 1) : 0;
    $marketUrl = $utils->getMarketUrl($coin, $order->market); ?>
<tr class="ssrow">
    <td width="16"><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode($coin->name), ['/admin/coin', 'id' => $coin->id]) ?></b>&nbsp;(<?= Html::encode($coin->symbol) ?>)</td>
    <td><b><?= Html::a(Html::encode($order->market), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($order->created) ?> ago</td>
    <td><?= Html::encode((string) $order->amount) ?></td>
    <td><?= $conv->bitcoinvaluetoa($order->price) ?></td>
    <td><?= $conv->bitcoinvaluetoa($order->bid) ?> (<?= $bidPct ?>%)</td>
    <td><?= $r['bidValue'] > 0.01 ? '<b>' . $conv->bitcoinvaluetoa($r['bidValue']) . '</b>' : $conv->bitcoinvaluetoa($r['bidValue']) ?></td>
    <td>
        <?= Html::a('[cancel]', ['/admin/cancelorder', 'id' => $order->id], ['title' => 'Cancel on exchange']) ?>
        <?= Html::a('[clear]',  ['/admin/clearorder',  'id' => $order->id], ['title' => 'Remove from DB only']) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot><tr>
    <td></td><td><b>Total</b></td><td colspan="3"></td>
    <td><b><?= $conv->bitcoinvaluetoa($totalValue) ?></b></td>
    <td><b><?= $conv->bitcoinvaluetoa($totalBid) ?> (<?= $totalBidPct ?>%)</b></td>
    <td></td>
</tr></tfoot>
</table>

<br>
<table class="dataGrid">
<thead><tr>
    <th width="20"></th>
    <th>Name</th><th>Market</th><th>Created</th>
    <th>Quantity</th><th>Estimate</th><th>Sold Price</th><th>Value</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($deposits as $dep):
    $coin = $coins[$dep->coinid] ?? null; if (!$coin) continue;
    $marketUrl = $utils->getMarketUrl($coin, $dep->market);
    $price    = $conv->bitcoinvaluetoa($dep->price  ?: $coin->price);
    $total    = $conv->bitcoinvaluetoa(($dep->price ?: $coin->price) * $dep->quantity);
    $totalRaw = (float) (($dep->price ?: $coin->price) * $dep->quantity);
    $trStyle  = $dep->status === 'waiting' ? " style='background-color:#e0d3e8;'" : " class='ssrow'";
?>
<tr<?= $trStyle ?>>
    <td width="16"><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><?= Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]) ?></b>&nbsp;(<?= Html::encode($coin->symbol) ?>)</td>
    <td><b><?= Html::a(Html::encode($dep->market), $marketUrl, ['target' => '_blank']) ?></b></td>
    <td><?= $conv->datetoa2($dep->send_time) ?> ago</td>
    <td><?= Html::encode((string) $dep->quantity) ?></td>
    <td><?= $conv->bitcoinvaluetoa($dep->price_estimate) ?></td>
    <td><?= $price ?></td>
    <td><?= $totalRaw > 0.01 ? "<b>{$total}</b>" : $total ?></td>
    <td><?php if ($dep->status === 'waiting'): ?>
        <?= Html::a('[del]', ['/admin/deleteexchangedeposit', 'id' => $dep->id]) ?>
    <?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if ($stuckMarkets): ?>
<div class="card border-warning shadow-sm mb-3">
    <div class="card-header bg-warning bg-opacity-10 d-flex align-items-center gap-2 py-2">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <strong class="small">Stuck Markets</strong>
        <span class="badge bg-warning text-dark ms-1"><?= count($stuckMarkets) ?></span>
        <small class="text-muted ms-2">Sent &gt;2h ago but not yet traded</small>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th style="width:24px"></th>
            <th>Coin</th><th>Exchange</th><th>Sent</th><th>Traded</th>
            <th class="text-end" style="width:80px">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stuckMarkets as $market):
            $coin = $coins[$market->coinid] ?? null; if (!$coin) continue;
            $marketUrl = $utils->getMarketUrl($coin, $market->name); ?>
        <tr class="table-warning">
            <td><?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?></td>
            <td><?= Html::a('<strong>' . Html::encode("{$coin->name} ({$coin->symbol})") . '</strong>',
                ['/admin/coin', 'id' => $coin->id], ['encode' => false]) ?></td>
            <td><?= Html::a(Html::encode($market->name), $marketUrl, ['target' => '_blank']) ?></td>
            <td class="small text-muted"><?= $conv->datetoa2($market->lastsent) ?> ago</td>
            <td class="small text-muted"><?= $conv->datetoa2($market->lasttraded) ?> ago</td>
            <td class="text-end">
                <?= Html::a('<i class="bi bi-arrow-counterclockwise"></i>', ['/admin/clearmarket', 'id' => $market->id],
                    ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Reset stuck market', 'encode' => false]) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        </table>
    </div>
</div>
<?php endif ?>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <strong class="small">Open Orders</strong>
        <span class="badge bg-secondary ms-1"><?= count($orderRows) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th style="width:24px"></th>
            <th>Coin</th><th>Exchange</th><th>Created</th>
            <th class="text-end">Qty</th><th class="text-end">Ask</th>
            <th class="text-end">Bid</th><th class="text-end">Value</th>
            <th class="text-end" style="width:110px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orderRows as $r):
            $order = $r['order']; $coin = $r['coin'];
            $bidPct    = $r['value'] > 0 ? round(($r['value'] - $r['bidValue']) / $r['value'] * 100, 1) : 0;
            $marketUrl = $utils->getMarketUrl($coin, $order->market); ?>
        <tr>
            <td><?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?></td>
            <td>
                <?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                    ['/admin/coin', 'id' => $coin->id], ['encode' => false]) ?>
                <small class="text-muted">(<?= Html::encode($coin->symbol) ?>)</small>
            </td>
            <td><?= Html::a(Html::encode($order->market), $marketUrl, ['target' => '_blank']) ?></td>
            <td class="small text-muted"><?= $conv->datetoa2($order->created) ?> ago</td>
            <td class="text-end small font-monospace"><?= Html::encode((string) $order->amount) ?></td>
            <td class="text-end small font-monospace"><?= $conv->bitcoinvaluetoa($order->price) ?></td>
            <td class="text-end small font-monospace">
                <?= $conv->bitcoinvaluetoa($order->bid) ?>
                <small class="text-muted">(<?= $bidPct ?>%)</small>
            </td>
            <td class="text-end small font-monospace <?= $r['bidValue'] > 0.01 ? 'fw-bold' : '' ?>">
                <?= $conv->bitcoinvaluetoa($r['bidValue']) ?>
            </td>
            <td class="text-end">
                <?= Html::a('<i class="bi bi-ban"></i>', ['/admin/cancelorder', 'id' => $order->id],
                    ['class' => 'btn btn-sm btn-outline-danger me-1', 'title' => 'Cancel on exchange', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-trash"></i>', ['/admin/clearorder', 'id' => $order->id],
                    ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Remove from DB only', 'encode' => false]) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="table-light">
        <tr>
            <th colspan="5"></th>
            <th class="text-end small"><b><?= $conv->bitcoinvaluetoa($totalValue) ?></b></th>
            <th class="text-end small">
                <b><?= $conv->bitcoinvaluetoa($totalBid) ?></b>
                <?php if ($totalBidPct !== ''): ?>
                    <small class="text-muted">(<?= $totalBidPct ?>%)</small>
                <?php endif ?>
            </th>
            <th colspan="2" class="text-muted small">Total</th>
        </tr>
        </tfoot>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <strong class="small">Exchange Deposits</strong>
        <span class="badge bg-secondary ms-1"><?= count($deposits) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th style="width:24px"></th>
            <th>Coin</th><th>Market</th><th>Sent</th>
            <th class="text-end">Qty</th><th class="text-end">Estimate</th>
            <th class="text-end">Sold</th><th class="text-end">Value</th>
            <th style="width:50px"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($deposits as $dep):
            $coin = $coins[$dep->coinid] ?? null; if (!$coin) continue;
            $marketUrl = $utils->getMarketUrl($coin, $dep->market);
            $price    = $conv->bitcoinvaluetoa($dep->price  ?: $coin->price);
            $total    = $conv->bitcoinvaluetoa(($dep->price ?: $coin->price) * $dep->quantity);
            $totalRaw = (float) (($dep->price ?: $coin->price) * $dep->quantity);
            $waiting  = $dep->status === 'waiting'; ?>
        <tr class="<?= $waiting ? 'table-info' : '' ?>">
            <td><?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?></td>
            <td>
                <?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                    ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
                <small class="text-muted">(<?= Html::encode($coin->symbol) ?>)</small>
                <?php if ($waiting): ?>
                    <span class="badge bg-info text-dark ms-1">waiting</span>
                <?php endif ?>
            </td>
            <td><?= Html::a(Html::encode($dep->market), $marketUrl, ['target' => '_blank']) ?></td>
            <td class="small text-muted"><?= $conv->datetoa2($dep->send_time) ?> ago</td>
            <td class="text-end small font-monospace"><?= Html::encode((string) $dep->quantity) ?></td>
            <td class="text-end small font-monospace"><?= $conv->bitcoinvaluetoa($dep->price_estimate) ?></td>
            <td class="text-end small font-monospace"><?= $price ?></td>
            <td class="text-end small font-monospace <?= $totalRaw > 0.01 ? 'fw-bold' : '' ?>"><?= $total ?></td>
            <td class="text-end">
                <?php if ($waiting): ?>
                    <?= Html::a('<i class="bi bi-trash"></i>', ['/admin/deleteexchangedeposit', 'id' => $dep->id],
                        ['class' => 'btn btn-sm btn-outline-danger', 'title' => 'Delete deposit', 'encode' => false]) ?>
                <?php endif ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        </table>
    </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if ($stuckMarkets): ?>
<div class="rounded-xl border border-amber-200 dark:border-amber-800
            bg-amber-50/50 dark:bg-amber-900/10 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-amber-200 dark:border-amber-800
                flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0"></i>
        <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Stuck Markets</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 dark:bg-amber-900/40
                     text-amber-700 dark:text-amber-300 font-medium">
            <?= count($stuckMarkets) ?>
        </span>
        <span class="text-xs text-amber-600 dark:text-amber-400 ml-1">sent &gt;2h ago, not yet traded</span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
    <thead>
    <tr class="bg-amber-50 dark:bg-amber-900/20 text-xs font-semibold
               text-amber-700 dark:text-amber-400 uppercase tracking-wider
               border-b border-amber-200 dark:border-amber-800">
        <th class="px-3 py-2.5 w-8"></th>
        <th class="px-3 py-2.5 text-left">Coin</th>
        <th class="px-3 py-2.5 text-left">Exchange</th>
        <th class="px-3 py-2.5 text-left">Sent</th>
        <th class="px-3 py-2.5 text-left">Traded</th>
        <th class="px-3 py-2.5 text-right w-20"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-amber-100 dark:divide-amber-900/30">
    <?php foreach ($stuckMarkets as $market):
        $coin = $coins[$market->coinid] ?? null; if (!$coin) continue;
        $marketUrl = $utils->getMarketUrl($coin, $market->name); ?>
    <tr class="hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">
        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
            <?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"),
                ['/admin/coin', 'id' => $coin->id],
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400']) ?>
        </td>
        <td class="px-3 py-2">
            <?= Html::a(Html::encode($market->name), $marketUrl,
                ['target' => '_blank', 'class' => 'hover:text-indigo-600 dark:hover:text-indigo-400']) ?>
        </td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500"><?= $conv->datetoa2($market->lastsent) ?> ago</td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500"><?= $conv->datetoa2($market->lasttraded) ?> ago</td>
        <td class="px-3 py-2 text-right">
            <?= Html::a('<i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>',
                ['/admin/clearmarket', 'id' => $market->id],
                ['class'  => 'inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors',
                 'title'  => 'Reset stuck market',
                 'encode' => false]) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
</div>
<?php endif ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Open Orders</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= count($orderRows) ?>
        </span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold
               text-gray-500 dark:text-gray-400 uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"></th>
        <th class="px-3 py-2.5 text-left">Coin</th>
        <th class="px-3 py-2.5 text-left">Exchange</th>
        <th class="px-3 py-2.5 text-left">Created</th>
        <th class="px-3 py-2.5 text-right">Qty</th>
        <th class="px-3 py-2.5 text-right">Ask</th>
        <th class="px-3 py-2.5 text-right">Bid</th>
        <th class="px-3 py-2.5 text-right">Value</th>
        <th class="px-3 py-2.5 text-right w-24"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($orderRows as $r):
        $order = $r['order']; $coin = $r['coin'];
        $bidPct    = $r['value'] > 0 ? round(($r['value'] - $r['bidValue']) / $r['value'] * 100, 1) : 0;
        $marketUrl = $utils->getMarketUrl($coin, $order->market); ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2.5">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2.5">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode($coin->name),
                    ['/admin/coin', 'id' => $coin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                <?= Html::encode($coin->symbol) ?>
                &nbsp;·&nbsp;
                <?= Html::a(Html::encode($order->market), $marketUrl,
                    ['target' => '_blank', 'class' => 'hover:text-indigo-500 transition-colors']) ?>
            </div>
        </td>
        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500">
            <?= Html::a(Html::encode($order->market), $marketUrl,
                ['target' => '_blank', 'class' => 'hidden']) ?>
        </td>
        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500 text-xs whitespace-nowrap">
            <?= $conv->datetoa2($order->created) ?> ago
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode((string) $order->amount) ?>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= $conv->bitcoinvaluetoa($order->price) ?>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= $conv->bitcoinvaluetoa($order->bid) ?>
            <span class="text-gray-400 dark:text-gray-500 text-xs">(<?= $bidPct ?>%)</span>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums
                   <?= $r['bidValue'] > 0.01 ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' ?>">
            <?= $conv->bitcoinvaluetoa($r['bidValue']) ?>
        </td>
        <td class="px-3 py-2.5 text-right">
            <div class="flex items-center justify-end gap-1">
                <?= Html::a('<i data-lucide="ban" class="w-3.5 h-3.5"></i>',
                    ['/admin/cancelorder', 'id' => $order->id],
                    ['class'  => 'inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors',
                     'title'  => 'Cancel on exchange',
                     'encode' => false]) ?>
                <?= Html::a('<i data-lucide="trash-2" class="w-3.5 h-3.5"></i>',
                    ['/admin/clearorder', 'id' => $order->id],
                    ['class'  => 'inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors',
                     'title'  => 'Remove from DB only',
                     'encode' => false]) ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-end gap-6 text-xs font-mono tabular-nums">
        <span class="text-gray-400 dark:text-gray-500">Total ask
            <strong class="text-gray-700 dark:text-gray-200 ml-1"><?= $conv->bitcoinvaluetoa($totalValue) ?></strong>
        </span>
        <span class="text-gray-400 dark:text-gray-500">bid
            <strong class="text-gray-700 dark:text-gray-200 ml-1"><?= $conv->bitcoinvaluetoa($totalBid) ?></strong>
            <?php if ($totalBidPct !== ''): ?>
                <span class="text-gray-400">(<?= $totalBidPct ?>%)</span>
            <?php endif ?>
        </span>
    </div>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Exchange Deposits</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= count($deposits) ?>
        </span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold
               text-gray-500 dark:text-gray-400 uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"></th>
        <th class="px-3 py-2.5 text-left">Coin</th>
        <th class="px-3 py-2.5 text-left">Market</th>
        <th class="px-3 py-2.5 text-left">Sent</th>
        <th class="px-3 py-2.5 text-right">Qty</th>
        <th class="px-3 py-2.5 text-right">Estimate</th>
        <th class="px-3 py-2.5 text-right">Sold</th>
        <th class="px-3 py-2.5 text-right">Value</th>
        <th class="px-3 py-2.5 w-12"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($deposits as $dep):
        $coin = $coins[$dep->coinid] ?? null; if (!$coin) continue;
        $marketUrl = $utils->getMarketUrl($coin, $dep->market);
        $price    = $conv->bitcoinvaluetoa($dep->price  ?: $coin->price);
        $total    = $conv->bitcoinvaluetoa(($dep->price ?: $coin->price) * $dep->quantity);
        $totalRaw = (float) (($dep->price ?: $coin->price) * $dep->quantity);
        $waiting  = $dep->status === 'waiting'; ?>
    <tr class="<?= $waiting
        ? 'bg-indigo-50/50 dark:bg-indigo-900/10 hover:bg-indigo-50 dark:hover:bg-indigo-900/20'
        : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">
        <td class="px-3 py-2.5">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2.5">
            <div class="flex items-center gap-2">
                <span class="font-medium text-gray-900 dark:text-gray-100">
                    <?= Html::a(Html::encode($coin->name),
                        ['/admin/coinwallet', 'id' => $coin->id],
                        ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                    <span class="text-gray-400 dark:text-gray-500 font-mono text-xs ml-1">
                        (<?= Html::encode($coin->symbol) ?>)
                    </span>
                </span>
                <?php if ($waiting): ?>
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs
                                 bg-indigo-100 dark:bg-indigo-900/40
                                 text-indigo-700 dark:text-indigo-300">
                        <i data-lucide="clock" class="w-3 h-3"></i>waiting
                    </span>
                <?php endif ?>
            </div>
        </td>
        <td class="px-3 py-2.5">
            <?= Html::a(Html::encode($dep->market), $marketUrl,
                ['target' => '_blank', 'class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>
        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500 text-xs whitespace-nowrap">
            <?= $conv->datetoa2($dep->send_time) ?> ago
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode((string) $dep->quantity) ?>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= $conv->bitcoinvaluetoa($dep->price_estimate) ?>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= $price ?>
        </td>
        <td class="px-3 py-2.5 text-right font-mono tabular-nums
                   <?= $totalRaw > 0.01 ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' ?>">
            <?= $total ?>
        </td>
        <td class="px-3 py-2.5 text-right">
            <?php if ($waiting): ?>
                <?= Html::a('<i data-lucide="trash-2" class="w-3.5 h-3.5"></i>',
                    ['/admin/deleteexchangedeposit', 'id' => $dep->id],
                    ['class'  => 'inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors',
                     'title'  => 'Delete deposit',
                     'encode' => false]) ?>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
</div>

<?php endif ?>
