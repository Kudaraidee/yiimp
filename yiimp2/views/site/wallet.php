<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Coins;
use app\models\Accounts;

$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$homeUrl    = Yii::$app->homeUrl;

// ── Recent wallets cookie ─────────────────────────────────────────────────────
$recents    = [];
$rawRecents = isset($_COOKIE['wallets']) ? explode('|', $_COOKIE['wallets']) : [];
foreach ($rawRecents as $addr) {
    if ($addr !== '') $recents[$addr] = $addr;
}

// ── Sanitise address ──────────────────────────────────────────────────────────
$address = trim((string) Yii::$app->request->get('address', ''));
if ($address !== '' && preg_match('/[^A-Za-z0-9]/', $address)) die;
$address = substr($address, 0, 52);

// ── Drop cookie entry ─────────────────────────────────────────────────────────
$dropAddress = Yii::$app->request->get('drop', '');
if ($dropAddress !== '' && isset($recents[$dropAddress])) {
    unset($recents[$dropAddress]);
    if (Yii::$app->user->identity !== null)
        setcookie('wallets', implode('|', $recents), time() + 86400 * 30, '/');
}

// ── Primary user lookup ───────────────────────────────────────────────────────
$user = null;
if ($address !== '') {
    $user = Accounts::find()->where(['username' => $address])->one();
}

if ($user !== null) {
    Yii::$app->session->set('yaamp-wallet', $user->username);
    $recents[$user->username] = $user->username;

    $coin = Coins::findOne((int) $user->coinid);
    if ($coin) {
        $faviconUrl = Html::encode($coin->image);
        $this->registerJs(
            "$(function(){\$('#favicon').remove();\$('head').append('<link href=\"{$faviconUrl}\" id=\"favicon\" rel=\"shortcut icon\">');});",
            \yii\web\View::POS_READY, 'favicon-handler'
        );
    }

    if (empty($user->hostaddr) && Yii::$app->user->identity === null) {
        $user->hostaddr = $_SERVER['REMOTE_ADDR'];
        $user->save();
    }
}

$username = $user ? $user->username : '';
if (Yii::$app->user->identity !== null)
    setcookie('wallets', implode('|', $recents), time() + 86400 * 30, '/');

// ── Batch-load recent wallet accounts + coins (replaces N+1 in loop) ─────────
$recentAddrs = array_values(array_filter($recents));
$recentUsers = $recentAddrs
    ? Accounts::find()->where(['username' => $recentAddrs])->indexBy('username')->all()
    : [];
$recentCoinIds = array_values(array_unique(array_filter(array_map(fn($u) => $u->coinid, $recentUsers))));
$recentCoins   = $recentCoinIds
    ? Coins::find()->where(['id' => $recentCoinIds])->indexBy('id')->all()
    : [];

$jsUsername = json_encode($username);
$jsAddress  = json_encode($address);
$jsHomeUrl  = json_encode($homeUrl);

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
.wallet-col { min-width:0; }
.graph-wrap { overflow:hidden; width:100%; }
.graph-wrap canvas { max-width:100%!important; }
.main-left-box { margin-bottom:1rem; }
.ajax-placeholder { min-height:120px; }

/* ── Shared wallet card chrome — mirrors /site/mining ─────────────────────── */
#wallet-cards .main-left-box {
    border: 1px solid #d8e2ef !important;
    border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    overflow: hidden;
    margin-bottom: 1rem;
}
#wallet-cards .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
    color: #26385a; font-size: 13px !important; font-weight: 800 !important; line-height: 1.3;
}
#wallet-cards .wl-head-main { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-width: 0; }
#wallet-cards .wl-title { font-size: 13px; font-weight: 800; color: #26385a; white-space: nowrap; }
#wallet-cards .wl-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#wallet-cards .wl-ico {
    position: relative; flex: 0 0 26px; height: 26px; border-radius: 6px; overflow: hidden;
    display: grid; place-items: center; color: #fff; font-size: 7px; font-weight: 800;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#wallet-cards .wl-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#wallet-cards .wl-chips { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }
#wallet-cards .wl-pill {
    display: inline-block; border-radius: 999px; padding: 2px 7px;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
}
#wallet-cards .wl-pill.blue { background: #eef4ff; color: #2c5aa0; border-color: #d7e5ff; }
#wallet-cards .wl-pill.green { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#wallet-cards .wl-pill.amber { background: #fff7e8; color: #a86400; border-color: #ffe2b8; }
#wallet-cards .wl-pill.red { background: #fdecec; color: #c0392b; border-color: #f6d2d2; }
#wallet-cards .wl-pill.teal { background: #e6fbfb; color: #0f766e; border-color: #c7f0ee; }
#wallet-cards .main-left-inner { padding: 0 !important; }
#wallet-cards .wl-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 8px 12px;
    background: #fcfdff; border-bottom: 1px solid #eef2f7;
    font-size: 10px; color: #8a94a6;
}
#wallet-cards .wl-summary b { color: #33405a; font-weight: 700; }
#wallet-cards .wl-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}
#wallet-cards .graph-wrap { padding: 8px 12px 6px; }
#wallet-cards .wl-foot {
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5; line-height: 1.5;
}
#wallet-cards .wl-foot b { color: #7d8aa0; }
#wallet-cards .wl-notice {
    margin: 10px 12px; padding: 8px 10px; border-radius: 6px;
    background: #fff7e8; border: 1px solid #ffe2b8; color: #a86400; font-size: 10px; font-weight: 700;
}

/* ── Table shell — identical to /site/mining ──────────────────────────────── */
#wallet-cards table.dataGrid2,
#wallet-cards .wm-table,
#wallet-cards .wf-table,
#wallet-cards .we-table {
    width: 100% !important; border-collapse: collapse; table-layout: fixed;
    font-size: 11px; color: #334155;
}
#wallet-cards table.dataGrid2 th,
#wallet-cards .wm-table th,
#wallet-cards .wf-table th,
#wallet-cards .we-table th {
    background: #fff !important; padding: 7px 9px !important; text-align: right;
    font-size: 9px !important; font-weight: 700 !important; letter-spacing: .05em;
    text-transform: uppercase; color: #8a94a6; border-bottom: 1px solid #e8eef6; white-space: nowrap;
}
#wallet-cards table.dataGrid2 th:first-child,
#wallet-cards table.dataGrid2 td:first-child,
#wallet-cards .wm-table th:first-child, #wallet-cards .wm-table td:first-child,
#wallet-cards .wf-table th:first-child, #wallet-cards .wf-table td:first-child,
#wallet-cards .we-table th:first-child, #wallet-cards .we-table td:first-child { text-align: left; }
#wallet-cards table.dataGrid2 td,
#wallet-cards .wm-table td,
#wallet-cards .wf-table td,
#wallet-cards .we-table td {
    padding: 8px 9px !important; border-bottom: 1px solid #eef2f7;
    text-align: right; vertical-align: middle;
}
#wallet-cards table.dataGrid2 tbody tr:last-child td,
#wallet-cards .wm-table tbody tr:last-child td,
#wallet-cards .wf-table tbody tr:last-child td,
#wallet-cards .we-table tbody tr:last-child td { border-bottom: 0; }
#wallet-cards table.dataGrid2 tbody tr:hover,
#wallet-cards .wm-table tbody tr:hover,
#wallet-cards .wf-table tbody tr:hover,
#wallet-cards .we-table tbody tr:hover { background: #f7faff; }
#wallet-cards .wl-left, #wallet-cards th.wl-left { text-align: left !important; }
#wallet-cards .tablesorter-header { cursor: pointer; }
#wallet-cards .tablesorter-header:hover { color: #2c5aa0; }

/* ── Cell typography and inline badges ───────────────────────────────────── */
#wallet-cards .wl-val {
    display: block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums; font-size: 11px; font-weight: 700;
    color: #2b3d5b; white-space: nowrap;
}
#wallet-cards .wl-cap { display: block; font-size: 9px; color: #98a3b5; margin-top: 1px; white-space: nowrap; }
#wallet-cards .wl-link { font-size: 11px; font-weight: 700; color: #26385a; text-decoration: none; }
#wallet-cards .wl-link:hover { color: #2c5aa0; }
#wallet-cards .wl-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
#wallet-cards .wl-coin { display: flex; align-items: center; gap: 8px; min-width: 0; }
#wallet-cards .wl-coin-main { min-width: 0; }
#wallet-cards .wl-eff { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
#wallet-cards .wl-eff-bar { width: 46px; height: 5px; border-radius: 999px; background: #edf1f6; overflow: hidden; }
#wallet-cards .wl-eff-bar i { display: block; height: 100%; border-radius: 999px; background: #2fae5a; }
#wallet-cards .wl-eff.mid .wl-eff-bar i { background: #e0a020; }
#wallet-cards .wl-eff.high .wl-eff-bar i { background: #d9534f; }
#wallet-cards .wl-eff-num { font-size: 10px; font-weight: 700; color: #475569; min-width: 42px; text-align: right; }
#wallet-cards .wl-eff.mid .wl-eff-num { color: #a86400; }
#wallet-cards .wl-eff.high .wl-eff-num { color: #c0392b; }
#wallet-cards .wl-empty { text-align: center !important; padding: 22px 10px !important; color: #98a3b5; font-size: 11px; }
#wallet-cards .wl-total td { background: #f8fafc; font-weight: 700; }
#wallet-cards .wl-extra th { background: #fdecec !important; color: #c0392b !important; }

/* ── Search wallet form ───────────────────────────────────────────────────── */
#wallet-cards .wl-form { display: flex; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #eef2f7; }
#wallet-cards .wl-input {
    flex: 1 1 auto; min-width: 0; padding: 7px 10px; font: inherit; font-size: 11px;
    color: #334155; background: #fff; border: 1px solid #d8e2ef; border-radius: 6px;
}
#wallet-cards .wl-input:focus { outline: none; border-color: #2c5aa0; box-shadow: 0 0 0 2px rgba(44,90,160,.12); }
#wallet-cards .wl-submit {
    flex: 0 0 auto; padding: 7px 14px; border: 1px solid #2c5aa0; border-radius: 6px;
    background: #2c5aa0; color: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer;
}
#wallet-cards .wl-submit:hover { background: #234a84; }
#wallet-cards .wl-del { width: 14px; height: 14px; cursor: pointer; opacity: .7; }
#wallet-cards .wl-del:hover { opacity: 1; }

@media(max-width:820px){
    #wallet-cards table.dataGrid2,
    #wallet-cards .wm-table,
    #wallet-cards .wf-table,
    #wallet-cards .we-table { table-layout: auto; }
    #wallet-cards .wl-chips { display: none; }
}
</style>

<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div id="wallet-cards" class="row gx-4 mt-2">
    <div class="col-12 col-md-6 wallet-col">
        <?php if ($user): ?>
        <div id="main_wallet_results" class="ajax-placeholder"></div>
        <div id="wallet-balance-card" class="main-left-box">
            <div class="main-left-title">
                <span class="wl-head-main">
                    <span class="wl-title">Last 24 Hours Balance</span>
                    <span class="wl-sub"><?= Html::encode($username) ?></span>
                </span>
                <span class="wl-chips"><span class="wl-pill blue">24h</span><span class="wl-pill">auto · 60s</span></span>
            </div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="graph_earnings_results" style="height:240px;"></div></div>
                <div class="wl-foot">
                    <span><b style="color:#4bb2c5;">●</b> Balance &nbsp; <b style="color:#eaa228;">●</b> Pending</span>
                    <span>Values in payout coin</span>
                </div>
            </div>
        </div>
        <div id="main_graphs_results"  class="ajax-placeholder"></div>
        <div id="main_miners_results"  class="ajax-placeholder"></div>
        <div id="main_found_results"   class="ajax-placeholder"></div>
        <?php endif ?>
        <div id="wallet-search-card" class="main-left-box">
            <div class="main-left-title">
                <span class="wl-head-main">
                    <span class="wl-title">Search Wallet</span>
                    <span class="wl-sub">Look up balance, workers and blocks</span>
                </span>
                <span class="wl-chips"><span class="wl-pill blue"><?= count($recents) ?> recent</span></span>
            </div>
            <div class="main-left-inner">
                <form action="<?= Html::encode($homeUrl) ?>" method="get" class="wl-form">
                    <input type="text" name="address" class="wl-input" placeholder="Wallet Address">
                    <input type="submit" value="Search" class="wl-submit">
                </form>
                <table class="dataGrid2">
                    <thead><tr><th style="width:30px">Coin</th><th class="wl-left">Address</th><th>Balance</th><th style="width:20px"></th></tr></thead>
                    <tbody>
                    <?php $recentRows = 0; foreach ($recents as $addr):
                        if (empty($addr)) continue;
                        $rUser = $recentUsers[$addr] ?? null; if (!$rUser) continue;
                        $rCoin = $recentCoins[$rUser->coinid] ?? null;
                        $bal   = Yii::$app->ConversionUtils->bitcoinvaluetoa($rUser->balance);
                        $balD  = $rCoin && $bal > 0 ? "{$bal} {$rCoin->symbol}" : ($bal > 0 ? "{$bal} BTC" : '—');
                        $active = $rUser->username === $username;
                        $del   = $address !== $addr
                            ? '<img class="wl-del" src="/images/base/delete.png" onclick="drop_cookie(this);" alt="remove">'
                            : '';
                        $recentRows++;
                    ?>
                    <tr class="<?= $active ? 'wl-total' : '' ?>">
                        <td><?php if ($rCoin && !empty($rCoin->image)): ?><img width="16" height="16" src="<?= Html::encode($rCoin->image) ?>" alt=""><?php endif ?></td>
                        <td class="wl-left"><?= Html::a(Html::encode($addr), $homeUrl . '?address=' . urlencode($addr), ['class' => 'wl-link address']) ?></td>
                        <td><span class="wl-val"><?= Html::encode($balD) ?></span></td>
                        <td><?= $del ?></td>
                    </tr>
                    <?php endforeach; if (!$recentRows): ?>
                    <tr><td colspan="4" class="wl-empty">No Recent Wallets</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
                <div class="wl-foot"><span>Recent wallets are stored in this browser for 30 days</span><span>Refresh 60s</span></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 wallet-col" style="overflow-x:auto;">
        <?php if ($user): ?><div id="found_results" class="ajax-placeholder"></div><?php endif ?>
    </div>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="alert alert-warning d-flex align-items-center gap-2 mb-3"
     style="cursor:pointer;display:none!important;"
     onclick="auto_page_resume()">
    <i class="bi bi-pause-circle-fill"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="row gx-3 mt-2">

    <!-- LEFT -->
    <div class="col-12 col-md-6">

        <?php if ($user): ?>
        <div id="main_wallet_results" class="mb-3"></div>

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-graph-up me-1"></i>Last 24h Balance — <?= Html::encode($username) ?>
            </div>
            <div class="card-body">
                <div id="graph_earnings_results" style="height:200px;"></div>
                <div class="d-flex justify-content-end gap-3 mt-1" style="font-size:.75rem;">
                    <span style="color:#4bb2c5;">● Balance</span>
                    <span style="color:#eaa228;">● Pending</span>
                </div>
            </div>
        </div>

        <div id="main_graphs_results"  class="mb-3"></div>
        <div id="main_miners_results"  class="mb-3"></div>
        <div id="main_found_results"   class="mb-3"></div>
        <?php endif ?>

        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-search me-1"></i>Search Wallet
            </div>
            <div class="card-body pb-2">
                <form action="<?= Html::encode($homeUrl) ?>" method="get" class="d-flex gap-2 mb-3">
                    <input type="text" name="address" class="form-control form-control-sm font-monospace"
                           placeholder="Wallet address">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Go</button>
                </form>

                <?php if ($recents): ?>
                <table class="table table-sm table-hover mb-0">
                <?php foreach ($recents as $addr):
                    if (empty($addr)) continue;
                    $rUser = $recentUsers[$addr] ?? null; if (!$rUser) continue;
                    $rCoin = $recentCoins[$rUser->coinid] ?? null;
                    $bal   = Yii::$app->ConversionUtils->bitcoinvaluetoa($rUser->balance);
                    $balD  = $rCoin && $bal > 0 ? "{$bal} {$rCoin->symbol}" : ($bal > 0 ? "{$bal} BTC" : '');
                    $active = $rUser->username === $username;
                ?>
                <tr class="<?= $active ? 'table-info' : '' ?>">
                    <td width="24"><?php if ($rCoin && !empty($rCoin->image)): ?>
                        <img width="16" src="<?= Html::encode($rCoin->image) ?>" alt="" style="object-fit:contain">
                    <?php endif ?></td>
                    <td><?= Html::a(Html::encode($addr), $homeUrl . '?address=' . urlencode($addr),
                        ['class' => 'font-monospace small text-decoration-none']) ?></td>
                    <td class="text-end small font-monospace text-muted"><?= Html::encode($balD) ?></td>
                    <td width="20"><?php if ($address !== $addr): ?>
                        <img src="/images/base/delete.png" onclick="drop_cookie(this);" style="cursor:pointer;">
                    <?php endif ?></td>
                </tr>
                <?php endforeach ?>
                </table>
                <?php endif ?>
            </div>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="col-12 col-md-6" style="overflow-x:auto;">
        <?php if ($user): ?><div id="found_results" class="mb-3"></div><?php endif ?>
    </div>

</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl
            bg-amber-50 dark:bg-amber-900/20
            border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 text-sm cursor-pointer"
     style="display:none;"
     onclick="auto_page_resume()">
    <i data-lucide="pause-circle" class="w-5 h-5 shrink-0"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">

    <!-- LEFT -->
    <div class="flex flex-col gap-4 min-w-0">

        <?php if ($user): ?>
        <div id="main_wallet_results"></div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4 text-indigo-500 shrink-0"></i>
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    Last 24h Balance
                    <span class="font-mono text-indigo-600 dark:text-indigo-400 ml-1"><?= Html::encode($username) ?></span>
                </span>
            </div>
            <div class="p-4">
                <div id="graph_earnings_results" style="height:200px;"></div>
                <div class="flex justify-end gap-4 mt-2 text-xs">
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#4bb2c5;"></span>
                        Balance
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#eaa228;"></span>
                        Pending
                    </span>
                </div>
            </div>
        </div>

        <div id="main_graphs_results"></div>
        <div id="main_miners_results"></div>
        <div id="main_found_results"></div>
        <?php endif ?>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700
                    bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                        flex items-center gap-2">
                <i data-lucide="search" class="w-4 h-4 text-gray-400 shrink-0"></i>
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Search Wallet</span>
            </div>
            <div class="p-4">
                <form action="<?= Html::encode($homeUrl) ?>" method="get"
                      class="flex items-center gap-2 mb-4">
                    <input type="text" name="address"
                           class="flex-1 px-3 py-1.5 text-sm rounded-lg border
                                  border-gray-300 dark:border-gray-600
                                  bg-white dark:bg-gray-700
                                  text-gray-900 dark:text-gray-100 font-mono
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500
                                  placeholder-gray-400 dark:placeholder-gray-500"
                           placeholder="Wallet address">
                    <button type="submit"
                            class="px-3 py-1.5 text-sm rounded-lg border
                                   border-gray-300 dark:border-gray-600
                                   bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300
                                   hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        Go
                    </button>
                </form>

                <?php if ($recents): ?>
                <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                <?php foreach ($recents as $addr):
                    if (empty($addr)) continue;
                    $rUser = $recentUsers[$addr] ?? null; if (!$rUser) continue;
                    $rCoin = $recentCoins[$rUser->coinid] ?? null;
                    $bal   = Yii::$app->ConversionUtils->bitcoinvaluetoa($rUser->balance);
                    $balD  = $rCoin && $bal > 0 ? "{$bal} {$rCoin->symbol}" : ($bal > 0 ? "{$bal} BTC" : '');
                    $active = $rUser->username === $username;
                ?>
                <div class="flex items-center gap-3 py-2
                            <?= $active ? 'bg-indigo-50/50 dark:bg-indigo-900/10 -mx-4 px-4' : '' ?>">
                    <?php if ($rCoin && !empty($rCoin->image)): ?>
                        <img width="18" height="18" src="<?= Html::encode($rCoin->image) ?>"
                             class="rounded object-contain shrink-0" alt=""
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <span class="w-[18px] shrink-0"></span>
                    <?php endif ?>
                    <span class="flex-1 min-w-0">
                        <?= Html::a(Html::encode($addr),
                            $homeUrl . '?address=' . urlencode($addr),
                            ['class' => 'font-mono text-xs text-gray-700 dark:text-gray-300
                                         hover:text-indigo-600 dark:hover:text-indigo-400
                                         transition-colors truncate block']) ?>
                    </span>
                    <?php if ($balD): ?>
                        <span class="text-xs font-mono text-gray-400 dark:text-gray-500 shrink-0">
                            <?= Html::encode($balD) ?>
                        </span>
                    <?php endif ?>
                    <?php if ($address !== $addr): ?>
                        <img src="/images/base/delete.png" onclick="drop_cookie(this);"
                             class="shrink-0 cursor-pointer opacity-40 hover:opacity-100 transition-opacity" alt="remove">
                    <?php endif ?>
                </div>
                <?php endforeach ?>
                </div>
                <?php endif ?>
            </div>
        </div>

    </div>

    <!-- RIGHT -->
    <div class="flex flex-col gap-4 min-w-0 overflow-x-auto">
        <?php if ($user): ?><div id="found_results"></div><?php endif ?>
    </div>

</div>

<?php endif ?>

<script>
var _username = <?= $jsUsername ?>;
var _homeUrl  = <?= $jsHomeUrl ?>;
var _address  = <?= $jsAddress ?>;

function page_refresh() {
    found_refresh();
    if (_username !== '') {
        main_wallet_refresh();
        main_miners_refresh();
        main_graphs_refresh();
        main_title_refresh();
        main_found_refresh();
    }
}
function select_algo(algo) {
    window.location.href = _homeUrl + 'site/algo?algo=' + encodeURIComponent(algo) + '&r=/site/mining';
}
function main_wallet_refresh() {
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_wallet_results').html(d); if(typeof lucide!=='undefined')lucide.createIcons(); });
}
function main_wallet_refresh_details() {
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username) + '&showdetails=1', '', function(d){ $('#main_wallet_results').html(d); });
}
function main_found_refresh() {
    $.get(_homeUrl + 'site/wallet_found_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_found_results').html(d); });
}
function main_miners_refresh() {
    $.get(_homeUrl + 'site/wallet_miners_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_miners_results').html(d); });
}
function main_title_refresh() {
    $.get(_homeUrl + 'site/title_results?address=' + encodeURIComponent(_username), '', function(d){ document.title = d; });
}
function found_refresh() {
    $.get(_homeUrl + 'site/user_earning_results?address=' + encodeURIComponent(_username), '', function(d){ $('#found_results').html(d); });
}
var _lastGraphUpdate = 0;
function main_graphs_refresh() {
    var now = Date.now() / 1000;
    if (now < _lastGraphUpdate + 900) return;
    _lastGraphUpdate = now;
    $.get(_homeUrl + 'site/wallet_graphs_results?address=' + encodeURIComponent(_username), '', function(d){
        $('#main_graphs_results').html(d);
        $('.graph_algo').each(function(){ main_refresh_hashrate($(this).attr('id')); });
    });
    graph_earnings_refresh();
}
function main_refresh_hashrate(algo) {
    $.get(_homeUrl + 'site/graph_user_results?address=' + encodeURIComponent(_username) + '&algo=' + encodeURIComponent(algo), '', function(data){
        try { yiimpChart('graph_results_' + algo, JSON.parse(data), { labels: ['Raw','Smoothed','Rejected'] }); } catch(e) {}
    });
}
function graph_earnings_refresh() {
    $.get(_homeUrl + 'site/graph_earnings_results?address=' + encodeURIComponent(_username), '', function(data){
        try { yiimpChart('graph_earnings_results', JSON.parse(data), { fill:true, stack:true, labels:['Balance','Pending'], colors:['#4bb2c5','#eaa228'], btcDecimals:true }); } catch(e) {}
    });
}
function main_wallet_tx() {
    window.open(_homeUrl + 'site/tx?address=' + encodeURIComponent(_username), 'YIIMP_tx', 'width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no');
}
function drop_cookie(el) {
    var addr = $(el).closest('tr,div').find('a.address,.address,.font-mono').first().text();
    window.location.href = '?address=' + encodeURIComponent(_address) + '&drop=' + encodeURIComponent(addr.trim());
}
</script>
