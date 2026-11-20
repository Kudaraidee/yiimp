<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Balanceuser;

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user) return;

$step = 15*60;
$t = time() - 24*60*60;

$stats = Balanceuser::find()
			->where(['userid' => $user->id])
			->andWhere(['>' , 'time', $t])
			->orderBy('time')
			->all();
echo '[[';

for($i = $t+$step, $j = 0; $i < time(); $i += $step)
{
	if($i != $t+$step) echo ',';
	$m = 0;

	if(isset($stats[$j]) && $i > $stats[$j]->time)
	{
		$m = Yii::$app->ConversionUtils->bitcoinvaluetoa($stats[$j]->balance);
		$j++;
	}

	$d = date('Y-m-d H:i:s', $i);
	echo "[\"$d\",$m]";

}

echo '],[';

for($i = $t+$step, $j = 0; $i < time(); $i += $step)
{
	if($i != $t+$step) echo ',';
	$m = 0;

	if(isset($stats[$j]) && $i > $stats[$j]->time)
	{
		$m = Yii::$app->ConversionUtils->bitcoinvaluetoa($stats[$j]->pending);
		$j++;
	}

	$d = date('Y-m-d H:i:s', $i);
	echo "[\"$d\",$m]";

}

echo ']]';

