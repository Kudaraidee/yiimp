<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'Coin Management';
?>
<div class="admin-coinwallets">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Add New Coin', ['coin-create'], ['class' => 'btn btn-success']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-bordered'],
        'columns' => [
            'id',
            'name',
            'symbol',
            'algo',
            [
                'attribute' => 'enable',
                'format' => 'boolean',
                'filter' => [0 => 'Disabled', 1 => 'Enabled'],
            ],
            [
                'attribute' => 'auto_ready',
                'label' => 'Auto Exchange',
                'format' => 'boolean',
            ],
            [
                'attribute' => 'balance',
                'format' => ['decimal', 8],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update} {delete}',
                'buttons' => [
                    'view' => function ($url, $model) {
                        return Html::a('View', ['coin', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-info',
                            'title' => 'View',
                        ]);
                    },
                    'update' => function ($url, $model) {
                        return Html::a('Edit', ['coin-update', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-primary',
                            'title' => 'Update',
                        ]);
                    },
                    'delete' => function ($url, $model) {
                        return Html::a('Delete', ['uninstall-coin', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-danger',
                            'title' => 'Delete',
                            'data-method' => 'post',
                            'data-confirm' => 'Are you sure you want to uninstall this coin?',
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>
</div>
