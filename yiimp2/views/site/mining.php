<?php

/**
 * @var yii\web\View $this
 * @var array $algoNav
 */

use yii\helpers\Html;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$algoNav    = $algoNav ?? ['active' => 'all', 'items' => []];
$activeAlgo = $algoNav['active'] ?? 'all';
$algoItems  = $algoNav['items'] ?? [];
$activeItem = $algoItems[$activeAlgo] ?? ['label' => $activeAlgo, 'port' => 0, 'workers' => 0,
    'pool_hash' => '0 h/s', 'network_hash' => '—'];

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

$graphLabel = ($algo === 'all') ? 'all algos' : $algo;

?>
<style>
.mining-col { min-width: 0; }
.graph-wrap { overflow: hidden; width: 100%; }
.graph-wrap canvas { max-width: 100% !important; }
.main-left-box { margin-bottom: 1rem; }

/* ── Algo scope bar ───────────────────────────────────────────────────────── */
.mining-algo-bar {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 12px 14px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
.mining-algo-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
.mining-algo-tabs {
    display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px;
    flex: 1 1 420px;
}
.mining-algo-tab {
    border: 1px solid #dfe5ec; background: #fafbfc; border-radius: 7px;
    padding: 6px 11px; min-width: 96px; text-align: left; cursor: pointer;
    font-family: inherit; line-height: 1.15; transition: .15s;
}
.mining-algo-tab:hover { border-color: var(--algo-color, #a9c4ea); background: #f7faff; }
.mining-algo-tab b { display: block; font-size: 12px; color: #33405a; }
.mining-algo-tab small { display: block; font-size: 10px; color: #8a94a6; margin-top: 2px; }
.mining-algo-tab .dot {
    display: inline-block; width: 7px; height: 7px; border-radius: 50%;
    margin-right: 5px; vertical-align: 1px; background: var(--algo-color, #888);
}
.mining-algo-tab.active {
    border-color: var(--algo-color, #2c5aa0);
    background: #eef4ff;
    box-shadow: inset 0 0 0 1px var(--algo-color, #2c5aa0);
}
.mining-algo-tab.active b { color: #1f467f; }
.mining-algo-tab.active small { color: #4f74ad; }
.mining-algo-live {
    margin-left: auto; white-space: nowrap; font-size: 11px; color: #5b6675;
    display: flex; align-items: center; gap: 6px;
}
.mining-algo-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
.mining-algo-summary {
    background: #f8fbff; border: 1px solid #d8e2ef;
    border-left: 3px solid var(--algo-color, #2c5aa0);
    border-radius: 6px; padding: 8px 14px; margin-bottom: 16px;
    display: flex; gap: 22px; flex-wrap: wrap; font-size: 12px; color: #4a586c;
}
.mining-algo-summary b { color: #223a5e; }
.mining-algo-summary .sep { color: #ccd4e0; }

/* ── Merged Miners Version card ──────────────────────────────────────────── */
.mining-new > .main-left-box {
    border-color: #b9d4ff !important;
    box-shadow: 0 0 0 2px rgba(70,130,220,.08);
}
</style>

<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div class="mining-algo-bar" style="--algo-color: <?= Html::encode($activeItem['color'] ?? '#2c5aa0') ?>">
    <span class="mining-algo-label">Algorithm</span>
    <div class="mining-algo-tabs" title="กดเพื่อเลือกอัลกอริทึม">
        <?php foreach ($algoItems as $key => $item): ?>
            <?php $color = Html::encode($item['color'] ?? '#888888'); ?>
            <button type="button" class="mining-algo-tab <?= $key === $activeAlgo ? 'active' : '' ?>"
                    style="--algo-color: <?= $color ?>" data-algo="<?= Html::encode($key) ?>"
                    onclick='select_algo(<?= json_encode($key) ?>)'>
                <b><i class="dot"></i><?= Html::encode($item['label'] ?? $key) ?></b>
                <small data-algo-sub>
                    <?php if ($key === 'all'): ?>
                        <?= count($algoItems) - 1 ?> algos
                    <?php elseif (!empty($item['port'])): ?>
                        port <?= (int) $item['port'] ?> · <?= (int) $item['workers'] ?> workers
                    <?php else: ?>
                        no stratum
                    <?php endif ?>
                </small>
            </button>
        <?php endforeach; ?>
    </div>
    <span class="mining-algo-live"><i></i> live · 60s</span>
</div>

<div class="mining-algo-summary" id="mining_algo_summary"
     style="--algo-color: <?= Html::encode($activeItem['color'] ?? '#2c5aa0') ?>">
    <span>Algo <b id="algo-summary-algo"><?= Html::encode($activeItem['label'] ?? $activeAlgo) ?></b></span>
    <span class="sep">|</span>
    <span>Port <b id="algo-summary-port"><?= !empty($activeItem['port']) ? (int) $activeItem['port'] : '—' ?></b></span>
    <span class="sep">|</span>
    <span>Workers <b id="algo-summary-workers"><?= (int) ($activeItem['workers'] ?? 0) ?></b></span>
    <span class="sep">|</span>
    <span>Pool hash <b id="algo-summary-pool"><?= Html::encode($activeItem['pool_hash'] ?? '0 h/s') ?></b></span>
    <span class="sep">|</span>
    <span>Network <b id="algo-summary-network"><?= Html::encode($activeItem['network_hash'] ?? '—') ?></b></span>
</div>

<div class="row gx-3 mt-2">
    <div class="col-12 col-lg-7 mining-col">
        <div id="mining_results" style="overflow-x:auto;"></div>
    </div>
    <div class="col-12 col-lg-5 mining-col" style="overflow-x:auto;">
        <div id="pool_current_results"></div>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12 col-lg-6 mining-col">
        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Estimate (<?= htmlspecialchars($graphLabel) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="graph_results_price" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 mining-col">
        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Hashrate (<?= htmlspecialchars($graphLabel) ?>)</div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="pool_hashrate_results" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12 col-lg-8 mining-col mining-new">
        <div id="miners_results" style="overflow-x:auto;"></div>
    </div>
    <div class="col-12 col-lg-4 mining-col" style="overflow-x:auto;">
        <div id="found_results"></div>
    </div>
</div>

<script>
var global_algo = <?= json_encode($algo) ?>;
var querystring = global_algo ? '?algo=' + encodeURIComponent(global_algo) : '';

function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + encodeURIComponent(algo) + '&r=<?= $homeUrl ?>site/mining';
}

function page_refresh() {
    algo_summary_refresh();
    pool_current_refresh();
    mining_refresh();
    found_refresh();
    miners_refresh();
    main_refresh_price();
    pool_hashrate_refresh();
}

function algo_summary_refresh() {
    $.get('<?= $homeUrl ?>site/mining_summary', '', function (d) {
        if (!d || !d.items) return;
        var item = d.items[d.active] || d.items.all;
        if (!item) return;

        $('#algo-summary-algo').text(item.label || d.active);
        $('#algo-summary-port').text(item.port ? item.port : '—');
        $('#algo-summary-workers').text(item.workers || 0);
        $('#algo-summary-pool').text(item.pool_hash || '0 h/s');
        $('#algo-summary-network').text(item.network_hash || '—');
        $('#mining_algo_summary').css('--algo-color', item.color || '#2c5aa0');
        $('.mining-algo-bar').css('--algo-color', item.color || '#2c5aa0');

        $('.mining-algo-tab').each(function () {
            var sub = $(this).find('[data-algo-sub]');
            var it = d.items[$(this).data('algo')];
            if (!sub.length || !it) return;
            if ($(this).data('algo') === 'all') {
                sub.text(Object.keys(d.items).length - 1 + ' algos');
            } else {
                sub.text(it.port ? 'port ' + it.port + ' · ' + it.workers + ' workers' : 'no stratum');
            }
        });
    });
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
function miners_refresh() {
    $.get('<?= $homeUrl ?>site/miners_results', '', function(d) {
        $('#miners_results').html(d);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
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
