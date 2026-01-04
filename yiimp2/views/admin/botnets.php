<?php

use yii\helpers\Html;

$this->title = 'Botnet Detection';
?>
<div class="admin-botnets">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info mt-4">
        <strong>Botnet Detection:</strong> This page shows suspicious mining patterns that may indicate botnet activity.
        Patterns include: multiple workers from same IP, unusual worker names, or abnormal connection patterns.
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Suspicious Patterns</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Botnet detection logic to be implemented based on pool-specific criteria.</p>
            <!-- Botnet detection results will be displayed here -->
        </div>
    </div>
</div>
