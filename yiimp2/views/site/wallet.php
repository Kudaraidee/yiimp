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
</style>

<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<div class="row gx-4 mt-2">
    <div class="col-12 col-md-6 wallet-col">
        <?php if ($user): ?>
        <div id="main_wallet_results" class="ajax-placeholder"></div>
        <div class="main-left-box">
            <div class="main-left-title">Last 24 Hours Balance: <?= Html::encode($username) ?></div>
            <div class="main-left-inner">
                <div class="graph-wrap"><div id="graph_earnings_results" style="height:240px;"></div></div>
                <div style="float:right;margin-top:4px;font-size:.8em;">
                    <span style="color:#4bb2c5;">Balance</span>&nbsp;
                    <span style="color:#eaa228;">Pending</span>
                </div>
                <div style="clear:both;"></div>
            </div>
        </div>
        <div id="main_graphs_results"  class="ajax-placeholder"></div>
        <div id="main_miners_results"  class="ajax-placeholder"></div>
        <div id="main_found_results"   class="ajax-placeholder"></div>
        <?php endif ?>
        <div class="main-left-box">
            <div class="main-left-title">Search Wallet</div>
            <div class="main-left-inner">
                <form action="<?= Html::encode($homeUrl) ?>" method="get" style="padding:10px;">
                    <input type="text" name="address" class="main-text-input" placeholder="Wallet Address">
                    <input type="submit" value="Submit" class="main-submit-button"><br><br>
                    <table class="dataGrid2">
                    <?php foreach ($recents as $addr):
                        if (empty($addr)) continue;
                        $rUser = $recentUsers[$addr] ?? null; if (!$rUser) continue;
                        $rCoin = $recentCoins[$rUser->coinid] ?? null;
                        $bal   = Yii::$app->ConversionUtils->bitcoinvaluetoa($rUser->balance);
                        $balD  = $rCoin && $bal > 0 ? "{$bal} {$rCoin->symbol}" : ($bal > 0 ? "{$bal} BTC" : '');
                        $rowSt = $rUser->username === $username ? 'background-color:#e0d3e8;' : '';
                        $del   = $address !== $addr
                            ? '<img src="/images/base/delete.png" onclick="drop_cookie(this);" style="cursor:pointer;">'
                            : '';
                    ?>
                    <tr class="ssrow" style="<?= Html::encode($rowSt) ?>">
                        <td width="24"><?php if ($rCoin): ?><img width="16" src="<?= Html::encode($rCoin->image) ?>" alt=""><?php endif ?></td>
                        <td><?= Html::a(Html::encode($addr), $homeUrl . '?address=' . urlencode($addr), ['class' => 'address', 'style' => 'font-family:monospace;font-size:1.1em;']) ?></td>
                        <td align="right"><?= Html::encode($balD) ?></td>
                        <td style="width:16px;"><?= $del ?></td>
                    </tr>
                    <?php endforeach ?>
                    </table>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 wallet-col" style="overflow-x:auto;">
        <div id="pool_current_results" class="ajax-placeholder"></div>
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
        <div id="pool_current_results" class="mb-3"></div>
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
        <div id="pool_current_results"></div>
        <?php if ($user): ?><div id="found_results"></div><?php endif ?>
    </div>

</div>

<?php endif ?>

<script>
var _username = <?= $jsUsername ?>;
var _homeUrl  = <?= $jsHomeUrl ?>;
var _address  = <?= $jsAddress ?>;

function page_refresh() {
    pool_current_refresh();
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
function pool_current_refresh() {
    $.get(_homeUrl + 'site/current_results', '', function(d){ $('#pool_current_results').html(d); if(typeof lucide!=='undefined')lucide.createIcons(); });
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
