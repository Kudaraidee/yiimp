<?php

/** @var yii\web\View     $this   */
/** @var app\models\Markets $market */
/** @var app\models\Coins   $coin   */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$conv = Yii::$app->ConversionUtils;
$utils = Yii::$app->YiimpUtils;

$symbol    = (string) $coin->getOfficialSymbol();
$base      = trim((string) $market->base_coin) ?: 'BTC';
$marketName = (string) $market->name;
$marketUrl = $utils->getMarketUrl($coin, $marketName);
$backUrl   = Url::to(['/admin/coinwallet', 'id' => $coin->id]);

$bidBtc = (float) $utils->marketBtcPrice($market, 'price');
$askBtc = (float) $utils->marketBtcPrice($market, 'price2');
$bidRaw = rtrim(rtrim(number_format((float) $market->price, 8, '.', ''), '0'), '.') ?: '0';
$askRaw = rtrim(rtrim(number_format((float) $market->price2, 8, '.', ''), '0'), '.') ?: '0';
$updated = trim(strip_tags($conv->datetoa2($market->pricetime))) ?: 'never';

$disabled    = (int) $market->disabled > 0;
$statusText  = $disabled ? 'Disabled' : 'Enabled';
$statusClass = $disabled ? 'warn' : 'good';

$sent   = trim(strip_tags($conv->datetoa2($market->lastsent))) ?: 'never';
$traded = trim(strip_tags($conv->datetoa2($market->lasttraded))) ?: 'never';

?>
<style>
#market_update_results { font-family: inherit; color: #4a586c; }
#market_update_results .mu-toolbar {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:9px 12px; margin:0 0 12px; border:1px solid #d8e2ef; border-radius:7px;
    background:#fff; box-shadow:0 1px 2px rgba(20,40,80,.04);
}
#market_update_results .mu-toolbar-brand {
    display:flex; align-items:center; gap:9px; padding-right:12px;
    border-right:1px solid #e8eef6;
}
#market_update_results .mu-live-dot {
    width:8px; height:8px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#market_update_results .mu-brand-text { display:flex; flex-direction:column; line-height:1.25; }
#market_update_results .mu-brand-text b { color:#26385a; font-size:13px; }
#market_update_results .mu-brand-text small { color:#8a94a6; font-size:9px; }
#market_update_results .mu-coin-icon { width:22px; height:22px; border-radius:50%; object-fit:contain; }
#market_update_results .mc-badge, #market_update_results .mu-toolbar .mc-badge {
    display:inline-block; padding:2px 7px; border-radius:999px; background:#eef4ff;
    border:1px solid #d7e5ff; color:#2c5aa0; font-size:9px; font-weight:700;
    line-height:14px; white-space:nowrap; text-decoration:none;
}
#market_update_results .mu-toolbar-links { display:flex; flex-wrap:wrap; gap:6px; flex:1 1 auto; }
#market_update_results .mu-toolbar-links a,
#market_update_results .mu-back {
    display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #dfe7f2;
    background:#f8fafc; color:#4a586c; font-size:10px; line-height:15px;
    text-decoration:none; white-space:nowrap;
}
#market_update_results .mu-toolbar-links a:hover, #market_update_results .mu-back:hover {
    background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none;
}

#market_update_results .mu-summary {
    background:#f8fbff; border:1px solid #d8e2ef; border-left:3px solid #2c5aa0;
    border-radius:6px; padding:8px 14px; display:flex; gap:18px; flex-wrap:wrap;
    font-size:12px; color:#4a586c; margin-bottom:14px;
}
#market_update_results .mu-summary b { color:#223a5e; }
#market_update_results .mu-summary .sep { color:#ccd4e0; }
#market_update_results .mu-summary b.green { color:#2fae5a; }
#market_update_results .mu-summary b.red { color:#c2410c; }

#market_update_results .mu-kpis {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:14px;
}
#market_update_results .mu-kpi {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff;
    padding:8px 10px; min-width:0;
}
#market_update_results .mu-kpi .k-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#market_update_results .mu-kpi .k-value {
    display:block; margin-top:3px; font-family:monospace; font-size:15px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#market_update_results .mu-kpi .k-sub { display:block; margin-top:2px; font-size:9px; color:#8a94a6; }
#market_update_results .mu-kpi.accent .k-value { color:#2c5aa0; }
#market_update_results .mu-kpi.good .k-value { color:#2fae5a; }
#market_update_results .mu-kpi.warn .k-value { color:#c2410c; }

#market_update_results .mu-grid {
    display:grid; grid-template-columns:minmax(0,1.35fr) minmax(280px,.85fr);
    gap:14px; align-items:start;
}
#market_update_results .main-left-box {
    border:1px solid #d8e2ef; border-radius:7px; background:#fff;
    box-shadow:0 1px 2px rgba(20,40,80,.04); overflow:hidden; margin:0;
}
#market_update_results .main-left-title {
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    min-height:34px; padding:9px 12px; background:#f8fafc;
    border-bottom:1px solid #e8eef6; color:#26385a; font-size:13px; font-weight:800;
}
#market_update_results .mn-head-main { display:flex; align-items:baseline; gap:9px; flex-wrap:wrap; min-width:0; }
#market_update_results .mn-sub { font-size:10px; color:#8a94a6; font-weight:500; }
#market_update_results .main-left-inner.mu-card-inner { padding:14px 16px 16px; }

#market_update_results .mu-uniform { margin:0 !important; }
#market_update_results .mu-fieldset { border:0 !important; margin:0 !important; padding:0 !important; }
#market_update_results .mu-uniform .form-group { margin:0 0 12px; }
#market_update_results .mu-uniform label.control-label {
    display:block; margin:0 0 4px; padding:0; color:#4a586c;
    font-size:10px; font-weight:800; letter-spacing:.02em;
}
#market_update_results .mu-uniform .form-control.mu-input {
    box-sizing:border-box; width:100%; height:auto; padding:6px 9px;
    border:1px solid #dfe5ec; border-radius:7px; background:#fafbfc; color:#26385a;
    font:11px/1.4 inherit; outline:0; box-shadow:none;
}
#market_update_results .mu-uniform .form-control.mu-input:focus {
    border-color:#a9c4ea; background:#fff;
}
#market_update_results .mu-uniform .form-control.mu-short { max-width:140px; }
#market_update_results .mu-uniform .form-control.mu-address { font-family:monospace; overflow-wrap:anywhere; }
#market_update_results .mu-uniform .hint-block {
    display:block; margin:4px 0 0; color:#8a94a6; font-size:10px; line-height:1.35;
}
#market_update_results .mu-uniform .error-summary {
    padding:8px 10px; margin:0 0 12px; border:1px solid #f2c5c5; border-radius:6px;
    background:#fff4f4; color:#a13d3d; font-size:11px;
}
#market_update_results .mu-uniform .help-block,
#market_update_results .mu-uniform .invalid-feedback { color:#c2410c; font-size:10px; }

#market_update_results .mu-actions {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding-top:12px; margin-top:4px; border-top:1px solid #e8eef6;
}
#market_update_results .mu-save {
    display:inline-block; padding:6px 12px; border-radius:7px; border:1px solid #2c5aa0;
    background:#2c5aa0; color:#fff; font-size:11px; font-weight:700; text-decoration:none;
    box-shadow:0 1px 2px rgba(20,40,80,.08); cursor:pointer;
}
#market_update_results .mu-save:hover { background:#244b86; border-color:#244b86; color:#fff; text-decoration:none; }
#market_update_results .mu-cancel {
    display:inline-block; padding:6px 12px; border-radius:7px; border:1px solid #dfe5ec;
    background:#fff; color:#5b6675; font-size:11px; font-weight:700; text-decoration:none;
}
#market_update_results .mu-cancel:hover { background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none; }

#market_update_results .mu-details {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px;
}
#market_update_results .mu-detail {
    border:1px solid #e8eef6; border-radius:6px; background:#fcfdff; padding:8px 10px; min-width:0;
}
#market_update_results .mu-detail .d-label {
    display:block; font-size:9px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; color:#98a3b5;
}
#market_update_results .mu-detail .d-value {
    display:block; margin-top:3px; font-family:monospace; font-size:12px;
    font-weight:700; color:#26385a; overflow-wrap:anywhere;
}
#market_update_results .mu-market-cta { margin-top:14px; padding-top:12px; border-top:1px solid #e8eef6; }
#market_update_results .mu-market-link {
    display:inline-block; padding:5px 10px; border-radius:7px; border:1px solid #d7e5ff;
    background:#eef4ff; color:#2c5aa0; font-size:10px; font-weight:700; text-decoration:none;
}
#market_update_results .mu-market-link:hover { background:#dfeaff; text-decoration:none; }
@media (max-width:900px) {
    #market_update_results .mu-toolbar-brand { border-right:0; }
    #market_update_results .mu-grid { grid-template-columns:1fr; }
}
</style>

<div id="market_update_results">

    <div class="mu-toolbar">
        <div class="mu-toolbar-brand">
            <span class="mu-live-dot"></span>
            <span class="mu-brand-text"><b>Market Settings</b><small>exchange · admin console</small></span>
            <?php if (!empty($coin->image)): ?>
                <?= Html::img($coin->image, ['alt' => $symbol, 'class' => 'mu-coin-icon']) ?>
            <?php endif ?>
            <span class="mc-badge"><?= Html::encode($symbol) ?></span>
        </div>
        <div class="mu-toolbar-links"><?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?></div>
        <a class="mu-back" href="<?= Html::encode($backUrl) ?>">&larr; <?= Html::encode($coin->name) ?></a>
    </div>

    <?= \app\widgets\Alert::widget() ?>

    <div class="mu-summary">
        <span>Coin <b><?= Html::encode($symbol . ' — ' . $coin->name) ?></b></span>
        <span class="sep">|</span>
        <span>Algo <b><?= Html::encode($coin->algo) ?></b></span>
        <span class="sep">|</span>
        <span>Market <b><?= Html::encode($marketName) ?></b></span>
        <span class="sep">|</span>
        <span>Pair <b><?= Html::encode(strtoupper($symbol) . '/' . $base) ?></b></span>
        <span class="sep">|</span>
        <span>Status <b class="<?= $disabled ? 'red' : 'green' ?>"><?= Html::encode($statusText) ?></b></span>
    </div>

    <div class="mu-kpis">
        <div class="mu-kpi <?= $statusClass ?>">
            <span class="k-label">Status</span>
            <span class="k-value"><?= Html::encode($statusText) ?></span>
            <span class="k-sub">pool routing</span>
        </div>
        <div class="mu-kpi accent">
            <span class="k-label">Bid</span>
            <span class="k-value">₿ <?= Html::encode($conv->bitcoinvaluetoa($bidBtc)) ?></span>
            <span class="k-sub"><?= Html::encode($bidRaw . ' ' . $base) ?></span>
        </div>
        <div class="mu-kpi accent">
            <span class="k-label">Ask</span>
            <span class="k-value">₿ <?= Html::encode($conv->bitcoinvaluetoa($askBtc)) ?></span>
            <span class="k-sub"><?= Html::encode($askRaw . ' ' . $base) ?></span>
        </div>
        <div class="mu-kpi">
            <span class="k-label">Price age</span>
            <span class="k-value"><?= Html::encode($updated) ?></span>
            <span class="k-sub">last market poll</span>
        </div>
    </div>

    <div class="mu-grid">
        <div class="main-left-box mu-card">
            <div class="main-left-title">
                <span class="mn-head-main">
                    <span>Deposit routing</span>
                    <span class="mn-sub">payout destination · quote currency</span>
                </span>
                <span class="mc-badge">EDIT</span>
            </div>
            <div class="main-left-inner mu-card-inner">
                <?php $form = ActiveForm::begin(['options' => ['class' => 'uniForm mu-uniform']]); ?>
                <?= $form->errorSummary($market) ?>
                <fieldset class="mu-fieldset">
                    <?= $form->field($market, 'deposit_address')
                        ->textInput(['maxlength' => 200, 'class' => 'form-control mu-input mu-address'])
                        ->label('Deposit address')
                        ->hint('Use Address::PaymentID on XMR forks') ?>

                    <?= $form->field($market, 'base_coin')
                        ->textInput(['maxlength' => 16, 'class' => 'form-control mu-input mu-short'])
                        ->label('Quote currency')
                        ->hint('Default (empty) is BTC · updates the pair suffix on this market') ?>
                </fieldset>
                <div class="mu-actions">
                    <?= Html::submitButton('Save changes', ['class' => 'mu-save']) ?>
                    <a class="mu-cancel" href="<?= Html::encode($backUrl) ?>">Cancel</a>
                </div>
                <?php ActiveForm::end() ?>
            </div>
        </div>

        <div class="main-left-box mu-card">
            <div class="main-left-title">
                <span class="mn-head-main">
                    <span>Market snapshot</span>
                    <span class="mn-sub">read-only · last poll</span>
                </span>
                <span class="mc-badge">#<?= (int) $market->id ?></span>
            </div>
            <div class="main-left-inner mu-card-inner">
                <div class="mu-details">
                    <div class="mu-detail">
                        <span class="d-label">Exchange</span>
                        <span class="d-value"><?= Html::encode($marketName) ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Base</span>
                        <span class="d-value"><?= Html::encode($base) ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Priority</span>
                        <span class="d-value"><?= (int) $market->priority ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Fee</span>
                        <span class="d-value"><?= Html::encode($conv->bitcoinvaluetoa((float) $market->txfee, 2)) ?>%</span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Balance</span>
                        <span class="d-value"><?= $market->balance > 0 ? Html::encode($conv->bitcoinvaluetoa((float) $market->balance)) : '—' ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">On trade</span>
                        <span class="d-value"><?= $market->ontrade > 0 ? Html::encode($conv->bitcoinvaluetoa((float) $market->ontrade)) : '—' ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Last sent</span>
                        <span class="d-value"><?= Html::encode($sent) ?></span>
                    </div>
                    <div class="mu-detail">
                        <span class="d-label">Last traded</span>
                        <span class="d-value"><?= Html::encode($traded) ?></span>
                    </div>
                </div>
                <?php if ($marketUrl !== ''): ?>
                    <div class="mu-market-cta">
                        <?= Html::a('Open ' . $marketName . ' ↗', $marketUrl, [
                            'class' => 'mu-market-link', 'target' => '_blank', 'rel' => 'noopener',
                        ]) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
