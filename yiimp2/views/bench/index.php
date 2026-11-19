<?php
/**
 * @var yii\web\View   $this
 * @var string         $algo
 * @var string         $vid
 * @var int            $idchip
 * @var array          $algos     [algo => count]
 * @var array          $chips     [idchip => name]
 * @var array          $rows
 * @var array|null     $avg
 * @var bool           $isAdmin
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Benchmarks';
$cu          = Yii::$app->ConversionUtils;
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

// ── Dropdown options ──────────────────────────────────────────────────────────
$algoOptions = '<option value="all">Show all</option>';
foreach ($algos as $a => $count) {
    $sel          = ($a === $algo) ? ' selected' : '';
    $algoOptions .= "<option value=\"{$a}\"{$sel}>{$a}</option>";
}
$chipOptions = '<option value="0">Show all</option>';
foreach ($chips as $id => $name) {
    $sel          = ($id == $idchip) ? ' selected' : '';
    $chipOptions .= "<option value=\"{$id}\"{$sel}>{$name}</option>";
}

// ── Shared per-row computation ────────────────────────────────────────────────
$resolved = [];
foreach ($rows as $row) {
    if (($row['chip_name'] ?? $row['chip'] ?? '') === 'Virtual') continue;

    $hashrate = $cu->Itoa2(1000 * round($row['khps'], 3), 2) . 'H';
    if ($row['algo'] === 'equihash') {
        $hashrate = $cu->Itoa2(1000 * round($row['khps'], 5), 3) . '&nbsp;Sol/s';
    }

    $chip     = $row['chip_name'] ?? $row['chip'] ?? '';
    $chipLink = $row['idchip']
        ? Html::a(Html::encode($chip), ['/bench', 'chip' => $row['idchip']])
        : Html::encode($chip);

    $device = Html::encode(BenchService::formatDevice($row));
    $arch   = $row['type'] === 'gpu'
        ? Html::encode(BenchService::formatCudaArch((string)($row['arch'] ?? '')))
        : Html::encode((string)($row['arch'] ?? ''));

    $freqContent = $row['freq'] ?: '-';
    $freqTitle   = '';
    $freqClass   = '';
    if ((int)($row['realfreq'] ?? 0) > ($row['freq'] ?? 0) / 10) {
        $freqContent = $row['realfreq'];
        $freqClass   = 'real';
        $freqTitle   = "Base clock: {$row['freq']} MHz\nMem clock: {$row['realmemf']} MHz";
    } elseif ($row['memf'] ?? 0) {
        $freqTitle = "Mem Clock: {$row['memf']} MHz";
    }

    $factor       = in_array($row['chip'], ['750', '750 Ti', 'Quadro K620']) ? 2.0 : 1.0;
    $power        = (float)($row['power'] ?? 0) * $factor;
    $powerContent = $power > 0 ? $power : '-';
    $powerTitle   = '';
    $powerClass   = '';
    if ($row['plimit'] ?? 0) { $powerTitle = "Power limit {$row['plimit']}W"; $powerClass = 'limited'; }

    $hpw = ($power > 0) ? $cu->Itoa2(1000 * round($row['khps'] / $power, 4), 3) : '-';

    if ($row['type'] === 'cpu') {
        $intContent = $row['throughput'] ?? '';
        $intTitle   = '';
    } elseif ($row['algo'] === 'neoscrypt') {
        $intContent = ($row['throughput'] ?? '') . '*';
        $intTitle   = 'neoscrypt intensity is different';
    } else {
        $intContent = $row['intensity'] ?: '-';
        $intTitle   = ($row['throughput'] ?? '') . ' threads';
    }

    $resolved[] = compact('row', 'hashrate', 'chipLink', 'device', 'arch',
        'freqContent', 'freqTitle', 'freqClass',
        'powerContent', 'powerTitle', 'powerClass', 'hpw',
        'intContent', 'intTitle');
}

// ── Average footer row ────────────────────────────────────────────────────────
$avgRow = null;
if ($avg && (int)($avg['records'] ?? 0) > 0) {
    $avgPower = (float)($avg['power'] ?? 0);
    $avgHpw   = $avgPower > 0 ? $cu->Itoa2(1000 * round((float)$avg['khps'] / $avgPower, 3), 2) : '';
    $avgRow   = compact('avgPower', 'avgHpw');
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
td.limited { color:silver; }
td.real    { color:black; }
.page .footer { width:auto; }
</style>
<div style="text-align:right; margin-top:-22px; margin-right:145px;">
    Select Algo: <select class="filter" id="algo_select"><?= $algoOptions ?></select>&nbsp;
    Chip: <select class="filter" id="chip_select"><?= $chipOptions ?></select>&nbsp;
</div>
<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
    <?php if ($algo === 'all'): ?>Last 100 results
    <?php else: ?>Last 100 <?= Html::encode($algo) ?> results,
        <?= Html::a('show totals', ['/bench/algo', 'algo' => $algo]) ?>
    <?php endif ?>
</p>
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    textExtraction: {
        1: function(node,table,n){ return \$(node).attr('data'); },
        5: function(node,table,n){ return \$(node).attr('data'); },
        6: function(node,table,n){ return \$(node).attr('data'); }
    },
    widgetOptions: {
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead><tr>
    <th>Algo</th><th>Time</th><th>Chip</th><th>Device</th><th>Vendor ID</th>
    <th>Arch</th><th>Hashrate</th>
    <th title="Intensity (-i) for GPU or Threads (-t) for CPU">Int.</th>
    <th title="MHz">Freq</th><th title="Watts">W</th><th title="Efficiency">H/W</th>
    <th>Client</th><th>OS</th><th>Driver / Compiler</th>
    <?= $isAdmin ? '<th width="30" data-sorter="">Admin</th>' : '' ?>
</tr></thead><tbody>
<?php foreach ($resolved as $r):
    $row = $r['row']; ?>
<tr class="ssrow">
    <td><?= Html::a(Html::encode($row['algo']), ['/bench', 'algo' => $row['algo']]) ?></td>
    <td data="<?= (int)$row['time'] ?>"><?= $cu->datetoa2($row['time']) ?></td>
    <td><?= $r['chipLink'] ?></td>
    <td><?= $r['device'] ?></td>
    <td><?= Html::a(Html::encode($row['vendorid'] ?? ''), ['/bench', 'vid' => $row['vendorid'] ?? '']) ?></td>
    <td><?= $r['arch'] ?></td>
    <td data="<?= (float)$row['khps'] ?>"><?= $r['hashrate'] ?></td>
    <td data="<?= $row['throughput'] ?? 0 ?>" title="<?= Html::encode($r['intTitle']) ?>"><?= Html::encode((string)$r['intContent']) ?></td>
    <td class="<?= $r['freqClass'] ?>" title="<?= Html::encode($r['freqTitle']) ?>"><?= Html::encode((string)$r['freqContent']) ?></td>
    <td class="<?= $r['powerClass'] ?>" title="<?= Html::encode($r['powerTitle']) ?>"><?= Html::encode((string)$r['powerContent']) ?></td>
    <td><?= $r['hpw'] ?></td>
    <td><?= Html::encode($row['client'] ?? '') ?></td>
    <td><?= Html::encode($row['os'] ?? '') ?></td>
    <td><?= Html::encode($row['driver'] ?? '') ?></td>
    <?php if ($isAdmin): ?><td><?= Html::a('del', ['/admin/benchdel', 'id' => $row['id']], ['style' => 'color:darkred']) ?></td><?php endif ?>
</tr>
<?php endforeach ?>
</tbody>
<?php if ($avgRow): ?>
<tfoot><tr class="ssfoot">
    <th><?= Html::a(Html::encode($algo), ['/bench', 'algo' => $algo]) ?></th>
    <th>&nbsp;</th>
    <th colspan="4">Average (<?= (int)$avg['records'] ?> records)</th>
    <th><?= $cu->Itoa2(1000 * round($avg['khps'], 3), 2) ?>H</th>
    <th><?= $avg['intensity'] ? round($avg['intensity'], 1) : '' ?></th>
    <th><?= $avg['freq'] ? round($avg['freq']) : '' ?></th>
    <th><?= $avgRow['avgPower'] > 0 ? round($avgRow['avgPower']) : '' ?></th>
    <th><?= $avgRow['avgHpw'] ?></th>
    <th colspan="<?= $isAdmin ? 4 : 3 ?>">&nbsp;</th>
</tr></tfoot>
<?php endif ?>
</table><br>
<p>
    <?= Html::a('Show current devices in the database', ['/bench/devices']) ?><br>
    <?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br>
</p>
<script>
$('select.filter').on('change', function () {
    window.location.href = '/bench?algo=' + $('#algo_select').val() + '&chip=' + $('#chip_select').val();
});
</script>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <strong class="small">Benchmarks</strong>
        <span class="badge bg-secondary ms-1"><?= count($resolved) ?> results</span>

        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
            <label class="text-muted small mb-0">Algo:</label>
            <select class="filter form-select form-select-sm" id="algo_select" style="width:120px;">
                <?= $algoOptions ?>
            </select>
            <label class="text-muted small mb-0">Chip:</label>
            <select class="filter form-select form-select-sm" id="chip_select" style="width:160px;">
                <?= $chipOptions ?>
            </select>
            <input class="search form-control form-control-sm" type="search"
                   style="width:140px;" placeholder="Search…">
        </div>
    </div>

    <div class="card-body p-2 border-bottom">
        <span class="small fw-semibold">
            <?php if ($algo === 'all'): ?>Last 100 results
            <?php else: ?>Last 100 <strong><?= Html::encode($algo) ?></strong> results —
                <?= Html::a('show totals', ['/bench/algo', 'algo' => $algo]) ?>
            <?php endif ?>
        </span>
    </div>

    <div class="card-body p-0">
    <div class="overflow-auto">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="text">Algo</th>
        <th data-sorter="numeric">Time</th>
        <th data-sorter="text">Chip</th>
        <th data-sorter="text">Device</th>
        <th data-sorter="text">VID</th>
        <th data-sorter="text">Arch</th>
        <th data-sorter="numeric" class="text-end">Hashrate</th>
        <th data-sorter="numeric" class="text-end" title="Intensity / Threads">Int.</th>
        <th data-sorter="numeric" class="text-end" title="MHz">Freq</th>
        <th data-sorter="numeric" class="text-end" title="Watts">W</th>
        <th data-sorter="numeric" class="text-end" title="Efficiency">H/W</th>
        <th data-sorter="text">Client</th>
        <th data-sorter="text">OS</th>
        <th data-sorter="text">Driver</th>
        <?= $isAdmin ? '<th data-sorter="false"></th>' : '' ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resolved as $r):
        $row = $r['row']; ?>
    <tr>
        <td class="small"><?= Html::a(Html::encode($row['algo']), ['/bench', 'algo' => $row['algo']]) ?></td>
        <td class="small text-muted" data="<?= (int)$row['time'] ?>"><?= $cu->datetoa2($row['time']) ?></td>
        <td class="small"><?= $r['chipLink'] ?></td>
        <td class="small"><?= $r['device'] ?></td>
        <td class="small font-monospace"><?= Html::a(Html::encode($row['vendorid'] ?? ''), ['/bench', 'vid' => $row['vendorid'] ?? '']) ?></td>
        <td class="small font-monospace"><?= $r['arch'] ?></td>
        <td class="text-end small font-monospace" data="<?= (float)$row['khps'] ?>"><?= $r['hashrate'] ?></td>
        <td class="text-end small tabular-nums" data="<?= $row['throughput'] ?? 0 ?>"
            title="<?= Html::encode($r['intTitle']) ?>"><?= Html::encode((string)$r['intContent']) ?></td>
        <td class="text-end small tabular-nums <?= $r['freqClass'] === 'real' ? 'text-success' : '' ?>"
            title="<?= Html::encode($r['freqTitle']) ?>"><?= Html::encode((string)$r['freqContent']) ?></td>
        <td class="text-end small tabular-nums <?= $r['powerClass'] === 'limited' ? 'text-muted' : '' ?>"
            title="<?= Html::encode($r['powerTitle']) ?>"><?= Html::encode((string)$r['powerContent']) ?></td>
        <td class="text-end small font-monospace"><?= $r['hpw'] ?></td>
        <td class="small"><?= Html::encode($row['client'] ?? '') ?></td>
        <td class="small"><?= Html::encode($row['os'] ?? '') ?></td>
        <td class="small"><?= Html::encode($row['driver'] ?? '') ?></td>
        <?php if ($isAdmin): ?>
        <td><?= Html::a('del', ['/admin/benchdel', 'id' => $row['id']],
            ['class' => 'btn btn-sm btn-outline-danger py-0']) ?></td>
        <?php endif ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    <?php if ($avgRow): ?>
    <tfoot class="table-light">
    <tr>
        <th class="small"><?= Html::a(Html::encode($algo), ['/bench', 'algo' => $algo]) ?></th>
        <th></th>
        <th colspan="4" class="small text-muted">Average (<?= (int)$avg['records'] ?> records)</th>
        <th class="text-end small font-monospace"><?= $cu->Itoa2(1000 * round($avg['khps'], 3), 2) ?>H</th>
        <th class="text-end small"><?= $avg['intensity'] ? round($avg['intensity'], 1) : '' ?></th>
        <th class="text-end small"><?= $avg['freq'] ? round($avg['freq']) : '' ?></th>
        <th class="text-end small"><?= $avgRow['avgPower'] > 0 ? round($avgRow['avgPower']) : '' ?></th>
        <th class="text-end small font-monospace"><?= $avgRow['avgHpw'] ?></th>
        <th colspan="<?= $isAdmin ? 4 : 3 ?>"></th>
    </tr>
    </tfoot>
    <?php endif ?>
    </table>
    </div>
    </div>

    <div class="card-footer d-flex gap-3 small py-2">
        <?= Html::a('Devices in database', ['/bench/devices']) ?>
        <?= Html::a('How to submit results', ['/site/benchmarks']) ?>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter'],
        textExtraction: {
            1: function(node,table,n){ return \$(node).attr('data'); },
            5: function(node,table,n){ return \$(node).attr('data'); },
            6: function(node,table,n){ return \$(node).attr('data'); }
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
    window.location.href = '/bench?algo=' + $('#algo_select').val() + '&chip=' + $('#chip_select').val();
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
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Benchmarks</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300"><?= count($resolved) ?> results</span>

        <div class="flex items-center gap-2 ml-auto flex-wrap">
            <label class="text-xs text-gray-400 dark:text-gray-500">Algo:</label>
            <select class="filter" id="algo_select"
                    class="px-2 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?= $algoOptions ?>
            </select>
            <label class="text-xs text-gray-400 dark:text-gray-500">Chip:</label>
            <select class="filter" id="chip_select"
                    class="px-2 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?= $chipOptions ?>
            </select>
            <input class="search px-3 py-1.5 text-sm rounded-lg border
                          border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-indigo-500
                          placeholder-gray-400 dark:placeholder-gray-500"
                   type="search" style="width:140px;" placeholder="Search…">
        </div>
    </div>

    <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold
                text-gray-700 dark:text-gray-300">
        <?php if ($algo === 'all'): ?>Last 100 results
        <?php else: ?>
            Last 100 <span class="text-indigo-600 dark:text-indigo-400 font-mono"><?= Html::encode($algo) ?></span> results
            — <?= Html::a('show totals →', ['/bench/algo', 'algo' => $algo],
                ['class' => 'text-indigo-500 hover:underline text-xs']) ?>
        <?php endif ?>
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 text-left" data-sorter="text">Algo</th>
        <th class="px-3 py-2.5 text-left" data-sorter="numeric">Time</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Chip</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Device</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">VID</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Arch</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Hashrate</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="Intensity / Threads">Int.</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="MHz">Freq</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="Watts">W</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric" title="Efficiency">H/W</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Client</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">OS</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Driver</th>
        <?= $isAdmin ? '<th class="px-3 py-2.5" data-sorter="false"></th>' : '' ?>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($resolved as $r):
        $row = $r['row']; ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 font-mono">
            <?= Html::a('<span class="text-indigo-600 dark:text-indigo-400">' . Html::encode($row['algo']) . '</span>',
                ['/bench', 'algo' => $row['algo']], ['encode' => false]) ?>
        </td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= (int)$row['time'] ?>"><?= $cu->datetoa2($row['time']) ?></td>
        <td class="px-3 py-2 text-gray-700 dark:text-gray-300"><?= $r['chipLink'] ?></td>
        <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><?= $r['device'] ?></td>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500">
            <?= Html::a(Html::encode($row['vendorid'] ?? ''), ['/bench', 'vid' => $row['vendorid'] ?? '']) ?>
        </td>
        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400"><?= $r['arch'] ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold
                   text-gray-800 dark:text-gray-200"
            data="<?= (float)$row['khps'] ?>"><?= $r['hashrate'] ?></td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300"
            data="<?= $row['throughput'] ?? 0 ?>"
            title="<?= Html::encode($r['intTitle']) ?>"><?= Html::encode((string)$r['intContent']) ?></td>
        <td class="px-3 py-2 text-right tabular-nums
                   <?= $r['freqClass'] === 'real' ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>"
            title="<?= Html::encode($r['freqTitle']) ?>"><?= Html::encode((string)$r['freqContent']) ?></td>
        <td class="px-3 py-2 text-right tabular-nums
                   <?= $r['powerClass'] === 'limited' ? 'text-gray-300 dark:text-gray-600' : 'text-gray-500 dark:text-gray-400' ?>"
            title="<?= Html::encode($r['powerTitle']) ?>"><?= Html::encode((string)$r['powerContent']) ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400"><?= $r['hpw'] ?></td>
        <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?= Html::encode($row['client'] ?? '') ?></td>
        <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?= Html::encode($row['os'] ?? '') ?></td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500"><?= Html::encode($row['driver'] ?? '') ?></td>
        <?php if ($isAdmin): ?>
        <td class="px-3 py-2 text-right">
            <?= Html::a('del', ['/admin/benchdel', 'id' => $row['id']],
                ['class' => 'text-xs text-red-500 dark:text-red-400 hover:underline']) ?>
        </td>
        <?php endif ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    <?php if ($avgRow): ?>
    <tfoot>
    <tr class="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700
               text-xs font-medium text-gray-600 dark:text-gray-400">
        <td class="px-3 py-2 font-mono text-indigo-600 dark:text-indigo-400">
            <?= Html::encode($algo) ?>
        </td>
        <td></td>
        <td colspan="4" class="px-3 py-2 text-gray-400 dark:text-gray-500">
            Average (<?= (int)$avg['records'] ?> records)
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold text-gray-800 dark:text-gray-200">
            <?= $cu->Itoa2(1000 * round($avg['khps'], 3), 2) ?>H
        </td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $avg['intensity'] ? round($avg['intensity'], 1) : '' ?></td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $avg['freq'] ? round($avg['freq']) : '' ?></td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $avgRow['avgPower'] > 0 ? round($avgRow['avgPower']) : '' ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums"><?= $avgRow['avgHpw'] ?></td>
        <td colspan="<?= $isAdmin ? 4 : 3 ?>"></td>
    </tr>
    </tfoot>
    <?php endif ?>
    </table>
    </div>

    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
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
                var n = parseFloat(av) - parseFloat(bv);
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
    window.location.href = '/bench?algo=' + encodeURIComponent($('#algo_select').val())
                         + '&chip=' + encodeURIComponent($('#chip_select').val());
});
</script>

<?php endif ?>
