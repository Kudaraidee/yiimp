<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\Renters */

$this->title = 'Renter Login';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-login">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <!-- Login Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Login</h3>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'login-form',
                        'action' => ['login'],
                        'method' => 'post',
                    ]); ?>

                    <div class="mb-3">
                        <label for="login-address" class="form-label">Wallet Address</label>
                        <input type="text" class="form-control" id="login-address" name="address" required>
                    </div>

                    <div class="mb-3">
                        <label for="login-password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="login-password" name="password" required>
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Login', ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>

        <!-- Registration Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Register</h3>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'register-form',
                        'action' => ['login'],
                        'method' => 'post',
                    ]); ?>

                    <input type="hidden" name="action" value="register">

                    <div class="mb-3">
                        <label for="register-address" class="form-label">Wallet Address *</label>
                        <input type="text" class="form-control" id="register-address" name="address" required>
                        <small class="form-text text-muted">Your Bitcoin wallet address for deposits</small>
                    </div>

                    <div class="mb-3">
                        <label for="register-email" class="form-label">Email (Optional)</label>
                        <input type="email" class="form-control" id="register-email" name="email">
                    </div>

                    <div class="mb-3">
                        <label for="register-password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="register-password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="register-confirm-password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="register-confirm-password" name="confirm_password" required>
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Register', ['class' => 'btn btn-success']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <strong>Note:</strong> After registration, you will receive an API key that you can use for programmatic access to the rental system.
    </div>
</div>
