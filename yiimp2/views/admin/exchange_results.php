<?php

use yii\helpers\Html;

?>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Coin</th>
            <th>Exchange</th>
            <th>Last Trade</th>
            <th>Price</th>
            <th>Volume</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($markets)): ?>
            <tr>
                <td colspan="5" class="text-center">No market data available</td>
            </tr>
        <?php else: ?>
            <?php foreach ($markets as $market): ?>
                <tr>
                    <td><?= Html::encode($market->coin ? $market->coin->symbol : '-') ?></td>
                    <td><?= Html::encode($market->name) ?></td>
                    <td><?= $market->last_trade ? date('Y-m-d H:i:s', $market->last_trade) : '-' ?></td>
                    <td><?= number_format($market->price, 8) ?></td>
                    <td><?= number_format($market->volume, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
