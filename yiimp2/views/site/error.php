<?php

/** @var yii\web\View $this      */
/** @var string       $name      */
/** @var string       $message   */
/** @var Exception    $exception */

use yii\helpers\Html;

$this->title = $name;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$code    = $exception instanceof \yii\web\HttpException ? $exception->statusCode : 500;
$isNotFound = $code === 404;

?>

<?php if ($isLegacy): ?>
<div style="max-width:600px;margin:32px auto;padding:20px;border:1px solid #d9534f;border-radius:4px;background:#fff8f8;">
    <h2 style="color:#c00;margin-top:0;"><?= Html::encode($name) ?></h2>
    <p style="font-size:.95em;color:#333;margin-bottom:12px;">
        <?= nl2br(Html::encode($message)) ?>
    </p>
    <p style="font-size:.85em;color:#888;margin:0;">
        <?= $isNotFound
            ? 'The page you requested could not be found.'
            : 'The above error occurred while processing your request. Please contact us if you think this is a server error.' ?>
    </p>
    <p style="font-size:.85em;margin-top:12px;">
        <a href="<?= Html::encode(Yii::$app->homeUrl) ?>">&larr; Return to home</a>
    </p>
</div>

<?php elseif (!$isTailwind): ?>
<div class="row justify-content-center mt-4">
    <div class="col-md-7 col-lg-6">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?= Html::encode($name) ?></strong>
            </div>
            <div class="card-body">
                <p class="mb-3"><?= nl2br(Html::encode($message)) ?></p>
                <p class="text-muted small mb-4">
                    <?= $isNotFound
                        ? 'The page you requested could not be found.'
                        : 'The above error occurred while processing your request. Please contact us if you think this is a server error.' ?>
                </p>
                <a href="<?= Html::encode(Yii::$app->homeUrl) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Return to home
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="flex justify-center mt-8">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-lg
                border border-red-200 dark:border-red-800 overflow-hidden">
        <div class="flex items-center gap-2 px-5 py-3 bg-red-600 dark:bg-red-700">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-white shrink-0"></i>
            <span class="text-sm font-semibold text-white"><?= Html::encode($name) ?></span>
        </div>
        <div class="px-5 py-5">
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                <?= nl2br(Html::encode($message)) ?>
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-5">
                <?= $isNotFound
                    ? 'The page you requested could not be found.'
                    : 'The above error occurred while processing your request. Please contact us if you think this is a server error.' ?>
            </p>
            <a href="<?= Html::encode(Yii::$app->homeUrl) ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg
                      border border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                      hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Return to home
            </a>
        </div>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady(<<<JS
if (typeof lucide !== 'undefined') lucide.createIcons();
JS);
?>

<?php endif ?>
