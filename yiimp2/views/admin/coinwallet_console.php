<?php

/** @var yii\web\View        $this   */
/** @var app\models\Coins    $coin   */
/** @var array|false         $info   */
/** @var string              $query  */
/** @var mixed               $result */
/** @var string|null         $rpcErr */

use yii\helpers\Html;

$this->title = 'Console — ' . $coin->symbol;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$backUrl   = '/admin/coinwallet?id=' . $coin->id;
$actionUrl = '/admin/coinwallet-console?id=' . $coin->id;

// ── JSON syntax highlighter (server-side, shared across all schemes) ─────────
$colorizeJson = function (mixed $data): string {
    $raw = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $out = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // strings → detect hash / address / data / hex / plain
    $out = preg_replace_callback('/ &quot;([^&]*)&quot;([,\s])/', function ($m) {
        $v   = $m[1];
        $sfx = $m[2];
        if (strlen($v) === 64 && ctype_xdigit($v)) $cls = 'hash';
        elseif ((strlen($v) === 34 || strlen($v) === 35) && ctype_alnum($v)) $cls = 'addr';
        elseif (strlen($v) > 160 && ctype_alnum($v)) $cls = 'data';
        elseif (strlen($v) < 64 && ctype_xdigit($v)) $cls = 'hexa';
        else $cls = '';
        $tag = $cls ? "<s class=\"{$cls}\">{$v}</s>" : $v;
        return ' "' . $tag . '"' . $sfx;
    }, $out);

    // keys
    $out = preg_replace_callback('/&quot;([^&]+)&quot;:/', function ($m) {
        return '"<s class="key">' . $m[1] . '</s>":';
    }, $out);

    // unix timestamps (10-digit numbers after a colon)
    $out = preg_replace_callback('/: ([0-9]{10})([,\s])/', function ($m) {
        $ts = (int) $m[1];
        if ($ts > 1_400_000_000 && $ts < 2_000_000_000) {
            return ': "<u>' . date('Y-m-d H:i:s', $ts) . '</u>"' . $m[2];
        }
        return $m[0];
    }, $out);

    // numeric values
    $out = preg_replace('/: ([e\-\.0-9]+)([,\s])/', ': <i>$1</i>$2', $out);

    $out = preg_replace('/\[\s+\]/', '[]', $out);
    $out = str_replace(['[', ']', '{', '}'], ['<b>[</b>', '<b>]</b>', '<b>{</b>', '<b>}</b>'], $out);

    return $out;
};

$termOutput = '';
if ($result !== null) {
    $termOutput = is_string($result)
        ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : $colorizeJson($result);
}

// Header KPIs. A daemon can answer with no `balance` key, so fall back to the
// pool DB balance for display without treating the daemon as offline.
$rpcOnline   = !empty($info);
$height      = $rpcOnline && isset($info['blocks']) ? (string) $info['blocks'] : '—';
$connections = $rpcOnline && isset($info['connections']) ? (string) $info['connections'] : '—';
$balanceRaw  = $rpcOnline && isset($info['balance'])
    ? (float) $info['balance']
    : (float) ($coin->balance ?? 0);
$balance     = Yii::$app->ConversionUtils->altcoinvaluetoa($balanceRaw);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style admin chrome
     ══════════════════════════════════════════════════════════════════════════ -->
<?php
$walletLinks = str_replace([' || ', '<br/>', '<br />'], '',
    Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'console'));
?>
<div class="admn-page">
<?= Yii::$app->ViewUtils->getAdminMiningToolbar(
    'RPC Console',
    $coin->symbol . ' · ' . $coin->algo . ' · coin operations',
    'CONSOLE',
    'static · on demand'
) ?>
<div class="admn-selectbar">
    <label>Coin</label>
    <div class="admn-toolbar-links"><?= $walletLinks ?></div>
</div>

<div class="admn-kpis">
    <div class="admn-kpi <?= $rpcOnline ? 'good' : 'warn' ?>">
        <span class="k-label">RPC</span>
        <span class="k-value"><?= $rpcOnline ? 'Online' : 'Offline' ?></span>
        <span class="k-sub">wallet daemon</span>
    </div>
    <div class="admn-kpi accent">
        <span class="k-label">Height</span>
        <span class="k-value"><?= Html::encode($height) ?></span>
        <span class="k-sub">last known block</span>
    </div>
    <div class="admn-kpi">
        <span class="k-label">Connections</span>
        <span class="k-value"><?= Html::encode($connections) ?></span>
        <span class="k-sub">daemon peers</span>
    </div>
    <div class="admn-kpi">
        <span class="k-label">Balance</span>
        <span class="k-value"><?= Html::encode($balance) ?></span>
        <span class="k-sub"><?= Html::encode($coin->symbol) ?> · pool / wallet</span>
    </div>
</div>

<?php if (!$info): ?>
<div class="admn-card">
    <div class="admn-card-head">
        <span class="admn-head-main"><span class="admn-title">RPC Unavailable</span>
        <span class="admn-sub">console disabled until the daemon answers</span></span>
        <span class="admn-badge warn">offline</span>
    </div>
    <div class="admn-card-body">
        Unable to connect to <b><?= Html::encode($coin->symbol) ?></b> wallet daemon.
        Check the coin RPC settings from the wallet page before running commands.
    </div>
</div>
<?php else: ?>
<div class="admn-card admn-console-card">
    <div class="admn-card-head">
        <span class="admn-head-main"><span class="admn-title">RPC Console</span>
        <span class="admn-sub">raw wallet commands · <?= Html::encode($coin->symbol) ?></span></span>
        <span class="admn-head-actions">
            <span class="admn-badge good">ready</span>
            <span class="admn-badge muted"><?= $query !== '' ? Html::encode($query) : 'idle' ?></span>
        </span>
    </div>
    <div class="admn-card-body">
        <form action="<?= Html::encode($actionUrl) ?>" method="post" class="admn-console-form">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <input class="admn-input mono" value="<?= Html::encode($query) ?>" type="text" name="query"
                   placeholder="RPC command, e.g. getmininginfo" autofocus>
            <button type="submit" class="admn-pill primary">Execute</button>
        </form>

        <?php if ($rpcErr): ?>
        <div class="admn-console-error"><?= Html::encode(is_string($rpcErr) ? $rpcErr : json_encode($rpcErr)) ?></div>
        <?php endif ?>
        <div class="admn-console-terminal"><?= $termOutput ?: '<span class="admn-console-idle">Waiting for command…</span>' ?></div>
    </div>
</div>

<?php Yii::$app->ViewUtils->JavascriptReady("
    \$('input[name=query]').focus();
    \$('s.addr').each(function() {
        var addr = \$(this).text();
        \$(this).html('<a href=\"/wallet/'+encodeURIComponent(addr)+'\" target=\"_blank\">'+addr+'</a>');
    });
    \$('s.hash').each(function() {
        var hash = \$(this).text();
        \$(this).html('<a href=\"/explorer/search?q='+hash+'\" target=\"_blank\">'+hash+'</a>');
    });
") ?>

<?php endif ?>
</div><!-- /.admn-page -->

<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'console') ?></div>
    <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary shrink-0">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (!$info): ?>
<div class="alert alert-danger">
    Unable to connect to <strong><?= Html::encode($coin->symbol) ?></strong> wallet daemon.
</div>
<?php else: ?>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-terminal text-secondary"></i>
        <strong class="small">RPC Console — <?= Html::encode($coin->symbol) ?></strong>
    </div>
    <div class="card-body pb-2">
        <form action="<?= Html::encode($actionUrl) ?>" method="post" class="d-flex gap-2 mb-3">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <input type="text" name="query" value="<?= Html::encode($query) ?>"
                   class="form-control form-control-sm font-monospace"
                   placeholder="e.g. getinfo, getblockcount, getbalance" autofocus>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-play-fill me-1"></i>Execute
            </button>
        </form>

        <?php if ($rpcErr): ?>
        <div class="alert alert-danger py-2 small font-monospace mb-2">
            <?= Html::encode(is_string($rpcErr) ? $rpcErr : json_encode($rpcErr)) ?>
        </div>
        <?php endif ?>

        <pre class="rounded p-3 mb-0 small"
             style="background:#1e1e1e;color:#d4d4d4;min-height:160px;overflow-x:auto;
                    font-family:'Courier New',monospace;line-height:1.5;">
<style>
.rpc-out s          { text-decoration:none; color:#dcdcaa; }
.rpc-out s.key      { color:#9cdcfe; }
.rpc-out s.addr     { color:#ce9178; }
.rpc-out s.hash     { color:#b5cea8; }
.rpc-out s.hexa     { color:#d7ba7d; }
.rpc-out s.data     { color:#808080; }
.rpc-out u          { text-decoration:none; color:#f44747; }
.rpc-out i          { font-style:normal; color:#b5cea8; }
.rpc-out b          { font-style:normal; color:#569cd6; }
</style><span class="rpc-out"><?= $termOutput ?: '<span style="color:#555">Waiting for command…</span>' ?></span></pre>
    </div>
</div>

<?php Yii::$app->ViewUtils->JavascriptReady("
    \$('input[name=query]').focus();
    \$('s.addr').each(function() {
        var addr = \$(this).text();
        \$(this).html('<a href=\"/wallet/'+encodeURIComponent(addr)+'\" target=\"_blank\" style=\"color:#ce9178\">'+addr+'</a>');
    });
    \$('s.hash').each(function() {
        var hash = \$(this).text();
        \$(this).html('<a href=\"/explorer/search?q='+hash+'\" target=\"_blank\" style=\"color:#b5cea8\">'+hash+'</a>');
    });
") ?>

<?php endif ?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'console') ?></div>
    <a href="<?= Html::encode($backUrl) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
              border border-gray-300 dark:border-gray-600
              bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
              hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shrink-0">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
    </a>
</div>

<?php if (!$info): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-600 dark:text-red-400">
    Unable to connect to <strong><?= Html::encode($coin->symbol) ?></strong> wallet daemon.
</div>
<?php else: ?>

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-200 dark:border-gray-700">
        <i data-lucide="terminal" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            RPC Console — <?= Html::encode($coin->symbol) ?>
        </span>
    </div>

    <div class="px-5 py-4">
        <form action="<?= Html::encode($actionUrl) ?>" method="post" class="flex gap-2 mb-4">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <input type="text" name="query" value="<?= Html::encode($query) ?>"
                   class="flex-1 text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5
                          focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   placeholder="e.g. getinfo, getblockcount, getbalance" autofocus>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium text-white
                           bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors whitespace-nowrap">
                <i data-lucide="play" class="w-3.5 h-3.5"></i>Execute
            </button>
        </form>

        <?php if ($rpcErr): ?>
        <div class="mb-3 text-xs font-mono text-red-400 bg-red-950/40 border border-red-800/50 rounded-lg px-3 py-2">
            <?= Html::encode(is_string($rpcErr) ? $rpcErr : json_encode($rpcErr)) ?>
        </div>
        <?php endif ?>

        <pre class="rounded-xl p-4 text-xs leading-relaxed overflow-x-auto"
             style="background:#0d1117;color:#c9d1d9;min-height:180px;font-family:'Fira Code','Courier New',monospace;">
<style>
.rpc-tw s          { text-decoration:none; color:#e3b341; }
.rpc-tw s.key      { color:#79c0ff; }
.rpc-tw s.addr     { color:#ffa657; }
.rpc-tw s.hash     { color:#7ee787; }
.rpc-tw s.hexa     { color:#d2a8ff; }
.rpc-tw s.data     { color:#6e7681; }
.rpc-tw u          { text-decoration:none; color:#ff7b72; }
.rpc-tw i          { font-style:normal; color:#7ee787; }
.rpc-tw b          { font-style:normal; color:#58a6ff; }
</style><span class="rpc-tw"><?= $termOutput ?: '<span style="color:#30363d">Waiting for command…</span>' ?></span></pre>
    </div>
</div>

<?php Yii::$app->ViewUtils->JavascriptReady("
    if (typeof lucide !== 'undefined') lucide.createIcons();
    document.querySelector('input[name=query]').focus();
    document.querySelectorAll('s.addr').forEach(function(el) {
        var addr = el.textContent;
        el.innerHTML = '<a href=\"/wallet/'+encodeURIComponent(addr)+'\" target=\"_blank\" style=\"color:#ffa657\">'+addr+'</a>';
    });
    document.querySelectorAll('s.hash').forEach(function(el) {
        var hash = el.textContent;
        el.innerHTML = '<a href=\"/explorer/search?q='+hash+'\" target=\"_blank\" style=\"color:#7ee787\">'+hash+'</a>';
    });
") ?>

<?php endif ?>
<?php endif ?>
