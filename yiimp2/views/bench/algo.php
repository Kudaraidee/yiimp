<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $algo string */
/* @var $algoList array */
/* @var $chipBenchmarks array */
/* @var $algoStats array */

$this->title = 'Algorithm Benchmarks: ' . strtoupper($algo);
$this->params['breadcrumbs'][] = ['label' => 'Benchmarks', 'url' => ['index']];
$this->params['breadcrumbs'][] = strtoupper($algo);

/**
 * Format hashrate with appropriate unit
 */
function formatHashrate($khps) {
    if ($khps === null) return 'N/A';
    
    if ($khps >= 1000000) {
        return number_format($khps / 1000000, 2) . ' GH/s';
    } elseif ($khps >= 1000) {
        return number_format($khps / 1000, 2) . ' MH/s';
    } else {
        return number_format($khps, 2) . ' kH/s';
    }
}

/**
 * Format power consumption
 */
function formatPower($power) {
    if ($power === null || $power <= 0) return 'N/A';
    return number_format($power, 0) . ' W';
}

/**
 * Calculate and format efficiency
 */
function formatEfficiency($khps, $power) {
    if ($power === null || $power <= 0 || $khps === null) return 'N/A';
    $efficiency = $khps / $power;
    return number_format($efficiency, 2) . ' kH/W';
}

?>

<div class="bench-algo">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5>Algorithms</h5>
                </div>
                <div class="card-body max-h-500 overflow-y-auto">
                    <div class="list-group">
                        <?php foreach ($algoList as $algoName => $count): ?>
                            <a href="<?= Url::to(['bench/algo', 'algo' => $algoName]) ?>" 
                               class="list-group-item list-group-item-action <?= $algo === $algoName ? 'active' : '' ?>">
                                <?= Html::encode($algoName) ?> 
                                <span class="badge bg-secondary float-end"><?= $count ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Algorithm Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Average Hashrate:</strong><br/>
                            <?= formatHashrate($algoStats['avg_khps']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Min Hashrate:</strong><br/>
                            <?= formatHashrate($algoStats['min_khps']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Max Hashrate:</strong><br/>
                            <?= formatHashrate($algoStats['max_khps']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Total Records:</strong><br/>
                            <?= number_format($algoStats['total_records']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Device Performance for <?= strtoupper($algo) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($chipBenchmarks)): ?>
                        <p class="text-muted">No chip-specific benchmark data available for this algorithm.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Device</th>
                                        <th>Avg Hashrate</th>
                                        <th>Avg Power</th>
                                        <th>Efficiency</th>
                                        <th>Avg Intensity</th>
                                        <th>Avg Frequency</th>
                                        <th>Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chipBenchmarks as $chip): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= strtoupper($chip['devicetype']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= Url::to(['bench/index', 'algo' => $algo, 'chip' => $chip['id']]) ?>">
                                                    <?= Html::encode($chip['chip']) ?>
                                                </a>
                                            </td>
                                            <td><?= formatHashrate($chip['avg_khps']) ?></td>
                                            <td><?= formatPower($chip['avg_power']) ?></td>
                                            <td><?= formatEfficiency($chip['avg_khps'], $chip['avg_power']) ?></td>
                                            <td>
                                                <?= $chip['avg_intensity'] ? number_format($chip['avg_intensity'], 2) : 'N/A' ?>
                                            </td>
                                            <td>
                                                <?= $chip['avg_freq'] ? number_format($chip['avg_freq'], 0) . ' MHz' : 'N/A' ?>
                                            </td>
                                            <td><?= $chip['record_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <p>
            <?= Html::a('Submit Your Benchmark', ['bench/submit'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('View Devices', ['bench/devices'], ['class' => 'btn btn-secondary']) ?>
        </p>
    </div>
</div>
