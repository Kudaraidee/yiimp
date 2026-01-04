<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */
/* @var $hash string */

$this->title = 'Block Details';
$this->params['breadcrumbs'][] = ['label' => 'Block Explorer', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $coin->name, 'url' => ['coin', 'symbol' => $coin->getOfficialSymbol()]];
$this->params['breadcrumbs'][] = $this->title;

// Get block details from RPC
$blockDetails = Yii::$app->ExplorerUtils->getBlockDetails($coin, $hash);

if (!$blockDetails) {
    echo '<div class="alert alert-danger">Block not found or RPC error occurred.</div>';
    return;
}
?>

<div class="explorer-block">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Block Details</h1>
            <p class="text-muted"><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->getOfficialSymbol()) ?>)</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Block Information</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th class="w-200">Block Hash</th>
                                <td><code><?= Html::encode($blockDetails['hash'] ?? $hash) ?></code></td>
                            </tr>
                            <?php if (isset($blockDetails['height'])): ?>
                            <tr>
                                <th>Height</th>
                                <td><?= number_format($blockDetails['height']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['confirmations'])): ?>
                            <tr>
                                <th>Confirmations</th>
                                <td><?= number_format($blockDetails['confirmations']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['size'])): ?>
                            <tr>
                                <th>Size</th>
                                <td><?= number_format($blockDetails['size']) ?> bytes</td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['time'])): ?>
                            <tr>
                                <th>Timestamp</th>
                                <td>
                                    <?= Yii::$app->formatter->asDatetime($blockDetails['time']) ?>
                                    <small class="text-muted">(<?= Yii::$app->formatter->asRelativeTime($blockDetails['time']) ?>)</small>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['difficulty'])): ?>
                            <tr>
                                <th>Difficulty</th>
                                <td><?= number_format($blockDetails['difficulty'], 8) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['bits'])): ?>
                            <tr>
                                <th>Bits</th>
                                <td><code><?= Html::encode($blockDetails['bits']) ?></code></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['nonce'])): ?>
                            <tr>
                                <th>Nonce</th>
                                <td><?= Html::encode($blockDetails['nonce']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['version'])): ?>
                            <tr>
                                <th>Version</th>
                                <td><?= Html::encode($blockDetails['version']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['merkleroot'])): ?>
                            <tr>
                                <th>Merkle Root</th>
                                <td><code><?= Html::encode($blockDetails['merkleroot']) ?></code></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['previousblockhash'])): ?>
                            <tr>
                                <th>Previous Block</th>
                                <td>
                                    <?= Html::a(
                                        '<code>' . Html::encode($blockDetails['previousblockhash']) . '</code>',
                                        ['block', 'id' => $coin->id, 'hash' => $blockDetails['previousblockhash']],
                                        ['class' => 'text-decoration-none']
                                    ) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($blockDetails['nextblockhash'])): ?>
                            <tr>
                                <th>Next Block</th>
                                <td>
                                    <?= Html::a(
                                        '<code>' . Html::encode($blockDetails['nextblockhash']) . '</code>',
                                        ['block', 'id' => $coin->id, 'hash' => $blockDetails['nextblockhash']],
                                        ['class' => 'text-decoration-none']
                                    ) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <?php if (isset($blockDetails['tx']) && !empty($blockDetails['tx'])): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transactions (<?= count($blockDetails['tx']) ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($blockDetails['tx'] as $txid): ?>
                        <div class="list-group-item">
                            <?= Html::a(
                                '<code>' . Html::encode($txid) . '</code>',
                                ['tx', 'id' => $coin->id, 'txid' => $txid],
                                ['class' => 'text-decoration-none']
                            ) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="btn-group" role="group">
                <?= Html::a(
                    '<i class="bi bi-arrow-left"></i> Back to Explorer',
                    ['coin', 'symbol' => $coin->getOfficialSymbol()],
                    ['class' => 'btn btn-secondary']
                ) ?>
                <?php if (isset($blockDetails['previousblockhash'])): ?>
                    <?= Html::a(
                        '<i class="bi bi-chevron-left"></i> Previous Block',
                        ['block', 'id' => $coin->id, 'hash' => $blockDetails['previousblockhash']],
                        ['class' => 'btn btn-outline-primary']
                    ) ?>
                <?php endif; ?>
                <?php if (isset($blockDetails['nextblockhash'])): ?>
                    <?= Html::a(
                        'Next Block <i class="bi bi-chevron-right"></i>',
                        ['block', 'id' => $coin->id, 'hash' => $blockDetails['nextblockhash']],
                        ['class' => 'btn btn-outline-primary']
                    ) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
