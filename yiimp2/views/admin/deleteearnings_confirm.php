<?php

/** @var yii\web\View $this */
/** @var app\models\Coins $coin */

use yii\helpers\Html;

$this->title = 'Delete Earnings — ' . $coin->symbol;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$cancelUrl  = '/admin/coinwallet?id=' . $coin->id;
$postUrl    = '/admin/deleteearnings?id=' . $coin->id;

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
<br>
<div style="max-width:540px;margin:24px auto;padding:20px;border:2px solid #c00;background:#fff8f8;">
    <h3 style="color:#c00;margin-top:0;">&#9888; Delete All Earnings — <?= Html::encode($coin->symbol) ?></h3>
    <p>This will permanently delete <strong>all earnings records</strong> for
        <strong><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->symbol) ?>)</strong>.</p>
    <p><strong>This action cannot be undone.</strong></p>
    <?= Html::beginForm($postUrl, 'post') ?>
        <?= Html::submitButton('YES, DELETE ALL EARNINGS', ['style' => 'background:#c00;color:#fff;padding:6px 16px;border:none;cursor:pointer;font-weight:bold;']) ?>
        &nbsp;&nbsp;
        <a href="<?= Html::encode($cancelUrl) ?>">Cancel</a>
    <?= Html::endForm() ?>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card border-danger shadow-sm">
      <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Delete All Earnings</strong>
      </div>
      <div class="card-body">
        <p class="mb-2">
          This will permanently delete <strong>all earnings records</strong> for
          <strong><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->symbol) ?>)</strong>.
        </p>
        <p class="text-danger fw-semibold mb-4">This action cannot be undone.</p>
        <?= Html::beginForm($postUrl, 'post') ?>
          <div class="d-flex gap-2">
            <?= Html::submitButton('<i class="bi bi-trash me-1"></i>Yes, delete all earnings', [
                'class' => 'btn btn-danger',
                'encode' => false,
            ]) ?>
            <a href="<?= Html::encode($cancelUrl) ?>" class="btn btn-secondary">Cancel</a>
          </div>
        <?= Html::endForm() ?>
      </div>
    </div>
  </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex justify-center">
  <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-red-300 dark:border-red-700 overflow-hidden">

    <div class="flex items-center gap-2 px-5 py-3 bg-red-600 dark:bg-red-700">
      <i data-lucide="alert-triangle" class="w-4 h-4 text-white"></i>
      <span class="text-sm font-semibold text-white">Delete All Earnings</span>
    </div>

    <div class="px-5 py-5">
      <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
        This will permanently delete <strong>all earnings records</strong> for
        <strong class="text-gray-900 dark:text-gray-100"><?= Html::encode($coin->name) ?>
        (<?= Html::encode($coin->symbol) ?>)</strong>.
      </p>
      <p class="text-sm font-semibold text-red-600 dark:text-red-400 mb-6">
        This action cannot be undone.
      </p>

      <?= Html::beginForm($postUrl, 'post') ?>
        <div class="flex gap-2">
          <?= Html::submitButton('Yes, delete all earnings', [
              'class' => 'inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors',
          ]) ?>
          <a href="<?= Html::encode($cancelUrl) ?>"
             class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg
                    border border-gray-300 dark:border-gray-600
                    bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                    hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            Cancel
          </a>
        </div>
      <?= Html::endForm() ?>
    </div>

  </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady(<<<JS
if (typeof lucide !== 'undefined') lucide.createIcons();
JS);
?>

<?php endif ?>
