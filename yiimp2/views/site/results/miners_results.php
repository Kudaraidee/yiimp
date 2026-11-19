<?php

/** @var yii\web\View  $this             */
/** @var string        $algo             */
/** @var string        $algoUnit         */
/** @var bool          $isAdmin          */
/** @var int           $totalWorkers     */
/** @var int           $totalExtranonce  */
/** @var string        $totalHashrateFmt */
/** @var int           $totalDonators    */
/** @var string        $totalBad         */
/** @var string        $totalAvg         */
/** @var string        $totalErrorTitle  */
/** @var array[]       $versionData      */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

if (!$algo) {
    echo $isTailwind
        ? '<p class="text-sm text-gray-400 dark:text-gray-500">No algo selected.</p>'
        : '<p class="text-muted">No algo selected.</p>';
    return;
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="main-left-box">
<div class="main-left-title">Miners Version (<?= Html::encode($algo) ?>)</div>
<div class="main-left-inner">
<br/>
<table id="maintable2" class="dataGrid2">
<thead><tr>
    <th>Version</th>
    <th align="right">Count</th>
    <th align="right">Donators</th>
    <th align="right" title="Extranonce Subscribe">ES</th>
    <th align="right">Percent</th>
    <th align="right">Hashrate*</th>
    <th align="right" title="Rate per miner">Avg</th>
    <?php if ($isAdmin): ?><th align="right" class="rejects">Reject</th><?php endif ?>
</tr></thead>
<tbody>
<?php foreach ($versionData as $r): ?>
<tr class="ssrow">
    <td><b><?= Html::encode($r['version']) ?></b></td>
    <td align="right"><?= $r['count'] ?></td>
    <td align="right"><?= $r['donators'] ?: '-' ?></td>
    <td align="right"><?= $r['extranonce'] ?: '-' ?></td>
    <td align="right"><?= floatval($r['percent']) > 50 ? "<b>{$r['percent']}</b>" : $r['percent'] ?></td>
    <td align="right"><?= Html::encode($r['hashrate']) ?></td>
    <td align="right"><?= Html::encode($r['avg']) ?></td>
    <?php if ($isAdmin): ?>
    <td align="right" class="rejects" title="<?= Html::encode($r['errorTitle']) ?>"><?= Html::encode($r['bad']) ?></td>
    <?php endif ?>
</tr>
<?php endforeach ?>
</tbody>
<tr class="ssrow">
    <th><b>Total</b></th>
    <th align="right"><?= $totalWorkers ?></th>
    <th align="right"><?= $totalDonators ?></th>
    <th align="right"><?= $totalExtranonce ?></th>
    <th align="right"></th>
    <th align="right"><?= Html::encode($totalHashrateFmt) ?></th>
    <th align="right"><?= Html::encode($totalAvg) ?></th>
    <?php if ($isAdmin): ?>
    <th align="right" class="rejects" title="<?= Html::encode($totalErrorTitle) ?>"><?= Html::encode($totalBad) ?></th>
    <?php endif ?>
</tr>
</table>
<p style="font-size:.8em;">&nbsp;* approximate from the last 5 minutes submitted shares</p>
<br></div></div><br>
<?php if ($isAdmin): ?>
<script>jQuery(".rejects").show();</script>
<?php endif ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <strong class="small">Miners</strong>
        <span class="badge bg-light text-dark border font-monospace ms-1"><?= Html::encode($algo) ?></span>
        <span class="badge bg-secondary ms-1"><?= $totalWorkers ?> workers</span>
        <span class="text-muted small ms-auto font-monospace"><?= Html::encode($totalHashrateFmt) ?></span>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th>Version</th>
        <th class="text-end" style="width:60px">Count</th>
        <th class="text-end" style="width:60px" title="Donators">Don.</th>
        <th class="text-end" style="width:50px" title="Extranonce Subscribe">ES</th>
        <th class="text-end" style="width:70px">Pool %</th>
        <th class="text-end" style="width:110px">Hashrate*</th>
        <th class="text-end" style="width:100px">Avg/miner</th>
        <?php if ($isAdmin): ?><th class="text-end rejects" style="width:70px;display:none;">Reject</th><?php endif ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($versionData as $r): ?>
    <tr>
        <td class="small font-monospace"><?= Html::encode($r['version']) ?></td>
        <td class="text-end small tabular-nums"><?= $r['count'] ?></td>
        <td class="text-end small tabular-nums"><?= $r['donators'] ?: '-' ?></td>
        <td class="text-end small tabular-nums"><?= $r['extranonce'] ?: '-' ?></td>
        <td class="text-end small tabular-nums <?= floatval($r['percent']) > 50 ? 'fw-bold' : '' ?>">
            <?= Html::encode($r['percent']) ?>
        </td>
        <td class="text-end small font-monospace"><?= Html::encode($r['hashrate']) ?></td>
        <td class="text-end small font-monospace"><?= Html::encode($r['avg']) ?></td>
        <?php if ($isAdmin): ?>
        <td class="text-end small rejects <?= $r['bad'] !== '-' ? 'text-danger' : '' ?>"
            title="<?= Html::encode($r['errorTitle']) ?>" style="display:none;">
            <?= Html::encode($r['bad']) ?>
        </td>
        <?php endif ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
    <tr>
        <th class="small">Total</th>
        <th class="text-end small"><?= $totalWorkers ?></th>
        <th class="text-end small"><?= $totalDonators ?></th>
        <th class="text-end small"><?= $totalExtranonce ?></th>
        <th></th>
        <th class="text-end small font-monospace"><?= Html::encode($totalHashrateFmt) ?></th>
        <th class="text-end small font-monospace"><?= Html::encode($totalAvg) ?></th>
        <?php if ($isAdmin): ?>
        <th class="text-end small rejects <?= $totalBad ? 'text-danger' : '' ?>"
            title="<?= Html::encode($totalErrorTitle) ?>" style="display:none;">
            <?= Html::encode($totalBad) ?>
        </th>
        <?php endif ?>
    </tr>
    </tfoot>
    </table>
    </div>
    <div class="card-footer text-muted small py-1">
        * approximate from last 5 min shares
        <?php if ($isAdmin): ?>
        &nbsp;·&nbsp;
        <a href="#" onclick="jQuery('.rejects').toggle();return false;">toggle rejects</a>
        <?php endif ?>
    </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Miners</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                     text-indigo-700 dark:text-indigo-300 font-mono">
            <?= Html::encode($algo) ?>
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300">
            <?= $totalWorkers ?> workers
        </span>
        <span class="ml-auto text-xs font-mono font-semibold text-gray-700 dark:text-gray-300">
            <?= Html::encode($totalHashrateFmt) ?>
        </span>
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 text-left">Version</th>
        <th class="px-3 py-2.5 text-right">Count</th>
        <th class="px-3 py-2.5 text-right" title="Donators">Don.</th>
        <th class="px-3 py-2.5 text-right" title="Extranonce Subscribe">ES</th>
        <th class="px-3 py-2.5 text-right">Pool %</th>
        <th class="px-3 py-2.5 text-right">Hashrate*</th>
        <th class="px-3 py-2.5 text-right">Avg/miner</th>
        <?php if ($isAdmin): ?><th class="px-3 py-2.5 text-right rejects" style="display:none;">Reject</th><?php endif ?>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($versionData as $r): ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
            <?= Html::encode($r['version']) ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['count'] ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500">
            <?= $r['donators'] ?: '—' ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500">
            <?= $r['extranonce'] ?: '—' ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums
                   <?= floatval($r['percent']) > 50
                       ? 'font-bold text-indigo-600 dark:text-indigo-400'
                       : 'text-gray-500 dark:text-gray-400' ?>">
            <?= Html::encode($r['percent']) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums
                   font-semibold text-gray-800 dark:text-gray-200">
            <?= Html::encode($r['hashrate']) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode($r['avg']) ?>
        </td>
        <?php if ($isAdmin): ?>
        <td class="px-3 py-2 text-right tabular-nums rejects
                   <?= $r['bad'] !== '-' ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' ?>"
            title="<?= Html::encode($r['errorTitle']) ?>" style="display:none;">
            <?= Html::encode($r['bad']) ?>
        </td>
        <?php endif ?>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot>
    <tr class="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700
               text-xs font-semibold text-gray-600 dark:text-gray-400">
        <td class="px-3 py-2">Total</td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $totalWorkers ?></td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $totalDonators ?></td>
        <td class="px-3 py-2 text-right tabular-nums"><?= $totalExtranonce ?></td>
        <td></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-800 dark:text-gray-200">
            <?= Html::encode($totalHashrateFmt) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums"><?= Html::encode($totalAvg) ?></td>
        <?php if ($isAdmin): ?>
        <td class="px-3 py-2 text-right tabular-nums rejects
                   <?= $totalBad ? 'text-red-500 dark:text-red-400' : '' ?>"
            title="<?= Html::encode($totalErrorTitle) ?>" style="display:none;">
            <?= Html::encode($totalBad) ?>
        </td>
        <?php endif ?>
    </tr>
    </tfoot>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
        <span>* approximate from last 5 min shares</span>
        <?php if ($isAdmin): ?>
        <a href="#" onclick="document.querySelectorAll('.rejects').forEach(function(el){el.style.display=el.style.display==='none'?'':el.style.display==='table-cell'?'none':'table-cell';});return false;"
           class="hover:text-indigo-500 transition-colors">
            toggle rejects
        </a>
        <?php endif ?>
    </div>
</div>

<?php endif ?>
