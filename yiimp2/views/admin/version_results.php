<?php

use yii\helpers\Html;

?>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Coin</th>
            <th>Symbol</th>
            <th>Version</th>
            <th>Protocol</th>
            <th>Blocks</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($versions)): ?>
            <tr>
                <td colspan="6" class="text-center">No coin version data available</td>
            </tr>
        <?php else: ?>
            <?php foreach ($versions as $v): ?>
                <tr>
                    <td><?= Html::encode($v['coin']->name) ?></td>
                    <td><?= Html::encode($v['coin']->symbol) ?></td>
                    <td><?= Html::encode($v['version']) ?></td>
                    <td><?= Html::encode($v['protocol']) ?></td>
                    <td><?= Html::encode($v['blocks']) ?></td>
                    <td>
                        <?php if (isset($v['error'])): ?>
                            <span class="badge bg-danger" title="<?= Html::encode($v['error']) ?>">Error</span>
                        <?php else: ?>
                            <span class="badge bg-success">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
