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
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?><br/><br/>
<?= Yii::$app->ViewUtils->getAdminWalletLinks($coin, $info, 'wallet') ?>

<style>
table.dataGrid a.red, table.dataGrid a.red:visited, a.red { color: darkred; }
div#main_actions { position:absolute; top:60px; right:16px; width:280px; text-align:right; }
div#markets      { overflow-x:hidden; overflow-y:scroll; max-height:156px; }
div#transactions { overflow-x:hidden; overflow-y:scroll; min-height:200px; max-height:360px; margin-bottom:8px; }
div#sums         { overflow-x:hidden; overflow-y:scroll; min-height:250px; max-height:600px;
                   width:380px; float:left; margin-top:16px; margin-bottom:8px; margin-right:16px; }
.page .footer    { clear:both; width:auto; margin-top:16px; }
tr.ssrow.bestmarket { background-color:#dfd; }
tr.ssrow.disabled   { background-color:#fdd; color:darkred; }
tr.ssrow.orphan     { color:darkred; }
</style>

<div id="main_actions">
<?php foreach ($actionLinks as [$action, $label, , , $confirm]): ?>
<br/><a class="<?= $action === 'deleteearnings' ? 'red' : '' ?>"
        href="/admin/<?= $action ?>?id=<?= $coin->id ?>"
        <?= $confirm ? 'onclick="return confirm(\'Are you sure?\')"' : '' ?>>
    <b><?= strtoupper($label) ?></b>
</a>
<?php endforeach ?>
</div>

<div id="main_results"></div>


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

function main_refresh() {
    clearTimeout(main_timeout);
    $.get('/admin/coinwallet_details?id=<?= $id ?>&rows=<?= $maxrows ?>&since=<?= $since ?>', '', main_ready);
}
function main_ready(data) {
    $('#main_results').html(data);
    $(window).trigger('resize');
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

$(function () { main_refresh(); });
</script>

<?php if ($coin->watch): ?>
<?= Yii::$app->controller->renderPartial('coin_market_graph', ['coin' => $coin]) ?>
<?php Yii::$app->view->registerJs('$(window).resize(graph_resized);') ?>
<?php endif ?>
