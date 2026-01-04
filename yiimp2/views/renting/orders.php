<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use app\models\Hashrenter;

/* @var $this yii\web\View */
/* @var $renter app\models\Renters */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'My Rental Orders';
$this->params['breadcrumbs'][] = ['label' => 'Rental Marketplace', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-orders">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Account Summary</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <p><strong>Balance:</strong> <?= number_format($renter->balance, 8) ?> BTC</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Available:</strong> <?= number_format($renter->getAvailableBalance(), 8) ?> BTC</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Total Spent:</strong> <?= number_format($renter->spent, 8) ?> BTC</p>
                        </div>
                        <div class="col-md-3">
                            <?= Html::a('Deposit Funds', ['deposit'], ['class' => 'btn btn-success btn-sm']) ?>
                            <?= Html::a('Create Order', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h3>Your Orders</h3>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute' => 'id',
                'label' => 'Order #',
            ],
            [
                'attribute' => 'algo',
                'label' => 'Algorithm',
            ],
            [
                'attribute' => 'speed',
                'label' => 'Hashrate',
                'format' => 'raw',
                'value' => function ($model) {
                    return Yii::$app->viewUtils->formatHashrate($model->speed);
                },
            ],
            [
                'attribute' => 'price',
                'label' => 'Price (BTC/MH/Day)',
                'format' => 'raw',
                'value' => function ($model) {
                    return number_format($model->price, 8);
                },
            ],
            [
                'label' => 'Status',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->active) {
                        return '<span class="badge bg-success">Active</span>';
                    } elseif ($model->ready) {
                        return '<span class="badge bg-warning">Ready</span>';
                    } else {
                        return '<span class="badge bg-secondary">Inactive</span>';
                    }
                },
            ],
            [
                'label' => 'Delivered Hashrate',
                'format' => 'raw',
                'value' => function ($model) {
                    $hashrate = Hashrenter::find()
                        ->where(['jobid' => $model->id])
                        ->sum('hashrate');
                    return $hashrate ? Yii::$app->viewUtils->formatHashrate($hashrate) : 'N/A';
                },
            ],
            [
                'label' => 'Estimated Cost',
                'format' => 'raw',
                'value' => function ($model) {
                    $cost = $model->price * $model->speed;
                    return number_format($cost, 8) . ' BTC/day';
                },
            ],
            [
                'attribute' => 'time',
                'label' => 'Created',
                'format' => 'raw',
                'value' => function ($model) {
                    return date('Y-m-d H:i:s', $model->time);
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{activate} {deactivate} {complete}',
                'buttons' => [
                    'activate' => function ($url, $model, $key) {
                        if (!$model->active) {
                            return Html::a('Activate', ['activate-order', 'id' => $model->id], [
                                'class' => 'btn btn-sm btn-success',
                                'data-method' => 'post',
                            ]);
                        }
                        return '';
                    },
                    'deactivate' => function ($url, $model, $key) {
                        if ($model->active) {
                            return Html::a('Deactivate', ['deactivate-order', 'id' => $model->id], [
                                'class' => 'btn btn-sm btn-warning',
                                'data-method' => 'post',
                            ]);
                        }
                        return '';
                    },
                    'complete' => function ($url, $model, $key) {
                        if ($model->active) {
                            return Html::a('Complete', ['complete-order', 'id' => $model->id], [
                                'class' => 'btn btn-sm btn-danger',
                                'data-method' => 'post',
                                'data-confirm' => 'Are you sure you want to complete this order? Your balance will be deducted.',
                            ]);
                        }
                        return '';
                    },
                ],
            ],
        ],
    ]); ?>

    <div class="alert alert-info mt-4">
        <strong>Order Management:</strong><br>
        - <strong>Activate:</strong> Start directing hashrate to your target pool<br>
        - <strong>Deactivate:</strong> Temporarily stop the order without completing it<br>
        - <strong>Complete:</strong> Finalize the order and deduct costs from your balance
    </div>
</div>
