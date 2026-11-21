<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Dashboard';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style admin console
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Admin toolbar ─────────────────────────────────────────────────────────── */
.ad-toolbar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 9px 12px; margin: 0 0 16px;
    border: 1px solid #d8e2ef; border-radius: 7px; background: #fff;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
.ad-toolbar-brand {
    display: flex; align-items: center; gap: 9px;
    padding-right: 12px; border-right: 1px solid #e8eef6;
}
.ad-live-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
.ad-brand-text { display: flex; flex-direction: column; line-height: 1.25; }
.ad-brand-text b { color: #26385a; font-size: 13px; }
.ad-brand-text small { color: #8a94a6; font-size: 9px; }
.ad-toolbar .mc-badge {
    display: inline-block; padding: 2px 7px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}
.ad-toolbar-links { display: flex; flex-wrap: wrap; gap: 6px; flex: 1 1 auto; }
.ad-toolbar-links a, .ad-action {
    display: inline-block; padding: 3px 8px; border-radius: 999px;
    border: 1px solid #dfe7f2; background: #f8fafc; color: #4a586c;
    font-size: 10px; line-height: 15px; text-decoration: none; white-space: nowrap;
}
.ad-toolbar-links a:hover, .ad-action:hover {
    background: #eef4ff; border-color: #c9d9f5; color: #2c5aa0; text-decoration: none;
}
.ad-toolbar-actions { display: flex; gap: 6px; margin-left: auto; }
.ad-action-primary { background: #eef4ff; border-color: #d7e5ff; color: #2c5aa0; font-weight: 700; }
.ad-action-green { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; font-weight: 700; }

/* ── Up / Down live dots (Algo Status) ────────────────────────────────────── */
.ad-up-icon {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    vertical-align: middle;
}
.ad-up-yes { background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.15); }
.ad-up-no  { background: #d9534f; box-shadow: 0 0 0 3px rgba(217,83,79,.15); }
.ad-up-time { margin-left: 7px; color: #8a94a6; font-size: 9px; vertical-align: middle; }

/* ── Card chrome (same as /site/mining) ───────────────────────────────────── */
#main_results .ad-grid {
    display: grid; grid-template-columns: minmax(0, 1fr) 320px;
    gap: 16px; align-items: start;
}
#main_results .ad-grid > .ad-span { grid-column: 1 / -1; }
#main_results .ad-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
#main_results .main-left-box {
    border: 1px solid #d8e2ef; border-radius: 7px; background: #fff;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; margin: 0;
}
#main_results .main-left-title {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px; background: #f8fafc;
    border-bottom: 1px solid #e8eef6; color: #26385a;
    font-size: 13px; font-weight: 800; line-height: 1.3;
}
#main_results .mn-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#main_results .mn-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#main_results .mc-badge {
    display: inline-block; padding: 2px 7px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
    text-decoration: none;
}
#main_results .ad-badge-warn { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
#main_results .main-left-inner { padding: 0; }
#main_results .ad-card-inner { padding: 6px 10px 8px; overflow-x: auto; }
#main_results .ad-side-body { padding: 10px 12px; }
#main_results .ad-graph { padding: 4px 6px; overflow: hidden; }

/* ── KPI tiles ─────────────────────────────────────────────────────────────── */
#main_results .ad-kpis {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px; padding: 12px 14px 14px;
}
#main_results .ad-grid > .ad-span > .main-left-title { background: #f4f8ff; }
#main_results .ad-kpi {
    border: 1px solid #e8eef6; border-radius: 6px; background: #fcfdff;
    padding: 8px 10px; min-width: 0;
}
#main_results .ad-kpi .k-label {
    display: block; font-size: 9px; font-weight: 800; letter-spacing: .06em;
    text-transform: uppercase; color: #98a3b5;
}
#main_results .ad-kpi .k-value {
    display: block; margin-top: 3px; font-family: monospace; font-size: 15px;
    font-weight: 700; color: #26385a; overflow-wrap: anywhere;
}
#main_results .ad-kpi .k-sub { display: block; margin-top: 2px; font-size: 9px; color: #8a94a6; }
#main_results .ad-kpi.accent .k-value { color: #2c5aa0; }
#main_results .ad-kpi.warn .k-value { color: #c2410c; }

/* ── BTC summary side card ─────────────────────────────────────────────────── */
#main_results .ad-wallet-line {
    display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;
    font-size: 11px; color: #4a586c;
}
#main_results .ad-wallet-line span { color: #8a94a6; }
#main_results .ad-wallet-line b { font-family: monospace; color: #26385a; font-size: 13px; }
#main_results .ad-wallet-line a { color: #2c5aa0; font-size: 9px; text-decoration: none; }
#main_results .ad-wallet-sub { margin: 3px 0 9px; font-size: 9px; color: #8a94a6; }
#main_results .ad-wallet-sub b { color: #4a586c; font-family: monospace; }
#main_results .ad-kv {
    display: grid; grid-template-columns: 1fr auto; gap: 3px 10px;
    padding-top: 8px; border-top: 1px solid #eef2f7; font-size: 10px;
}
#main_results .ad-kv span { color: #8a94a6; }
#main_results .ad-kv b { color: #4a586c; font-family: monospace; font-weight: 600; }

/* ── Tables inside cards ───────────────────────────────────────────────────── */
#main_results table.dataGrid, #main_results table.dataGrid2 {
    width: 100%; border-collapse: collapse; font-size: 11px;
}
#main_results table.dataGrid th, #main_results table.dataGrid2 th {
    padding: 6px 8px; background: #fff; border-bottom: 1px solid #e8eef6;
    color: #8a94a6; font-size: 9px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; text-align: right;
}
#main_results table.dataGrid th:first-child, #main_results table.dataGrid2 th:first-child,
#main_results table.dataGrid th:nth-child(2), #main_results table.dataGrid2 th:nth-child(2) { text-align: left; }
#main_results table.dataGrid td, #main_results table.dataGrid2 td {
    padding: 5px 8px; border-bottom: 1px solid #eef2f7; vertical-align: middle;
}
#main_results table.dataGrid tr:hover td, #main_results table.dataGrid2 tr:hover td { background: #f7faff; }
#main_results .ad-graph > div { width: 100%; overflow: hidden; }
#main_results #graph_results_negative { height: 160px; }
#main_results #graph_results_assets { height: 200px; }

@media (max-width: 1100px) {
    #main_results .ad-grid { grid-template-columns: 1fr; }
}
</style>

<div class="ad-toolbar">
    <div class="ad-toolbar-brand">
        <span class="ad-live-dot"></span>
        <span class="ad-brand-text"><b>Admin Console</b><small>pool operations · live</small></span>
        <span class="mc-badge">ONLINE</span>
    </div>
    <div class="ad-toolbar-links"><?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?></div>
    <div class="ad-toolbar-actions">
        <a href="/admin/memcached" class="ad-action">Memcache</a>
        <a href="/admin/connections" class="ad-action">Connections</a>
        <?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
        <a href="/renting/admin" class="ad-action">Rental</a>
        <?php endif ?>
        <a href="/admin/coinwallet_create" class="ad-action ad-action-green">+ Create Coin</a>
        <a href="/admin/updateprice" class="ad-action ad-action-primary">Update Price</a>
    </div>
</div>

<div id="main_results"></div>

<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/memcached" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-database me-1"></i>Memcache
        </a>
        <a href="/admin/connections" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-plug me-1"></i>Connections
        </a>
        <?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
        <a href="/renting/admin" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-share me-1"></i>Rental
        </a>
        <?php endif ?>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/coinwallet_create" class="btn btn-sm btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i>Create Coin
        </a>
        <a href="/admin/updateprice" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-repeat me-1"></i>Update Price
        </a>
    </div>
</div>

<div id="main_results"></div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
    <div class="flex gap-2 flex-wrap">
        <a href="/admin/memcached"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="database" class="w-3.5 h-3.5"></i>Memcache
        </a>
        <a href="/admin/connections"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="plug" class="w-3.5 h-3.5"></i>Connections
        </a>
        <?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
        <a href="/renting/admin"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="share-2" class="w-3.5 h-3.5"></i>Rental
        </a>
        <?php endif ?>
    </div>
    <div class="flex gap-2">
        <a href="/admin/coinwallet_create"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-green-300 dark:border-green-700
                  bg-white dark:bg-gray-700 text-green-600 dark:text-green-400
                  hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>Create Coin
        </a>
        <a href="/admin/updateprice"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-indigo-300 dark:border-indigo-700
                  bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400
                  hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>Update Price
        </a>
    </div>
</div>

<div id="main_results"></div>

<?php endif ?>

<script>
$(function () { main_refresh(); });

var main_delay   = 30000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    main_timeout = setTimeout(main_refresh, main_delay);
    main_refresh_assets();
    main_refresh_negative();
    <?php if ($isTailwind): ?>
    if (typeof lucide !== 'undefined') lucide.createIcons();
    <?php endif ?>
}
function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}
function main_refresh() {
    clearTimeout(main_timeout);
    $.get('/admin/common_results', '', main_ready).fail(main_error);
}

function main_refresh_assets() {
    $.get('/admin/graph_assets_results', '', function(data) {
        var $el = $('#graph_results_assets');
        if (!$el.length) return;
        try {
            var t = JSON.parse(data);
            var isEmpty = !t || t.every(function(s) { return !s || s.length === 0; });
            if (isEmpty) {
                $el.html('<div style="color:#aaa;text-align:center;padding-top:60px;">No stats data yet — StatsService needs to run first</div>');
                return;
            }
            yiimpChart('graph_results_assets', t, { type: 'bar', stack: true, labels: ['Margin', 'Balances', 'On sell', 'Wallets'] });
        } catch(e) {
            $el.html('<div style="color:#aaa;text-align:center;padding-top:60px;">Graph error: ' + e.message + '</div>');
        }
    });
}
function main_refresh_negative() {
    $.get('/admin/graph_negative_results', '', function(data) {
        var $el = $('#graph_results_negative');
        if (!$el.length) return;
        try {
            var t = JSON.parse(data);
            var isEmpty = !t || t.every(function(s) { return !s || s.length === 0; });
            if (isEmpty) {
                $el.html('<div style="color:#aaa;text-align:center;padding-top:50px;">No stats data yet</div>');
                return;
            }
            yiimpChart('graph_results_negative', t, { type: 'bar', stack: true, labels: ['Waiting', 'Immature'] });
        } catch(e) {
            $el.html('<div style="color:#aaa;text-align:center;padding-top:50px;">Graph error: ' + e.message + '</div>');
        }
    });
}
</script>
