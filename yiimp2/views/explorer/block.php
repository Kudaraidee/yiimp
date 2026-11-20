<?php

use app\components\rpc\WalletRPC;
use yii\helpers\Html;

if (!$coin) return Yii::$app->controller->goHome();

$this->title = $coin->name . ' block explorer';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;

$txid = Yii::$app->getRequest()->getQueryParam('txid');
$q    = Yii::$app->getRequest()->getQueryParam('q');
$qhex = preg_replace('/^0x/', '', (string) $q); // Ethereum hashes are 0x-prefixed
if (!empty($qhex) && ctype_xdigit($qhex)) $txid = $q;
elseif (empty($txid)) $txid = 'txid not set';

$actionUrl = $coin->visible ? '/explorer/' . $coin->symbol : '/explorer/search?id=' . $coin->id;

// ── Favicon + txid highlight ──────────────────────────────────────────────────
$this->registerJs("
    \$('#favicon').remove();
    \$('head').append('<link href=\"{$coin->image}\" id=\"favicon\" rel=\"shortcut icon\">');
    \$('span.txid').on('click', function(){ \$(this).parents('tr').next('tr.raw').toggle(); });
    \$('span.txid:contains(\"" . addslashes($txid) . "\")').css('color','darkred');
", \yii\web\View::POS_READY);

// ── JSON colorizer (unchanged) ────────────────────────────────────────────────
function colorizeJson($json)
{
    $json = str_replace('"', '&quot;', $json);
    $res  = preg_match_all("# &quot;([^&]+)&quot;([,\s])#", $json, $matches);
    if ($res) foreach ($matches[1] as $n => $m) {
        $sfx   = $matches[2][$n];
        $class = '';
        if (strlen($m) == 64 && ctype_xdigit($m)) $class = 'hash';
        if (strlen($m) == 34 && ctype_alnum($m))  $class = 'addr';
        if (strlen($m) == 35 && ctype_alnum($m))  $class = 'addr';
        if (strlen($m) > 160 && ctype_alnum($m))  $class = 'data';
        if ($class === '' && strlen($m) < 64 && ctype_xdigit($m)) $class = 'hexa';
        $json = str_replace(' &quot;' . $m . '&quot;' . $sfx,
            ' "<s class="' . $class . '">' . $m . '</s>"' . $sfx, $json);
    }
    $res = preg_match_all("#&quot;([^&]+)&quot;:#", $json, $matches);
    if ($res) foreach ($matches[1] as $n => $m) {
        $json = str_replace('&quot;' . $m . '&quot;', '"<s class="key">' . $m . '</s>"', $json);
    }
    $res = preg_match_all("#: ([0-9]{10})([,\s])#", $json, $matches);
    if ($res) foreach ($matches[1] as $n => $m) {
        $ts = intval($m);
        if ($ts > 1400000000 && $ts < 1900000000) {
            $sfx  = $matches[2][$n];
            $date = date("<u>Y-m-d H:i:s</u>", $ts);
            $json = str_replace(' ' . $m . $sfx, ' "' . $date . '"' . $sfx, $json);
        }
    }
    $res = preg_match_all("#: ([e\-\.0-9]+)([,\s])#", $json, $matches);
    if ($res) foreach ($matches[1] as $n => $m) {
        $sfx  = $matches[2][$n];
        $json = str_replace(' ' . $m . $sfx, ' <i>' . $m . '</i>' . $sfx, $json);
    }
    $json = preg_replace('#\[\s+\]#', '[]', $json);
    $json = str_replace('[', '<b>[</b>', $json);
    $json = str_replace(']', '<b>]</b>', $json);
    $json = str_replace('{', '<b>{</b>', $json);
    $json = str_replace('}', '<b>}</b>', $json);
    return $json;
}

function simplifyscript($script)
{
    $script = preg_replace("/[0-9a-f]+ OP_DROP ?/", '', $script);
    $script = preg_replace("/OP_NOP ?/", '', $script);
    return trim($script);
}

// ── Fetch block ───────────────────────────────────────────────────────────────
$remote = new WalletRPC($coin);
$block  = $remote->getblock($hash);
if (!$block) return;

$d        = date('Y-m-d H:i:s', $block['time']);
$confirms = $block['confirmations'] ?? '';
$txcount  = count($block['tx']);
$version  = dechex($block['version']);
$nonce    = $block['nonce'];

// ── Scheme-specific styles ────────────────────────────────────────────────────
$jsonBoxCss = '
span.monospace { font-family:monospace; }
span.txid { cursor:pointer; }
tr.raw td { max-width:1880px; }
td.ntx { width:8px; font-family:monospace; }
div.json { font-size:11px; white-space:pre; font-family:monospace; padding:4px;
           overflow-x:hidden; background:#f0f0f0; border:1px solid silver; }
div.json s { text-decoration:none; color:#003f7f; }
div.json s.key { color:#000; }
div.json s.addr { color:#3f003f; }
div.json i { font-style:normal; color:#7f0000; }
div.json b { font-style:normal; color:#7f0000; }
';
?>
<style><?= $jsonBoxCss ?></style>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Block detail — mirrors /site/mining ───────────────────────────────────── */
.page .footer { width:auto; }
#block-detail, #block-txs { margin-bottom:12px; }
#block-detail .main-left-box, #block-txs .main-left-box {
    border:1px solid #d8e2ef !important; border-radius:7px !important;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden;
}
#block-detail .main-left-title, #block-txs .main-left-title {
    display:flex !important; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px !important;
    background:#f8fafc !important; border-bottom:1px solid #e8eef6;
    color:#26385a; font-size:13px !important; font-weight:800 !important; line-height:1.3;
}
#block-detail .bd-head, #block-txs .bd-head { display:flex; align-items:center; gap:8px; min-width:0; }
#block-detail .bd-ico {
    flex:0 0 26px; height:26px; border-radius:6px; overflow:hidden; display:grid; place-items:center;
    color:#fff; font-size:7px; font-weight:800; background:linear-gradient(135deg,#4f79c8,#2c5aa0);
}
#block-detail .bd-ico img { width:100%; height:100%; object-fit:contain; background:#fff; }
#block-detail .bd-title, #block-txs .bd-title { font-size:13px; font-weight:800; color:#26385a; }
#block-detail .bd-sub, #block-txs .bd-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#block-detail .bd-chips { display:flex; gap:5px; flex-wrap:wrap; }
#block-detail .bd-pill, #block-txs .bd-pill {
    display:inline-block; border-radius:999px; padding:2px 7px;
    font-size:9px; font-weight:700; line-height:14px; white-space:nowrap;
    background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; text-decoration:none;
}
#block-detail .bd-pill.green { background:#e8f7ee; color:#187d46; border-color:#ccebd8; }
#block-detail .bd-pill.blue { background:#eef4ff; color:#2c5aa0; border-color:#d7e5ff; }
#block-detail .main-left-inner, #block-txs .main-left-inner { padding:0 !important; }
#block-detail table, #block-txs #tx-table {
    width:100% !important; border-collapse:collapse; table-layout:fixed;
    font-size:11px; color:#334155; margin:0 !important;
}
#block-detail th {
    width:150px; padding:7px 12px !important; text-align:left;
    font-size:9px !important; font-weight:700 !important; letter-spacing:.05em;
    text-transform:uppercase; color:#8a94a6; border-bottom:1px solid #eef2f7;
    vertical-align:top;
}
#block-detail td {
    padding:7px 12px !important; border-bottom:1px solid #eef2f7;
    vertical-align:middle; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    font-size:10px; word-break:break-all;
}
#block-detail tbody tr:last-child td { border-bottom:0; }
#block-detail tr:hover td, #block-detail tr:hover th { background:#f7faff; }
#block-detail .txid { cursor:pointer; color:#2c5aa0; font-weight:700; }
#block-detail .txid:hover { text-decoration:underline; }
#block-detail td .profile-link, #block-txs td .profile-link { color:#2c5aa0; text-decoration:none; }
#block-detail td .profile-link:hover { text-decoration:underline; }
#block-detail tr.raw td, #block-txs tr.raw td {
    padding:10px 12px !important; background:#fcfdff; border-bottom:1px solid #eef2f7;
}
#block-detail div.json, #block-txs div.json {
    font-size:10px; white-space:pre-wrap; word-break:break-word;
    padding:8px 10px; overflow-x:auto; background:#fcfdff !important;
    border:1px solid #e8eef6 !important; border-radius:7px; color:#475569;
}
#block-txs #tx-table th {
    background:#fff !important; padding:7px 9px !important; text-align:right;
    font-size:9px !important; font-weight:700 !important; letter-spacing:.05em;
    text-transform:uppercase; color:#8a94a6; border-bottom:1px solid #e8eef6; white-space:nowrap;
}
#block-txs #tx-table td {
    padding:8px 9px !important; border-bottom:1px solid #eef2f7;
    text-align:right; vertical-align:middle;
}
#block-txs #tx-table tbody tr:not(.raw):last-child td { border-bottom:0; }
#block-txs #tx-table tbody tr:hover { background:#f7faff; }
#block-txs #tx-table .ce-left { text-align:left !important; }
#block-txs #tx-table .ntx { width:28px; color:#98a3b5; font-size:10px; }
#block-txs #tx-table .txid { cursor:pointer; color:#2c5aa0; font-size:10px; word-break:break-all; }
#block-txs #tx-table .txid:hover { text-decoration:underline; }
#block-txs .bd-foot {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:8px 12px; background:#fcfdff; border-top:1px solid #eef2f7;
}
#block-txs .bd-foot form { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
#block-txs .main-text-input {
    border:1px solid #d8e2ef; border-radius:7px; padding:4px 8px;
    font-size:10px; color:#334155; background:#fff; outline:none;
}
#block-txs .main-text-input:focus { border-color:#2c5aa0; }
#block-txs .main-submit-button {
    border:1px solid #2c5aa0; border-radius:7px; padding:4px 10px;
    font-size:10px; font-weight:700; color:#fff; background:#2c5aa0; cursor:pointer;
}
@media(max-width:820px){
    #block-detail table, #block-txs #tx-table { table-layout:auto; }
    #block-detail .bd-chips { display:none; }
}
</style>
<div id="block-detail">
<div class="main-left-box">
<div class="main-left-title">
    <span class="bd-head">
        <span class="bd-ico"><?php if (!empty($coin->image)): ?><img src="<?= Html::encode($coin->image) ?>" alt="" onerror="this.remove()"><?php endif ?></span>
        <span class="bd-title"><?= Html::encode($coin->name) ?> Block</span>
        <span class="bd-sub">height <?= Html::encode((string)$block['height']) ?> · <?= Html::encode($d) ?></span>
    </span>
    <span class="bd-chips"><span class="bd-pill green"><?= Html::encode((string)$confirms) ?> conf</span><span class="bd-pill blue"><?= $txcount ?> tx</span></span>
</div>
<div class="main-left-inner">
<table>
<tbody>
<tr><th>Coin</th><td><?= $coin->createExplorerLink(Html::encode($coin->name)) ?></td></tr>
<tr><th>Blockhash</th><td><span class="txid monospace"><?= Html::encode($hash) ?></span></td></tr>
<tr class="raw" style="display:none;"><td colspan="2"><div class="json">
<?= colorizeJson(json_encode($block, 128)) ?>
</div></td></tr>
<tr><th>Confirmations</th><td><?= Html::encode((string)$confirms) ?></td></tr>
<tr><th>Height</th><td><?= Html::encode((string)$block['height']) ?></td></tr>
<tr><th>Time</th><td><?= Html::encode($d) ?> (<?= (int)$block['time'] ?>)</td></tr>
<tr><th>Difficulty</th><td><?= Html::encode((string)$block['difficulty']) ?></td></tr>
<tr><th>Bits</th><td><?= Html::encode($block['bits']) ?></td></tr>
<tr><th>Nonce</th><td><?= Html::encode((string)$nonce) ?></td></tr>
<tr><th>Version</th><td><?= Html::encode($version) ?></td></tr>
<tr><th>Size</th><td><?= (int)$block['size'] ?> bytes</td></tr>
<?php if (isset($block['flags'])): ?><tr><th>Flags</th><td><?= Html::encode($block['flags']) ?></td></tr><?php endif ?>
<?php if (isset($block['previousblockhash']) && $coin->algo === 'x16r'): ?>
<tr><th>Hash order</th><td><?= Html::encode(substr($block['previousblockhash'], -16)) ?></td></tr>
<?php endif ?>
<?php if (isset($block['previousblockhash'])): ?>
<tr><th>Previous Hash</th><td><?= $coin->createExplorerLink($block['previousblockhash'], ['hash' => $block['previousblockhash']]) ?></td></tr>
<?php endif ?>
<?php if (isset($block['nextblockhash'])): ?>
<tr><th>Next Hash</th><td><?= $coin->createExplorerLink($block['nextblockhash'], ['hash' => $block['nextblockhash']]) ?></td></tr>
<?php endif ?>
<tr><th>Merkle Root</th><td><?= Html::encode($block['merkleroot']) ?></td></tr>
<tr><th>Transactions</th><td><?= $txcount ?></td></tr>
</tbody></table>
</div></div>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE  (Bootstrap 5)
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="20" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?>
        <strong class="small"><?= Html::encode($coin->name) ?> — Block <?= Html::encode((string)$block['height']) ?></strong>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm mb-0" style="table-layout:fixed;">
    <colgroup><col style="width:140px"><col></colgroup>
    <?php
    $infoRows = [
        ['Coin',          $coin->createExplorerLink(Html::encode($coin->name))],
        ['Block hash',    '<span class="txid font-monospace small">' . Html::encode($hash) . '</span>'],
        ['Confirmations', Html::encode((string)$confirms)],
        ['Height',        Html::encode((string)$block['height'])],
        ['Time',          Html::encode($d) . ' <span class="text-muted small">(' . $block['time'] . ')</span>'],
        ['Difficulty',    Html::encode((string)$block['difficulty'])],
        ['Bits',          '<span class="font-monospace small">' . Html::encode($block['bits']) . '</span>'],
        ['Nonce',         '<span class="font-monospace small">' . Html::encode((string)$nonce) . '</span>'],
        ['Version',       '<span class="font-monospace small">' . Html::encode($version) . '</span>'],
        ['Size',          Html::encode((string)$block['size']) . ' bytes'],
        ['Transactions',  Html::encode((string)$txcount)],
    ];
    if (isset($block['flags'])) $infoRows[] = ['Flags', '<span class="font-monospace small">' . Html::encode($block['flags']) . '</span>'];
    if (isset($block['previousblockhash'])) $infoRows[] = ['Previous', '<span class="font-monospace small">' . $coin->createExplorerLink(Html::encode($block['previousblockhash']), ['hash' => $block['previousblockhash']]) . '</span>'];
    if (isset($block['nextblockhash'])) $infoRows[] = ['Next', '<span class="font-monospace small">' . $coin->createExplorerLink(Html::encode($block['nextblockhash']), ['hash' => $block['nextblockhash']]) . '</span>'];
    $infoRows[] = ['Merkle Root', '<span class="font-monospace small">' . Html::encode($block['merkleroot']) . '</span>'];

    foreach ($infoRows as [$label, $val]):
    ?>
    <tr>
        <td class="text-muted small ps-3 py-1 border-end fw-semibold"><?= Html::encode($label) ?></td>
        <td class="small ps-3 py-1" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $val ?></td>
    </tr>
    <?php endforeach ?>
    </table>
    </div>
    <!-- Raw JSON toggle row (hidden by default, revealed by txid click) -->
    <div class="raw" style="display:none;"><div class="card-body p-2">
        <div class="json p-2 rounded"><?= colorizeJson(json_encode($block, 128)) ?></div>
    </div></div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            <?= Html::encode($coin->name) ?> &mdash; Block <?= Html::encode((string)$block['height']) ?>
        </span>
    </div>
    <table class="w-full text-xs">
        <colgroup><col style="width:130px"><col></colgroup>
        <?php
        $infoRows = [
            ['Coin',          $coin->createExplorerLink(Html::encode($coin->name))],
            ['Block hash',    '<span class="txid font-mono text-gray-500 dark:text-gray-400 cursor-pointer break-all">' . Html::encode($hash) . '</span>'],
            ['Confirmations', Html::encode((string)$confirms)],
            ['Height',        Html::encode((string)$block['height'])],
            ['Time',          Html::encode($d) . ' <span class="text-gray-400 dark:text-gray-500">(' . $block['time'] . ')</span>'],
            ['Difficulty',    Html::encode((string)$block['difficulty'])],
            ['Bits',          '<span class="font-mono">' . Html::encode($block['bits']) . '</span>'],
            ['Nonce',         '<span class="font-mono">' . Html::encode((string)$nonce) . '</span>'],
            ['Version',       '<span class="font-mono">' . Html::encode($version) . '</span>'],
            ['Size',          Html::encode((string)$block['size']) . ' bytes'],
            ['Transactions',  Html::encode((string)$txcount)],
        ];
        if (isset($block['flags'])) $infoRows[] = ['Flags', '<span class="font-mono">' . Html::encode($block['flags']) . '</span>'];
        if (isset($block['previousblockhash'])) $infoRows[] = ['Previous', '<span class="font-mono">' . $coin->createExplorerLink(Html::encode($block['previousblockhash']), ['hash' => $block['previousblockhash']]) . '</span>'];
        if (isset($block['nextblockhash'])) $infoRows[] = ['Next', '<span class="font-mono">' . $coin->createExplorerLink(Html::encode($block['nextblockhash']), ['hash' => $block['nextblockhash']]) . '</span>'];
        $infoRows[] = ['Merkle root', '<span class="font-mono break-all">' . Html::encode($block['merkleroot']) . '</span>'];

        foreach ($infoRows as [$label, $val]):
        ?>
        <tr class="border-b border-gray-100 dark:border-gray-700/50">
            <td class="px-4 py-1.5 text-gray-500 dark:text-gray-400 font-semibold whitespace-nowrap border-r border-gray-100 dark:border-gray-700/50 align-top"><?= Html::encode($label) ?></td>
            <td class="px-4 py-1.5 text-gray-700 dark:text-gray-300 overflow-hidden"><?= $val ?></td>
        </tr>
        <?php endforeach ?>
    </table>
    <!-- Raw JSON toggle (hidden by default, revealed by clicking the block hash) -->
    <div class="raw" style="display:none;">
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/50">
            <div class="json p-2 rounded"><?= colorizeJson(json_encode($block, 128)) ?></div>
        </div>
    </div>
</div>

<?php endif ?>

<?php
// ── Scheme helpers for the transaction table ──────────────────────────────────

if ($isLegacy) {
    $tblOpen     = '<div id="block-txs"><div class="main-left-box">'
                 . '<div class="main-left-title"><span class="bd-head"><span class="bd-title">Transactions</span>'
                 . '<span class="bd-sub">' . $txcount . ' in block · click hash for raw JSON</span></span>'
                 . '<span class="bd-pill blue">' . $txcount . ' tx</span></div>'
                 . '<div class="main-left-inner"><div style="overflow-x:auto">'
                 . '<table id="tx-table">';
    $tblClose    = '</table></div><div class="bd-foot">'
                 . '<form action="' . Html::encode($actionUrl) . '" method="POST">'
                 . '<input type="text" name="height" class="main-text-input" placeholder="Block height" style="width:100px;">'
                 . '<input type="text" name="txid" class="main-text-input" placeholder="Transaction hash" style="width:260px;">'
                 . '<input type="submit" value="Search" class="main-submit-button">'
                 . '</form></div></div></div></div>';
    $thRow       = '<thead><tr><th class="ce-left ntx">#</th><th class="ce-left">Transaction Hash</th>'
                 . '<th>Size</th><th>Value</th><th class="ce-left">From</th><th class="ce-left">To (amount)</th></tr></thead>';
    $trOpen      = '<tr>';
    $trClose     = '</tr>';
    $tdNum       = fn($n) => '<td class="ntx">' . $n . '</td>';
    $tdTxid      = fn($v) => '<td class="ce-left"><span class="txid monospace">' . $v . '</span></td>';
    $tdNum2      = fn($v) => '<td>' . $v . '</td>';
    $tdFromOpen  = '<td class="ce-left">';
    $tdFromClose = '</td>';
    $tdToOpen    = '<td class="ce-left">';
    $tdToClose   = '</td>';
    $spanAddr    = fn($a) => '<span class="monospace">' . $a . '</span>';
    $rawColspan  = 6;
    $rawOpen     = '<tr class="raw" style="display:none;"><td colspan="6"><div class="json">';
    $rawClose    = '</div></td></tr>';
    $tooMany     = fn() => '<tr><td colspan="6" class="ce-left" style="color:#98a3b5;padding:10px 12px;font-size:10px;">Too many transactions to display...</td></tr>';
    $stakeHdr    = '<tr><th class="ce-left" colspan="6" style="background:#f8fafc;color:#26385a;padding:7px 9px;font-size:9px;text-transform:uppercase;letter-spacing:.05em;">Stake Transactions</th></tr>';
} elseif (!$isTailwind) {
    // AdminLTE
    $tblOpen     = '<div class="card shadow-sm mt-3"><div class="card-header py-2 small fw-semibold">'
                 . count($block['tx']) . ' Transaction' . (count($block['tx']) !== 1 ? 's' : '')
                 . '</div><div class="card-body p-0"><div class="overflow-auto">'
                 . '<table class="table table-sm table-bordered table-hover mb-0">';
    $tblClose    = '</table></div></div></div>';
    $thRow       = '<thead class="table-light"><tr>'
                 . '<th style="width:30px">#</th>'
                 . '<th>Transaction Hash</th>'
                 . '<th class="text-end" style="width:60px">Size</th>'
                 . '<th class="text-end" style="width:80px">Value</th>'
                 . '<th style="width:100px">From</th>'
                 . '<th>To (amount)</th>'
                 . '</tr></thead>';
    $trOpen      = '<tr>';
    $trClose     = '</tr>';
    $tdNum       = fn($n) => '<td class="tabular-nums text-muted small">' . $n . '</td>';
    $tdTxid      = fn($v) => '<td class="font-monospace small" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                           . '<span class="txid" style="cursor:pointer;">' . $v . '</span></td>';
    $tdNum2      = fn($v) => '<td class="text-end tabular-nums small">' . $v . '</td>';
    $tdFromOpen  = '<td class="small">';
    $tdFromClose = '</td>';
    $tdToOpen    = '<td class="small font-monospace" style="max-width:400px;">';
    $tdToClose   = '</td>';
    $spanAddr    = fn($a) => '<span class="font-monospace">' . $a . '</span>';
    $rawColspan  = 6;
    $rawOpen     = '<tr class="raw" style="display:none;"><td colspan="6" class="p-2"><div class="json">';
    $rawClose    = '</div></td></tr>';
    $tooMany     = fn() => '<tr><td colspan="6" class="text-muted small">Too many transactions to display...</td></tr>';
    $stakeHdr    = '<tr><th colspan="6" class="table-secondary small">Stake Transactions</th></tr>';
} else {
    // Tailwind
    $tblOpen     = '<div class="rounded-xl border border-gray-200 dark:border-gray-700 '
                 . 'bg-white dark:bg-gray-800 shadow-sm overflow-hidden mt-4">'
                 . '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 '
                 . 'text-xs font-semibold text-gray-600 dark:text-gray-300">'
                 . count($block['tx']) . ' Transaction' . (count($block['tx']) !== 1 ? 's' : '')
                 . '</div><div class="overflow-x-auto"><table class="w-full text-xs">';
    $tblClose    = '</table></div></div>';
    $thRow       = '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 '
                 . 'font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">'
                 . '<th class="px-3 py-2.5 text-right w-8">#</th>'
                 . '<th class="px-3 py-2.5 text-left">Transaction Hash</th>'
                 . '<th class="px-3 py-2.5 text-right w-16">Size</th>'
                 . '<th class="px-3 py-2.5 text-right w-24">Value</th>'
                 . '<th class="px-3 py-2.5 text-left w-28">From</th>'
                 . '<th class="px-3 py-2.5 text-left">To (amount)</th>'
                 . '</tr></thead>';
    $trOpen      = '<tr class="border-b border-gray-100 dark:border-gray-700/50 '
                 . 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">';
    $trClose     = '</tr>';
    $tdNum       = fn($n) => '<td class="px-3 py-2 text-right tabular-nums text-gray-400 dark:text-gray-500">' . $n . '</td>';
    $tdTxid      = fn($v) => '<td class="px-3 py-2" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                           . '<span class="txid font-mono text-gray-500 dark:text-gray-400 cursor-pointer">' . $v . '</span></td>';
    $tdNum2      = fn($v) => '<td class="px-3 py-2 text-right tabular-nums font-mono text-gray-600 dark:text-gray-300">' . $v . '</td>';
    $tdFromOpen  = '<td class="px-3 py-2 text-gray-500 dark:text-gray-400">';
    $tdFromClose = '</td>';
    $tdToOpen    = '<td class="px-3 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">';
    $tdToClose   = '</td>';
    $spanAddr    = fn($a) => '<span class="font-mono">' . $a . '</span>';
    $rawColspan  = 6;
    $rawOpen     = '<tr class="raw" style="display:none;"><td colspan="6" class="px-3 py-2"><div class="json">';
    $rawClose    = '</div></td></tr>';
    $tooMany     = fn() => '<tr><td colspan="6" class="px-3 py-2 text-gray-400 dark:text-gray-500">Too many transactions to display...</td></tr>';
    $stakeHdr    = '<tr class="bg-indigo-50/50 dark:bg-indigo-900/10">'
                 . '<th colspan="6" class="px-3 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400">Stake Transactions</th></tr>';
}

echo $tblOpen . $thRow . '<tbody>';

$ntx = 0;
foreach ($block['tx'] as $txhash) {
    $ntx++;
    $tx = $remote->getrawtransaction($txhash, 1);
    if (!$tx && ($ntx == 1 || $txid == $txhash)) {
        $tx = $remote->gettransaction($txhash);
        if ($tx && isset($tx['hex'])) {
            $hex = $tx['hex'];
            $tx  = $remote->decoderawtransaction($hex);
            $tx['hex'] = $hex;
        } else { continue; }
    }
    if (!$tx) continue;

    $valuetx = 0;
    foreach ($tx['vout'] as $vout) $valuetx += $vout['value'];
    $size = strlen($tx['hex']) / 2;

    // ── From cell (buffered) ──────────────────────────────────────────────────
    ob_start();
    $segwit = false;
    foreach ($tx['vin'] as $vin) {
        if (isset($vin['coinbase'])) echo 'Generation';
        if (isset($vin['addr'])) echo $spanAddr(Html::encode($vin['addr'])); // Ethereum sender
        if (isset($vin['txinwitness'])) $segwit = true;
    }
    if ($segwit) echo '&nbsp;<img src="/images/ui/segwit.png" height="8px" title="segwit"/>';
    $fromContent = ob_get_clean();

    // ── To cell (buffered) ────────────────────────────────────────────────────
    ob_start();
    $nvout = count($tx['vout']);
    if ($nvout > 500) {
        echo "Too many addresses ($nvout)";
    } else {
        foreach ($tx['vout'] as $vout) {
            $value = $vout['value'];
            if ($value == 0) continue;
            if (isset($vout['scriptPubKey']['addresses'][0]))
                echo $spanAddr(Html::encode($vout['scriptPubKey']['addresses'][0])) . " ($value)";
            else echo "($value)";
            echo '<br>';
        }
    }
    $toContent = ob_get_clean();

    echo $trOpen
       . $tdNum($ntx)
       . $tdTxid($tx['txid'])
       . $tdNum2($size)
       . $tdNum2($valuetx)
       . $tdFromOpen . $fromContent . $tdFromClose
       . $tdToOpen   . $toContent   . $tdToClose
       . $trClose;

    // Raw JSON toggle row
    unset($tx['hex']);
    echo $rawOpen
       . ($nvout > 500 ? 'truncated' : colorizeJson(json_encode($tx, 128)))
       . $rawClose;

    if ($ntx > 100) {
        echo $tooMany();
        break;
    }
}

// ── Decred stake ──────────────────────────────────────────────────────────────
if ($coin->rpcencoding === 'DCR' && isset($block['stx'])) {
    echo $stakeHdr;
    $ntx = 0;
    foreach ($block['stx'] as $txhash) {
        $ntx++;
        $stx = $remote->getrawtransaction($txhash, 1);
        if (!$stx) continue;
        $valuetx = 0;
        foreach ($stx['vout'] as $vout) $valuetx += $vout['value'];
        $size = strlen($stx['hex']) / 2;

        ob_start();
        if (isset($stx['vout'][0]['scriptPubKey'])
            && $conv->arraySafeVal($stx['vout'][0]['scriptPubKey'], 'type') === 'stakesubmission') {
            echo 'Ticket';
        } else {
            foreach ($stx['vin'] as $vin) {
                if ($conv->arraySafeVal($vin, 'blockheight') > 0) {
                    echo $coin->createExplorerLink($vin['blockheight'], ['height' => $vin['blockheight']]);
                    echo '<br/>';
                }
            }
        }
        $stakeFrom = ob_get_clean();

        ob_start();
        foreach ($stx['vout'] as $vout) {
            $value = $vout['value']; if ($value == 0) continue;
            if (isset($vout['scriptPubKey']['addresses'][0]))
                echo $spanAddr(Html::encode($vout['scriptPubKey']['addresses'][0])) . " ($value)";
            else echo "($value)";
            echo '<br/>';
        }
        $stakeTo = ob_get_clean();

        echo $trOpen
           . $tdNum($ntx)
           . $tdTxid($stx['txid'])
           . $tdNum2($size)
           . $tdNum2($valuetx)
           . $tdFromOpen . $stakeFrom . $tdFromClose
           . $tdToOpen   . $stakeTo   . $tdToClose
           . $trClose;

        unset($stx['hex']);
        echo $rawOpen . colorizeJson(json_encode($stx, 128)) . $rawClose;
    }
}

echo '</tbody>' . $tblClose;
?>

<?php if ($isLegacy): ?>
    <!-- search form is rendered inside the legacy transaction card footer -->
<?php elseif (!$isTailwind): ?>
<div class="mt-3">
<form action="<?= Html::encode($actionUrl) ?>" method="POST"
      class="d-flex align-items-center gap-2 flex-wrap">
    <input type="text" name="height" class="form-control form-control-sm"
           placeholder="Block height" style="width:100px;">
    <input type="text" name="txid"   class="form-control form-control-sm"
           placeholder="Transaction hash" style="width:400px;">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
</form>
</div>
<?php else: ?>
<form action="<?= Html::encode($actionUrl) ?>" method="POST"
      class="mt-4 flex items-center gap-2 flex-wrap">
    <input type="text" name="height"
           class="w-24 text-sm rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5
                  bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                  focus:outline-none focus:ring-2 focus:ring-indigo-500"
           placeholder="Block height">
    <input type="text" name="txid"
           class="flex-1 min-w-48 text-sm rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5
                  bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                  focus:outline-none focus:ring-2 focus:ring-indigo-500"
           placeholder="Transaction hash">
    <button type="submit"
            class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
                   text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700
                   hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors cursor-pointer">
        Search
    </button>
</form>
<?php endif ?>
