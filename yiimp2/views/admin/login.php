<?php

/** @var yii\web\View        $this  */
/** @var app\models\LoginForm $model */

use yii\helpers\Html;

$this->title = 'Login';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="main-left-box" style="max-width:380px;margin:24px auto;">
<div class="main-left-title">Admin Login</div>
<div class="main-left-inner">

<?php if ($model->hasErrors()): ?>
<p style="color:red;font-size:.9em;"><?= Html::encode($model->getFirstError('username') ?: $model->getFirstError('password') ?: 'Login failed.') ?></p>
<?php endif ?>

<?= Html::beginForm('', 'post', ['class' => 'uniForm']) ?>
<fieldset>

  <div class="ctrlHolder">
    <?= Html::label('Username', 'loginform-username') ?>
    <?= Html::activeTextInput($model, 'username', ['id' => 'loginform-username', 'autofocus' => true, 'class' => 'main-text-input']) ?>
  </div>

  <div class="ctrlHolder">
    <?= Html::label('Password', 'loginform-password') ?>
    <?= Html::activePasswordInput($model, 'password', ['id' => 'loginform-password', 'class' => 'main-text-input']) ?>
  </div>

  <div class="ctrlHolder">
    <?= Html::activeCheckbox($model, 'rememberMe', ['label' => 'Remember me']) ?>
  </div>

</fieldset>
<div class="buttonHolder">
  <?= Html::submitButton('Login', ['class' => 'submitButton']) ?>
</div>
<?= Html::endForm() ?>

</div></div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="row justify-content-center mt-4">
<div class="col-sm-8 col-md-5 col-lg-4">
<div class="card shadow-sm">
    <div class="card-header py-2 text-center">
        <strong><?= Html::encode(YIIMP_SITE_NAME) ?></strong>
        <span class="text-muted small ms-1">— Login</span>
    </div>
    <div class="card-body">

        <?= \app\widgets\Alert::widget() ?>

        <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger py-2 small">
            <?= Html::encode($model->getFirstError('username') ?: $model->getFirstError('password') ?: 'Login failed.') ?>
        </div>
        <?php endif ?>

        <?= Html::beginForm('', 'post') ?>

        <div class="mb-3">
            <?= Html::label('Username / Wallet', 'loginform-username', ['class' => 'form-label small fw-semibold']) ?>
            <?= Html::activeTextInput($model, 'username', [
                'id'        => 'loginform-username',
                'class'     => 'form-control form-control-sm' . ($model->hasErrors('username') ? ' is-invalid' : ''),
                'autofocus' => true,
            ]) ?>
            <?php if ($model->hasErrors('username')): ?>
            <div class="invalid-feedback"><?= Html::encode($model->getFirstError('username')) ?></div>
            <?php endif ?>
        </div>

        <div class="mb-3">
            <?= Html::label('Password', 'loginform-password', ['class' => 'form-label small fw-semibold']) ?>
            <?= Html::activePasswordInput($model, 'password', [
                'id'    => 'loginform-password',
                'class' => 'form-control form-control-sm' . ($model->hasErrors('password') ? ' is-invalid' : ''),
            ]) ?>
            <?php if ($model->hasErrors('password')): ?>
            <div class="invalid-feedback"><?= Html::encode($model->getFirstError('password')) ?></div>
            <?php endif ?>
        </div>

        <div class="mb-4">
            <div class="form-check">
                <?= Html::activeCheckbox($model, 'rememberMe', ['class' => 'form-check-input', 'label' => false]) ?>
                <?= Html::label('Remember me', 'loginform-rememberme', ['class' => 'form-check-label small']) ?>
            </div>
        </div>

        <?= Html::submitButton('Login', ['class' => 'btn btn-primary btn-sm w-100']) ?>
        <?= Html::endForm() ?>

    </div>
</div>
</div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex justify-center mt-10">
<div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-lg
            border border-gray-200 dark:border-gray-700 overflow-hidden">

    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 text-center">
        <span class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= Html::encode(YIIMP_SITE_NAME) ?></span>
        <span class="text-xs text-gray-400 dark:text-gray-500 ms-1">— Login</span>
    </div>

    <div class="px-6 py-5">

        <?php if ($model->hasErrors()): ?>
        <div class="mb-4 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20
                    border border-red-200 dark:border-red-700 rounded-lg px-3 py-2">
            <?= Html::encode($model->getFirstError('username') ?: $model->getFirstError('password') ?: 'Login failed.') ?>
        </div>
        <?php endif ?>

        <?= Html::beginForm('', 'post') ?>

        <div class="mb-4">
            <label for="loginform-username"
                   class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                Username / Wallet
            </label>
            <?= Html::activeTextInput($model, 'username', [
                'id'        => 'loginform-username',
                'autofocus' => true,
                'class'     => 'w-full text-sm rounded-lg border px-3 py-2
                               bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 '
                               . ($model->hasErrors('username')
                                   ? 'border-red-400 dark:border-red-600'
                                   : 'border-gray-300 dark:border-gray-600'),
            ]) ?>
            <?php if ($model->hasErrors('username')): ?>
            <p class="mt-1 text-xs text-red-500"><?= Html::encode($model->getFirstError('username')) ?></p>
            <?php endif ?>
        </div>

        <div class="mb-4">
            <label for="loginform-password"
                   class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                Password
            </label>
            <?= Html::activePasswordInput($model, 'password', [
                'id'    => 'loginform-password',
                'class' => 'w-full text-sm rounded-lg border px-3 py-2
                           bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 '
                           . ($model->hasErrors('password')
                               ? 'border-red-400 dark:border-red-600'
                               : 'border-gray-300 dark:border-gray-600'),
            ]) ?>
            <?php if ($model->hasErrors('password')): ?>
            <p class="mt-1 text-xs text-red-500"><?= Html::encode($model->getFirstError('password')) ?></p>
            <?php endif ?>
        </div>

        <div class="mb-5 flex items-center gap-2">
            <?= Html::activeCheckbox($model, 'rememberMe', [
                'class' => 'w-4 h-4 rounded border-gray-300 dark:border-gray-600
                           text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0
                           dark:bg-gray-700',
                'label' => false,
            ]) ?>
            <label for="loginform-rememberme"
                   class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                Remember me
            </label>
        </div>

        <?= Html::submitButton('Login', [
            'class' => 'w-full py-2 text-sm font-medium text-white
                        bg-indigo-600 hover:bg-indigo-700 rounded-lg
                        transition-colors cursor-pointer',
        ]) ?>
        <?= Html::endForm() ?>

    </div>
</div>
</div>

<?php endif ?>
