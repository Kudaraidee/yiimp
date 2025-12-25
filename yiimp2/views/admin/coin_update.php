<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

use app\models\Algos;
use app\assets\CspCompliantAsset;

CspCompliantAsset::register($this);

echo Html::a('Back to coin list', ['coinwallets']);

$form = ActiveForm::begin([
	'options' => [
		'class' => 'yiimp-form',
	],
	'fieldConfig' => [
		'options' => ['class' => 'form-group'],
		'errorOptions' => ['class' => 'invalid-feedback'],
		'inputOptions' => ['class' => 'form-control'],
		'labelOptions' => ['class' => 'form-label'],
	],
	'enableClientValidation' => true,
	'validateOnSubmit' => true,
]);

echo $form->errorSummary($coin);
echo Html::beginTag('fieldset', array('class'=>'inlineLabels'));

if(!$coin->installed)
{
	echo $form->field($coin, 'name')->textInput()->label('Name');

	echo $form->field($coin, 'symbol')->textInput()->label('Symbol');

		echo $form->field($coin, 'image')->textInput()->label('image');

	$ListAlgos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
	echo $form->field($coin, 'algo')->dropDownList($ListAlgos)->label('Algo');
}
echo $form->field($coin, 'link_bitcointalk')->textInput()->label('link_bitcointalk');

echo $form->field($coin, 'link_github')->textInput()->label('link_github');

echo $form->field($coin, 'link_site')->textInput()->label('link_site');

echo $form->field($coin, 'link_exchange')->textInput()->label('link_exchange');

echo $form->field($coin, 'link_explorer')->textInput()->label('link_explorer');

echo $form->field($coin, 'link_twitter')->textInput()->label('link_twitter');

echo $form->field($coin, 'link_discord')->textInput()->label('link_discord');

echo $form->field($coin, 'link_facebook')->textInput()->label('link_facebook');

echo Html::endTag('fieldset');

echo '<div class="form-group">';
echo Html::submitButton(($update? 'Save': 'Create'), ['class' => 'btn btn-primary']);
echo '</div>';

ActiveForm::end();
