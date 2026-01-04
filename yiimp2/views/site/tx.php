<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Accounts;
use app\models\Payouts;

$address = Yii::$app->getRequest()->getQueryParam('address');
$user = Accounts::find()->where(['username' => $address])->one();

if(!$user) {
    echo "<div class='alert alert-warning'>User not found</div>";
    return;
}

$this->title = $user->username . ' Transactions';

$bitcoin = Coins::find()->where(['symbol' => 'BTC'])->one();

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Transactions to $user->username</div>";
echo "<div class='main-left-inner'>";

$list = Payouts::find()
    ->where(['account_id' => $user->id])
    ->orderBy(['time' => SORT_DESC])
    ->all();

echo '<table class="dataGrid2">';

echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Time</th>";
echo "<th align=right>Amount</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

$coin = ($user->coinid == $bitcoin->id) ? $bitcoin : Coins::find()->where(['id' => $user->coinid])->one();

$total = 0;
foreach($list as $payout)
{
    $d = Yii::$app->ConversionUtils->datetoa2($payout->time);
    $amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($payout->amount);

    echo "<tr class='ssrow'>";
    echo "<td width=18></td>";
    echo "<td><b>$d ago</b></td>";

    echo "<td align=right><b>$amount</b></td>";

    $url = $coin->createExplorerLink($payout->tx, array('txid'=>$payout->tx), array('target'=>'_blank'));
    echo '<td class="font-mono">'.$url.'</td>';

    echo "</tr>";
    $total += $payout->amount;
}

$total = Yii::$app->ConversionUtils->bitcoinvaluetoa($total);

echo "<tr class='ssrow border-top-thick'>";
echo "<td width=18></td>";
echo "<td><b>Total</b></td>";

echo "<td align=right><b>$total</b></td>";
echo "<td></td>";

echo "</tr>";

echo "</table><br>";
echo "</div></div><br>";
