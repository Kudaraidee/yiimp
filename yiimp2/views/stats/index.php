<?php

/** @var yii\web\View  $this     */
/** @var string        $algo     */
/** @var string        $algoUnit */
/** @var array         $algos    */  // ['algoname' => coinCount, ...]
/** @var array         $stats1   */  // ['hashrate', 'total', 'btcPerMhd']
/** @var array         $stats2   */
/** @var array         $stats3   */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$height     = '240px';
$au         = Html::encode($algoUnit);

$options = '';
foreach ($algos as $a => $count) {
    $sel      = ($a === $algo) ? ' selected' : '';
    $options .= '<option value="' . Html::encode($a) . '"' . $sel . '>' . Html::encode($a) . '</option>';
}

// Three period columns — same structure, different graph IDs
$periods = [
    ['label' => 'Last 48 Hours', 'stats' => $stats1, 'graphs' => [1, 2, 3]],
    ['label' => 'Last 7 Days',   'stats' => $stats2, 'graphs' => [4, 5, 6]],
    ['label' => 'Last 30 Days',  'stats' => $stats3, 'graphs' => [7, 8, 9]],
];

$graphInits = [
    1 => "yiimpChart('graph_results_1', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    2 => "yiimpChart('graph_results_2', JSON.parse(data), { type: 'bar', title: 'BTC/Day', btcDecimals: true });",
    3 => "yiimpChart('graph_results_3', JSON.parse(data), { type: 'bar', title: 'BTC/{$au}/d', btcDecimals: true });",
    4 => "yiimpChart('graph_results_4', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    5 => "yiimpChart('graph_results_5', JSON.parse(data), { type: 'bar', title: 'BTC/Day', btcDecimals: true });",
    6 => "yiimpChart('graph_results_6', JSON.parse(data), { type: 'bar', title: 'BTC/{$au}/d', btcDecimals: true });",
    7 => "yiimpChart('graph_results_7', JSON.parse(data), { title: 'Hashrate ({$au}/s)', fill: true, colors: ['rgba(78,180,180,0.8)'], decimals: 3 });",
    8 => "yiimpChart('graph_results_8', JSON.parse(data), { title: 'BTC/Day', btcDecimals: true });",
    9 => "yiimpChart('graph_results_9', JSON.parse(data), { title: 'BTC/{$au}/d', btcDecimals: true });",
];

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Stats cards — mirrors /site/mining ──────────────────────────────────── */
#stats-cards .stats-toolbar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 10px 14px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
#stats-cards .st-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
#stats-cards .st-select {
    padding: 6px 10px; border: 1px solid #d8e2ef; border-radius: 6px;
    background: #fafbfc; color: #33405a; font: inherit; font-size: 11px; font-weight: 700;
}
#stats-cards .st-live {
    margin-left: auto; display: flex; align-items: center; gap: 6px;
    font-size: 11px; color: #5b6675; white-space: nowrap;
}
#stats-cards .st-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#stats-cards .main-left-box {
    height: 100%; border: 1px solid #d8e2ef !important; border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; margin-bottom: 1rem;
}
#stats-cards .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
    color: #26385a; font-size: 13px !important; font-weight: 800 !important; line-height: 1.3;
}
#stats-cards .st-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#stats-cards .st-title { font-size: 13px; font-weight: 800; color: #26385a; }
#stats-cards .st-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#stats-cards .st-chip {
    display: inline-block; padding: 2px 8px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; white-space: nowrap;
}
#stats-cards .main-left-inner { padding: 0 !important; }
#stats-cards .st-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 8px 12px;
    background: #fcfdff; border-bottom: 1px solid #eef2f7;
    font-size: 10px; color: #8a94a6;
}
#stats-cards .st-summary b { color: #33405a; font-weight: 700; }
#stats-cards .st-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}
#stats-cards .st-metrics { width: 100%; border-collapse: collapse; font-size: 11px; color: #334155; }
#stats-cards .st-metrics th {
    padding: 7px 12px; text-align: left; font-size: 9px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; color: #8a94a6;
    border-bottom: 1px solid #eef2f7; background: #fff;
}
#stats-cards .st-metrics td {
    padding: 7px 12px; text-align: right; border-bottom: 1px solid #eef2f7;
}
#stats-cards .st-metrics tr:last-child th,
#stats-cards .st-metrics tr:last-child td { border-bottom: 0; }
#stats-cards .st-val {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums; font-size: 11px; font-weight: 700; color: #2b3d5b;
}
#stats-cards .graph-wrap { padding: 8px 12px 6px; }
#stats-cards .graph-wrap + .graph-wrap { border-top: 1px solid #f1f5f9; }
#stats-cards .st-foot {
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    color: #98a3b5; font-size: 9px; line-height: 1.5;
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
}
@media(max-width:820px){ #stats-cards .st-sub { display: none; } }
</style>

<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div id="stats-cards">
<div class="stats-toolbar">
    <span class="st-label">Algorithm</span>
    <select id="algo_select" class="st-select"><?= $options ?></select>
    <span class="st-live"><i></i> live · 60s</span>
</div>

<div class="row gx-3 mt-2">
<?php foreach ($periods as $p): ?>
    <div class="col-12 col-md-4">
        <div class="main-left-box">
            <div class="main-left-title">
                <span class="st-head-main"><span class="st-title"><?= Html::encode($p['label']) ?></span>
                    <span class="st-sub">Network averages</span></span>
                <span class="st-chip"><?= $au ?></span>
            </div>
            <div class="main-left-inner">
                <div class="st-summary">
                    <span><i></i><b><?= Html::encode($p['stats']['hashrate']) ?>h/s</b> average</span>
                    <span><?= Html::encode($p['stats']['total']) ?> BTC</span>
                </div>
                <table class="st-metrics"><tbody>
                    <tr><th>Average Hashrate</th><td><span class="st-val"><?= Html::encode($p['stats']['hashrate']) ?>h/s</span></td></tr>
                    <tr><th>BTC Value</th><td><span class="st-val"><?= Html::encode($p['stats']['total']) ?></span></td></tr>
                    <tr><th>BTC/<?= $au ?>/d</th><td><span class="st-val"><?= Html::encode($p['stats']['btcPerMhd']) ?></span></td></tr>
                </tbody></table>
                <?php foreach ($p['graphs'] as $gid): ?>
                <div class="graph-wrap">
                    <div id="graph_results_<?= $gid ?>" style="height:<?= $height ?>"></div>
                </div>
                <?php endforeach ?>
                <div class="st-foot"><span>Hashrate · BTC/day · BTC/<?= $au ?>/day</span><span>Refresh 60s</span></div>
            </div>
        </div>
    </div>
<?php endforeach ?>
</div>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="alert alert-warning d-flex align-items-center gap-2 mb-3"
     style="cursor:pointer;display:none!important;"
     onclick="auto_page_resume()">
    <i class="bi bi-pause-circle-fill"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="d-flex align-items-center gap-2 mb-3">
    <label class="text-muted small mb-0">Algo:</label>
    <select id="algo_select" class="form-select form-select-sm" style="width:140px;">
        <?= $options ?>
    </select>
</div>

<div class="row gx-3">
<?php foreach ($periods as $p): ?>
    <div class="col-12 col-md-4 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <strong class="small"><?= Html::encode($p['label']) ?></strong>
                <span class="badge bg-light text-dark border font-monospace ms-1"><?= $au ?></span>
            </div>
            <div class="card-body pb-0">
                <ul class="list-unstyled small mb-3">
                    <li class="text-muted mb-1">
                        Hashrate
                        <strong class="text-dark ms-1"><?= Html::encode($p['stats']['hashrate']) ?>h/s</strong>
                    </li>
                    <li class="text-muted mb-1">
                        BTC Value
                        <strong class="text-dark ms-1 font-monospace"><?= Html::encode($p['stats']['total']) ?></strong>
                    </li>
                    <li class="text-muted">
                        BTC/<?= $au ?>/d
                        <strong class="text-dark ms-1 font-monospace"><?= Html::encode($p['stats']['btcPerMhd']) ?></strong>
                    </li>
                </ul>
                <?php foreach ($p['graphs'] as $gid): ?>
                <div id="graph_results_<?= $gid ?>"
                     style="height:<?= $height ?>;<?= $gid !== $p['graphs'][0] ? 'margin-top:1rem;' : '' ?>"></div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
<?php endforeach ?>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl
            bg-amber-50 dark:bg-amber-900/20
            border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 text-sm cursor-pointer"
     style="display:none;"
     onclick="auto_page_resume()">
    <i data-lucide="pause-circle" class="w-5 h-5 shrink-0"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="flex items-center gap-2 mb-4">
    <label class="text-xs text-gray-400 dark:text-gray-500">Algo:</label>
    <select id="algo_select"
            class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                   focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <?= $options ?>
    </select>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<?php foreach ($periods as $p): ?>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 shadow-sm overflow-hidden flex flex-col">

        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                    flex items-center gap-2">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                <?= Html::encode($p['label']) ?>
            </span>
            <span class="px-1.5 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                         text-indigo-700 dark:text-indigo-300 font-mono ml-auto">
                <?= $au ?>
            </span>
        </div>

        <div class="px-4 py-3">
            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs mb-4">
                <dt class="text-gray-400 dark:text-gray-500">Hashrate</dt>
                <dd class="text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                    <?= Html::encode($p['stats']['hashrate']) ?>h/s
                </dd>
                <dt class="text-gray-400 dark:text-gray-500">BTC Value</dt>
                <dd class="text-right font-mono text-gray-700 dark:text-gray-300">
                    <?= Html::encode($p['stats']['total']) ?>
                </dd>
                <dt class="text-gray-400 dark:text-gray-500">BTC/<?= $au ?>/d</dt>
                <dd class="text-right font-mono text-gray-700 dark:text-gray-300">
                    <?= Html::encode($p['stats']['btcPerMhd']) ?>
                </dd>
            </dl>

            <?php foreach ($p['graphs'] as $gid): ?>
            <div id="graph_results_<?= $gid ?>"
                 style="height:<?= $height ?>;<?= $gid !== $p['graphs'][0] ? 'margin-top:1rem;' : '' ?>"></div>
            <?php endforeach ?>
        </div>
    </div>
<?php endforeach ?>
</div>

<?php endif ?>

<script>
$('#algo_select').change(function () {
    window.location.href = '/site/algo?algo=' + encodeURIComponent($('#algo_select').val()) + '&r=/stats';
});

function page_refresh() {
    for (var i = 1; i <= 9; i++) {
        (function (n) {
            $.get('/stats/graph_results_' + n, '', function (data) {
                window['graph_init_' + n](data);
            });
        })(i);
    }
}

<?php foreach ($graphInits as $n => $body): ?>
function graph_init_<?= $n ?>(data) { <?= $body ?> }
<?php endforeach ?>
</script>
