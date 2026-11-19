<?php

/** @var yii\web\View              $this           */
/** @var string                    $algo           */
/** @var app\models\Workers[]      $workers        */
/** @var app\models\Accounts[]     $accounts       */
/** @var app\models\Coins[]        $coins          */
/** @var array<int,array>          $shareStatsMap  */
/** @var array<int,int>            $shareCountMap  */
/** @var array<int,int>            $workerBlockMap */
/** @var array<int,int>            $userBlockMap   */
/** @var float                     $totalRate      */

use yii\helpers\Html;

$conv       = Yii::$app->ConversionUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

if ($algo === '') {
    $msg = 'No algo selected.';
    if ($isTailwind) {
        echo '<p class="text-sm text-gray-400 dark:text-gray-500">' . $msg . '</p>';
    } else {
        echo '<p class="text-muted">' . $msg . '</p>';
    }
    return;
}

// ── Shared per-worker computation ─────────────────────────────────────────────
$rows = [];
foreach ($workers as $worker) {
    $wid        = $worker->id;
    $uid        = $worker->userid ?: null;
    $stats      = $shareStatsMap[$wid]  ?? ['rate' => 0.0, 'bad' => 0.0];
    $workerRate = $stats['rate'];
    $workerBad  = $stats['bad'];
    $shares     = $shareCountMap[$wid]  ?? 0;
    $wBlocks    = $workerBlockMap[$wid] ?? 0;
    $uBlocks    = $uid ? ($userBlockMap[$uid] ?? 0) : 0;

    $sharePercent = ($totalRate > 0) ? (100.0 * $workerRate) / $totalRate : 0.0;
    $pctBad       = ($workerRate + $workerBad) > 0
                    ? round($workerBad * 100 / ($workerRate + $workerBad), 3) : 0;
    $rateDisplay  = $workerRate ? $conv->Itoa2($workerRate) . 'H' : '-';

    $user = $uid ? ($accounts[$uid] ?? null) : null;
    $coin = $user ? ($coins[$user->coinid] ?? null) : null;

    $displayName = $worker->worker ?? '';
    if ($displayName === '' && $user) {
        $displayName = $user->login ?? $user->username ?? '';
    }

    $dns = !empty($worker->dns) ? $worker->dns : $worker->ip;
    if (strlen((string) $dns) > 40) {
        $dns = '…' . substr($dns, strlen($dns) - 40);
    }

    $rows[] = compact('worker', 'wid', 'uid', 'user', 'coin',
                      'workerRate', 'workerBad', 'shares', 'wBlocks', 'uBlocks',
                      'sharePercent', 'pctBad', 'rateDisplay', 'displayName', 'dns');
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all"
           style="width:140px;" placeholder="Search…">
</div>
<style>tr.ssrow.filtered { display: none; }</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: { 6: function(node, table, n) { return \$(node).attr('data'); } },
    widgets: ['zebra','filter','Storage','saveSort'],
    widgetOptions: {
        saveSort: true, filter_saveFilters: false,
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="text">Pass</th>
    <th data-sorter="text">Client</th>
    <th data-sorter="text">Version</th>
    <th data-sorter="numeric">Hashrate</th>
    <th data-sorter="numeric">Diff</th>
    <th data-sorter="numeric">Shares</th>
    <th data-sorter="numeric">Bad</th>
    <th data-sorter="numeric">%</th>
    <th data-sorter="numeric">Found</th>
    <th data-sorter="text" width="30">Name</th>
    <th data-sorter="text"></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r):
    $worker  = $r['worker'];
    $coin    = $r['coin'];
    $user    = $r['user'];
    $coinImg  = $coin ? Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]) : '';
    $coinLink = $coin ? Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]) : '';
    $coinsym  = $coin ? $coin->symbol : '';
?>
<tr class="ssrow">
    <td width="20"><?= $coinImg ?></td>
    <td>
        <b><?= $coinLink ?></b>
        <?= $coinsym ? '&nbsp;(' . Html::encode($coinsym) . ')' : '<span class="text-muted">-</span>' ?>
    </td>
    <td><?= Html::a('<b>' . Html::encode($worker->name) . '</b>', '/?address=' . urlencode($worker->name), ['encode' => false]) ?></td>
    <td><?= Html::encode($worker->password) ?></td>
    <td title="<?= Html::encode($worker->ip) ?>"><?= Html::encode($r['dns']) ?></td>
    <td><?= Html::encode($worker->version) ?></td>
    <td data="<?= (float) $r['workerRate'] ?>"><?= Html::encode($r['rateDisplay']) ?></td>
    <td><?= (int) $worker->difficulty ?></td>
    <td><?= $r['shares'] ?></td>
    <td><?php if ($r['workerBad'] > 0): ?>
        <?= $r['pctBad'] > 50 ? '<b>' . round($r['pctBad'], 1) . '%</b>' : round($r['pctBad'], 1) . '%' ?>
    <?php endif ?></td>
    <td><?= number_format($r['sharePercent'], 1, '.', '') ?>%</td>
    <td><?= $r['wBlocks'] ?> / <?= $r['uBlocks'] ?></td>
    <td><?= Html::encode($r['displayName']) ?></td>
    <td><?= $user && $user->donation ? Html::encode($user->donation) . '&nbsp;%' : '' ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <span class="badge bg-secondary"><?= count($workers) ?> workers</span>
        <span class="badge bg-light text-dark border font-monospace"><?= Html::encode($algo) ?></span>
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
        <th data-sorter="text">Pass</th>
        <th data-sorter="text">Client</th>
        <th data-sorter="text">Version</th>
        <th data-sorter="numeric" class="text-end">Hashrate</th>
        <th data-sorter="numeric" class="text-end">Diff</th>
        <th data-sorter="numeric" class="text-end">Shares</th>
        <th data-sorter="numeric" class="text-end">Bad %</th>
        <th data-sorter="numeric" class="text-end">Pool %</th>
        <th data-sorter="numeric" class="text-end">Found</th>
        <th data-sorter="text">Name</th>
        <th data-sorter="text" class="text-end">Donation</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $worker = $r['worker'];
        $coin   = $r['coin'];
        $user   = $r['user'];
        $highBad = $r['pctBad'] > 50;
    ?>
    <tr>
        <td>
            <?php if ($coin && !empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?>
        </td>
        <td>
            <?php if ($coin): ?>
                <?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                    ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
                <small class="text-muted font-monospace">(<?= Html::encode($coin->symbol) ?>)</small>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif ?>
        </td>
        <td>
            <?= Html::a('<strong>' . Html::encode($worker->name) . '</strong>',
                '/?address=' . urlencode($worker->name), ['encode' => false]) ?>
        </td>
        <td class="small text-muted"><?= Html::encode($worker->password) ?></td>
        <td class="small font-monospace text-muted" title="<?= Html::encode($worker->ip) ?>">
            <?= Html::encode($r['dns']) ?>
        </td>
        <td class="small font-monospace"><?= Html::encode($worker->version) ?></td>
        <td class="text-end small font-monospace" data="<?= (float) $r['workerRate'] ?>">
            <?= Html::encode($r['rateDisplay']) ?>
        </td>
        <td class="text-end small tabular-nums"><?= (int) $worker->difficulty ?></td>
        <td class="text-end small tabular-nums"><?= $r['shares'] ?></td>
        <td class="text-end small <?= $highBad ? 'text-danger fw-bold' : ($r['pctBad'] > 20 ? 'text-warning' : '') ?>">
            <?= $r['workerBad'] > 0 ? round($r['pctBad'], 1) . '%' : '' ?>
        </td>
        <td class="text-end small tabular-nums"><?= number_format($r['sharePercent'], 1) ?>%</td>
        <td class="text-end small tabular-nums">
            <?= $r['wBlocks'] ?><span class="text-muted">/<?= $r['uBlocks'] ?></span>
        </td>
        <td class="small"><?= Html::encode($r['displayName']) ?></td>
        <td class="text-end small text-muted">
            <?= $user && $user->donation ? Html::encode($user->donation) . '%' : '' ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
        <tr>
            <th colspan="6" class="text-muted small"><?= count($workers) ?> workers</th>
            <th class="text-end small font-monospace">
                <?= $totalRate ? Html::encode($conv->Itoa2($totalRate)) . 'H' : '' ?>
            </th>
            <th colspan="7"></th>
        </tr>
    </tfoot>
    </table>
    </div>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: { 6: function(node, table, n) { return \$(node).attr('data'); } },
        widgets: ['zebra','filter','Storage','saveSort'],
        widgetOptions: {
            saveSort: true, filter_saveFilters: false,
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
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= count($workers) ?> workers
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                     text-indigo-700 dark:text-indigo-300 font-mono font-medium">
            <?= Html::encode($algo) ?>
        </span>
        <?php if ($totalRate): ?>
        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">
            total <?= Html::encode($conv->Itoa2($totalRate)) ?>H/s
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
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Pass</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Client</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Version</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Hashrate</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Diff</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Shares</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Bad %</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Pool %</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Found</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Name</th>
        <th class="px-3 py-2.5 text-right" data-sorter="text">Donation</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $worker  = $r['worker'];
        $coin    = $r['coin'];
        $user    = $r['user'];
        $highBad = $r['pctBad'] > 50;
    ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">

        <td class="px-3 py-2">
            <?php if ($coin && !empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <?php if ($coin): ?>
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    <?= Html::a(Html::encode($coin->name),
                        ['/admin/coinwallet', 'id' => $coin->id],
                        ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                </div>
                <div class="font-mono text-gray-400 dark:text-gray-500">
                    <?= Html::encode($coin->symbol) ?>
                </div>
            <?php else: ?>
                <span class="text-gray-300 dark:text-gray-600">—</span>
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <?= Html::a('<span class="font-medium text-gray-900 dark:text-gray-100">'
                . Html::encode($worker->name) . '</span>',
                '/?address=' . urlencode($worker->name),
                ['encode' => false,
                 'class'  => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500">
            <?= Html::encode($worker->password) ?>
        </td>

        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500"
            title="<?= Html::encode($worker->ip) ?>">
            <?= Html::encode($r['dns']) ?>
        </td>

        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">
            <?= Html::encode($worker->version) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums
                   font-semibold text-gray-800 dark:text-gray-200"
            data="<?= (float) $r['workerRate'] ?>">
            <?= Html::encode($r['rateDisplay']) ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= (int) $worker->difficulty ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['shares'] ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums
                   <?= $highBad
                       ? 'text-red-500 dark:text-red-400 font-bold'
                       : ($r['pctBad'] > 20 ? 'text-amber-500 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500') ?>">
            <?= $r['workerBad'] > 0 ? round($r['pctBad'], 1) . '%' : '' ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= number_format($r['sharePercent'], 1) ?>%
        </td>

        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['wBlocks'] ?>
            <span class="text-gray-300 dark:text-gray-600">/<?= $r['uBlocks'] ?></span>
        </td>

        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
            <?= Html::encode($r['displayName']) ?>
        </td>

        <td class="px-3 py-2 text-right text-gray-400 dark:text-gray-500">
            <?= $user && $user->donation ? Html::encode($user->donation) . '%' : '' ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
        <span><?= count($workers) ?> workers for <span class="font-mono text-indigo-600 dark:text-indigo-400"><?= Html::encode($algo) ?></span></span>
        <?php if ($totalRate): ?>
        <span class="font-mono">total <?= Html::encode($conv->Itoa2($totalRate)) ?>H/s</span>
        <?php endif ?>
    </div>
</div>

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
            var rows = Array.from(tbody.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
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
