<?php
/**
 * @var yii\web\View $this
 * @var array        $devices      rows: device, type, chip, idchip, vendorid
 * @var string[]     $algos        distinct algo names (last 30 days, max 20)
 * @var array        $gpuCoverage  [vendorid => [algo, ...]]
 * @var array        $cpuCoverage  [device  => [algo, ...]]
 */

use yii\helpers\Html;
use app\services\BenchService;

$this->title = 'Devices';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

// ── Shared row resolution ─────────────────────────────────────────────────────
$resolved = [];
foreach ($devices as $row) {
    if (($row['chip'] ?? '') === 'Virtual') continue;

    $vendorid = $row['vendorid'] ?? '';
    $chip     = $row['chip'] ?? '';
    if (empty($chip)) $chip = BenchService::formatCPUPublic($row);

    $chipCell = $row['idchip']
        ? Html::a(Html::encode($chip), ['/bench', 'chip' => $row['idchip'], 'algo' => 'all'])
        : Html::encode($chip);

    $deviceLabel = Html::encode(BenchService::formatDevice($row));

    if (str_starts_with($vendorid, '10de')) {
        $vidCell = '<span title="nVidia product id" class="text-muted">' . Html::encode($vendorid) . '</span>';
    } else {
        $vidCell = $vendorid
            ? Html::a(Html::encode($vendorid), ['/bench', 'vid' => $vendorid])
            : '';
    }

    $covered   = $vendorid
        ? ($gpuCoverage[$vendorid] ?? [])
        : ($cpuCoverage[$row['device']] ?? []);

    $resolved[] = compact('row', 'chipCell', 'deviceLabel', 'vidCell', 'covered');
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
td.tick { font-weight:bolder; }
span.generic { color:gray; }
.page .footer { width:auto; }
</style>
<p style="margin-top:-20px; margin-bottom:4px; line-height:22px; font-weight:bolder;">
    Devices in database
</p>
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th width="70">Chip</th>
    <th width="220">Device</th>
    <th width="70">Vendor ID</th>
    <?php foreach ($algos as $a): ?><th><?= Html::encode($a) ?></th><?php endforeach ?>
</tr>
</thead><tbody>
<?php foreach ($resolved as $r): ?>
<tr class="ssrow">
    <td><?= $r['chipCell'] ?></td>
    <td><?= $r['deviceLabel'] ?></td>
    <td><?= $r['vidCell'] ?></td>
    <?php foreach ($algos as $a):
        $tick = in_array($a, $r['covered'])
            ? Html::a('✓', array_filter(['/bench', 'algo' => $a, 'chip' => $r['row']['idchip'] ?: null]))
            : '&nbsp;'; ?>
    <td class="tick"><?= $tick ?></td>
    <?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody></table><br>
<?= Html::a('Learn how to submit your results', ['/site/benchmarks']) ?><br><br>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <strong class="small">Devices in database</strong>
        <span class="badge bg-secondary ms-1"><?= count($resolved) ?></span>
        <input class="search form-control form-control-sm ms-auto"
               type="search" style="width:160px;" placeholder="Search…">
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="text" style="width:100px">Chip</th>
        <th data-sorter="text" style="width:240px">Device</th>
        <th data-sorter="text" style="width:90px">Vendor ID</th>
        <?php foreach ($algos as $a): ?>
        <th data-sorter="false" class="text-center" style="width:36px;font-size:10px;">
            <?= Html::encode($a) ?>
        </th>
        <?php endforeach ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resolved as $r): ?>
    <tr>
        <td class="small"><?= $r['chipCell'] ?></td>
        <td class="small"><?= $r['deviceLabel'] ?></td>
        <td class="small font-monospace"><?= $r['vidCell'] ?></td>
        <?php foreach ($algos as $a):
            $has = in_array($a, $r['covered']); ?>
        <td class="text-center">
            <?php if ($has): ?>
                <?= Html::a('✓', array_filter(['/bench', 'algo' => $a, 'chip' => $r['row']['idchip'] ?: null]),
                    ['class' => 'text-success fw-bold text-decoration-none']) ?>
            <?php else: ?>
                <span class="text-muted">·</span>
            <?php endif ?>
        </td>
        <?php endforeach ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    </div>
    <div class="card-footer small py-2">
        <?= Html::a('How to submit results', ['/site/benchmarks']) ?>
    </div>
</div>
<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter'],
        widgetOptions: {
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
                flex items-center gap-3">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Devices in database</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300"><?= count($resolved) ?></span>
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
        <th class="px-3 py-2.5 text-left" style="min-width:90px" data-sorter="text">Chip</th>
        <th class="px-3 py-2.5 text-left" style="min-width:200px" data-sorter="text">Device</th>
        <th class="px-3 py-2.5 text-left" style="min-width:80px" data-sorter="text">VID</th>
        <?php foreach ($algos as $a): ?>
        <th class="px-2 py-2.5 text-center" style="min-width:36px" data-sorter="false"
            title="<?= Html::encode($a) ?>">
            <span class="text-gray-400"><?= Html::encode(substr($a, 0, 6)) ?></span>
        </th>
        <?php endforeach ?>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($resolved as $r): ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 text-gray-700 dark:text-gray-300"><?= $r['chipCell'] ?></td>
        <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><?= $r['deviceLabel'] ?></td>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500"><?= $r['vidCell'] ?></td>
        <?php foreach ($algos as $a):
            $has = in_array($a, $r['covered']); ?>
        <td class="px-2 py-2 text-center">
            <?php if ($has): ?>
                <?= Html::a('✓', array_filter(['/bench', 'algo' => $a, 'chip' => $r['row']['idchip'] ?: null]),
                    ['class' => 'text-green-500 dark:text-green-400 font-bold no-underline']) ?>
            <?php else: ?>
                <span class="text-gray-200 dark:text-gray-700">·</span>
            <?php endif ?>
        </td>
        <?php endforeach ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
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
                var av = (a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].textContent || '').trim();
                return asc[col] ? av.localeCompare(bv) : bv.localeCompare(av);
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
