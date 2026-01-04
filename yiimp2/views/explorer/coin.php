<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */

$this->title = $coin->name . ' Explorer';
$this->params['breadcrumbs'][] = ['label' => 'Block Explorer', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Get blockchain statistics
$stats = Yii::$app->ExplorerUtils->getBlockchainStats($coin, 7);
$recentBlocks = Yii::$app->ExplorerUtils->getRecentBlocks($coin, 10);
$recentTxs = Yii::$app->ExplorerUtils->getRecentTransactions($coin, 10);
?>

<div class="explorer-coin">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>
                <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" alt="<?= Html::encode($coin->name) ?>" class="img-48 mr-12">
                <?php endif; ?>
                <?= Html::encode($coin->name) ?> (<?= Html::encode($coin->getOfficialSymbol()) ?>) Explorer
            </h1>
        </div>
    </div>

    <!-- Blockchain Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Algorithm</h5>
                    <p class="card-text display-6"><?= Html::encode($coin->algo) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Difficulty</h5>
                    <p class="card-text display-6"><?= $coin->difficulty ? number_format($coin->difficulty, 2) : 'N/A' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Block Reward</h5>
                    <p class="card-text display-6"><?= $coin->reward ? number_format($coin->reward, 8) : 'N/A' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Block Time</h5>
                    <p class="card-text display-6"><?= $coin->block_time ? round($coin->block_time) . 's' : 'N/A' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Search Blockchain</h3>
                </div>
                <div class="card-body">
                    <form method="get" action="<?= Url::to(['search', 'id' => $coin->id]) ?>">
                        <div class="input-group">
                            <input type="text" name="query" class="form-control" placeholder="Enter block hash, transaction ID, or block height..." required>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                        <small class="form-text text-muted">Search by block hash, transaction ID, or block height</small>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Blocks -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Blocks</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentBlocks)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Height</th>
                                        <th>Hash</th>
                                        <th>Time</th>
                                        <th>Confirmations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBlocks as $block): ?>
                                    <tr>
                                        <td>
                                            <?= Html::a(
                                                number_format($block->height),
                                                ['block', 'id' => $coin->id, 'hash' => $block->hash],
                                                ['class' => 'text-decoration-none']
                                            ) ?>
                                        </td>
                                        <td>
                                            <small><?= Html::a(
                                                substr($block->hash, 0, 16) . '...',
                                                ['block', 'id' => $coin->id, 'hash' => $block->hash],
                                                ['class' => 'text-decoration-none', 'title' => $block->hash]
                                            ) ?></small>
                                        </td>
                                        <td><small><?= Yii::$app->formatter->asRelativeTime($block->time) ?></small></td>
                                        <td><?= $block->confirmations ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent blocks found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Transactions</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentTxs)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Height</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTxs as $tx): ?>
                                    <tr>
                                        <td>
                                            <small><?= Html::a(
                                                substr($tx['txid'], 0, 16) . '...',
                                                ['tx', 'id' => $coin->id, 'txid' => $tx['txid']],
                                                ['class' => 'text-decoration-none', 'title' => $tx['txid']]
                                            ) ?></small>
                                        </td>
                                        <td><?= number_format($tx['height']) ?></td>
                                        <td><small><?= Yii::$app->formatter->asRelativeTime($tx['time']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent transactions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Links -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Additional Information</h3>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <?= Html::a(
                            '<i class="bi bi-diagram-3"></i> Peer Connections',
                            ['peers', 'id' => $coin->id],
                            ['class' => 'btn btn-outline-primary']
                        ) ?>
                        <?= Html::a(
                            '<i class="bi bi-graph-up"></i> Blockchain Graphs',
                            ['graph', 'id' => $coin->id],
                            ['class' => 'btn btn-outline-primary']
                        ) ?>
                        <?php if (!empty($coin->block_explorer)): ?>
                            <?= Html::a(
                                '<i class="bi bi-box-arrow-up-right"></i> External Explorer',
                                $coin->block_explorer,
                                ['class' => 'btn btn-outline-secondary', 'target' => '_blank']
                            ) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
