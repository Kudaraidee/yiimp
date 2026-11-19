<?php

/** @var yii\web\View              $this     */
/** @var int                       $coinId   */
/** @var app\models\Earnings[]     $earnings */
/** @var app\models\Coins[]        $coins    */
/** @var app\models\Accounts[]     $accounts */
/** @var app\models\Blocks[]       $blocks   */
/** @var app\models\Coins|null     $coin     */
/** @var float                     $cleared  */

use yii\helpers\Html;

$cu         = Yii::$app->ConversionUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$saveSort   = $coinId ? 'false' : 'true';

// ── Shared row computation ────────────────────────────────────────────────────
$total      = 0.0;
$totalImmat = 0.0;
$totalStake = 0.0;
$rows       = [];

foreach ($earnings as $earning) {
    $rowCoin  = $coins[$earning->coinid]    ?? null; if (!$rowCoin)  continue;
    $user     = $accounts[$earning->userid] ?? null; if (!$user)     continue;
    $block    = $blocks[$earning->blockid]  ?? null; if (!$block)    continue;

    $t1 = $cu->datetoa2($earning->create_time) . ' ago';
    $t2 = $cu->datetoa2($earning->mature_time);
    if ($t2) $t2 = '+' . $t2;

    if ($block->category === 'immature') {
        $total      += (float) $earning->amount;
        $totalImmat += (float) $earning->amount;
    } elseif ($block->category === 'generate') {
        $total += (float) $earning->amount;
    } elseif ($block->category === 'stake' || $block->category === 'generated') {
        $totalStake += (float) $earning->amount;
    }

    $rows[] = compact('earning', 'rowCoin', 'user', 'block', 't1', 't2');
}

// ── Coin totals block (only when filtered by coin) ────────────────────────────
$totalsHtml = '';
if ($coinId && $coin) {
    $symbol    = $coin->symbol;
    $feePct    = Yii::$app->YiimpUtils->yiimp_fee($coin->algo);
    $totalFees = ($total / ((100 - $feePct) / 100.0)) - $total;
    $exchange  = $total - $totalImmat;
    $available = $coin->balance - $exchange - $cleared;

    $totals = [
        ['Immature',                      $cu->bitcoinvaluetoa($totalImmat)],
        ['Total owed',                     $cu->bitcoinvaluetoa($total)],
        ['Pool fees ' . round($feePct,1) . '%', $cu->bitcoinvaluetoa($totalFees)],
    ];
    if ($coin->rpcencoding === 'POS') {
        $totals[] = ['Stake', $cu->bitcoinvaluetoa($totalStake)];
    }
    $wallet = [
        ['Balance',   $cu->bitcoinvaluetoa($coin->balance),  false],
        ['Cleared',   $cu->bitcoinvaluetoa($cleared),        false],
        ['Available', $cu->bitcoinvaluetoa($available),
            'Available = Balance − Cleared − in exchange'],
    ];
}

// ── Category badge helpers ────────────────────────────────────────────────────
$categoryBadge = function(string $cat) use ($isTailwind): string {
    if ($isTailwind) {
        $map = [
            'immature'  => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'generate'  => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'generated' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'stake'     => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'orphan'    => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        ];
        $cls = $map[$cat] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium ' . $cls . '">'
             . Html::encode($cat) . '</span>';
    }
    $map = [
        'immature'  => 'warning',
        'generate'  => 'success',
        'generated' => 'success',
        'stake'     => 'info',
        'orphan'    => 'danger',
    ];
    $bs = $map[$cat] ?? 'secondary';
    return '<span class="badge bg-' . $bs . ($bs === 'warning' || $bs === 'info' ? ' text-dark' : '') . '">'
         . Html::encode($cat) . '</span>';
};

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search...">
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
.actions { width: 120px; text-align: right; }
table.dataGrid a.red { color: darkred; }
table.totals { margin-top: 8px; margin-left: 16px; display: inline-block; }
table.totals th { text-align: left; width: 100px; }
table.totals td { text-align: right; }
.page .footer { width: auto; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	widgets: ['zebra','filter','Storage','saveSort'],
	textExtraction: {
		6: function(node, table, n) { return \$(node).attr('data'); },
		7: function(node, table, n) { return \$(node).attr('data'); }
	},
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
    <th data-sorter="currency">Quantity</th>
    <th data-sorter="currency">BTC</th>
    <th data-sorter="numeric">Block</th>
    <th data-sorter="">Status</th>
    <th data-sorter="numeric">Sent</th>
    <th data-sorter="" class="actions">Actions</th>
</tr>
</thead><tbody>
<?php foreach ($rows as $r):
    $earning = $r['earning']; $rowCoin = $r['rowCoin'];
    $user = $r['user']; $block = $r['block'];
?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($rowCoin->image), ['width' => 16, 'alt' => Html::encode($rowCoin->symbol)]) ?></td>
    <td><b><?= Html::a(Html::encode($rowCoin->name), ['/admin/coin', 'id' => $rowCoin->id]) ?></b>&nbsp;(<?= Html::encode($rowCoin->symbol_show) ?>)</td>
    <td><b><?= Html::a(Html::encode($user->username), ['/?address=' . urlencode($user->username)]) ?></b></td>
    <td><?= $cu->bitcoinvaluetoa($earning->amount) ?></td>
    <td><?= $cu->bitcoinvaluetoa($earning->amount * $earning->price) ?></td>
    <td><?= (int) $block->height ?></td>
    <td data="<?= (int) $block->height ?>"><?= Html::encode($block->category) ?> (<?= (int) $block->confirmations ?>)</td>
    <td data="<?= (int) $earning->create_time ?>"><?= $r['t1'] ?> <?= $r['t2'] ?></td>
    <td class="actions">
        <?= Html::a('clear',  ['/admin/clearearning',  'id' => $earning->id]) ?>
        <?= Html::a('delete', ['/admin/deleteearning', 'id' => $earning->id], ['class' => 'red']) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr><th colspan="9">
    <?= count($rows) ?> records<?= count($earnings) >= 1500 ? ' (limit reached)' : '' ?>
</th></tr>
</tfoot>
</table>

<?php if ($coinId && $coin): ?>
<div class="totals" align="right">
    <table class="totals">
        <?php foreach ($totals as [$label, $val]): ?>
        <tr><th><?= Html::encode($label) ?></th><td><?= $val ?> <?= Html::encode($symbol) ?></td></tr>
        <?php endforeach ?>
    </table>
    <table class="totals">
        <?php foreach ($wallet as [$label, $val, $title]): ?>
        <tr<?= $title ? ' title="' . Html::encode($title) . '"' : '' ?>><th><?= Html::encode($label) ?></th><td><?= $val ?> <?= Html::encode($symbol) ?></td></tr>
        <?php endforeach ?>
    </table>
</div>
<?php endif ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm<?= $coinId ? ' mb-3' : '' ?>">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <span class="badge bg-secondary"><?= count($rows) ?> records</span>
        <?php if (count($earnings) >= 1500): ?>
            <span class="badge bg-warning text-dark">limit reached</span>
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
        <th data-sorter="currency" class="text-end">Quantity</th>
        <th data-sorter="currency" class="text-end">BTC value</th>
        <th data-sorter="numeric" class="text-end">Block</th>
        <th data-sorter="text">Status</th>
        <th data-sorter="numeric">Sent</th>
        <th data-sorter="false" class="text-end" style="width:120px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $earning = $r['earning']; $rowCoin = $r['rowCoin'];
        $user    = $r['user'];    $block   = $r['block'];
    ?>
    <tr>
        <td><?php if (!empty($rowCoin->image)): ?>
            <img src="<?= Html::encode($rowCoin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td>
            <?= Html::a('<strong>' . Html::encode($rowCoin->name) . '</strong>',
                ['/admin/coin', 'id' => $rowCoin->id], ['encode' => false]) ?>
            <small class="text-muted">(<?= Html::encode($rowCoin->symbol_show) ?>)</small>
        </td>
        <td>
            <?= Html::a(Html::encode($user->username), '/?address=' . urlencode($user->username)) ?>
        </td>
        <td class="text-end small font-monospace"><?= $cu->bitcoinvaluetoa($earning->amount) ?></td>
        <td class="text-end small font-monospace"><?= $cu->bitcoinvaluetoa($earning->amount * $earning->price) ?></td>
        <td class="text-end small tabular-nums"><?= (int) $block->height ?></td>
        <td data="<?= (int) $block->height ?>">
            <?= $categoryBadge($block->category) ?>
            <small class="text-muted ms-1">(<?= (int) $block->confirmations ?>)</small>
        </td>
        <td class="small text-muted" data="<?= (int) $earning->create_time ?>">
            <?= $r['t1'] ?> <?= $r['t2'] ?>
        </td>
        <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
                <?= Html::a('clear',  ['/admin/clearearning',  'id' => $earning->id],
                    ['class' => 'btn btn-sm btn-outline-secondary py-0']) ?>
                <?= Html::a('delete', ['/admin/deleteearning', 'id' => $earning->id],
                    ['class' => 'btn btn-sm btn-outline-danger py-0']) ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
    <tr><th colspan="9" class="small text-muted">
        <?= count($rows) ?> records<?= count($earnings) >= 1500 ? ' — limit reached' : '' ?>
    </th></tr>
    </tfoot>
    </table>
    </div>
    </div>
</div>

<?php if ($coinId && $coin): ?>
<div class="row g-3 justify-content-end">
    <div class="col-auto">
        <div class="card shadow-sm">
            <div class="card-header py-2 small fw-semibold">Earnings</div>
            <div class="card-body py-2 px-3">
            <table class="table table-sm mb-0" style="min-width:200px;">
            <?php foreach ($totals as [$label, $val]): ?>
            <tr><th class="text-muted small fw-normal pe-3"><?= Html::encode($label) ?></th>
                <td class="text-end font-monospace small"><?= $val ?> <span class="text-muted"><?= Html::encode($symbol) ?></span></td></tr>
            <?php endforeach ?>
            </table>
            </div>
        </div>
    </div>
    <div class="col-auto">
        <div class="card shadow-sm">
            <div class="card-header py-2 small fw-semibold">Wallet</div>
            <div class="card-body py-2 px-3">
            <table class="table table-sm mb-0" style="min-width:200px;">
            <?php foreach ($wallet as [$label, $val, $title]): ?>
            <tr<?= $title ? ' title="' . Html::encode($title) . '"' : '' ?>>
                <th class="text-muted small fw-normal pe-3"><?= Html::encode($label) ?></th>
                <td class="text-end font-monospace small"><?= $val ?> <span class="text-muted"><?= Html::encode($symbol) ?></span></td>
            </tr>
            <?php endforeach ?>
            </table>
            </div>
        </div>
    </div>
</div>
<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter','Storage','saveSort'],
        textExtraction: {
            6: function(node,table,n){ return \$(node).attr('data'); },
            7: function(node,table,n){ return \$(node).attr('data'); }
        },
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
            <?= count($rows) ?> records
        </span>
        <?php if (count($earnings) >= 1500): ?>
        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-50 dark:bg-amber-900/30
                     text-amber-700 dark:text-amber-300 font-medium">limit reached</span>
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
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Quantity</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">BTC value</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Block</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Status</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">Sent</th>
        <th class="px-3 py-2.5 text-right" data-sorter="false">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $earning = $r['earning']; $rowCoin = $r['rowCoin'];
        $user    = $r['user'];    $block   = $r['block'];
    ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">

        <td class="px-3 py-2">
            <?php if (!empty($rowCoin->image)): ?>
                <img src="<?= Html::encode($rowCoin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode($rowCoin->name),
                    ['/admin/coin', 'id' => $rowCoin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="font-mono text-gray-400 dark:text-gray-500">
                <?= Html::encode($rowCoin->symbol_show) ?>
            </div>
        </td>

        <td class="px-3 py-2">
            <?= Html::a(Html::encode($user->username),
                '/?address=' . urlencode($user->username),
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= $cu->bitcoinvaluetoa($earning->amount) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= $cu->bitcoinvaluetoa($earning->amount * $earning->price) ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= (int) $block->height ?>
        </td>

        <td class="px-3 py-2" data="<?= (int) $block->height ?>">
            <?= $categoryBadge($block->category) ?>
            <span class="text-gray-400 dark:text-gray-500 ml-1">(<?= (int) $block->confirmations ?>)</span>
        </td>

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= (int) $earning->create_time ?>">
            <?= $r['t1'] ?>
            <?php if ($r['t2']): ?>
                <span class="text-indigo-400 dark:text-indigo-500 ml-1"><?= $r['t2'] ?></span>
            <?php endif ?>
        </td>

        <td class="px-3 py-2 text-right">
            <div class="flex items-center justify-end gap-2">
                <?= Html::a('clear',  ['/admin/clearearning',  'id' => $earning->id],
                    ['class' => 'text-xs text-gray-400 dark:text-gray-500 hover:underline transition-colors']) ?>
                <?= Html::a('delete', ['/admin/deleteearning', 'id' => $earning->id],
                    ['class' => 'text-xs text-red-500 dark:text-red-400 hover:underline transition-colors']) ?>
            </div>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= count($rows) ?> records<?= count($earnings) >= 1500 ? ' — limit reached' : '' ?>
    </div>
</div>

<?php if ($coinId && $coin): ?>
<div class="flex flex-wrap justify-end gap-4">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700
                    text-xs font-semibold text-gray-600 dark:text-gray-300">
            Earnings
        </div>
        <table class="text-xs" style="min-width:200px;">
        <?php foreach ($totals as [$label, $val]): ?>
        <tr class="border-b border-gray-100 dark:border-gray-700/50 last:border-0">
            <td class="px-4 py-1.5 text-gray-400 dark:text-gray-500"><?= Html::encode($label) ?></td>
            <td class="px-4 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                <?= $val ?>
                <span class="text-gray-400 dark:text-gray-500 ml-0.5"><?= Html::encode($symbol) ?></span>
            </td>
        </tr>
        <?php endforeach ?>
        </table>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700
                    text-xs font-semibold text-gray-600 dark:text-gray-300">
            Wallet
        </div>
        <table class="text-xs" style="min-width:200px;">
        <?php foreach ($wallet as [$label, $val, $title]): ?>
        <tr class="border-b border-gray-100 dark:border-gray-700/50 last:border-0"
            <?= $title ? 'title="' . Html::encode($title) . '"' : '' ?>>
            <td class="px-4 py-1.5 text-gray-400 dark:text-gray-500"><?= Html::encode($label) ?></td>
            <td class="px-4 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
                <?= $val ?>
                <span class="text-gray-400 dark:text-gray-500 ml-0.5"><?= Html::encode($symbol) ?></span>
            </td>
        </tr>
        <?php endforeach ?>
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
