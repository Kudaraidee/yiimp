<?php
/**
 * @var yii\web\View $this
 * @var string       $algo
 * @var array        $algos    [algo => count]
 * @var array        $rows     aggregated per-chip rows
 * @var float        $algo24E  mBTC per kHs (24h average)
 * @var float        $btcusd   BTC/USD rate
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Algo Benchmarks';
$cu          = Yii::$app->ConversionUtils;
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

$options = '';
foreach ($algos as $a => $count) {
    $sel      = ($a === $algo) ? ' selected' : '';
    $options .= "<option value=\"{$a}\"{$sel}>{$a}</option>";
}

$kwh = defined('YIIMP_KWH_USD_PRICE') ? YIIMP_KWH_USD_PRICE : 0.25;

// ── Shared per-row computation ────────────────────────────────────────────────
$resolved = [];
foreach ($rows as $row) {
    if (($row['chip'] ?? '') === 'Virtual') continue;

    $factor   = in_array($row['chip'], ['750', '750 Ti', 'Quadro K620']) ? 2.0 : 1.0;
    $power    = (float)($row['power'] ?? 0) * $factor;
    $khps     = (float)($row['khps'] ?? 0);

    $cost   = BenchService::powercostMbtc($power, $btcusd);
    $reward = $khps * $algo24E;
    $profit = $reward - $cost;
    $ppw    = $power > 0 ? $khps / $power : 0.0;

    $chipLink = Html::a(Html::encode($row['chip'] ?? ''),
        ['/bench', 'chip' => $row['idchip'], 'algo' => $algo]);

    if ($algo === 'equihash') {
        $hashStr = round(1000 * $khps, 1) . '&nbsp;Sol/s';
        $ppwStr  = $power > 0 ? $cu->Itoa2(1000 * round($ppw, 5), 2) . '&nbsp;Sol/W' : '-';
    } else {
        $hashStr = $cu->Itoa2(1000 * round($khps, 3), 3) . 'H';
        $ppwStr  = $power > 0 ? $cu->Itoa2(1000 * round($ppw, 3), 3) . 'H' : '-';
    }

    $powerStr   = $power > 0 ? round($power) . ($factor > 1.0 ? '&nbsp;W*' : '&nbsp;W') : '-';
    $powerTitle = $factor > 1.0
        ? "Note: The {$row['chip']} power value seems to be for the chip only, x2 factor applied!" : '';

    $resolved[] = compact('row', 'power', 'khps', 'cost', 'reward', 'profit', 'ppw',
                           'chipLink', 'hashStr', 'ppwStr', 'powerStr', 'powerTitle');
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div style="text-align:right; margin-bottom:2px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
tr.ssrow.filtered { display:none; }
td.red { color:darkred; }
.page .footer { width:auto; }
</style>
<div style="text-align:right; margin-top:-22px; margin-right:145px;">
    Select Algo: <select class="filter" id="algo_select"><?= $options ?></select>&nbsp;
</div>
<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
    Overall <?= Html::encode($algo) ?> performance
</p>
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    textExtraction: {
        2: function(node,table,n){ return \$(node).attr('data'); },
        4: function(node,table,n){ return \$(node).attr('data'); }
    },
    widgetOptions: {
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th width="50">Type</th><th width="300">Chip</th>
    <th width="100">Hashrate</th><th width="80">Power</th><th width="100">H/W</th>
    <th width="100" title="mBTC/day">Cost*</th>
    <th width="100" title="mBTC/day">Reward</th>
    <th width="100" title="mBTC/day">Profit**</th>
    <th width="100">Int</th><th width="100">Freq</th>
</tr>
</thead><tbody>
<?php foreach ($resolved as $r):
    $row = $r['row']; ?>
<tr class="ssrow">
    <td><?= strtoupper(Html::encode($row['type'] ?? '')) ?></td>
    <td><?= $r['chipLink'] ?></td>
    <td data="<?= $r['khps'] ?>"><?= $r['hashStr'] ?></td>
    <td title="<?= Html::encode($r['powerTitle']) ?>"><?= $r['powerStr'] ?></td>
    <td data="<?= $r['ppw'] ?>"><?= $r['ppwStr'] ?></td>
    <td><?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['cost']) : '-' ?></td>
    <td><?= $cu->mbitcoinvaluetoa($r['reward']) ?></td>
    <td class="<?= $r['profit'] < 0 ? 'red' : '' ?>"><?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['profit']) : '-' ?></td>
    <td><?= ($row['intensity'] ?? 0) > 0 ? round($row['intensity']) : '-' ?></td>
    <td><?= ($row['freq'] ?? 0) > 0 ? round($row['freq']) : '-' ?></td>
</tr>
<?php endforeach ?>
</tbody></table><br>
<p>
    * Device power cost per day based on <?= $kwh ?> USD per kWh<br>
    ** Reward and profit are based on the average estimates from the last 24 hours<br><br>
</p>
<?= Html::a('Show current devices in the database', ['/bench/devices']) ?><br>
<?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br><br>
<script>
$('select.filter').on('change', function () {
    window.location.href = '/bench/algo?algo=' + $('#algo_select').val();
});
</script>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <strong class="small">Overall <span class="font-monospace"><?= Html::encode($algo) ?></span> performance</strong>
        <span class="badge bg-secondary ms-1"><?= count($resolved) ?></span>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <label class="text-muted small mb-0">Algo:</label>
            <select class="filter form-select form-select-sm" id="algo_select" style="width:120px;">
                <?= $options ?>
            </select>
            <input class="search form-control form-control-sm" type="search"
                   style="width:140px;" placeholder="Search…">
        </div>
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="text"    style="width:50px">Type</th>
        <th data-sorter="text">Chip</th>
        <th data-sorter="numeric" class="text-end">Hashrate</th>
        <th data-sorter="numeric" class="text-end">Power</th>
        <th data-sorter="numeric" class="text-end">H/W</th>
        <th data-sorter="numeric" class="text-end" title="mBTC/day">Cost*</th>
        <th data-sorter="numeric" class="text-end" title="mBTC/day">Reward</th>
        <th data-sorter="numeric" class="text-end" title="mBTC/day">Profit**</th>
        <th data-sorter="numeric" class="text-end">Int</th>
        <th data-sorter="numeric" class="text-end">Freq</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resolved as $r):
        $row = $r['row']; ?>
    <tr>
        <td class="small"><?= strtoupper(Html::encode($row['type'] ?? '')) ?></td>
        <td class="small"><?= $r['chipLink'] ?></td>
        <td class="text-end small font-monospace" data="<?= $r['khps'] ?>"><?= $r['hashStr'] ?></td>
        <td class="text-end small tabular-nums" title="<?= Html::encode($r['powerTitle']) ?>">
            <?= $r['powerStr'] ?>
        </td>
        <td class="text-end small font-monospace" data="<?= $r['ppw'] ?>"><?= $r['ppwStr'] ?></td>
        <td class="text-end small font-monospace"><?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['cost']) : '-' ?></td>
        <td class="text-end small font-monospace"><?= $cu->mbitcoinvaluetoa($r['reward']) ?></td>
        <td class="text-end small font-monospace <?= $r['profit'] < 0 ? 'text-danger' : 'text-success' ?>">
            <?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['profit']) : '-' ?>
        </td>
        <td class="text-end small tabular-nums"><?= ($row['intensity'] ?? 0) > 0 ? round($row['intensity']) : '-' ?></td>
        <td class="text-end small tabular-nums"><?= ($row['freq'] ?? 0) > 0 ? round($row['freq']) : '-' ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    </div>
    <div class="card-footer small py-2 text-muted">
        * Device power cost per day based on <?= $kwh ?> USD per kWh<br>
        ** Reward and profit are based on the average estimates from the last 24 hours
    </div>
</div>
<div class="mt-2 small">
    <?= Html::a('Devices in database', ['/bench/devices']) ?> &nbsp;·&nbsp;
    <?= Html::a('How to submit results', ['/site/benchmarks']) ?>
</div>
<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter'],
        textExtraction: {
            2: function(node,table,n){ return \$(node).attr('data'); },
            4: function(node,table,n){ return \$(node).attr('data'); }
        },
        widgetOptions: {
            filter_external: '.search', filter_columnFilters: false,
            filter_childRows: true, filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>
<script>
$('select.filter').on('change', function () {
    window.location.href = '/bench/algo?algo=' + encodeURIComponent($('#algo_select').val());
});
</script>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Overall
            <span class="font-mono text-indigo-600 dark:text-indigo-400 ml-1"><?= Html::encode($algo) ?></span>
            performance
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300"><?= count($resolved) ?></span>

        <div class="flex items-center gap-2 ml-auto flex-wrap">
            <label class="text-xs text-gray-400 dark:text-gray-500">Algo:</label>
            <select class="filter" id="algo_select"
                    class="px-2 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?= $options ?>
            </select>
            <input class="search px-3 py-1.5 text-sm rounded-lg border
                          border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-indigo-500
                          placeholder-gray-400 dark:placeholder-gray-500"
                   type="search" style="width:140px;" placeholder="Search…">
        </div>
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 text-left"  data-sorter="text"    style="width:50px">Type</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Chip</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Hashrate</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Power</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">H/W</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="mBTC/day">Cost*</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="mBTC/day">Reward</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="mBTC/day">Profit**</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Int</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Freq</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($resolved as $r):
        $row = $r['row'];
        $profitable = $r['profit'] >= 0; ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 font-semibold text-gray-500 dark:text-gray-400">
            <?= strtoupper(Html::encode($row['type'] ?? '')) ?>
        </td>
        <td class="px-3 py-2 text-gray-700 dark:text-gray-300"><?= $r['chipLink'] ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold
                   text-gray-800 dark:text-gray-200"
            data="<?= $r['khps'] ?>"><?= $r['hashStr'] ?></td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400"
            title="<?= Html::encode($r['powerTitle']) ?>"><?= $r['powerStr'] ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300"
            data="<?= $r['ppw'] ?>"><?= $r['ppwStr'] ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['cost']) : '-' ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-indigo-600 dark:text-indigo-400">
            <?= $cu->mbitcoinvaluetoa($r['reward']) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold
                   <?= $r['power'] <= 0 ? 'text-gray-400 dark:text-gray-600'
                       : ($profitable ? 'text-green-600 dark:text-green-400'
                                      : 'text-red-500 dark:text-red-400') ?>">
            <?= $r['power'] > 0 ? $cu->mbitcoinvaluetoa($r['profit']) : '-' ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= ($row['intensity'] ?? 0) > 0 ? round($row['intensity']) : '-' ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= ($row['freq'] ?? 0) > 0 ? round($row['freq']) : '-' ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
        <div>* Device power cost per day based on <?= $kwh ?> USD per kWh</div>
        <div>** Reward and profit based on average estimates from the last 24 hours</div>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                flex gap-4 text-xs text-gray-400 dark:text-gray-500">
        <?= Html::a('Devices in database →', ['/bench/devices'],
            ['class' => 'hover:text-indigo-500 transition-colors']) ?>
        <?= Html::a('How to submit results →', ['/site/benchmarks'],
            ['class' => 'hover:text-indigo-500 transition-colors']) ?>
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
<script>
$('select.filter').on('change', function () {
    window.location.href = '/bench/algo?algo=' + encodeURIComponent($('#algo_select').val());
});
</script>

<?php endif ?>
