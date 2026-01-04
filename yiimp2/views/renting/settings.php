<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $renter app\models\Renters */

$this->title = 'Rental Settings';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-settings">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Account Settings</h3>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'settings-form',
                        'method' => 'post',
                    ]); ?>

                    <h4>Account Information</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Wallet Address</label>
                        <input type="text" class="form-control" value="<?= Html::encode($renter->address) ?>" disabled>
                        <small class="form-text text-muted">Your wallet address cannot be changed</small>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= Html::encode($renter->email) ?>">
                        <small class="form-text text-muted">Optional: for notifications and account recovery</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="text" class="form-control" value="<?= Html::encode($renter->apikey) ?>" disabled>
                        <small class="form-text text-muted">Use this key for API access</small>
                    </div>

                    <hr>

                    <h4>Default Pool Settings</h4>

                    <div class="mb-3">
                        <label for="custom_server" class="form-label">Default Pool Server</label>
                        <input type="text" class="form-control" id="custom_server" name="custom_server" 
                               value="<?= Html::encode($renter->custom_server) ?>" 
                               placeholder="pool.example.com:3333">
                        <small class="form-text text-muted">Default stratum server for new orders</small>
                    </div>

                    <div class="mb-3">
                        <label for="custom_address" class="form-label">Default Pool Address</label>
                        <input type="text" class="form-control" id="custom_address" name="custom_address" 
                               value="<?= Html::encode($renter->custom_address) ?>" 
                               placeholder="your_wallet_address">
                        <small class="form-text text-muted">Default wallet address for new orders</small>
                    </div>

                    <hr>

                    <h4>Change Password</h4>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Save Settings', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('Cancel', ['orders'], ['class' => 'btn btn-secondary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Account Statistics</h4>
                </div>
                <div class="card-body">
                    <p><strong>Balance:</strong> <?= number_format($renter->balance, 8) ?> BTC</p>
                    <p><strong>Total Received:</strong> <?= number_format($renter->received, 8) ?> BTC</p>
                    <p><strong>Total Spent:</strong> <?= number_format($renter->spent, 8) ?> BTC</p>
                    <p><strong>Account Created:</strong> <?= date('Y-m-d', $renter->created) ?></p>
                    <p><strong>Last Updated:</strong> <?= date('Y-m-d H:i', $renter->updated) ?></p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h4>Quick Links</h4>
                </div>
                <div class="card-body">
                    <?= Html::a('My Orders', ['orders'], ['class' => 'btn btn-primary btn-sm d-block mb-2']) ?>
                    <?= Html::a('Balance History', ['balance'], ['class' => 'btn btn-info btn-sm d-block mb-2']) ?>
                    <?= Html::a('Deposit Funds', ['deposit'], ['class' => 'btn btn-success btn-sm d-block mb-2']) ?>
                    <?= Html::a('Create Order', ['create'], ['class' => 'btn btn-warning btn-sm d-block']) ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h4>Account Actions</h4>
                </div>
                <div class="card-body">
                    <?= Html::beginForm(['logout'], 'post') ?>
                    <?= Html::submitButton('Logout', ['class' => 'btn btn-danger btn-sm d-block']) ?>
                    <?= Html::endForm() ?>
                </div>
            </div>
        </div>
    </div>
</div>
