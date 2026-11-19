<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Dashboard';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
&nbsp;-&nbsp;
<a href='/admin/memcached'>Memcache</a>&nbsp;
<a href='/admin/connections'>Connections</a>&nbsp;
<?php if (defined('YIIMP_RENTAL') && YIIMP_RENTAL): ?>
<a href='/renting/admin'>Rental</a>&nbsp;
<?php endif ?>

<style>
#graph_results_assets, #graph_results_negative { overflow: hidden; width: 100%; }
</style>

<div id="main_results"></div>

<br>
<a href='/admin/coinwallet_create'><b>CREATE COIN</b></a>&nbsp;&nbsp;
<a href='/admin/updateprice'><b>UPDATE PRICE</b></a>


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
