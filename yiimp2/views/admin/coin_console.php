<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'RPC Console: ' . $model->name;
?>
<div class="admin-coin-console">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Execute RPC Command</h5>
        </div>
        <div class="card-body">
            <?php $form = ActiveForm::begin(['method' => 'post']); ?>

            <div class="mb-3">
                <label for="command" class="form-label">Command</label>
                <input type="text" class="form-control" id="command" name="command" placeholder="e.g., getinfo" required>
            </div>

            <div class="mb-3">
                <label for="params" class="form-label">Parameters (JSON array, optional)</label>
                <input type="text" class="form-control" id="params" name="params" placeholder='e.g., ["param1", "param2"]'>
                <small class="form-text text-muted">Leave empty for commands without parameters</small>
            </div>

            <button type="submit" class="btn btn-primary">Execute</button>
            <?= Html::a('Back to Coin', ['coin', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <?php if ($result !== null || $error !== null): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Result</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?= Html::encode($error) ?>
                    </div>
                <?php else: ?>
                    <pre class="bg-light p-3"><?= Html::encode(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
