<?php
use app\models\Nicehash;
use yii\helpers\Html;

$cu   = Yii::$app->ConversionUtils;
$list = Nicehash::find()->all();
?>
<br>
<table class="dataGrid">
<thead><tr>
  <th>ID</th><th>Algo</th><th>BTC</th>
  <th>NiceHash</th><th>Yaamp</th>
  <th>Price</th><th>Speed</th>
  <th>Last Dec</th><th>Workers</th><th>Accepted</th>
  <th></th>
</tr></thead><tbody>
<?php foreach ($list as $order):
    $price2 = $cu->mbitcoinvaluetoa(
        (float) Yii::$app->db->createCommand(
            "SELECT price FROM services WHERE algo = :a", [':a' => $order->algo]
        )->queryScalar() * 1000
    );
    $yaamp = $cu->mbitcoinvaluetoa(
        (float) Yii::$app->db->createCommand(
            "SELECT price FROM hashrate WHERE algo = :a ORDER BY time DESC LIMIT 1", [':a' => $order->algo]
        )->queryScalar()
    );
    $d = $cu->datetoa2($order->last_decrease);
?>
<tr class="ssrow">
  <td><?= Html::encode($order->orderid) ?></td>
  <td><?= Html::encode($order->algo) ?></td>
  <td><?= Html::encode($order->btc) ?></td>
  <td><?= $price2 ?></td>
  <td<?= $yaamp > $price2 * 1.1 ? ' style="color:#4a4"' : '' ?>><?= $yaamp ?></td>
  <td<?= $order->price > $yaamp ? ' style="color:#a44"' : '' ?>><?= Html::encode($order->price) ?></td>
  <td><?= Html::encode($order->speed) ?></td>
  <td><?= $d ?></td>
  <?php if (!$order->workers && !$order->accepted && !$order->rejected): ?>
  <td colspan="2"></td>
  <?php else: ?>
  <td><?= Html::encode($order->workers) ?></td>
  <td><?= Html::encode($order->accepted) ?></td>
  <?php endif; ?>
  <td>
    <?php if ($order->active): ?>
      <?= Html::a('[stop]', ['/nicehash/stop', 'id' => $order->id]) ?>
    <?php else: ?>
      <?= Html::a('[start]', ['/nicehash/start', 'id' => $order->id]) ?>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
