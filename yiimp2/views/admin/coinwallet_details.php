<?php

/** @var yii\web\View $this */
/** @var app\models\Coins        $coin      */
/** @var string                  $balance   */
/** @var string                  $reserved1 */
/** @var string                  $owed      */
/** @var string                  $owed_btc  */
/** @var string|null             $reserved2 */
/** @var app\models\Markets[]    $markets   */
/** @var app\models\Bookmarks[]  $bookmarks */
/** @var string                  $symbol    */

use yii\helpers\Html;
use yii\helpers\Json;
use app\components\rpc\WalletRPC;
use app\services\CoinService;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

$PoS = ($coin->algo === 'PoS');
$DCR = ($coin->rpcencoding === 'DCR' || $coin->getOfficialSymbol() === 'DCR');
$DGB = ($coin->rpcencoding === 'DGB' || $coin->getOfficialSymbol() === 'DGB');
$ETH = ($coin->rpcencoding === 'GETH');

$remote = new WalletRPC($coin);

// ── Transaction query params ──────────────────────────────────────────────────
$list_since = (int) $conv->arraySafeVal($_GET, 'since', time() - 7 * 86400);
$maxrows    = max(250, min(2500, (int) $conv->arraySafeVal($_GET, 'rows', 500)));

// ── RPC info ──────────────────────────────────────────────────────────────────
$info = $remote->getinfo();
if (!empty($info)) {
    $stake = $conv->arraySafeVal($info, 'stake', '');
    if ($stake !== '') $PoS = true;
}
$rpcAvailable = !empty($info) && isset($info['balance']);

// DCR stake info
$dcrStake = $dcrTickets = $dcrTicketPrice = null;
if ($DCR) {
    $dcrStake = 0;
    $balances = $remote->getbalance('*', 0);
    if (isset($balances['balances'])) {
        foreach ($balances['balances'] as $accb)
            $dcrStake += $conv->arraySafeVal($accb, 'lockedbytickets', 0);
    }
    $stakeinfo      = $remote->getstakeinfo();
    $dcrTicketPrice = $conv->arraySafeVal($stakeinfo, 'difficulty');
    $dcrTickets     = $conv->arraySafeVal($stakeinfo, 'live', 0)
                    + $conv->arraySafeVal($stakeinfo, 'immature', 0);
}

// ── Category row / badge helpers ──────────────────────────────────────────────
$catRowCls = function (string $cat) use ($isTailwind, $isLegacy): string {
    if ($isLegacy) return 'ssrow ' . $cat;
    if (!$isTailwind) return [
        'orphan'    => 'table-danger',
        'immature'  => 'table-warning',
        'generate'  => 'table-success',
        'generated' => 'table-success',
        'receive'   => '',
        'send'      => 'table-light',
        'ticket'    => 'table-info',
        'stake'     => 'table-light',
    ][$cat] ?? '';
    return [
        'orphan'    => 'bg-red-50/60 dark:bg-red-900/10',
        'immature'  => 'bg-amber-50/60 dark:bg-amber-900/10',
        'generate'  => 'bg-green-50/60 dark:bg-green-900/10',
        'generated' => 'bg-green-50/60 dark:bg-green-900/10',
        'receive'   => '',
        'send'      => '',
        'ticket'    => 'bg-cyan-50/60 dark:bg-cyan-900/10',
        'stake'     => '',
    ][$cat] ?? '';
};

$catBadge = function (string $cat, string $title = '') use ($isTailwind, $isLegacy): string {
    $titleAttr = $title ? ' title="' . Html::encode($title) . '"' : '';
    if ($isLegacy) {
        $catCls = preg_replace('/[^a-z0-9_-]/i', '', $cat);
        return '<span class="cwd-cat cwd-cat-' . $catCls . '"' . $titleAttr . '>' . Html::encode($cat) . '</span>';
    }
    if (!$isTailwind) {
        $bs = ['orphan' => 'bg-danger', 'immature' => 'bg-warning text-dark',
               'generate' => 'bg-success', 'generated' => 'bg-success',
               'receive' => 'bg-primary', 'send' => 'bg-secondary',
               'ticket' => 'bg-info text-dark', 'stake' => 'bg-light text-dark border'][$cat] ?? 'bg-secondary';
        return '<span class="badge ' . $bs . '"' . $titleAttr . '>' . Html::encode($cat) . '</span>';
    }
    $tw = ['orphan' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
           'immature' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
           'generate' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
           'generated'=> 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
           'receive'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
           'send'     => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
           'ticket'   => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400',
           'stake'    => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'][$cat]
        ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ' . $tw . '"' . $titleAttr . '>' . Html::encode($cat) . '</span>';
};

if ($isLegacy) {
    // Styles live inside the AJAX fragment: #main_results is replaced every 30s.
    echo <<<'HTML'
<div id="cwd">
<style>
#cwd { color: #334155; font-size: 11px; }
#cwd a { color: #2c5aa0; text-decoration: none; }
#cwd a:hover { color: #1f467f; text-decoration: underline; }
#cwd a.red, #cwd a.red:visited { color: #b91c1c; }

/* Balance strip */
#cwd .cwd-balance {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 9px 12px; margin-bottom: 12px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
#cwd .cwd-bal { display: inline-flex; align-items: baseline; gap: 6px; padding-right: 12px; border-right: 1px solid #eef2f7; }
#cwd .cwd-bal:last-child { border-right: 0; padding-right: 0; }
#cwd .cwd-bal em {
    font-style: normal; font-size: 9px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #8a94a6; white-space: nowrap;
}
#cwd .cwd-bal b { color: #26385a; font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums; }
#cwd .cwd-bal small { color: #98a3b5; font-size: 9px; white-space: nowrap; }

/* Cards */
#cwd .cwd-card {
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; margin-bottom: 12px;
}
#cwd .cwd-card-head {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 40px; padding: 9px 13px;
    background: #f8fafc; border-bottom: 1px solid #e8eef6;
}
#cwd .cwd-title { color: #26385a; font-size: 13px; font-weight: 800; }
#cwd .cwd-sub { color: #8a94a6; font-size: 10px; font-weight: 500; }
#cwd .cwd-scope {
    display: inline-block; border: 1px solid #d7e5ff; background: #eef4ff; color: #2c5aa0;
    border-radius: 999px; padding: 2px 9px; line-height: 15px;
    font-size: 9px; font-weight: 800; white-space: nowrap;
}
#cwd .cwd-scope.muted { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; }
#cwd .cwd-scroll { overflow: auto; }
#cwd #markets { max-height: 190px; }
#cwd #transactions { max-height: 380px; }
#cwd #sums { max-height: 420px; }

/* Tables */
#cwd .cwd-table { width: 100%; border-collapse: collapse; font-size: 11px; }
#cwd .cwd-table th {
    position: sticky; top: 0; z-index: 1; background: #fff !important;
    padding: 8px 9px !important; color: #8a94a6;
    font-size: 9px !important; font-weight: 800 !important;
    letter-spacing: .05em; text-transform: uppercase;
    border-bottom: 1px solid #e2e8f0 !important; white-space: nowrap;
}
#cwd .cwd-table td {
    padding: 7px 9px !important; border-bottom: 1px solid #eef2f7 !important;
    vertical-align: middle;
}
#cwd .cwd-table tbody tr:last-child td { border-bottom: 0 !important; }
#cwd .cwd-table tbody tr:hover td { background: #f7faff !important; }
#cwd .cwd-table tbody tr.disabled td,
#cwd .cwd-table tbody tr.orphan td { background: #fff8f8 !important; }
#cwd .cwd-table tbody tr.disabled td:first-child,
#cwd .cwd-table tbody tr.orphan td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
#cwd .cwd-table td[align="right"] { text-align: right; }
#cwd .cwd-table td[align="center"] { text-align: center; }
#cwd #markets .cwd-table { min-width: 920px; }
#cwd #infos .cwd-table { min-width: 980px; }
#cwd #transactions .cwd-table { min-width: 720px; }
#cwd #sums .cwd-table { min-width: 240px; }
#cwd #infos img { border: 1px solid #e8eef6; border-radius: 6px; padding: 1px; background: #fff; }

/* Category pills */
#cwd .cwd-cat {
    display: inline-block; border-radius: 999px; padding: 2px 8px;
    border: 1px solid #e2e8f0; background: #f1f5f9; color: #64748b;
    font-size: 9px; font-weight: 800; line-height: 14px;
    text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
#cwd .cwd-cat-orphan { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
#cwd .cwd-cat-immature { background: #fff5e6; border-color: #fde3b8; color: #b45309; }
#cwd .cwd-cat-generate, #cwd .cwd-cat-generated { background: #e8f7ee; border-color: #ccebd8; color: #187d46; }
#cwd .cwd-cat-receive { background: #eef4ff; border-color: #d7e5ff; color: #2c5aa0; }
#cwd .cwd-cat-send, #cwd .cwd-cat-spent { background: #f1f5f9; border-color: #e2e8f0; color: #64748b; }
#cwd .cwd-cat-ticket { background: #ecfeff; border-color: #cffafe; color: #0e7490; }
#cwd .cwd-cat-stake, #cwd .cwd-cat-unlock { background: #f5f3ff; border-color: #e9d5ff; color: #7c3aed; }

/* Transactions + daily sums */
#cwd .cwd-split { display: grid; grid-template-columns: minmax(0,1fr) 280px; gap: 12px; align-items: start; }
#cwd .cwd-main-col, #cwd .cwd-side-col { min-width: 0; }
#cwd .loadfooter { background: #fcfdff; border-top: 1px solid #eef2f7; padding: 8px 13px; }
#cwd .loadfooter a {
    display: inline-block; border: 1px solid #dfe5ec; background: #fafbfc; color: #64748b;
    border-radius: 999px; padding: 3px 10px; font-size: 10px; font-weight: 700;
    line-height: 1.6; text-decoration: none;
}
#cwd .loadfooter a:hover { border-color: #b9d4ff; color: #2c5aa0; background: #f7faff; }
#cwd .cwd-empty { color: #98a3b5; text-align: center; padding: 14px 9px; }
@media (max-width: 1100px) { #cwd .cwd-split { grid-template-columns: 1fr; } }
</style>
HTML;
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Balance summary
// ══════════════════════════════════════════════════════════════════════════════
$ownedFmt = $conv->bitcoinvaluetoa($coin->available);
$owedLink = Html::a($owed, '/admin/earning?id=' . $coin->id);
$clrLink  = Html::a($reserved1, '/admin/payments?id=' . $coin->id);

if ($isLegacy) {
    echo '<div class="cwd-balance">';
    if (YIIMP_ALLOW_EXCHANGE && $reserved2 !== null)
        echo '<span class="cwd-bal"><em>Earnings</em><b>' . Html::encode($reserved2) . ' BTC</b></span>';
    echo '<span class="cwd-bal"><em>Balance (db)</em><b>' . Html::encode($balance) . ' ' . Html::encode($symbol) . '</b></span>';
    echo '<span class="cwd-bal"><em>Owned</em><b>' . Html::encode($ownedFmt) . ' ' . Html::encode($symbol) . '</b></span>';
    echo '<span class="cwd-bal"><em>Owed</em><b>' . $owedLink . ' ' . Html::encode($symbol) . '</b><small>(' . Html::encode($owed_btc) . ' BTC)</small></span>';
    echo '<span class="cwd-bal"><em>Cleared</em><b>' . $clrLink . ' ' . Html::encode($symbol) . '</b></span>';
    echo '</div>';
} elseif (!$isTailwind) {
    echo '<div class="d-flex flex-wrap gap-3 mb-3 small">';
    if (YIIMP_ALLOW_EXCHANGE && $reserved2 !== null)
        echo '<span class="text-muted">Earnings <b class="text-dark">' . Html::encode($reserved2) . ' BTC</b></span>';
    echo '<span class="text-muted">Balance <b class="font-monospace">' . Html::encode($balance) . ' ' . Html::encode($symbol) . '</b></span>';
    echo '<span class="text-muted">Owned <b class="font-monospace">' . Html::encode($ownedFmt) . ' ' . Html::encode($symbol) . '</b></span>';
    echo '<span class="text-muted">Owed ' . $owedLink . ' <b class="font-monospace">' . Html::encode($symbol) . '</b>'
       . ' <span class="text-secondary">(' . Html::encode($owed_btc) . ' BTC)</span></span>';
    echo '<span class="text-muted">Cleared ' . $clrLink . ' <b class="font-monospace">' . Html::encode($symbol) . '</b></span>';
    echo '</div>';
} else {
    echo '<div class="flex flex-wrap gap-4 mb-4 text-xs">';
    $pills = [];
    if (YIIMP_ALLOW_EXCHANGE && $reserved2 !== null)
        $pills[] = ['Earnings', Html::encode($reserved2) . ' BTC', ''];
    $pills[] = ['Balance',  Html::encode($balance)  . ' ' . Html::encode($symbol), ''];
    $pills[] = ['Owned',    Html::encode($ownedFmt) . ' ' . Html::encode($symbol), ''];
    $pills[] = ['Owed',     $owedLink . ' ' . Html::encode($symbol) . ' <span class="text-gray-400">(' . Html::encode($owed_btc) . ' BTC)</span>', ''];
    $pills[] = ['Cleared',  $clrLink  . ' ' . Html::encode($symbol), ''];
    foreach ($pills as [$label, $val]) {
        echo '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2">';
        echo '<div class="text-gray-400 dark:text-gray-500 mb-0.5">' . $label . '</div>';
        echo '<div class="font-mono font-semibold text-gray-800 dark:text-gray-200">' . $val . '</div>';
        echo '</div>';
    }
    echo '</div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — Markets + Bookmarks
// ══════════════════════════════════════════════════════════════════════════════
$bookmarkAdd = Html::a('+', '/admin/bookmark-add?id=' . $coin->id, ['title' => 'Add a bookmark']);

$mktHeaders = ['Market', 'Bid', 'Ask', 'Deposit / Send', 'Balance', 'Locked', 'Sent', 'Traded', 'Late', 'Message', $bookmarkAdd . ' Actions'];

if ($isLegacy) {
    echo '<div class="cwd-card">';
    echo '<div class="cwd-card-head"><span><span class="cwd-title">Markets &amp; Bookmarks</span>'
       . '<span class="cwd-sub"> · exchange routes · deposit addresses</span></span>'
       . '<span class="cwd-scope">' . count($markets) . (count($markets) === 1 ? ' market' : ' markets')
       . ' · ' . count($bookmarks) . ' bookmarks</span></div>';
    echo '<div id="markets" class="cwd-scroll"><table class="dataGrid cwd-table"><thead><tr>';
    foreach ($mktHeaders as $h) echo '<th>' . $h . '</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3">';
    echo '<div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-arrow-left-right text-secondary"></i>';
    echo '<strong class="small">Markets &amp; Bookmarks</strong>';
    echo '<span class="badge bg-secondary ms-1">' . count($markets) . '</span>';
    echo '<a href="/admin/bookmark-add?id=' . $coin->id . '" class="btn btn-sm btn-outline-secondary ms-auto" title="Add bookmark"><i class="bi bi-bookmark-plus"></i></a>';
    echo '</div><div class="card-body p-0">';
    echo '<div class="overflow-auto" style="max-height:200px;">';
    echo '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>';
    foreach (['Market', 'Bid', 'Ask', 'Deposit / Send', 'Bal.', 'Locked', 'Sent', 'Traded', 'Late', 'Msg', 'Actions'] as $h)
        echo '<th class="small text-nowrap">' . $h . '</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="arrow-left-right" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Markets &amp; Bookmarks</span>';
    echo '<a href="/admin/bookmark-add?id=' . $coin->id . '" class="ml-auto inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors" title="Add bookmark"><i data-lucide="bookmark-plus" class="w-3.5 h-3.5"></i> Add</a>';
    echo '</div><div class="overflow-x-auto" style="max-height:200px;">';
    echo '<table class="w-full text-xs"><thead>';
    echo '<tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider sticky top-0">';
    foreach (['Market', 'Bid', 'Ask', 'Deposit / Send', 'Bal.', 'Locked', 'Sent', 'Traded', 'Late', 'Msg', 'Actions'] as $h)
        echo '<th class="px-3 py-2 text-left whitespace-nowrap">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody>';
}

// Market rows
foreach ($markets as $market) {
    $marketurl = Yii::$app->YiimpUtils->getMarketUrl($coin, $market->name);
    $price     = $conv->bitcoinvaluetoa(Yii::$app->YiimpUtils->marketBtcPrice($market, 'price'));
    $price2    = $conv->bitcoinvaluetoa(Yii::$app->YiimpUtils->marketBtcPrice($market, 'price2'));
    $updated   = 'last updated: ' . strip_tags($conv->datetoa2($market->pricetime));
    $balFmt    = $market->balance > 0 ? $conv->bitcoinvaluetoa($market->balance) : '';
    $ontrade   = $market->ontrade > 0 ? $conv->bitcoinvaluetoa($market->ontrade) : '';
    $sent      = $conv->datetoa2($market->lastsent);
    $traded    = $conv->datetoa2($market->lasttraded);
    $late      = $market->lastsent > $market->lasttraded && $market->lasttraded;
    $disabled  = (bool) $market->disabled;

    $depositCell = '';
    if (!empty($market->deposit_address)) {
        $name = Json::encode($market->name);
        $addr = Json::encode($market->deposit_address);
        $sendLabel = YIIMP_ALLOW_EXCHANGE ? 'sell' : 'send';
        $depositCell .= Html::a($sendLabel, 'javascript:;', ['onclick' => "return showSellAmountDialog($name, $addr, {$market->id});"]);
        $depositCell .= ' ' . Html::encode($market->deposit_address);
    }
    $depositCell .= ' <a href="/admin/market/update?id=' . $market->id . '">edit</a>';

    if ($isLegacy) {
        $actCell = $disabled
            ? '<a href="/admin/market/enable?id=' . $market->id . '&amp;en=1" title="Enable">enable</a>'
            : '<a href="/admin/market/enable?id=' . $market->id . '&amp;en=0" title="Disable">disable</a>';
        $actCell .= ' &nbsp;<a href="/admin/market/delete?id=' . $market->id . '" title="Remove" class="red">delete</a>';
    } elseif (!$isTailwind) {
        $actCell = $disabled
            ? '<a href="/admin/market/enable?id=' . $market->id . '&en=1" class="btn btn-sm btn-outline-success" title="Enable market"><i class="bi bi-toggle-off"></i></a>'
            : '<a href="/admin/market/enable?id=' . $market->id . '&en=0" class="btn btn-sm btn-outline-warning" title="Disable market"><i class="bi bi-toggle-on"></i></a>';
        $actCell .= ' <a href="/admin/market/delete?id=' . $market->id . '" class="btn btn-sm btn-outline-danger" title="Delete market"><i class="bi bi-trash"></i></a>';
    } else {
        $actCell = $disabled
            ? '<a href="/admin/market/enable?id=' . $market->id . '&en=1" class="inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" title="Enable market"><i data-lucide="toggle-left" class="w-3.5 h-3.5"></i></a>'
            : '<a href="/admin/market/enable?id=' . $market->id . '&en=0" class="inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors" title="Disable market"><i data-lucide="toggle-right" class="w-3.5 h-3.5"></i></a>';
        $actCell .= ' <a href="/admin/market/delete?id=' . $market->id . '" class="inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Delete market"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a>';
    }

    if ($isLegacy) {
        $rowCls = 'ssrow' . ($disabled ? ' disabled' : '');
        echo "<tr class='{$rowCls}'>";
        echo '<td><b><a href="' . Html::encode($marketurl) . '" target="_blank">' . Html::encode($market->name) . '</a></b></td>';
        echo '<td title="' . Html::encode($updated) . '">' . $price  . '</td>';
        echo '<td title="' . Html::encode($updated) . '">' . $price2 . '</td>';
        echo '<td style="max-width:800px;text-overflow:ellipsis;overflow:hidden;">' . $depositCell . '</td>';
        echo '<td title="' . Html::encode($updated) . '">' . $balFmt  . '</td>';
        echo '<td title="' . Html::encode($updated) . '">' . $ontrade . '</td>';
        echo '<td>' . ($sent   ? $sent   . ' ago' : '') . '</td>';
        echo '<td>' . ($traded ? $traded . ' ago' : '') . '</td>';
        echo '<td>' . ($late ? 'late' : '') . '</td>';
        echo '<td align="center">' . Html::encode($market->message ?? '') . '</td>';
        echo '<td align="right">' . $actCell . '</td>';
        echo '</tr>';
    } elseif (!$isTailwind) {
        $rowCls = $disabled ? 'table-danger opacity-75' : '';
        echo "<tr class='{$rowCls}'>";
        echo '<td class="small fw-bold text-nowrap"><a href="' . Html::encode($marketurl) . '" target="_blank">' . Html::encode($market->name) . '</a>';
        if ($disabled) echo ' <span class="badge bg-danger">off</span>';
        echo '</td>';
        echo '<td class="small font-monospace" title="' . Html::encode($updated) . '">' . $price  . '</td>';
        echo '<td class="small font-monospace" title="' . Html::encode($updated) . '">' . $price2 . '</td>';
        echo '<td class="small" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $depositCell . '</td>';
        echo '<td class="small font-monospace" title="' . Html::encode($updated) . '">' . $balFmt  . '</td>';
        echo '<td class="small font-monospace" title="' . Html::encode($updated) . '">' . $ontrade . '</td>';
        echo '<td class="small text-muted text-nowrap">' . ($sent   ? $sent   . ' ago' : '') . '</td>';
        echo '<td class="small text-muted text-nowrap">' . ($traded ? $traded . ' ago' : '') . '</td>';
        echo '<td>' . ($late ? '<span class="badge bg-warning text-dark">late</span>' : '') . '</td>';
        echo '<td class="small">' . Html::encode($market->message ?? '') . '</td>';
        echo '<td class="small text-nowrap">' . $actCell . '</td>';
        echo '</tr>';
    } else {
        $rowCls = $disabled
            ? 'bg-red-50/40 dark:bg-red-900/10 opacity-75'
            : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors';
        echo "<tr class='{$rowCls} border-b border-gray-100 dark:border-gray-700/50'>";
        echo '<td class="px-3 py-1.5 font-medium text-nowrap">';
        echo '<a href="' . Html::encode($marketurl) . '" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">' . Html::encode($market->name) . '</a>';
        if ($disabled) echo ' <span class="inline-flex items-center px-1 py-0.5 rounded text-xs bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400">off</span>';
        echo '</td>';
        echo '<td class="px-3 py-1.5 font-mono text-gray-600 dark:text-gray-300" title="' . Html::encode($updated) . '">' . $price  . '</td>';
        echo '<td class="px-3 py-1.5 font-mono text-gray-600 dark:text-gray-300" title="' . Html::encode($updated) . '">' . $price2 . '</td>';
        echo '<td class="px-3 py-1.5 max-w-xs overflow-hidden whitespace-nowrap text-ellipsis">' . $depositCell . '</td>';
        echo '<td class="px-3 py-1.5 font-mono text-gray-600 dark:text-gray-300" title="' . Html::encode($updated) . '">' . $balFmt  . '</td>';
        echo '<td class="px-3 py-1.5 font-mono text-gray-500 dark:text-gray-400" title="' . Html::encode($updated) . '">' . $ontrade . '</td>';
        echo '<td class="px-3 py-1.5 text-gray-400 dark:text-gray-500 whitespace-nowrap">' . ($sent   ? $sent   . ' ago' : '') . '</td>';
        echo '<td class="px-3 py-1.5 text-gray-400 dark:text-gray-500 whitespace-nowrap">' . ($traded ? $traded . ' ago' : '') . '</td>';
        echo '<td class="px-3 py-1.5">' . ($late ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">late</span>' : '') . '</td>';
        echo '<td class="px-3 py-1.5 text-gray-500 dark:text-gray-400">' . Html::encode($market->message ?? '') . '</td>';
        echo '<td class="px-3 py-1.5 whitespace-nowrap">' . $actCell . '</td>';
        echo '</tr>';
    }
}

// Bookmark rows
foreach ($bookmarks as $bookmark) {
    $name = Json::encode($bookmark->label);
    $addr = Json::encode($bookmark->address ?? '');
    $sent = !empty($bookmark->lastused) ? $conv->datetoa2($bookmark->lastused) . ' ago' : '';
    $bkDepositCell = !empty($bookmark->address)
        ? Html::a('send', 'javascript:;', ['onclick' => "return showSellAmountDialog($name, $addr, 0, {$bookmark->id});"]) . ' ' . Html::encode($bookmark->address)
        : '';
    $bkDepositCell .= ' <a href="/admin/bookmark-edit?id=' . $bookmark->id . '">edit</a>';
    if ($isLegacy) {
        $bkActCell = '<a href="/admin/bookmark-del?id=' . $bookmark->id . '" class="red">delete</a>';
    } elseif (!$isTailwind) {
        $bkActCell = '<a href="/admin/bookmark-del?id=' . $bookmark->id . '" class="btn btn-sm btn-outline-danger" title="Delete bookmark"><i class="bi bi-trash"></i></a>';
    } else {
        $bkActCell = '<a href="/admin/bookmark-del?id=' . $bookmark->id . '" class="inline-flex p-1.5 rounded text-gray-400 dark:text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Delete bookmark"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a>';
    }

    if ($isLegacy) {
        echo '<tr class="ssrow bookmark"><td><b>' . Html::encode($bookmark->label) . '</b></td>';
        echo '<td></td><td></td><td>' . $bkDepositCell . '</td>';
        echo '<td></td><td></td><td>' . $sent . '</td><td></td><td></td><td></td>';
        echo '<td align="right">' . $bkActCell . '</td></tr>';
    } elseif (!$isTailwind) {
        echo '<tr class="table-light"><td class="small fw-bold">' . Html::encode($bookmark->label) . ' <span class="badge bg-light text-dark border">bkm</span></td>';
        echo '<td></td><td></td><td class="small">' . $bkDepositCell . '</td>';
        echo '<td></td><td></td><td class="small text-muted">' . $sent . '</td><td></td><td></td><td></td>';
        echo '<td class="small">' . $bkActCell . '</td></tr>';
    } else {
        echo '<tr class="bg-gray-50/40 dark:bg-gray-700/20 border-b border-gray-100 dark:border-gray-700/50">';
        echo '<td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">' . Html::encode($bookmark->label);
        echo ' <span class="inline-flex items-center px-1 py-0.5 rounded text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">bkm</span></td>';
        echo '<td></td><td></td><td class="px-3 py-1.5">' . $bkDepositCell . '</td>';
        echo '<td></td><td></td><td class="px-3 py-1.5 text-gray-400">' . $sent . '</td><td></td><td></td><td></td>';
        echo '<td class="px-3 py-1.5">' . $bkActCell . '</td></tr>';
    }
}

if ($isLegacy) echo '</tbody></table></div></div>';
elseif (!$isTailwind) echo '</tbody></table></div></div></div>';
else echo '</tbody></table></div></div>';

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — Coin info table
// ══════════════════════════════════════════════════════════════════════════════
$extraCols = ($PoS || $DCR ? 1 : 0) + ($DCR ? 1 : 0);

if ($isLegacy) {
    echo '<div class="cwd-card">';
    echo '<div class="cwd-card-head"><span><span class="cwd-title">Coin Info</span>'
       . '<span class="cwd-sub"> · ' . Html::encode($coin->symbol) . ' · ' . Html::encode($coin->algo) . '</span></span>'
       . '<span class="cwd-scope' . ($rpcAvailable ? '' : ' muted') . '">'
       . ($rpcAvailable ? 'RPC online' : 'RPC offline') . '</span></div>';
    echo '<div class="cwd-scroll"><table id="infos" class="dataGrid cwd-table"><thead><tr>';
    foreach (['', '', 'Name', 'Symbol', 'Algo', 'Difficulty', 'Blocks', 'Balance', 'BTC'] as $h)
        echo '<th>' . $h . '</th>';
    if ($PoS || $DCR) echo '<th>Stake</th>';
    if ($DCR)         echo '<th>Ticket price</th>';
    echo '<th>Conns</th><th>Price</th><th>Reward</th><th>Index *</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-info-circle text-secondary"></i><strong class="small">Coin Info</strong>';
    if (!$rpcAvailable) echo ' <span class="badge bg-danger ms-1">RPC offline</span>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table id="infos" class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>';
    foreach (['', 'En', 'Name', 'Symbol', 'Algo', 'Diff.', 'Blocks', 'Balance', 'BTC'] as $h)
        echo '<th class="small">' . $h . '</th>';
    if ($PoS || $DCR) echo '<th class="small">Stake</th>';
    if ($DCR)         echo '<th class="small">Ticket$</th>';
    echo '<th class="small">Conns</th><th class="small">Price</th><th class="small">Reward</th><th class="small">Index</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="info" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Coin Info</span>';
    if (!$rpcAvailable) echo ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 ml-1">RPC offline</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table id="infos" class="w-full text-xs"><thead>';
    echo '<tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
    foreach (['', 'En', 'Name', 'Symbol', 'Algo', 'Diff', 'Blocks', 'Balance', 'BTC'] as $h)
        echo '<th class="px-3 py-2.5">' . Html::encode($h) . '</th>';
    if ($PoS || $DCR) echo '<th class="px-3 py-2.5">Stake</th>';
    if ($DCR)         echo '<th class="px-3 py-2.5">Ticket$</th>';
    echo '<th class="px-3 py-2.5">Conns</th><th class="px-3 py-2.5">Price</th><th class="px-3 py-2.5">Reward</th><th class="px-3 py-2.5">Index</th>';
    echo '</tr></thead><tbody>';
}

// Coin info row
$rpcErr  = Html::encode($remote->error ?: 'RPC not configured');
$coinImg = "<img src='" . Html::encode($coin->image) . "' width='24'>";
$enBadge = $coin->enable
    ? ($isLegacy ? '[ + ]' : ($isTailwind ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">on</span>' : '<span class="badge bg-success">on</span>'))
    : ($isLegacy ? '[&nbsp;&nbsp;&nbsp;&nbsp;]' : ($isTailwind ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">off</span>' : '<span class="badge bg-secondary">off</span>'));
$diffFmt  = $conv->round_difficulty($coin->difficulty);
$priceFmt = $conv->bitcoinvaluetoa($coin->price);
$index    = $coin->difficulty ? round($coin->reward * $coin->price / $coin->difficulty * 10000, 3) : '';

$tdR = $isLegacy ? '' : ($isTailwind ? 'px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300' : 'text-end small font-monospace');
$tdL = $isLegacy ? '' : ($isTailwind ? 'px-3 py-2 text-gray-700 dark:text-gray-200' : 'small');

echo $isLegacy ? "<tr class='ssrow'>" : "<tr>";
echo "<td class='{$tdL}'>{$coinImg}</td>";
echo "<td class='{$tdL}'>{$enBadge}</td>";
echo "<td class='{$tdL}'><b>" . Html::a(Html::encode($coin->name), Html::encode($coin->blockUrl)) . "</b></td>";
echo "<td class='{$tdL}'><b>" . Html::encode($coin->symbol) . "</b></td>";
echo "<td class='{$tdL}'>" . Html::a(Html::encode($coin->algo), '/site/gomining?algo=' . urlencode($coin->algo)) . "</td>";
echo "<td class='{$tdR}'>{$diffFmt}</td>";

if (!$rpcAvailable) {
    $dbBalance = (float) ($coin->balance ?? 0);
    echo "<td class='{$tdR}' title='{$rpcErr}'>—</td>";
    echo "<td class='{$tdR}'>" . $conv->altcoinvaluetoa($dbBalance) . "</td>";
    echo "<td class='{$tdR}'>" . $conv->bitcoinvaluetoa($dbBalance * $coin->price) . "</td>";
    if ($PoS || $DCR) echo "<td class='{$tdR}'>—</td>";
    if ($DCR)         echo "<td class='{$tdR}'>—</td>";
    echo "<td class='{$tdR}' title='{$rpcErr}'>—</td>";
    echo "<td class='{$tdR}'>{$priceFmt}</td>";
    echo "<td class='{$tdR}'>" . Html::encode((string) $coin->reward) . "</td>";
    echo "<td class='{$tdR}'>" . Html::encode((string) $index) . "</td>";
} else {
    $errors  = $info['errors'] ?? '';
    $balance = $info['balance'] ?? '';
    $btc     = $conv->bitcoinvaluetoa($balance * $coin->price);
    $conns   = isset($info['connections'])
        ? Html::a(Html::encode((string) $info['connections']), '/admin/coinpeers?id=' . $coin->id)
        : '';
    $blocks  = $info['blocks'] ?? '';

    $zbalance = null;
    if (!empty($coin->wallet_zaddress) && trim($coin->wallet_zaddress) !== '')
        $zbalance = $remote->z_getbalance(trim($coin->wallet_zaddress));

    if (!empty($errors)) {
        echo "<td class='{$tdR} " . ($isLegacy ? 'red' : ($isTailwind ? 'text-red-500 dark:text-red-400' : 'text-danger')) . "' title='" . Html::encode($errors) . "'>{$blocks}</td>";
    } else {
        echo "<td class='{$tdR}'>" . Html::encode((string) $blocks) . "</td>";
    }

    $balCell = $conv->altcoinvaluetoa($balance);
    if ($zbalance) $balCell .= '<br><span class="text-gray-400">(' . $conv->bitcoinvaluetoa($zbalance) . ')</span>';
    echo "<td class='{$tdR}'>{$balCell}</td>";
    echo "<td class='{$tdR}'>{$btc}</td>";

    if ($PoS) {
        echo "<td class='{$tdR}'>" . Html::encode((string) ($info['stake'] ?? '')) . "</td>";
    } elseif ($DCR) {
        echo "<td class='{$tdR}'>" . Html::a(Html::encode("{$dcrStake} ({$dcrTickets})"), '/admin/cointickets?id=' . $coin->id) . "</td>";
        echo "<td class='{$tdR}'>" . Html::a(Html::encode((string) $dcrTicketPrice), 'https://dcrstats.com/', ['target' => '_blank']) . "</td>";
    }

    echo "<td class='{$tdR}'>{$conns}</td>";
    echo "<td class='{$tdR}'>{$priceFmt}</td>";
    echo "<td class='{$tdR}'>" . Html::encode((string) $coin->reward) . "</td>";
    echo "<td class='{$tdR}'>" . Html::encode((string) $index) . "</td>";
}
echo '</tr></tbody></table>';
if ($isLegacy) echo '</div></div>';
else echo '</div></div></div>';

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Transactions + Daily sums (side-by-side for AdminLTE/Tailwind)
// ══════════════════════════════════════════════════════════════════════════════

// ── Build transaction data (business logic unchanged) ─────────────────────────
$account = '';
if ($DCR || $DGB) $account = '*';
elseif ($ETH)     $account = $coin->master_wallet;
elseif ($coin->symbol === 'BTC') $account = '*';

$txs = [];
if ($rpcAvailable) {
    $txs = $remote->listtransactions($account, $maxrows);
    if (empty($txs)) { $account = '*'; $txs = $remote->listtransactions($account, $maxrows); }
    if (empty($txs)) $txs = $remote->listtransactions($account, 200);
}

$txs_array = [];
$lastday   = '';
if (!empty($txs)) {
    $tx = reset($txs);
    if (count($txs) == $maxrows && isset($tx['time']))
        $lastday = strftime('%F', $tx['time']);
    foreach ($txs as $tx) {
        if ($conv->arraySafeVal($tx, 'time', $list_since + 1) > $list_since)
            $txs_array[] = $tx;
    }
    krsort($txs_array);
}

// DCR tx filtering (unchanged)
if ($DCR) {
    $amountin_mul = ($info['version'] ?? 0) >= 10500 ? 1.0 : 0.00000001;
    $prev_tx      = [];
    $lastday      = '';
    foreach ($txs_array as $key => $tx) {
        $txs_array[$key]['time'] = min($tx['timereceived'], $conv->arraySafeVal($tx, 'blocktime', $tx['time']));
        $prev_txid = $conv->arraySafeVal($prev_tx, 'txid');
        $category  = $tx['category'];
        if ($conv->arraySafeVal($tx, 'txtype') === 'ticket') {
            $txs_array[$key]['category'] = 'ticket';
            if ($category !== 'receive' || $prev_txid === $conv->arraySafeVal($tx, 'txid'))
                unset($txs_array[$key]);
            else
                $txs_array[$key]['amount'] = 0 - $tx['amount'];
            continue;
        }
        if ($category === 'send' && $conv->arraySafeVal($tx, 'generated')) {
            $txs_array[$key]['category'] = 'spent';
        } elseif ($category === 'send' && $tx['amount'] == -0) {
            $cat2 = $tx['vout'] > 0 ? 'spent'
                : ($conv->arraySafeVal($tx, 'confirmations') >= 256 ? 'receive' : 'immature');
            if ($cat2 === 'spent' && $conv->arraySafeVal($tx, 'txtype') === 'vote') $cat2 = 'unlock';
            $txs_array[$key]['category'] = $cat2;
            if ($tx['vout'] == 0) {
                $t = $remote->getrawtransaction($tx['txid'], 1);
                if ($t && isset($t['vin'][0]))
                    $txs_array[$key]['amount'] = $t['vin'][0]['amountin'] * $amountin_mul;
            }
            if ($cat2 === 'unlock') {
                $t = $remote->getrawtransaction($tx['txid'], 1);
                if ($t && isset($t['vin'][1]))
                    $txs_array[$key]['amount'] = $t['vin'][1]['amountin'] * $amountin_mul;
            }
        } elseif ($category === 'send' && $prev_txid === $conv->arraySafeVal($tx, 'txid')) {
            if ($prev_tx['amount'] == 0 - $tx['amount'])
                $txs_array[$key]['category'] = 'spent';
        } elseif ($category === 'receive') {
            $prev_tx = $tx;
        }
        if ($lastday === '' && count($txs) == $maxrows)
            $lastday = strftime('%F', $tx['time']);
    }
    if (($info['version'] ?? 0) < 1010200) ksort($txs_array);
}

// Batch-load known addresses
$_addrs = array_unique(array_filter(array_map(fn($tx) => $tx['address'] ?? null, $txs_array)));
$knownAddresses = CoinService::getKnownAddresses($_addrs);

// ── Daily sums ────────────────────────────────────────────────────────────────
$sums = [];
foreach ($txs_array as $tx) {
    if (!isset($tx['time'], $tx['amount'])) continue;
    $day = strftime('%F', $tx['time']);
    if ($day === $lastday) break;
    $category = $tx['category'];
    if ($category === 'spent') continue;
    $key        = $day . ' ' . $category;
    $sums[$key] = ($sums[$key] ?? 0) + $tx['amount'];
}

// ── Layout split ──────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '<div class="cwd-split"><div class="cwd-main-col">';
} elseif (!$isTailwind) {
    echo '<div class="row gx-3">';
    echo '<div class="col-12 col-xl-7">';
} else {
    echo '<div class="flex flex-col xl:flex-row gap-4">';
    echo '<div class="flex-1 min-w-0">';
}

// ── Transactions ──────────────────────────────────────────────────────────────
$moreUrl = '/admin/coinwallet?id=' . $coin->id . '&since=' . (time() - 31 * 86400) . '&rows=' . ($maxrows * 2);
$moreLink = Html::a('Show more transactions…', $moreUrl);

if ($isLegacy) {
    echo '<div class="cwd-card">';
    echo '<div class="cwd-card-head"><span><span class="cwd-title">Transactions</span>'
       . '<span class="cwd-sub"> · last ' . $maxrows . ' entries</span></span>'
       . '<span class="cwd-scope">' . count($txs_array) . ' entries</span></div>';
    echo '<div id="transactions" class="cwd-scroll"><table class="dataGrid cwd-table"><thead><tr>';
    foreach (['Time', 'Category', 'Amount', 'Height', 'Difficulty', 'Confirm', 'Address', 'Tx'] as $h)
        echo '<th>' . $h . '</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-list-ul text-secondary"></i><strong class="small">Transactions</strong></div>';
    echo '<div class="card-body p-0"><div class="overflow-auto" style="max-height:380px;">';
    echo '<table class="table table-sm table-bordered mb-0"><thead class="table-light sticky-top"><tr>';
    foreach (['Time', 'Category', 'Amount', 'Height', 'Diff.', 'Confs', 'Address', 'Tx'] as $h)
        echo '<th class="small">' . $h . '</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="list" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Transactions</span></div>';
    echo '<div class="overflow-auto" style="max-height:420px;">';
    echo '<table class="w-full text-xs"><thead>';
    echo '<tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider sticky top-0">';
    foreach (['Time', 'Category', 'Amount', 'Height', 'Diff', 'Confs', 'Address', 'Tx'] as $h)
        echo '<th class="px-3 py-2.5 text-left whitespace-nowrap">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">';
}

$rows = 0;
foreach ($txs_array as $tx) {
    if (!isset($tx['amount'])) {
        if (!isset($tx['reward'])) continue;
        $tx['amount'] = $tx['reward'];
    }
    $category = $conv->arraySafeVal($tx, 'category');
    if ($category === 'spent') continue;

    $block = null;
    if (isset($tx['blockhash'])) $block = $remote->getblock($tx['blockhash']);

    if (!isset($tx['time'])) {
        $dbg = json_encode($tx);
        if ($isLegacy) echo '<tr class="ssrow"><td colspan="8">' . Html::encode($dbg) . '</td></tr>';
        elseif (!$isTailwind) echo '<tr><td colspan="8" class="small text-muted">' . Html::encode($dbg) . '</td></tr>';
        else echo '<tr><td colspan="8" class="px-3 py-2 text-xs text-gray-400 font-mono">' . Html::encode($dbg) . '</td></tr>';
        continue;
    }

    $d      = $conv->datetoa2($tx['time']);
    $eta    = '';
    if ($category === 'immature' && $coin->block_time && $coin->mature_blocks) {
        $t   = (int) ($coin->mature_blocks - $conv->arraySafeVal($tx, 'confirmations', 0)) * $coin->block_time;
        $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
    }

    $addrCell = '';
    if (isset($tx['address'])) {
        $address = $tx['address'];
        $addrCell = isset($knownAddresses[$address])
            ? Html::a(Html::encode($address), Yii::$app->YiimpUtils->walletUrl($address))
            : Html::encode($address);
    }

    $txCell = '';
    if (!empty($block) && isset($tx['txid'])) {
        $txid  = $tx['txid'];
        $label = substr($txid, 0, 7);
        $txCell = $coin->createExplorerLink($label, ['txid' => $txid], ['target' => '_blank']);
    }

    $heightCell = $block ? Html::encode((string) $block['height'])        : '';
    $diffCell   = $block ? $conv->round_difficulty($block['difficulty'])  : '';
    $confsCell  = Html::encode((string) $conv->arraySafeVal($tx, 'confirmations'));
    $amtCell    = Html::encode((string) $tx['amount']);

    $rowCls = $catRowCls($category);

    if ($isLegacy) {
        echo "<tr class='{$rowCls}'>";
        echo '<td><b>' . $d . '</b></td>';
        echo '<td title="' . Html::encode($eta) . '">' . $category . '</td>';
        echo '<td>' . $amtCell . '</td>';
        echo '<td>' . $heightCell . '</td><td>' . $diffCell . '</td>';
        echo '<td>' . $confsCell . '</td>';
        echo '<td width="280">' . $addrCell . '</td>';
        echo '<td>' . $txCell . '</td>';
        echo '</tr>';
    } elseif (!$isTailwind) {
        echo "<tr class='{$rowCls}'>";
        echo '<td class="small text-muted text-nowrap">' . $d . '</td>';
        echo '<td>' . $catBadge($category, $eta) . '</td>';
        echo '<td class="small font-monospace">' . $amtCell . '</td>';
        echo '<td class="small text-end tabular-nums">' . $heightCell . '</td>';
        echo '<td class="small font-monospace">' . $diffCell . '</td>';
        echo '<td class="small text-end tabular-nums">' . $confsCell . '</td>';
        echo '<td class="small" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $addrCell . '</td>';
        echo '<td class="small font-monospace">' . $txCell . '</td>';
        echo '</tr>';
    } else {
        echo "<tr class='{$rowCls} transition-colors'>";
        echo '<td class="px-3 py-1.5 text-gray-400 dark:text-gray-500 whitespace-nowrap">' . $d . '</td>';
        echo '<td class="px-3 py-1.5">' . $catBadge($category, $eta) . '</td>';
        echo '<td class="px-3 py-1.5 font-mono tabular-nums text-gray-700 dark:text-gray-300">' . $amtCell . '</td>';
        echo '<td class="px-3 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">' . $heightCell . '</td>';
        echo '<td class="px-3 py-1.5 font-mono text-gray-500 dark:text-gray-400">' . $diffCell . '</td>';
        echo '<td class="px-3 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">' . $confsCell . '</td>';
        echo '<td class="px-3 py-1.5 font-mono max-w-xs overflow-hidden whitespace-nowrap text-ellipsis text-gray-600 dark:text-gray-300">' . $addrCell . '</td>';
        echo '<td class="px-3 py-1.5 font-mono">' . $txCell . '</td>';
        echo '</tr>';
    }

    if (++$rows >= $maxrows) break;
}

if ($isLegacy) {
    echo '</tbody></table></div>'; // #transactions
    echo '<div class="loadfooter">' . $moreLink . '</div>';
    echo '</div>'; // card
    echo '</div>'; // main column
    echo '<div class="cwd-side-col">';
} elseif (!$isTailwind) {
    echo '</tbody></table></div></div>';
    echo '<div class="card-footer small py-1">' . $moreLink . '</div></div>';
    echo '</div>'; // col-xl-7
    echo '<div class="col-12 col-xl-5">';
} else {
    echo '</tbody></table></div>';
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs">' . $moreLink . '</div>';
    echo '</div>'; // card
    echo '</div>'; // flex-1
    echo '<div class="xl:w-72 shrink-0">';
}

// ── Daily sums ────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '<div class="cwd-card">';
    echo '<div class="cwd-card-head"><span><span class="cwd-title">Daily Summary</span>'
       . '<span class="cwd-sub"> · by category</span></span></div>';
    echo '<div id="sums" class="cwd-scroll"><table class="dataGrid cwd-table"><thead><tr>';
    foreach (['Day', 'Category', 'Sum', 'BTC'] as $h) echo '<th>' . $h . '</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm h-100"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-calendar3 text-secondary"></i><strong class="small">Daily Summary</strong></div>';
    echo '<div class="card-body p-0"><div class="overflow-auto" style="max-height:420px;">';
    echo '<table class="table table-sm table-bordered mb-0"><thead class="table-light sticky-top"><tr>';
    foreach (['Day', 'Category', 'Sum', 'BTC'] as $h) echo '<th class="small">' . $h . '</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="calendar" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Daily Summary</span></div>';
    echo '<div class="overflow-auto" style="max-height:420px;">';
    echo '<table class="w-full text-xs"><thead>';
    echo '<tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider sticky top-0">';
    foreach (['Day', 'Category', 'Sum', 'BTC'] as $h)
        echo '<th class="px-3 py-2.5">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">';
}

if (empty($sums)) {
    $msg = '<i>No activity in the last 7 days</i>';
    if ($isLegacy) echo '<tr class="ssrow"><td colspan="4" class="cwd-empty">' . $msg . '</td></tr>';
    elseif (!$isTailwind) echo '<tr><td colspan="4" class="small text-muted p-2">' . $msg . '</td></tr>';
    else echo '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">' . $msg . '</td></tr>';
} else {
    foreach ($sums as $key => $amount) {
        $parts    = explode(' ', $key, 2);
        $day      = substr($parts[0], 5);
        $category = $parts[1];
        $btcVal   = $conv->bitcoinvaluetoa($coin->price * $amount);
        $rowCls   = $catRowCls($category);

        if ($isLegacy) {
            echo "<tr class='{$rowCls}'>";
            echo '<td><b>' . Html::encode($day) . '</b></td>';
            echo '<td>' . Html::encode($category) . '</td>';
            echo '<td>' . Html::encode((string) $amount) . '</td>';
            echo '<td>' . $btcVal . '</td></tr>';
        } elseif (!$isTailwind) {
            echo "<tr class='{$rowCls}'>";
            echo '<td class="small fw-bold">' . Html::encode($day) . '</td>';
            echo '<td>' . $catBadge($category) . '</td>';
            echo '<td class="small font-monospace">' . Html::encode((string) $amount) . '</td>';
            echo '<td class="small font-monospace">' . $btcVal . '</td></tr>';
        } else {
            echo "<tr class='{$rowCls} transition-colors'>";
            echo '<td class="px-3 py-1.5 font-semibold text-gray-700 dark:text-gray-300">' . Html::encode($day) . '</td>';
            echo '<td class="px-3 py-1.5">' . $catBadge($category) . '</td>';
            echo '<td class="px-3 py-1.5 font-mono tabular-nums text-gray-600 dark:text-gray-300">' . Html::encode((string) $amount) . '</td>';
            echo '<td class="px-3 py-1.5 font-mono tabular-nums text-gray-500 dark:text-gray-400">' . $btcVal . '</td></tr>';
        }
    }
}

if ($isLegacy) {
    echo '</tbody></table></div>'; // #sums
    echo '</div>'; // card
    echo '</div></div>'; // side column + split
    echo '</div>'; // #cwd
} elseif (!$isTailwind) {
    echo '</tbody></table></div></div></div>';
    echo '</div></div>'; // col + row
} else {
    echo '</tbody></table></div></div>';
    echo '</div></div>'; // xl:w-72 + outer flex
}
