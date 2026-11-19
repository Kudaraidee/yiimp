<?php

/** @var yii\web\View  $this */
/** @var string        $algo */
/** @var array[]       $rows */

use yii\helpers\Html;

$conv       = Yii::$app->ConversionUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

// ── Shared computation ────────────────────────────────────────────────────────
$computed = [];
$totalWorkers = 0;
foreach ($rows as $item) {
    $hashrate = (float) $item['hashrate'];
    $invalid  = (float) $item['invalid'];
    $percent  = $hashrate ? round($invalid * 100 / $hashrate, 3) : 0;
    $totalWorkers += (int) $item['workers'];
    $computed[] = [
        'version'  => $item['version'],
        'workers'  => (int) $item['workers'],
        'hashrate' => $hashrate,
        'invalid'  => $invalid,
        'percent'  => $percent,
    ];
}

if ($algo === '') {
    if ($isTailwind) {
        echo '<p class="text-sm text-gray-400 dark:text-gray-500">No algo selected.</p>';
    } else {
        echo '<p class="text-muted">No algo selected.</p>';
    }
    return;
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<br>
<table class="dataGrid">
<thead>
<tr>
    <th>Version</th>
    <th align="right">Workers</th>
    <th align="right">Hashrate</th>
    <th align="right">Bad</th>
    <th align="right">%</th>
</tr>
</thead>
<tbody>
<?php foreach ($computed as $r): ?>
<tr class="ssrow">
    <td><b><?= Html::encode($r['version']) ?></b></td>
    <td align="right"><?= $r['workers'] ?></td>
    <td align="right"><?= Html::encode($conv->Itoa2($r['hashrate']) . 'h/s') ?></td>
    <td align="right"><?= Html::encode($conv->Itoa2($r['invalid'])  . 'h/s') ?></td>
    <td align="right"><?= $r['percent'] ?>%</td>
</tr>
<?php endforeach ?>
<?php if (empty($computed)): ?>
<tr><td colspan="5" class="text-muted">No workers connected for algo <b><?= Html::encode($algo) ?></b>.</td></tr>
<?php endif ?>
</tbody>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <span class="badge bg-secondary"><?= $totalWorkers ?> workers</span>
        <span class="badge bg-light text-dark border font-monospace"><?= Html::encode($algo) ?></span>
    </div>

    <?php if (empty($computed)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">
            No workers connected for algo <strong><?= Html::encode($algo) ?></strong>.
        </p>
    </div>
    <?php else: ?>
    <div class="card-body p-0">
        <table class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th>Version</th>
            <th class="text-end" style="width:80px">Workers</th>
            <th class="text-end" style="width:120px">Hashrate</th>
            <th class="text-end" style="width:120px">Bad</th>
            <th class="text-end" style="width:70px">Bad %</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($computed as $r):
            $highBad = $r['percent'] > 50;
            $midBad  = $r['percent'] > 20;
        ?>
        <tr>
            <td><strong class="font-monospace"><?= Html::encode($r['version']) ?></strong></td>
            <td class="text-end tabular-nums"><?= $r['workers'] ?></td>
            <td class="text-end font-monospace tabular-nums">
                <?= Html::encode($conv->Itoa2($r['hashrate']) . 'h/s') ?>
            </td>
            <td class="text-end font-monospace tabular-nums <?= $highBad ? 'text-danger' : ($midBad ? 'text-warning' : '') ?>">
                <?= $r['invalid'] > 0 ? Html::encode($conv->Itoa2($r['invalid']) . 'h/s') : '' ?>
            </td>
            <td class="text-end tabular-nums <?= $highBad ? 'text-danger fw-bold' : ($midBad ? 'text-warning' : 'text-muted') ?>">
                <?= $r['percent'] > 0 ? $r['percent'] . '%' : '' ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="table-light">
        <tr>
            <th class="text-muted small"><?= count($computed) ?> version<?= count($computed) !== 1 ? 's' : '' ?></th>
            <th class="text-end small"><?= $totalWorkers ?></th>
            <th colspan="3"></th>
        </tr>
        </tfoot>
        </table>
    </div>
    <?php endif ?>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= $totalWorkers ?> workers
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                     text-indigo-700 dark:text-indigo-300 font-mono font-medium">
            <?= Html::encode($algo) ?>
        </span>
        <span class="text-xs text-gray-400 dark:text-gray-500">
            <?= count($computed) ?> version<?= count($computed) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($computed)): ?>
    <div class="px-4 py-6 text-sm text-gray-400 dark:text-gray-500 text-center">
        No workers connected for algo
        <span class="font-mono text-indigo-600 dark:text-indigo-400"><?= Html::encode($algo) ?></span>.
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold
               text-gray-500 dark:text-gray-400 uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-4 py-2.5 text-left">Version</th>
        <th class="px-4 py-2.5 text-right">Workers</th>
        <th class="px-4 py-2.5 text-right">Hashrate</th>
        <th class="px-4 py-2.5 text-right">Bad</th>
        <th class="px-4 py-2.5 text-right">Bad %</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($computed as $r):
        $highBad = $r['percent'] > 50;
        $midBad  = $r['percent'] > 20;
    ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-4 py-2.5 font-mono font-medium text-gray-900 dark:text-gray-100">
            <?= Html::encode($r['version']) ?>
        </td>
        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['workers'] ?>
        </td>
        <td class="px-4 py-2.5 text-right font-mono tabular-nums
                   font-semibold text-gray-800 dark:text-gray-200">
            <?= Html::encode($conv->Itoa2($r['hashrate']) . 'h/s') ?>
        </td>
        <td class="px-4 py-2.5 text-right font-mono tabular-nums
                   <?= $highBad ? 'text-red-500 dark:text-red-400'
                       : ($midBad ? 'text-amber-500 dark:text-amber-400'
                                  : 'text-gray-400 dark:text-gray-500') ?>">
            <?= $r['invalid'] > 0 ? Html::encode($conv->Itoa2($r['invalid']) . 'h/s') : '' ?>
        </td>
        <td class="px-4 py-2.5 text-right tabular-nums
                   <?= $highBad ? 'text-red-500 dark:text-red-400 font-bold'
                       : ($midBad ? 'text-amber-500 dark:text-amber-400'
                                  : 'text-gray-400 dark:text-gray-500') ?>">
            <?= $r['percent'] > 0 ? $r['percent'] . '%' : '' ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
        <span><?= count($computed) ?> version<?= count($computed) !== 1 ? 's' : '' ?></span>
        <span class="tabular-nums"><?= $totalWorkers ?> workers total</span>
    </div>
    <?php endif ?>
</div>

<?php endif ?>
