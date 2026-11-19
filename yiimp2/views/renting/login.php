<?php
/** @var yii\web\View $this */
use yii\helpers\Html;
use yii\captcha\Captcha;

$this->title = 'Renting Login';
$address = Html::encode(Yii::$app->request->get('address', ''));
if (!empty($address) && preg_match('/[^A-Za-z0-9]/', $address)) $address = '';
?>

<div class="row">
<div class="col-md-6">
<div class="yaamp-login-container" style="padding:20px; border:1px solid #ddd; border-radius:8px;">

<?php if (defined('YIIMP_RENTAL') && !YIIMP_RENTAL): ?>
<p style="font-size:1.2em; font-weight:bold; color:red;">Renting is temporarily disabled.</p>
<?php endif; ?>

<?php if (Yii::$app->session->hasFlash('error')): ?>
<p style="color:red;"><?= Html::encode(Yii::$app->session->getFlash('error')) ?></p>
<?php endif; ?>

<form action="/renting/login" method="post">
<?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
<p style="font-size:1.2em; font-weight:bold;">Login to the renting area</p>
<p>Enter your deposit address and password to access your account.</p>
<table cellspacing="10">
  <tr><td>Deposit Address</td><td><input type="text" value="<?= $address ?>" name="deposit_address" class="main-text-input" style="width:280px;"></td></tr>
  <tr><td>Password</td><td><input type="password" name="deposit_password" class="main-text-input" style="width:280px;"></td></tr>
</table>
<br>
<input type="submit" value="Login" class="main-submit-button">
<input type="button" value="Register" class="main-submit-button" onclick="document.getElementById('deposit-create-modal').style.display='block'">
</form>

</div>
</div>
<div class="col-md-6">
<div id="all_orders_results"></div>
</div>
</div>

<!-- Register modal -->
<div id="deposit-create-modal" class="modal fade" tabindex="-1" style="display:none;">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Create Deposit Address</h5></div>
<div class="modal-body">
<form action="/renting/create" method="post">
<?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
<p>You are about to create a new Bitcoin deposit address. You will then be able to rent hashpower from this pool.</p>
<p>It is recommended you send a small amount (minimum 0.001 BTC) first to verify your setup.</p>
<?= Captcha::widget(['name' => 'create_code', 'captchaAction' => '/renting/captcha']) ?>
<br><br>
<input type="submit" value="Register" class="main-submit-button">
</form>
</div>
</div>
</div>
</div>

<script>
$(function () {
    all_orders_refresh();
    setInterval(all_orders_refresh, 60000);
    // Bootstrap modal trigger
    $('[data-bs-toggle="modal"]').on('click', function () {
        new bootstrap.Modal(document.getElementById('deposit-create-modal')).show();
    });
});
function all_orders_ready(data) { $('#all_orders_results').html(data); }
function all_orders_refresh() { $.get('/renting/all_orders_results', '', all_orders_ready); }
</script>
