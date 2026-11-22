<?php

/** @var yii\web\View      $this */
/** @var app\models\Coins  $coin */
/** @var array|false        $info */
/** @var array[]            $list */

use yii\helpers\Html;

$this->title = 'Peers — ' . $coin->symbol;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
// Geth mining nodes disable the admin namespace: addnode/removenode do not
// apply, and getpeerinfo returns a peer-count summary row instead of addresses.
$isGeth     = $coin->rpcencoding === 'GETH';
// Geth has no per-peer P2P byte counters; admin peers shows the bytes of the
// local RPC probe instead. Ping is that probe's round-trip time.
$rxTxTitle  = $isGeth
    ? 'Geth has no per-peer P2P counters; values are bytes of the local RPC probe'
    : 'Bytes received / sent';
$pingTitle  = $isGeth
    ? 'RPC request round-trip time, or TCP handshake time to the p2p port'
    : 'Peer ping';

$localHeight   = $info['blocks'] ?? 0;
$addnodeLines  = [];
$latestVersion = '';

// Pre-compute per-peer values
$peers = [];
$syncOk = $syncBad = 0;
$pingSum = 0.0; $pingCount = 0;
foreach ($list as $peer) {
    $node    = $peer['addr'] ?? '';
    $prefix  = $coin->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=';
    if (!$isGeth) {
        $addnodeLines[] = $prefix . $node;
    }

    $peerVer = trim($peer['subver'] ?? '', '/');
    if ($peerVer > $latestVersion) $latestVersion = $peerVer;

    $height      = $peer['currentheight'] ?? ($peer['synced_blocks'] ?? 0);
    // A Geth peer with no public RPC reports 0 = height unknown, not stuck at genesis.
    $outOfSync   = $height > 0 && abs($height - $localHeight) > 5;
    if ($outOfSync) $syncBad++;
    elseif ($height > 0) $syncOk++;
    $pingText = trim((string) ($peer['pingtime'] ?? ''));
    if ($pingText !== '' && preg_match('/^\d+(?:\.\d+)?/', $pingText, $pm)) {
        $pingSum += (float) $pm[0];
        $pingCount++;
    }
    $connTime    = $peer['conntime']       ?? time();
    $startHeight = $peer['startingheight'] ?? '';
    $lastRecv    = $peer['lastrecv']       ?? time();
    $lastSend    = $peer['lastsend']       ?? time();
    $bytesRecv   = round(($peer['bytesrecv'] ?? 0) / 1024, 1);
    $bytesSent   = round(($peer['bytessent'] ?? 0) / 1024, 1);
    $removeUrl   = Yii::$app->urlManager->createUrl([
        'admin/coinpeerremove', 'id' => $coin->id, 'node' => $node,
    ]);

    $peers[] = compact(
        'node', 'peerVer', 'height', 'outOfSync', 'connTime', 'startHeight',
        'lastRecv', 'lastSend', 'bytesRecv', 'bytesSent', 'removeUrl', 'peer'
    );
}

$localVersion  = $conv->formatWalletVersion($coin);
$addNodeAction = Yii::$app->urlManager->createUrl(['admin/coinpeeradd', 'id' => $coin->id]);
$peerCount     = count($peers);
$avgPing       = $pingCount ? round($pingSum / $pingCount, 1) . ' ms' : '—';
$connections   = (is_array($info) || is_object($info))
    ? (int) (($info['connections'] ?? $peerCount)) : $peerCount;
$walletLinks   = str_replace(
    ['<br/>', '<br>', '||'],
    '',
    Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers')
);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style peer console (same chrome as /site/mining)
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
#coinpeers_results { display:flex; flex-direction:column; gap:16px; }
#coinpeers_results .ad-toolbar {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:9px 12px; margin:0; border:1px solid #d8e2ef; border-radius:7px;
    background:#fff; box-shadow:0 1px 2px rgba(20,40,80,.04);
}
#coinpeers_results .ad-toolbar-brand {
    display:flex; align-items:center; gap:9px; padding-right:12px;
    border-right:1px solid #e8eef6;
}
#coinpeers_results .ad-live-dot {
    width:8px; height:8px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#coinpeers_results .ad-brand-text { display:flex; flex-direction:column; line-height:1.25; }
#coinpeers_results .ad-brand-text b { color:#26385a; font-size:13px; }
#coinpeers_results .ad-brand-text small { color:#8a94a6; font-size:9px; }
#coinpeers_results .mc-badge, #coinpeers_results .ad-toolbar .mc-badge {
    display:inline-block; padding:2px 7px; border-radius:999px; background:#eef4ff;
    border:1px solid #d7e5ff; color:#2c5aa0; font-size:9px; font-weight:700;
    line-height:14px; white-space:nowrap; text-decoration:none;
}
#coinpeers_results .ad-toolbar-links { display:flex; flex-wrap:wrap; gap:6px; flex:1 1 auto; }
#coinpeers_results .ad-toolbar-links a, #coinpeers_results .cp-action {
    display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #dfe7f2;
    background:#f8fafc; color:#4a586c; font-size:10px; line-height:15px;
    text-decoration:none; white-space:nowrap;
}
#coinpeers_results .ad-toolbar-links a:hover, #coinpeers_results .cp-action:hover {
    background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none;
}
#coinpeers_results .cp-add-form { display:flex; gap:6px; margin-left:auto; }
#coinpeers_results .cp-add-form input {
    width:170px; padding:4px 9px; border:1px solid #dfe5ec; border-radius:7px;
    background:#fafbfc; font:11px/1.4 inherit; color:#26385a; outline:0;
}
#coinpeers_results .cp-add-form input:focus { border-color:#a9c4ea; background:#fff; }
#coinpeers_results .cp-add-form button {
    padding:4px 10px; border-radius:7px; border:1px solid #d7e5ff; cursor:pointer;
    background:#eef4ff; color:#2c5aa0; font-size:10px; font-weight:700;
}
#coinpeers_results .cp-add-form button:hover { background:#e3eeff; }
#coinpeers_results .cp-admin-nav {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    padding:5px 12px; margin-top:-8px; font-size:10px; color:#8a94a6;
}
#coinpeers_results .cp-admin-nav > span {
    font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#a7b0c0;
}
#coinpeers_results .cp-admin-nav a { color:#5b6675; text-decoration:none; }
#coinpeers_results .cp-admin-nav a:hover { color:#2c5aa0; text-decoration:underline; }
#coinpeers_results .cp-flash {
    padding:6px 12px; border-radius:6px; font-size:11px;
    border:1px solid #bbf7d0; background:#f0fdf4; color:#15803d;
}
#coinpeers_results .cp-flash-error { border-color:#fecaca; background:#fef2f2; color:#b91c1c; }

#coinpeers_results .cp-summary {
    background:#f8fbff; border:1px solid #d8e2ef; border-left:3px solid #2c5aa0;
    border-radius:6px; padding:8px 14px; display:flex; gap:22px; flex-wrap:wrap;
    font-size:12px; color:#4a586c;
}
#coinpeers_results .cp-summary b { color:#223a5e; }
#coinpeers_results .cp-summary .sep { color:#ccd4e0; }

#coinpeers_results .main-left-box {
    border:1px solid #d8e2ef; border-radius:7px; background:#fff;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden; margin:0;
}
#coinpeers_results .main-left-title {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px; background:#f8fafc;
    border-bottom:1px solid #e8eef6; color:#26385a; font-size:13px; font-weight:800;
}
#coinpeers_results .mn-head-main { display:flex; align-items:baseline; gap:9px; flex-wrap:wrap; min-width:0; }
#coinpeers_results .mn-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#coinpeers_results .main-left-inner { padding:0; }
#coinpeers_results .cp-card-inner { padding:0; overflow-x:auto; }

#coinpeers_results .cp-kpis {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:12px; padding:12px 14px;
}
#coinpeers_results .cp-kpi {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff; padding:8px 10px; min-width:0;
}
#coinpeers_results .cp-kpi .k-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#coinpeers_results .cp-kpi .k-value {
    display:block; margin-top:3px; font-family:monospace; font-size:15px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#coinpeers_results .cp-kpi .k-sub { display:block; margin-top:2px; font-size:9px; color:#8a94a6; }
#coinpeers_results .cp-kpi.accent .k-value { color:#2c5aa0; }
#coinpeers_results .cp-kpi.good .k-value { color:#2fae5a; }
#coinpeers_results .cp-kpi.warn .k-value { color:#c2410c; }

#coinpeers_results .cp-filter {
    display:flex; align-items:center; gap:10px; padding:8px 12px;
    border-top:1px solid #eef2f7; border-bottom:1px solid #e8eef6; background:#fff;
}
#coinpeers_results .cp-filter label {
    font-size:10px; font-weight:700; letter-spacing:.12em;
    color:#8a94a6; text-transform:uppercase;
}
#coinpeers_results .cp-filter input {
    width:230px; padding:4px 9px; border:1px solid #dfe5ec; border-radius:7px;
    background:#fafbfc; font:11px/1.4 inherit; color:#26385a; outline:0;
}
#coinpeers_results .cp-filter input:focus { border-color:#a9c4ea; background:#fff; }
#coinpeers_results .cp-count { font-size:10px; color:#8a94a6; font-variant-numeric:tabular-nums; }
#coinpeers_results .cp-live {
    margin-left:auto; font-size:11px; color:#5b6675; display:flex; align-items:center; gap:6px;
}
#coinpeers_results .cp-live i {
    width:7px; height:7px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}

#coinpeers_results table.dataGrid { width:100%; border-collapse:collapse; font-size:11px; }
#coinpeers_results table.dataGrid th {
    padding:6px 8px; background:#f8fafc; border-bottom:1px solid #e8eef6;
    color:#8a94a6; font-size:9px; font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; text-align:right; white-space:nowrap;
}
#coinpeers_results table.dataGrid th:first-child,
#coinpeers_results table.dataGrid th:nth-child(2),
#coinpeers_results table.dataGrid th:nth-child(5) { text-align:left; }
#coinpeers_results table.dataGrid td {
    padding:5px 8px; border-bottom:1px solid #eef2f7; vertical-align:middle;
}
#coinpeers_results table.dataGrid tr:hover td { background:#f7faff; }
#coinpeers_results table.dataGrid td.mono { font-family:monospace; color:#33405a; }
#coinpeers_results table.dataGrid td.num {
    text-align:right; font-family:monospace; font-variant-numeric:tabular-nums; color:#4a586c;
}
#coinpeers_results table.dataGrid td.muted { color:#8a94a6; }
#coinpeers_results .cp-red { color:#c2410c !important; font-weight:700; }
#coinpeers_results table.dataGrid a.cp-remove { color:#c2410c; text-decoration:none; font-size:10px; }
#coinpeers_results table.dataGrid a.cp-remove:hover { text-decoration:underline; }
#coinpeers_results .cp-empty { padding:18px; text-align:center; color:#98a3b5; font-size:11px; }
#coinpeers_results .cp-foot {
    padding:8px 12px; border-top:1px solid #e8eef6; background:#f8fafc;
    display:flex; gap:20px; flex-wrap:wrap; font-size:10px; color:#8a94a6;
}
#coinpeers_results .cp-foot b { color:#4a586c; font-family:monospace; }
#coinpeers_results pre.cp-config {
    margin:0; padding:12px; font-family:monospace; font-size:11px; color:#33405a;
    background:#fcfdff; overflow-x:auto; white-space:pre;
}
@media (max-width:900px) {
    #coinpeers_results .cp-add-form { margin-left:0; }
    #coinpeers_results .ad-toolbar-brand { border-right:0; }
}
</style>

<div id="coinpeers_results">

    <div class="ad-toolbar">
        <div class="ad-toolbar-brand">
            <span class="ad-live-dot"></span>
            <span class="ad-brand-text"><b>Coin Peers</b><small>node diagnostics · live</small></span>
            <span class="mc-badge"><?= Html::encode(strtoupper($coin->symbol)) ?></span>
        </div>
        <div class="ad-toolbar-links"><?= $walletLinks ?></div>
        <?php if (!$isGeth): ?>
        <form action="<?= Html::encode($addNodeAction) ?>" method="post" class="cp-add-form">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <input type="text" name="node" placeholder="addr[:port]" autocomplete="off" aria-label="Node address">
            <button type="submit">+ Add node</button>
        </form>
        <?php endif ?>
    </div>

    <div class="cp-admin-nav">
        <span>Admin</span>
        <?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?>
    </div>

    <?php foreach (['error', 'success'] as $t): ?>
    <?php if (Yii::$app->session->hasFlash($t)): ?>
    <div class="cp-flash <?= $t === 'error' ? 'cp-flash-error' : '' ?>">
        <?= Html::encode(Yii::$app->session->getFlash($t)) ?>
    </div>
    <?php endif ?>
    <?php endforeach ?>

    <div class="cp-summary">
        <span>Coin <b><?= Html::encode($coin->symbol . ' — ' . $coin->name) ?></b></span>
        <span class="sep">|</span>
        <span>Algo <b><?= Html::encode($coin->algo) ?></b></span>
        <span class="sep">|</span>
        <span>RPC <b><?= Html::encode($coin->rpcencoding) ?></b></span>
        <span class="sep">|</span>
        <span>Connections <b><?= $connections ?></b></span>
        <span class="sep">|</span>
        <span>Local height <b><?= (int) $localHeight ?></b></span>
    </div>

    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span><?= Html::encode($coin->name) ?> Peers</span>
                <span class="mn-sub">live from node RPC</span>
            </span>
            <span class="mc-badge">LIVE · <?= $peerCount ?> PEERS</span>
        </div>

        <div class="cp-kpis">
            <div class="cp-kpi accent">
                <span class="k-label">Peers</span>
                <span class="k-value"><?= $peerCount ?></span>
                <span class="k-sub">connected nodes</span>
            </div>
            <div class="cp-kpi good">
                <span class="k-label">In sync</span>
                <span class="k-value"><?= $syncOk ?></span>
                <span class="k-sub">height within 5 blocks</span>
            </div>
            <div class="cp-kpi <?= $syncBad ? 'warn' : '' ?>">
                <span class="k-label">Out of sync</span>
                <span class="k-value"><?= $syncBad ?></span>
                <span class="k-sub">height mismatch</span>
            </div>
            <div class="cp-kpi">
                <span class="k-label">Avg ping</span>
                <span class="k-value"><?= Html::encode($avgPing) ?></span>
                <span class="k-sub">RPC / p2p round-trip</span>
            </div>
        </div>

        <div class="cp-filter">
            <label for="cp-filter-input">Filter</label>
            <input type="search" id="cp-filter-input" placeholder="address, version, service…" autocomplete="off">
            <span class="cp-count" id="cp-count">showing <?= $peerCount ?> / <?= $peerCount ?></span>
            <span class="cp-live"><i></i> refreshed on page load</span>
        </div>

        <div class="main-left-inner cp-card-inner">
            <table id="maintable" class="dataGrid">
            <thead><tr>
                <th title="Peer address">Address</th>
                <th>Version</th>
                <th>Height</th>
                <th title="<?= Html::encode($pingTitle) ?>">Ping</th>
                <th>Services</th>
                <th>Since</th>
                <th>Last</th>
                <th title="<?= Html::encode($rxTxTitle) ?>">Rx / Tx (kB)</th>
                <th data-sorter="false"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($peers as $p): ?>
            <tr>
                <td class="mono"><?= Html::encode($p['node']) ?></td>
                <td class="mono"><?= Html::encode($p['peerVer']) ?></td>
                <td class="num <?= $p['outOfSync'] ? 'cp-red' : '' ?>"><?= (int) $p['height'] ?></td>
                <td class="num"><?= Html::encode($p['peer']['pingtime'] ?? '') ?></td>
                <td class="mono muted"><?= Html::encode($p['peer']['services'] ?? '') ?></td>
                <td class="muted"><?= $conv->datetoa2($p['connTime']) ?> (<?= (int) $p['startHeight'] ?>)</td>
                <td class="muted"><?= $conv->datetoa2(max($p['lastRecv'], $p['lastSend'])) ?></td>
                <td class="num">
                    <?= ($p['bytesRecv'] + $p['bytesSent']) ? Html::encode("{$p['bytesRecv']} / {$p['bytesSent']}") : '' ?>
                </td>
                <td>
                    <?php if (!$isGeth) echo Html::a('remove', $p['removeUrl'], [
                        'class' => 'cp-remove', 'title' => 'Disconnect from node',
                    ]); ?>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
            </table>
            <?php if (empty($peers)): ?>
            <div class="cp-empty">No peers available.</div>
            <?php endif ?>
        </div>

        <div class="cp-foot">
            <span><b>Local version:</b> <?= Html::encode($localVersion) ?></span>
            <?php if ($latestVersion): ?>
            <span><b>Latest peer:</b> <?= Html::encode($latestVersion) ?></span>
            <?php endif ?>
            <span><b>Encoding:</b> <?= Html::encode($coin->rpcencoding) ?></span>
        </div>
    </div>

    <?php if ($addnodeLines): ?>
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>addnode config</span>
                <span class="mn-sub">copy into your node configuration</span>
            </span>
            <span class="mc-badge"><?= count($addnodeLines) ?> LINES</span>
        </div>
        <div class="main-left-inner">
            <pre class="cp-config"><?= Html::encode(implode("\n", $addnodeLines)) ?></pre>
        </div>
    </div>
    <?php endif ?>

</div>

<?php
$this->registerJs("
    (function () {
        if (typeof \$.fn.tablesorter !== 'undefined') {
            \$('#coinpeers_results #maintable').tablesorter({
                headers: { 2:{sorter:'numeric'}, 3:{sorter:'numeric'}, 7:{sorter:'numeric'}, 8:{sorter:false} },
                widgets: ['zebra','Storage','saveSort'],
                widgetOptions: { saveSort: true }
            });
        }
        var input = document.getElementById('cp-filter-input');
        var table = document.getElementById('maintable');
        if (!input || !table) return;
        var rows = Array.prototype.slice.call(table.tBodies[0].rows);
        var count = document.getElementById('cp-count');
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase();
            var shown = 0;
            rows.forEach(function (r) {
                var hit = r.textContent.toLowerCase().indexOf(q) !== -1;
                r.style.display = hit ? '' : 'none';
                if (hit) shown++;
            });
            if (count) count.textContent = 'showing ' + shown + ' / ' + rows.length;
        });
    })();
");
?>

<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers') ?></div>
    <?php if (!$isGeth): ?>
    <form action="<?= Html::encode($addNodeAction) ?>" method="post"
          class="d-flex gap-2 align-items-center shrink-0">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="text" name="node" class="form-control form-control-sm"
               placeholder="addr[:port]" autocomplete="off" style="width:180px;">
        <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle me-1"></i>Add node
        </button>
    </form>
    <?php endif ?>
</div>

<?= \app\widgets\Alert::widget() ?>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-diagram-3 text-secondary"></i>
        <strong class="small"><?= Html::encode($coin->name) ?> Peers</strong>
        <span class="badge bg-secondary ms-1"><?= count($peers) ?></span>
    </div>
    <div class="card-body p-0"><div class="overflow-auto">
    <table id="maintable" class="table table-sm table-bordered mb-0">
    <thead class="table-light"><tr>
        <th class="small">Address</th>
        <th class="small">Version</th>
        <th class="small text-end">Height</th>
        <th class="small text-end" title="<?= Html::encode($pingTitle) ?>">Ping</th>
        <th class="small">Services</th>
        <th class="small">Since</th>
        <th class="small">Last</th>
        <th class="small text-end" title="<?= Html::encode($rxTxTitle) ?>">Rx / Tx (kB)</th>
        <th data-sorter="false"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($peers as $p): ?>
    <tr>
        <td class="small font-monospace"><?= Html::encode($p['node']) ?></td>
        <td class="small"><?= Html::encode($p['peerVer']) ?></td>
        <td class="small text-end tabular-nums <?= $p['outOfSync'] ? 'text-danger fw-bold' : '' ?>">
            <?= (int) $p['height'] ?>
        </td>
        <td class="small text-end font-monospace"><?= Html::encode($p['peer']['pingtime'] ?? '') ?></td>
        <td class="small font-monospace text-muted"><?= Html::encode($p['peer']['services'] ?? '') ?></td>
        <td class="small text-muted text-nowrap">
            <?= $conv->datetoa2($p['connTime']) ?>
            <?php if ($p['startHeight'] !== ''): ?>
            <span class="text-muted">(<?= (int) $p['startHeight'] ?>)</span>
            <?php endif ?>
        </td>
        <td class="small text-muted text-nowrap"><?= $conv->datetoa2(max($p['lastRecv'], $p['lastSend'])) ?></td>
        <td class="small text-end font-monospace">
            <?= ($p['bytesRecv'] + $p['bytesSent']) ? Html::encode("{$p['bytesRecv']} / {$p['bytesSent']}") : '' ?>
        </td>
        <td class="text-center">
            <?php if (!$isGeth) echo Html::a('remove', $p['removeUrl'], [
                'class' => 'text-danger small',
                'title' => 'Disconnect from node',
            ]); ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div></div>
    <div class="card-footer py-2 small text-muted d-flex gap-4">
        <span><strong>Local:</strong> <?= Html::encode($localVersion) ?></span>
        <?php if ($latestVersion): ?>
        <span><strong>Latest peer:</strong> <?= Html::encode($latestVersion) ?></span>
        <?php endif ?>
    </div>
</div>

<?php if ($addnodeLines): ?>
<div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-file-text text-secondary"></i>
        <strong class="small">addnode config</strong>
        <span class="badge bg-secondary ms-1"><?= count($addnodeLines) ?></span>
    </div>
    <div class="card-body p-2">
        <pre class="mb-0 small"><?= Html::encode(implode("\n", $addnodeLines)) ?></pre>
    </div>
</div>
<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        headers: { 2:{sorter:'numeric'}, 3:{sorter:'numeric'}, 7:{sorter:'numeric'}, 8:{sorter:false} }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers') ?></div>
    <?php if (!$isGeth): ?>
    <form action="<?= Html::encode($addNodeAction) ?>" method="post"
          class="flex gap-2 items-center shrink-0">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="text" name="node"
               class="text-xs font-mono rounded-lg border border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                      focus:outline-none focus:ring-2 focus:ring-indigo-500 w-44"
               placeholder="addr[:port]" autocomplete="off">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                       border border-indigo-300 dark:border-indigo-700
                       bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400
                       hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>Add node
        </button>
    </form>
    <?php endif ?>
</div>

<?= \app\widgets\Alert::widget() ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <i data-lucide="network" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            <?= Html::encode($coin->name) ?> Peers
        </span>
        <span class="ml-auto px-2 py-0.5 text-xs rounded-full
                     bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
            <?= count($peers) ?>
        </span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700
               text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
        <th class="px-3 py-2.5 text-left">Address</th>
        <th class="px-3 py-2.5 text-left">Version</th>
        <th class="px-3 py-2.5 text-right">Height</th>
        <th class="px-3 py-2.5 text-right" title="<?= Html::encode($pingTitle) ?>">Ping</th>
        <th class="px-3 py-2.5 text-left">Services</th>
        <th class="px-3 py-2.5 text-left">Since</th>
        <th class="px-3 py-2.5 text-left">Last</th>
        <th class="px-3 py-2.5 text-right" title="<?= Html::encode($rxTxTitle) ?>">Rx / Tx (kB)</th>
        <th class="px-3 py-2.5 w-12" data-sorter="false"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($peers as $p): ?>
    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
            <?= Html::encode($p['node']) ?>
        </td>
        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
            <?= Html::encode($p['peerVer']) ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums font-mono
                   <?= $p['outOfSync'] ? 'text-red-500 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300' ?>">
            <?= (int) $p['height'] ?>
            <?php if ($p['outOfSync']): ?>
            <span class="ml-1 inline-flex items-center">
                <i data-lucide="alert-triangle" class="w-3 h-3"></i>
            </span>
            <?php endif ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode($p['peer']['pingtime'] ?? '') ?>
        </td>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500">
            <?= Html::encode($p['peer']['services'] ?? '') ?>
        </td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $conv->datetoa2($p['connTime']) ?>
            <?php if ($p['startHeight'] !== ''): ?>
            <span class="text-gray-300 dark:text-gray-600">(<?= (int) $p['startHeight'] ?>)</span>
            <?php endif ?>
        </td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $conv->datetoa2(max($p['lastRecv'], $p['lastSend'])) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= ($p['bytesRecv'] + $p['bytesSent']) ? Html::encode("{$p['bytesRecv']} / {$p['bytesSent']}") : '' ?>
        </td>
        <td class="px-3 py-2 text-center">
            <?php if (!$isGeth) echo Html::a('remove', $p['removeUrl'], [
                'class' => 'text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400 transition-colors',
                'title' => 'Disconnect from node',
            ]); ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="px-4 py-2.5 border-t border-gray-200 dark:border-gray-700
                flex gap-6 text-xs text-gray-500 dark:text-gray-400">
        <span>
            <span class="text-gray-400 dark:text-gray-500">Local:</span>
            <span class="font-mono ml-1 text-gray-700 dark:text-gray-300"><?= Html::encode($localVersion) ?></span>
        </span>
        <?php if ($latestVersion): ?>
        <span>
            <span class="text-gray-400 dark:text-gray-500">Latest peer:</span>
            <span class="font-mono ml-1 text-gray-700 dark:text-gray-300"><?= Html::encode($latestVersion) ?></span>
        </span>
        <?php endif ?>
    </div>
</div>

<?php if ($addnodeLines): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <i data-lucide="file-text" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">addnode config</span>
        <span class="ml-auto px-2 py-0.5 text-xs rounded-full
                     bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
            <?= count($addnodeLines) ?>
        </span>
    </div>
    <div class="p-4">
        <pre class="text-xs font-mono text-gray-600 dark:text-gray-300
                    bg-gray-50 dark:bg-gray-900/40 rounded-lg p-3 overflow-x-auto
                    whitespace-pre"><?= Html::encode(implode("\n", $addnodeLines)) ?></pre>
    </div>
</div>
<?php endif ?>

<script>
(function () {
    var t = document.querySelector('table.w-full');
    if (!t) return;
    var tb  = t.tBodies[0];
    var ths = Array.from(t.tHead.rows[0].cells);
    var asc = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rows = Array.from(tb.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n)
                    ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av))
                    : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tb.appendChild(r); });
        });
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
</script>

<?php endif ?>
