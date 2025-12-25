<?php

use yii\helpers\Html;

$this->title = 'Cache Management';
?>
<div class="admin-memcached">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Memcached Status</h5>
            <?= Html::a('Clear Cache', ['clear-cache'], [
                'class' => 'btn btn-danger btn-sm',
                'data-method' => 'post',
                'data-confirm' => 'Are you sure you want to clear all cache?',
            ]) ?>
        </div>
        <div class="card-body">
            <?php if ($stats): ?>
                <h6>Cache Servers:</h6>
                <ul>
                    <?php foreach ($stats['servers'] as $server): ?>
                        <li><?= Html::encode($server['host']) ?>:<?= Html::encode($server['port']) ?> (Weight: <?= Html::encode($server['weight']) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">Cache statistics not available or file cache is being used.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Cached Keys</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Note: Memcached does not provide a built-in way to list all keys. Consider using Redis for better cache inspection capabilities.</p>
        </div>
    </div>
</div>
