<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $algoList array */
/* @var $chipList array */
/* @var $selectedAlgo string */
/* @var $selectedChip int */

$this->title = 'Benchmarks';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="bench-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Filter by Algorithm</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="<?= Url::to(['bench/index', 'algo' => 'all']) ?>" 
                           class="list-group-item list-group-item-action <?= $selectedAlgo === 'all' ? 'active' : '' ?>">
                            All Algorithms
                        </a>
                        <?php foreach ($algoList as $algo => $count): ?>
                            <a href="<?= Url::to(['bench/index', 'algo' => $algo]) ?>" 
                               class="list-group-item list-group-item-action <?= $selectedAlgo === $algo ? 'active' : '' ?>">
                                <?= Html::encode($algo) ?> 
                                <span class="badge bg-secondary float-end"><?= $count ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Filter by Chip</h5>
                </div>
                <div class="card-body">
                    <div class="list-group max-h-400 overflow-y-auto">
                        <a href="<?= Url::to(['bench/index', 'algo' => $selectedAlgo]) ?>" 
                           class="list-group-item list-group-item-action <?= !$selectedChip ? 'active' : '' ?>">
                            All Chips
                        </a>
                        <?php foreach ($chipList as $chip): ?>
                            <a href="<?= Url::to(['bench/index', 'algo' => $selectedAlgo, 'chip' => $chip['id']]) ?>" 
                               class="list-group-item list-group-item-action <?= $selectedChip == $chip['id'] ? 'active' : '' ?>">
                                <span class="badge bg-info"><?= strtoupper($chip['devicetype']) ?></span>
                                <?= Html::encode($chip['chip']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Benchmark Results</h5>
        </div>
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'tableOptions' => ['class' => 'table table-striped table-hover'],
                'columns' => [
                    [
                        'attribute' => 'algo',
                        'label' => 'Algorithm',
                        'format' => 'text',
                    ],
                    [
                        'attribute' => 'type',
                        'label' => 'Type',
                        'format' => 'text',
                        'value' => function($model) {
                            return strtoupper($model->type);
                        },
                    ],
                    [
                        'attribute' => 'device',
                        'label' => 'Device',
                        'format' => 'text',
                    ],
                    [
                        'label' => 'Chip',
                        'format' => 'text',
                        'value' => function($model) {
                            return $model->benchChip ? $model->benchChip->chip : $model->chip;
                        },
                    ],
                    [
                        'attribute' => 'khps',
                        'label' => 'Hashrate',
                        'format' => 'raw',
                        'value' => function($model) {
                            return $model->getFormattedHashrate();
                        },
                    ],
                    [
                        'attribute' => 'power',
                        'label' => 'Power',
                        'format' => 'raw',
                        'value' => function($model) {
                            return $model->getFormattedPower();
                        },
                    ],
                    [
                        'label' => 'Efficiency',
                        'format' => 'raw',
                        'value' => function($model) {
                            return $model->getFormattedEfficiency();
                        },
                    ],
                    [
                        'attribute' => 'time',
                        'label' => 'Date',
                        'format' => 'datetime',
                    ],
                ],
            ]); ?>
        </div>
    </div>

    <div class="mt-4">
        <p>
            <?= Html::a('Submit Your Benchmark', ['bench/submit'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('View Devices', ['bench/devices'], ['class' => 'btn btn-secondary']) ?>
        </p>
    </div>
</div>
