<?php

use yii\helpers\Html;

$this->title = 'Coin Details: ' . $model->name;
?>
<div class="admin-coin">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Update', ['coin-update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Console', ['coin-console', 'id' => $model->id], ['class' => 'btn btn-info']) ?>
            <?= Html::a('Peers', ['coin-peers', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Basic Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
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
                            <th>Status:</th>
                            <td><?= $model->enable ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></td>
                        </tr>
                        <tr>
                            <th>Balance:</th>
                            <td><?= number_format($model->balance, 8) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>RPC Configuration</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>RPC Host:</th>
                            <td><?= Html::encode($model->rpchost ?? 'localhost') ?></td>
                        </tr>
                        <tr>
                            <th>RPC Port:</th>
                            <td><?= Html::encode($model->rpcport) ?></td>
                        </tr>
                        <tr>
                            <th>RPC User:</th>
                            <td><?= Html::encode($model->rpcuser) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Control Actions</h5>
                </div>
                <div class="card-body">
                    <?= Html::a('Start Daemon', ['start-coin', 'id' => $model->id], [
                        'class' => 'btn btn-success',
                        'data-method' => 'post',
                    ]) ?>
                    <?= Html::a('Stop Daemon', ['stop-coin', 'id' => $model->id], [
                        'class' => 'btn btn-warning',
                        'data-method' => 'post',
                    ]) ?>
                    <?= Html::a('Restart Daemon', ['restart-coin', 'id' => $model->id], [
                        'class' => 'btn btn-info',
                        'data-method' => 'post',
                    ]) ?>
                    <?= Html::a('Reset Blockchain', ['reset-blockchain', 'id' => $model->id], [
                        'class' => 'btn btn-danger',
                    ]) ?>
                    <?= Html::a('Uninstall', ['uninstall-coin', 'id' => $model->id], [
                        'class' => 'btn btn-danger',
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
