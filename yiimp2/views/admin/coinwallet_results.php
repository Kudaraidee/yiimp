<?php

/** @var yii\web\View                        $this        */
/** @var app\models\Coins[]                  $coins       */
/** @var app\models\Mining|null              $mining      */
/** @var array{found:array,orphan:array}     $blockCounts */

use yii\helpers\Html;
use app\services\MarketService;

$usdBtc     = $mining->usdbtc ?? 0;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
$utils      = Yii::$app->YiimpUtils;

// ── Shared per-coin computation ───────────────────────────────────────────────
// Runs regardless of scheme; each branch renders from these variables.

$rows       = [];
$marketSvc  = new MarketService();
foreach ($coins as $coin) {
    $algoColor  = $utils->getAlgoColors($coin->algo);
    $version    = $conv->formatWalletVersion($coin);
    if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

    $difficulty = $conv->Itoa2($coin->difficulty, 3);
    if ($coin->difficulty > 1e20) $difficulty = '';

    $btcmhd = $conv->mbitcoinvaluetoa($utils->yiimp_profitability($coin));
    $ss1    = $blockCounts['found'][$coin->id]  ?? 0;
    $ss2    = $blockCounts['orphan'][$coin->id] ?? 0;

    $price  = $conv->bitcoinvaluetoa($coin->price);
    $price2 = $conv->bitcoinvaluetoa($coin->price2);

    $btcBalance   = $conv->bitcoinvaluetoa($coin->balance   * $coin->price);
    $btcAvailable = $conv->bitcoinvaluetoa($coin->available * $coin->price);

    $fiatBalance   = round($coin->balance   * $coin->price * $usdBtc, 2);
    $fiatAvailable = round($coin->available * $coin->price * $usdBtc, 2);

    $hasDeficit  = $coin->balance + $coin->mint < $coin->cleared;
    $hasDontsell = $coin->dontsell && YIIMP_ALLOW_EXCHANGE;
    $syncPct     = ($coin->block_height < $coin->target_height && $coin->target_height)
                   ? round($coin->block_height * 100 / $coin->target_height, 1) : null;

    $bestMarket = $marketSvc->getBestMarket($coin);
    $marketName = $bestMarket ? (string) $bestMarket->name : '';

    $rows[] = compact(
        'coin', 'algoColor', 'version', 'difficulty',
        'btcmhd', 'ss1', 'ss2', 'price', 'price2',
        'btcBalance', 'btcAvailable', 'fiatBalance', 'fiatAvailable',
        'hasDeficit', 'hasDontsell', 'syncPct', 'marketName'
    );
}

// ── Legacy ────────────────────────────────────────────────────────────────────
if ($isLegacy):
?>
<style type="text/css">
tr.ssrow.filtered { display: none; }
span.eov { opacity: .45; }

/* Coin wallet grid — same card chrome as /site/mining */
#cw-card { color: #334155; font-size: 11px; margin-bottom: 12px; }
#cw-card .main-left-box {
    border: 1px solid #d8e2ef !important; border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; background: #fff;
}
#cw-card .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 40px; padding: 9px 13px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
}
#cw-head { display: flex; align-items: center; gap: 9px; min-width: 0; }
#cw-ico {
    flex: 0 0 26px; height: 26px; border-radius: 6px; display: grid; place-items: center;
    color: #fff; font-size: 12px; font-weight: 800;
    background: linear-gradient(135deg,#4f79c8,#2c5aa0);
}
#cw-title { display: block; font-size: 13px; font-weight: 800; color: #26385a; line-height: 1.2; }
#cw-subtitle { display: block; font-size: 10px; color: #8a94a6; font-weight: 500; }
#cw-pill {
    display: inline-block; border: 1px solid #d7e5ff; background: #eef4ff;
    color: #2c5aa0; border-radius: 999px; padding: 2px 9px;
    font-size: 9px; font-weight: 800; line-height: 15px; white-space: nowrap;
}
#cw-scroll { max-height: 72vh; overflow: auto; }
#cw-card table#maintable {
    width: 100% !important; min-width: 1180px; margin: 0 !important;
    border-collapse: collapse; table-layout: fixed; font-size: 11px; color: #334155;
}
#cw-card table#maintable th {
    position: sticky; top: 0; z-index: 2;
    background: #f8fafc !important; padding: 8px 9px !important;
    font-size: 9px !important; font-weight: 800 !important; letter-spacing: .05em;
    text-transform: uppercase; color: #8a94a6; text-align: right;
    border-bottom: 1px solid #e2e8f0 !important; white-space: nowrap;
}
#cw-card table#maintable td {
    padding: 8px 9px !important; border-bottom: 1px solid #eef2f7 !important;
    vertical-align: middle; text-align: right;
}
#cw-card table#maintable tbody tr:hover td { background: #f7faff; }
#cw-card table#maintable tbody tr:last-child td { border-bottom: 0; }
#cw-card table#maintable tfoot th {
    position: static; background: #fcfdff !important; text-align: left;
    color: #64748b; font-size: 10px; letter-spacing: 0; text-transform: none;
    padding: 7px 11px !important; border-top: 1px solid #e8eef6;
}
#cw-card .cw-left { text-align: left !important; }
#cw-card .cw-center { text-align: center !important; }
#cw-card .cw-w-ico { width: 42px; }
#cw-card .cw-w-status { width: 66px; }
#cw-card .cw-w-coin { width: 172px; }
#cw-card .cw-w-node { width: 178px; }
#cw-card .cw-w-num { width: 108px; }
#cw-card .cw-w-small { width: 92px; }
#cw-card .cw-coin { display: flex; align-items: center; gap: 8px; min-width: 0; }
#cw-card .cw-avatar {
    flex: 0 0 28px; width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid #e8eef6; background: #fff; display: grid; place-items: center; overflow: hidden;
}
#cw-card .cw-avatar img { width: 22px; height: 22px; object-fit: contain; }
#cw-card .cw-coin a { color: #26385a; font-weight: 800; text-decoration: none; }
#cw-card .cw-coin a:hover { color: #2c5aa0; text-decoration: underline; }
#cw-card .cw-meta { display: block; font-size: 9px; color: #94a3b8; font-family: ui-monospace, monospace; }
#cw-card .cw-status { display: flex; flex-direction: column; align-items: center; gap: 3px; }
#cw-card .cw-badges, #cw-card .cw-dots { display: flex; gap: 3px; justify-content: center; }
#cw-card .cw-badge {
    min-width: 17px; padding: 1px 4px; border-radius: 4px; border: 1px solid transparent;
    font-size: 9px; font-weight: 800; line-height: 14px;
}
#cw-card .cw-badge.on { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#cw-card .cw-badge.off { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
#cw-card .cw-badge.warn { background: #fff5e6; color: #b45309; border-color: #fde3b8; }
#cw-card .cw-badge.err { background: #fdecec; color: #b91c1c; border-color: #f8caca; }
#cw-card .cw-dot { width: 6px; height: 6px; border-radius: 50%; background: #e2e8f0; }
#cw-card .cw-dot.on { background: #3b82f6; }
#cw-card .cw-sync { font-size: 9px; color: #64748b; font-weight: 700; }
#cw-card .cw-algo {
    display: inline-block; border-radius: 4px; padding: 1px 6px;
    color: #26385a; font-size: 9px; font-weight: 800; line-height: 15px;
}
#cw-card .cw-conn { color: #187d46; font-size: 9px; font-weight: 800; }
#cw-card .cw-val {
    display: block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 10.5px; line-height: 1.45; color: #334155;
}
#cw-card .cw-val.sub { color: #94a3b8; font-size: 9.5px; }
#cw-card .cw-val.strong { color: #26385a; font-weight: 800; }
#cw-card .cw-val.good { color: #187d46; font-weight: 800; }
#cw-card .cw-val.bad { color: #c0564a; font-weight: 800; }
#cw-card .cw-row-error td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
#cw-card .cw-warn-cell { background: #fff8ef !important; }
#cw-card .cw-deficit { color: #c0564a !important; }
</style>

<div id="cw-card">
<div class="main-left-box">
<div class="main-left-title">
    <span id="cw-head">
        <span id="cw-ico">▦</span>
        <span><span id="cw-title">Coin Wallets</span><span id="cw-subtitle">node status · balances · markets</span></span>
    </span>
    <span class="cw-pill"><?= count($coins) ?> coins</span>
</div>
<div id="cw-scroll">
<?php Yii::$app->ViewUtils->showTableSorter('maintable', '{
tableClass: "dataGrid cw-table",
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}'); ?>

<thead>
<tr>
<th class="cw-w-ico" data-sorter=""></th>
<th class="cw-w-status cw-center" data-sorter="text">Status</th>
<th class="cw-w-coin cw-left" data-sorter="text">Coin</th>
<th class="cw-w-node cw-left" data-sorter="text">Node / Server</th>
<th class="cw-w-num" data-sorter="currency" title="Network difficulty / current height">Difficulty<br/>Height</th>
<th class="cw-w-small" data-sorter="currency" title="mBTC/MH/day · pool share of last 100 blocks">Profit<br/>Pool %</th>
<th class="cw-w-num" data-sorter="currency">Bid<br/>Ask</th>
<th class="cw-w-num" data-sorter="currency">Immature<br/>Cleared</th>
<th class="cw-w-num" data-sorter="currency">Balance<br/>Available</th>
<th class="cw-w-small" data-sorter="currency">BTC</th>
<th class="cw-w-small" data-sorter="currency">USD</th>
<th class="cw-w-num" data-sorter="currency">Reward<br/>Market</th>
</tr>
</thead><tbody>

<?php foreach ($rows as $r):
    $coin      = $r['coin'];
    $algoColor = $r['algoColor'];
    $hasError  = !empty($coin->errors);

    $enableBadge = !$coin->enable
        ? '<span class="cw-badge err" title="Coin disabled">X</span>'
        : ($coin->auto_ready
            ? '<span class="cw-badge on" title="Auto enable">A</span>'
            : '<span class="cw-badge warn" title="Stratum disabled">D</span>');
    $visibleBadge = $coin->visible
        ? '<span class="cw-badge on" title="Visible to public">V</span>'
        : '<span class="cw-badge off" title="Hidden">H</span>';
    $auxBadge = $coin->auxpow ? '<span class="cw-badge warn" title="AUX PoW">X</span>' : '';

    $dot = function (bool $on, string $title): string {
        return '<span class="cw-dot' . ($on ? ' on' : '') . '" title="' . Html::encode($title) . '"></span>';
    };
    $statusCell = '<div class="cw-status">'
        . '<div class="cw-badges">' . $enableBadge . $visibleBadge . $auxBadge . '</div>'
        . '<div class="cw-dots">'
        . $dot((bool) $coin->rpccurl, 'RPC with Curl')
        . $dot((bool) $coin->rpcssl, 'RPC over SSL')
        . $dot((bool) $coin->watch, 'Watched (history)')
        . '</div>'
        . ($r['syncPct'] !== null ? '<span class="cw-sync">' . $r['syncPct'] . '%</span>' : '')
        . '</div>';
?>
<tr class="ssrow<?= $hasError ? ' cw-row-error' : '' ?>">
    <td>
        <span class="cw-avatar"><?= Html::img(Html::encode($coin->image), ['alt' => '']) ?></span>
    </td>
    <td class="cw-center" style="box-shadow: inset 3px 0 0 <?= $algoColor ?>;"><?= $statusCell ?></td>
    <td class="cw-left">
        <span class="cw-coin">
            <span>
                <?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"), ['/admin/coinwallet', 'id' => $coin->id]) ?>
                <span class="cw-meta"><?= Html::encode($r['version']) ?></span>
            </span>
        </span>
    </td>
    <td class="cw-left">
        <span class="cw-val strong"><?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?></span>
        <span class="cw-val sub">
            <span class="cw-algo" style="background-color:<?= $algoColor ?>"><?= Html::encode($coin->algo) ?></span>
            <?= Html::encode($coin->rpcencoding) ?>
            <?php if ($coin->connections): ?>
                <span class="cw-conn">● <?= (int) $coin->connections ?></span>
            <?php endif ?>
        </span>
    </td>
    <td title="<?= Html::encode((string) $coin->errors) ?>">
        <span class="cw-val strong<?= $hasError ? ' bad' : '' ?>"><?= $r['difficulty'] ?></span>
        <span class="cw-val sub"><?= number_format((int) $coin->block_height) ?></span>
    </td>
    <td title="Pool % of last 100 net blocks">
        <span class="cw-val<?= $r['ss1'] > 50 ? ' good' : '' ?>"><?= $r['btcmhd'] ?></span>
        <span class="cw-val sub">
            <?php if ($r['ss1']): ?><?= $r['ss1'] ?>%<?php endif ?>
            <?php if ($r['ss2']): ?><span class="cw-val bad" title="orphans"><?= $r['ss2'] ?>%</span><?php endif ?>
        </span>
    </td>
    <td class="<?= $r['hasDontsell'] ? 'cw-warn-cell' : '' ?>">
        <span class="cw-val"><?= $r['price'] ?></span>
        <span class="cw-val sub"><?= $r['price2'] ?></span>
    </td>
    <td class="<?= $r['hasDeficit'] ? 'cw-deficit' : '' ?>">
        <span class="cw-val"><?= $conv->valuetocell($coin->mint) ?></span>
        <span class="cw-val sub"><?= $conv->valuetocell($coin->cleared) ?></span>
    </td>
    <td class="<?= $r['hasDeficit'] ? 'cw-deficit' : '' ?>">
        <span class="cw-val"><?= $conv->valuetocell($coin->balance) ?></span>
        <span class="cw-val sub"><?= $conv->valuetocell($coin->available) ?></span>
    </td>
    <td>
        <span class="cw-val"><?= $r['btcBalance'] ?></span>
        <span class="cw-val sub"><?= $r['btcAvailable'] ?></span>
    </td>
    <td>
        <span class="cw-val"><?= $r['fiatBalance'] ?> $</span>
        <span class="cw-val sub"><?= $r['fiatAvailable'] ?> $</span>
    </td>
    <td>
        <span class="cw-val strong"><?= Html::encode((string) $coin->reward) ?></span>
        <?php if ($r['marketName'] !== ''): ?>
            <span class="cw-val sub"><?= Html::encode($r['marketName']) ?></span>
        <?php endif ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr><th colspan="12"><?= count($coins) ?> wallets · auto-refresh 30s</th></tr>
</tfoot>
</table>
</div>
</div>
</div>
<br/>
<?php
// ── AdminLTE ──────────────────────────────────────────────────────────────────
elseif (!$isTailwind):
?>

<div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <span class="small fw-semibold">
            <i class="bi bi-wallet2 text-secondary me-1"></i>Coin Wallets
            <span class="text-muted fw-normal">· node status · balances · markets</span>
        </span>
        <span class="badge bg-primary-subtle text-primary"><?= count($coins) ?> coins</span>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
        <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th data-sorter="" style="width:28px"></th>
            <th data-sorter="text" style="width:54px" class="text-center">Flags</th>
            <th data-sorter="text">Name</th>
            <th data-sorter="text">Server</th>
            <th data-sorter="currency" class="text-end">Diff<br/><small>Height</small></th>
            <th data-sorter="currency" class="text-end" title="mBTC/MH/d · pool %">Profit</th>
            <th data-sorter="currency" class="text-end">Bid<br/><small>Ask</small></th>
            <th data-sorter="currency" class="text-end">Immature<br/><small>Cleared</small></th>
            <th data-sorter="currency" class="text-end">Balance<br/><small>Available</small></th>
            <th data-sorter="currency" class="text-end">BTC</th>
            <th data-sorter="currency" class="text-end">USD</th>
            <th data-sorter="currency" class="text-end">Reward<br/><small>Market</small></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $coin = $r['coin'];

            // Status flags as compact Bootstrap badges
            if (!$coin->enable)         $enableBadge = '<span class="badge bg-danger"     title="Coin disabled">D</span>';
            elseif ($coin->auto_ready)  $enableBadge = '<span class="badge bg-success"    title="Auto enable">A</span>';
            else                        $enableBadge = '<span class="badge bg-warning text-dark" title="Stratum disabled">D</span>';

            $flagBits = $enableBadge
                . ($coin->visible ? '<span class="badge bg-secondary ms-1" title="Visible">V</span>'
                                  : '<span class="badge bg-light text-dark border ms-1" title="Hidden">H</span>')
                . ($coin->auxpow  ? '<span class="badge bg-warning text-dark ms-1" title="AUX PoW">X</span>' : '')
                . ($coin->rpccurl ? '<span class="badge bg-secondary ms-1" title="RPC Curl">C</span>' : '')
                . ($coin->rpcssl  ? '<span class="badge bg-secondary ms-1" title="RPC SSL">S</span>' : '')
                . ($coin->watch   ? '<span class="badge bg-info text-dark ms-1" title="Watched">W</span>' : '')
                . ($r['syncPct'] !== null
                    ? '<br/><small class="text-muted">' . $r['syncPct'] . '%</small>' : '');
        ?>
        <tr class="<?= !empty($coin->errors) ? 'table-danger' : '' ?>">

            <td>
                <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" width="22" height="22"
                         style="object-fit:contain" onerror="this.style.display='none'" alt="">
                <?php endif ?>
            </td>

            <td class="text-center" style="background-color:<?= $r['algoColor'] ?>22;">
                <?= $flagBits ?>
            </td>

            <td>
                <div>
                    <?= Html::a('<strong>' . Html::encode("{$coin->name} ({$coin->symbol})") . '</strong>',
                        ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
                </div>
                <small class="text-muted font-monospace"><?= Html::encode($r['version']) ?></small>
            </td>

            <td>
                <div class="font-monospace small"><?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
                    <?= $coin->connections ? ' <span class="text-muted">(' . (int) $coin->connections . ')</span>' : '' ?>
                </div>
                <div>
                    <span class="badge text-dark"
                          style="background-color:<?= $r['algoColor'] ?>;">
                        <?= Html::encode($coin->algo) ?>
                    </span>
                    <small class="text-muted"><?= Html::encode($coin->rpcencoding) ?></small>
                </div>
            </td>

            <td class="text-end small <?= !empty($coin->errors) ? 'text-danger' : '' ?>"
                title="<?= Html::encode($coin->errors ?? '') ?>">
                <strong><?= $r['difficulty'] ?></strong><br>
                <span class="text-muted"><?= number_format((int) $coin->block_height) ?></span>
            </td>

            <td class="text-end small" title="Pool % of last 100 net blocks">
                <strong><?= $r['btcmhd'] ?></strong><br>
                <?php if ($r['ss1']): ?>
                    <span class="<?= $r['ss1'] > 50 ? 'text-primary fw-bold' : 'text-muted' ?>"><?= $r['ss1'] ?>%</span>
                <?php endif ?>
                <?php if ($r['ss2']): ?>
                    <span class="text-danger ms-1" title="orphans"><?= $r['ss2'] ?>%</span>
                <?php endif ?>
            </td>

            <td class="text-end small <?= $r['hasDontsell'] ? 'table-warning' : '' ?>">
                <?= $r['price'] ?><br><span class="text-muted"><?= $r['price2'] ?></span>
            </td>

            <td class="text-end small <?= $r['hasDeficit'] ? 'text-danger' : '' ?>">
                <?= $conv->valuetocell($coin->mint) ?><br>
                <span class="text-muted"><?= $conv->valuetocell($coin->cleared) ?></span>
            </td>

            <td class="text-end small">
                <?= $conv->valuetocell($coin->balance) ?><br>
                <span class="text-muted"><?= $conv->valuetocell($coin->available) ?></span>
            </td>

            <td class="text-end small font-monospace">
                <?= $r['btcBalance'] ?><br>
                <span class="text-muted"><?= $r['btcAvailable'] ?></span>
            </td>

            <td class="text-end small font-monospace">
                <?= $r['fiatBalance'] ?> $<br>
                <span class="text-muted"><?= $r['fiatAvailable'] ?> $</span>
            </td>

            <td class="text-end small">
                <?= Html::encode((string) $coin->reward) ?>
                <?php if ($r['marketName'] !== ''): ?>
                    <br><span class="text-muted small"><?= Html::encode($r['marketName']) ?></span>
                <?php endif ?>
            </td>

        </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="table-light">
            <tr><th colspan="12" class="text-muted small"><?= count($coins) ?> wallets</th></tr>
        </tfoot>
        </table>
        </div>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter','Storage','saveSort'],
        widgetOptions: {
            saveSort: true, filter_saveFilters: true,
            filter_external: '.search', filter_columnFilters: false,
            filter_childRows: true, filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>

<?php
// ── Tailwind ──────────────────────────────────────────────────────────────────
else:
?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                flex items-center justify-between gap-3">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <i data-lucide="wallet" class="w-4 h-4 text-gray-400"></i>Coin Wallets
            <span class="text-xs font-normal text-gray-400">· node status · balances · markets</span>
        </span>
        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-900/30
                     text-indigo-600 dark:text-indigo-300"><?= count($coins) ?> coins</span>
    </div>
    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
        <th class="px-2 py-2.5 w-7" data-sorter="false"></th>
        <th class="px-2 py-2.5 w-14 text-center" data-sorter="text">Flags</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Name</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Server</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Diff / Height</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Profit</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Bid / Ask</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Immature / Cleared</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Balance / Avail</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">BTC</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">USD</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Reward / Market</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $coin = $r['coin'];

        $enableDot = !$coin->enable
            ? '<span class="inline-block w-2 h-2 rounded-full bg-red-400" title="Disabled"></span>'
            : ($coin->auto_ready
                ? '<span class="inline-block w-2 h-2 rounded-full bg-green-400" title="Auto enable"></span>'
                : '<span class="inline-block w-2 h-2 rounded-full bg-amber-400" title="Stratum disabled"></span>');

        $flags = $enableDot
            . ($coin->visible
                ? '<span class="inline-block w-2 h-2 rounded-full bg-blue-400 ml-0.5" title="Visible"></span>'
                : '<span class="inline-block w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 ml-0.5" title="Hidden"></span>')
            . ($coin->auxpow
                ? '<span class="inline-block w-2 h-2 rounded-full bg-amber-400 ml-0.5" title="AUX PoW"></span>' : '')
            . ($coin->rpccurl
                ? '<span class="inline-block w-2 h-2 rounded-full bg-orange-400 ml-0.5" title="RPC Curl"></span>' : '')
            . ($coin->rpcssl
                ? '<span class="inline-block w-2 h-2 rounded-full bg-teal-400 ml-0.5" title="RPC SSL"></span>' : '')
            . ($coin->watch
                ? '<span class="inline-block w-2 h-2 rounded-full bg-indigo-400 ml-0.5" title="Watched"></span>' : '')
            . ($r['syncPct'] !== null
                ? '<div class="text-gray-400 dark:text-gray-500 mt-0.5">' . $r['syncPct'] . '%</div>' : '');

        $rowClass = !empty($coin->errors)
            ? 'bg-red-50/60 dark:bg-red-900/10'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20';
    ?>
    <tr class="<?= $rowClass ?> transition-colors">

        <td class="px-2 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-2 py-2 text-center">
            <div class="flex flex-wrap items-center justify-center gap-0.5"><?= $flags ?></div>
        </td>

        <td class="px-3 py-2">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"),
                    ['/admin/coinwallet', 'id' => $coin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="font-mono text-gray-400 dark:text-gray-500 text-xs">
                <?= Html::encode($r['version']) ?>
            </div>
        </td>

        <td class="px-3 py-2">
            <div class="font-mono text-gray-600 dark:text-gray-300">
                <?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
                <?= $coin->connections ? '<span class="text-gray-400"> (' . (int) $coin->connections . ')</span>' : '' ?>
            </div>
            <div class="flex items-center gap-1 mt-0.5">
                <span class="px-1.5 py-0 rounded text-white text-xs font-medium"
                      style="background-color:<?= $r['algoColor'] ?>;">
                    <?= Html::encode($coin->algo) ?>
                </span>
                <span class="text-gray-400 dark:text-gray-500"><?= Html::encode($coin->rpcencoding) ?></span>
            </div>
        </td>

        <td class="px-3 py-2 text-right <?= !empty($coin->errors) ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' ?>"
            title="<?= Html::encode($coin->errors ?? '') ?>">
            <div class="font-semibold"><?= $r['difficulty'] ?></div>
            <div class="text-gray-400 dark:text-gray-500 font-mono tabular-nums">
                <?= number_format((int) $coin->block_height) ?>
            </div>
        </td>

        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300"
            title="Pool % of last 100 net blocks">
            <div class="font-semibold"><?= $r['btcmhd'] ?></div>
            <div class="flex items-center justify-end gap-1 mt-0.5">
                <?php if ($r['ss1']): ?>
                    <span class="<?= $r['ss1'] > 50 ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-gray-400' ?>">
                        <?= $r['ss1'] ?>%
                    </span>
                <?php endif ?>
                <?php if ($r['ss2']): ?>
                    <span class="text-red-400 dark:text-red-500" title="orphans"><?= $r['ss2'] ?>%</span>
                <?php endif ?>
            </div>
        </td>

        <td class="px-3 py-2 text-right <?= $r['hasDontsell'] ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
            <div class="text-gray-700 dark:text-gray-300 font-mono"><?= $r['price'] ?></div>
            <div class="text-gray-400 dark:text-gray-500 font-mono"><?= $r['price2'] ?></div>
        </td>

        <td class="px-3 py-2 text-right <?= $r['hasDeficit'] ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' ?>">
            <div class="font-mono"><?= $conv->valuetocell($coin->mint) ?></div>
            <div class="font-mono text-gray-400 dark:text-gray-500"><?= $conv->valuetocell($coin->cleared) ?></div>
        </td>

        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
            <div class="font-mono"><?= $conv->valuetocell($coin->balance) ?></div>
            <div class="font-mono text-gray-400 dark:text-gray-500"><?= $conv->valuetocell($coin->available) ?></div>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <div><?= $r['btcBalance'] ?></div>
            <div class="text-gray-400 dark:text-gray-500"><?= $r['btcAvailable'] ?></div>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <div><?= $r['fiatBalance'] ?> $</div>
            <div class="text-gray-400 dark:text-gray-500"><?= $r['fiatAvailable'] ?> $</div>
        </td>

        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
            <div><?= Html::encode((string) $coin->reward) ?></div>
            <?php if ($r['marketName'] !== ''): ?>
                <div class="text-gray-400 dark:text-gray-500"><?= Html::encode($r['marketName']) ?></div>
            <?php endif ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= count($coins) ?> wallets
    </div>
</div>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0];
    var ths   = Array.from(table.tHead.rows[0].cells);
    var asc   = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rows = Array.from(tbody.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });
});
");
?>

<?php endif ?>
