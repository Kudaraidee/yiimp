<?php

/** @var yii\web\View $this */
/** @var array[]     $jobs           One entry per job from JobsController::buildJobData() */
/** @var bool        $tableExists    False when the queue table hasn't been created yet */
/** @var bool        $balancesLocked True when PaymentsJob holds the payment lock */

use yii\helpers\Html;

$this->title = 'Queue Jobs';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

// ── Helpers ───────────────────────────────────────────────────────────────────
$fmtSecs = function (?int $s) use ($isTailwind): string {
    if ($s === null) return '—';
    if ($s < 0) return $isTailwind
        ? '<span class="text-red-500 dark:text-red-400 font-medium">overdue</span>'
        : '<span class="text-danger">overdue</span>';
    if ($s < 60)   return "{$s}s";
    if ($s < 3600) return round($s / 60) . 'm';
    return round($s / 3600, 1) . 'h';
};

$fmtAgo = function (?int $ts): string {
    if (!$ts) return '—';
    $s = time() - $ts;
    if ($s < 60)    return "{$s}s ago";
    if ($s < 3600)  return round($s / 60) . 'm ago';
    if ($s < 86400) return round($s / 3600, 1) . 'h ago';
    return round($s / 86400, 1) . 'd ago';
};

// Status badge configs per scheme
$badgeLegacy = [
    'running'    => ['color:#fff;background:#0d6efd;',    'running'],
    'ready'      => ['color:#fff;background:#198754;',    'ready'],
    'pending'    => ['color:#fff;background:#198754;',    'pending'],
    'late'       => ['color:#000;background:#ffc107;',    'late'],
    'paused'     => ['color:#fff;background:#6c757d;',    'paused'],
    'not_seeded' => ['color:#fff;background:#dc3545;',    'not seeded'],
];
$badgeAdminlte = [
    'running'    => ['bg-primary',               'running'],
    'ready'      => ['bg-success',               'ready'],
    'pending'    => ['bg-success',               'pending'],
    'late'       => ['bg-warning text-dark',     'late'],
    'paused'     => ['bg-secondary',             'paused'],
    'not_seeded' => ['bg-danger',                'not seeded'],
];
$badgeTailwind = [
    'running'    => ['bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',   'running'],
    'ready'      => ['bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300', 'ready'],
    'pending'    => ['bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400', 'pending'],
    'late'       => ['bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', 'late'],
    'paused'     => ['bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',       'paused'],
    'not_seeded' => ['bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',        'not seeded'],
];

// Domain labels + Lucide icons
$domainMeta = [
    'blocks'   => ['Block Pipeline',              'layers'],
    'earnings' => ['Earnings & Payments',          'banknote'],
    'coins'    => ['Coin Management',              'coins'],
    'stats'    => ['Statistics',                   'bar-chart-2'],
    'market'   => ['Market & Exchange',            'trending-up'],
    'renting'  => ['Renting (pool rents out)',     'share-2'],
    'nicehash' => ['NiceHash (pool buys in)',      'zap'],
    'system'   => ['System & Housekeeping',        'settings'],
];

// Group jobs by domain
$grouped = [];
foreach ($jobs as $j) {
    $grouped[$j['domain']][] = $j;
}

$countRunning = count(array_filter($jobs, fn($j) => $j['status'] === 'running'));
$countPaused  = count(array_filter($jobs, fn($j) => $j['paused']));
$countMissing = count(array_filter($jobs, fn($j) => $j['status'] === 'not_seeded'));
$countActive  = count($jobs) - $countPaused - $countMissing;

$intervalFmt = function (int $secs): string {
    if ($secs < 60)   return $secs . 's';
    if ($secs < 3600) return ($secs / 60) . 'm';
    return ($secs / 3600) . 'h';
};
?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
#refresh-bar { height:3px; background:#08c; transition:width 1s linear; margin-bottom:.75rem; }
.job-badge { padding:1px 6px; border-radius:3px; font-size:.8em; display:inline-block; }
.job-actions form { display:inline; margin-right:2px; }
</style>
<div id="refresh-bar" style="width:100%"></div>

<?php if (!$tableExists): ?>
<div class="main-left-box"><div class="main-left-inner">
<p style="color:red;"><b>The <code>queue</code> table does not exist yet.</b><br>
Run <code>php yii migrate --migrationPath=@yii/queue/db/migrations</code> then
<code>php yii queue/seed</code> to initialise the queue.</p>
</div></div>
<?php else: ?>

<div class="main-left-box">
<div class="main-left-title">Queue Jobs<?php if ($balancesLocked): ?> &mdash; <span style="color:#c55;">&#9888; balances locked</span><?php endif ?></div>
<div class="main-left-inner">
<div style="font-size:.85em;margin-bottom:4px;">
<?= $countRunning ?> running &nbsp;&middot;&nbsp;
<?= $countActive ?> active &nbsp;&middot;&nbsp;
<?php if ($countPaused): ?><span style="color:#666"><?= $countPaused ?> paused</span> &nbsp;&middot;&nbsp;<?php endif ?>
<?php if ($countMissing): ?><span style="color:#c55"><?= $countMissing ?> not seeded</span>&nbsp;&nbsp;<?php endif ?>
<?= Html::beginForm(['jobs/seed-all'], 'post', ['style' => 'display:inline']) ?>
<?= Html::submitButton('Seed missing', ['style' => 'font-size:.8em;padding:2px 8px;cursor:pointer;']) ?>
<?= Html::endForm() ?>
</div>
<?= \app\widgets\Alert::widget() ?>
</div></div>

<div class="main-left-box">
<div class="main-left-inner">
<table class="dataGrid2" style="width:100%;table-layout:fixed;">
<colgroup>
    <col><!-- job name, flexible -->
    <col style="width:50px;">
    <col style="width:80px;">
    <col style="width:58px;">
    <col style="width:76px;">
    <col style="width:136px;">
</colgroup>
<thead><tr>
<th>Job</th>
<th align="right">Every</th>
<th align="center">Status</th>
<th align="right">Runs in</th>
<th align="right">Last run</th>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($grouped as $domain => $domainJobs):
    [$label] = $domainMeta[$domain] ?? [ucfirst($domain), 'circle'];
?>
<tr style="background:#e8e8e8;">
    <td colspan="6" style="font-weight:bold;font-size:.8em;padding:3px 6px;color:#444;">
        <?= Html::encode($label) ?>
    </td>
</tr>
<?php foreach ($domainJobs as $j):
    [$badgeStyle, $badgeText] = $badgeLegacy[$j['status']] ?? ['background:#eee;color:#333;', $j['status']];
?>
<tr class="ssrow">
<td style="font-family:monospace;font-size:.85em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= Html::encode($j['name']) ?></td>
<td align="right" style="font-size:.8em;color:#666;"><?= $intervalFmt($j['interval']) ?></td>
<td align="center"><span class="job-badge" style="<?= $badgeStyle ?>"><?= $badgeText ?></span></td>
<td align="right" style="font-size:.85em;"><?= $fmtSecs($j['secsLeft']) ?></td>
<td align="right" style="font-size:.85em;color:#666;"><?= $fmtAgo($j['lastRun']) ?></td>
<td class="job-actions" style="white-space:nowrap;">
<?php if (!$j['paused']): ?>
<?= Html::beginForm(['jobs/pause', 'job' => $j['name']]) ?>
<?= Html::submitButton('Pause', ['style' => 'font-size:.78em;padding:1px 6px;cursor:pointer;']) ?>
<?= Html::endForm() ?>
<?php else: ?>
<?= Html::beginForm(['jobs/resume', 'job' => $j['name']]) ?>
<?= Html::submitButton('Resume', ['style' => 'font-size:.78em;padding:1px 6px;cursor:pointer;color:#0a5;']) ?>
<?= Html::endForm() ?>
<?php endif ?>
<?php if (!$j['running']): ?>
<?= Html::beginForm(['jobs/run-now', 'job' => $j['name']]) ?>
<?= Html::submitButton('▶ Now', [
    'style'   => 'font-size:.78em;padding:1px 6px;cursor:pointer;',
    'onclick' => 'return confirm("Run ' . Html::encode($j['name']) . ' immediately?")',
]) ?>
<?= Html::endForm() ?>
<?php endif ?>
</td>
</tr>
<?php endforeach ?>
<?php endforeach ?>
</tbody>
</table>
</div></div>

<?php endif // tableExists ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
#refresh-bar { height:3px; background:#0d6efd; transition:width 1s linear; margin-bottom:.75rem; border-radius:1px; }
.jobs-table td, .jobs-table th { vertical-align:middle; }
.jobs-table .actions form { display:inline; margin-right:2px; }
</style>
<div id="refresh-bar" style="width:100%"></div>

<?php if (!$tableExists): ?>
<div class="alert alert-danger">
    The <code>queue</code> table does not exist yet.
    Run <code>php yii migrate --migrationPath=@yii/queue/db/migrations</code> then
    <code>php yii queue/seed</code> to initialise the queue.
</div>
<?php else: ?>

<!-- Header bar -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-0 fw-semibold">
            Queue Jobs
            <?php if ($balancesLocked): ?>
                <span class="badge bg-warning text-dark ms-2" title="PaymentsJob is running">&#9888; balances locked</span>
            <?php endif ?>
        </h5>
        <small class="text-muted">
            <?= $countRunning ?> running &nbsp;&middot;&nbsp;
            <?= $countActive ?> active &nbsp;&middot;&nbsp;
            <?php if ($countPaused): ?><span class="text-secondary"><?= $countPaused ?> paused</span> &nbsp;&middot;&nbsp;<?php endif ?>
            <?php if ($countMissing): ?><span class="text-danger"><?= $countMissing ?> not seeded</span><?php endif ?>
        </small>
    </div>
    <div>
        <?= Html::beginForm(['jobs/seed-all']) ?>
        <?= Html::submitButton('Seed missing', ['class' => 'btn btn-sm btn-outline-primary']) ?>
        <?= Html::endForm() ?>
    </div>
</div>

<?= \app\widgets\Alert::widget() ?>

<?php foreach ($grouped as $domain => $domainJobs):
    [$label, $icon] = $domainMeta[$domain] ?? [ucfirst($domain), 'circle'];
?>
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-<?= Html::encode(str_replace(['-2', 'bar-', 'trending-', 'share-'], ['2', 'bar-chart-line', 'graph-up', 'share'], $icon)) ?> text-secondary small"></i>
        <strong class="small"><?= Html::encode($label) ?></strong>
        <span class="badge bg-light text-dark border ms-1 fw-normal"><?= count($domainJobs) ?></span>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm table-bordered jobs-table mb-0" style="table-layout:fixed;">
        <colgroup>
            <col style="width:auto;">
            <col style="width:52px;">
            <col style="width:82px;">
            <col style="width:68px;">
            <col style="width:80px;">
            <col style="width:140px;">
        </colgroup>
        <thead class="table-light">
        <tr>
            <th class="small">Job</th>
            <th class="small text-end">Every</th>
            <th class="small text-center">Status</th>
            <th class="small text-end">Runs in</th>
            <th class="small text-end">Last run</th>
            <th class="small actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($domainJobs as $j):
            [$badgeClass, $badgeText] = $badgeAdminlte[$j['status']] ?? ['bg-light text-dark', $j['status']];
        ?>
        <tr>
            <td class="small font-monospace"><?= Html::encode($j['name']) ?></td>
            <td class="small text-end text-muted"><?= $intervalFmt($j['interval']) ?></td>
            <td class="text-center"><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
            <td class="small text-end"><?= $fmtSecs($j['secsLeft']) ?></td>
            <td class="small text-end text-muted"><?= $fmtAgo($j['lastRun']) ?></td>
            <td class="actions">
                <?php if (!$j['paused']): ?>
                    <?= Html::beginForm(['jobs/pause', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('Pause', ['class' => 'btn btn-outline-secondary', 'style' => 'font-size:.72rem;padding:1px 6px;']) ?>
                    <?= Html::endForm() ?>
                <?php else: ?>
                    <?= Html::beginForm(['jobs/resume', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('Resume', ['class' => 'btn btn-outline-success', 'style' => 'font-size:.72rem;padding:1px 6px;']) ?>
                    <?= Html::endForm() ?>
                <?php endif ?>
                <?php if (!$j['running']): ?>
                    <?= Html::beginForm(['jobs/run-now', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('&#9654; Now', [
                        'class'   => 'btn btn-outline-primary',
                        'style'   => 'font-size:.72rem;padding:1px 6px;',
                        'onclick' => 'return confirm("Run ' . Html::encode($j['name']) . ' immediately?")',
                    ]) ?>
                    <?= Html::endForm() ?>
                <?php endif ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>
<?php endforeach ?>
<?php endif // tableExists ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="refresh-bar" class="mb-4 rounded-full overflow-hidden h-0.5 bg-gray-200 dark:bg-gray-700">
    <div id="refresh-bar-fill" class="h-full bg-indigo-500 transition-all duration-1000" style="width:0%"></div>
</div>

<?php if (!$tableExists): ?>
<div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400">
    <p class="font-semibold mb-1">The <code class="font-mono bg-red-100 dark:bg-red-900/40 px-1 rounded">queue</code> table does not exist yet.</p>
    <p>Run <code class="font-mono bg-red-100 dark:bg-red-900/40 px-1 rounded">php yii migrate --migrationPath=@yii/queue/db/migrations</code>
    then <code class="font-mono bg-red-100 dark:bg-red-900/40 px-1 rounded">php yii queue/seed</code> to initialise the queue.</p>
</div>
<?php else: ?>

<!-- Header bar -->
<div class="flex items-start justify-between mb-4 gap-4">
    <div>
        <div class="flex items-center gap-2 mb-0.5">
            <h2 class="text-base font-bold text-gray-800 dark:text-gray-100">Queue Jobs</h2>
            <?php if ($balancesLocked): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                         bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i data-lucide="alert-triangle" class="w-3 h-3"></i> balances locked
            </span>
            <?php endif ?>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 flex flex-wrap gap-x-3 gap-y-0.5">
            <span><span class="font-medium text-blue-600 dark:text-blue-400"><?= $countRunning ?></span> running</span>
            <span><span class="font-medium text-green-600 dark:text-green-400"><?= $countActive ?></span> active</span>
            <?php if ($countPaused): ?>
            <span><span class="font-medium text-gray-500 dark:text-gray-400"><?= $countPaused ?></span> paused</span>
            <?php endif ?>
            <?php if ($countMissing): ?>
            <span><span class="font-medium text-red-500 dark:text-red-400"><?= $countMissing ?></span> not seeded</span>
            <?php endif ?>
        </div>
    </div>
    <div class="shrink-0">
        <?= Html::beginForm(['jobs/seed-all']) ?>
        <?= Html::submitButton('Seed missing', [
            'class' => 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg'
                     . ' border border-indigo-300 dark:border-indigo-700'
                     . ' bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400'
                     . ' hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer',
        ]) ?>
        <?= Html::endForm() ?>
    </div>
</div>

<?= \app\widgets\Alert::widget() ?>

<div class="flex flex-col gap-4">
<?php foreach ($grouped as $domain => $domainJobs):
    [$label, $icon] = $domainMeta[$domain] ?? [ucfirst($domain), 'circle'];
?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <i data-lucide="<?= Html::encode($icon) ?>" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?= Html::encode($label) ?></span>
        <span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= count($domainJobs) ?></span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
        <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
            <th class="px-4 py-2.5 text-left">Job</th>
            <th class="px-3 py-2.5 text-right w-14">Every</th>
            <th class="px-3 py-2.5 text-center w-20">Status</th>
            <th class="px-3 py-2.5 text-right w-16">Runs in</th>
            <th class="px-3 py-2.5 text-right w-20">Last run</th>
            <th class="px-3 py-2.5 text-left w-36">Actions</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
        <?php foreach ($domainJobs as $j):
            [$twCls, $twText] = $badgeTailwind[$j['status']] ?? ['bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400', $j['status']];
            $rowCls = $j['status'] === 'running'
                ? 'bg-blue-50/30 dark:bg-blue-900/10'
                : ($j['status'] === 'not_seeded' ? 'bg-red-50/30 dark:bg-red-900/10' : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/20');
        ?>
        <tr class="<?= $rowCls ?> transition-colors">
            <td class="px-4 py-2 font-mono text-gray-700 dark:text-gray-300"><?= Html::encode($j['name']) ?></td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500"><?= $intervalFmt($j['interval']) ?></td>
            <td class="px-3 py-2 text-center">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium <?= $twCls ?>"><?= $twText ?></span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300"><?= $fmtSecs($j['secsLeft']) ?></td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500 whitespace-nowrap"><?= $fmtAgo($j['lastRun']) ?></td>
            <td class="px-3 py-2">
                <div class="flex items-center gap-1 flex-wrap">
                <?php if (!$j['paused']): ?>
                    <?= Html::beginForm(['jobs/pause', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('Pause', [
                        'class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium cursor-pointer'
                                 . ' border border-gray-300 dark:border-gray-600'
                                 . ' bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                 . ' hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors',
                    ]) ?>
                    <?= Html::endForm() ?>
                <?php else: ?>
                    <?= Html::beginForm(['jobs/resume', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('Resume', [
                        'class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium cursor-pointer'
                                 . ' border border-green-300 dark:border-green-700'
                                 . ' bg-white dark:bg-gray-700 text-green-600 dark:text-green-400'
                                 . ' hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors',
                    ]) ?>
                    <?= Html::endForm() ?>
                <?php endif ?>
                <?php if (!$j['running']): ?>
                    <?= Html::beginForm(['jobs/run-now', 'job' => $j['name']]) ?>
                    <?= Html::submitButton('▶ Now', [
                        'class'   => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium cursor-pointer'
                                   . ' border border-indigo-300 dark:border-indigo-700'
                                   . ' bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400'
                                   . ' hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors',
                        'onclick' => 'return confirm("Run ' . Html::encode($j['name']) . ' immediately?")',
                    ]) ?>
                    <?= Html::endForm() ?>
                <?php endif ?>
                </div>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>
<?php endforeach ?>
</div>

<?php endif // tableExists ?>
<?php endif // scheme ?>

<script>
(function () {
    var total   = 15;
    var elapsed = 0;
    <?php if ($isTailwind): ?>
    var fill = document.getElementById('refresh-bar-fill');
    function tick() {
        elapsed++;
        if (fill) fill.style.width = ((elapsed / total) * 100) + '%';
        if (elapsed >= total) { location.reload(); } else { setTimeout(tick, 1000); }
    }
    <?php else: ?>
    var bar = document.getElementById('refresh-bar');
    function tick() {
        elapsed++;
        if (bar) bar.style.width = ((elapsed / total) * 100) + '%';
        if (elapsed >= total) { location.reload(); } else { setTimeout(tick, 1000); }
    }
    <?php endif ?>
    setTimeout(tick, 1000);
})();
<?php if ($isTailwind): ?>
if (typeof lucide !== 'undefined') lucide.createIcons();
<?php endif ?>
</script>
