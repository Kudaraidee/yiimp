<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Admin Login';
?>
<div class="admin-login">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card shadow-sm mt-5">
                <div class="card-body p-5">
                    <h1 class="text-center mb-4"><?= Html::encode($this->title) ?></h1>

                    <p class="text-center text-muted mb-4">Please fill out the following fields to login:</p>

                    <?php $form = ActiveForm::begin([
                        'id' => 'login-form',
                        'layout' => 'horizontal',
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'form-label'],
                        ],
                    ]); ?>

                        <?= $form->field($model, 'username')->textInput(['autofocus' => true, 'class' => 'form-control']) ?>

                        <?= $form->field($model, 'password')->passwordInput(['class' => 'form-control']) ?>

                        <?= $form->field($model, 'rememberMe')->checkbox([
                            'template' => "<div class=\"form-check\">{input} {label}</div>\n{error}",
                            'labelOptions' => ['class' => 'form-check-label'],
                            'inputOptions' => ['class' => 'form-check-input'],
                        ]) ?>

                        <div class="form-group mt-4">
                            <?= Html::submitButton('Login', ['class' => 'btn btn-primary w-100', 'name' => 'login-button']) ?>
                        </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
