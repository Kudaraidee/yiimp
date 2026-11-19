<?php

/** @var yii\web\View     $this   */
/** @var app\models\Markets $market */
/** @var app\models\Coins   $coin   */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
echo ' - ' . Html::a($coin->name, ['/admin/coinwallet', 'id' => $coin->id]);
echo " &mdash; {$market->name}<br/><br/>";

$form = ActiveForm::begin(['options' => ['class' => 'uniForm']]);

echo '<fieldset class="inlineLabels">';

echo $form->field($market, 'deposit_address')
    ->textInput(['maxlength' => 200])
    ->hint('Use Address::PaymentID on XMR forks');

echo $form->field($market, 'base_coin')
    ->textInput(['maxlength' => 16, 'style' => 'width: 80px;'])
    ->hint('Default (empty) is BTC');

echo '</fieldset>';

echo Html::submitButton('Save', ['class' => 'submitButton ui-button ui-corner-all ui-widget']);

ActiveForm::end();
