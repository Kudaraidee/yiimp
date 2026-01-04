<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $orders app\models\Nicehash[] */
/* @var $balance float|null */
/* @var $balancePending float|null */
/* @var $error string|null */

?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= Html::encode($error) ?>
    </div>
<?php else: ?>

    <?php if ($balance !== null): ?>
        <div class="mb-3">
            <strong>Balance:</strong> <?= number_format($balance, 8) ?> BTC
            <?php if ($balancePending !== null && $balancePending > 0): ?>
                - <strong>Pending:</strong> <?= number_format($balancePending, 8) ?> BTC
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Algorithm</th>
                    <th>BTC</th>
                    <th>NiceHash Price</th>
                    <th>Yaamp Price</th>
                    <th>Order Price</th>
                    <th>Speed</th>
                    <th>Last Decrease</th>
                    <th>Workers</th>
                    <th>Accepted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="11" class="text-center">No NiceHash orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= Html::encode($order->orderid ?: '-') ?></td>
                            <td><strong><?= Html::encode($order->algo) ?></strong></td>
                            <td><?= $order->btc !== null ? number_format($order->btc, 8) : '-' ?></td>
                            
                            <!-- NiceHash Service Price -->
                            <td>
                                <?php if (isset($order->servicePrice) && $order->servicePrice !== null): ?>
                                    <?= number_format($order->servicePrice, 4) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <!-- Yaamp Price (highlight if significantly higher) -->
                            <td>
                                <?php if (isset($order->yaampPrice) && $order->yaampPrice !== null): ?>
                                    <?php
                                    $yaampPrice = $order->yaampPrice;
                                    $servicePrice = $order->servicePrice ?? 0;
                                    $isHigher = $servicePrice > 0 && $yaampPrice > ($servicePrice * 1.1);
                                    ?>
                                    <span class="<?= $isHigher ? 'text-success fw-bold' : '' ?>">
                                        <?= number_format($yaampPrice, 4) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <!-- Order Price (highlight if higher than Yaamp) -->
                            <td>
                                <?php if ($order->price !== null): ?>
                                    <?php
                                    $yaampPrice = $order->yaampPrice ?? 0;
                                    $isHigher = $yaampPrice > 0 && $order->price > $yaampPrice;
                                    ?>
                                    <span class="<?= $isHigher ? 'text-danger fw-bold' : '' ?>">
                                        <?= number_format($order->price, 4) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <td><?= $order->getFormattedSpeed() ?></td>
                            
                            <td>
                                <?php if ($order->last_decrease): ?>
                                    <?= date('Y-m-d H:i:s', $order->last_decrease) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <?php if (!$order->workers && !$order->accepted && !$order->rejected): ?>
                                <td colspan="2" class="text-center">-</td>
                            <?php else: ?>
                                <td><?= Html::encode($order->workers ?: 0) ?></td>
                                <td>
                                    <?= Html::encode($order->accepted ?: 0) ?>
                                    <?php if ($order->rejected > 0): ?>
                                        <small class="text-muted">(<?= $order->rejected ?> rejected)</small>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            
                            <td>
                                <?php if ($order->isActive()): ?>
                                    <?= Html::a('Stop', ['stop', 'id' => $order->id], [
                                        'class' => 'btn btn-sm btn-danger',
                                        'data' => [
                                            'confirm' => 'Are you sure you want to stop this NiceHash order?',
                                            'method' => 'post',
                                        ],
                                    ]) ?>
                                <?php else: ?>
                                    <?= Html::a('Start', ['start', 'id' => $order->id], [
                                        'class' => 'btn btn-sm btn-success',
                                        'data' => [
                                            'confirm' => 'Are you sure you want to start this NiceHash order?',
                                            'method' => 'post',
                                        ],
                                    ]) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($orders)): ?>
        <div class="mt-3">
            <small class="text-muted">
                <strong>Note:</strong> 
                <span class="text-success">Green prices</span> indicate Yaamp price is significantly higher than NiceHash service price.
                <span class="text-danger">Red prices</span> indicate order price is higher than Yaamp price.
            </small>
        </div>
    <?php endif; ?>

<?php endif; ?>
