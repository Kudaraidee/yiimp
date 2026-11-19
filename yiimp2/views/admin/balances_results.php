<?php

/** @var yii\web\View            $this       */
/** @var string                  $exch       */
/** @var app\models\Markets[]    $markets    */
/** @var app\models\Mining       $mining     */
/** @var app\models\Coins[]      $coins      */
/** @var array<string,float>     $btcRateMap */

use yii\helpers\Html;

$conv         = Yii::$app->ConversionUtils;
$utils        = Yii::$app->YiimpUtils;
$usdbtc       = (float) $mining->usdbtc;
$isTailwind   = Yii::$app->LayoutManager->isTailwind();
$isLegacy     = Yii::$app->LayoutManager->isLegacy();

$dustThreshold = 0.00000001;

// ── Shared row computation ────────────────────────────────────────────────────
$totalsBtc = 0.0;
$totalsUsd = 0.0;
$seen      = [];
$visibleRows = [];

foreach ($markets as $market) {
    if (!$market->pricetime) continue;
    $coin = $coins[$market->coinid] ?? null;
    if (!$coin) continue;

    $base      = $market->base_coin ?: 'BTC';
    $btcFactor = $btcRateMap[$base] ?? 0.0;
    $total     = (float) $market->balance + (float) $market->ontrade;
    $rawPrice  = (float) ($market->price  ?: $coin->price);
    $rawPrice2 = (float) ($market->price2 ?: $coin->price2);

    if ($total * $rawPrice2 * $btcFactor < $dustThreshold) continue;

    $symbol = !empty($coin->symbol2) ? $coin->symbol2 : $coin->symbol;
    if (isset($seen[$symbol])) continue;
    $seen[$symbol] = true;

    $btcValueRaw = $total * $rawPrice * $btcFactor;
    $usdValue    = round($btcValueRaw * $usdbtc, 2);
    $totalsBtc  += $btcValueRaw;
    $totalsUsd  += $usdValue;

    $ptime   = $market->pricetime   ? $conv->datetoa2($market->pricetime)   . ' ago' : 'never';
    $btime   = $market->balancetime ? $conv->datetoa2($market->balancetime) . ' ago' : 'never';

    $statusLabel = $market->disabled > 0 ? "market disabled ({$market->disabled})" : 'OK';
    if (!$coin->enable) $statusLabel = 'coin disabled';

    $visibleRows[] = compact(
        'market', 'coin', 'symbol', 'base', 'total',
        'rawPrice', 'rawPrice2', 'btcValueRaw', 'usdValue',
        'ptime', 'btime', 'statusLabel'
    );
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style type="text/css">
td.disabled { color: gray; }
table.dataGrid th.ops, td.ops { text-align: right; padding-right: 16px; }
th.btc, td.btc { width: 120px; max-width: 120px; }
th.addr, td.addr { width: 300px; max-width: 300px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
</style>
<br/>
<table class="dataGrid">
<thead>
<tr>
    <th width="20"></th>
    <th>Name</th>
    <th>Market</th>
    <th class="btc">Bid</th>
    <th class="btc">Ask</th>
    <th title="last price update">Updated</th>
    <th class="btc">Locked</th>
    <th class="btc">Total</th>
    <th class="btc">BTC</th>
    <th>USD</th>
    <th title="last balance update">Updated</th>
    <th class="addr">Deposit</th>
    <th>Status</th>
    <th class="ops">API</th>
</tr>
</thead>
<tbody>
<?php foreach ($visibleRows as $r):
    $market    = $r['market'];
    $coin      = $r['coin'];
    $bold      = $r['btcValueRaw'] > 0.1;
    $tdClass   = $market->disabled ? 'disabled' : '';
    $ontrade   = $market->ontrade ?: '-';
    $btcValue  = $conv->bitcoinvaluetoa($r['btcValueRaw']);
    $marketUrl = $utils->getMarketUrl($coin, $market->name);
?>
<tr class="ssrow">
    <td width="16" class="<?= Html::encode($tdClass) ?>"><?= Html::img(Html::encode($coin->image), ['alt' => Html::encode($coin->symbol), 'width' => 16]) ?></td>
    <td><b><a href="/admin/coinwallet?id=<?= $coin->id ?>"><?= Html::encode($r['symbol']) ?></a></b></td>
    <td><b><a href="<?= Html::encode($marketUrl) ?>" target="_blank"><?= Html::encode($market->name) ?></a></b></td>
    <td class="btc"><?= $conv->bitcoinvaluetoa($r['rawPrice']) ?></td>
    <td class="btc"><?= $conv->bitcoinvaluetoa($r['rawPrice2']) ?></td>
    <td><?= $r['ptime'] ?></td>
    <td><?= Html::encode((string) $ontrade) ?></td>
    <td><?= $r['total'] ?></td>
    <td><?= $bold ? "<b>{$btcValue}</b>" : $btcValue ?></td>
    <td><?= $bold ? '<b>' . sprintf('%.2f', $r['usdValue']) . '</b>' : sprintf('%.2f', $r['usdValue']) ?></td>
    <td><?= $r['btime'] ?></td>
    <td class="addr"><?= Html::encode((string) $market->deposit_address) ?></td>
    <td><?= Html::encode($r['statusLabel']) ?></td>
    <td class="ops"><a href="/admin/balanceUpdate?market=<?= $market->id ?>">update ticker</a></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr>
    <th colspan="8">Total</th>
    <th><b><?= $conv->bitcoinvaluetoa($totalsBtc) ?></b></th>
    <th><b><?= round($totalsUsd, 2) ?></b></th>
    <th></th><th></th><th></th><th></th>
</tr>
</tfoot>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-3 py-2 flex-wrap">
        <span class="small fw-semibold"><?= $exch ? Html::encode($exch) : 'All exchanges' ?></span>
        <span class="badge bg-secondary ms-1"><?= count($visibleRows) ?> markets</span>
        <div class="ms-auto d-flex gap-3 small">
            <span class="text-muted">
                BTC: <strong><?= $conv->bitcoinvaluetoa($totalsBtc) ?></strong>
            </span>
            <span class="text-muted">
                USD: <strong><?= sprintf('%.2f', $totalsUsd) ?></strong>
            </span>
        </div>
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th style="width:24px"></th>
        <th>Coin</th>
        <th>Market</th>
        <th class="text-end" style="width:110px">Bid</th>
        <th class="text-end" style="width:110px">Ask</th>
        <th title="last price update">Price updated</th>
        <th class="text-end" style="width:90px">Locked</th>
        <th class="text-end" style="width:90px">Total</th>
        <th class="text-end" style="width:110px">BTC</th>
        <th class="text-end" style="width:80px">USD</th>
        <th title="last balance update">Bal. updated</th>
        <th style="max-width:220px">Deposit</th>
        <th>Status</th>
        <th class="text-end" style="width:110px">API</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($visibleRows as $r):
        $market    = $r['market'];
        $coin      = $r['coin'];
        $bold      = $r['btcValueRaw'] > 0.1;
        $disabled  = (bool) $market->disabled || !$coin->enable;
        $ontrade   = $market->ontrade ?: '—';
        $btcValue  = $conv->bitcoinvaluetoa($r['btcValueRaw']);
        $marketUrl = $utils->getMarketUrl($coin, $market->name);

        if ($r['statusLabel'] === 'OK') {
            $statusBadge = '<span class="badge bg-success">OK</span>';
        } elseif (!$coin->enable) {
            $statusBadge = '<span class="badge bg-danger">coin disabled</span>';
        } else {
            $statusBadge = '<span class="badge bg-secondary">' . Html::encode($r['statusLabel']) . '</span>';
        }
    ?>
    <tr class="<?= $disabled ? 'text-muted' : '' ?>">
        <td>
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?>
        </td>
        <td>
            <?= Html::a('<strong>' . Html::encode($r['symbol']) . '</strong>',
                ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
        </td>
        <td>
            <?= Html::a(Html::encode($market->name), $marketUrl,
                ['target' => '_blank']) ?>
        </td>
        <td class="text-end small font-monospace"><?= $conv->bitcoinvaluetoa($r['rawPrice']) ?></td>
        <td class="text-end small font-monospace"><?= $conv->bitcoinvaluetoa($r['rawPrice2']) ?></td>
        <td class="small text-muted"><?= $r['ptime'] ?></td>
        <td class="text-end small font-monospace"><?= Html::encode((string) $ontrade) ?></td>
        <td class="text-end small font-monospace"><?= $r['total'] ?></td>
        <td class="text-end small font-monospace <?= $bold ? 'fw-bold' : '' ?>"><?= $btcValue ?></td>
        <td class="text-end small font-monospace <?= $bold ? 'fw-bold' : '' ?>"><?= sprintf('%.2f', $r['usdValue']) ?></td>
        <td class="small text-muted"><?= $r['btime'] ?></td>
        <td class="small text-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= Html::encode((string) $market->deposit_address) ?>
        </td>
        <td><?= $statusBadge ?></td>
        <td class="text-end">
            <?= Html::a('<i class="bi bi-arrow-repeat"></i> ticker',
                ['/admin/balanceUpdate', 'market' => $market->id],
                ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
    <tr>
        <th colspan="8" class="text-end small text-muted">Total</th>
        <th class="text-end small"><strong><?= $conv->bitcoinvaluetoa($totalsBtc) ?></strong></th>
        <th class="text-end small"><strong><?= sprintf('%.2f', $totalsUsd) ?></strong></th>
        <th colspan="4"></th>
    </tr>
    </tfoot>
    </table>
    </div>
    </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <!-- header with totals -->
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            <?= $exch ? Html::encode($exch) : 'All exchanges' ?>
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300">
            <?= count($visibleRows) ?> markets
        </span>
        <div class="ml-auto flex items-center gap-4 text-xs font-mono">
            <span class="text-gray-500 dark:text-gray-400">
                BTC <strong class="text-gray-800 dark:text-gray-100 ml-1">
                    <?= $conv->bitcoinvaluetoa($totalsBtc) ?>
                </strong>
            </span>
            <span class="text-gray-500 dark:text-gray-400">
                USD <strong class="text-gray-800 dark:text-gray-100 ml-1">
                    <?= sprintf('%.2f', $totalsUsd) ?>
                </strong>
            </span>
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"></th>
        <th class="px-3 py-2.5 text-left">Coin</th>
        <th class="px-3 py-2.5 text-left">Market</th>
        <th class="px-3 py-2.5 text-right">Bid</th>
        <th class="px-3 py-2.5 text-right">Ask</th>
        <th class="px-3 py-2.5 text-left" title="last price update">Price upd.</th>
        <th class="px-3 py-2.5 text-right">Locked</th>
        <th class="px-3 py-2.5 text-right">Total</th>
        <th class="px-3 py-2.5 text-right">BTC</th>
        <th class="px-3 py-2.5 text-right">USD</th>
        <th class="px-3 py-2.5 text-left" title="last balance update">Bal. upd.</th>
        <th class="px-3 py-2.5 text-left">Deposit</th>
        <th class="px-3 py-2.5 text-left">Status</th>
        <th class="px-3 py-2.5 text-right w-20"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($visibleRows as $r):
        $market    = $r['market'];
        $coin      = $r['coin'];
        $bold      = $r['btcValueRaw'] > 0.1;
        $disabled  = (bool) $market->disabled || !$coin->enable;
        $ontrade   = $market->ontrade ?: '—';
        $btcValue  = $conv->bitcoinvaluetoa($r['btcValueRaw']);
        $marketUrl = $utils->getMarketUrl($coin, $market->name);

        if ($r['statusLabel'] === 'OK') {
            $statusPill = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">OK</span>';
        } elseif (!$coin->enable) {
            $statusPill = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">coin disabled</span>';
        } else {
            $statusPill = '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">'
                . Html::encode($r['statusLabel']) . '</span>';
        }
    ?>
    <tr class="<?= $disabled
            ? 'opacity-50'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">

        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <?= Html::a('<span class="font-medium text-gray-900 dark:text-gray-100">'
                . Html::encode($r['symbol']) . '</span>',
                ['/admin/coinwallet', 'id' => $coin->id],
                ['encode' => false, 'class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2">
            <?= Html::a(Html::encode($market->name), $marketUrl,
                ['target' => '_blank',
                 'class'  => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">
            <?= $conv->bitcoinvaluetoa($r['rawPrice']) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">
            <?= $conv->bitcoinvaluetoa($r['rawPrice2']) ?>
        </td>

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $r['ptime'] ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode((string) $ontrade) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['total'] ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums
                   <?= $bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' ?>">
            <?= $btcValue ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums
                   <?= $bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' ?>">
            <?= sprintf('%.2f', $r['usdValue']) ?>
        </td>

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $r['btime'] ?>
        </td>

        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 font-mono"
            style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
            title="<?= Html::encode((string) $market->deposit_address) ?>">
            <?= Html::encode((string) $market->deposit_address) ?>
        </td>

        <td class="px-3 py-2"><?= $statusPill ?></td>

        <td class="px-3 py-2 text-right">
            <?= Html::a(
                '<i data-lucide="refresh-cw" class="w-3 h-3 mr-1"></i>ticker',
                ['/admin/balanceUpdate', 'market' => $market->id],
                ['encode' => false,
                 'class'  => 'inline-flex items-center gap-0.5 text-xs text-gray-500 dark:text-gray-400
                              hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <!-- totals footer -->
    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-end gap-6 text-xs font-mono tabular-nums">
        <span class="text-gray-400 dark:text-gray-500">Total BTC
            <strong class="text-gray-800 dark:text-gray-100 ml-1">
                <?= $conv->bitcoinvaluetoa($totalsBtc) ?>
            </strong>
        </span>
        <span class="text-gray-400 dark:text-gray-500">USD
            <strong class="text-gray-800 dark:text-gray-100 ml-1">
                <?= sprintf('%.2f', $totalsUsd) ?>
            </strong>
        </span>
    </div>
</div>

<?php endif ?>
