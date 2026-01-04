<?php

use yii\helpers\Html;
use yii\helpers\Json;
use app\assets\ChartJsAsset;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */

// Get blockchain statistics
$stats = Yii::$app->ExplorerUtils->getBlockchainStats($coin, 30);

// Prepare data for charts
$dates = [];
$blocks = [];
$difficulties = [];

if ($stats && isset($stats['history'])) {
    foreach ($stats['history'] as $day => $data) {
        $dates[] = $day;
        $blocks[] = $data['blocks'];
        $difficulties[] = isset($data['avg_difficulty']) ? $data['avg_difficulty'] : 0;
    }
}

// Convert to JSON for JavaScript
$chartData = [
    'dates' => $dates,
    'blocks' => $blocks,
    'difficulties' => $difficulties,
];

// Register Chart.js assets for CSP-compliant charting
ChartJsAsset::register($this);
?>

<div class="explorer-graph">
    <h2><?= Html::encode($coin->name) ?> Blockchain Statistics</h2>
    
    <?php if (empty($dates)): ?>
        <div class="alert alert-info">
            No historical data available for graphing.
        </div>
    <?php else: ?>
        <!-- Blocks per Day Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Blocks Found per Day (Last 30 Days)</h3>
            </div>
            <div class="card-body">
                <canvas id="blocksChart" class="max-h-300"></canvas>
            </div>
        </div>

        <!-- Difficulty Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Average Difficulty per Day (Last 30 Days)</h3>
            </div>
            <div class="card-body">
                <canvas id="difficultyChart" class="max-h-300"></canvas>
            </div>
        </div>

        <!-- Current Statistics -->
        <?php if (isset($stats['current'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Current Blockchain Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (isset($stats['current']['blocks'])): ?>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>Current Height</h5>
                            <p class="display-6"><?= number_format($stats['current']['blocks']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['current']['difficulty'])): ?>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>Current Difficulty</h5>
                            <p class="display-6"><?= number_format($stats['current']['difficulty'], 2) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['current']['networkhashps'])): ?>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>Network Hashrate</h5>
                            <p class="display-6"><?= Yii::$app->ViewUtils->formatHashrate($stats['current']['networkhashps']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['current']['connections'])): ?>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>Connections</h5>
                            <p class="display-6"><?= number_format($stats['current']['connections']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($dates)): ?>
<?php
$this->registerJs("
    // Blocks Chart
    const blocksCtx = document.getElementById('blocksChart').getContext('2d');
    const blocksChart = new Chart(blocksCtx, {
        type: 'line',
        data: {
            labels: " . Json::encode($dates) . ",
            datasets: [{
                label: 'Blocks Found',
                data: " . Json::encode($blocks) . ",
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Difficulty Chart
    const difficultyCtx = document.getElementById('difficultyChart').getContext('2d');
    const difficultyChart = new Chart(difficultyCtx, {
        type: 'line',
        data: {
            labels: " . Json::encode($dates) . ",
            datasets: [{
                label: 'Average Difficulty',
                data: " . Json::encode($difficulties) . ",
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });
", \yii\web\View::POS_READY);
?>
<?php endif; ?>
