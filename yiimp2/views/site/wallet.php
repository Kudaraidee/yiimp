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
$coin = null;
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

// ── Dashboard shell data ───────────────────────────────────────────────────────
$payoutSymbol = 'BTC';
if ($user && isset($coin) && $coin) $payoutSymbol = (string) $coin->symbol;
$walletInitials = strtoupper(substr($payoutSymbol, 0, 3));
$shortAddress   = $username !== ''
    ? substr($username, 0, 8) . '…' . substr($username, -6)
    : '';
$walletFormAction = Yii::$app->YiimpUtils->walletUrl();

// Reusable dashboard fragments — rendered by all three layouts.
$renderWalletHero = function () use ($username, $shortAddress, $payoutSymbol, $walletInitials, $coin): void {
    if ($username === '') return;
    ?>
    <section class="wd-hero">
        <div class="wd-hero-main">
            <span class="wd-avatar"><?= Html::encode($walletInitials) ?>
                <?php if ($coin && !empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" alt="" onerror="this.remove()">
                <?php endif ?>
            </span>
            <div class="wd-identity">
                <div class="wd-eyebrow">Wallet Overview</div>
                <div class="wd-address-line">
                    <span class="wd-address" title="<?= Html::encode($username) ?>"><?= Html::encode($shortAddress) ?></span>
                    <button type="button" class="wd-copy" data-copy="<?= Html::encode($username) ?>"
                            onclick="copyWalletAddress(this)">Copy</button>
                    <span id="wd-status" class="wd-status">● Checking</span>
                </div>
                <div class="wd-meta">Payout <b><?= Html::encode($payoutSymbol) ?></b> · auto refresh 60s · values in payout coin</div>
            </div>
        </div>
        <div class="wd-actions">
            <button type="button" class="wd-btn primary" onclick="main_wallet_refresh()">Refresh</button>
            <button type="button" class="wd-btn" onclick="main_wallet_refresh_details()">Details</button>
        </div>
    </section>
    <?php
};

$renderWalletKpis = function () use ($payoutSymbol): void {
    ?>
    <section class="wd-kpis" aria-label="Wallet summary">
        <div class="wd-kpi accent"><span>Balance</span><b id="kpi-balance">—</b><small><?= Html::encode($payoutSymbol) ?></small></div>
        <div class="wd-kpi"><span>Pending</span><b id="kpi-pending">—</b><small>confirmed</small></div>
        <div class="wd-kpi"><span>Hashrate</span><b id="kpi-hashrate">—</b><small>active algo</small></div>
        <div class="wd-kpi"><span>Workers</span><b id="kpi-workers">—</b><small>connections</small></div>
        <div class="wd-kpi"><span>Blocks</span><b id="kpi-blocks">—</b><small>recent</small></div>
    </section>
    <?php
};

$renderWalletSwitch = function () use ($walletFormAction, $recents, $recentUsers, $recentCoins, $username, $address): void {
    ?>
    <section id="wallet-search-card" class="wd-switch">
        <div class="wd-switch-head">
            <span class="wd-switch-title">Switch Wallet</span>
            <span class="wd-switch-hint">Recent wallets are stored in this browser for 30 days</span>
        </div>
        <div class="wd-switch-body">
            <form action="<?= Html::encode($walletFormAction) ?>" method="get" class="wd-switch-form">
                <input type="text" name="address" placeholder="Wallet address" autocomplete="off">
                <button type="submit">Go</button>
            </form>
            <?php if ($recents): ?>
                <div class="wd-recent">
                    <?php foreach ($recents as $addr):
                        if (empty($addr)) continue;
                        $rUser = $recentUsers[$addr] ?? null; if (!$rUser) continue;
                        $rCoin = $recentCoins[$rUser->coinid] ?? null;
                        $active = $rUser->username === $username;
                        $short = substr($addr, 0, 6) . '…' . substr($addr, -4);
                    ?>
                        <span class="wd-chip-wallet <?= $active ? 'active' : '' ?>">
                            <?php if ($rCoin && !empty($rCoin->image)): ?>
                                <img src="<?= Html::encode($rCoin->image) ?>" alt="" onerror="this.remove()">
                            <?php endif ?>
                            <a class="address" href="<?= Html::encode(Yii::$app->YiimpUtils->walletUrl($addr)) ?>"
                               title="<?= Html::encode($addr) ?>"><?= Html::encode($short) ?></a>
                            <?php if ($address !== $addr): ?>
                                <img class="wd-chip-del" src="/images/base/delete.png" alt="remove"
                                     data-address="<?= Html::encode($addr) ?>" onclick="drop_cookie(this);">
                            <?php endif ?>
                        </span>
                    <?php endforeach ?>
                </div>
            <?php else: ?>
                <div class="wd-empty-recent">No recent wallets</div>
            <?php endif ?>
        </div>
    </section>
    <?php
};

?>

<style>
/* ── Wallet dashboard shell (shared by Legacy / AdminLTE / Tailwind) ─────────── */
#wallet-cards {
    --wd-ink: #26385a;
    --wd-blue: #2c5aa0;
    --wd-line: #d8e2ef;
    --wd-soft: #f8fafc;
}
#wallet-cards .wd-hero,
#wallet-cards .wd-switch {
    border: 1px solid var(--wd-line) !important;
    border-radius: 10px !important;
    box-shadow: 0 1px 3px rgba(20,40,80,.05);
    background: #fff;
    overflow: hidden;
    margin-bottom: 14px;
}
#wallet-cards .wd-hero {
    position: relative;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #eef4ff 100%);
}
#wallet-cards .wd-hero::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
    background: linear-gradient(180deg, #4f79c8, #2c5aa0);
}
#wallet-cards .wd-hero-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
#wallet-cards .wd-avatar {
    position: relative; flex: 0 0 44px; height: 44px; border-radius: 10px;
    display: grid; place-items: center; overflow: hidden;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
    color: #fff; font-size: 8px; font-weight: 800; letter-spacing: .04em;
}
#wallet-cards .wd-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#wallet-cards .wd-identity { min-width: 0; }
#wallet-cards .wd-eyebrow {
    font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #8a94a6;
}
#wallet-cards .wd-address-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 2px; }
#wallet-cards .wd-address {
    font: 700 15px/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    color: var(--wd-ink); overflow-wrap: anywhere;
}
#wallet-cards .wd-copy,
#wallet-cards .wd-btn {
    border: 1px solid #d7e5ff; border-radius: 6px; background: #eef4ff; color: var(--wd-blue);
    font-size: 10px; font-weight: 700; padding: 4px 9px; cursor: pointer; line-height: 1.4;
}
#wallet-cards .wd-copy:hover { background: #e2ecff; }
#wallet-cards .wd-btn { padding: 6px 11px; font-size: 11px; }
#wallet-cards .wd-btn.primary { background: var(--wd-blue); border-color: var(--wd-blue); color: #fff; }
#wallet-cards .wd-btn.primary:hover { background: #234a84; }
#wallet-cards .wd-status {
    border-radius: 999px; padding: 2px 8px; font-size: 9px; font-weight: 700;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
}
#wallet-cards .wd-status.active { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#wallet-cards .wd-meta { margin-top: 4px; font-size: 10px; color: #8a94a6; }
#wallet-cards .wd-meta b { color: #475569; }
#wallet-cards .wd-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }

#wallet-cards .wd-kpis {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 14px;
}
#wallet-cards .wd-kpi {
    min-width: 0; padding: 10px 12px; border: 1px solid var(--wd-line);
    border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(20,40,80,.03);
}
#wallet-cards .wd-kpi span {
    display: block; font-size: 9px; font-weight: 800; letter-spacing: .1em;
    text-transform: uppercase; color: #98a3b5;
}
#wallet-cards .wd-kpi b {
    display: block; margin-top: 4px; color: var(--wd-ink);
    font: 700 15px/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
#wallet-cards .wd-kpi.accent b { color: var(--wd-blue); }
#wallet-cards .wd-kpi small { display: block; margin-top: 2px; font-size: 9px; color: #98a3b5; }

/* ── Section dividers ─────────────────────────────────────────────────────── */
#wallet-cards .wd-section {
    display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
    margin: 20px 0 10px; padding: 0 2px 6px;
    border-bottom: 1px solid var(--wd-line);
}
#wallet-cards .wd-section-title {
    font-size: 11px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase;
    color: var(--wd-blue);
}
#wallet-cards .wd-section-sub { font-size: 9px; color: #98a3b5; }

#wallet-cards .wd-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px; align-items: start;
}
#wallet-cards .wd-grid-single { grid-template-columns: 1fr; }
/* Pair the two summary/payout cards inside the shared AJAX container */
#wallet-cards #main_wallet_results {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px; align-items: start;
}
#wallet-cards .wd-col { min-width: 0; }
#wallet-cards .wd-col > * { margin-bottom: 14px; }
#wallet-cards .wd-grid > .ajax-placeholder,
#wallet-cards .wd-grid-single > * { min-width: 0; }

#wallet-cards .wd-switch-head {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; background: var(--wd-soft); border-bottom: 1px solid #e8eef6;
}
#wallet-cards .wd-switch-title { font-size: 12px; font-weight: 800; color: var(--wd-ink); }
#wallet-cards .wd-switch-hint { font-size: 9px; color: #98a3b5; }
#wallet-cards .wd-switch-body { padding: 12px 14px; }
#wallet-cards .wd-switch-form { display: flex; gap: 8px; }
#wallet-cards .wd-switch-form input {
    flex: 1 1 auto; min-width: 0; padding: 8px 10px; border: 1px solid var(--wd-line);
    border-radius: 7px; font: 11px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #334155;
}
#wallet-cards .wd-switch-form input:focus { outline: none; border-color: var(--wd-blue); box-shadow: 0 0 0 2px rgba(44,90,160,.12); }
#wallet-cards .wd-switch-form button {
    flex: 0 0 auto; padding: 8px 16px; border: 1px solid var(--wd-blue); border-radius: 7px;
    background: var(--wd-blue); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer;
}
#wallet-cards .wd-switch-form button:hover { background: #234a84; }
#wallet-cards .wd-recent { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
#wallet-cards .wd-chip-wallet {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px;
    border: 1px solid #e2e8f0; border-radius: 999px; background: var(--wd-soft);
    font: 10px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #475569;
}
#wallet-cards .wd-chip-wallet.active { background: #eef4ff; border-color: #d7e5ff; color: var(--wd-blue); font-weight: 700; }
#wallet-cards .wd-chip-wallet img { width: 14px; height: 14px; object-fit: contain; }
#wallet-cards .wd-chip-wallet a { color: inherit; text-decoration: none; }
#wallet-cards .wd-chip-wallet a:hover { text-decoration: underline; }
#wallet-cards .wd-chip-del { width: 12px; height: 12px; cursor: pointer; opacity: .6; }
#wallet-cards .wd-chip-del:hover { opacity: 1; }
#wallet-cards .wd-empty-recent { margin-top: 10px; font-size: 10px; color: #98a3b5; }

@media(max-width:900px){
    #wallet-cards .wd-grid,
    #wallet-cards #main_wallet_results { grid-template-columns: 1fr; }
    #wallet-cards .wd-section { margin-top: 16px; }
}
@media(max-width:640px){
    #wallet-cards .wd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    #wallet-cards .wd-hero { flex-direction: column; align-items: flex-start; }
    #wallet-cards .wd-actions { justify-content: flex-start; }
    #wallet-cards .wd-address { font-size: 13px; }
    #wallet-cards .wd-switch-hint { display: none; }
}
</style>


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

/* ── Miners card internals ──────────────────────────────────────────────── */
#wallet-cards .wm-section-label {
    padding: 8px 12px 3px; background: #fff;
    font-size: 9px; font-weight: 800; letter-spacing: .1em;
    text-transform: uppercase; color: #8a94a6;
}
#wallet-cards .wm-algo-strip {
    display: flex; gap: 8px; flex-wrap: wrap; padding: 0 12px 10px; background: #fff;
}
#wallet-cards .wm-algo-pill {
    display: inline-flex; align-items: center; gap: 7px; padding: 5px 10px;
    border: 1px solid #d7e5ff; border-radius: 7px; background: #f6f9ff;
    font-size: 10px; font-weight: 700; color: var(--wd-blue);
}
#wallet-cards .wm-algo-bolt { font-size: 11px; }
#wallet-cards .wm-algo-pill b { color: #475569; font-weight: 700; }
#wallet-cards .wm-algo-pill span { color: #8a94a6; font-weight: 600; }
#wallet-cards .wm-worker { display: inline-flex; align-items: flex-start; gap: 7px; }
#wallet-cards .wm-worker-dot {
    flex: 0 0 6px; height: 6px; margin-top: 5px; border-radius: 50%; background: #cbd5e1;
}
#wallet-cards .wm-worker-dot.active { background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); }
#wallet-cards .wm-worker-main { display: inline-flex; flex-direction: column; gap: 2px; min-width: 0; }
#wallet-cards .wm-worker-name { font-size: 11px; font-weight: 700; color: #334155; }
#wallet-cards .wm-worker-sub { font-size: 9px; color: #98a3b5; line-height: 1.4; word-break: break-all; }
#wallet-cards .wm-tag-es {
    display: inline-block; margin-left: 4px; padding: 1px 5px; border-radius: 4px;
    background: #e6fbfb; border: 1px solid #c7f0ee; color: #0f766e;
    font-size: 8px; font-weight: 800; letter-spacing: .04em; vertical-align: 1px;
}
#wallet-cards .wm-tag-tls {
    display: inline-block; margin-left: 4px; padding: 1px 5px; border-radius: 4px;
    background: #eef2ff; border: 1px solid #dbe4ff; color: #3730a3;
    font-size: 8px; font-weight: 800; letter-spacing: .04em; vertical-align: 1px;
}

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

<div id="wallet-cards" class="mt-2">
    <?php $renderWalletHero(); ?>
    <?php if ($user): ?>
    <?php $renderWalletKpis(); ?>

    <div class="wd-section">
        <span class="wd-section-title">Performance</span>
        <span class="wd-section-sub">Last 24 hours · charts auto-refresh every 60s</span>
    </div>
    <div class="wd-grid">
        <div class="wd-col">
            <div id="wallet-balance-card" class="main-left-box">
                <div class="main-left-title">
                    <span class="wl-head-main">
                        <span class="wl-title">Last 24 Hours Balance</span>
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
        </div>
        <div class="wd-col">
            <div id="main_graphs_results" class="ajax-placeholder"></div>
        </div>
    </div>

    <div class="wd-section">
        <span class="wd-section-title">Wallet &amp; Workers</span>
        <span class="wd-section-sub">Summary, payouts &amp; connected miners · auto-refresh 60s</span>
    </div>
    <div id="main_wallet_results" class="ajax-placeholder"></div>
    <div id="main_miners_results" class="ajax-placeholder"></div>

    <div class="wd-section">
        <span class="wd-section-title">Activity</span>
        <span class="wd-section-sub">Blocks &amp; earnings · newest first · auto-refresh 60s</span>
    </div>
    <div class="wd-grid wd-grid-single">
        <div id="main_found_results" class="ajax-placeholder"></div>
        <div id="found_results"       class="ajax-placeholder"></div>
    </div>
    <?php endif ?>
    <?php $renderWalletSwitch(); ?>
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

<div id="wallet-cards" class="mt-2">
    <?php $renderWalletHero(); ?>
    <?php if ($user): ?>
    <?php $renderWalletKpis(); ?>

    <div class="wd-section">
        <span class="wd-section-title">Performance</span>
        <span class="wd-section-sub">Last 24 hours · charts auto-refresh every 60s</span>
    </div>
    <div class="wd-grid">
        <div class="wd-col">
            <div class="card shadow-sm">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-graph-up me-1"></i>Last 24h Balance
                </div>
                <div class="card-body">
                    <div id="graph_earnings_results" style="height:200px;"></div>
                    <div class="d-flex justify-content-end gap-3 mt-1" style="font-size:.75rem;">
                        <span style="color:#4bb2c5;">● Balance</span>
                        <span style="color:#eaa228;">● Pending</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="wd-col">
            <div id="main_graphs_results"></div>
        </div>
    </div>

    <div class="wd-section">
        <span class="wd-section-title">Wallet &amp; Workers</span>
        <span class="wd-section-sub">Summary, payouts &amp; connected miners · auto-refresh 60s</span>
    </div>
    <div id="main_wallet_results"></div>
    <div id="main_miners_results"></div>

    <div class="wd-section">
        <span class="wd-section-title">Activity</span>
        <span class="wd-section-sub">Blocks &amp; earnings · newest first · auto-refresh 60s</span>
    </div>
    <div class="wd-grid wd-grid-single">
        <div id="main_found_results"></div>
        <div id="found_results"></div>
    </div>
    <?php endif ?>
    <?php $renderWalletSwitch(); ?>
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

<div id="wallet-cards" class="mt-2">
    <?php $renderWalletHero(); ?>
    <?php if ($user): ?>
    <?php $renderWalletKpis(); ?>

    <div class="wd-section">
        <span class="wd-section-title">Performance</span>
        <span class="wd-section-sub">Last 24 hours · charts auto-refresh every 60s</span>
    </div>
    <div class="wd-grid">
        <div class="wd-col">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700
                        bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                            flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4 text-indigo-500 shrink-0"></i>
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Last 24h Balance
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
        </div>
        <div class="wd-col">
            <div id="main_graphs_results"></div>
        </div>
    </div>

    <div class="wd-section">
        <span class="wd-section-title">Wallet &amp; Workers</span>
        <span class="wd-section-sub">Summary, payouts &amp; connected miners · auto-refresh 60s</span>
    </div>
    <div id="main_wallet_results"></div>
    <div id="main_miners_results"></div>

    <div class="wd-section">
        <span class="wd-section-title">Activity</span>
        <span class="wd-section-sub">Blocks &amp; earnings · newest first · auto-refresh 60s</span>
    </div>
    <div class="wd-grid wd-grid-single">
        <div id="main_found_results"></div>
        <div id="found_results"></div>
    </div>
    <?php endif ?>
    <?php $renderWalletSwitch(); ?>
</div>

<?php endif ?>

<script>
var _username = <?= $jsUsername ?>;
var _homeUrl  = <?= $jsHomeUrl ?>;
var _address  = <?= $jsAddress ?>;

function kpiSet(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = (value === '' || value === null || typeof value === 'undefined') ? '—' : value;
}
function summaryValue(label) {
    var val = '';
    $('#main_wallet_results tr').each(function () {
        var cells = $(this).find('td');
        if (cells.length >= 2 && $.trim(cells.eq(cells.length - 2).text()) === label) {
            val = $.trim(cells.last().text());
            return false;
        }
    });
    return val;
}
function countIn(scope, re) {
    var m = $.trim(scope.text()).match(re);
    return m ? m[1] : '';
}
function connectionCount(scope) {
    var found = '';
    scope.find('span,div,small').each(function () {
        var text = $.trim($(this).text());
        if (text.length > 40) return;
        var m = text.match(/^(\d+)\s*connections?$/i);
        if (m) { found = m[1]; return false; }
    });
    return found;
}
function shownCount(scope) {
    var found = '';
    scope.find('span,div,small').each(function () {
        var text = $.trim($(this).text());
        if (text.length > 40) return;
        var m = text.match(/^(\d+)\s*(?:shown|blocks found)$/i);
        if (m) { found = m[1]; return false; }
    });
    return found || countIn(scope, /\b(\d+)\s*shown\b/i);
}
function updateWalletKpis() {
    if (_username === '') return;

    kpiSet('kpi-balance', summaryValue('Balance'));
    kpiSet('kpi-pending', summaryValue('Pending (confirmed)'));

    var $miners  = $('#main_miners_results');
    var workers  = connectionCount($miners);
    var rate     = $.trim($miners.find('.wm-table tbody tr').first().find('td').eq(2).text());
    kpiSet('kpi-workers', workers === '' ? '—' : workers + ' conn');
    kpiSet('kpi-hashrate', rate);

    var $blocks = $('#main_found_results');
    var blocks  = shownCount($blocks);
    kpiSet('kpi-blocks', blocks === '' ? '—' : blocks + ' recent');

    var $status = $('#wd-status');
    if ($status.length) {
        var online = workers !== '' && parseInt(workers, 10) > 0;
        $status.toggleClass('active', online)
                .text(online ? '● Active' : (workers === '' ? '● Checking' : '○ Idle'));
    }
}
function fallbackCopy(text, done) {
    var $t = $('<textarea>').val(text).css({position:'fixed', top:'-1000px'}).appendTo('body');
    $t.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    $t.remove();
}
function copyWalletAddress(btn) {
    var text = $(btn).data('copy') || '';
    if (!text) return;
    function done() {
        var $b = $(btn), old = $b.text();
        $b.text('Copied');
        setTimeout(function () { $b.text(old); }, 1200);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
    } else {
        fallbackCopy(text, done);
    }
}
$(function () { updateWalletKpis(); });

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
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_wallet_results').html(d); updateWalletKpis(); if(typeof lucide!=='undefined')lucide.createIcons(); });
}
function main_wallet_refresh_details() {
    $.get(_homeUrl + 'site/wallet_results?address=' + encodeURIComponent(_username) + '&showdetails=1', '', function(d){ $('#main_wallet_results').html(d); updateWalletKpis(); });
}
function main_found_refresh() {
    $.get(_homeUrl + 'site/wallet_found_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_found_results').html(d); updateWalletKpis(); });
}
function main_miners_refresh() {
    $.get(_homeUrl + 'site/wallet_miners_results?address=' + encodeURIComponent(_username), '', function(d){ $('#main_miners_results').html(d); updateWalletKpis(); });
}
function main_title_refresh() {
    $.get(_homeUrl + 'site/title_results?address=' + encodeURIComponent(_username), '', function(d){ document.title = d; });
}
function found_refresh() {
    $.get(_homeUrl + 'site/user_earning_results?address=' + encodeURIComponent(_username), '', function(d){ $('#found_results').html(d); updateWalletKpis(); });
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
    var addr = $(el).data('address') || $(el).closest('tr,div').find('a.address,.address,.font-mono').first().text();
    window.location.href = <?= json_encode(Yii::$app->YiimpUtils->walletUrl()) ?> +
        (_address !== '' ? '/' + encodeURIComponent(_address) : '') +
        '?drop=' + encodeURIComponent(String(addr).trim());
}
</script>
