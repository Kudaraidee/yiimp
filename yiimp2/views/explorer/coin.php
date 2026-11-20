<?php

use app\components\rpc\WalletRPC;
use yii\helpers\Html;

if (!$coin) return Yii::$app->controller->goHome();

$this->title    = $coin->name . ' block explorer';
$isTailwind     = Yii::$app->LayoutManager->isTailwind();
$isLegacy       = Yii::$app->LayoutManager->isLegacy();
$conv           = Yii::$app->ConversionUtils;

$start      = (int) Yii::$app->getRequest()->getQueryParam('start');
$multiAlgos = $coin->multialgos || Yii::$app->ExplorerUtils->versionToAlgo($coin, 0) !== false;
$remote     = new WalletRPC($coin);

if (!$start || $start > $coin->block_height) $start = $coin->block_height;

// ── Fetch blocks ──────────────────────────────────────────────────────────────
$blockRows = [];
for ($i = $start; $i > max(1, $start - 21); $i--) {
    $hash = $remote->getblockhash($i); if (!$hash) continue;
    $block = $remote->getblock($hash);  if (!$block) continue;

    $d        = $conv->datetoa2($block['time']);
    $confirms = $block['confirmations'] ?? '';
    $tx       = count($block['tx']);
    $diff     = $block['difficulty'];
    $algo     = Yii::$app->ExplorerUtils->versionToAlgo($coin, $block['version']);
    $type     = '';
    if ($conv->arraySafeval($block, 'nonce', 0) > 0) $type = 'PoW';
    elseif (isset($block['auxpow'])) $type = 'Aux';
    elseif (isset($block['mint']) || strstr($conv->arraySafeVal($block, 'flags', ''), 'proof-of-stake')) $type = 'PoS';
    if ($type === '' && $coin->symbol === 'ZEC') $type = 'PoW';

    $blockRows[] = compact('i', 'hash', 'd', 'confirms', 'tx', 'diff', 'algo', 'type');
}

// ── Pager links ───────────────────────────────────────────────────────────────
$pagerPrev = $start <= $coin->block_height - 20
    ? $coin->createExplorerLink('&laquo; Prev', ['start' => min($coin->block_height, $start + 20)])
    : null;
$pagerNow  = $start !== $coin->block_height
    ? $coin->createExplorerLink('Now')
    : null;
$pagerNext = $start > 20
    ? $coin->createExplorerLink('Next &raquo;', ['start' => max(1, $start - 20)])
    : null;

$actionUrl = $coin->visible ? '/explorer/' . $coin->symbol : '/explorer/search?id=' . $coin->id;
$showGraph = ($start === $coin->block_height);

// ── Favicon update ────────────────────────────────────────────────────────────
$this->registerJs("
    \$('#favicon').remove();
    \$('head').append('<link href=\"{$coin->image}\" id=\"favicon\" rel=\"shortcut icon\">');
", \yii\web\View::POS_READY);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Per-coin block explorer — mirrors /site/mining ─────────────────────────── */
#coin-explorer .main-left-box {
    border: 1px solid #d8e2ef !important; border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden;
}
#coin-explorer .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
    color: #26385a; font-size: 13px !important; font-weight: 800 !important; line-height: 1.3;
}
#coin-explorer .ce-head { display:flex; align-items:center; gap:8px; min-width:0; }
#coin-explorer .ce-coin-ico {
    flex:0 0 26px; height:26px; border-radius:6px; overflow:hidden; display:grid; place-items:center;
    color:#fff; font-size:7px; font-weight:800; background:linear-gradient(135deg,#4f79c8,#2c5aa0);
}
#coin-explorer .ce-coin-ico img { width:100%; height:100%; object-fit:contain; background:#fff; }
#coin-explorer .ce-title { font-size:13px; font-weight:800; color:#26385a; }
#coin-explorer .ce-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#coin-explorer .ce-pill {
    display:inline-block; border-radius:999px; padding:2px 7px;
    font-size:9px; font-weight:700; line-height:14px; white-space:nowrap;
    background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; text-decoration:none;
}
#coin-explorer .ce-pill.blue { background:#eef4ff; color:#2c5aa0; border-color:#d7e5ff; }
#coin-explorer .ce-pill.green { background:#e8f7ee; color:#187d46; border-color:#ccebd8; }
#coin-explorer .ce-pill.red { background:#fdecec; color:#c0392b; border-color:#f6d2d2; }
#coin-explorer .main-left-inner { padding:0 !important; }
#coin-explorer #coin-blocks {
    width:100% !important; border-collapse:collapse; table-layout:fixed;
    font-size:11px; color:#334155;
}
#coin-explorer #coin-blocks th {
    background:#fff !important; padding:7px 9px !important; text-align:right;
    font-size:9px !important; font-weight:700 !important; letter-spacing:.05em;
    text-transform:uppercase; color:#8a94a6; border-bottom:1px solid #e8eef6; white-space:nowrap;
}
#coin-explorer #coin-blocks .ce-left { text-align:left !important; }
#coin-explorer #coin-blocks td {
    padding:8px 9px !important; border-bottom:1px solid #eef2f7;
    text-align:right; vertical-align:middle;
}
#coin-explorer #coin-blocks tbody tr:last-child td { border-bottom:0; }
#coin-explorer #coin-blocks tbody tr:hover { background:#f7faff; }
#coin-explorer .ce-age { font-size:10px; color:#475569; font-weight:700; white-space:nowrap; }
#coin-explorer #coin-blocks .profile-link { color:#2c5aa0; font-weight:700; text-decoration:none; }
#coin-explorer #coin-blocks .profile-link:hover { text-decoration:underline; }
#coin-explorer .ce-val { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-variant-numeric:tabular-nums; font-size:11px; font-weight:700; color:#2b3d5b; white-space:nowrap; }
#coin-explorer .ce-cap { display:block; font-size:9px; color:#98a3b5; margin-top:1px; }
#coin-explorer .ce-hash { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:10px; color:#64748b; text-decoration:none; }
#coin-explorer .ce-hash a { color:inherit; text-decoration:none; }
#coin-explorer .ce-hash a:hover { color:#2c5aa0; }
#coin-explorer .ce-type {
    display:inline-block; border-radius:999px; padding:2px 7px;
    font-size:9px; font-weight:700; line-height:14px; white-space:nowrap;
    background:#eef4ff; color:#2c5aa0; border:1px solid #d7e5ff;
}
#coin-explorer .ce-type.pos { background:#e8f7ee; color:#187d46; border-color:#ccebd8; }
#coin-explorer .ce-type.aux { background:#f3e8ff; color:#7e22ce; border-color:#e9d5ff; }
#coin-explorer .ce-foot {
    display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    padding:8px 12px; background:#fcfdff; border-top:1px solid #eef2f7;
}
#coin-explorer #pager { display:flex; gap:6px; align-items:center; }
#coin-explorer #pager a { font-size:10px; color:#2c5aa0; text-decoration:none; }
#coin-explorer #pager a:hover { text-decoration:underline; }
#coin-explorer #form form { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
#coin-explorer .main-text-input {
    border:1px solid #d8e2ef; border-radius:7px; padding:4px 8px;
    font-size:10px; color:#334155; background:#fff; outline:none;
}
#coin-explorer .main-text-input:focus { border-color:#2c5aa0; }
#coin-explorer .main-submit-button {
    border:1px solid #2c5aa0; border-radius:7px; padding:4px 10px;
    font-size:10px; font-weight:700; color:#fff; background:#2c5aa0; cursor:pointer;
}
#coin-explorer #diff_graph { height:220px; padding:8px 12px 12px; border-top:1px solid #eef2f7; background:#fcfdff; }
@media(max-width:820px){ #coin-explorer #coin-blocks { table-layout:auto; } }
</style>
<div id="coin-explorer">
<div class="main-left-box">
<div class="main-left-title">
    <span class="ce-head">
        <span class="ce-coin-ico"><?php if (!empty($coin->image)): ?><img src="<?= Html::encode($coin->image) ?>" alt="" onerror="this.remove()"><?php endif ?></span>
        <span class="ce-title"><?= Html::encode($coin->name) ?> Explorer</span>
        <span class="ce-sub">recent blocks · height <?= Html::encode((string)$coin->block_height) ?></span>
    </span>
    <span class="ce-pill blue"><?= Html::encode($coin->symbol) ?></span>
</div>
<div class="main-left-inner">
<div style="overflow-x:auto">
<table id="coin-blocks">
<thead><tr>
    <th class="ce-left" style="width:11%">Age</th><th style="width:10%">Height</th><th style="width:15%">Difficulty</th><th class="ce-left" style="width:8%">Type</th>
    <?= $multiAlgos ? '<th class="ce-left" style="width:8%">Algo</th>' : '' ?>
    <th style="width:7%">Tx</th><th style="width:8%">Conf</th><th class="ce-left" style="width:43%">Blockhash</th>
</tr></thead><tbody>
<?php foreach ($blockRows as $b):
    $typeClass = $b['type'] === 'PoS' ? 'pos' : ($b['type'] === 'Aux' ? 'aux' : '');
?>
<tr>
    <td class="ce-left"><span class="ce-age"><?= $b['d'] ?></span></td>
    <td><?= $coin->createExplorerLink($b['i'], ['height' => $b['i']]) ?></td>
    <td><span class="ce-val"><?= Html::encode((string)$b['diff']) ?></span></td>
    <td class="ce-left"><?php if ($b['type'] !== ''): ?><span class="ce-type <?= $typeClass ?>"><?= Html::encode($b['type']) ?></span><?php else: ?><span class="ce-cap">—</span><?php endif ?></td>
    <?php if ($multiAlgos): ?><td class="ce-left"><span class="ce-pill blue"><?= Html::encode($b['algo']) ?></span></td><?php endif ?>
    <td><span class="ce-val"><?= (int)$b['tx'] ?></span></td>
    <td><span class="ce-pill green"><?= Html::encode((string)$b['confirms']) ?></span></td>
    <td class="ce-left"><span class="ce-hash"><?= $coin->createExplorerLink($b['hash'], ['hash' => $b['hash']]) ?></span></td>
</tr>
<?php endforeach ?>
</tbody></table>
</div>
<div class="ce-foot">
    <div id="pager"><?= $pagerPrev ?>&nbsp;<?= $pagerNow ?>&nbsp;<?= $pagerNext ?></div>
    <div id="form">
    <form action="<?= Html::encode($actionUrl) ?>" method="POST">
        <input type="text" name="height" class="main-text-input" placeholder="Height" style="width:80px;">
        <input type="text" name="txid"   class="main-text-input" placeholder="Transaction hash" style="width:260px;">
        <input type="submit" value="Search" class="main-submit-button">
    </form>
    </div>
</div>
<?php if ($showGraph): ?>
<div id="diff_graph"></div>
<?php endif ?>
</div></div>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="20" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?>
        <strong class="small"><?= Html::encode($coin->name) ?> Explorer</strong>
        <span class="badge bg-light text-dark border font-monospace ms-1"><?= Html::encode($coin->symbol) ?></span>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if ($pagerPrev): ?><?= $pagerPrev ?><?php endif ?>
            <?php if ($pagerNow): ?><?= $pagerNow ?><?php endif ?>
            <?php if ($pagerNext): ?><?= $pagerNext ?><?php endif ?>
        </div>
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th>Age</th><th class="text-end">Height</th>
        <th class="text-end">Difficulty</th><th>Type</th>
        <?= $multiAlgos ? '<th>Algo</th>' : '' ?>
        <th class="text-end">Tx</th><th class="text-end">Conf</th><th>Blockhash</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($blockRows as $b): ?>
    <tr>
        <td class="small text-muted whitespace-nowrap"><?= $b['d'] ?></td>
        <td class="text-end small tabular-nums"><?= $coin->createExplorerLink($b['i'], ['height' => $b['i']]) ?></td>
        <td class="text-end small font-monospace"><?= Html::encode((string)$b['diff']) ?></td>
        <td><span class="badge bg-<?= $b['type'] === 'PoW' ? 'primary' : ($b['type'] === 'PoS' ? 'success' : 'secondary') ?>">
            <?= Html::encode($b['type']) ?></span></td>
        <?php if ($multiAlgos): ?><td class="small"><?= Html::encode($b['algo']) ?></td><?php endif ?>
        <td class="text-end small tabular-nums"><?= (int)$b['tx'] ?></td>
        <td class="text-end small tabular-nums"><?= Html::encode((string)$b['confirms']) ?></td>
        <td class="small font-monospace" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= $coin->createExplorerLink($b['hash'], ['hash' => $b['hash']]) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    </div>
    <div class="card-footer py-2">
        <form action="<?= Html::encode($actionUrl) ?>" method="POST" class="d-flex align-items-center gap-2">
            <input type="text" name="height" class="form-control form-control-sm"
                   placeholder="Block height" style="width:100px;">
            <input type="text" name="txid" class="form-control form-control-sm"
                   placeholder="Transaction hash" style="width:360px;">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
    </div>
</div>
<?php if ($showGraph): ?>
<div class="card shadow-sm">
    <div class="card-header py-2 small fw-semibold">Network Difficulty</div>
    <div class="card-body"><div id="diff_graph" style="height:220px;"></div></div>
</div>
<?php endif ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="22" height="22"
                 class="rounded object-contain" onerror="this.style.display='none'" alt="">
        <?php endif ?>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            <?= Html::encode($coin->name) ?> Explorer
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30
                     text-indigo-700 dark:text-indigo-300 font-mono">
            <?= Html::encode($coin->symbol) ?>
        </span>
        <div class="ml-auto flex items-center gap-3 text-xs">
            <?php if ($pagerPrev): ?><?= $pagerPrev ?><?php endif ?>
            <?php if ($pagerNow):  ?><?= $pagerNow  ?><?php endif ?>
            <?php if ($pagerNext): ?><?= $pagerNext ?><?php endif ?>
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 text-left">Age</th>
        <th class="px-3 py-2.5 text-right">Height</th>
        <th class="px-3 py-2.5 text-right">Difficulty</th>
        <th class="px-3 py-2.5 text-left">Type</th>
        <?= $multiAlgos ? '<th class="px-3 py-2.5 text-left">Algo</th>' : '' ?>
        <th class="px-3 py-2.5 text-right">Tx</th>
        <th class="px-3 py-2.5 text-right">Conf</th>
        <th class="px-3 py-2.5 text-left">Blockhash</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($blockRows as $b):
        $typeCls = $b['type'] === 'PoW'
            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
            : ($b['type'] === 'PoS'
                ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400');
    ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"><?= $b['d'] ?></td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $coin->createExplorerLink($b['i'], ['height' => $b['i']]) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode((string)$b['diff']) ?>
        </td>
        <td class="px-3 py-2">
            <?php if ($b['type']): ?>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium <?= $typeCls ?>">
                <?= Html::encode($b['type']) ?>
            </span>
            <?php endif ?>
        </td>
        <?php if ($multiAlgos): ?>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500"><?= Html::encode($b['algo']) ?></td>
        <?php endif ?>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300"><?= (int)$b['tx'] ?></td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= Html::encode((string)$b['confirms']) ?>
        </td>
        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400"
            style="max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= $coin->createExplorerLink($b['hash'], ['hash' => $b['hash']]) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
        <form action="<?= Html::encode($actionUrl) ?>" method="POST"
              class="flex items-center gap-2 flex-wrap">
            <input type="text" name="height"
                   class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-indigo-500
                          placeholder-gray-400 dark:placeholder-gray-500"
                   placeholder="Block height" style="width:100px;">
            <input type="text" name="txid"
                   class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-indigo-500
                          placeholder-gray-400 dark:placeholder-gray-500"
                   placeholder="Transaction hash" style="width:360px;">
            <button type="submit"
                    class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300
                           hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                Search
            </button>
        </form>
    </div>
</div>

<?php if ($showGraph): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                text-xs font-semibold text-gray-600 dark:text-gray-300">
        Network Difficulty
    </div>
    <div class="p-4"><div id="diff_graph" style="height:220px;"></div></div>
</div>
<?php endif ?>

<?php endif ?>

<?php if ($showGraph): ?>
<script>
var last_graph_update = 0;
function graph_refresh() {
    var now = Date.now() / 1000;
    if (now < last_graph_update + 900) return;
    last_graph_update = now;
    $.get('/explorer/graph?id=<?= $coin->id ?>', '', diff_graph_data);
}
function diff_graph_data(data) {
    var t = JSON.parse(data);
    if (!t || !t.length) return;
    var s1 = t[0].map(function(p) { return [p[0], p[1]]; });
    var s2 = t[1] ? t[1].map(function(p) { return [p[0], p[1]]; }) : null;
    yiimpChart('diff_graph', s2 ? [s1, s2] : [s1], {
        title: 'Network diff', labels: ['Difficulty', 'Pool blocks'], decimals: 3
    });
}
</script>
<?php Yii::$app->view->registerJs('graph_refresh();', \yii\web\View::POS_READY, 'graph'); ?>
<?php endif ?>
