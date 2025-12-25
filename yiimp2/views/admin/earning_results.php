<?php

use yii\helpers\Html;
use yii\grid\GridView;

?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-bordered table-sm'],
    'columns' => [
        'id',
        [
            'attribute' => 'userid',
            'label' => 'User',
            'value' => function($model) {
                return $model->account ? substr($model->account->username, 0, 25) . '...' : '-';
            },
        ],
        [
            'attribute' => 'coinid',
            'label' => 'Coin',
            'value' => function($model) {
                return $model->coin ? $model->coin->symbol : '-';
            },
        ],
        [
            'attribute' => 'amount',
            'format' => ['decimal', 8],
        ],
        [
            'attribute' => 'create_time',
            'format' => 'datetime',
        ],
        [
            'class' => 'yii\grid\ActionColumn',
            'template' => '{delete}',
            'buttons' => [
                'delete' => function ($url, $model) {
                    return Html::a('Delete', ['delete-earning', 'id' => $model->id], [
                        'class' => 'btn btn-sm btn-danger',
                        'data-method' => 'post',
                        'data-confirm' => 'Are you sure you want to delete this earning record?',
                    ]);
                },
            ],
        ],
    ],
]); ?>
