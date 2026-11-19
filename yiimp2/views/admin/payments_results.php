<?php

/** @var yii\web\View           $this        */
/** @var int                    $coinId      */
/** @var app\models\Accounts[]  $list        */
/** @var app\models\Coins[]     $coins       */
/** @var app\models\Coins|null  $coin        */
/** @var array<string,float>    $immatureMap */
/** @var array<int,float>       $failedMap   */

use yii\helpers\Html;

$conv       = Yii::$app->ConversionUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$saveSort   = $coinId ? 'false' : 'true';

// ── Shared row computation ────────────────────────────────────────────────────
$totalBalance  = 0.0;
$totalImmature = 0.0;
$totalFailed   = 0.0;
$rows          = [];

foreach ($list as $user) {
    $rowCoin     = $coins[$user->coinid] ?? null;
    $immKey      = $rowCoin ? "{$rowCoin->id}-{$user->id}" : "0-{$user->id}";
    $rawImmature = $immatureMap[$immKey] ?? 0.0;
    $rawFailed   = $failedMap[$user->id] ?? 0.0;

    $totalBalance  += (float) $user->balance;
    $totalImmature += $rawImmature;
    $totalFailed   += $rawFailed;

    $coinBalance = $rowCoin
        ? ($rowCoin->balance ? $conv->bitcoinvaluetoa($rowCoin->balance) : '')
        : '-';

    $rows[] = compact('user', 'rowCoin', 'rawImmature', 'rawFailed', 'coinBalance');
}

$userCount = count($list);
$limited   = (!$coinId && $userCount === 100);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display: none; }
.currency { width:120px; max-width:180px; text-align:right; }
.red      { color: darkred; }
.actions  { width:120px; text-align:right; }
table.totals { margin-top:8px; margin-right:16px; }
table.totals th { text-align:left; width:100px; }
table.totals td { text-align:right; }
table.totals tr.red td { color:darkred; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: { 3: function(node, table, n) { return \$(node).attr('data'); } },
    widgets: ['zebra','filter','Storage','saveSort'],
    widgetOptions: {
        saveSort: {$saveSort}, filter_saveFilters: {$saveSort},
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last block</th>
    <th data-sorter="currency" class="currency">Pool</th>
    <th data-sorter="currency" class="currency">Balance</th>
    <th data-sorter="currency" class="currency">Immature</th>
    <th data-sorter="currency" class="currency">Failed</th>
    <th data-sorter="" class="actions">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r):
    $user    = $r['user'];    $rowCoin    = $r['rowCoin'];
    $rawFailed  = $r['rawFailed'];  $rawImmature = $r['rawImmature'];
    $balanceFmt  = $user->balance   ? $conv->bitcoinvaluetoa($user->balance)      : '';
    $immatureFmt = $rawImmature     ? $conv->bitcoinvaluetoa($rawImmature)         : '';
    $failedFmt   = $rawFailed       ? $conv->bitcoinvaluetoa($rawFailed)           : '';
?>
<tr class="ssrow">
    <td><?php if ($rowCoin): ?><img width="16" src="<?= Html::encode($rowCoin->image) ?>" alt=""><?php endif ?></td>
    <td><?php if ($rowCoin): ?>
        <b><?= Html::a(Html::encode($rowCoin->name), ['/admin/coinwallet', 'id' => $rowCoin->id]) ?></b>
        &nbsp;(<?= Html::encode($rowCoin->symbol_show) ?>)
    <?php endif ?></td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', '/?address=' . urlencode($user->username), ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $conv->datetoa2($user->last_earning) ?></td>
    <td class="currency"><?= Html::encode($r['coinBalance']) ?></td>
    <td class="currency"><?= Html::encode($balanceFmt) ?></td>
    <td class="currency"><?= Html::encode($immatureFmt) ?></td>
    <td class="currency red"><?= Html::encode($failedFmt) ?></td>
    <td class="actions"><?php if ($rawFailed > 0): ?>
        <?= Html::a('[add to balance]', ['/admin/cancelUserPayment', 'id' => $user->id],
            ['title' => 'Restore failed payouts to user balance']) ?>
    <?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr><th colspan="9">
    <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?>
    <?= $limited ? ' (limited to 100)' : '' ?>
</th></tr>
</tfoot>
</table>

<?php if ($coinId && $coin):
    $symbol = Html::encode($coin->symbol); ?>
<div class="totals" align="right">
    <table class="totals">
        <tr><th>Balances</th><td><?= Html::encode($conv->bitcoinvaluetoa($totalBalance)) ?> <?= $symbol ?></td></tr>
        <tr><th>Immature</th><td><?= Html::encode($conv->bitcoinvaluetoa($totalImmature)) ?> <?= $symbol ?></td></tr>
        <?php if ($totalFailed > 0): ?>
        <tr class="red">
            <th>Failed</th><td><?= Html::encode($conv->bitcoinvaluetoa($totalFailed)) ?> <?= $symbol ?></td>
        </tr>
        <tr><td colspan="2">
            <?= Html::a('Reset all failed', ['/admin/cancelUsersPayment', 'id' => $coinId], [
                'title'   => 'Add all failed payouts back to user balances',
                'onclick' => 'return confirm("Restore all failed payouts for ' . $symbol . ' to user balances?")',
            ]) ?>
        </td></tr>
        <?php endif ?>
    </table>
</div>
<?php endif ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm<?= $coinId ? ' mb-3' : '' ?>">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <span class="badge bg-secondary"><?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?></span>
        <?php if ($limited): ?>
            <span class="badge bg-warning text-dark">limited to 100</span>
        <?php endif ?>
        <?php if ($totalFailed > 0): ?>
            <span class="badge bg-danger">
                <?= $conv->bitcoinvaluetoa($totalFailed) ?> failed
            </span>
        <?php endif ?>
        <input class="search form-control form-control-sm ms-auto"
               type="search" style="width:160px;" placeholder="Search…">
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="false" style="width:24px"></th>
        <th data-sorter="text">Coin</th>
        <th data-sorter="text">Address</th>
        <th data-sorter="numeric">Last block</th>
        <th data-sorter="currency" class="text-end" style="width:110px">Pool</th>
        <th data-sorter="currency" class="text-end" style="width:110px">Balance</th>
        <th data-sorter="currency" class="text-end" style="width:110px">Immature</th>
        <th data-sorter="currency" class="text-end" style="width:110px">Failed</th>
        <th data-sorter="false"   class="text-end"  style="width:130px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $user        = $r['user'];
        $rowCoin     = $r['rowCoin'];
        $rawFailed   = $r['rawFailed'];
        $rawImmature = $r['rawImmature'];
        $balanceFmt  = $user->balance ? $conv->bitcoinvaluetoa($user->balance) : '';
        $immatureFmt = $rawImmature   ? $conv->bitcoinvaluetoa($rawImmature)   : '';
        $failedFmt   = $rawFailed     ? $conv->bitcoinvaluetoa($rawFailed)     : '';
    ?>
    <tr class="<?= $rawFailed > 0 ? 'table-warning' : '' ?>">
        <td><?php if ($rowCoin && !empty($rowCoin->image)): ?>
            <img src="<?= Html::encode($rowCoin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td><?php if ($rowCoin): ?>
            <?= Html::a('<strong>' . Html::encode($rowCoin->name) . '</strong>',
                ['/admin/coinwallet', 'id' => $rowCoin->id], ['encode' => false]) ?>
            <small class="text-muted">(<?= Html::encode($rowCoin->symbol_show) ?>)</small>
        <?php endif ?></td>
        <td>
            <?= Html::a(Html::encode($user->username), '/?address=' . urlencode($user->username)) ?>
        </td>
        <td class="small text-muted" data="<?= (int) $user->last_earning ?>">
            <?= $conv->datetoa2($user->last_earning) ?>
        </td>
        <td class="text-end small font-monospace"><?= Html::encode($r['coinBalance']) ?></td>
        <td class="text-end small font-monospace"><?= Html::encode($balanceFmt) ?></td>
        <td class="text-end small font-monospace"><?= Html::encode($immatureFmt) ?></td>
        <td class="text-end small font-monospace <?= $rawFailed > 0 ? 'text-danger fw-semibold' : '' ?>">
            <?= Html::encode($failedFmt) ?>
        </td>
        <td class="text-end">
            <?php if ($rawFailed > 0): ?>
                <?= Html::a('add to balance', ['/admin/cancelUserPayment', 'id' => $user->id], [
                    'class' => 'btn btn-sm btn-outline-warning py-0',
                    'title' => 'Restore failed payouts to user balance',
                ]) ?>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
    <tr>
        <th colspan="5" class="small text-muted">
            <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?>
            <?= $limited ? ' (limited to 100)' : '' ?>
        </th>
        <th class="text-end small font-monospace">
            <?= $totalBalance ? Html::encode($conv->bitcoinvaluetoa($totalBalance)) : '' ?>
        </th>
        <th class="text-end small font-monospace">
            <?= $totalImmature ? Html::encode($conv->bitcoinvaluetoa($totalImmature)) : '' ?>
        </th>
        <th class="text-end small font-monospace <?= $totalFailed > 0 ? 'text-danger' : '' ?>">
            <?= $totalFailed ? Html::encode($conv->bitcoinvaluetoa($totalFailed)) : '' ?>
        </th>
        <th></th>
    </tr>
    </tfoot>
    </table>
    </div>
    </div>
</div>

<?php if ($coinId && $coin):
    $symbol = Html::encode($coin->symbol); ?>
<div class="d-flex justify-content-end">
    <div class="card shadow-sm" style="min-width:240px;">
        <div class="card-header py-2 small fw-semibold">Totals — <?= $symbol ?></div>
        <div class="card-body py-2 px-3">
        <table class="table table-sm mb-0">
            <tr><th class="text-muted fw-normal small pe-3">Balances</th>
                <td class="text-end font-monospace small"><?= Html::encode($conv->bitcoinvaluetoa($totalBalance)) ?> <span class="text-muted"><?= $symbol ?></span></td></tr>
            <tr><th class="text-muted fw-normal small pe-3">Immature</th>
                <td class="text-end font-monospace small"><?= Html::encode($conv->bitcoinvaluetoa($totalImmature)) ?> <span class="text-muted"><?= $symbol ?></span></td></tr>
            <?php if ($totalFailed > 0): ?>
            <tr class="table-danger">
                <th class="fw-normal small pe-3">Failed</th>
                <td class="text-end font-monospace small text-danger fw-semibold">
                    <?= Html::encode($conv->bitcoinvaluetoa($totalFailed)) ?> <span><?= $symbol ?></span>
                </td>
            </tr>
            <tr><td colspan="2" class="pt-1">
                <?= Html::a('Reset all failed', ['/admin/cancelUsersPayment', 'id' => $coinId], [
                    'class'   => 'btn btn-sm btn-outline-danger w-100',
                    'title'   => 'Add all failed payouts back to user balances',
                    'onclick' => 'return confirm("Restore all failed payouts for ' . $symbol . ' to user balances?")',
                ]) ?>
            </td></tr>
            <?php endif ?>
        </table>
        </div>
    </div>
</div>
<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: { 3: function(node,table,n){ return \$(node).attr('data'); } },
        widgets: ['zebra','filter','Storage','saveSort'],
        widgetOptions: {
            saveSort: {$saveSort}, filter_saveFilters: {$saveSort},
            filter_external: '.search', filter_columnFilters: false,
            filter_childRows: true, filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden<?= $coinId ? ' mb-4' : '' ?>">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?>
        </span>
        <?php if ($limited): ?>
        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-50 dark:bg-amber-900/30
                     text-amber-700 dark:text-amber-300">limited to 100</span>
        <?php endif ?>
        <?php if ($totalFailed > 0): ?>
        <span class="px-2 py-0.5 text-xs rounded-full bg-red-50 dark:bg-red-900/30
                     text-red-600 dark:text-red-400 font-medium">
            <?= $conv->bitcoinvaluetoa($totalFailed) ?> failed
        </span>
        <?php endif ?>
        <input class="search ml-auto px-3 py-1.5 text-sm rounded-lg border
                      border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-500
                      placeholder-gray-400 dark:placeholder-gray-500"
               type="search" style="width:160px;" placeholder="Search…">
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Coin</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Address</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">Last block</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Pool</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Balance</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Immature</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Failed</th>
        <th class="px-3 py-2.5 text-right" data-sorter="false">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $user        = $r['user'];
        $rowCoin     = $r['rowCoin'];
        $rawFailed   = $r['rawFailed'];
        $rawImmature = $r['rawImmature'];
        $balanceFmt  = $user->balance ? $conv->bitcoinvaluetoa($user->balance) : '';
        $immatureFmt = $rawImmature   ? $conv->bitcoinvaluetoa($rawImmature)   : '';
        $failedFmt   = $rawFailed     ? $conv->bitcoinvaluetoa($rawFailed)     : '';
        $hasFailed   = $rawFailed > 0;
    ?>
    <tr class="<?= $hasFailed
            ? 'bg-red-50/30 dark:bg-red-900/10 hover:bg-red-50/50 dark:hover:bg-red-900/20'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">

        <td class="px-3 py-2">
            <?php if ($rowCoin && !empty($rowCoin->image)): ?>
                <img src="<?= Html::encode($rowCoin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <?php if ($rowCoin): ?>
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode($rowCoin->name),
                    ['/admin/coinwallet', 'id' => $rowCoin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="font-mono text-gray-400 dark:text-gray-500">
                <?= Html::encode($rowCoin->symbol_show) ?>
            </div>
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <?= Html::a(Html::encode($user->username),
                '/?address=' . urlencode($user->username),
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= (int) $user->last_earning ?>">
            <?= $conv->datetoa2($user->last_earning) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode($r['coinBalance']) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode($balanceFmt) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-amber-600 dark:text-amber-400">
            <?= Html::encode($immatureFmt) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums
                   <?= $hasFailed ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-400 dark:text-gray-500' ?>">
            <?= Html::encode($failedFmt) ?>
        </td>

        <td class="px-3 py-2 text-right">
            <?php if ($hasFailed): ?>
                <?= Html::a('add to balance', ['/admin/cancelUserPayment', 'id' => $user->id], [
                    'class' => 'text-xs text-amber-600 dark:text-amber-400 hover:underline transition-colors',
                    'title' => 'Restore failed payouts to user balance',
                ]) ?>
            <?php endif ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <!-- totals footer row -->
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-between text-xs font-mono tabular-nums flex-wrap gap-3">
        <span class="text-gray-400 dark:text-gray-500">
            <?= $userCount ?> user<?= $userCount !== 1 ? 's' : '' ?>
            <?= $limited ? ' (limited to 100)' : '' ?>
        </span>
        <div class="flex items-center gap-4">
            <?php if ($totalBalance): ?>
            <span class="text-gray-500 dark:text-gray-400">
                balance <strong class="text-gray-800 dark:text-gray-200 ml-1">
                    <?= $conv->bitcoinvaluetoa($totalBalance) ?>
                </strong>
            </span>
            <?php endif ?>
            <?php if ($totalImmature): ?>
            <span class="text-amber-600 dark:text-amber-400">
                immature <?= $conv->bitcoinvaluetoa($totalImmature) ?>
            </span>
            <?php endif ?>
            <?php if ($totalFailed > 0): ?>
            <span class="text-red-600 dark:text-red-400 font-semibold">
                failed <?= $conv->bitcoinvaluetoa($totalFailed) ?>
            </span>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if ($coinId && $coin):
    $symbol = Html::encode($coin->symbol); ?>
<div class="flex justify-end">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
         style="min-width:220px;">
        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700
                    text-xs font-semibold text-gray-600 dark:text-gray-300">
            Totals — <?= $symbol ?>
        </div>
        <table class="w-full text-xs">
        <tr class="border-b border-gray-100 dark:border-gray-700/50">
            <td class="px-4 py-1.5 text-gray-400 dark:text-gray-500">Balances</td>
            <td class="px-4 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                <?= Html::encode($conv->bitcoinvaluetoa($totalBalance)) ?>
                <span class="text-gray-400 ml-0.5"><?= $symbol ?></span>
            </td>
        </tr>
        <tr class="<?= $totalFailed > 0 ? 'border-b border-gray-100 dark:border-gray-700/50' : '' ?>">
            <td class="px-4 py-1.5 text-amber-600 dark:text-amber-400">Immature</td>
            <td class="px-4 py-1.5 text-right font-mono tabular-nums text-amber-600 dark:text-amber-400">
                <?= Html::encode($conv->bitcoinvaluetoa($totalImmature)) ?>
                <span class="text-amber-400 dark:text-amber-600 ml-0.5"><?= $symbol ?></span>
            </td>
        </tr>
        <?php if ($totalFailed > 0): ?>
        <tr class="bg-red-50/40 dark:bg-red-900/10 border-b border-gray-100 dark:border-gray-700/50">
            <td class="px-4 py-1.5 text-red-600 dark:text-red-400 font-semibold">Failed</td>
            <td class="px-4 py-1.5 text-right font-mono tabular-nums text-red-600 dark:text-red-400 font-semibold">
                <?= Html::encode($conv->bitcoinvaluetoa($totalFailed)) ?>
                <span class="text-red-400 ml-0.5"><?= $symbol ?></span>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="px-4 py-2">
                <?= Html::a(
                    '<i data-lucide="rotate-ccw" class="w-3 h-3 mr-1"></i>Reset all failed',
                    ['/admin/cancelUsersPayment', 'id' => $coinId],
                    [
                        'encode'  => false,
                        'class'   => 'inline-flex items-center w-full justify-center
                                      px-3 py-1.5 text-xs rounded-lg
                                      bg-red-50 dark:bg-red-900/20
                                      text-red-600 dark:text-red-400
                                      border border-red-200 dark:border-red-800
                                      hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors',
                        'title'   => 'Add all failed payouts back to user balances',
                        'onclick' => 'return confirm("Restore all failed payouts for ' . $symbol . ' to user balances?")',
                    ]
                ) ?>
            </td>
        </tr>
        <?php endif ?>
        </table>
    </div>
</div>
<?php endif ?>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0], ths = Array.from(table.tHead.rows[0].cells);
    var asc = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rs = Array.from(tbody.rows);
            rs.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rs.forEach(function (r) { tbody.appendChild(r); });
        });
    });
    var search = document.querySelector('input.search');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            Array.from(tbody.rows).forEach(function (r) {
                r.classList.toggle('hidden', q !== '' && r.textContent.toLowerCase().indexOf(q) === -1);
            });
        });
    }
});
");
?>

<?php endif ?>
