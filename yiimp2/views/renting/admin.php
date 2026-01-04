<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $rentersDataProvider yii\data\ActiveDataProvider */
/* @var $jobsDataProvider yii\data\ActiveDataProvider */
/* @var $stats array */

$this->title = 'Rental Administration';
$this->params['breadcrumbs'][] = ['label' => 'Admin', 'url' => ['/admin/dashboard']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-admin">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Rental Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <p><strong>Total Renters:</strong></p>
                            <h4><?= number_format($stats['total_renters']) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Active Orders:</strong></p>
                            <h4 class="text-success"><?= number_format($stats['active_orders']) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Orders:</strong></p>
                            <h4><?= number_format($stats['total_orders']) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Balance:</strong></p>
                            <h4><?= number_format($stats['total_balance'], 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Received:</strong></p>
                            <h4 class="text-success"><?= number_format($stats['total_received'], 8) ?> BTC</h4>
                        </div>
                        <div class="col-md-2">
                            <p><strong>Total Spent:</strong></p>
                            <h4 class="text-danger"><?= number_format($stats['total_spent'], 8) ?> BTC</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h3>All Renters</h3>

    <?= GridView::widget([
        'dataProvider' => $rentersDataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute' => 'id',
                'label' => 'ID',
            ],
            [
                'attribute' => 'address',
                'label' => 'Address',
                'format' => 'raw',
                'value' => function ($model) {
                    return '<small>' . Html::encode(substr($model->address, 0, 30)) . '...</small>';
                },
            ],
            [
                'attribute' => 'email',
                'label' => 'Email',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->email ? Html::encode($model->email) : 'N/A';
                },
            ],
            [
                'attribute' => 'balance',
                'label' => 'Balance',
                'format' => 'raw',
                'value' => function ($model) {
                    return number_format($model->balance, 8) . ' BTC';
                },
            ],
            [
                'attribute' => 'received',
                'label' => 'Received',
                'format' => 'raw',
                'value' => function ($model) {
                    return number_format($model->received, 8) . ' BTC';
                },
            ],
            [
                'attribute' => 'spent',
                'label' => 'Spent',
                'format' => 'raw',
                'value' => function ($model) {
                    return number_format($model->spent, 8) . ' BTC';
                },
            ],
            [
                'label' => 'Orders',
                'format' => 'raw',
                'value' => function ($model) {
                    $count = $model->getJobs()->count();
                    $active = $model->getJobs()->where(['active' => 1])->count();
                    return "$count total ($active active)";
                },
            ],
            [
                'attribute' => 'created',
                'label' => 'Created',
                'format' => 'raw',
                'value' => function ($model) {
                    return date('Y-m-d', $model->created);
                },
            ],
        ],
    ]); ?>

    <h3 class="mt-5">All Rental Orders</h3>

    <?= GridView::widget([
        'dataProvider' => $jobsDataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute' => 'id',
                'label' => 'Order #',
            ],
            [
                'label' => 'Renter',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->renter) {
                        return '<small>' . Html::encode(substr($model->renter->address, 0, 20)) . '...</small>';
                    }
                    return 'N/A';
                },
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
                'label' => 'Price',
                'format' => 'raw',
                'value' => function ($model) {
                    return number_format($model->price, 8) . ' BTC/MH/Day';
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
                'attribute' => 'host',
                'label' => 'Target Pool',
                'format' => 'raw',
                'value' => function ($model) {
                    return '<small>' . Html::encode($model->host . ':' . $model->port) . '</small>';
                },
            ],
            [
                'attribute' => 'time',
                'label' => 'Created',
                'format' => 'raw',
                'value' => function ($model) {
                    return date('Y-m-d H:i', $model->time);
                },
            ],
        ],
    ]); ?>

    <div class="alert alert-info mt-4">
        <strong>Admin Notes:</strong><br>
        - Monitor active orders and renter balances<br>
        - Check for suspicious activity or unusual patterns<br>
        - Ensure renters have sufficient balance for their active orders<br>
        - Review transaction history for any issues
    </div>
</div>
