<?php

use yii\helpers\Html;

$this->title = 'Memcache Stats';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

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
$usedPct   = ($stat && $mbMax > 0) ? round($stat['bytes'] / $stat['limit_maxbytes'] * 100, 1) : null;

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
&nbsp;-&nbsp;<a href='<?= Html::encode($backUrl) ?>'>&larr; Back</a>&nbsp;-&nbsp;<a href='/admin/memcached'>Refresh</a><br>

<?php if (!$stat): ?>
<p style="color:red;"><b>Could not connect to Memcache server.</b></p>
<?php else: ?>
<hr>
<table>
<tr><td>Memcache server version</td><td><?= Html::encode($stat['version']) ?></td></tr>
<tr><td>Process id</td><td><?= Html::encode($stat['pid']) ?></td></tr>
<tr><td>Server uptime</td><td><?= Html::encode($stat['uptime']) ?> seconds</td></tr>
<tr><td>CPU user time</td><td><?= round($stat['rusage_user'], 1) ?> seconds</td></tr>
<tr><td>CPU system time</td><td><?= round($stat['rusage_system'], 1) ?> seconds</td></tr>
<tr><td>Total items stored</td><td><?= Html::encode($stat['total_items']) ?></td></tr>
<tr><td>Open connections</td><td><?= Html::encode($stat['curr_connections']) ?></td></tr>
<tr><td>Total connections since start</td><td><?= Html::encode($stat['total_connections']) ?></td></tr>
<tr><td>Connection structures</td><td><?= Html::encode($stat['connection_structures']) ?></td></tr>
<tr><td>Cumulative GET requests</td><td><?= Html::encode($stat['cmd_get']) ?></td></tr>
<tr><td>Cumulative SET requests</td><td><?= Html::encode($stat['cmd_set']) ?></td></tr>
<tr><td>Cache hits</td><td><?= Html::encode($stat['get_hits']) ?><?= $hitRate !== null ? " ({$hitRate}%)" : '' ?></td></tr>
<tr><td>Cache misses</td><td><?= Html::encode($stat['get_misses']) ?><?= $missRate !== null ? " ({$missRate}%)" : '' ?></td></tr>
<tr><td>Bytes read from network</td><td><?= $mbRead ?> MB</td></tr>
<tr><td>Bytes written to network</td><td><?= $mbWrite ?> MB</td></tr>
<tr><td>Max storage size</td><td><?= $mbMax ?> MB</td></tr>
<tr><td>Evictions</td><td><?= Html::encode($stat['evictions']) ?></td></tr>
</table>
<?php endif ?>

<div style="margin-top:8px;margin-bottom:24px;margin-right:16px;">
<?php if (empty($res)): ?>
<p><i>No URL timing data available.</i></p>
<?php else: ?>
<table class="dataGrid">
<thead><tr>
<th>URL</th>
<th align="right">Count</th>
<th align="right">Time</th>
<th align="right">Average</th>
</tr></thead>
<tbody>
<?php foreach ($res as $item): ?>
<tr class="ssrow">
<td><a href="/<?= Html::encode($item[0]) ?>"><?= Html::encode($item[0]) ?></a></td>
<td align="right"><?= $item[1] ?></td>
<td align="right"><?= round($item[2], 3) ?></td>
<td align="right"><?= round($item[3], 3) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endif ?>
</div>


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
