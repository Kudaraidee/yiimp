<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$height     = '240px';

$algo       = Yii::$app->session->get('yaamp-algo');
$homeUrl    = Yii::$app->homeUrl;
$algoFactor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
$algoUnit   = match (true) {
    $algoFactor == 0.001       => 'Kh',
    $algoFactor == 1000        => 'Gh',
    $algoFactor == 1000000     => 'Th',
    $algoFactor == 1000000000  => 'Ph',
    default                    => 'Mh',
};

$hasGraphs = ($algo !== 'all');

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
.mining-col { min-width:0; }
.graph-wrap { overflow:hidden; width:100%; }
.graph-wrap canvas { max-width:100%!important; }
.main-left-box { margin-bottom:1rem; }
</style>
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>
<div class="row gx-4 mt-2">
    <div class="col-12 col-md-6 mining-col">
        <div id="mining_results"></div>
        <?php if ($hasGraphs): ?>
        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Estimate (<?= htmlspecialchars($algo) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="graph_results_price" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Hashrate (<?= htmlspecialchars($algo) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="pool_hashrate_results" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
        <?php endif ?>
    </div>
    <div class="col-12 col-md-6 mining-col" style="overflow-x:auto;">
        <div id="pool_current_results"></div>
        <div id="found_results"></div>
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
<div class="row gx-3 mt-2">
    <div class="col-12 col-md-6">
        <div id="mining_results" class="mb-3"></div>
        <?php if ($hasGraphs): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-graph-up me-1"></i>
                Last 24h Estimate — <span class="font-monospace"><?= htmlspecialchars($algo) ?></span>
            </div>
            <div class="card-body">
                <div id="graph_results_price" style="height:<?= $height ?>;"></div>
            </div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-activity me-1"></i>
                Last 24h Hashrate — <span class="font-monospace"><?= htmlspecialchars($algo) ?></span>
            </div>
            <div class="card-body">
                <div id="pool_hashrate_results" style="height:<?= $height ?>;"></div>
            </div>
        </div>
        <?php endif ?>
    </div>
    <div class="col-12 col-md-6" style="overflow-x:auto;">
        <div id="pool_current_results" class="mb-3"></div>
        <div id="found_results"></div>
    </div>
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
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">

    <div class="flex flex-col gap-4 min-w-0">
        <div id="mining_results"></div>

        <?php if ($hasGraphs): ?>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                <i data-lucide="trending-up" class="w-4 h-4 text-indigo-500 shrink-0"></i>
                Last 24h Estimate
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                             text-indigo-700 dark:text-indigo-300 font-mono ml-auto">
                    <?= htmlspecialchars($algo) ?>
                </span>
            </div>
            <div class="p-4">
                <div id="graph_results_price" style="height:<?= $height ?>;"></div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                <i data-lucide="activity" class="w-4 h-4 text-indigo-500 shrink-0"></i>
                Last 24h Hashrate
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                             text-indigo-700 dark:text-indigo-300 font-mono ml-auto">
                    <?= htmlspecialchars($algo) ?>
                </span>
            </div>
            <div class="p-4">
                <div id="pool_hashrate_results" style="height:<?= $height ?>;"></div>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="flex flex-col gap-4 min-w-0 overflow-x-auto">
        <div id="pool_current_results"></div>
        <div id="found_results"></div>
    </div>

</div>

<?php endif ?>

<script>
var global_algo = '<?= addslashes($algo) ?>';
var querystring = global_algo ? '?algo=' + encodeURIComponent(global_algo) : '';

function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + encodeURIComponent(algo) + '&r=<?= $homeUrl ?>site/mining';
}

function page_refresh() {
    pool_current_refresh();
    mining_refresh();
    found_refresh();
    if (global_algo !== 'all') {
        main_refresh_price();
        pool_hashrate_refresh();
    }
}

function pool_current_refresh() {
    $.get('<?= $homeUrl ?>site/current_results' + querystring, '', function(d) {
        $('#pool_current_results').html(d);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}
function mining_refresh() {
    $.get('<?= $homeUrl ?>site/mining_results' + querystring, '', function(d) {
        $('#mining_results').html(d);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}
function found_refresh() {
    $.get('<?= $homeUrl ?>site/found_results' + querystring, '', function(d) { $('#found_results').html(d); });
}

function main_refresh_price() {
    $.get('<?= $homeUrl ?>site/graph_price_results' + querystring, '', function(data) {
        try {
            yiimpChart('graph_results_price', JSON.parse(data), {
                title:   'Estimate (mBTC/<?= $algoUnit ?>/day)',
                labels:  ['Raw', 'Smoothed'],
                decimals: 5
            });
        } catch(e) {}
    });
}

function pool_hashrate_refresh() {
    $.get('<?= $homeUrl ?>site/graph_hashrate_results' + querystring, '', function(data) {
        try {
            yiimpChart('pool_hashrate_results', JSON.parse(data), {
                title:  'Pool Hashrate (<?= $algoUnit ?>/s)',
                labels: ['Raw', 'Smoothed']
            });
        } catch(e) {}
    });
}
</script>
