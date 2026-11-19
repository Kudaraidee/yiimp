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

$localHeight   = $info['blocks'] ?? 0;
$addnodeLines  = [];
$latestVersion = '';

// Pre-compute per-peer values
$peers = [];
foreach ($list as $peer) {
    $node    = $peer['addr'] ?? '';
    $prefix  = $coin->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=';
    $addnodeLines[] = $prefix . $node;

    $peerVer = trim($peer['subver'] ?? '', '/');
    if ($peerVer > $latestVersion) $latestVersion = $peerVer;

    $height      = $peer['currentheight'] ?? ($peer['synced_blocks'] ?? 0);
    $outOfSync   = abs($height - $localHeight) > 5;
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

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?><br/><br/>
<?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers') ?><br/><br/>

<style>
td.red { color: darkred; }
table.dataGrid a.red { color: darkred; }
div.form { text-align:right; height:30px; width:350px; float:right;
           margin-top:-48px; margin-bottom:16px; margin-right:-8px; }
.main-submit-button { cursor:pointer; }
</style>

<div class="form">
    <form action="<?= Html::encode($addNodeAction) ?>" method="post" style="padding:8px;">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="text" name="node" class="main-text-input"
               placeholder="addr[:port]" autocomplete="off" style="width:150px; margin-right:4px;">
        <input type="submit" value="Add node" class="main-submit-button">
    </form>
</div>

<?php foreach (['error', 'success'] as $t): ?>
<?php if (Yii::$app->session->hasFlash($t)): ?>
<p class="<?= $t === 'error' ? 'red' : '' ?>"><?= Html::encode(Yii::$app->session->getFlash($t)) ?></p>
<?php endif ?>
<?php endforeach ?>

<table id="maintable" class="dataGrid tablesorter">
<thead><tr>
    <th>Address</th>
    <th>Version</th>
    <th>Height</th>
    <th>Ping</th>
    <th>Services</th>
    <th>Since</th>
    <th>Last</th>
    <th>Rx / Tx (kB)</th>
    <th width="30"></th>
</tr></thead>
<tbody>
<?php foreach ($peers as $p): ?>
<tr class="ssrow">
    <td><?= Html::encode($p['node']) ?></td>
    <td><?= Html::encode($p['peerVer']) ?></td>
    <td class="<?= $p['outOfSync'] ? 'red' : '' ?>"><?= (int) $p['height'] ?></td>
    <td><?= Html::encode($p['peer']['pingtime'] ?? '') ?></td>
    <td><?= Html::encode($p['peer']['services'] ?? '') ?></td>
    <td><?= $conv->datetoa2($p['connTime']) ?> (<?= (int) $p['startHeight'] ?>)</td>
    <td><?= $conv->datetoa2(max($p['lastRecv'], $p['lastSend'])) ?></td>
    <td><?= ($p['bytesRecv'] + $p['bytesSent']) ? Html::encode("{$p['bytesRecv']} / {$p['bytesSent']}") : '' ?></td>
    <td><?= Html::a('remove', $p['removeUrl'], ['class' => 'red', 'title' => 'Disconnect']) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<br>

<b>Local version:</b> <?= Html::encode($localVersion) ?>&nbsp;
<b>Latest peer:</b> <?= Html::encode($latestVersion) ?>

<?php if ($addnodeLines): ?>
<pre><?= Html::encode(implode("\n", $addnodeLines)) ?></pre>
<?php endif ?>

<?php
$this->registerJs("
    if (typeof \$.fn.tablesorter !== 'undefined') {
        \$('#maintable').tablesorter({
            headers: { 2:{sorter:'numeric'}, 3:{sorter:'numeric'}, 7:{sorter:'numeric'}, 8:{sorter:false} },
            widgets: ['zebra','Storage','saveSort'],
            widgetOptions: { saveSort: true }
        });
    }
");
?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'peers') ?></div>
    <form action="<?= Html::encode($addNodeAction) ?>" method="post"
          class="d-flex gap-2 align-items-center shrink-0">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <input type="text" name="node" class="form-control form-control-sm"
               placeholder="addr[:port]" autocomplete="off" style="width:180px;">
        <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle me-1"></i>Add node
        </button>
    </form>
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
        <th class="small text-end">Ping</th>
        <th class="small">Services</th>
        <th class="small">Since</th>
        <th class="small">Last</th>
        <th class="small text-end">Rx / Tx (kB)</th>
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
            <?= Html::a('remove', $p['removeUrl'], [
                'class' => 'text-danger small',
                'title' => 'Disconnect from node',
            ]) ?>
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
        <th class="px-3 py-2.5 text-right">Ping</th>
        <th class="px-3 py-2.5 text-left">Services</th>
        <th class="px-3 py-2.5 text-left">Since</th>
        <th class="px-3 py-2.5 text-left">Last</th>
        <th class="px-3 py-2.5 text-right">Rx / Tx (kB)</th>
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
            <?= Html::a('remove', $p['removeUrl'], [
                'class' => 'text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400 transition-colors',
                'title' => 'Disconnect from node',
            ]) ?>
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
