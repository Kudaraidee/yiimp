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
            'attribute' => 'name',
            'label' => 'Worker Name',
        ],
        [
            'attribute' => 'userid',
            'label' => 'User',
            'value' => function($model) {
                return $model->account ? substr($model->account->username, 0, 20) . '...' : '-';
            },
        ],
        'algo',
        'ip',
        [
            'attribute' => 'difficulty',
            'format' => ['decimal', 2],
        ],
        [
            'attribute' => 'shares',
            'format' => 'integer',
        ],
        [
            'attribute' => 'time',
            'label' => 'Last Activity',
            'format' => 'relativeTime',
        ],
        [
            'attribute' => 'solo',
            'format' => 'boolean',
        ],
        [
            'label' => 'Status',
            'format' => 'raw',
            'value' => function($model) {
                if ($model->isActive()) {
                    return '<span class="badge bg-success">Active</span>';
                }
                return '<span class="badge bg-secondary">Inactive</span>';
            },
        ],
    ],
]); ?>
