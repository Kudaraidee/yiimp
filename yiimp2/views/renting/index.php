<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Rental Marketplace';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="renting-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info">
        <strong>Hashrate Rental Marketplace</strong><br>
        Browse and rent mining hashrate for various algorithms. All active rental orders are displayed below.
    </div>

    <?php if (!Yii::$app->session->has('renter_id')): ?>
        <div class="alert alert-warning">
            <strong>Not logged in</strong><br>
            <?= Html::a('Login', ['login'], ['class' => 'btn btn-primary btn-sm']) ?> to create rental orders or manage your account.
        </div>
    <?php else: ?>
        <div class="mb-3">
            <?= Html::a('Create New Order', ['create'], ['class' => 'btn btn-success']) ?>
            <?= Html::a('My Orders', ['orders'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Balance History', ['balance'], ['class' => 'btn btn-info']) ?>
            <?= Html::a('Settings', ['settings'], ['class' => 'btn btn-secondary']) ?>
        </div>
    <?php endif; ?>

    <h3>Active Rental Orders</h3>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

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
                'attribute' => 'host',
                'label' => 'Target Pool',
                'format' => 'raw',
                'value' => function ($model) {
                    return Html::encode($model->host . ':' . $model->port);
                },
            ],
            [
                'attribute' => 'percent',
                'label' => 'Percent',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->percent ? number_format($model->percent, 2) . '%' : 'N/A';
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
                'template' => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) {
                        return Html::a('View', ['view', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']);
                    },
                ],
            ],
        ],
    ]); ?>

</div>
