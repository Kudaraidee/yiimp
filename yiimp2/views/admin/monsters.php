<?php

use yii\helpers\Html;

$this->title = 'Monster Detection';
?>
<div class="admin-monsters">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info mt-4">
        <strong>Monster Detection:</strong> This page shows workers with unusually high hashrate that may indicate
        ASIC miners on GPU-only algorithms or other anomalies.
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>High Hashrate Anomalies</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Monster detection logic to be implemented based on algorithm-specific thresholds.</p>
            <!-- Monster detection results will be displayed here -->
        </div>
    </div>
</div>
