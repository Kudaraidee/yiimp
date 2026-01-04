<?php

use yii\helpers\Html;
use app\components\CspHelper;

$this->title = 'Peer Management: ' . $model->name;
?>
<div class="admin-coin-peers">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= Html::a('Back to Coin', ['coin', 'id' => $model->id], ['class' => 'btn btn-secondary mb-4']) ?>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Add Peer</h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= \yii\helpers\Url::to(['add-peer', 'id' => $model->id]) ?>" class="row g-3">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="col-md-10">
                    <input type="text" class="form-control" name="peer_address" placeholder="IP:Port or hostname" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Add Peer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Connected Peers</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= Html::encode($error) ?></div>
            <?php elseif (empty($peers)): ?>
                <p class="text-muted">No peers connected</p>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Address</th>
                            <th>Version</th>
                            <th>Subversion</th>
                            <th>Connection Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($peers as $peer): ?>
                            <tr>
                                <td><?= Html::encode($peer['addr'] ?? '-') ?></td>
                                <td><?= Html::encode($peer['version'] ?? '-') ?></td>
                                <td><?= Html::encode($peer['subver'] ?? '-') ?></td>
                                <td><?= isset($peer['conntime']) ? date('Y-m-d H:i:s', $peer['conntime']) : '-' ?></td>
                                <td>
                                    <form method="post" action="<?= \yii\helpers\Url::to(['remove-peer', 'id' => $model->id]) ?>" class="d-inline" class="remove-peer-form">
                                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                                        <input type="hidden" name="peer_address" value="<?= Html::encode($peer['addr'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= CspHelper::beginScript() ?>
// Event listener for remove peer confirmation
document.addEventListener('DOMContentLoaded', function() {
	var removeForms = document.querySelectorAll('.remove-peer-form');
	removeForms.forEach(function(form) {
		form.addEventListener('submit', function(e) {
			if (!confirm('Remove this peer?')) {
				e.preventDefault();
				return false;
			}
		});
	});
});
<?= CspHelper::endScript() ?>
