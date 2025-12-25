<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Workers;
use app\models\Stratums;
use app\components\CspHelper;

$defaultalgo = Yii::$app->session->get('yaamp-algo');

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Status</div>";
echo "<div class='main-left-inner'>";
Yii::$app->ViewUtils->showTableSorter('maintable1', "{
    tableClass: 'dataGrid2',
    textExtraction: {
        4: function(node, table, n) { return $(node).attr('data'); },
        8: function(node, table, n) { return $(node).attr('data'); }
    }
}");
echo <<<END
<thead>
<tr>
<th>Coins</th>
<th data-sorter="numeric" align="center">Auto Exchanged</th>
<th data-sorter="numeric" align="center">Minimum Payout</th>
<th data-sorter="numeric" align="center">Port</th>
<th data-sorter="numeric" align="center">Users (Active)</th>
<th data-sorter="numeric" align="center">Workers<br/>Share/Solo</th>
<th data-sorter="numeric" align="center">Pool HashRate<br/>Share/Solo/Total</th>
<th data-sorter="numeric" align="center">Network Hashrate</th>
<th data-sorter="currency" align="center">Fees<br/>Share/Solo</th>
<!--<th data-sorter="currency" class="estimate" align="right">Current<br />Estimate</th>-->
<!--<th data-sorter="currency" >Norm</th>-->
<!--<th data-sorter="currency" class="estimate" align="right">24 Hours<br />Estimated</th>-->
<th data-sorter="currency"align="center">24 Hours<br />Actual</th>
</tr>
</thead>
END;
$best_algo = '';
$best_norm = 0;
$algos = array();

foreach (Yii::$app->YiimpUtils->get_algos() as $algo)
{
    $algo_norm = Yii::$app->YiimpUtils->get_algo_norm($algo);

    $price = Yii::$app->cache->get("current_price-$algo");
    if (!$price) {
        $price = (new \yii\db\Query())
                ->select(['price'])
                ->from('hashrate')
                ->where(['algo' => $algo])
                ->orderBy(['time' => SORT_DESC])
                ->scalar();
        Yii::$app->cache->set("current_price-$algo", $price);
    }

    $norm = $price * $algo_norm;
    $norm = Yii::$app->YiimpUtils->take_yiimp_fee($norm, $algo);
    $algos[] = array(
        $norm,
        $algo
    );
    if ($norm > $best_norm)
    {
        $best_norm = $norm;
        $best_algo = $algo;
    }
}

// Use anonymous function to avoid redeclaration
usort($algos, function($a, $b) {
    return $a[0] < $b[0];
});
$total_coins = 0;
$total_workers = 0;
$total_solo_workers = 0;
$total_users = 0;
$showestimates = false;
echo "<tbody>";


foreach ($algos as $item)
{
    $norm = $item[0];
    $algo = $item[1];
    $coinsym = '';
    $users_total = 0;

    $coins = Coins::find()->where(['enable' => 1 , 'visible' => 1 , 'auto_ready' => 1 , 'algo' => $algo])->orderBy(['index_avg' => SORT_DESC]);

    if ((!$coins) || ($coins->count()==0)) continue;

    if ($coins->count() == 2)
    {
        // If we only mine one coin, show it...
        $coin = $coins->one();
        $coinsym = empty($coin->symbol2) ? $coin->symbol : $coin->symbol2;
        $coinsym = '<span title="' . $coin->name . '">' . $coinsym . '</a>';
    }

    $workers = Workers::find()->where(['algo' => $algo])->andFilterWhere(['not like', 'password', 'm=solo'])->count();
    $solo_workers = Workers::find()->where(['algo' => $algo])->andFilterWhere(['like', 'password', 'm=solo'])->count();

    $hashrate = Yii::$app->cache->get("current_hashrate-$algo");
    if (!$hashrate) {
        $hashrate = (new \yii\db\Query())
                ->select(['hashrate'])
                ->from('hashrate')
                ->where(['algo' => $algo])
                ->orderBy(['time' => SORT_DESC])
                ->scalar();
        Yii::$app->cache->set("current_hashrate-$algo", $hashrate);
    }
    $hashrate_sfx = $hashrate ? Yii::$app->ConversionUtils->Itoa2($hashrate) . 'h/s' : '-';

    $price = Yii::$app->cache->get("current_price-$algo");
    if (!$price) {
        $price = (new \yii\db\Query())
                ->select(['price'])
                ->from('hashrate')
                ->where(['algo' => $algo])
                ->orderBy(['time' => SORT_DESC])
                ->scalar();
        Yii::$app->cache->set("current_price-$algo", $price);
    }
    $price = $price ? Yii::$app->ConversionUtils->mbitcoinvaluetoa(Yii::$app->YiimpUtils->take_yiimp_fee($price, $algo)) : '-';

    $norm = Yii::$app->ConversionUtils->mbitcoinvaluetoa($norm);

    $t = time() - 24 * 60 * 60;

    $avgprice = Yii::$app->cache->get("current_avgprice-$algo");
    if (!$avgprice) {
        $avgprice = (new \yii\db\Query())
                ->select(['avg(price)'])
                ->from('hashrate')
                ->where(['algo' => $algo])
                ->andWhere(['>', 'time', $t])
                ->scalar();
        Yii::$app->cache->set("current_avgprice-$algo", $avgprice);
    }
    $avgprice = $avgprice ? Yii::$app->ConversionUtils->mbitcoinvaluetoa(Yii::$app->YiimpUtils->take_yiimp_fee($avgprice, $algo)) : '-';

    $total1 = Yii::$app->cache->get("current_total-$algo");
    if (!$total1) {
        $total1 = (new \yii\db\Query())
                ->select(['SUM(amount*price)'])
                ->from('blocks')
                ->where(['algo' => $algo])
                ->andWhere(['>', 'time', $t])
                ->andWhere(['not in', 'category', ['orphan','stake','generated']])
                ->scalar();
        Yii::$app->cache->set("current_total-$algo", $total1);
    }

    $hashrate1 = Yii::$app->cache->get("current_hashrate1-$algo");
    if (!$hashrate1) {
        $hashrate1 = (new \yii\db\Query())
                ->select(['avg(hashrate)'])
                ->from('hashrate')
                ->where(['algo' => $algo])
                ->andWhere(['>', 'time', $t])
                ->scalar();
        Yii::$app->cache->set("current_hashrate1-$algo", $hashrate1);
    }

    $algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
    $btcmhday1 = $hashrate1 != 0 ? Yii::$app->ConversionUtils->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : '';
    $fees = Yii::$app->YiimpUtils->yiimp_fee($algo);
    $fees_solo = Yii::$app->YiimpUtils->yiimp_fee_solo($algo);
    $port = 1;//getAlgoPort($algo);

    if ($defaultalgo == $algo) echo "<tr class='cursor-pointer algo-row bg-gray' data-algo='$algo'>";
    else echo "<tr class='cursor-pointer ssrow algo-row' data-algo='$algo'>";
    echo "<td class='bg-light-gray text-larger'><b>$algo</b></td>";
    echo "<td align=center class='text-small bg-light-gray'></td>";
    echo "<td align=center class='text-small bg-light-gray'></td>";
    echo "<td align=center class='text-small bg-light-gray'></td>";
    echo "<td align=center class='text-small bg-light-gray'></td>";
    echo '<td align="center" class="text-small bg-light-gray"></td>';
    echo '<td align="center" class="text-small bg-light-gray"></td>';
    echo "<td align=center class='text-small bg-light-gray'></td>";
    echo "<td align=center class='text-small bg-light-gray'></td>";
    if ($algo == $best_algo) echo '<td class="estimate text-small bg-light-gray" align="center" title="normalized ' . $norm . '"><b>' . $price . '</b></td>';
    else if ($norm > 0) echo '<td class="estimate text-small bg-light-gray" align="center" title="normalized ' . $norm . '">' . $price . '</td>';
    else echo '<td class="estimate text-small bg-light-gray" align="center"></td>';
    echo '<td class="estimate text-small bg-light-gray" align="center"></td>';
    if ($algo == $best_algo) echo '<td align="center" class="text-small bg-light-gray" data="' . $btcmhday1 . '"><b>' . $btcmhday1 . '*</b></td>';
    else echo '<td align="center" class="text-small bg-light-gray" data="' . $btcmhday1 . '">' . $btcmhday1 . '</td>';
    echo "</tr>";
    if ($coins->count() > 0)
    {
        $list = $coins->all();

        foreach ($list as $coin)
        {
            $name = substr($coin->name, 0, 20);
            $symbol = $coin->getOfficialSymbol();
            echo "<td align='left' valign='top' class='text-small'><img width='10' src='" . $coin->image . "'>  <b>$name ($coin->symbol)</b> </td>";

            $coin_stratum = Stratums::find()
                                ->where(['algo' => $algo, 'symbol' => $symbol]);
            $port_count = $coin_stratum->count();
            $port_db = ($port_count > 1) ? $coin_stratum->one():null;

            $auto_exchange = $coin->auto_exchange;
            if ($auto_exchange != 1) echo "<td align='center' valign='top' class='text-small'><img width=13 src='/images/cancel.png'></td>";
            else echo "<td align='center' valign='top' class='text-small'><img width=13 src='/images/ok.png'></td>";
			
			$min_payout = max(floatval(YAAMP_PAYMENTS_MINI), floatval($coin->payout_min));
			echo "<td align='center' class='text-small'><b>".$min_payout." $symbol</b></td>";

			if ($port_count >= 1) 
				echo "<td align='center' class='text-small'><b>".$port_db->port."</b></td>";
			else 
				echo "<td align='center' class='text-small'><b>$port</b></td>";
            
            $subquery = (new \yii\db\Query())->select(['userid'])->from('workers')->distinct();
            $users_total = (new \yii\db\Query())
                ->select(['count(id)'])
                ->from('accounts')
                ->Where(['in', 'id', $subquery])
                ->scalar();
            $users_coins = (new \yii\db\Query())
                ->select(['count(id)'])
                ->from('accounts')
                ->Where(['coinid' => $coin->id])
                ->andWhere(['in', 'id', $subquery])
                ->scalar();

            if ($port_count >= 1) 
				echo "<td align='center' class='text-small'>$users_coins</td>";
			else	
				echo "<td align='center' class='text-small'>$users_total</td>";
            
            $workers_coin_query = (new \yii\db\Query())
                ->select(['count(id)'])
                ->from('workers')
                ->Where(['algo' => $algo , 'pid' => (is_null($port_db)?0 :$port_db->pid)]);

            $workers_coins= $workers_coin_query->andWhere(['not like', 'password', 'm=solo'])->scalar();
            $solo_workers_coins = $workers_coin_query->andWhere(['like', 'password', 'm=solo'])->scalar();
            if ($port_count == 1) 
	    		echo "<td align='center' class='text-small'>$workers_coins / $solo_workers_coins </td>";
			else
				echo "<td align='center' class='text-small'>$workers / $solo_workers </td>";
			
            $pool_hash = Yii::$app->YiimpUtils->coin_rate($coin->id);
            $pool_hash_sfx = $pool_hash ? Yii::$app->ConversionUtils->Itoa2($pool_hash) . 'h/s' : '0 h/s';
			$pool_shared_hash = Yii::$app->YiimpUtils->coin_shared_rate($coin->id);
			$pool_shared_hash_sfx = $pool_shared_hash ? Yii::$app->ConversionUtils->Itoa2($pool_shared_hash) . 'h/s' : '0 h/s';
			$pool_solo_hash = Yii::$app->YiimpUtils->coin_solo_rate($coin->id);
			$pool_solo_hash_sfx = $pool_solo_hash ? Yii::$app->ConversionUtils->Itoa2($pool_solo_hash) . 'h/s' : '0 h/s';
			echo "<td align='center' class='text-small'>$pool_shared_hash_sfx / $pool_solo_hash_sfx / $pool_hash_sfx</td>";
            
            $min_ttf = $coin->network_ttf > 0 ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;

            $network_hash = Yii::$app->YiimpUtils->coin_nethash($coin);
            $network_hash = $network_hash ? Yii::$app->ConversionUtils->Itoa2($network_hash) . 'h/s' : '';
            echo "<td align='center' class='text-small' data='$pool_hash'>$network_hash</td>";
            echo "<td align='center' class='text-small'>{$fees}% / {$fees_solo}% </td>";
            $btcmhd = Yii::$app->YiimpUtils->yiimp_profitability($coin);
            $btcmhd = Yii::$app->ConversionUtils->mbitcoinvaluetoa($btcmhd);
            echo "<td align='center' class='text-small'>$btcmhd</td>";
            echo "</tr>";
        }
    }

	$total_coins += $coins->count();
	$total_users = $users_total;
	$total_workers += $workers;
	$total_solo_workers += $solo_workers;
}

echo "</tbody>";


if ($defaultalgo == 'all') echo "<tr class='cursor-pointer algo-row bg-gray' data-algo='all'>";
else echo "<tr class='cursor-pointer ssrow algo-row' data-algo='all'>";
echo "<td><b>all</b></td>";
echo "<td></td>";
echo "<td align=center class='text-small'>$total_coins Coins</td>";
echo "<td></td>";
echo "<td align=center class='text-small'>$total_users Users</td>";
echo "<td align=center class='text-small'>Shared: $total_workers workers<br>Solo: $total_solo_workers workers</td>";
echo "<td></td>";
echo '<td class="estimate"></td>';
echo '<td class="estimate"></td>';
echo "<td></td>";
echo "<td></td>";
echo "<td></td>";
echo "</tr>";
echo "</table>";
echo '<p class="text-small">&nbsp;* values in mBTC/MH/day, per GH for sha & blake algos</p>';
echo "</div></div><br />";
?>

<?= CspHelper::beginScript() ?>
// Event listeners for algo selection rows
document.addEventListener('DOMContentLoaded', function() {
	var algoRows = document.querySelectorAll('.algo-row');
	algoRows.forEach(function(row) {
		row.addEventListener('click', function() {
			var algo = this.getAttribute('data-algo');
			select_algo(algo);
		});
	});
});
<?= CspHelper::endScript() ?>

<?php
if (!$showestimates):
?>
<?= CspHelper::style('#maintable1 .estimate { display: none; }') ?>
<?php
endif;
?>