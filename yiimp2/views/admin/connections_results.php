<?php

use yii\helpers\Html;

?>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>IP Address</th>
            <th>Algorithm</th>
            <th>Worker Count</th>
            <th>Last Seen</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($connections)): ?>
            <tr>
                <td colspan="4" class="text-center">No active connections</td>
            </tr>
        <?php else: ?>
            <?php foreach ($connections as $conn): ?>
                <tr>
                    <td><?= Html::encode($conn['ip']) ?></td>
                    <td><?= Html::encode($conn['algo']) ?></td>
                    <td><?= Html::encode($conn['count']) ?></td>
                    <td><?= date('Y-m-d H:i:s', $conn['last_seen']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
