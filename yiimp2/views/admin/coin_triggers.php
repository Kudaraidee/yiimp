<?php

use yii\helpers\Html;

$this->title = 'Blockchain Triggers: ' . $model->name;
?>
<div class="admin-coin-triggers">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= Html::a('Back to Coin', ['coin', 'id' => $model->id], ['class' => 'btn btn-secondary mb-4']) ?>

    <div class="alert alert-info">
        <strong>Blockchain Triggers:</strong> Configure automatic actions based on blockchain events.
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Available Triggers</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Trigger management to be implemented based on pool-specific requirements.</p>
            <!-- Trigger configuration will be displayed here -->
        </div>
    </div>
</div>
