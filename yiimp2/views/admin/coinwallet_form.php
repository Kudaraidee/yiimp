<?php

/** @var yii\web\View  $this */
/** @var app\models\Coins  $coin */
/** @var bool          $update */
/** @var array         $algos */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\jui\Tabs;
use app\components\CUFHtml;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$this->title = $update ? 'Edit — ' . $coin->name : 'New Coin';
$backUrl     = $coin->id ? "/admin/coinwallet?id={$coin->id}" : '/admin/coinwallets';

// ── RPC defaults (for Daemon tab) ─────────────────────────────────────────────
if (empty($coin->rpcport))   $coin->rpcport   = $coin->id * 10;
if (empty($coin->rpcuser))   $coin->rpcuser   = 'yiimprpc';
if (empty($coin->rpcpasswd)) $coin->rpcpasswd = preg_replace("|[^\w]|m", '', base64_encode(pack('H*', md5(time() . YIIMP_SITE_URL))));

$port    = Yii::$app->YiimpUtils->getAlgoPort($coin->algo);
$dedport = $coin->dedicatedport;

// Mining-style header / KPI values
$stratumPort = !empty($dedport) ? $dedport : $port;
$coinStatus  = $coin->enable ? 'Enabled' : 'Disabled';
$statusClass = $coin->enable ? 'good' : 'warn';
$heightText  = $coin->block_height ? number_format((int) $coin->block_height) : '—';
$marketText  = !empty($coin->market) && $coin->market !== 'unknown'
    ? $coin->market : '—';
$algoText    = !empty($coin->algo) ? $coin->algo : '—';
$symbolText  = !empty($coin->symbol) ? $coin->symbol : 'NEW';

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY — mining-style coin properties editor
     ══════════════════════════════════════════════════════════════════════════ -->
<?php
$coin_algo = $coin->algo
    ? '<span class="cw-algo-ok">' . Html::encode($coin->algo) . '</span>'
    : '<span class="cw-algo-none">None</span>';
?>
<style>
#coinwallet_form_results { display:flex; flex-direction:column; gap:16px; }
#coinwallet_form_results .cw-toolbar {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:9px 12px; margin:0; border:1px solid #d8e2ef; border-radius:7px;
    background:#fff; box-shadow:0 1px 2px rgba(20,40,80,.04);
}
#coinwallet_form_results .cw-toolbar-brand {
    display:flex; align-items:center; gap:9px; padding-right:12px;
    border-right:1px solid #e8eef6;
}
#coinwallet_form_results .cw-live-dot {
    width:8px; height:8px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#coinwallet_form_results .cw-brand-text { display:flex; flex-direction:column; line-height:1.25; }
#coinwallet_form_results .cw-brand-text b { color:#26385a; font-size:13px; }
#coinwallet_form_results .cw-brand-text small { color:#8a94a6; font-size:9px; }
#coinwallet_form_results .mc-badge, #coinwallet_form_results .cw-toolbar .mc-badge {
    display:inline-block; padding:2px 7px; border-radius:999px; background:#eef4ff;
    border:1px solid #d7e5ff; color:#2c5aa0; font-size:9px; font-weight:700;
    line-height:14px; white-space:nowrap; text-decoration:none;
}
#coinwallet_form_results .cw-toolbar-links { display:flex; flex-wrap:wrap; gap:6px; flex:1 1 auto; }
#coinwallet_form_results .cw-toolbar-links a,
#coinwallet_form_results .cw-back {
    display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #dfe7f2;
    background:#f8fafc; color:#4a586c; font-size:10px; line-height:15px;
    text-decoration:none; white-space:nowrap;
}
#coinwallet_form_results .cw-toolbar-links a:hover, #coinwallet_form_results .cw-back:hover {
    background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none;
}
#coinwallet_form_results .cw-back.muted { color:#98a3b5; cursor:default; }

#coinwallet_form_results .cw-summary {
    background:#f8fbff; border:1px solid #d8e2ef; border-left:3px solid #2c5aa0;
    border-radius:6px; padding:8px 14px; display:flex; gap:22px; flex-wrap:wrap;
    font-size:12px; color:#4a586c;
}
#coinwallet_form_results .cw-summary b { color:#223a5e; }
#coinwallet_form_results .cw-summary .sep { color:#ccd4e0; }

#coinwallet_form_results .cw-kpis {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:12px;
}
#coinwallet_form_results .cw-kpi {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff;
    padding:8px 10px; min-width:0;
}
#coinwallet_form_results .cw-kpi .k-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#coinwallet_form_results .cw-kpi .k-value {
    display:block; margin-top:3px; font-family:monospace; font-size:15px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#coinwallet_form_results .cw-kpi .k-sub { display:block; margin-top:2px; font-size:9px; color:#8a94a6; }
#coinwallet_form_results .cw-kpi.accent .k-value { color:#2c5aa0; }
#coinwallet_form_results .cw-kpi.good .k-value { color:#2fae5a; }
#coinwallet_form_results .cw-kpi.warn .k-value { color:#c2410c; }

#coinwallet_form_results .main-left-box {
    border:1px solid #d8e2ef; border-radius:7px; background:#fff;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden; margin:0;
}
#coinwallet_form_results .main-left-title {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px; background:#f8fafc;
    border-bottom:1px solid #e8eef6; color:#26385a; font-size:13px; font-weight:800;
}
#coinwallet_form_results .mn-head-main { display:flex; align-items:baseline; gap:9px; flex-wrap:wrap; min-width:0; }
#coinwallet_form_results .mn-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#coinwallet_form_results .main-left-inner.cw-card-inner { padding:14px 16px 16px; }

#coinwallet_form_results .cw-uniform { margin:0 !important; }
#coinwallet_form_results .cw-fieldset { border:0 !important; margin:0 !important; padding:0 !important; }
#coinwallet_form_results .ctrlHolder {
    float:none !important; clear:none !important; width:auto !important;
    margin:0 0 10px !important; padding:0 !important; border:0 !important;
    background:transparent !important;
}
#coinwallet_form_results .ctrlHolder > div[style*="clear"] { display:none; }
#coinwallet_form_results .ctrlHolder label[for] {
    display:block; margin:0 0 3px !important; padding:0 !important; float:none !important;
    width:auto !important; color:#4a586c; font-size:10px; font-weight:800;
    letter-spacing:.02em; text-transform:none;
}
#coinwallet_form_results .ctrlHolder label[for][required]::after { content:' *'; color:#c2410c; }
#coinwallet_form_results .ctrlHolder label:not([for]) {
    display:inline !important; margin:0 !important; padding:0 !important;
    color:inherit; font-size:inherit; font-weight:inherit; letter-spacing:normal;
}
#coinwallet_form_results .ctrlHolder .textInput,
#coinwallet_form_results .ctrlHolder input[type="text"],
#coinwallet_form_results .ctrlHolder select {
    box-sizing:border-box; width:100% !important; height:auto !important;
    padding:5px 9px !important; border:1px solid #dfe5ec !important; border-radius:7px !important;
    background:#fafbfc !important; color:#26385a !important;
    font:11px/1.4 inherit !important; outline:0 !important; box-shadow:none !important;
}
#coinwallet_form_results .ctrlHolder .textInput:focus,
#coinwallet_form_results .ctrlHolder input[type="text"]:focus,
#coinwallet_form_results .ctrlHolder select:focus {
    border-color:#a9c4ea !important; background:#fff !important;
}
#coinwallet_form_results .ctrlHolder input[readonly].textInput {
    background:#f3f6fa !important; color:#8a94a6 !important; cursor:default;
}
#coinwallet_form_results .ctrlHolder input[type="checkbox"] {
    width:15px !important; height:15px !important; margin:0 4px 0 0;
    accent-color:#2c5aa0; vertical-align:middle;
}
#coinwallet_form_results .formHint2 {
    margin:3px 0 0 !important; padding:0 !important; color:#8a94a6;
    font-size:10px; line-height:1.35;
}
#coinwallet_form_results .errorField {
    margin:0 0 6px; padding:5px 8px; border-radius:6px;
    border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; font-size:10px;
}
#coinwallet_form_results .cw-algo-ok { color:#2fae5a; font-weight:800; }
#coinwallet_form_results .cw-algo-none { color:#c2410c; font-weight:800; }

/* Yii's jui Tabs adds ui-* classes at runtime; style the raw markup as well. */
#coinwallet_form_results .cw-fieldset > div { border:0 !important; background:transparent !important; padding:0 !important; }
#coinwallet_form_results .cw-fieldset > div > ul {
    display:flex !important; gap:4px; flex-wrap:wrap; float:none !important;
    border:0 !important; border-radius:8px !important; background:#f8fafc !important;
    padding:4px !important; margin:0 0 12px !important; list-style:none !important;
}
#coinwallet_form_results .cw-fieldset > div > ul > li {
    float:none !important; margin:0 !important; border:0 !important;
    background:transparent !important; border-radius:6px !important;
}
#coinwallet_form_results .cw-fieldset > div > ul > li > a {
    display:block; padding:6px 12px; border-radius:6px !important;
    color:#5b6675; font-size:11px; font-weight:700; text-decoration:none;
}
#coinwallet_form_results .cw-fieldset > div > div[id*="-tab"] {
    border:1px solid #e8eef6 !important; background:#fff !important;
    border-radius:7px !important; padding:14px !important;
}
#coinwallet_form_results .cw-fieldset > div > ul > li.ui-tabs-active {
    background:#fff !important; border:1px solid #d8e2ef !important;
    box-shadow:0 1px 2px rgba(20,40,80,.05);
}
#coinwallet_form_results .cw-fieldset > div > ul > li.ui-tabs-active > a { color:#2c5aa0; }

#coinwallet_form_results .ui-tabs { border:0 !important; background:transparent !important; padding:0 !important; }
#coinwallet_form_results .ui-tabs .ui-tabs-nav {
    display:flex !important; gap:4px; flex-wrap:wrap; float:none !important;
    border:0 !important; border-radius:8px !important; background:#f8fafc !important;
    padding:4px !important; margin:0 0 12px !important;
}
#coinwallet_form_results .ui-tabs .ui-tabs-nav li {
    float:none !important; margin:0 !important; border:0 !important;
    background:transparent !important; border-radius:6px !important;
}
#coinwallet_form_results .ui-tabs .ui-tabs-nav li a {
    display:block; padding:6px 12px; border-radius:6px !important;
    color:#5b6675; font-size:11px; font-weight:700; text-decoration:none;
}
#coinwallet_form_results .ui-tabs .ui-tabs-nav li.ui-tabs-active {
    background:#fff !important; border:1px solid #d8e2ef !important;
    box-shadow:0 1px 2px rgba(20,40,80,.05);
}
#coinwallet_form_results .ui-tabs .ui-tabs-nav li.ui-tabs-active a { color:#2c5aa0; }
#coinwallet_form_results .ui-tabs .ui-tabs-panel {
    border:1px solid #e8eef6 !important; background:#fff !important;
    border-radius:7px !important; padding:14px !important;
}
#coinwallet_form_results .ui-tabs hr { border:0; border-top:1px solid #e8eef6; margin:14px 0 10px; }
#coinwallet_form_results .ui-tabs b { color:#26385a; font-size:11px; }
#coinwallet_form_results .ui-tabs pre {
    margin:6px 0 0; padding:12px; border:1px solid #e8eef6; border-radius:7px;
    background:#fcfdff; color:#33405a; font:11px/1.5 monospace;
    overflow-x:auto; white-space:pre;
}

#coinwallet_form_results .cw-actions {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:12px 16px; border-top:1px solid #e8eef6; background:#f8fafc;
}
#coinwallet_form_results .cw-save {
    display:inline-block; padding:6px 14px; border-radius:7px; cursor:pointer;
    border:1px solid #2c5aa0; background:#2c5aa0; color:#fff;
    font-size:11px; font-weight:700; text-decoration:none;
    box-shadow:0 1px 2px rgba(20,40,80,.08);
}
#coinwallet_form_results .cw-save:hover { background:#244b86; border-color:#244b86; color:#fff; text-decoration:none; }
#coinwallet_form_results .cw-cancel {
    display:inline-block; padding:6px 12px; border-radius:7px;
    border:1px solid #dfe5ec; background:#fff; color:#5b6675;
    font-size:11px; font-weight:700; text-decoration:none;
}
#coinwallet_form_results .cw-cancel:hover { background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none; }
@media (max-width:900px) {
    #coinwallet_form_results .cw-toolbar-brand { border-right:0; }
    #coinwallet_form_results .cw-summary { gap:12px; }
}
</style>

<div id="coinwallet_form_results">

    <div class="cw-toolbar">
        <div class="cw-toolbar-brand">
            <span class="cw-live-dot"></span>
            <span class="cw-brand-text"><b>Coin Wallet</b><small>properties · admin console</small></span>
            <span class="mc-badge"><?= Html::encode($symbolText) ?></span>
        </div>
        <div class="cw-toolbar-links"><?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?></div>
        <?php if ($coin->id): ?>
        <a class="cw-back" href="<?= Html::encode($backUrl) ?>">&larr; <?= Html::encode($coin->name) ?></a>
        <?php else: ?>
        <span class="cw-back muted">new coin</span>
        <?php endif ?>
    </div>

    <?= \app\widgets\Alert::widget() ?>

    <div class="cw-summary">
        <span>Coin <b><?= $coin->id ? Html::encode($coin->symbol . ' — ' . $coin->name) : 'New coin' ?></b></span>
        <span class="sep">|</span>
        <span>Algo <b><?= Html::encode($algoText) ?></b></span>
        <span class="sep">|</span>
        <span>Market <b><?= Html::encode($marketText) ?></b></span>
        <span class="sep">|</span>
        <span>Stratum <b><?= (int) $stratumPort ?></b></span>
    </div>

    <div class="cw-kpis">
        <div class="cw-kpi <?= $statusClass ?>">
            <span class="k-label">Status</span>
            <span class="k-value"><?= Html::encode($coinStatus) ?></span>
            <span class="k-sub">daemon setting</span>
        </div>
        <div class="cw-kpi accent">
            <span class="k-label">Algorithm</span>
            <span class="k-value"><?= Html::encode($algoText) ?></span>
            <span class="k-sub">proof of work</span>
        </div>
        <div class="cw-kpi accent">
            <span class="k-label">Stratum port</span>
            <span class="k-value"><?= (int) $stratumPort ?></span>
            <span class="k-sub"><?= !empty($dedport) ? 'dedicated' : 'shared algo port' ?></span>
        </div>
        <div class="cw-kpi">
            <span class="k-label">Block height</span>
            <span class="k-value"><?= Html::encode($heightText) ?></span>
            <span class="k-sub">last known</span>
        </div>
    </div>

    <div class="main-left-box cw-card">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>Coin properties</span>
                <span class="mn-sub">general · settings · daemon · exchange · links</span>
            </span>
            <span class="mc-badge">5 SECTIONS</span>
        </div>
        <div class="main-left-inner cw-card-inner">
<?php
$form = ActiveForm::begin(['options' => ['class' => 'uniForm cw-uniform']]);
echo CUFHtml::beginTag('fieldset', ['class' => 'inlineLabels cw-fieldset']);

$tab_general =
    CUFHtml::openActiveCtrlHolder($coin, 'name').
    CUFHtml::activeLabelEx($coin, 'name').
    CUFHtml::activeTextField($coin, 'name', ['maxlength' => 200]).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'symbol').
    CUFHtml::activeLabelEx($coin, 'symbol').
    CUFHtml::activeTextField($coin, 'symbol', ['maxlength' => 200, 'style' => 'width:120px;']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'symbol2').
    CUFHtml::activeLabelEx($coin, 'symbol2').
    CUFHtml::activeTextField($coin, 'symbol2', ['maxlength' => 200, 'style' => 'width:120px;']).
    '<p class="formHint2">Set if symbol differs from official</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'algo').
    CUFHtml::activeLabelEx($coin, 'algo').
    CUFHtml::dropDownList('db_coins[algo]', $coin->algo, $algos, ['style' => 'border:1px solid #dfdfdf;height:26px;width:135px;', 'class' => 'textInput tweetnews-input']).
    '<label style="padding-left:20px;" for="algo">Algo: ' . $coin_algo . '</label>'.
    '<p class="formHint2">Required, all lower case</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'auto_exchange').
    CUFHtml::activeLabelEx($coin, 'auto_exchange').
    CUFHtml::activeCheckBox($coin, 'auto_exchange', ['label' => '']).
    '<p class="formHint2">Include in automatic mining selection</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'image').
    CUFHtml::activeLabelEx($coin, 'image').
    CUFHtml::activeTextField($coin, 'image', ['maxlength' => 200]).
    '<p class="formHint2">Icon URL</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'payout_min').
    CUFHtml::activeLabelEx($coin, 'payout_min').
    CUFHtml::activeTextField($coin, 'payout_min', ['maxlength' => 200, 'style' => 'width:120px;']).
    '<p class="formHint2">Pay users when they reach this amount</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'payout_max').
    CUFHtml::activeLabelEx($coin, 'payout_max').
    CUFHtml::activeTextField($coin, 'payout_max', ['maxlength' => 200, 'style' => 'width:120px;']).
    '<p class="formHint2">Maximum transaction amount</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'txfee').
    CUFHtml::activeLabelEx($coin, 'txfee').
    CUFHtml::activeTextField($coin, 'txfee', ['maxlength' => 200, 'style' => 'width:100px;', 'readonly' => 'readonly']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'block_height').
    CUFHtml::activeLabelEx($coin, 'block_height').
    CUFHtml::activeTextField($coin, 'block_height', ['readonly' => 'readonly', 'style' => 'width:120px;']).
    '<p class="formHint2">Current height</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'target_height').
    CUFHtml::activeLabelEx($coin, 'target_height').
    CUFHtml::activeTextField($coin, 'target_height', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Known height of the network</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'mature_blocks').
    CUFHtml::activeLabelEx($coin, 'mature_blocks').
    CUFHtml::activeTextField($coin, 'mature_blocks', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Required block count to mature</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'block_time').
    CUFHtml::activeLabelEx($coin, 'block_time').
    CUFHtml::activeTextField($coin, 'block_time', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Average block time (seconds)</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'errors').
    CUFHtml::activeLabelEx($coin, 'errors').
    CUFHtml::activeTextField($coin, 'errors', ['maxlength' => 200, 'readonly' => 'readonly', 'style' => 'width:600px;']).
    CUFHtml::closeCtrlHolder();

$tab_settings =
    CUFHtml::openActiveCtrlHolder($coin, 'enable').
    CUFHtml::activeLabelEx($coin, 'enable').
    CUFHtml::activeCheckBox($coin, 'enable', ['label' => '']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'auto_ready').
    CUFHtml::activeLabelEx($coin, 'auto_ready').
    CUFHtml::activeCheckBox($coin, 'auto_ready', ['label' => '']).
    '<p class="formHint2">Allowed to mine</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'visible').
    CUFHtml::activeLabelEx($coin, 'visible').
    CUFHtml::activeCheckBox($coin, 'visible', ['label' => '']).
    '<p class="formHint2">Visibility for the public</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'installed').
    CUFHtml::activeLabelEx($coin, 'installed').
    CUFHtml::activeCheckBox($coin, 'installed', ['label' => '']).
    '<p class="formHint2">Required to be visible in the Wallets board</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'no_explorer').
    CUFHtml::activeLabelEx($coin, 'no_explorer').
    CUFHtml::activeCheckBox($coin, 'no_explorer', ['label' => '']).
    '<p class="formHint2">Disable block explorer for the public</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'watch').
    CUFHtml::activeLabelEx($coin, 'watch').
    CUFHtml::activeCheckBox($coin, 'watch', ['label' => '']).
    '<p class="formHint2">Track balance and markets history</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'auxpow').
    CUFHtml::activeLabelEx($coin, 'auxpow').
    CUFHtml::activeCheckBox($coin, 'auxpow', ['label' => '']).
    '<p class="formHint2">Merged mining</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'hasgetinfo').
    CUFHtml::activeLabelEx($coin, 'hasgetinfo').
    CUFHtml::activeCheckBox($coin, 'hasgetinfo', ['label' => '']).
    '<p class="formHint2">Enable if getinfo RPC method is present</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'hassubmitblock').
    CUFHtml::activeLabelEx($coin, 'hassubmitblock').
    CUFHtml::activeCheckBox($coin, 'hassubmitblock', ['label' => '']).
    '<p class="formHint2">Enable if submitblock method is present</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'txmessage').
    CUFHtml::activeLabelEx($coin, 'txmessage').
    CUFHtml::activeCheckBox($coin, 'txmessage', ['label' => '']).
    '<p class="formHint2">Block template with a tx message</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'hasmasternodes').
    CUFHtml::activeLabelEx($coin, 'hasmasternodes').
    CUFHtml::activeCheckBox($coin, 'hasmasternodes', ['label' => '']).
    '<p class="formHint2">Require payee/payee_amount or masternode in getblocktemplate</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'usesegwit').
    CUFHtml::activeLabelEx($coin, 'usesegwit').
    CUFHtml::activeCheckBox($coin, 'usesegwit', ['label' => '']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'usemweb').
    CUFHtml::activeLabelEx($coin, 'usemweb').
    CUFHtml::activeCheckBox($coin, 'usemweb', ['label' => '']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'enable_rpcdebug').
    CUFHtml::activeLabelEx($coin, 'enable_rpcdebug').
    CUFHtml::activeCheckBox($coin, 'enable_rpcdebug', ['label' => '']).
    '<p class="formHint2">Debug RPC communication from stratum to wallet</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'reward_mul').
    CUFHtml::activeLabelEx($coin, 'reward_mul').
    CUFHtml::activeTextField($coin, 'reward_mul', ['maxlength' => 200, 'style' => 'width:120px;']).
    '<p class="formHint2">Adjust the block reward if incorrect</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'master_wallet').
    CUFHtml::activeLabelEx($coin, 'master_wallet').
    CUFHtml::activeTextField($coin, 'master_wallet', ['maxlength' => 200]).
    '<p class="formHint2">The pool wallet address</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'wallet_zaddress').
    CUFHtml::activeLabelEx($coin, 'wallet_zaddress').
    CUFHtml::activeTextField($coin, 'wallet_zaddress', ['maxlength' => 200]).
    '<p class="formHint2">z-address for privacy coins (Zcash)</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'max_miners').
    CUFHtml::activeLabelEx($coin, 'max_miners').
    CUFHtml::activeTextField($coin, 'max_miners', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Miners allowed by the stratum</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'max_shares').
    CUFHtml::activeLabelEx($coin, 'max_shares').
    CUFHtml::activeTextField($coin, 'max_shares', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Auto restart stratum after this many shares</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'personalization').
    CUFHtml::activeLabelEx($coin, 'personalization').
    CUFHtml::activeTextField($coin, 'personalization', ['maxlength' => 100]).
    '<p class="formHint2">Equihash personalization string (default "ZcashPoW")</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'charity_percent').
    CUFHtml::activeLabelEx($coin, 'charity_percent').
    CUFHtml::activeTextField($coin, 'charity_percent', ['maxlength' => 10, 'style' => 'width:60px;']).
    '<p class="formHint2">Dev/foundation fee (1–10%)</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'charity_address').
    CUFHtml::activeLabelEx($coin, 'charity_address').
    CUFHtml::activeTextField($coin, 'charity_address', ['maxlength' => 200]).
    '<p class="formHint2">Foundation address if dev fees required</p>'.
    CUFHtml::closeCtrlHolder();

$tab_daemon =
    CUFHtml::openActiveCtrlHolder($coin, 'program').
    CUFHtml::activeLabelEx($coin, 'program').
    CUFHtml::activeTextField($coin, 'program', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2">Daemon process name</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'conf_folder').
    CUFHtml::activeLabelEx($coin, 'conf_folder').
    CUFHtml::activeTextField($coin, 'conf_folder', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2">Config folder (e.g. .bitcoin)</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'serveruser').
    CUFHtml::activeLabelEx($coin, 'serveruser').
    CUFHtml::activeTextField($coin, 'serveruser', ['maxlength' => 35, 'style' => 'width:180px;']).
    '<p class="formHint2">Daemon process username</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpchost').
    CUFHtml::activeLabelEx($coin, 'rpchost').
    CUFHtml::activeTextField($coin, 'rpchost', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2">Wallet IP</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpcport').
    CUFHtml::activeLabelEx($coin, 'rpcport').
    CUFHtml::activeTextField($coin, 'rpcport', ['maxlength' => 5, 'style' => 'width:60px;']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpcuser').
    CUFHtml::activeLabelEx($coin, 'rpcuser').
    CUFHtml::activeTextField($coin, 'rpcuser', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpcpasswd').
    CUFHtml::activeLabelEx($coin, 'rpcpasswd').
    CUFHtml::activeTextField($coin, 'rpcpasswd', ['maxlength' => 128]).
    '<p class="formHint2"></p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpcencoding').
    CUFHtml::activeLabelEx($coin, 'rpcencoding').
    CUFHtml::activeTextField($coin, 'rpcencoding', ['maxlength' => 5, 'style' => 'width:60px;']).
    '<p class="formHint2">POW / POS / DCR / GETH</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'dedicatedport').
    CUFHtml::activeLabelEx($coin, 'dedicatedport').
    CUFHtml::activeTextField($coin, 'dedicatedport', ['maxlength' => 5, 'style' => 'width:60px;']).
    '<p class="formHint2">Run addport to get port number</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'powend_height').
    CUFHtml::activeLabelEx($coin, 'powend_height').
    CUFHtml::activeTextField($coin, 'powend_height', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Height of end of PoW mining</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'powlimit_bits').
    CUFHtml::activeLabelEx($coin, 'powlimit_bits').
    CUFHtml::activeTextField($coin, 'powlimit_bits', ['maxlength' => 32, 'style' => 'width:120px;']).
    '<p class="formHint2">Leading \'0\' bits on powlimit (basehash for diff 1)</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpccurl').
    CUFHtml::activeLabelEx($coin, 'rpccurl').
    CUFHtml::activeCheckBox($coin, 'rpccurl', ['label' => '']).
    '<p class="formHint2">Force stratum to use curl for RPC</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpcssl').
    CUFHtml::activeLabelEx($coin, 'rpcssl').
    CUFHtml::activeCheckBox($coin, 'rpcssl', ['label' => '']).
    '<p class="formHint2">Wallet RPC secured via SSL</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'rpccert').
    CUFHtml::activeLabelEx($coin, 'rpccert').
    CUFHtml::activeTextField($coin, 'rpccert').
    '<p class="formHint2">Certificate file for RPC over SSL</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'account').
    CUFHtml::activeLabelEx($coin, 'account').
    CUFHtml::activeTextField($coin, 'account', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2">Wallet account to use</p>'.
    CUFHtml::closeCtrlHolder();

if ($coin->id) {
    $tab_daemon .=
        '<hr>' .
        '<b>Sample wallet config</b><br>' .
        '<pre>' .
        Html::encode(
            "rpcuser={$coin->rpcuser}\n" .
            "rpcpassword={$coin->rpcpasswd}\n" .
            "rpcport={$coin->rpcport}\n" .
            "rpcthreads=8\nrpcallowip=127.0.0.1\nmaxconnections=12\ndaemon=1\ngen=0\n\n" .
            'alertnotify=%s | mail -s "' . $coin->name . ' alert!" ' . YIIMP_ADMIN_EMAIL . "\n" .
            (empty($dedport)
                ? "blocknotify=/var/stratum/blocknotify " . YIIMP_STRATUM_URL . ":{$port} {$coin->id} %s\n"
                : "blocknotify=/var/stratum/blocknotify " . YIIMP_STRATUM_URL . ":{$dedport} {$coin->id} %s\n")
        ) .
        '</pre>' .
        '<b>Sample miner command</b><br>' .
        '<pre>' .
        Html::encode(
            "-a {$coin->algo} " .
            (empty($dedport)
                ? "-o stratum+tcp://" . YIIMP_STRATUM_URL . ":{$port} "
                : "-o stratum+tcp://" . YIIMP_STRATUM_URL . ":{$dedport} ") .
            "-u {$coin->master_wallet} -p c={$coin->symbol}\n"
        ) .
        '</pre>';
}

$tab_exchange =
    CUFHtml::openActiveCtrlHolder($coin, 'dontsell').
    CUFHtml::activeLabelEx($coin, 'dontsell').
    CUFHtml::activeCheckBox($coin, 'dontsell', ['label' => '']).
    '<p class="formHint2">Disable auto send to exchange</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'sellonbid').
    CUFHtml::activeLabelEx($coin, 'sellonbid').
    CUFHtml::activeCheckBox($coin, 'sellonbid', ['label' => '']).
    '<p class="formHint2">Reduce the sell price on exchanges</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'sellthreshold').
    CUFHtml::activeLabelEx($coin, 'sellthreshold').
    CUFHtml::activeTextField($coin, 'sellthreshold', ['maxlength' => 16, 'style' => 'width:120px;']).
    '<p class="formHint2">Minimum amount to sell</p>'.
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'market').
    CUFHtml::activeLabelEx($coin, 'market').
    CUFHtml::activeTextField($coin, 'market', ['maxlength' => 128, 'style' => 'width:180px;']).
    '<p class="formHint2">Selected exchange</p>'.
    CUFHtml::closeCtrlHolder();

if (empty($coin->price) || empty($coin->market) || $coin->market === 'unknown') {
    $tab_exchange .=
        CUFHtml::openActiveCtrlHolder($coin, 'price').
        CUFHtml::activeLabelEx($coin, 'price').
        CUFHtml::activeTextField($coin, 'price', ['maxlength' => 16, 'style' => 'width:120px;']).
        '<p class="formHint2">Manually set BTC price if missing</p>'.
        CUFHtml::closeCtrlHolder();
}

$tab_links =
    CUFHtml::openActiveCtrlHolder($coin, 'link_bitcointalk').
    CUFHtml::activeLabelEx($coin, 'link_bitcointalk').
    CUFHtml::activeTextField($coin, 'link_bitcointalk').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_github').
    CUFHtml::activeLabelEx($coin, 'link_github').
    CUFHtml::activeTextField($coin, 'link_github').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_site').
    CUFHtml::activeLabelEx($coin, 'link_site').
    CUFHtml::activeTextField($coin, 'link_site').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_exchange').
    CUFHtml::activeLabelEx($coin, 'link_exchange').
    CUFHtml::activeTextField($coin, 'link_exchange').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_explorer').
    CUFHtml::activeLabelEx($coin, 'link_explorer').
    CUFHtml::activeTextField($coin, 'link_explorer').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_twitter').
    CUFHtml::activeLabelEx($coin, 'link_twitter').
    CUFHtml::activeTextField($coin, 'link_twitter').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_discord').
    CUFHtml::activeLabelEx($coin, 'link_discord').
    CUFHtml::activeTextField($coin, 'link_discord').
    CUFHtml::closeCtrlHolder().

    CUFHtml::openActiveCtrlHolder($coin, 'link_facebook').
    CUFHtml::activeLabelEx($coin, 'link_facebook').
    CUFHtml::activeTextField($coin, 'link_facebook').
    CUFHtml::closeCtrlHolder();

echo Tabs::widget([
    'items' => [
        ['label' => 'General',  'content' => $tab_general,  'active' => true],
        ['label' => 'Settings', 'content' => $tab_settings],
        ['label' => 'Daemon',   'content' => $tab_daemon],
        ['label' => 'Exchange', 'content' => $tab_exchange],
        ['label' => 'Links',    'content' => $tab_links],
    ],
]);
?>

<div class="cw-actions">
    <?= Html::submitButton($update ? 'Save changes' : 'Create coin', ['class' => 'cw-save']) ?>
    <a class="cw-cancel" href="<?= Html::encode($backUrl) ?>">Cancel</a>
</div>
<?= CUFHtml::endTag('fieldset') ?>
<?php ActiveForm::end() ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE + TAILWIND  (shared field helpers, per-scheme tab chrome)
     ══════════════════════════════════════════════════════════════════════════ -->
<?php

// ── Scheme-aware field helper closures ────────────────────────────────────────
$iT  = $isTailwind;
$wC  = $iT ? 'mb-4' : 'mb-3';                  // wrapper class
$lC  = $iT ? 'block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1'
           : 'form-label small fw-medium mb-1'; // label class
$hC  = $iT ? 'text-xs text-gray-400 dark:text-gray-500 mt-0.5'
           : 'form-text text-muted small';      // hint class
$iC  = $iT ? 'block w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500'
           : 'form-control form-control-sm';    // input class
$iCR = $iC . ($iT ? ' opacity-60 cursor-not-allowed' : ''); // readonly input
$eC  = $iT ? 'text-xs text-red-500 dark:text-red-400 mt-0.5' : 'text-danger small d-block mt-1';

$errHtml = function(string $attr) use ($coin, $eC): string {
    return $coin->hasErrors($attr)
        ? '<div class="' . $eC . '">' . Html::encode($coin->getFirstError($attr)) . '</div>'
        : '';
};

// Text input
$tf = function(string $attr, array $opts = [], string $hint = '') use ($coin, $iT, $wC, $lC, $hC, $iC, $iCR, $errHtml): string {
    $ro = !empty($opts['readonly']);
    $opts['class'] = $ro ? $iCR : $iC;
    if ($iT) unset($opts['style']); // don't carry legacy inline widths
    return '<div class="' . $wC . '">'
        . Html::activeLabel($coin, $attr, ['class' => $lC])
        . Html::activeTextInput($coin, $attr, $opts)
        . ($hint ? '<div class="' . $hC . '">' . Html::encode($hint) . '</div>' : '')
        . $errHtml($attr)
        . '</div>';
};

// Textarea
$ta = function(string $attr, array $opts = [], string $hint = '') use ($coin, $iT, $wC, $lC, $hC, $iC, $errHtml): string {
    $opts['class'] = $iC;
    if ($iT) { $opts['rows'] = $opts['rows'] ?? 4; unset($opts['style']); }
    return '<div class="' . $wC . '">'
        . Html::activeLabel($coin, $attr, ['class' => $lC])
        . Html::activeTextarea($coin, $attr, $opts)
        . ($hint ? '<div class="' . $hC . '">' . Html::encode($hint) . '</div>' : '')
        . $errHtml($attr)
        . '</div>';
};

// Dropdown
$dd = function(string $attr, array $items, string $hint = '') use ($coin, $iT, $wC, $lC, $hC, $errHtml): string {
    $cls = $iT
        ? 'block w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500'
        : 'form-select form-select-sm';
    return '<div class="' . $wC . '">'
        . Html::activeLabel($coin, $attr, ['class' => $lC])
        . Html::activeDropDownList($coin, $attr, $items, ['class' => $cls])
        . ($hint ? '<div class="' . $hC . '">' . Html::encode($hint) . '</div>' : '')
        . $errHtml($attr)
        . '</div>';
};

// Checkbox
$cb = function(string $attr, string $hint = '') use ($coin, $iT, $wC, $hC, $errHtml): string {
    $label = Html::encode($coin->getAttributeLabel($attr));
    if ($iT) {
        $input = Html::activeCheckbox($coin, $attr, ['class' => 'w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 dark:bg-gray-700', 'label' => false]);
        return '<div class="' . $wC . '">'
            . '<label class="flex items-start gap-2 cursor-pointer">'
            . $input
            . '<div><span class="text-sm text-gray-700 dark:text-gray-300">' . $label . '</span>'
            . ($hint ? '<div class="' . $hC . '">' . Html::encode($hint) . '</div>' : '')
            . '</div></label>'
            . $errHtml($attr) . '</div>';
    }
    $input = Html::activeCheckbox($coin, $attr, ['class' => 'form-check-input', 'label' => false]);
    $lbl   = Html::activeLabel($coin, $attr, ['class' => 'form-check-label small']);
    return '<div class="' . $wC . '"><div class="form-check">' . $input . $lbl . '</div>'
        . ($hint ? '<div class="' . $hC . '">' . Html::encode($hint) . '</div>' : '')
        . $errHtml($attr) . '</div>';
};

// Section heading inside a tab panel
$sec = function(string $title) use ($iT): string {
    return $iT
        ? '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3 mt-1 border-b border-gray-100 dark:border-gray-700 pb-1">' . Html::encode($title) . '</h4>'
        : '<h6 class="text-muted small fw-semibold text-uppercase mb-2 mt-1 border-bottom pb-1">' . Html::encode($title) . '</h6>';
};

// col wrapper for 2-up layout
$col2open  = $iT ? '<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">' : '<div class="row">';
$col2close = '</div>';
$colopen   = $iT ? '<div>' : '<div class="col-md-6">';
$colclose  = '</div>';
$fullopen  = $iT ? '<div class="md:col-span-2">' : '<div class="col-12">';

$configPre = function(string $content) use ($iT): string {
    $cls = $iT
        ? 'text-xs font-mono bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto whitespace-pre'
        : 'small bg-light border rounded p-3';
    return '<pre class="' . $cls . '">' . Html::encode($content) . '</pre>';
};

// ── Page header ───────────────────────────────────────────────────────────────
if (!$isTailwind): ?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-semibold">
        <?= $update ? Html::encode($coin->name) . ' — Edit Wallet' : 'New Coin' ?>
    </h5>
    <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>
<?= \app\widgets\Alert::widget() ?>
<?php else: ?>
<div class="flex items-center justify-between mb-4 flex-wrap gap-2">
    <h2 class="text-base font-bold text-gray-800 dark:text-gray-100">
        <?= $update ? Html::encode($coin->name) . ' — Edit Wallet' : 'New Coin' ?>
    </h2>
    <a href="<?= Html::encode($backUrl) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
              border border-gray-300 dark:border-gray-600
              bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
              hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
    </a>
</div>
<?= \app\widgets\Alert::widget() ?>
<?php endif ?>

<?php
// ── Tabs definition ────────────────────────────────────────────────────────────
$tabs = [
    ['id' => 'general',  'label' => 'General'],
    ['id' => 'settings', 'label' => 'Settings'],
    ['id' => 'daemon',   'label' => 'Daemon'],
    ['id' => 'exchange', 'label' => 'Exchange'],
    ['id' => 'links',    'label' => 'Links'],
];

// ── Form open ─────────────────────────────────────────────────────────────────
$form = ActiveForm::begin(['options' => ['class' => $iT ? '' : 'mt-0']]);

// ── Tab navigation ────────────────────────────────────────────────────────────
if (!$isTailwind): ?>
<ul class="nav nav-tabs mb-0" role="tablist">
<?php foreach ($tabs as $i => $tab): ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link small<?= $i === 0 ? ' active' : '' ?>"
           data-bs-toggle="tab" href="#twf-<?= $tab['id'] ?>" role="tab">
            <?= Html::encode($tab['label']) ?>
        </a>
    </li>
<?php endforeach ?>
</ul>
<div class="tab-content border border-top-0 rounded-bottom p-3 mb-3">
<?php else: ?>
<div class="flex border-b border-gray-200 dark:border-gray-700 mb-0 overflow-x-auto">
<?php foreach ($tabs as $i => $tab): ?>
    <button type="button"
            class="tw-tab px-4 py-2.5 text-sm font-medium whitespace-nowrap
                   border-b-2 transition-colors
                   <?= $i === 0
                       ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                       : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' ?>"
            data-tab="twf-<?= $tab['id'] ?>">
        <?= Html::encode($tab['label']) ?>
    </button>
<?php endforeach ?>
</div>
<div class="rounded-b-xl border border-t-0 border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 p-4 mb-4">
<?php endif ?>

<!-- ── TAB: General ─────────────────────────────────────────────────────── -->
<?php if (!$isTailwind): ?><div class="tab-pane fade show active" id="twf-general" role="tabpanel"><?php else: ?><div id="twf-general" class="tw-pane"><?php endif ?>
<?= $col2open ?>
<?= $colopen ?>
<?= $sec('Identity') ?>
<?= $tf('name',   [], '') ?>
<?= $tf('symbol', [], '') ?>
<?= $tf('symbol2', [], 'Set if symbol differs from official') ?>
<?= $dd('algo', $algos, 'All lower case') ?>
<?= $tf('image',  [], 'Icon URL') ?>
<?= $colclose ?>
<?= $colopen ?>
<?= $sec('Payouts') ?>
<?= $tf('payout_min', [], 'Pay when balance reaches this') ?>
<?= $tf('payout_max', [], 'Maximum transaction amount') ?>
<?= $tf('txfee',      ['readonly' => true], '') ?>
<?= $sec('Block info') ?>
<?= $tf('block_height',  ['readonly' => true], 'Current height') ?>
<?= $tf('target_height', [], 'Known height of the network') ?>
<?= $tf('mature_blocks', [], 'Required block count to mature') ?>
<?= $tf('block_time',    [], 'Average block time (seconds)') ?>
<?= $colclose ?>
<?= $col2close ?>
<?= $tf('errors', ['readonly' => true], '') ?>
</div>

<!-- ── TAB: Settings ────────────────────────────────────────────────────── -->
<?php if (!$isTailwind): ?><div class="tab-pane fade" id="twf-settings" role="tabpanel"><?php else: ?><div id="twf-settings" class="tw-pane hidden"><?php endif ?>
<?= $col2open ?>
<?= $colopen ?>
<?= $sec('Visibility') ?>
<?= $cb('enable',      '') ?>
<?= $cb('auto_ready',  'Allowed to mine') ?>
<?= $cb('visible',     'Visibility for the public') ?>
<?= $cb('installed',   'Required to be visible in the Wallets board') ?>
<?= $cb('no_explorer', 'Disable block explorer for the public') ?>
<?= $cb('watch',       'Track balance and markets history') ?>
<?= $cb('auxpow',      'Merged mining') ?>
<?= $colclose ?>
<?= $colopen ?>
<?= $sec('RPC protocol') ?>
<?= $cb('hasgetinfo',    'Enable if getinfo RPC method is present') ?>
<?= $cb('hassubmitblock','Enable if submitblock method is present') ?>
<?= $cb('txmessage',     'Block template with a tx message') ?>
<?= $cb('hasmasternodes','Require payee/payee_amount in getblocktemplate') ?>
<?= $cb('usesegwit',     '') ?>
<?= $cb('usemweb',       '') ?>
<?= $cb('enable_rpcdebug', 'Debug RPC communication from stratum to wallet') ?>
<?= $sec('Mining') ?>
<?= $tf('reward_mul',     [], 'Adjust block reward if incorrect') ?>
<?= $tf('master_wallet',  [], 'The pool wallet address') ?>
<?= $tf('wallet_zaddress',[], 'z-address for privacy coins (Zcash)') ?>
<?= $tf('max_miners',     [], 'Miners allowed by the stratum') ?>
<?= $tf('max_shares',     [], 'Auto restart stratum after this many shares') ?>
<?= $tf('personalization',[], 'Equihash personalization string (default "ZcashPoW")') ?>
<?= $tf('charity_percent', [], 'Dev/foundation fee (1–10%)') ?>
<?= $tf('charity_address', [], 'Foundation address if dev fees required') ?>
<?= $colclose ?>
<?= $col2close ?>
</div>

<!-- ── TAB: Daemon ──────────────────────────────────────────────────────── -->
<?php if (!$isTailwind): ?><div class="tab-pane fade" id="twf-daemon" role="tabpanel"><?php else: ?><div id="twf-daemon" class="tw-pane hidden"><?php endif ?>
<?= $col2open ?>
<?= $colopen ?>
<?= $sec('Process') ?>
<?= $tf('program',    [], 'Daemon process name') ?>
<?= $tf('conf_folder',[], 'Config folder (e.g. .bitcoin)') ?>
<?= $tf('serveruser', [], 'Daemon process username') ?>
<?= $sec('RPC connection') ?>
<?= $tf('rpchost',    [], 'Wallet IP') ?>
<?= $tf('rpcport',    [], '') ?>
<?= $tf('rpcuser',    [], '') ?>
<?= $tf('rpcpasswd',  [], '') ?>
<?= $tf('rpcencoding',[], 'POW / POS / DCR / GETH') ?>
<?= $cb('rpccurl',    'Force stratum to use curl for RPC') ?>
<?= $cb('rpcssl',     'Wallet RPC secured via SSL') ?>
<?= $tf('rpccert',    [], 'Certificate file for RPC over SSL') ?>
<?= $tf('account',    [], 'Wallet account to use') ?>
<?= $colclose ?>
<?= $colopen ?>
<?= $sec('Stratum port') ?>
<?= $tf('dedicatedport', [], 'Run addport to get port number') ?>
<?= $tf('powend_height', [], 'Height of end of PoW mining') ?>
<?= $tf('powlimit_bits', [], "Leading '0' bits on powlimit (basehash for diff 1)") ?>
<?= $colclose ?>
<?= $col2close ?>
<?php if ($coin->id): ?>
<div class="mt-4">
<?= $sec('Sample wallet config') ?>
<?= $configPre(
    "rpcuser={$coin->rpcuser}\n" .
    "rpcpassword={$coin->rpcpasswd}\n" .
    "rpcport={$coin->rpcport}\n" .
    "rpcthreads=8\n" .
    "rpcallowip=127.0.0.1\n" .
    "maxconnections=12\n" .
    "daemon=1\n" .
    "gen=0\n\n" .
    "alertnotify=%s | mail -s \"{$coin->name} alert!\" " . YIIMP_ADMIN_EMAIL . "\n" .
    (empty($dedport)
        ? "blocknotify=/var/stratum/blocknotify " . YIIMP_STRATUM_URL . ":{$port} {$coin->id} %s\n"
        : "blocknotify=/var/stratum/blocknotify " . YIIMP_STRATUM_URL . ":{$dedport} {$coin->id} %s\n")
) ?>
<?= $sec('Sample miner command') ?>
<?= $configPre(
    "-a {$coin->algo} " .
    (empty($dedport)
        ? "-o stratum+tcp://" . YIIMP_STRATUM_URL . ":{$port} "
        : "-o stratum+tcp://" . YIIMP_STRATUM_URL . ":{$dedport} ") .
    "-u {$coin->master_wallet} " .
    "-p c={$coin->symbol}\n"
) ?>
</div>
<?php endif ?>
</div>

<!-- ── TAB: Exchange ────────────────────────────────────────────────────── -->
<?php if (!$isTailwind): ?><div class="tab-pane fade" id="twf-exchange" role="tabpanel"><?php else: ?><div id="twf-exchange" class="tw-pane hidden"><?php endif ?>
<?= $col2open ?>
<?= $colopen ?>
<?= $cb('dontsell',   'Disable auto send to exchange') ?>
<?= $cb('sellonbid',  'Reduce the sell price on exchanges') ?>
<?= $tf('sellthreshold', [], 'Minimum amount to sell') ?>
<?= $tf('market',        [], 'Selected exchange') ?>
<?php if (empty($coin->price) || empty($coin->market) || $coin->market === 'unknown'): ?>
<?= $tf('price', [], 'Manually set BTC price if missing') ?>
<?php endif ?>
<?= $colclose ?>
<?= $col2close ?>
</div>

<!-- ── TAB: Links ───────────────────────────────────────────────────────── -->
<?php if (!$isTailwind): ?><div class="tab-pane fade" id="twf-links" role="tabpanel"><?php else: ?><div id="twf-links" class="tw-pane hidden"><?php endif ?>
<?= $col2open ?>
<?= $colopen ?>
<?= $tf('link_bitcointalk', [], '') ?>
<?= $tf('link_github',      [], '') ?>
<?= $tf('link_site',        [], '') ?>
<?= $tf('link_exchange',    [], '') ?>
<?= $colclose ?>
<?= $colopen ?>
<?= $tf('link_explorer', [], '') ?>
<?= $tf('link_twitter',  [], '') ?>
<?= $tf('link_discord',  [], '') ?>
<?= $tf('link_facebook', [], '') ?>
<?= $colclose ?>
<?= $col2close ?>
</div>

<?php // Close tab-content wrapper
if (!$isTailwind): ?>
</div><!-- /.tab-content -->
<?php else: ?>
</div><!-- /.tw-pane-wrapper -->
<?php endif ?>

<!-- ── Submit ────────────────────────────────────────────────────────────── -->
<div class="<?= $iT ? 'flex gap-3 mt-2' : 'd-flex gap-2 mt-2' ?>">
<?php if (!$isTailwind): ?>
    <?= Html::submitButton($update ? 'Save changes' : 'Create coin', ['class' => 'btn btn-primary btn-sm']) ?>
    <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
<?php else: ?>
    <?= Html::submitButton($update ? 'Save changes' : 'Create coin', [
        'class' => 'inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg'
                 . ' bg-indigo-600 hover:bg-indigo-700 text-white transition-colors cursor-pointer'
    ]) ?>
    <a href="<?= Html::encode($backUrl) ?>"
       class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg
              border border-gray-300 dark:border-gray-600
              bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
              hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
        Cancel
    </a>
<?php endif ?>
</div>

<?php ActiveForm::end() ?>

<?php if ($isTailwind): ?>
<script>
(function () {
    var tabs  = document.querySelectorAll('.tw-tab');
    var panes = document.querySelectorAll('.tw-pane');
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.dataset.tab;
            tabs.forEach(function (b) {
                b.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                b.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });
            btn.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            btn.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            panes.forEach(function (p) { p.classList.add('hidden'); });
            var pane = document.getElementById(target);
            if (pane) pane.classList.remove('hidden');
        });
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
</script>
<?php endif ?>

<?php endif // scheme ?>
