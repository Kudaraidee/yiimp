<?php

use yii\helpers\Html;
use yii\grid\GridView;
use app\models\Rentertxs;

/* @var $this yii\web\View */
/* @var $renter app\models\Renters */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Balance History';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-balance">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Account Balance</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <p><strong>Current Balance:</strong></p>
                            <h4><?= number_format($renter->balance, 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Available:</strong></p>
                            <h4><?= number_format($renter->getAvailableBalance(), 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Unconfirmed:</strong></p>
                            <h4><?= number_format($renter->unconfirmed, 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Received:</strong></p>
                            <h4 class="text-success"><?= number_format($renter->received, 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Spent:</strong></p>
                            <h4 class="text-danger"><?= number_format($renter->spent, 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <?= Html::a('Deposit Funds', ['deposit'], ['class' => 'btn btn-success']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h3>Transaction History</h3>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute' => 'time',
                'label' => 'Date',
                'format' => 'raw',
                'value' => function ($model) {
                    return date('Y-m-d H:i:s', $model->time);
                },
            ],
            [
                'attribute' => 'type',
                'label' => 'Type',
                'format' => 'raw',
                'value' => function ($model) {
                    $badges = [
                        Rentertxs::TYPE_DEPOSIT => '<span class="badge bg-success">Deposit</span>',
                        Rentertxs::TYPE_ORDER => '<span class="badge bg-primary">Order</span>',
                        Rentertxs::TYPE_REFUND => '<span class="badge bg-info">Refund</span>',
                        Rentertxs::TYPE_WITHDRAWAL => '<span class="badge bg-warning">Withdrawal</span>',
                    ];
                    return $badges[$model->type] ?? Html::encode($model->type);
                },
            ],
            [
                'attribute' => 'amount',
                'label' => 'Amount',
                'format' => 'raw',
                'value' => function ($model) {
                    $class = ($model->isDeposit() || $model->isRefund()) ? 'text-success' : 'text-danger';
                    return '<span class="' . $class . '">' . $model->getFormattedAmount() . ' BTC</span>';
                },
            ],
            [
                'attribute' => 'address',
                'label' => 'Address',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->address) {
                        return '<small>' . Html::encode(substr($model->address, 0, 20)) . '...</small>';
                    }
                    return 'N/A';
                },
            ],
            [
                'attribute' => 'tx',
                'label' => 'Transaction/Note',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->tx) {
                        if (strlen($model->tx) > 30) {
                            return '<small>' . Html::encode(substr($model->tx, 0, 30)) . '...</small>';
                        }
                        return '<small>' . Html::encode($model->tx) . '</small>';
                    }
                    return 'N/A';
                },
            ],
        ],
    ]); ?>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Transaction Types</h4>
                </div>
                <div class="card-body">
                    <ul>
                        <li><strong>Deposit:</strong> Funds added to your account</li>
                        <li><strong>Order:</strong> Costs deducted for rental orders</li>
                        <li><strong>Refund:</strong> Funds returned to your account</li>
                        <li><strong>Withdrawal:</strong> Funds withdrawn from your account</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <?= Html::a('Back to Orders', ['orders'], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Create New Order', ['create'], ['class' => 'btn btn-success']) ?>
    </div>
</div>
