<?php

use yii\helpers\Html;
use yii\grid\GridView;

?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-bordered'],
    'columns' => [
        'id',
        [
            'attribute' => 'username',
            'label' => 'Wallet Address',
        ],
        [
            'attribute' => 'coinid',
            'label' => 'Coin',
            'value' => function($model) {
                return $model->coin ? $model->coin->symbol : '-';
            },
        ],
        [
            'attribute' => 'balance',
            'format' => ['decimal', 8],
        ],
        [
            'attribute' => 'is_locked',
            'label' => 'Status',
            'format' => 'raw',
            'value' => function($model) {
                if ($model->is_locked) {
                    return '<span class="badge bg-danger">Banned</span>';
                }
                return '<span class="badge bg-success">Active</span>';
            },
        ],
        [
            'attribute' => 'donation',
            'label' => 'Donation %',
        ],
        [
            'class' => 'yii\grid\ActionColumn',
            'template' => '{ban} {unban}',
            'buttons' => [
                'ban' => function ($url, $model) {
                    if (!$model->is_locked) {
                        return Html::a('Ban', ['ban-user', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-warning',
                            'data-method' => 'post',
                            'data-confirm' => 'Are you sure you want to ban this user?',
                        ]);
                    }
                    return '';
                },
                'unban' => function ($url, $model) {
                    if ($model->is_locked) {
                        return Html::a('Unban', ['unban-user', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-success',
                            'data-method' => 'post',
                            'data-confirm' => 'Are you sure you want to unban this user?',
                        ]);
                    }
                    return '';
                },
            ],
        ],
    ],
]); ?>
