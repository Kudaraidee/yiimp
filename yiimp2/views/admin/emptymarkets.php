<?php

/** @var yii\web\View          $this    */
/** @var array[]               $rows    */
/** @var app\models\Coins[]    $coins   */
/** @var app\models\Markets[]  $markets */

use yii\helpers\Html;

$this->title  = 'Empty Markets';
$isTailwind   = Yii::$app->LayoutManager->isTailwind();
$isLegacy     = Yii::$app->LayoutManager->isLegacy();
$conv         = Yii::$app->ConversionUtils;
$count        = count($rows);

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

// ── Shared per-row helper ─────────────────────────────────────────────────────
$missingAddress = fn($market) => empty($market->deposit_address);
$hasMessage     = fn($market) => !empty($market->message);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="mb-2">
    <input class="search form-control form-control-sm d-inline-block"
           type="search" style="width:180px;" placeholder="Search…">
</div>

<?php if (empty($rows)): ?>
<div class="alert alert-success">
    All active coin markets have a deposit address and no error messages.
</div>
<?php else: ?>

<p class="text-muted small mb-2">
    Active coin markets with no deposit address configured or with an error message set.
</p>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    headers: { 0: { sorter: false } },
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th width="20"></th>
    <th>Coin</th>
    <th>Market</th>
    <th>Price</th>
    <th>Message</th>
    <th>Deposit Address</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
    $coin   = $coins[$row['coinid']]     ?? null;
    $market = $markets[$row['marketid']] ?? null;
    if (!$coin || !$market) continue;
?>
<tr class="ssrow">
    <td><?php if (!empty($coin->image)): ?>
        <img src="<?= Html::encode($coin->image) ?>" width="16" alt=""
             onerror="this.style.display='none'">
    <?php endif ?></td>
    <td><?= Html::a(Html::encode($coin->name), ['/admin/coin_update', 'id' => $coin->id]) ?></td>
    <td><?= Html::a(Html::encode($market->name), ['/admin/market/update', 'id' => $market->id]) ?></td>
    <td class="text-end"><?= $conv->bitcoinvaluetoa($market->price) ?></td>
    <td class="<?= $hasMessage($market) ? 'text-danger' : 'text-muted' ?>">
        <?= Html::encode((string) $market->message) ?>
    </td>
    <td class="<?= $missingAddress($market) ? 'text-danger fw-semibold' : 'text-muted' ?>">
        <?= $missingAddress($market) ? 'not configured' : Html::encode($market->deposit_address) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<p class="text-muted small mt-2">
    <?= $count ?> market<?= $count !== 1 ? 's' : '' ?> need attention.
</p>

<?php endif ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if (empty($rows)): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
    <i class="bi bi-check-circle-fill"></i>
    All active coin markets have a deposit address and no error messages.
</div>
<?php else: ?>

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <span class="badge bg-warning text-dark"><?= $count ?> need attention</span>
        <small class="text-muted ms-1">
            Markets missing a deposit address or carrying an error message.
        </small>
        <input class="search form-control form-control-sm ms-auto" type="search"
               style="width:180px;" placeholder="Search…">
    </div>
    <div class="card-body p-0">
        <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th data-sorter="false" style="width:24px"></th>
            <th data-sorter="text">Coin</th>
            <th data-sorter="text">Market</th>
            <th data-sorter="currency" class="text-end" style="width:110px">Price</th>
            <th data-sorter="text">Message</th>
            <th data-sorter="text">Deposit Address</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row):
            $coin   = $coins[$row['coinid']]     ?? null;
            $market = $markets[$row['marketid']] ?? null;
            if (!$coin || !$market) continue;
            $noAddr = $missingAddress($market);
            $hasMsg = $hasMessage($market);
        ?>
        <tr class="<?= ($noAddr || $hasMsg) ? 'table-warning' : '' ?>">
            <td>
                <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                         style="object-fit:contain" onerror="this.style.display='none'">
                <?php endif ?>
            </td>
            <td>
                <?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                    ['/admin/coin_update', 'id' => $coin->id], ['encode' => false]) ?>
                <small class="text-muted font-monospace ms-1"><?= Html::encode($coin->symbol) ?></small>
            </td>
            <td>
                <?= Html::a(Html::encode($market->name),
                    ['/admin/market/update', 'id' => $market->id]) ?>
            </td>
            <td class="text-end small font-monospace">
                <?= $conv->bitcoinvaluetoa($market->price) ?>
            </td>
            <td class="small <?= $hasMsg ? 'text-danger' : 'text-muted' ?>">
                <?= Html::encode((string) $market->message) ?>
            </td>
            <td class="small <?= $noAddr ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= $noAddr ? '<i class="bi bi-exclamation-triangle-fill me-1"></i>not configured'
                            : Html::encode($market->deposit_address) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="6" class="text-muted small">
                    <?= $count ?> market<?= $count !== 1 ? 's' : '' ?> need attention
                    &mdash; click a market name to edit and configure the deposit address.
                </th>
            </tr>
        </tfoot>
        </table>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter'],
        widgetOptions: {
            filter_external: '.search',
            filter_columnFilters: false,
            filter_childRows: true,
            filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>

<?php endif ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if (empty($rows)): ?>
<div class="flex items-center gap-3 px-4 py-3 rounded-xl
            bg-green-50 dark:bg-green-900/20
            border border-green-200 dark:border-green-800
            text-green-700 dark:text-green-300 text-sm">
    <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
    All active coin markets have a deposit address and no error messages.
</div>
<?php else: ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <!-- header -->
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                         bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                <?= $count ?> need attention
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Markets missing a deposit address or carrying an error message
            </span>
        </div>
        <input class="search ml-auto px-3 py-1.5 text-sm rounded-lg border
                      border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-700
                      text-gray-900 dark:text-gray-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-500
                      placeholder-gray-400 dark:placeholder-gray-500"
               type="search" style="width:170px;" placeholder="Search…">
    </div>

    <!-- table -->
    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-sm">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold
               text-gray-500 dark:text-gray-400 uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8" data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Coin</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Market</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Price</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Message</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Deposit Address</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $row):
        $coin   = $coins[$row['coinid']]     ?? null;
        $market = $markets[$row['marketid']] ?? null;
        if (!$coin || !$market) continue;
        $noAddr = $missingAddress($market);
        $hasMsg = $hasMessage($market);
    ?>
    <tr class="hover:bg-amber-50/40 dark:hover:bg-amber-900/10 transition-colors">

        <td class="px-3 py-2.5">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2.5">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode($coin->name),
                    ['/admin/coin_update', 'id' => $coin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                <?= Html::encode($coin->symbol) ?>
            </div>
        </td>

        <td class="px-3 py-2.5">
            <?= Html::a(Html::encode($market->name),
                ['/admin/market/update', 'id' => $market->id],
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">
            <?= $conv->bitcoinvaluetoa($market->price) ?>
        </td>

        <td class="px-3 py-2.5 <?= $hasMsg
            ? 'text-red-600 dark:text-red-400 font-medium'
            : 'text-gray-400 dark:text-gray-500' ?>">
            <?= Html::encode((string) $market->message) ?>
        </td>

        <td class="px-3 py-2.5">
            <?php if ($noAddr): ?>
                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                    not configured
                </span>
            <?php else: ?>
                <span class="font-mono text-xs text-gray-500 dark:text-gray-400 break-all">
                    <?= Html::encode($market->deposit_address) ?>
                </span>
            <?php endif ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <!-- footer -->
    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= $count ?> market<?= $count !== 1 ? 's' : '' ?> need attention
        &mdash; click a market name to edit and configure the deposit address.
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
<?php endif ?>
