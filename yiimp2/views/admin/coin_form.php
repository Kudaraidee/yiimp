<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\bootstrap5\ActiveForm;
use app\models\Algos;

$this->title = $isNew ? 'Create New Coin' : 'Update Coin: ' . $model->name;

// Get algos for dropdown
$algos = ArrayHelper::map(
    Algos::find()->orderBy('name')->all(),
    'name',
    'name'
);

// Register custom CSS for coin form
$this->registerCss(<<<CSS
.admin-coin-form .card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.admin-coin-form .nav-tabs {
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 1.5rem;
}

.admin-coin-form .nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
    padding: 0.75rem 1.25rem;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease-in-out;
}

.admin-coin-form .nav-tabs .nav-link:hover {
    color: #007bff;
    border-bottom-color: #007bff;
    background-color: transparent;
}

.admin-coin-form .nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background-color: transparent;
}

.admin-coin-form .form-group {
    margin-bottom: 1.25rem;
}

.admin-coin-form .hint-block {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
    display: block;
}

.admin-coin-form .form-control[readonly] {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.admin-coin-form .form-check {
    padding-left: 1.5rem;
}

.admin-coin-form .form-check-input {
    margin-top: 0.3rem;
    cursor: pointer;
}

.admin-coin-form .form-check-label {
    cursor: pointer;
}

.admin-coin-form h5 {
    color: #495057;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

.admin-coin-form pre {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 1rem;
    font-size: 0.875rem;
    overflow-x: auto;
}

.admin-coin-form .btn-success {
    padding: 0.5rem 2rem;
    font-weight: 500;
}

.admin-coin-form .btn-secondary {
    padding: 0.5rem 2rem;
}

/* Loading overlay */
.form-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.form-loading-overlay.active {
    display: flex;
}

.form-loading-spinner {
    text-align: center;
    color: white;
}

.form-loading-spinner .spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3rem;
}

/* Validation feedback styling */
.admin-coin-form .invalid-feedback {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
}

.admin-coin-form .is-invalid {
    border-color: #dc3545;
}

.admin-coin-form .is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

/* Responsive design */
@media (max-width: 768px) {
    .admin-coin-form .nav-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-coin-form .nav-tabs .nav-link {
        white-space: nowrap;
        padding: 0.5rem 1rem;
    }
    
    .admin-coin-form h1 {
        font-size: 1.5rem;
    }
    
    .admin-coin-form .btn-success,
    .admin-coin-form .btn-secondary {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Focus indicators for accessibility */
.admin-coin-form .nav-link:focus,
.admin-coin-form .form-control:focus,
.admin-coin-form .form-check-input:focus,
.admin-coin-form .btn:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .admin-coin-form .nav-tabs .nav-link.active {
        border-bottom-width: 4px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .admin-coin-form .nav-tabs .nav-link {
        transition: none;
    }
}
CSS
);
?>
<div class="admin-coin-form">
    <h1><?= Html::encode($this->title) ?></h1>

    <!-- Loading overlay -->
    <div class="form-loading-overlay" id="formLoadingOverlay" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="form-loading-spinner">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Saving...</span>
            </div>
            <div class="mt-3">Saving coin configuration...</div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'id' => 'coin-form',
                'method' => 'post',
                'action' => $isNew ? ['admin/coin-create'] : ['admin/coin-update', 'id' => $model->id],
                'enableClientValidation' => false,
                'enableAjaxValidation' => false,
                'validateOnSubmit' => false,
            ]); ?>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="coinTabs" role="tablist" aria-label="Coin configuration sections">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true" aria-label="General settings tab">
                        <span aria-hidden="true">General</span>
                        <span class="visually-hidden">General settings</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false" aria-label="Coin behavior settings tab">
                        <span aria-hidden="true">Settings</span>
                        <span class="visually-hidden">Coin behavior settings</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="exchange-tab" data-bs-toggle="tab" data-bs-target="#exchange" type="button" role="tab" aria-controls="exchange" aria-selected="false" aria-label="Exchange settings tab">
                        <span aria-hidden="true">Exchange</span>
                        <span class="visually-hidden">Exchange settings</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="daemon-tab" data-bs-toggle="tab" data-bs-target="#daemon" type="button" role="tab" aria-controls="daemon" aria-selected="false" aria-label="Daemon connection settings tab">
                        <span aria-hidden="true">Daemon</span>
                        <span class="visually-hidden">Daemon connection settings</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="links-tab" data-bs-toggle="tab" data-bs-target="#links" type="button" role="tab" aria-controls="links" aria-selected="false" aria-label="External links tab">
                        <span aria-hidden="true">Links</span>
                        <span class="visually-hidden">External links</span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content mt-3" id="coinTabsContent">
                
                <!-- General Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'name')->textInput(['maxlength' => true])
                                ->hint('Full name of the cryptocurrency') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'symbol')->textInput(['maxlength' => true])
                                ->hint('Trading symbol (e.g., BTC)') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'symbol2')->textInput(['maxlength' => true])
                                ->hint('Official symbol if different from trading symbol') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'algo')->dropDownList($algos, ['prompt' => 'Select Algorithm'])
                                ->hint('Mining algorithm (required, all lowercase)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'image')->textInput(['maxlength' => true])
                                ->hint('URL to coin logo image') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'version_installed')->textInput(['maxlength' => true])
                                ->hint('Wallet version currently installed') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'version_github')->textInput(['maxlength' => true, 'readonly' => true])
                                ->hint('Latest wallet version on GitHub (auto-updated)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <?= $form->field($model, 'payout_min')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Minimum payout amount to users') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'payout_max')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Maximum transaction amount') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'txfee')->textInput(['type' => 'number', 'step' => 'any', 'readonly' => true])
                                ->hint('Transaction fee (auto-updated from daemon)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <?= $form->field($model, 'block_height')->textInput(['type' => 'number', 'readonly' => true])
                                ->hint('Current blockchain height') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'target_height')->textInput(['type' => 'number'])
                                ->hint('Known network height') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'powend_height')->textInput(['type' => 'number'])
                                ->hint('Height where PoW mining ends') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'mature_blocks')->textInput(['type' => 'number'])
                                ->hint('Blocks required for maturity') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <?= $form->field($model, 'powlimit_bits')->textInput(['type' => 'number'])
                                ->hint('Number of leading \'0\' bits on powlimit') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'block_time')->textInput(['type' => 'number'])
                                ->hint('Average block time in seconds') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'decimals')->textInput(['type' => 'number'])
                                ->hint('Number of decimal places (default: 8)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'errors')->textInput(['maxlength' => true, 'readonly' => true])
                                ->hint('Error messages from daemon (auto-updated)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'specifications')->textarea(['rows' => 5])
                                ->hint('Coin specifications and additional information') ?>
                        </div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                    <h5>Status Flags</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <?= $form->field($model, 'enable')->checkbox()
                                ->hint('Enable this coin for mining') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'auto_ready')->checkbox()
                                ->hint('Coin is ready for automatic mining') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'visible')->checkbox()
                                ->hint('Visible to public users') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'installed')->checkbox()
                                ->hint('Required for Wallets board visibility') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <?= $form->field($model, 'no_explorer')->checkbox()
                                ->hint('Disable block explorer for public') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'watch')->checkbox()
                                ->hint('Track balance and market history') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'auxpow')->checkbox()
                                ->hint('Enable merged mining (AuxPoW)') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'enable_rpcdebug')->checkbox()
                                ->hint('Enable RPC debug logging') ?>
                        </div>
                    </div>

                    <h5 class="mt-4">Protocol Features</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <?= $form->field($model, 'hasgetinfo')->checkbox()
                                ->hint('Daemon supports getinfo RPC') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'hassubmitblock')->checkbox()
                                ->hint('Daemon supports submitblock RPC') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'txmessage')->checkbox()
                                ->hint('Block template includes TX message') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'hasmasternodes')->checkbox()
                                ->hint('Coin has masternode support') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <?= $form->field($model, 'usesegwit')->checkbox()
                                ->hint('Coin uses SegWit') ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($model, 'usemweb')->checkbox()
                                ->hint('Coin uses MimbleWimble Extension Blocks') ?>
                        </div>
                    </div>

                    <h5 class="mt-4">Configuration</h5>
                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'personalization')->textInput(['maxlength' => true])
                                ->hint('Equihash personalization string (e.g., "ZcashPoW")') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'max_miners')->textInput(['type' => 'number'])
                                ->hint('Maximum miners allowed by stratum') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'max_shares')->textInput(['type' => 'number'])
                                ->hint('Auto-restart stratum after this share count') ?>
                        </div>
                    </div>

                    <h5 class="mt-4">Wallet Addresses</h5>
                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'master_wallet')->textInput(['maxlength' => true])
                                ->hint('Pool\'s primary wallet address') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'wallet_zaddress')->textInput(['maxlength' => true])
                                ->hint('Z-address for privacy coins (Zcash, etc.)') ?>
                        </div>
                    </div>

                    <h5 class="mt-4">Rewards</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'reward')->textInput(['type' => 'number', 'step' => 'any', 'readonly' => true])
                                ->hint('Block reward (auto-updated from daemon)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'reward_mul')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Multiplier to adjust block reward if incorrect') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <?= $form->field($model, 'charity_percent')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Dev fee percentage (0-100)') ?>
                        </div>
                        <div class="col-md-8">
                            <?= $form->field($model, 'charity_address')->textInput(['maxlength' => true])
                                ->hint('Foundation/developer fee address') ?>
                        </div>
                    </div>
                </div>

                <!-- Exchange Tab -->
                <div class="tab-pane fade" id="exchange" role="tabpanel" aria-labelledby="exchange-tab">
                    <div class="row">
                        <div class="col-md-4">
                            <?= $form->field($model, 'dontsell')->checkbox()
                                ->hint('Disable automatic sending to exchanges') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'sellonbid')->checkbox()
                                ->hint('Sell at bid price instead of ask') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'auto_exchange')->checkbox()
                                ->hint('Include in automatic mining selection') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'sellthreshold')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Minimum amount to sell on exchange') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'market')->textInput(['maxlength' => true])
                                ->hint('Selected exchange market') ?>
                        </div>
                    </div>

                    <?php if (empty($model->price) || empty($model->market) || $model->market == 'unknown'): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'price')->textInput(['type' => 'number', 'step' => 'any'])
                                ->hint('Manually set BTC price if market data unavailable') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Daemon Tab -->
                <div class="tab-pane fade" id="daemon" role="tabpanel" aria-labelledby="daemon-tab">
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'program')->textInput(['maxlength' => true])
                                ->hint('Daemon process name (e.g., bitcoind)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'conf_folder')->textInput(['maxlength' => true])
                                ->hint('Config folder name (e.g., .bitcoin)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'rpchost')->textInput(['maxlength' => true, 'placeholder' => 'localhost'])
                                ->hint('Daemon RPC host address') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'rpcport')->textInput(['type' => 'number'])
                                ->hint('Daemon RPC port (1-65535)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'rpcwallet')->textInput(['maxlength' => true])
                                ->hint('Wallet name for Bitcoin Core 0.17+ (e.g., "pool")') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'rpcuser')->textInput(['maxlength' => true])
                                ->hint('RPC username (default: yiimprpc)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'rpcpasswd')->passwordInput(['maxlength' => true])
                                ->hint('RPC password (auto-generated if empty)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'serveruser')->textInput(['maxlength' => true])
                                ->hint('Server username running the daemon') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'rpcencoding')->textInput(['maxlength' => true, 'placeholder' => 'POW'])
                                ->hint('RPC encoding type (POW/POS/DCR/DGB/GETH)') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'dedicatedport')->textInput(['type' => 'number'])
                                ->hint('Dedicated stratum port for this coin') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'account')->textInput(['maxlength' => true])
                                ->hint('Wallet account to use') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <?= $form->field($model, 'rpccurl')->checkbox()
                                ->hint('Force stratum to use curl for RPC') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'rpcssl')->checkbox()
                                ->hint('Use SSL for RPC connection') ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'rpccert')->textInput(['maxlength' => true])
                                ->hint('SSL certificate file path') ?>
                        </div>
                    </div>

                    <?php if (!$isNew && $model->id): ?>
                    <hr class="my-4">
                    <h5>Sample Daemon Configuration</h5>
                    <pre class="bg-light p-3 rounded"><?= Html::encode($model->getSampleConfig()) ?></pre>

                    <h5 class="mt-4">Sample Miner Command</h5>
                    <pre class="bg-light p-3 rounded"><?= Html::encode($model->getSampleMinerCommand()) ?></pre>
                    <?php endif; ?>
                </div>

                <!-- Links Tab -->
                <div class="tab-pane fade" id="links" role="tabpanel" aria-labelledby="links-tab">
                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_bitcointalk')->textInput(['maxlength' => true])
                                ->hint('BitcoinTalk forum thread URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_github')->textInput(['maxlength' => true])
                                ->hint('GitHub repository URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_site')->textInput(['maxlength' => true])
                                ->hint('Official website URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_exchange')->textInput(['maxlength' => true])
                                ->hint('Exchange listing URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_explorer')->textInput(['maxlength' => true])
                                ->hint('Block explorer URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_twitter')->textInput(['maxlength' => true])
                                ->hint('Twitter profile URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_discord')->textInput(['maxlength' => true])
                                ->hint('Discord server invite URL') ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <?= $form->field($model, 'link_facebook')->textInput(['maxlength' => true])
                                ->hint('Facebook page URL') ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Form Actions (visible on all tabs) -->
            <div class="form-group mt-4">
                <?= Html::submitButton($isNew ? 'Create Coin' : 'Update Coin', [
                    'class' => 'btn btn-success',
                    'type' => 'submit',
                    'name' => 'submit-button'
                ]) ?>
                <?= Html::a('Cancel', $isNew ? ['coinwallets'] : ['coin', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
// JavaScript for tab state management, URL hash handling, form state preservation, and client-side validation
$this->registerJs(<<<JS
(function($) {
    'use strict';
    
    // Form state storage key
    const FORM_STATE_KEY = 'coin_form_state_' + ('{$model->id}' || 'new');
    
    // Initialize tab from URL hash or default to first tab
    function initializeTab() {
        let hash = window.location.hash;
        if (hash) {
            let tabTrigger = document.querySelector('#coinTabs button[data-bs-target="' + hash + '"]');
            if (tabTrigger) {
                let tab = new bootstrap.Tab(tabTrigger);
                tab.show();
            }
        }
    }
    
    // Save form state to sessionStorage
    function saveFormState() {
        const formData = {};
        $('#coin-form').find('input, select, textarea').each(function() {
            const field = $(this);
            const name = field.attr('name');
            if (name) {
                if (field.attr('type') === 'checkbox') {
                    formData[name] = field.is(':checked');
                } else if (field.attr('type') === 'radio') {
                    if (field.is(':checked')) {
                        formData[name] = field.val();
                    }
                } else {
                    formData[name] = field.val();
                }
            }
        });
        try {
            sessionStorage.setItem(FORM_STATE_KEY, JSON.stringify(formData));
        } catch (e) {
            console.warn('Failed to save form state:', e);
        }
    }
    
    // Restore form state from sessionStorage
    function restoreFormState() {
        try {
            const savedState = sessionStorage.getItem(FORM_STATE_KEY);
            if (savedState) {
                const formData = JSON.parse(savedState);
                Object.keys(formData).forEach(function(name) {
                    const field = $('#coin-form').find('[name="' + name + '"]');
                    if (field.length) {
                        if (field.attr('type') === 'checkbox') {
                            field.prop('checked', formData[name]);
                        } else if (field.attr('type') === 'radio') {
                            field.filter('[value="' + formData[name] + '"]').prop('checked', true);
                        } else {
                            field.val(formData[name]);
                        }
                    }
                });
            }
        } catch (e) {
            console.warn('Failed to restore form state:', e);
        }
    }
    
    // Clear form state from sessionStorage
    function clearFormState() {
        try {
            sessionStorage.removeItem(FORM_STATE_KEY);
        } catch (e) {
            console.warn('Failed to clear form state:', e);
        }
    }
    
    // Enhanced client-side validation feedback
    function setupClientValidation() {
        // Validate charity_percent (0-100)
        $('input[name="Coins[charity_percent]"]').on('input blur', function() {
            const value = parseFloat($(this).val());
            const field = $(this).closest('.field-coins-charity_percent');
            field.find('.invalid-feedback').remove();
            field.removeClass('has-error');
            
            if ($(this).val() !== '' && (isNaN(value) || value < 0 || value > 100)) {
                field.addClass('has-error');
                $(this).addClass('is-invalid');
                if (!field.find('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Charity percent must be between 0 and 100</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Validate rpcport (1-65535)
        $('input[name="Coins[rpcport]"]').on('input blur', function() {
            const value = parseInt($(this).val());
            const field = $(this).closest('.field-coins-rpcport');
            field.find('.invalid-feedback').remove();
            field.removeClass('has-error');
            
            if ($(this).val() !== '' && (isNaN(value) || value < 1 || value > 65535)) {
                field.addClass('has-error');
                $(this).addClass('is-invalid');
                if (!field.find('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">RPC port must be between 1 and 65535</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Validate positive numeric fields
        const positiveFields = [
            'Coins[payout_min]',
            'Coins[payout_max]',
            'Coins[sellthreshold]',
            'Coins[price]'
        ];
        
        positiveFields.forEach(function(fieldName) {
            $('input[name="' + fieldName + '"]').on('input blur', function() {
                const value = parseFloat($(this).val());
                const field = $(this).closest('.form-group');
                field.find('.invalid-feedback').remove();
                field.removeClass('has-error');
                
                if ($(this).val() !== '' && (isNaN(value) || value < 0)) {
                    field.addClass('has-error');
                    $(this).addClass('is-invalid');
                    if (!field.find('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Value must be a positive number</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
        });
        
        // Validate URL fields
        const urlFields = [
            'Coins[link_bitcointalk]',
            'Coins[link_github]',
            'Coins[link_site]',
            'Coins[link_exchange]',
            'Coins[link_explorer]',
            'Coins[link_twitter]',
            'Coins[link_discord]',
            'Coins[link_facebook]'
        ];
        
        const urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
        
        urlFields.forEach(function(fieldName) {
            $('input[name="' + fieldName + '"]').on('blur', function() {
                const value = $(this).val().trim();
                const field = $(this).closest('.form-group');
                field.find('.invalid-feedback').remove();
                field.removeClass('has-error');
                
                if (value !== '' && !urlPattern.test(value)) {
                    field.addClass('has-error');
                    $(this).addClass('is-invalid');
                    if (!field.find('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Please enter a valid URL</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
        });
        
        // Validate required fields
        const requiredFields = ['Coins[name]', 'Coins[symbol]', 'Coins[algo]'];
        
        requiredFields.forEach(function(fieldName) {
            $('[name="' + fieldName + '"]').on('blur', function() {
                const value = $(this).val();
                const field = $(this).closest('.form-group');
                field.find('.invalid-feedback').remove();
                field.removeClass('has-error');
                
                if (!value || value.trim() === '') {
                    field.addClass('has-error');
                    $(this).addClass('is-invalid');
                    if (!field.find('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">This field is required</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
        });
    }
    
    // Validate form before submission
    function validateFormBeforeSubmit() {
        let isValid = true;
        const errors = [];
        
        // Check required fields
        const name = $('input[name="Coins[name]"]').val();
        const symbol = $('input[name="Coins[symbol]"]').val();
        const algo = $('select[name="Coins[algo]"]').val();
        
        if (!name || name.trim() === '') {
            errors.push('Coin name is required');
            isValid = false;
        }
        if (!symbol || symbol.trim() === '') {
            errors.push('Coin symbol is required');
            isValid = false;
        }
        if (!algo || algo === '') {
            errors.push('Algorithm is required');
            isValid = false;
        }
        
        // Check for any visible validation errors
        if ($('.is-invalid:visible').length > 0) {
            errors.push('Please fix validation errors before submitting');
            isValid = false;
        }
        
        if (!isValid) {
            alert('Form validation failed:\\n\\n' + errors.join('\\n'));
        }
        
        return isValid;
    }
    
    // Show loading overlay
    function showLoadingOverlay() {
        $('#formLoadingOverlay').addClass('active');
        // Disable form inputs to prevent changes during submission
        $('#coin-form').find('input, select, textarea, button').prop('disabled', true);
    }
    
    // Hide loading overlay
    function hideLoadingOverlay() {
        $('#formLoadingOverlay').removeClass('active');
        // Re-enable form inputs
        $('#coin-form').find('input, select, textarea, button').prop('disabled', false);
    }
    
    // Update URL hash when tab changes
    document.querySelectorAll('#coinTabs button[data-bs-toggle="tab"]').forEach(function(tabButton) {
        tabButton.addEventListener('shown.bs.tab', function(event) {
            let target = event.target.getAttribute('data-bs-target');
            if (target) {
                window.location.hash = target;
            }
            // Save form state when switching tabs
            saveFormState();
            
            // Announce tab change to screen readers
            const tabName = event.target.textContent.trim();
            const announcement = document.createElement('div');
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'visually-hidden';
            announcement.textContent = tabName + ' tab selected';
            document.body.appendChild(announcement);
            setTimeout(function() {
                document.body.removeChild(announcement);
            }, 1000);
        });
    });
    
    // Keyboard navigation for tabs
    document.querySelectorAll('#coinTabs button[data-bs-toggle="tab"]').forEach(function(tabButton, index, tabs) {
        tabButton.addEventListener('keydown', function(event) {
            let newIndex = index;
            
            // Arrow key navigation
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                event.preventDefault();
                newIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                event.preventDefault();
                newIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                event.preventDefault();
                newIndex = 0;
            } else if (event.key === 'End') {
                event.preventDefault();
                newIndex = tabs.length - 1;
            } else {
                return;
            }
            
            // Activate the new tab
            const newTab = new bootstrap.Tab(tabs[newIndex]);
            newTab.show();
            tabs[newIndex].focus();
        });
    });
    
    // Initialize on page load
    $(document).ready(function() {
        initializeTab();
        restoreFormState();
        setupClientValidation();
        
        // Auto-save form state periodically
        setInterval(saveFormState, 5000);
        
        // Save form state on any input change
        \$('#coin-form').on('change input', 'input, select, textarea', function() {
            // Debounce the save operation
            clearTimeout(window.formStateSaveTimeout);
            window.formStateSaveTimeout = setTimeout(saveFormState, 500);
        });
        
        // Handle form submission via AJAX
        \$('#coin-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validate form before submission
            if (!validateFormBeforeSubmit()) {
                return false;
            }
            
            // Temporarily enable all fields for serialization
            var disabledFields = \$('#coin-form').find(':disabled');
            disabledFields.prop('disabled', false);
            
            // Collect form data manually
            var formData = {};
            \$('#coin-form').find('input, select, textarea').each(function() {
                var el = \$(this);
                var name = el.attr('name');
                if (name) {
                    if (el.attr('type') === 'checkbox') {
                        formData[name] = el.is(':checked') ? '1' : '0';
                    } else if (el.attr('type') !== 'radio' || el.is(':checked')) {
                        formData[name] = el.val() || '';
                    }
                }
            });
            
            // Re-disable the fields
            disabledFields.prop('disabled', true);
            
            console.log('Manual form data keys:', Object.keys(formData).length);
            console.log('CSRF token:', formData['_csrf-yiimp2'] ? 'present' : 'MISSING');
            
            // Show loading overlay and clear form state
            showLoadingOverlay();
            clearFormState();
            \$('#coin-form').data('submitting', true);
            
            // Submit via AJAX
            \$.ajax({
                url: \$(this).attr('action'),
                type: 'POST',
                data: formData,
                success: function(response, textStatus, xhr) {
                    // Check if response contains success message or redirect
                    if (response.indexOf('Coin created successfully') !== -1 || 
                        response.indexOf('coinlist') !== -1 ||
                        response.indexOf('class="alert-success"') !== -1) {
                        window.location.href = '/admin/coinlist';
                    } else {
                        // Replace page content with response (shows validation errors if any)
                        document.open();
                        document.write(response);
                        document.close();
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    // Handle redirect as success (302 Found means coin was created)
                    if (xhr.status === 302 || xhr.status === 301 || errorThrown === 'Found') {
                        window.location.href = '/admin/coinlist';
                        return;
                    }
                    // Check if response contains success indicators
                    if (xhr.responseText && (
                        xhr.responseText.indexOf('Coin created successfully') !== -1 ||
                        xhr.responseText.indexOf('alert-success') !== -1)) {
                        window.location.href = '/admin/coinlist';
                        return;
                    }
                    hideLoadingOverlay();
                    alert('Error submitting form: ' + textStatus + ' - ' + errorThrown);
                }
            });
            
            return false;
        });
        
        // Handle form submission errors (if AJAX validation fails)
        \$('#coin-form').on('afterValidate', function(event, messages, errorAttributes) {
            if (errorAttributes.length > 0) {
                hideLoadingOverlay();
                \$('#coin-form').data('submitting', false);
                
                // Find the tab containing the first error and switch to it
                const firstErrorField = errorAttributes[0];
                const errorElement = \$('#coin-form').find('[name="' + firstErrorField + '"]');
                if (errorElement.length) {
                    const tabPane = errorElement.closest('.tab-pane');
                    if (tabPane.length) {
                        const tabId = tabPane.attr('id');
                        const tabButton = document.querySelector('#coinTabs button[data-bs-target="#' + tabId + '"]');
                        if (tabButton) {
                            const tab = new bootstrap.Tab(tabButton);
                            tab.show();
                        }
                    }
                }
            }
        });
        
        // Add smooth scroll to error fields
        $(document).on('click', '.invalid-feedback', function() {
            const field = $(this).prev('input, select, textarea');
            if (field.length) {
                field.focus();
                field[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('hashchange', function() {
        initializeTab();
    });
    
    // Clear form state when leaving the page (except on form submission)
    window.addEventListener('beforeunload', function(e) {
        // Don't clear if form is being submitted
        if (!$('#coin-form').data('submitting')) {
            saveFormState();
        }
    });
    
    // Handle page visibility changes (e.g., tab switching)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !$('#coin-form').data('submitting')) {
            // Page became visible, restore form state if needed
            restoreFormState();
        }
    });
    
})(jQuery);
JS
);
?>
</div>
</div>
