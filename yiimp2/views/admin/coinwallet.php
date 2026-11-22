<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\components\rpc\WalletRPC;
use app\models\Coins;

$id   = (int) Yii::$app->getRequest()->getQueryParam('id');
$coin = Coins::findOne($id);

if (!$coin) {
    return Yii::$app->ViewUtils->redirectBack();
}

$this->title = 'Wallet — ' . $coin->symbol;
$this->registerMetaTag(['http-equiv' => 'refresh', 'content' => '600']);

if (!empty($coin->algo) && $coin->algo !== 'PoS') {
    Yii::$app->session->set('yaamp-algo', $coin->algo);
}

$remote      = new WalletRPC($coin);
$info        = $remote->error === null ? $remote->getinfo() : false;
$sellamount  = $coin->balance;

$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

$maxrows = (int) Yii::$app->ConversionUtils->arraySafeVal($_REQUEST, 'rows', 500);
$since   = (int) Yii::$app->ConversionUtils->arraySafeVal($_REQUEST, 'since', time() - 7 * 86400);

$actionLinks = [
    ['deleteearnings', 'Delete Earnings', 'danger',    'trash',      true],
    ['clearearnings',  'Clear Earnings',  'warning',   'eraser',     false],
    ['checkblocks',    'Update Blocks',   'secondary', 'refresh-cw', false],
    ['payuserscoin',   'Do Payments',     'primary',   'credit-card',false],
];

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style wallet chrome
     ══════════════════════════════════════════════════════════════════════════ -->
<?php
$walletLinks = str_replace([' || ', '<br/>', '<br />'], '',
    Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet'));
$sidebarLinks = str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks());
$coinImage = Html::encode($coin->image);
$coinName  = Html::encode($coin->name);
$coinSym   = Html::encode($coin->symbol);
?>

<style>
#cw-admin-bar {
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 11px 14px 12px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    color: #334155; font-size: 11px;
}
#cw-bar-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
#cw-bar-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
#cw-coin { display: inline-flex; align-items: center; gap: 7px; min-width: 0; }
#cw-coin img {
    width: 24px; height: 24px; object-fit: contain; background: #fff;
    border: 1px solid #e8eef6; border-radius: 6px; padding: 2px;
}
#cw-coin b { color: #26385a; font-size: 13px; font-weight: 800; }
#cw-coin span { color: #8a94a6; font-size: 10px; }
#cw-live {
    margin-left: auto; display: flex; align-items: center; gap: 6px;
    white-space: nowrap; font-size: 11px; color: #5b6675;
}
#cw-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#cw-live span { color: #98a3b5; font-size: 10px; font-variant-numeric: tabular-nums; }
#cw-bar-links, #cw-bar-actions, #cw-bar-nav {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    border-top: 1px solid #eef2f7; margin-top: 10px; padding-top: 9px;
}
#cw-bar-links a, #cw-bar-nav a, #cw-bar-actions a {
    display: inline-block; border: 1px solid #dfe5ec; background: #fafbfc;
    color: #64748b; border-radius: 999px; padding: 3px 10px;
    font-size: 10px; font-weight: 700; line-height: 1.6; text-decoration: none;
}
#cw-bar-links a:hover, #cw-bar-nav a:hover, #cw-bar-actions a:hover {
    border-color: #b9d4ff; color: #2c5aa0; background: #f7faff;
}
#cw-bar-actions { margin-left: auto; border-top: 0; margin-top: 0; padding-top: 0; }
#cw-bar-actions a.cw-act-danger { color: #b91c1c; border-color: #fecaca; background: #fff8f8; }
#cw-bar-actions a.cw-act-danger:hover { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
#cw-bar-actions a.cw-act-primary { background: #2c5aa0; border-color: #2c5aa0; color: #fff; }
#cw-bar-actions a.cw-act-primary:hover { background: #1f467f; border-color: #1f467f; color: #fff; }
#cw-bar-nav a { font-size: 9px; padding: 2px 9px; }

#main_results .cw-loading { margin: 0; }
#main_results .cw-loading .main-left-title { min-height: 40px; padding: 9px 13px; }
#main_results .cw-sk-title { font-size: 13px; font-weight: 800; color: #26385a; }
#main_results .cw-sk-sub { font-size: 10px; color: #8a94a6; margin-left: 8px; }
#main_results .cw-sk-lines { padding: 14px; display: grid; gap: 9px; }
#main_results .cw-sk-line {
    height: 20px; border-radius: 4px;
    background: linear-gradient(90deg,#f1f5f9 25%,#f8fafc 50%,#f1f5f9 75%);
    background-size: 200% 100%; animation: cw-shimmer 1.4s infinite linear;
}
#main_results .cw-sk-line:nth-child(3n) { width: 72%; }
@keyframes cw-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

@media (max-width: 720px) {
    #cw-live, #cw-bar-actions { margin-left: 0; }
}
</style>

<div id="cw-admin-bar">
    <div id="cw-bar-head">
        <span id="cw-bar-label">Wallet</span>
        <span id="cw-coin"><img src="<?= $coinImage ?>" alt=""><b><?= $coinSym ?></b><span><?= $coinName ?></span></span>
        <span id="cw-live"><i></i> live · 30s <span id="cw-last-update">just now</span></span>
    </div>
    <div id="cw-bar-links"><?= $walletLinks ?></div>
    <div id="cw-bar-actions">
<?php foreach ($actionLinks as [$action, $label, $variant, , $confirm]): ?>
        <a href="/admin/<?= $action ?>?id=<?= $coin->id ?>"
           class="<?= $action === 'deleteearnings' ? 'cw-act-danger' : ($variant === 'primary' ? 'cw-act-primary' : '') ?>"
           <?= $confirm ? 'onclick="return confirm(\'Are you sure?\')"' : '' ?>><?= strtoupper(Html::encode($label)) ?></a>
<?php endforeach ?>
    </div>
    <div id="cw-bar-nav"><?= $sidebarLinks ?></div>
</div>

<div id="main_results">
    <div class="main-left-box cw-loading">
        <div class="main-left-title">
            <span class="cw-sk-title"><?= $coinSym ?> wallet</span>
            <span class="cw-sk-sub">loading balances · markets · transactions…</span>
        </div>
        <div class="cw-sk-lines">
            <div class="cw-sk-line"></div><div class="cw-sk-line"></div>
            <div class="cw-sk-line"></div><div class="cw-sk-line"></div>
            <div class="cw-sk-line"></div>
        </div>
    </div>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet') ?></div>
    <div class="d-flex gap-1 flex-wrap shrink-0">
        <?php foreach ($actionLinks as [$action, $label, $variant, , $confirm]): ?>
        <a href="/admin/<?= $action ?>?id=<?= $coin->id ?>"
           class="btn btn-sm btn-<?= $variant === 'danger' ? 'danger' : 'outline-' . $variant ?>"
           <?= $confirm ? 'onclick="return confirm(\'Delete all earnings for this coin? This cannot be undone.\')"' : '' ?>>
            <?= Html::encode($label) ?>
        </a>
        <?php endforeach ?>
    </div>
</div>

<div id="main_results"></div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
    <div><?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet') ?></div>
    <div class="flex gap-1.5 flex-wrap shrink-0">
        <?php foreach ($actionLinks as [$action, $label, $variant, $icon, $confirm]): ?>
        <?php
        $cls = match ($variant) {
            'danger'    => 'border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            'warning'   => 'border-amber-300 dark:border-amber-700 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'primary'   => 'border-indigo-300 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
            default     => 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600',
        };
        ?>
        <a href="/admin/<?= $action ?>?id=<?= $coin->id ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border bg-white dark:bg-gray-700 transition-colors <?= $cls ?>"
           <?= $confirm ? 'onclick="return confirm(\'Delete all earnings for this coin? This cannot be undone.\')"' : '' ?>>
            <i data-lucide="<?= Html::encode($icon) ?>" class="w-3.5 h-3.5"></i>
            <?= Html::encode($label) ?>
        </a>
        <?php endforeach ?>
    </div>
</div>

<div id="main_results"></div>

<?php endif ?>


<!-- ── Sell-amount dialog ──────────────────────────────────────────────────── -->
<?php if ($isLegacy || !$isTailwind): ?>
<div class="modal fade" id="sell-amount-dialog" tabindex="-1" aria-labelledby="sell-dialog-title" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sell-dialog-title">Send to Exchange</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-1">Address:</p>
        <p><code id="dlgaddr" class="small"></code></p>
        <label class="form-label small">Amount</label>
        <input type="text" id="input_sell_amount" class="form-control form-control-sm" value="<?= Html::encode((string) $sellamount) ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="btn-sell-confirm">Send / Sell</button>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<!-- Tailwind custom dialog (no Bootstrap JS dependency) -->
<div id="sell-amount-dialog" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSellDialog()"></div>
  <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
    <h3 id="sell-dialog-title" class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Send to Exchange</h3>
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Address</div>
    <div id="dlgaddr" class="text-xs font-mono text-gray-700 dark:text-gray-300
                              bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2 mb-4 break-all"></div>
    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Amount</label>
    <input type="text" id="input_sell_amount"
           class="w-full text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-2
                  focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-5"
           value="<?= Html::encode((string) $sellamount) ?>">
    <div class="flex justify-end gap-2">
      <button onclick="closeSellDialog()"
              class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300
                     hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          Cancel
      </button>
      <button id="btn-sell-confirm"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600
                     hover:bg-indigo-700 rounded-lg transition-colors">
          Send / Sell
      </button>
    </div>
  </div>
</div>
<?php endif ?>


<script>
function uninstall_coin() {
    if (!confirm('Uninstall this coin?')) return;
    window.location.href = '/admin/uninstallcoin?id=<?= $coin->id ?>';
}

var main_delay   = 30000;
var main_timeout;
var cwLastUpdate = 0;

function cwTick()
{
    if (!cwLastUpdate) return;
    var age = Math.floor((Date.now() - cwLastUpdate) / 1000);
    $('#cw-last-update').text(age + 's ago · next ' + Math.max(0, main_delay/1000 - age) + 's');
}

function main_refresh() {
    clearTimeout(main_timeout);
    $.get('/admin/coinwallet_details?id=<?= $id ?>&rows=<?= $maxrows ?>&since=<?= $since ?>', '', main_ready);
}
function main_ready(data) {
    $('#main_results').html(data);
    $(window).trigger('resize');
    cwLastUpdate = Date.now();
    cwTick();
    main_timeout = setTimeout(main_refresh, main_delay);
    <?php if ($isTailwind): ?>
    if (typeof lucide !== 'undefined') lucide.createIcons();
    <?php endif ?>
}
function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}

function showSellAmountDialog(marketname, address, marketid, bookmarkid) {
    marketid   = marketid   || 0;
    bookmarkid = bookmarkid || 0;

    document.getElementById('sell-dialog-title').textContent =
        'Send <?= addslashes($coin->symbol) ?> to ' + marketname;
    document.getElementById('dlgaddr').textContent = address;

    document.getElementById('btn-sell-confirm').onclick = function () {
        var amount = document.getElementById('input_sell_amount').value;
        if (marketid > 0)
            window.location.href = '/admin/market/sellto?id=' + marketid + '&amount=' + amount;
        else
            window.location.href = '/admin/bookmark-send?id=' + bookmarkid + '&amount=' + amount;
    };

    <?php if ($isTailwind): ?>
    var dlg = document.getElementById('sell-amount-dialog');
    dlg.classList.remove('hidden');
    dlg.classList.add('flex');
    <?php else: ?>
    new bootstrap.Modal(document.getElementById('sell-amount-dialog')).show();
    <?php endif ?>
    return false;
}

<?php if ($isTailwind): ?>
function closeSellDialog() {
    var dlg = document.getElementById('sell-amount-dialog');
    dlg.classList.add('hidden');
    dlg.classList.remove('flex');
}
<?php endif ?>

$(function () {
    setInterval(cwTick, 1000);
    main_refresh();
});
</script>

<?php if ($coin->watch): ?>
<?= Yii::$app->controller->renderPartial('coin_market_graph', ['coin' => $coin]) ?>
<?php Yii::$app->view->registerJs('$(window).resize(graph_resized);') ?>
<?php endif ?>
