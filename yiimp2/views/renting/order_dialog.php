<?php
/**
 * @var yii\web\View            $this
 * @var app\models\Renters      $renter
 * @var app\models\Jobs|null    $job
 * @var bool                    $isAdmin
 */
use yii\helpers\Html;

$cu      = Yii::$app->ConversionUtils;
$algos   = Yii::$app->YiimpUtils->get_algos();
$id      = $job ? $job->id : 0;
$selAlgo = $job ? $job->algo : (defined('YIIMP_DEFAULT_ALGO') ? YIIMP_DEFAULT_ALGO : 'x11');
$server  = $job ? "{$job->host}:{$job->port}" : '';
$uname   = Html::encode($job->username ?? '');
$pass    = Html::encode($job->password ?? 'xx');
$pct     = Html::encode($job->percent ?? '');
$price   = $job ? $cu->mbitcoinvaluetoa($job->price) : '';
$speed   = $job ? $job->speed / 1_000_000 : '';
?>
<form id="order-edit-form" action="/renting/ordersave" method="post">
<?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
<input type="hidden" name="order_id" value="<?= $id ?>">
<input type="hidden" name="order_renterid" value="<?= $renter->id ?>">
<input type="hidden" name="order_address" value="<?= Html::encode($renter->address) ?>">

<p>Enter your job information and click Submit.</p>

<table cellspacing="10" width="100%">
<tr><td>Algo:</td><td>
  <select name="order_algo" class="main-text-input">
    <?php foreach ($algos as $a):
        if (!$isAdmin && in_array($a, ['sha256', 'scryptn'])) continue;
    ?>
    <option value="<?= Html::encode($a) ?>"<?= $a === $selAlgo ? ' selected' : '' ?>><?= Html::encode($a) ?></option>
    <?php endforeach; ?>
  </select>
</td></tr>
<tr><td>Server:</td><td><input type="text" value="<?= Html::encode($server) ?>" name="order_host" class="main-text-input" placeholder="stratum.server.com:3333"></td></tr>
<tr><td>Username:</td><td><input type="text" value="<?= $uname ?>" name="order_username" class="main-text-input" placeholder="wallet_address"></td></tr>
<tr><td>Password:</td><td><input type="text" value="<?= $pass ?>" name="order_password" class="main-text-input"></td></tr>
<tr><td>Max Price<br><small>(mBTC/mh/day)</small>:</td><td><input type="text" value="<?= Html::encode($price) ?>" name="order_price" class="main-text-input"></td></tr>
<tr><td>Max Hashrate<br><small>(Mh/s)</small>:</td><td><input type="text" value="<?= Html::encode($speed) ?>" name="order_speed" class="main-text-input"></td></tr>
<?php if ($isAdmin): ?>
<tr><td>Percent:</td><td><input type="text" value="<?= $pct ?>" name="order_percent" class="main-text-input"></td></tr>
<?php endif; ?>
</table>
</form>
