<?php

use yii\helpers\Html;

?>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Exchange</th>
            <th>Symbol</th>
            <th>Balance</th>
            <th>Deposit Address</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($balances)): ?>
            <tr>
                <td colspan="4" class="text-center">No balance data available</td>
            </tr>
        <?php else: ?>
            <?php foreach ($balances as $balance): ?>
                <tr>
                    <td><?= Html::encode($balance->name) ?></td>
                    <td><?= Html::encode($balance->symbol) ?></td>
                    <td><?= number_format($balance->balance, 8) ?></td>
                    <td><small><?= Html::encode($balance->deposit_address ?? '-') ?></small></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
