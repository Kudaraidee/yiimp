<?php

use yii\helpers\Html;

$this->title = 'Uninstall Coin: ' . $model->name;
?>
<div class="admin-confirm-uninstall">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Confirm Uninstall</h5>
        </div>
        <div class="card-body">
            <p class="lead">Are you sure you want to uninstall <strong><?= Html::encode($model->name) ?></strong> (<?= Html::encode($model->symbol) ?>)?</p>
            
            <div class="alert alert-warning">
                <strong>Warning:</strong> This action will permanently remove the coin and all related data. This cannot be undone.
            </div>

            <table class="table table-sm mb-4">
                <tr>
                    <th>Name:</th>
                    <td><?= Html::encode($model->name) ?></td>
                </tr>
                <tr>
                    <th>Symbol:</th>
                    <td><?= Html::encode($model->symbol) ?></td>
                </tr>
                <tr>
                    <th>Algorithm:</th>
                    <td><?= Html::encode($model->algo) ?></td>
                </tr>
                <tr>
                    <th>Balance:</th>
                    <td><?= number_format($model->balance, 8) ?></td>
                </tr>
            </table>

            <?= Html::beginForm(['uninstall-coin', 'id' => $model->id], 'post') ?>
                <?= Html::submitButton('Yes, Uninstall', ['class' => 'btn btn-danger']) ?>
                <?= Html::a('Cancel', ['coin', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
