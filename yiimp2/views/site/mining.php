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

/* ── Algo picker — wrapped searchable chips (no horizontal scroll) ─────────── */
.mining-algo-bar {
    display: flex; flex-direction: column; gap: 10px;
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 11px 14px 12px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
.mining-algo-bar-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.mining-algo-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
.mining-algo-search {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 9px; border: 1px solid #dfe5ec; border-radius: 7px;
    background: #fafbfc; color: #8a94a6;
}
.mining-algo-search:focus-within { border-color: #a9c4ea; background: #fff; }
.mining-algo-search input {
    width: 180px; border: 0; outline: 0; background: transparent;
    font: 11px/1.4 inherit; color: #26385a;
}
.mining-algo-search input::placeholder { color: #aab4c4; }
.mining-algo-count { font-size: 10px; color: #8a94a6; font-variant-numeric: tabular-nums; }
.mining-algo-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.mining-algo-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 9px; border: 1px solid #dfe5ec; background: #fafbfc;
    border-radius: 999px; cursor: pointer; font-family: inherit;
    line-height: 1.2; transition: .15s; white-space: nowrap;
}
.mining-algo-chip:hover { border-color: var(--algo-color, #a9c4ea); background: #f7faff; }
.mining-algo-chip b { font-size: 11px; color: #33405a; font-weight: 700; }
.mining-algo-chip small {
    font-size: 9px; color: #98a3b5; font-variant-numeric: tabular-nums;
}
.mining-algo-chip .dot {
    width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto;
    background: var(--algo-color, #888);
}
.mining-algo-chip.active {
    border-color: var(--algo-color, #2c5aa0); background: #eef4ff;
    box-shadow: inset 0 0 0 1px var(--algo-color, #2c5aa0);
}
.mining-algo-chip.active b { color: #1f467f; }
.mining-algo-chip.active small { color: #4f74ad; }
.mining-algo-empty {
    display: none; padding: 6px 2px; font-size: 11px; color: #98a3b5;
}
.mining-algo-live {
    margin-left: auto; white-space: nowrap; font-size: 11px; color: #5b6675;
    display: flex; align-items: center; gap: 6px;
}
.mining-algo-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
.mining-algo-live #mining-last-update {
    color: #98a3b5; font-size: 10px; font-variant-numeric: tabular-nums;
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
    <div class="mining-algo-bar-head">
        <span class="mining-algo-label">Algorithm</span>
        <label class="mining-algo-search">
            <span aria-hidden="true">⌕</span>
            <input type="search" id="algo-filter" placeholder="Filter algos…" autocomplete="off"
                   aria-label="Filter algorithms">
        </label>
        <span class="mining-algo-count" id="algo-count"><?= count($algoItems) - 1 ?> algos</span>
        <span class="mining-algo-live"><i></i> live · 60s <span id="mining-last-update">just now</span></span>
    </div>
    <div class="mining-algo-chips" id="algo-chips">
        <?php foreach ($algoItems as $key => $item): ?>
            <?php $color = Html::encode($item['color'] ?? '#888888'); ?>
            <button type="button" class="mining-algo-chip <?= $key === $activeAlgo ? 'active' : '' ?>"
                    style="--algo-color: <?= $color ?>" data-algo="<?= Html::encode($key) ?>"
                    title="<?= Html::encode($key === 'all' ? 'All algorithms' : (!empty($item['port']) ? 'port ' . (int) $item['port'] . ' · ' . (int) $item['workers'] . ' workers' : 'no stratum')) ?>"
                    onclick='select_algo(<?= json_encode($key) ?>)'>
                <i class="dot"></i>
                <b><?= Html::encode($item['label'] ?? $key) ?></b>
                <small data-algo-sub><?php if ($key === 'all'): ?>
                    <?= count($algoItems) - 1 ?>
                <?php elseif (!empty($item['port'])): ?>
                    <?= (int) $item['port'] ?>
                <?php else: ?>
                    —
                <?php endif ?></small>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="mining-algo-empty" id="algo-empty">No algorithm matches that filter.</div>
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

<div id="mining-cards">
<div class="row gx-3 gy-3 mt-2 mining-top-row">
    <div class="col-12 col-lg-6 mining-col">
        <?= $this->render('_mining_command', ['algoNav' => $algoNav]) ?>
    </div>
    <div class="col-12 col-lg-6 mining-col">
        <div class="main-left-box">
            <div class="main-left-title">
                <span class="mn-head-main">
                    <span>Last 24 Hours Estimate</span>
                    <span class="mn-sub"><?= htmlspecialchars($graphLabel) ?></span>
                </span>
                <span class="mc-badge">24h</span>
            </div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="graph_results_price" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-3 gy-3 mining-top-row">
    <div class="col-12 col-lg-6 mining-col" style="overflow-x:auto;">
        <div id="pool_current_results"></div>
    </div>
    <div class="col-12 col-lg-6 mining-col">
        <div class="main-left-box">
            <div class="main-left-title">
                <span class="mn-head-main">
                    <span>Last 24 Hours Hashrate</span>
                    <span class="mn-sub"><?= htmlspecialchars($graphLabel) ?></span>
                </span>
                <span class="mc-badge">24h</span>
            </div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="pool_hashrate_results" style="height:<?= $height ?>"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12 mining-col">
        <div id="mining_results" style="overflow-x:auto;"></div>
    </div>
    <div class="col-12 mining-col" style="overflow-x:auto;">
        <div id="found_results"></div>
    </div>
</div>

<div class="row gx-3">
    <div class="col-12 mining-col mining-new">
        <div id="miners_results" style="overflow-x:auto;"></div>
    </div>
</div>
</div>

<style>
/* ── Consistent card rhythm ────────────────────────────────────────────────── */
#mining-cards {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
#mining-cards > .row {
    --bs-gutter-x: 1rem;
    --bs-gutter-y: 0;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}
#mining-cards > .row > [class*="col-"] { margin-top: 0; }
#mining-cards > .row:not(.mining-top-row) > [class*="col-"] + [class*="col-"] {
    margin-top: 16px;
}
/* AJAX partials may end with whitespace text nodes; flex containers ignore them
   so the gap between stacked cards stays exactly 16px. */
#mining-cards #pool_current_results,
#mining-cards #mining_results,
#mining-cards #found_results,
#mining-cards #miners_results {
    display: flex;
    flex-direction: column;
}
#mining-cards #pool_current_results > br,
#mining-cards #mining_results > br,
#mining-cards #found_results > br,
#mining-cards #miners_results > br { display: none; }
@media (max-width: 991.98px) {
    /* Bootstrap stacks col-lg-6 here; keep the same 16px vertical rhythm. */
    #mining-cards > .mining-top-row > [class*="col-"] + [class*="col-"] {
        margin-top: 16px;
    }
}

/* ── Shared mining card chrome ─────────────────────────────────────────────── */
#mining-cards .main-left-box {
    border: 1px solid #d8e2ef !important;
    border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    overflow: hidden;
    margin-bottom: 0;
}
#mining-cards .main-left-title {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 34px;
    padding: 9px 12px !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #e8eef6;
    color: #26385a;
    font-size: 13px !important;
    font-weight: 800 !important;
    line-height: 1.3;
}
#mining-cards .mn-head-main,
#mining-cards .bf-head-main {
    display: flex;
    align-items: baseline;
    gap: 9px;
    flex-wrap: wrap;
    min-width: 0;
}
#mining-cards .mn-sub,
#mining-cards .bf-sub {
    font-size: 10px;
    color: #8a94a6;
    font-weight: 500;
}
#mining-cards .main-left-inner { padding: 0 !important; }
#mining-cards .mc-gen { padding: 12px; }
#mining-cards .graph-wrap { padding: 8px 12px 6px; }

/* ── Top four cards: equal width and equal height ─────────────────────────── */
#mining-cards .mining-top-row > .mining-col { display: flex; }
#mining-cards .mining-top-row > .mining-col > #pool_current_results {
    width: 100%;
    display: flex;
}
#mining-cards .mining-top-row > .mining-col > .main-left-box,
#mining-cards .mining-top-row > .mining-col > #pool_current_results > .main-left-box {
    width: 100%;
    min-height: 332px;
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
}
#mining-cards .mining-top-row .main-left-box > .main-left-inner {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
#mining-cards .mining-top-row .graph-wrap {
    flex: 1;
    display: flex;
    align-items: center;
}
#mining-cards .mining-top-row .graph-wrap > div { flex: 1 1 auto; }
#pool_current_results .ps-foot { margin-top: auto; }
#mining-cards .mining-new > .main-left-box {
    border-color: #d8e2ef !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}

/* Table shell — identical across Pool Status, Mining, Blocks and Miners Version */
#mining-cards #maintable1,
#mining-cards #maintable2,
#mining-cards #maintable3,
#mining-cards #found-table {
    width: 100% !important;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
    color: #334155;
}
#mining-cards #maintable1 th,
#mining-cards #maintable2 th,
#mining-cards #maintable3 th,
#mining-cards #found-table th {
    padding: 7px 9px !important;
    background: #fff !important;
    border-bottom: 1px solid #e8eef6;
    color: #8a94a6;
    font-size: 9px !important;
    font-weight: 700 !important;
    letter-spacing: .05em;
    text-transform: uppercase;
    white-space: nowrap;
}
#mining-cards #maintable1 td,
#mining-cards #maintable2 td,
#mining-cards #maintable3 td,
#mining-cards #found-table td {
    padding: 8px 9px !important;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}
#mining-cards #maintable1 tbody tr:last-child td,
#mining-cards #maintable2 tbody tr:last-child td,
#mining-cards #maintable3 tbody tr:last-child td,
#mining-cards #found-table tbody tr:last-child td { border-bottom: 0; }
#mining-cards #maintable1 tbody tr:hover,
#mining-cards #maintable2 tbody tr:hover,
#mining-cards #maintable3 tbody tr:hover,
#mining-cards #found-table tbody tr:hover { background: #f7faff; }
#mining-cards #maintable1 th.num,
#mining-cards #maintable1 td.num,
#mining-cards #maintable2 th.num,
#mining-cards #maintable2 td.num { text-align: right; }
#mining-cards .tablesorter-header { cursor: pointer; }
#mining-cards .tablesorter-header:hover { color: #2c5aa0; }

/* Shared footers */
#mining-cards .ps-foot,
#mining-cards .mn-foot,
#mining-cards .bf-foot,
#mining-cards .mv-foot {
    padding: 7px 12px !important;
    background: #fcfdff !important;
    border-top: 1px solid #eef2f7;
    color: #98a3b5;
    font-size: 9px !important;
    line-height: 1.5;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
#mining-cards .ps-foot b,
#mining-cards .mn-foot b,
#mining-cards .bf-foot b,
#mining-cards .mv-foot b { color: #7d8aa0; }

/* Shared chips / status pills */
#mining-cards .mc-badge,
#mining-cards .mn-scope,
#mining-cards .bf-chip,
#mining-cards .mv-head .pill {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 999px;
    background: #eef4ff;
    border: 1px solid #d7e5ff;
    color: #2c5aa0;
    font-family: inherit;
    font-size: 9px;
    font-weight: 700;
    line-height: 14px;
    white-space: nowrap;
}
#mining-cards .mv-head .pill.gray { background: #f1f5f9; border-color: #e2e8f0; color: #64748b; }
#mining-cards .bf-chip.confirmed { background: #e8f7ee; border-color: #ccebd8; color: #187d46; }
#mining-cards .bf-chip.immature { background: #fff7e8; border-color: #ffe2b8; color: #a86400; }
#mining-cards .bf-chip.orphan { background: #fdecec; border-color: #f6d2d2; color: #c0392b; }
#mining-cards .mc-preset { font-size: 9px; padding: 3px 9px; }
#mining-cards .mv-head .live { font-size: 9px; color: #5b6675; display: flex; align-items: center; gap: 6px; }
#mining-cards .mv-head .live i {
    width: 6px; height: 6px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}

/* Shared inline badges / coin typography */
#mining-cards .tag,
#mining-cards .mn-pay,
#mining-cards .chip,
#mining-cards .mn-algo {
    display: inline-block;
    padding: 0 5px;
    border-radius: 3px;
    background: #eef4ff;
    border: 1px solid #d7e5ff;
    color: #2c5aa0;
    font-size: 9px;
    font-weight: 700;
    line-height: 14px;
    vertical-align: 1px;
    margin-right: 4px;
}
#mining-cards .tag.manual,
#mining-cards .mn-pay.manual { background: #fff7e8; border-color: #ffe2b8; color: #a86400; }
#mining-cards .coin-ico,
#mining-cards .mn-ico,
#mining-cards .bf-ico,
#mining-cards .vico {
    flex: 0 0 24px;
    width: 24px;
    height: 24px;
    border-radius: 6px;
}
#mining-cards .coin-name,
#mining-cards .mn-link,
#mining-cards .bf-link,
#mining-cards .vname {
    font-size: 10.5px;
    font-weight: 700;
    color: #26385a;
}

/* ── Keep long data tables contained (50 algos / 100+ coins) ─────────────────── */
#mining-cards #pool_current_results .main-left-inner > div[style*="overflow"],
#mining-cards #mining_results .main-left-inner > div[style*="overflow"],
#mining-cards #found_results .main-left-inner > div[style*="overflow"],
#mining-cards #miners_results .main-left-inner > div[style*="overflow"] {
    max-height: 420px;
    overflow: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
}
#mining-cards #pool_current_results thead th,
#mining-cards #mining_results thead th,
#mining-cards #found_results thead th,
#mining-cards #miners_results thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff !important;
    box-shadow: 0 1px 0 #e8eef6;
}

/* ── Small screens: let card headers wrap and keep tables readable ─────────── */
@media (max-width: 560px) {
    #mining-cards .main-left-title { flex-wrap: wrap; align-items: flex-start; }
    #mining-cards .mn-head-main,
    #mining-cards .bf-head-main { flex: 1 1 100%; }
    #mining-cards .mc-badge,
    #mining-cards .mn-scope,
    #mining-cards .bf-chips { flex: 0 0 auto; margin-left: auto; }
    #mining-cards #pool_current_results .main-left-inner > div { min-width: 0; }
    #maintable1.ps-table { min-width: 620px; }
    #maintable1.ps-table .coin-sub { white-space: normal; overflow-wrap: anywhere; }
    /* Blocks table: keep Type/Status badges from colliding on narrow screens. */
    #mining-cards #found-table { min-width: 620px; }
    #mining-cards #found-table th,
    #mining-cards #found-table td { padding-left: 5px !important; padding-right: 5px !important; }
    #mining-cards #found-table .bf-status,
    #mining-cards #found-table .bf-type { padding-left: 4px; padding-right: 4px; font-size: 8px; }
    #mining-cards #found-table .bf-status em { display: none; }
}
</style>

<script>
var global_algo = <?= json_encode($algo) ?>;
var querystring = global_algo ? '?algo=' + encodeURIComponent(global_algo) : '';
var MINING_AUTO_MAX = 7 * 24 * 60 * 60 * 1000; // keep mining live far past the shared 1h pause
var mining_last_refresh = Date.now();

function select_algo(algo) {
    window.location.href = '<?= $homeUrl ?>site/algo?algo=' + encodeURIComponent(algo) + '&r=<?= $homeUrl ?>site/mining';
}

function page_refresh() {
    mining_last_refresh = Date.now();
    mining_tick();

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

        $('.mining-algo-chip').each(function () {
            var $chip = $(this), sub = $chip.find('[data-algo-sub]');
            var it = d.items[$chip.data('algo')];
            if (!sub.length || !it) return;
            if ($chip.data('algo') === 'all') {
                var total = Object.keys(d.items).length - 1;
                sub.text(total);
                $chip.attr('title', total + ' algorithms');
            } else {
                sub.text(it.port ? it.port : '—');
                $chip.attr('title', it.port ? 'port ' + it.port + ' · ' + it.workers + ' workers' : 'no stratum');
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

function mining_tick() {
    // auto_refresh.js pauses after 1h by default; mining stays live indefinitely.
    if (typeof window.auto_max_time !== 'undefined' && window.auto_max_time !== MINING_AUTO_MAX) {
        window.auto_max_time = MINING_AUTO_MAX;
        var resume = document.getElementById('resume_update_button');
        if (resume) resume.style.display = 'none';
    }
    var age  = Math.floor((Date.now() - mining_last_refresh) / 1000);
    var next = Math.max(0, 60 - age);
    var label = document.getElementById('mining-last-update');
    if (label) label.textContent = age < 2 ? 'just now' : age + 's ago · next ' + next + 's';
}

function algo_filter_apply() {
    var q = ($('#algo-filter').val() || '').toLowerCase().trim();
    var shown = 0;
    $('.mining-algo-chip').each(function () {
        var key = String($(this).data('algo') || '').toLowerCase();
        var label = String($(this).find('b').text() || '').toLowerCase();
        var ok = !q || key.indexOf(q) !== -1 || label.indexOf(q) !== -1;
        $(this).toggle(ok);
        if (ok && $(this).data('algo') !== 'all') shown++;
    });
    $('#algo-count').text(shown + ' algos');
    $('#algo-empty').toggle(shown === 0 && q !== '');
}

function mining_start() {
    $('#algo-filter').on('input search', algo_filter_apply);
    setInterval(mining_tick, 1000);
    mining_tick();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mining_start);
} else {
    mining_start();
}
</script>
