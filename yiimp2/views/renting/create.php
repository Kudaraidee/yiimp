<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use app\models\Algos;

/* @var $this yii\web\View */
/* @var $model app\models\Jobs */
/* @var $renter app\models\Renters */

$this->title = 'Create Rental Order';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Get available algorithms
$algos = Algos::find()->select(['name'])->column();
?>

<div class="renting-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Order Details</h3>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'create-order-form',
                    ]); ?>

                    <?= $form->field($model, 'algo')->dropDownList(
                        array_combine($algos, $algos),
                        ['prompt' => 'Select Algorithm']
                    )->label('Algorithm *') ?>

                    <?= $form->field($model, 'speed')->textInput([
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0.01',
                    ])->label('Hashrate (MH/s) *')->hint('The amount of hashrate you want to rent') ?>

                    <?= $form->field($model, 'price')->textInput([
                        'type' => 'number',
                        'step' => '0.00000001',
                        'min' => '0.00000001',
                    ])->label('Price (BTC/MH/Day) *')->hint('Price you are willing to pay per MH/s per day') ?>

                    <?= $form->field($model, 'host')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'pool.example.com',
                    ])->label('Target Pool Host *')->hint('The stratum server hostname or IP') ?>

                    <?= $form->field($model, 'port')->textInput([
                        'type' => 'number',
                        'min' => '1',
                        'max' => '65535',
                        'placeholder' => '3333',
                    ])->label('Target Pool Port *')->hint('The stratum server port') ?>

                    <?= $form->field($model, 'username')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'your_wallet_address',
                    ])->label('Username/Wallet *')->hint('Your wallet address or username for the target pool') ?>

                    <?= $form->field($model, 'password')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'x',
                    ])->label('Password')->hint('Worker password (usually "x" or "c=BTC")') ?>

                    <?= $form->field($model, 'percent')->textInput([
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0',
                        'max' => '100',
                        'value' => '100',
                    ])->label('Percent')->hint('Percentage of hashrate to allocate (0-100)') ?>

                    <div class="alert alert-info">
                        <strong>Estimated Cost:</strong> <span id="estimated-cost">0.00000000</span> BTC/day<br>
                        <strong>Your Balance:</strong> <?= number_format($renter->getAvailableBalance(), 8) ?> BTC
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Create Order', ['class' => 'btn btn-success']) ?>
                        <?= Html::a('Cancel', ['orders'], ['class' => 'btn btn-secondary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Order Information</h4>
                </div>
                <div class="card-body">
                    <p><strong>How it works:</strong></p>
                    <ol class="small">
                        <li>Specify the algorithm and hashrate you want to rent</li>
                        <li>Set your price per MH/s per day</li>
                        <li>Provide your target pool details</li>
                        <li>The system will direct matching hashrate to your pool</li>
                        <li>You will be charged based on actual hashrate delivered</li>
                    </ol>
                    
                    <div class="alert alert-warning small mt-3">
                        <strong>Note:</strong> Make sure you have sufficient balance to cover the estimated costs. 
                        Orders will be automatically deactivated if your balance is insufficient.
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h4>Quick Links</h4>
                </div>
                <div class="card-body">
                    <?= Html::a('Deposit Funds', ['deposit'], ['class' => 'btn btn-info btn-sm d-block mb-2']) ?>
                    <?= Html::a('My Orders', ['orders'], ['class' => 'btn btn-primary btn-sm d-block mb-2']) ?>
                    <?= Html::a('Balance History', ['balance'], ['class' => 'btn btn-secondary btn-sm d-block']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs("
    function updateEstimatedCost() {
        var speed = parseFloat($('#jobs-speed').val()) || 0;
        var price = parseFloat($('#jobs-price').val()) || 0;
        var cost = speed * price;
        $('#estimated-cost').text(cost.toFixed(8));
    }
    
    $('#jobs-speed, #jobs-price').on('input', updateEstimatedCost);
    updateEstimatedCost();
");
?>
