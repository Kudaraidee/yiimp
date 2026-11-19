<?php

/** @var yii\web\View              $this     */
/** @var app\models\Connections[]  $list     */
/** @var string|null               $lastTime */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
$count      = count($list);

// ── Card / table open ─────────────────────────────────────────────────────────
if ($isLegacy) {
    Yii::$app->ViewUtils->showTableSorter('maintable');
    echo '<thead><tr>';
    echo '<th>ID</th><th>User</th><th>Host</th><th>Database</th>';
    echo '<th>Idle</th><th>Created</th><th>Last</th><th>Active</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-diagram-3 text-secondary"></i>';
    echo '<strong class="small">Connections</strong>';
    echo '<span class="badge bg-secondary ms-1">' . $count . '</span>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table id="maintable" class="table table-sm table-bordered mb-0">';
    echo '<thead class="table-light"><tr>';
    foreach (['ID', 'User', 'Host', 'Database', 'Idle', 'Created', 'Last', 'Active'] as $h)
        echo '<th class="small' . (in_array($h, ['ID', 'Idle']) ? ' text-end' : '') . '">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody>';
    Yii::$app->ViewUtils->JavascriptReady("\$('#maintable').tablesorter();");
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="database" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Connections</span>';
    echo '<span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">' . $count . '</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
    foreach (['ID', 'User', 'Host', 'Database', 'Idle', 'Created', 'Last', ''] as $h)
        echo '<th class="px-3 py-2.5 ' . (in_array($h, ['ID', 'Idle', 'Created', 'Last']) ? 'text-right' : 'text-left') . '">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">';
}

// ── Rows ──────────────────────────────────────────────────────────────────────
foreach ($list as $conn) {
    $isActive = ($conn->last == $lastTime);
    $idle     = $conv->sectoa($conn->idle);
    $created  = $conv->datetoa2($conn->created);
    $last     = $conv->datetoa2($conn->last);
    $idleSecs = (int) $conn->idle;

    if ($isLegacy) {
        $rowCls = $isActive ? "style='font-weight:bold;'" : "class='ssrow'";
        echo "<tr {$rowCls}>";
        echo '<td>' . (int) $conn->id . '</td>';
        echo '<td>' . Html::encode($conn->user) . '</td>';
        echo '<td>' . Html::encode($conn->host) . '</td>';
        echo '<td>' . Html::encode($conn->db)   . '</td>';
        echo '<td>' . $idle    . '</td>';
        echo '<td>' . $created . '</td>';
        echo '<td>' . $last    . '</td>';
        echo '<td>' . $conv->Booltoa($isActive) . '</td>';
        echo '</tr>';
    } elseif (!$isTailwind) {
        $rowCls  = $isActive ? 'table-success' : '';
        $idleCls = $idleSecs > 300 ? 'text-warning' : ($idleSecs > 60 ? 'text-muted' : '');
        echo "<tr class='{$rowCls}'>";
        echo '<td class="text-end small tabular-nums">' . (int) $conn->id . '</td>';
        echo '<td class="small font-monospace">' . Html::encode($conn->user) . '</td>';
        echo '<td class="small font-monospace">' . Html::encode($conn->host) . '</td>';
        echo '<td class="small font-monospace">' . Html::encode($conn->db)   . '</td>';
        echo '<td class="text-end small font-monospace ' . $idleCls . '">' . $idle . '</td>';
        echo '<td class="small text-muted">' . $created . '</td>';
        echo '<td class="small text-muted">' . $last    . '</td>';
        echo '<td class="text-center">';
        if ($isActive)
            echo '<span class="badge bg-success">active</span>';
        echo '</td>';
        echo '</tr>';
    } else {
        $rowCls  = $isActive
            ? 'bg-green-50/50 dark:bg-green-900/10'
            : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors';
        $idleCls = $idleSecs > 300
            ? 'text-red-500 dark:text-red-400'
            : ($idleSecs > 60 ? 'text-amber-500 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400');
        echo "<tr class='{$rowCls}'>";
        echo '<td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500">' . (int) $conn->id . '</td>';
        echo '<td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">' . Html::encode($conn->user) . '</td>';
        echo '<td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-400">' . Html::encode($conn->host) . '</td>';
        echo '<td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-400">' . Html::encode($conn->db)   . '</td>';
        echo '<td class="px-3 py-2 text-right font-mono tabular-nums ' . $idleCls . '">' . $idle . '</td>';
        echo '<td class="px-3 py-2 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">' . $created . '</td>';
        echo '<td class="px-3 py-2 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">' . $last    . '</td>';
        echo '<td class="px-3 py-2 text-center">';
        if ($isActive)
            echo '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">active</span>';
        echo '</td>';
        echo '</tr>';
    }
}

// ── Card / table close + footer ───────────────────────────────────────────────
if ($isLegacy) {
    echo '</tbody></table><br>';
    echo $count . ' connection' . ($count !== 1 ? 's' : '') . ' to the database<br>';
} elseif (!$isTailwind) {
    echo '</tbody></table></div></div>';
    echo '<div class="card-footer text-muted small py-1">';
    echo $count . ' connection' . ($count !== 1 ? 's' : '') . ' to the database';
    echo '</div></div>';
} else {
    echo '</tbody></table></div>';
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">';
    echo $count . ' connection' . ($count !== 1 ? 's' : '') . ' to the database';
    echo '</div></div>';
}
