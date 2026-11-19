<?php

/** @var yii\web\View         $this     */
/** @var app\models\Bookmarks $bookmark */
/** @var app\models\Coins     $coin     */

use yii\helpers\Html;

$isNew      = $bookmark->isNewRecord;
$this->title = ($isNew ? 'Add' : 'Edit') . ' Bookmark — ' . $coin->symbol;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$backUrl    = '/admin/coinwallet?id=' . $coin->id;
$postUrl    = $isNew
    ? '/admin/bookmark-add?id=' . $coin->id
    : '/admin/bookmark-edit?id=' . $bookmark->id;

$errors = $bookmark->hasErrors();

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
&nbsp;-&nbsp;<a href="<?= Html::encode($backUrl) ?>"><?= Html::encode($coin->name) ?></a>
&mdash; <?= $isNew ? 'Add Bookmark' : 'Edit Bookmark: ' . Html::encode($bookmark->label) ?>
<br><br>

<?php if ($errors): ?>
<p style="color:red;font-weight:bold;">Please fix the errors below.</p>
<?php endif ?>

<?= Html::beginForm($postUrl, 'post', ['class' => 'uniForm']) ?>
<fieldset class="inlineLabels">

  <div class="ctrlHolder">
    <?= Html::label('Label', 'bookmarks-label') ?>
    <?= Html::activeTextInput($bookmark, 'label', ['maxlength' => 32, 'class' => 'textInput tweetnews-input', 'id' => 'bookmarks-label']) ?>
    <?php if ($bookmark->hasErrors('label')): ?>
    <p class="errorField"><strong><?= Html::encode($bookmark->getFirstError('label')) ?></strong></p>
    <?php endif ?>
  </div>

  <div class="ctrlHolder">
    <?= Html::label('Address', 'bookmarks-address') ?>
    <?= Html::activeTextInput($bookmark, 'address', ['maxlength' => 128, 'class' => 'textInput tweetnews-input', 'id' => 'bookmarks-address']) ?>
    <?php if ($bookmark->hasErrors('address')): ?>
    <p class="errorField"><strong><?= Html::encode($bookmark->getFirstError('address')) ?></strong></p>
    <?php endif ?>
  </div>

</fieldset>
<?= Html::submitButton('Save', ['class' => 'submitButton']) ?>
&nbsp;&nbsp;<a href="<?= Html::encode($backUrl) ?>">Cancel</a>
<?= Html::endForm() ?>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">

      <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
          <?= $isNew ? 'Add Bookmark' : 'Edit Bookmark' ?>
          &mdash; <a href="<?= Html::encode($backUrl) ?>"><?= Html::encode($coin->symbol) ?></a>
        </span>
        <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back
        </a>
      </div>

      <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small">Please fix the errors below.</div>
        <?php endif ?>

        <?= Html::beginForm($postUrl, 'post') ?>

        <div class="mb-3">
          <?= Html::label('Label', 'bookmarks-label', ['class' => 'form-label small fw-semibold']) ?>
          <?= Html::activeTextInput($bookmark, 'label', [
              'maxlength' => 32,
              'class'     => 'form-control form-control-sm' . ($bookmark->hasErrors('label') ? ' is-invalid' : ''),
              'id'        => 'bookmarks-label',
          ]) ?>
          <?php if ($bookmark->hasErrors('label')): ?>
          <div class="invalid-feedback"><?= Html::encode($bookmark->getFirstError('label')) ?></div>
          <?php endif ?>
        </div>

        <div class="mb-4">
          <?= Html::label('Address', 'bookmarks-address', ['class' => 'form-label small fw-semibold']) ?>
          <?= Html::activeTextInput($bookmark, 'address', [
              'maxlength' => 128,
              'class'     => 'form-control form-control-sm font-monospace' . ($bookmark->hasErrors('address') ? ' is-invalid' : ''),
              'id'        => 'bookmarks-address',
          ]) ?>
          <?php if ($bookmark->hasErrors('address')): ?>
          <div class="invalid-feedback"><?= Html::encode($bookmark->getFirstError('address')) ?></div>
          <?php endif ?>
        </div>

        <div class="d-flex gap-2">
          <?= Html::submitButton('<i class="bi bi-floppy me-1"></i>Save', ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
          <a href="<?= Html::encode($backUrl) ?>" class="btn btn-secondary btn-sm">Cancel</a>
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
  <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
      <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
        <?= $isNew ? 'Add Bookmark' : 'Edit Bookmark' ?>
        &mdash;
        <a href="<?= Html::encode($backUrl) ?>"
           class="text-indigo-600 dark:text-indigo-400 hover:underline"><?= Html::encode($coin->symbol) ?></a>
      </span>
      <a href="<?= Html::encode($backUrl) ?>"
         class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg
                border border-gray-300 dark:border-gray-600
                bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
        <i data-lucide="arrow-left" class="w-3 h-3"></i>Back
      </a>
    </div>

    <div class="px-5 py-5">
      <?php if ($errors): ?>
      <div class="mb-4 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20
                  border border-red-200 dark:border-red-700 rounded-lg px-3 py-2">
        Please fix the errors below.
      </div>
      <?php endif ?>

      <?= Html::beginForm($postUrl, 'post') ?>

      <div class="mb-4">
        <label for="bookmarks-label"
               class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label</label>
        <?= Html::activeTextInput($bookmark, 'label', [
            'maxlength' => 32,
            'id'        => 'bookmarks-label',
            'class'     => 'w-full text-sm rounded-lg border px-3 py-1.5
                           bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 '
                           . ($bookmark->hasErrors('label')
                               ? 'border-red-400 dark:border-red-600'
                               : 'border-gray-300 dark:border-gray-600'),
        ]) ?>
        <?php if ($bookmark->hasErrors('label')): ?>
        <p class="mt-1 text-xs text-red-500"><?= Html::encode($bookmark->getFirstError('label')) ?></p>
        <?php endif ?>
      </div>

      <div class="mb-6">
        <label for="bookmarks-address"
               class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Address</label>
        <?= Html::activeTextInput($bookmark, 'address', [
            'maxlength' => 128,
            'id'        => 'bookmarks-address',
            'class'     => 'w-full text-sm font-mono rounded-lg border px-3 py-1.5
                           bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 '
                           . ($bookmark->hasErrors('address')
                               ? 'border-red-400 dark:border-red-600'
                               : 'border-gray-300 dark:border-gray-600'),
        ]) ?>
        <?php if ($bookmark->hasErrors('address')): ?>
        <p class="mt-1 text-xs text-red-500"><?= Html::encode($bookmark->getFirstError('address')) ?></p>
        <?php endif ?>
      </div>

      <div class="flex gap-2">
        <?= Html::submitButton('Save', [
            'class' => 'inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white
                        bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors',
        ]) ?>
        <a href="<?= Html::encode($backUrl) ?>"
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
