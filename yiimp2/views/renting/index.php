<?php
/**
 * @var yii\web\View   $this
 * @var app\models\Renters $renter
 */
use yii\helpers\Html;

$this->title = 'Renting';
$cu      = Yii::$app->ConversionUtils;
$balance = $cu->bitcoinvaluetoa($renter->balance);
$addr    = Html::encode($renter->address);
?>

<?php if (Yii::$app->session->hasFlash('error')): ?>
<p style="color:red;"><?= Html::encode(Yii::$app->session->getFlash('error')) ?></p>
<?php endif; ?>
<?php if (Yii::$app->session->hasFlash('message')): ?>
<p style="color:green;"><?= Html::encode(Yii::$app->session->getFlash('message')) ?></p>
<?php endif; ?>

<div class="row">
<div class="col-md-6">
  <div id="balance_results"></div>
  <div id="orders_results"></div>
</div>
<div class="col-md-6">
  <div id="all_orders_results"></div>
  <div id="status_results"></div>
  <div class="main-left-box"><div class="main-left-title">Last 24 Hours Renting</div>
  <div class="main-left-inner"><div id="graph_results_price" style="height:240px;"></div></div></div>
</div>
</div>

<!-- Order edit modal -->
<div class="modal fade" id="order-edit-modal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="order-modal-title">Edit Job</h5></div>
  <div class="modal-body" id="order-edit-body"></div>
  <div class="modal-footer">
    <button class="btn btn-primary" id="btn-order-submit">Submit</button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-danger d-none" id="btn-order-delete">Delete</button>
  </div>
</div>
</div>
</div>

<!-- Withdraw modal -->
<div class="modal fade" id="withdraw-modal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Withdraw</h5></div>
  <div class="modal-body">
    <form action="/renting/withdraw" method="post" id="withdraw-form">
      <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
      Amount: <input type="text" name="withdraw_amount" class="main-text-input" style="width:100px;" value="<?= $balance ?>"><br>
      Address: <input type="text" name="withdraw_address" class="main-text-input" style="width:300px;"><br><br>
      <p>Withdraw fee 0.0001</p>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-primary" onclick="$('#withdraw-form').submit()">Withdraw</button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
  </div>
</div>
</div>
</div>

<script>
var rentingAddress = '<?= $addr ?>';

$(function () {
    balance_refresh();
    orders_refresh();
    all_orders_refresh();
    status_refresh();
    graph_price_refresh();
    setInterval(function () {
        balance_refresh(); orders_refresh(); all_orders_refresh(); status_refresh();
    }, 30000);
});

function balance_refresh()      { $.get('/renting/balance_results?address=' + rentingAddress, '', function(d){ $('#balance_results').html(d); }); }
function orders_refresh()       { $.get('/renting/orders_results?address=' + rentingAddress,  '', function(d){ $('#orders_results').html(d); }); }
function all_orders_refresh()   { $.get('/renting/all_orders_results?address=' + rentingAddress, '', function(d){ $('#all_orders_results').html(d); }); }
function status_refresh()       { $.get('/renting/status_results', '', function(d){ $('#status_results').html(d); }); }

function graph_price_refresh() {
    $.get('/renting/graph_price_results', '', function (data) {
        yiimpChart('graph_results_price', JSON.parse(data), {
            title: 'Renting Price (mBTC/Mh/day)', labels: ['Raw', 'Smoothed']
        });
    });
}

function order_edit(jobid) {
    $('#order-modal-title').text('Edit Job');
    $('#btn-order-delete').removeClass('d-none').off('click').on('click', function () {
        if (confirm('Delete this job?')) window.location.href = '/renting/orderdelete?id=' + jobid;
    });
    $('#order-edit-body').load('/renting/orderdialog?address=' + rentingAddress + '&id=' + jobid, function () {
        $('#btn-order-submit').off('click').on('click', function () { $('#order-edit-form').submit(); });
        new bootstrap.Modal(document.getElementById('order-edit-modal')).show();
    });
}

function order_new() {
    $('#order-modal-title').text('New Job');
    $('#btn-order-delete').addClass('d-none');
    $('#order-edit-body').load('/renting/orderdialog?address=' + rentingAddress, function () {
        $('#btn-order-submit').off('click').on('click', function () { $('#order-edit-form').submit(); });
        new bootstrap.Modal(document.getElementById('order-edit-modal')).show();
    });
}

function yaamp_withdraw() {
    new bootstrap.Modal(document.getElementById('withdraw-modal')).show();
}

function reset_spent() {
    if (confirm('Reset the spent counter?')) window.location.href = '/renting/resetspent?address=' + rentingAddress;
}

function show_job_graph(jobid) {
    var $ph = $('#graph_placeholder_job-' + jobid);
    if ($ph.is(':visible')) {
        $ph.hide();
    } else {
        $ph.show();
        $.get('/renting/graph_job_results?jobid=' + jobid, '', function (data) {
            yiimpChart('graph_results_job-' + jobid, JSON.parse(data), {
                title: 'Hashrate (Mh/s)', labels: ['Raw', 'Smoothed', 'Rejected']
            });
        });
    }
}

function main_renter_tx() {
    window.open('/renting/tx?address=' + rentingAddress, 'renting_tx', 'width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no');
}
</script>
