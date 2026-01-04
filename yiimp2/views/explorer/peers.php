<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */

$this->title = 'Peer Connections - ' . $coin->name;
$this->params['breadcrumbs'][] = ['label' => 'Block Explorer', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $coin->name, 'url' => ['coin', 'symbol' => $coin->getOfficialSymbol()]];
$this->params['breadcrumbs'][] = 'Peer Connections';

// Get peer information
$peers = Yii::$app->ExplorerUtils->getPeerInfo($coin);
?>

<div class="explorer-peers">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Peer Connections</h1>
            <p class="text-muted"><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->getOfficialSymbol()) ?>)</p>
        </div>
    </div>

    <?php if ($peers === null): ?>
        <div class="alert alert-danger">
            Unable to retrieve peer information. The coin daemon may be offline or RPC is not responding.
        </div>
    <?php elseif (empty($peers)): ?>
        <div class="alert alert-info">
            No peer connections found. The node may be starting up or not connected to the network.
        </div>
    <?php else: ?>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-success">
                    <strong><?= count($peers) ?></strong> peer connection<?= count($peers) !== 1 ? 's' : '' ?> active
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Connected Peers</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Address</th>
                                        <th>Version</th>
                                        <th>Subversion</th>
                                        <th>Connected Since</th>
                                        <th>Inbound</th>
                                        <th>Height</th>
                                        <th>Ping</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($peers as $index => $peer): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <code><?= Html::encode($peer['addr'] ?? 'N/A') ?></code>
                                        </td>
                                        <td><?= isset($peer['version']) ? Html::encode($peer['version']) : 'N/A' ?></td>
                                        <td>
                                            <?php if (isset($peer['subver'])): ?>
                                                <small><?= Html::encode($peer['subver']) ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($peer['conntime'])): ?>
                                                <?= Yii::$app->formatter->asRelativeTime($peer['conntime']) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($peer['inbound'])): ?>
                                                <?php if ($peer['inbound']): ?>
                                                    <span class="badge bg-info">Inbound</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Outbound</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($peer['startingheight'])): ?>
                                                <?= number_format($peer['startingheight']) ?>
                                            <?php elseif (isset($peer['synced_blocks'])): ?>
                                                <?= number_format($peer['synced_blocks']) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($peer['pingtime'])): ?>
                                                <?= number_format($peer['pingtime'] * 1000, 2) ?> ms
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="row mt-4">
        <div class="col-md-12">
            <?= Html::a(
                '<i class="bi bi-arrow-left"></i> Back to Explorer',
                ['coin', 'symbol' => $coin->getOfficialSymbol()],
                ['class' => 'btn btn-secondary']
            ) ?>
        </div>
    </div>
</div>
