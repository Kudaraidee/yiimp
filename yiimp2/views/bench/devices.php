<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $devices array */
/* @var $recentAlgos array */
/* @var $deviceAlgos array */

$this->title = 'Benchmark Devices';
$this->params['breadcrumbs'][] = ['label' => 'Benchmarks', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Devices';
?>

<div class="bench-devices">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Recent Algorithms (Last 30 Days)</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($recentAlgos as $algo): ?>
                    <a href="<?= Url::to(['bench/algo', 'algo' => $algo]) ?>" class="btn btn-sm btn-outline-primary">
                        <?= Html::encode($algo) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Devices in Database</h5>
        </div>
        <div class="card-body">
            <?php if (empty($devices)): ?>
                <p class="text-muted">No devices found in the benchmark database.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Device</th>
                                <th>Chip</th>
                                <th>Vendor ID</th>
                                <th>Supported Algorithms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): 
                                $key = $device['device'] . '_' . $device['vendorid'];
                                $algos = isset($deviceAlgos[$key]) ? $deviceAlgos[$key] : [];
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $device['type'] === 'gpu' ? 'info' : 'secondary' ?>">
                                            <?= strtoupper($device['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= Html::encode($device['device']) ?></td>
                                    <td>
                                        <?php if ($device['chip']): ?>
                                            <a href="<?= Url::to(['bench/index', 'chip' => $device['idchip']]) ?>">
                                                <?= Html::encode($device['chip']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($device['vendorid']): ?>
                                            <code><?= Html::encode($device['vendorid']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php 
                                            $displayCount = 0;
                                            foreach ($recentAlgos as $algo): 
                                                if (in_array($algo, $algos)):
                                                    $displayCount++;
                                            ?>
                                                <a href="<?= Url::to(['bench/algo', 'algo' => $algo]) ?>" 
                                                   class="badge bg-success text-decoration-none">
                                                    <?= Html::encode($algo) ?>
                                                </a>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            
                                            // Show count of additional algorithms not in recent list
                                            $additionalCount = count($algos) - $displayCount;
                                            if ($additionalCount > 0):
                                            ?>
                                                <span class="badge bg-secondary">
                                                    +<?= $additionalCount ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <p>
            <?= Html::a('Submit Your Benchmark', ['bench/submit'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('View All Benchmarks', ['bench/index'], ['class' => 'btn btn-secondary']) ?>
        </p>
    </div>
</div>

<style>
.gap-1 {
    gap: 0.25rem;
}
.gap-2 {
    gap: 0.5rem;
}
</style>
