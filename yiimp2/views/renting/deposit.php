<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $renter app\models\Renters */

$this->title = 'Deposit Funds';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-deposit">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Current Balance</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Total Balance:</strong> <?= number_format($renter->balance, 8) ?> BTC</p>
                            <p><strong>Available:</strong> <?= number_format($renter->getAvailableBalance(), 8) ?> BTC</p>
                            <p><strong>Unconfirmed:</strong> <?= number_format($renter->unconfirmed, 8) ?> BTC</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Received:</strong> <?= number_format($renter->received, 8) ?> BTC</p>
                            <p><strong>Total Spent:</strong> <?= number_format($renter->spent, 8) ?> BTC</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3>Make a Deposit</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Deposit Instructions:</strong><br>
                        1. Send Bitcoin to your deposit address: <code><?= Html::encode($renter->address) ?></code><br>
                        2. After sending, fill out the form below to record your deposit<br>
                        3. Your balance will be updated once the transaction is confirmed
                    </div>

                    <?php $form = ActiveForm::begin([
                        'id' => 'deposit-form',
                        'action' => ['deposit-process'],
                        'method' => 'post',
                    ]); ?>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Deposit Amount (BTC) *</label>
                        <input type="number" step="0.00000001" class="form-control" id="amount" name="amount" required>
                        <small class="form-text text-muted">Enter the exact amount you sent</small>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">From Address</label>
                        <input type="text" class="form-control" id="address" name="address">
                        <small class="form-text text-muted">The address you sent from (optional)</small>
                    </div>

                    <div class="mb-3">
                        <label for="tx_hash" class="form-label">Transaction Hash</label>
                        <input type="text" class="form-control" id="tx_hash" name="tx_hash">
                        <small class="form-text text-muted">The transaction ID (optional but recommended)</small>
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Record Deposit', ['class' => 'btn btn-success']) ?>
                        <?= Html::a('Cancel', ['orders'], ['class' => 'btn btn-secondary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Deposit Address</h4>
                </div>
                <div class="card-body">
                    <p><strong>Your Deposit Address:</strong></p>
                    <div class="alert alert-secondary">
                        <code class="word-break-all"><?= Html::encode($renter->address) ?></code>
                    </div>
                    <p class="text-muted small">
                        Send Bitcoin to this address to fund your rental account. 
                        Make sure to record the deposit after sending.
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h4>Quick Links</h4>
                </div>
                <div class="card-body">
                    <?= Html::a('View Balance History', ['balance'], ['class' => 'btn btn-info btn-sm d-block mb-2']) ?>
                    <?= Html::a('My Orders', ['orders'], ['class' => 'btn btn-primary btn-sm d-block mb-2']) ?>
                    <?= Html::a('Create Order', ['create'], ['class' => 'btn btn-success btn-sm d-block']) ?>
                </div>
            </div>
        </div>
    </div>
</div>
