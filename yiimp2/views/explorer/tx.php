<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */
/* @var $txid string */

$this->title = 'Transaction Details';
$this->params['breadcrumbs'][] = ['label' => 'Block Explorer', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $coin->name, 'url' => ['coin', 'symbol' => $coin->getOfficialSymbol()]];
$this->params['breadcrumbs'][] = $this->title;

// Get transaction details from RPC
$txDetails = Yii::$app->ExplorerUtils->getTransactionDetails($coin, $txid);

if (!$txDetails) {
    echo '<div class="alert alert-danger">Transaction not found or RPC error occurred.</div>';
    return;
}
?>

<div class="explorer-tx">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Transaction Details</h1>
            <p class="text-muted"><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->getOfficialSymbol()) ?>)</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transaction Information</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th class="w-200">Transaction ID</th>
                                <td><code><?= Html::encode($txDetails['txid'] ?? $txid) ?></code></td>
                            </tr>
                            <?php if (isset($txDetails['blockhash'])): ?>
                            <tr>
                                <th>Block Hash</th>
                                <td>
                                    <?= Html::a(
                                        '<code>' . Html::encode($txDetails['blockhash']) . '</code>',
                                        ['block', 'id' => $coin->id, 'hash' => $txDetails['blockhash']],
                                        ['class' => 'text-decoration-none']
                                    ) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['blockheight'])): ?>
                            <tr>
                                <th>Block Height</th>
                                <td><?= number_format($txDetails['blockheight']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['confirmations'])): ?>
                            <tr>
                                <th>Confirmations</th>
                                <td><?= number_format($txDetails['confirmations']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['time'])): ?>
                            <tr>
                                <th>Timestamp</th>
                                <td>
                                    <?= Yii::$app->formatter->asDatetime($txDetails['time']) ?>
                                    <small class="text-muted">(<?= Yii::$app->formatter->asRelativeTime($txDetails['time']) ?>)</small>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['size'])): ?>
                            <tr>
                                <th>Size</th>
                                <td><?= number_format($txDetails['size']) ?> bytes</td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['version'])): ?>
                            <tr>
                                <th>Version</th>
                                <td><?= Html::encode($txDetails['version']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($txDetails['locktime'])): ?>
                            <tr>
                                <th>Lock Time</th>
                                <td><?= Html::encode($txDetails['locktime']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Inputs -->
    <?php if (isset($txDetails['vin']) && !empty($txDetails['vin'])): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Inputs (<?= count($txDetails['vin']) ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Previous Output</th>
                                    <th>Script Signature</th>
                                    <th>Sequence</th>
                                    <?php if (isset($txDetails['vin'][0]['value'])): ?>
                                    <th>Amount</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($txDetails['vin'] as $index => $vin): ?>
                                <tr>
                                    <td><?= $index ?></td>
                                    <td>
                                        <?php if (isset($vin['coinbase'])): ?>
                                            <span class="badge bg-success">Coinbase</span>
                                            <br><small><code><?= Html::encode(substr($vin['coinbase'], 0, 64)) ?><?= strlen($vin['coinbase']) > 64 ? '...' : '' ?></code></small>
                                        <?php elseif (isset($vin['txid'])): ?>
                                            <?= Html::a(
                                                '<code>' . Html::encode(substr($vin['txid'], 0, 16)) . '...</code>',
                                                ['tx', 'id' => $coin->id, 'txid' => $vin['txid']],
                                                ['class' => 'text-decoration-none', 'title' => $vin['txid']]
                                            ) ?>
                                            : <?= isset($vin['vout']) ? $vin['vout'] : 'N/A' ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($vin['scriptSig']['hex'])): ?>
                                            <small><code><?= Html::encode(substr($vin['scriptSig']['hex'], 0, 32)) ?><?= strlen($vin['scriptSig']['hex']) > 32 ? '...' : '' ?></code></small>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($vin['sequence']) ? Html::encode($vin['sequence']) : 'N/A' ?></td>
                                    <?php if (isset($vin['value'])): ?>
                                    <td><?= number_format($vin['value'], 8) ?> <?= Html::encode($coin->getOfficialSymbol()) ?></td>
                                    <?php endif; ?>
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

    <!-- Outputs -->
    <?php if (isset($txDetails['vout']) && !empty($txDetails['vout'])): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Outputs (<?= count($txDetails['vout']) ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Amount</th>
                                    <th>Script PubKey</th>
                                    <th>Addresses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($txDetails['vout'] as $vout): ?>
                                <tr>
                                    <td><?= isset($vout['n']) ? $vout['n'] : 'N/A' ?></td>
                                    <td>
                                        <?php if (isset($vout['value'])): ?>
                                            <strong><?= number_format($vout['value'], 8) ?></strong> <?= Html::encode($coin->getOfficialSymbol()) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($vout['scriptPubKey']['type'])): ?>
                                            <span class="badge bg-info"><?= Html::encode($vout['scriptPubKey']['type']) ?></span>
                                            <?php if (isset($vout['scriptPubKey']['hex'])): ?>
                                                <br><small><code><?= Html::encode(substr($vout['scriptPubKey']['hex'], 0, 32)) ?><?= strlen($vout['scriptPubKey']['hex']) > 32 ? '...' : '' ?></code></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($vout['scriptPubKey']['addresses']) && !empty($vout['scriptPubKey']['addresses'])): ?>
                                            <?php foreach ($vout['scriptPubKey']['addresses'] as $address): ?>
                                                <code><?= Html::encode($address) ?></code><br>
                                            <?php endforeach; ?>
                                        <?php elseif (isset($vout['scriptPubKey']['address'])): ?>
                                            <code><?= Html::encode($vout['scriptPubKey']['address']) ?></code>
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
            <?php if (isset($txDetails['blockhash'])): ?>
                <?= Html::a(
                    '<i class="bi bi-box"></i> View Block',
                    ['block', 'id' => $coin->id, 'hash' => $txDetails['blockhash']],
                    ['class' => 'btn btn-outline-primary']
                ) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
