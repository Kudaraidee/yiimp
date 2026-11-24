<?php

/** @var yii\web\View              $this     */
/** @var app\models\Connections[]  $list     */
/** @var string|null               $lastTime */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
$count      = count($list);

// ── Shared aggregates ─────────────────────────────────────────────────────────
$activeCount = 0;
$idleLong    = 0;
$users       = [];
foreach ($list as $conn) {
    if ($conn->last == $lastTime) $activeCount++;
    if ((int) $conn->idle > 300)  $idleLong++;
    $users[$conn->user] = true;
}
$dbName   = $list[0]->db ?? '—';
$lastSeen = $lastTime ? $conv->datetoa2($lastTime) : '—';

// ── Legacy: mining-style console ──────────────────────────────────────────────
if ($isLegacy): ?>

<style>
#dbconn_results .dbc-summary {
    background:#f8fbff; border:1px solid #d8e2ef; border-left:3px solid #2c5aa0;
    border-radius:6px; padding:8px 14px; display:flex; gap:22px; flex-wrap:wrap;
    font-size:12px; color:#4a586c;
}
#dbconn_results .dbc-summary b { color:#223a5e; }
#dbconn_results .dbc-summary .sep { color:#ccd4e0; }
#dbconn_results .main-left-box {
    border:1px solid #d8e2ef; border-radius:7px; background:#fff;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden; margin:0;
}
#dbconn_results .main-left-title {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px; background:#f8fafc;
    border-bottom:1px solid #e8eef6; color:#26385a; font-size:13px; font-weight:800;
}
#dbconn_results .mn-head-main { display:flex; align-items:baseline; gap:9px; flex-wrap:wrap; min-width:0; }
#dbconn_results .mn-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#dbconn_results .dbc-kpis {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:12px; padding:12px 14px;
}
#dbconn_results .dbc-kpi {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff;
    padding:8px 10px; min-width:0;
}
#dbconn_results .dbc-kpi .k-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#dbconn_results .dbc-kpi .k-value {
    display:block; margin-top:3px; font-family:monospace; font-size:15px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#dbconn_results .dbc-kpi .k-sub { display:block; margin-top:2px; font-size:9px; color:#8a94a6; }
#dbconn_results .dbc-kpi.accent .k-value { color:#2c5aa0; }
#dbconn_results .dbc-kpi.good .k-value { color:#2fae5a; }
#dbconn_results .dbc-kpi.warn .k-value { color:#c2410c; }
#dbconn_results .dbc-filter {
    display:flex; align-items:center; gap:10px; padding:8px 12px;
    border-top:1px solid #eef2f7; border-bottom:1px solid #e8eef6; background:#fff;
}
#dbconn_results .dbc-filter label {
    font-size:10px; font-weight:700; letter-spacing:.12em;
    color:#8a94a6; text-transform:uppercase;
}
#dbconn_results .dbc-filter input {
    width:260px; padding:4px 9px; border:1px solid #dfe5ec; border-radius:7px;
    background:#fafbfc; font:11px/1.4 inherit; color:#26385a; outline:0;
}
#dbconn_results .dbc-filter input:focus { border-color:#a9c4ea; background:#fff; }
#dbconn_results .dbc-count { font-size:10px; color:#8a94a6; font-variant-numeric:tabular-nums; }
#dbconn_results .dbc-card-inner { padding:0; overflow-x:auto; }
#dbconn_results table.dataGrid { width:100%; border-collapse:collapse; font-size:11px; }
#dbconn_results table.dataGrid th {
    padding:6px 10px; background:#f8fafc; border-bottom:1px solid #e8eef6;
    color:#8a94a6; font-size:9px; font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; text-align:left; white-space:nowrap;
}
#dbconn_results table.dataGrid th.num { text-align:right; }
#dbconn_results table.dataGrid td {
    padding:5px 10px; border-bottom:1px solid #eef2f7; vertical-align:middle;
}
#dbconn_results table.dataGrid tr:hover td { background:#f7faff; }
#dbconn_results table.dataGrid td.mono { font-family:monospace; color:#33405a; }
#dbconn_results table.dataGrid td.num {
    text-align:right; font-family:monospace; font-variant-numeric:tabular-nums; color:#4a586c;
}
#dbconn_results table.dataGrid td.muted { color:#8a94a6; }
#dbconn_results table.dataGrid td.slow { color:#c2410c; font-weight:700; }
#dbconn_results table.dataGrid td.warn { color:#b45309; }
#dbconn_results .dbc-active {
    display:inline-block; padding:2px 7px; border-radius:999px;
    background:#e8f8ee; border:1px solid #bfe6cd; color:#2f7d4f;
    font-size:9px; font-weight:700; line-height:14px;
}
#dbconn_results .dbc-empty { padding:18px; text-align:center; color:#98a3b5; font-size:11px; }
#dbconn_results .dbc-foot {
    padding:8px 12px; border-top:1px solid #e8eef6; background:#f8fafc;
    display:flex; gap:20px; flex-wrap:wrap; font-size:10px; color:#8a94a6;
}
#dbconn_results .dbc-foot b { color:#4a586c; font-family:monospace; }
@media (max-width:900px) {
    #dbconn_results .dbc-filter input { width:100%; }
}
</style>

<div class="dbc-summary">
    <span>Database <b><?= Html::encode($dbName) ?></b></span>
    <span class="sep">|</span>
    <span>Last check <b><?= $lastSeen ?></b></span>
    <span class="sep">|</span>
    <span>Users <b><?= count($users) ?></b></span>
    <span class="sep">|</span>
    <span>Idle &gt;5m <b><?= $idleLong ?></b></span>
</div>

<div class="main-left-box">
    <div class="main-left-title">
        <span class="mn-head-main"><span>Connections</span><span class="mn-sub">current database sessions</span></span>
        <span class="mc-badge">LIVE · <?= $count ?></span>
    </div>

    <div class="dbc-kpis">
        <div class="dbc-kpi accent">
            <span class="k-label">Connections</span>
            <span class="k-value"><?= $count ?></span>
            <span class="k-sub">open sessions</span>
        </div>
        <div class="dbc-kpi good">
            <span class="k-label">Active</span>
            <span class="k-value"><?= $activeCount ?></span>
            <span class="k-sub">last seen this poll</span>
        </div>
        <div class="dbc-kpi <?= $idleLong ? 'warn' : '' ?>">
            <span class="k-label">Idle &gt;5m</span>
            <span class="k-value"><?= $idleLong ?></span>
            <span class="k-sub">stale sessions</span>
        </div>
        <div class="dbc-kpi">
            <span class="k-label">Distinct users</span>
            <span class="k-value"><?= count($users) ?></span>
            <span class="k-sub">database accounts</span>
        </div>
    </div>

    <div class="dbc-filter">
        <label for="dbc-filter-input">Filter</label>
        <input type="search" id="dbc-filter-input" placeholder="id, user, host, db…" autocomplete="off">
        <span class="dbc-count" id="dbc-count">showing <?= $count ?> / <?= $count ?></span>
    </div>

    <div class="main-left-inner dbc-card-inner">
        <table id="maintable" class="dataGrid">
        <thead><tr>
            <th>ID</th>
            <th>User</th>
            <th>Host</th>
            <th>Database</th>
            <th class="num">Idle</th>
            <th>Created</th>
            <th>Last</th>
            <th>Active</th>
        </tr></thead>
        <tbody>
        <?php foreach ($list as $conn): ?>
            <?php
            $isActive = ($conn->last == $lastTime);
            $idle     = $conv->sectoa($conn->idle);
            $created  = $conv->datetoa2($conn->created);
            $last     = $conv->datetoa2($conn->last);
            $idleSecs = (int) $conn->idle;
            $idleCls  = $idleSecs > 300 ? 'slow' : ($idleSecs > 60 ? 'warn' : '');
            ?>
            <tr>
                <td class="num"><?= (int) $conn->id ?></td>
                <td class="mono"><?= Html::encode($conn->user) ?></td>
                <td class="mono"><?= Html::encode($conn->host) ?></td>
                <td class="mono"><?= Html::encode($conn->db) ?></td>
                <td class="num <?= $idleCls ?>"><?= Html::encode($idle) ?></td>
                <td class="muted"><?= $created ?></td>
                <td class="muted"><?= $last ?></td>
                <td><?= $isActive ? '<span class="dbc-active">active</span>' : '' ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
        </table>
        <?php if (empty($list)): ?>
        <div class="dbc-empty">No database connections available.</div>
        <?php endif ?>
    </div>

    <div class="dbc-foot">
        <span><b>Total</b> <?= $count ?> connection<?= $count !== 1 ? 's' : '' ?></span>
        <span><b>Active</b> <?= $activeCount ?></span>
        <span><b>Idle &gt;5m</b> <?= $idleLong ?></span>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady(<<<'JS'
(function () {
    var table = document.getElementById("maintable");
    if (table && typeof $.fn.tablesorter !== "undefined") {
        $(table).tablesorter({ headers: { 4: { sorter: "numeric" } } });
    }
    var input = document.getElementById("dbc-filter-input");
    var count = document.getElementById("dbc-count");
    if (!input || !table) return;
    var rows = Array.prototype.slice.call(table.tBodies[0].rows);
    input.addEventListener("input", function () {
        var q = input.value.toLowerCase();
        var shown = 0;
        rows.forEach(function (r) {
            var hit = r.textContent.toLowerCase().indexOf(q) !== -1;
            r.style.display = hit ? "" : "none";
            if (hit) shown++;
        });
        if (count) count.textContent = "showing " + shown + " / " + rows.length;
    });
})();
JS
);

// ── AdminLTE ──────────────────────────────────────────────────────────────────
elseif (!$isTailwind):
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

    foreach ($list as $conn) {
        $isActive = ($conn->last == $lastTime);
        $idle     = $conv->sectoa($conn->idle);
        $created  = $conv->datetoa2($conn->created);
        $last     = $conv->datetoa2($conn->last);
        $idleSecs = (int) $conn->idle;
        $rowCls   = $isActive ? 'table-success' : '';
        $idleCls  = $idleSecs > 300 ? 'text-warning' : ($idleSecs > 60 ? 'text-muted' : '');
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
    }

    echo '</tbody></table></div></div>';
    echo '<div class="card-footer text-muted small py-1">';
    echo $count . ' connection' . ($count !== 1 ? 's' : '') . ' to the database';
    echo '</div></div>';

// ── Tailwind ──────────────────────────────────────────────────────────────────
else:
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

    foreach ($list as $conn) {
        $isActive = ($conn->last == $lastTime);
        $idle     = $conv->sectoa($conn->idle);
        $created  = $conv->datetoa2($conn->created);
        $last     = $conv->datetoa2($conn->last);
        $idleSecs = (int) $conn->idle;
        $rowCls   = $isActive
            ? 'bg-green-50/50 dark:bg-green-900/10'
            : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors';
        $idleCls  = $idleSecs > 300
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

    echo '</tbody></table></div>';
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">';
    echo $count . ' connection' . ($count !== 1 ? 's' : '') . ' to the database';
    echo '</div></div>';
endif;
