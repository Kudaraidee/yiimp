<?php
/**
 * @var yii\web\View       $this
 * @var app\models\Renters $renter
 */
use yii\helpers\Html;

$this->title = 'Renting Settings';
$addr = Html::encode($renter->address);
?>
<div class="row">
<div class="col-md-6">
<div style="padding:20px; border:1px solid #ddd; border-radius:8px;">
<form action="/renting?address=<?= $addr ?>" method="post">
<?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

<p style="font-size:1.2em; font-weight:bold;">Your Bitcoin deposit address</p>
<p>Save this address — you will need it to log in next time.</p>

<code style="background-color:#eee; font-size:1.3em;"><?= $addr ?></code><br><br>
<img width="200" height="200" src="https://chart.googleapis.com/chart?cht=qr&chl=bitcoin%3A<?= $addr ?>&choe=UTF-8&chs=200x200" alt="QR"><br><br>

<p>Minimum deposit 0.001 BTC.</p>
<p>Set a password to secure your account. Email is optional.</p>

<table cellspacing="10">
  <tr><td>Email</td><td><input value="<?= Html::encode($renter->email ?? '') ?>" type="text" name="deposit_email" placeholder="optional" class="main-text-input" style="width:280px;"></td></tr>
  <tr><td>API Key</td><td><input readonly value="<?= Html::encode($renter->apikey ?? '') ?>" type="text" class="main-text-input" style="width:280px;"></td></tr>
  <tr><td>Deposit Address</td><td><input readonly value="<?= $addr ?>" type="text" class="main-text-input" style="width:280px;"></td></tr>
  <tr><td>Password</td><td><input type="password" name="deposit_password" placeholder="leave empty for no change" class="main-text-input" style="width:280px;"></td></tr>
  <tr><td>Confirm</td><td><input type="password" name="deposit_confirm" class="main-text-input" style="width:280px;"></td></tr>
</table>
<br>
<input type="submit" value="Save" class="main-submit-button">
<?= Html::a('Cancel', ['/renting', 'address' => $renter->address], ['class' => 'main-submit-button']) ?>
</form>
</div>
</div>
</div>
