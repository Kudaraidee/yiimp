<?php

use yii\helpers\Html;

$this->title = 'Memcache Stats';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

// ── Fetch memcache stats ──────────────────────────────────────────────────────
$stat = null;
try {
    $stat = Yii::$app->cache->getMemcache()->getStats();
} catch (\Throwable $e) {
    $stat = null;
}

// ── URL timing map ────────────────────────────────────────────────────────────
$urlMap = Yii::$app->cache->get('url-map');
$res = [];
if (!empty($urlMap)) {
    foreach ($urlMap as $url => $n) {
        $d     = (float) Yii::$app->cache->get("{$url}-time");
        $res[] = [$url, $n, $d, $n > 0 ? $d / $n : 0];
    }
    usort($res, fn($a, $b) => $a[2] < $b[2]);
}

$backUrl = Yii::$app->request->referrer ?: '/admin/dashboard';

// ── Computed stats ────────────────────────────────────────────────────────────
$hitRate  = null;
$missRate = null;
if ($stat && (float) $stat['cmd_get'] > 0) {
    $hitRate  = round((float) $stat['get_hits'] / (float) $stat['cmd_get'] * 100, 1);
    $missRate = round(100 - $hitRate, 1);
}
$mbRead    = $stat ? round((float) $stat['bytes_read']       / (1024 * 1024), 2) : null;
$mbWrite   = $stat ? round((float) $stat['bytes_written']    / (1024 * 1024), 2) : null;
$mbMax     = $stat ? round((float) $stat['limit_maxbytes']   / (1024 * 1024), 0) : null;
$mbUsed    = $stat ? round((float) $stat['bytes']            / (1024 * 1024), 2) : null;
$usedPct   = ($stat && $mbMax > 0) ? round($stat['bytes'] / $stat['limit_maxbytes'] * 100, 1) : null;
$uptimeText = $stat ? $conv->sectoa((int) $stat['uptime']) : '—';

// Human-readable snapshot of the raw stats, used by the mono counters card.
$rawLines = [];
if ($stat) {
    foreach ([
        'version', 'pid', 'uptime', 'rusage_user', 'rusage_system',
        'total_items', 'curr_connections', 'total_connections',
        'connection_structures', 'cmd_get', 'cmd_set', 'get_hits',
        'get_misses', 'bytes_read', 'bytes_written', 'limit_maxbytes',
        'evictions',
    ] as $key) {
        $rawLines[] = str_pad($key, 22) . ' ' . (string) ($stat[$key] ?? '');
    }
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style cache console (same chrome as /site/mining)
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
#memcached_results { display:flex; flex-direction:column; gap:16px; }
#memcached_results .ad-toolbar {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:9px 12px; margin:0; border:1px solid #d8e2ef; border-radius:7px;
    background:#fff; box-shadow:0 1px 2px rgba(20,40,80,.04);
}
#memcached_results .ad-toolbar-brand {
    display:flex; align-items:center; gap:9px; padding-right:12px;
    border-right:1px solid #e8eef6;
}
#memcached_results .ad-live-dot {
    width:8px; height:8px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#memcached_results .ad-brand-text { display:flex; flex-direction:column; line-height:1.25; }
#memcached_results .ad-brand-text b { color:#26385a; font-size:13px; }
#memcached_results .ad-brand-text small { color:#8a94a6; font-size:9px; }
#memcached_results .mc-badge {
    display:inline-block; padding:2px 7px; border-radius:999px; background:#eef4ff;
    border:1px solid #d7e5ff; color:#2c5aa0; font-size:9px; font-weight:700;
    line-height:14px; white-space:nowrap; text-decoration:none;
}
#memcached_results .ad-toolbar-links { display:flex; flex-wrap:wrap; gap:6px; flex:1 1 auto; }
#memcached_results .ad-toolbar-links a {
    display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #dfe7f2;
    background:#f8fafc; color:#4a586c; font-size:10px; line-height:15px;
    text-decoration:none; white-space:nowrap;
}
#memcached_results .ad-toolbar-links a:hover {
    background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none;
}
#memcached_results .mc-live {
    margin-left:auto; white-space:nowrap; font-size:11px; color:#5b6675;
    display:flex; align-items:center; gap:6px;
}
#memcached_results .mc-live i {
    width:7px; height:7px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#memcached_results .ad-admin-nav {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    padding:5px 12px; margin-top:-8px; font-size:10px; color:#8a94a6;
}
#memcached_results .ad-admin-nav > span {
    font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#a7b0c0;
}
#memcached_results .ad-admin-nav a { color:#5b6675; text-decoration:none; }
#memcached_results .ad-admin-nav a:hover { color:#2c5aa0; text-decoration:underline; }

#memcached_results .mc-summary {
    background:#f8fbff; border:1px solid #d8e2ef; border-left:3px solid #2c5aa0;
    border-radius:6px; padding:8px 14px; display:flex; gap:22px; flex-wrap:wrap;
    font-size:12px; color:#4a586c;
}
#memcached_results .mc-summary b { color:#223a5e; }
#memcached_results .mc-summary .sep { color:#ccd4e0; }

#memcached_results .main-left-box {
    border:1px solid #d8e2ef; border-radius:7px; background:#fff;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden; margin:0;
}
#memcached_results .main-left-title {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px; background:#f8fafc;
    border-bottom:1px solid #e8eef6; color:#26385a; font-size:13px; font-weight:800;
}
#memcached_results .mn-head-main { display:flex; align-items:baseline; gap:9px; flex-wrap:wrap; min-width:0; }
#memcached_results .mn-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#memcached_results .main-left-inner { padding:0; overflow-x:auto; }

#memcached_results .mc-kpis {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:12px; padding:12px 14px;
}
#memcached_results .mc-kpi {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff;
    padding:8px 10px; min-width:0;
}
#memcached_results .mc-kpi .k-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#memcached_results .mc-kpi .k-value {
    display:block; margin-top:3px; font-family:monospace; font-size:15px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#memcached_results .mc-kpi .k-sub { display:block; margin-top:2px; font-size:9px; color:#8a94a6; }
#memcached_results .mc-kpi.accent .k-value { color:#2c5aa0; }
#memcached_results .mc-kpi.good .k-value { color:#2fae5a; }
#memcached_results .mc-kpi.warn .k-value { color:#c2410c; }

#memcached_results .mc-filter {
    display:flex; align-items:center; gap:10px; padding:8px 12px;
    border-top:1px solid #eef2f7; border-bottom:1px solid #e8eef6; background:#fff;
}
#memcached_results .mc-filter label {
    font-size:10px; font-weight:700; letter-spacing:.12em;
    color:#8a94a6; text-transform:uppercase;
}
#memcached_results .mc-filter input {
    width:260px; padding:4px 9px; border:1px solid #dfe5ec; border-radius:7px;
    background:#fafbfc; font:11px/1.4 inherit; color:#26385a; outline:0;
}
#memcached_results .mc-filter input:focus { border-color:#a9c4ea; background:#fff; }
#memcached_results .mc-count { font-size:10px; color:#8a94a6; font-variant-numeric:tabular-nums; }

#memcached_results table.dataGrid { width:100%; border-collapse:collapse; font-size:11px; }
#memcached_results table.dataGrid th {
    padding:6px 10px; background:#f8fafc; border-bottom:1px solid #e8eef6;
    color:#8a94a6; font-size:9px; font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; text-align:left; white-space:nowrap;
}
#memcached_results table.dataGrid th.num { text-align:right; }
#memcached_results table.dataGrid td {
    padding:5px 10px; border-bottom:1px solid #eef2f7; vertical-align:middle;
}
#memcached_results table.dataGrid tr:hover td { background:#f7faff; }
#memcached_results table.dataGrid td.mono { font-family:monospace; color:#33405a; }
#memcached_results table.dataGrid td.num {
    text-align:right; font-family:monospace; font-variant-numeric:tabular-nums; color:#4a586c;
}
#memcached_results table.dataGrid td.slow { color:#c2410c; font-weight:700; }
#memcached_results table.dataGrid td.warn { color:#b45309; }
#memcached_results .mc-empty { padding:18px; text-align:center; color:#98a3b5; font-size:11px; }
#memcached_results .mc-foot {
    padding:8px 12px; border-top:1px solid #e8eef6; background:#f8fafc;
    display:flex; gap:20px; flex-wrap:wrap; font-size:10px; color:#8a94a6;
}
#memcached_results .mc-foot b { color:#4a586c; font-family:monospace; }
#memcached_results pre.mc-config {
    margin:0; padding:12px; font-family:monospace; font-size:11px; color:#33405a;
    background:#fcfdff; overflow-x:auto; white-space:pre;
}
#memcached_results .mc-error {
    padding:14px; border:1px solid #fecaca; background:#fef2f2;
    border-radius:6px; color:#b91c1c; font-size:12px; font-weight:700;
}
@media (max-width:900px) {
    #memcached_results .mc-live { margin-left:0; }
    #memcached_results .ad-toolbar-brand { border-right:0; }
    #memcached_results .mc-filter input { width:100%; }
}
</style>

<div id="memcached_results">

    <div class="ad-toolbar">
        <div class="ad-toolbar-brand">
            <span class="ad-live-dot"></span>
            <span class="ad-brand-text"><b>Memcache</b><small>cache diagnostics · live</small></span>
            <span class="mc-badge">MEMCACHED</span>
        </div>
        <div class="ad-toolbar-links">
            <a href="<?= Html::encode($backUrl) ?>">&larr; Back</a>
            <a href="/admin/memcached">&#8635; Refresh</a>
        </div>
        <span class="mc-live"><i></i> snapshot · page load</span>
    </div>

    <div class="ad-admin-nav">
        <span>Admin</span>
        <?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?>
    </div>

    <?php if ($stat): ?>
    <div class="mc-summary">
        <span>Server <b>v<?= Html::encode($stat['version']) ?></b></span>
        <span class="sep">|</span>
        <span>PID <b><?= Html::encode($stat['pid']) ?></b></span>
        <span class="sep">|</span>
        <span>Uptime <b><?= Html::encode($uptimeText) ?></b></span>
        <span class="sep">|</span>
        <span>Items <b><?= number_format((int) $stat['total_items']) ?></b></span>
        <span class="sep">|</span>
        <span>Network <b><?= $mbRead ?> / <?= $mbWrite ?> MB</b></span>
    </div>
    <?php endif ?>

    <?php if (!$stat): ?>
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main"><span>Cache Performance</span><span class="mn-sub">memcache status</span></span>
            <span class="mc-badge">OFFLINE</span>
        </div>
        <div class="main-left-inner" style="padding:12px;">
            <div class="mc-error">Could not connect to Memcache server.</div>
        </div>
    </div>
    <?php else: ?>
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main"><span>Cache Performance</span><span class="mn-sub">since server start</span></span>
            <span class="mc-badge">LIVE</span>
        </div>
        <div class="mc-kpis">
            <div class="mc-kpi accent">
                <span class="k-label">Hit rate</span>
                <span class="k-value"><?= $hitRate !== null ? $hitRate . '%' : '—' ?></span>
                <span class="k-sub"><?= number_format((int) $stat['get_hits']) ?> / <?= number_format((int) $stat['cmd_get']) ?> GET</span>
            </div>
            <div class="mc-kpi <?= ($usedPct ?? 0) > 85 ? 'warn' : '' ?>">
                <span class="k-label">Memory used</span>
                <span class="k-value"><?= $usedPct !== null ? $usedPct . '%' : '—' ?></span>
                <span class="k-sub"><?= $mbUsed ?> / <?= $mbMax ?> MB</span>
            </div>
            <div class="mc-kpi">
                <span class="k-label">Open connections</span>
                <span class="k-value"><?= number_format((int) $stat['curr_connections']) ?></span>
                <span class="k-sub"><?= number_format((int) $stat['total_connections']) ?> lifetime</span>
            </div>
            <div class="mc-kpi <?= (int) $stat['evictions'] > 0 ? 'warn' : 'good' ?>">
                <span class="k-label">Evictions</span>
                <span class="k-value"><?= number_format((int) $stat['evictions']) ?></span>
                <span class="k-sub">items removed for space</span>
            </div>
        </div>
        <div class="mc-foot">
            <span><b>GET</b> <?= number_format((int) $stat['cmd_get']) ?></span>
            <span><b>SET</b> <?= number_format((int) $stat['cmd_set']) ?></span>
            <span><b>Misses</b> <?= number_format((int) $stat['get_misses']) ?><?= $missRate !== null ? ' (' . $missRate . '%)' : '' ?></span>
            <span><b>Structs</b> <?= number_format((int) $stat['connection_structures']) ?></span>
            <span><b>CPU</b> <?= round($stat['rusage_user'], 1) ?> / <?= round($stat['rusage_system'], 1) ?> s</span>
        </div>
    </div>
    <?php endif ?>

    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main"><span>URL Timing</span><span class="mn-sub">cached page render cost</span></span>
            <span class="mc-badge"><?= count($res) ?> URLS</span>
        </div>
        <div class="mc-filter">
            <label for="mc-url-filter">Filter</label>
            <input type="search" id="mc-url-filter" placeholder="url…" autocomplete="off">
            <span class="mc-count" id="mc-url-count">showing <?= count($res) ?> / <?= count($res) ?></span>
        </div>
        <div class="main-left-inner">
            <?php if (empty($res)): ?>
            <div class="mc-empty">No URL timing data available.</div>
            <?php else: ?>
            <table id="mc-url-table" class="dataGrid">
            <thead><tr>
                <th>URL</th>
                <th class="num">Count</th>
                <th class="num">Total (s)</th>
                <th class="num">Avg (s)</th>
            </tr></thead>
            <tbody>
            <?php foreach ($res as $item): ?>
            <?php
                $avg = $item[3];
                $avgCls = $avg > 1 ? 'slow' : ($avg > 0.5 ? 'warn' : '');
            ?>
            <tr>
                <td class="mono"><a href="/<?= Html::encode($item[0]) ?>"><?= Html::encode($item[0]) ?></a></td>
                <td class="num"><?= number_format((int) $item[1]) ?></td>
                <td class="num"><?= round($item[2], 3) ?></td>
                <td class="num <?= $avgCls ?>"><?= round($avg, 3) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>

    <?php if ($rawLines): ?>
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main"><span>Raw counters</span><span class="mn-sub">memcache stats snapshot</span></span>
            <span class="mc-badge">STATS</span>
        </div>
        <div class="main-left-inner">
            <pre class="mc-config"><?= Html::encode(implode("\n", $rawLines)) ?></pre>
        </div>
    </div>
    <?php endif ?>

</div>

<?php $this->registerJs('
(function () {
    var input = document.getElementById("mc-url-filter");
    var table = document.getElementById("mc-url-table");
    var count = document.getElementById("mc-url-count");
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
'); ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0 fw-semibold">Memcache Stats</h5>
    <div class="d-flex gap-2">
        <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <a href="/admin/memcached" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </a>
    </div>
</div>

<?php if (!$stat): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    Could not connect to Memcache server.
</div>
<?php else: ?>

<div class="row gx-3 mb-3">

    <!-- Server Info -->
    <div class="col-12 col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <i class="bi bi-server text-secondary"></i>
                <strong class="small">Server Info</strong>
            </div>
            <div class="card-body p-0">
            <table class="table table-sm table-borderless mb-0">
            <tbody>
            <?php foreach ([
                ['Version',       $stat['version']],
                ['PID',           $stat['pid']],
                ['Uptime',        $stat['uptime'] . ' s'],
                ['CPU user',      round($stat['rusage_user'],   1) . ' s'],
                ['CPU system',    round($stat['rusage_system'], 1) . ' s'],
                ['Total items',   number_format($stat['total_items'])],
                ['Max storage',   $mbMax . ' MB'],
                ['Evictions',     number_format($stat['evictions'])],
            ] as [$label, $value]): ?>
            <tr>
                <td class="small text-muted ps-3"><?= Html::encode($label) ?></td>
                <td class="small font-monospace fw-semibold"><?= Html::encode((string) $value) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Cache Performance -->
    <div class="col-12 col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-secondary"></i>
                <strong class="small">Performance</strong>
            </div>
            <div class="card-body p-0">
            <table class="table table-sm table-borderless mb-0">
            <tbody>
            <tr>
                <td class="small text-muted ps-3">Hit rate</td>
                <td class="small">
                    <?php if ($hitRate !== null): ?>
                    <span class="badge <?= $hitRate >= 90 ? 'bg-success' : ($hitRate >= 70 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                        <?= $hitRate ?>%
                    </span>
                    <?php else: ?>—<?php endif ?>
                </td>
            </tr>
            <?php foreach ([
                ['GET requests',  number_format($stat['cmd_get'])],
                ['SET requests',  number_format($stat['cmd_set'])],
                ['Hits',          number_format($stat['get_hits'])   . ($hitRate  !== null ? " ({$hitRate}%)"  : '')],
                ['Misses',        number_format($stat['get_misses']) . ($missRate !== null ? " ({$missRate}%)" : '')],
                ['Read',          $mbRead  . ' MB'],
                ['Written',       $mbWrite . ' MB'],
                ['Open conns',    number_format($stat['curr_connections'])],
                ['Total conns',   number_format($stat['total_connections'])],
            ] as [$label, $value]): ?>
            <tr>
                <td class="small text-muted ps-3"><?= Html::encode($label) ?></td>
                <td class="small font-monospace"><?= Html::encode((string) $value) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
            </table>
            </div>
        </div>
    </div>

</div>
<?php endif ?>

<!-- URL Timing -->
<div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-stopwatch text-secondary"></i>
        <strong class="small">URL Timing</strong>
        <?php if (!empty($res)): ?>
        <span class="badge bg-secondary ms-1"><?= count($res) ?></span>
        <?php endif ?>
    </div>
    <?php if (empty($res)): ?>
    <div class="card-body text-muted small">No URL timing data available.</div>
    <?php else: ?>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table class="table table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th class="small">URL</th>
        <th class="small text-end">Count</th>
        <th class="small text-end">Total (s)</th>
        <th class="small text-end">Avg (s)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($res as $item): ?>
    <tr>
        <td class="small font-monospace">
            <a href="/<?= Html::encode($item[0]) ?>"><?= Html::encode($item[0]) ?></a>
        </td>
        <td class="text-end small tabular-nums"><?= Html::encode((string) $item[1]) ?></td>
        <td class="text-end small font-monospace tabular-nums"><?= round($item[2], 3) ?></td>
        <td class="text-end small font-monospace tabular-nums <?= $item[3] > 1 ? 'text-danger fw-bold' : ($item[3] > 0.5 ? 'text-warning' : '') ?>">
            <?= round($item[3], 3) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    </div>
    <?php endif ?>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-base font-bold text-gray-800 dark:text-gray-100">Memcache Stats</h2>
    <div class="flex gap-2">
        <a href="<?= Html::encode($backUrl) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
        </a>
        <a href="/admin/memcached"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>Refresh
        </a>
    </div>
</div>

<?php if (!$stat): ?>
<div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    Could not connect to Memcache server.
</div>
<?php else: ?>

<!-- Hit rate banner -->
<?php if ($hitRate !== null): ?>
<?php
$hrColor = $hitRate >= 90
    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-400'
    : ($hitRate >= 70
        ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300'
        : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-400');
?>
<div class="rounded-xl border <?= $hrColor ?> px-4 py-3 mb-4 flex items-center gap-3">
    <i data-lucide="gauge" class="w-5 h-5 shrink-0"></i>
    <div class="flex-1">
        <div class="text-sm font-semibold mb-1">Cache hit rate: <?= $hitRate ?>%</div>
        <div class="w-full bg-white/50 dark:bg-black/20 rounded-full h-1.5 overflow-hidden">
            <div class="h-full rounded-full <?= $hitRate >= 90 ? 'bg-green-500' : ($hitRate >= 70 ? 'bg-amber-400' : 'bg-red-500') ?>"
                 style="width:<?= $hitRate ?>%"></div>
        </div>
    </div>
    <span class="text-xs font-mono"><?= number_format($stat['get_hits']) ?> / <?= number_format($stat['cmd_get']) ?></span>
</div>
<?php endif ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

    <!-- Server Info -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <i data-lucide="server" class="w-4 h-4 text-gray-400 shrink-0"></i>
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Server Info</span>
            <span class="ml-auto text-xs font-mono text-gray-400 dark:text-gray-500">v<?= Html::encode($stat['version']) ?></span>
        </div>
        <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
        <?php foreach ([
            ['PID',           $stat['pid']],
            ['Uptime',        $stat['uptime'] . ' s'],
            ['CPU user',      round($stat['rusage_user'],   1) . ' s'],
            ['CPU system',    round($stat['rusage_system'], 1) . ' s'],
            ['Total items',   number_format($stat['total_items'])],
            ['Max storage',   $mbMax . ' MB'],
            ['Evictions',     number_format($stat['evictions'])],
            ['Conn. structs', number_format($stat['connection_structures'])],
        ] as [$label, $value]): ?>
            <span class="text-gray-400 dark:text-gray-500"><?= Html::encode($label) ?></span>
            <span class="font-mono tabular-nums text-gray-700 dark:text-gray-200"><?= Html::encode((string) $value) ?></span>
        <?php endforeach ?>
        </div>
    </div>

    <!-- Connections & I/O -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <i data-lucide="activity" class="w-4 h-4 text-gray-400 shrink-0"></i>
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Traffic &amp; Connections</span>
        </div>
        <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
        <?php foreach ([
            ['GET requests',  number_format($stat['cmd_get'])],
            ['SET requests',  number_format($stat['cmd_set'])],
            ['Cache hits',    number_format($stat['get_hits'])],
            ['Cache misses',  number_format($stat['get_misses'])],
            ['Read',          $mbRead  . ' MB'],
            ['Written',       $mbWrite . ' MB'],
            ['Open conns',    number_format($stat['curr_connections'])],
            ['Total conns',   number_format($stat['total_connections'])],
        ] as [$label, $value]): ?>
            <span class="text-gray-400 dark:text-gray-500"><?= Html::encode($label) ?></span>
            <span class="font-mono tabular-nums text-gray-700 dark:text-gray-200"><?= Html::encode((string) $value) ?></span>
        <?php endforeach ?>
        </div>
    </div>

</div>
<?php endif // stat ?>

<!-- URL Timing -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <i data-lucide="timer" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">URL Timing</span>
        <?php if (!empty($res)): ?>
        <span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
            <?= count($res) ?>
        </span>
        <?php endif ?>
    </div>
    <?php if (empty($res)): ?>
    <div class="px-4 py-6 text-sm text-gray-400 dark:text-gray-500 text-center">
        No URL timing data available.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700
               text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
        <th class="px-4 py-2.5 text-left">URL</th>
        <th class="px-3 py-2.5 text-right w-16">Count</th>
        <th class="px-3 py-2.5 text-right w-20">Total (s)</th>
        <th class="px-3 py-2.5 text-right w-20">Avg (s)</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($res as $item):
        $avgSlow = $item[3] > 1.0;
        $avgWarn = !$avgSlow && $item[3] > 0.5;
        $avgCls  = $avgSlow
            ? 'text-red-600 dark:text-red-400 font-bold'
            : ($avgWarn ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-300');
    ?>
    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-4 py-2 font-mono text-gray-700 dark:text-gray-300">
            <a href="/<?= Html::encode($item[0]) ?>"
               class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <?= Html::encode($item[0]) ?>
            </a>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400"><?= Html::encode((string) $item[1]) ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400"><?= round($item[2], 3) ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums <?= $avgCls ?>"><?= round($item[3], 3) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <?php endif ?>
</div>

<?php endif // scheme ?>

<?php if ($isTailwind): ?>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
<?php endif ?>
