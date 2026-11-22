<?php

/** @var yii\web\View $this */
/** @var string[]     $serverList */

use yii\helpers\Html;

$server     = (string) Yii::$app->getRequest()->getQueryParam('server');
$serverJson = json_encode($server);

?>
<style>
/* Coin Wallets admin — same visual language as /site/mining */
#cw-admin-bar {
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 11px 14px 12px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    color: #334155; font-size: 11px;
}
#cw-bar-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
#cw-bar-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
#cw-search {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 9px; border: 1px solid #dfe5ec; border-radius: 7px;
    background: #fafbfc; color: #8a94a6;
}
#cw-search:focus-within { border-color: #a9c4ea; background: #fff; }
#cw-search input {
    width: 190px; border: 0; outline: 0; background: transparent;
    font: 11px/1.4 inherit; color: #26385a;
}
#cw-search input::placeholder { color: #aab4c4; }
#cw-server {
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
    color: #8a94a6; font-size: 10px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase;
}
#cw-server select {
    border: 1px solid #dfe5ec; background: #fafbfc; color: #26385a;
    border-radius: 7px; padding: 4px 8px; font: 11px/1.4 inherit;
    max-width: 220px; outline: none;
}
#cw-server select:focus { border-color: #a9c4ea; background: #fff; }
#cw-count { font-size: 10px; color: #8a94a6; font-variant-numeric: tabular-nums; }
#cw-live {
    margin-left: auto; white-space: nowrap; font-size: 11px; color: #5b6675;
    display: flex; align-items: center; gap: 6px;
}
#cw-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#cw-live span { color: #98a3b5; font-size: 10px; font-variant-numeric: tabular-nums; }
#cw-bar-links {
    display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    border-top: 1px solid #eef2f7; margin-top: 10px; padding-top: 9px;
}
#cw-bar-links a {
    display: inline-block; border: 1px solid #dfe5ec; background: #fafbfc;
    color: #64748b; border-radius: 999px; padding: 3px 10px;
    font-size: 10px; font-weight: 700; line-height: 1.6; text-decoration: none;
}
#cw-bar-links a:hover { border-color: #b9d4ff; color: #2c5aa0; background: #f7faff; }
#cw-bar-links a.cw-create {
    background: #2c5aa0; border-color: #2c5aa0; color: #fff;
}
#cw-bar-links a.cw-create:hover { background: #1f467f; border-color: #1f467f; color: #fff; }

/* Mining-style loading skeleton (replaced by the first AJAX response) */
#main_results .cw-loading { margin: 0; }
#main_results .cw-loading .main-left-title { min-height: 40px; padding: 9px 13px; }
#main_results .cw-sk-title { font-size: 13px; font-weight: 800; color: #26385a; }
#main_results .cw-sk-sub { font-size: 10px; color: #8a94a6; margin-left: 8px; }
#main_results .cw-sk-lines { padding: 14px; display: grid; gap: 9px; }
#main_results .cw-sk-line {
    height: 20px; border-radius: 4px;
    background: linear-gradient(90deg,#f1f5f9 25%,#f8fafc 50%,#f1f5f9 75%);
    background-size: 200% 100%; animation: cw-shimmer 1.4s infinite linear;
}
#main_results .cw-sk-line:nth-child(3n) { width: 72%; }
@keyframes cw-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

@media (max-width: 720px) {
    #cw-bar-head { align-items: flex-start; }
    #cw-live { margin-left: 0; }
    #cw-search input { width: 140px; }
}
</style>

<div id="cw-admin-bar">
    <div id="cw-bar-head">
        <span id="cw-bar-label">Coin Wallets</span>
        <label id="cw-search" for="server_select_search">
            <span aria-hidden="true">⌕</span>
            <input class="search" id="server_select_search" type="search" data-column="all"
                   placeholder="Search coins, nodes…" autocomplete="off" aria-label="Search coin wallets">
        </label>
        <label id="cw-server" for="server_select">
            <span>Server</span>
            <select id="server_select">
                <option value="">all</option>
<?php foreach ($serverList as $host): ?>
                <option value="<?= Html::encode($host) ?>"<?= $host === $server ? ' selected' : '' ?>><?= Html::encode($host) ?></option>
<?php endforeach ?>
            </select>
        </label>
        <span id="cw-count"><?= count($serverList) ?> <?= count($serverList) === 1 ? 'node' : 'nodes' ?></span>
        <span id="cw-live"><i></i> live · 30s <span id="cw-last-update">just now</span></span>
    </div>
    <div id="cw-bar-links">
        <?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?>
        <a href="/admin/emptymarkets">Empty Markets</a>
        <a class="cw-create" href="/admin/coinwallet_create">+ Create coin</a>
    </div>
</div>

<div id="main_results">
    <div class="main-left-box cw-loading">
        <div class="main-left-title">
            <span class="cw-sk-title">Coin Wallets</span>
            <span class="cw-sk-sub">loading node status · balances · markets…</span>
        </div>
        <div class="cw-sk-lines">
            <div class="cw-sk-line"></div><div class="cw-sk-line"></div>
            <div class="cw-sk-line"></div><div class="cw-sk-line"></div>
            <div class="cw-sk-line"></div>
        </div>
    </div>
</div>

<?php
Yii::$app->view->registerJs("
$('#server_select').change(function(event)
{
	var server = $('#server_select').val();
	clearTimeout(main_timeout);
	window.location.href = '/admin/coinwallets?server='+encodeURIComponent(server);
});

var main_delay=30000;
var main_timeout;
var lastSearch = false;
var cwLastUpdate = 0;

function cwTick()
{
	if (!cwLastUpdate) return;
	var age = Math.floor((Date.now() - cwLastUpdate) / 1000);
	$('#cw-last-update').text(age + 's ago · next ' + Math.max(0, main_delay/1000 - age) + 's');
}

function main_ready(data)
{
	$('#main_results').html(data);
	$('#server_select').val({$serverJson});

	if (lastSearch) {
		$('input.search').val(lastSearch);
		$('#maintable').trigger('search');
	}

	cwLastUpdate = Date.now();
	cwTick();
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_refresh()
{
	var url = '/admin/coinwallet_results?server=' + encodeURIComponent({$serverJson});

	clearTimeout(main_timeout);
	lastSearch = $('input.search').val() || false;
	$.get(url, '', main_ready);
}

$(function()
{
	setInterval(cwTick, 1000);
	main_refresh();
});
");
