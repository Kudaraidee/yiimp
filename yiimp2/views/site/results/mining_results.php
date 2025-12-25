<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Coins;
use app\models\Workers;
use app\components\ViewHelper;

$algo = Yii::$app->session->get('yaamp-algo');

$total_rate   = Yii::$app->YiimpUtils->pool_rate();
$total_rate_d = $total_rate ? 'at ' . Yii::$app->ConversionUtils->Itoa2($total_rate) . 'h/s' : '';

if ($algo == 'all')
    $list = Coins::find()->where(['enable' => 1, 'visible' => 1])->orderBy(['auxpow' => SORT_ASC, 'index_avg' => SORT_DESC])->all();
else
    $list = Coins::find()->where(['enable' => 1, 'visible' => 1, 'algo' => $algo])->orderBy(['auxpow' => SORT_ASC, 'index_avg' => SORT_DESC])->all();

$count = count($list);


if ($algo == 'all')
    $worker = Workers::find()->count();
else
    $worker = Workers::find()->where(['algo' => $algo])->count();

////////////////////////////////////////////////////////////////////////////////////

$coin_count  = $count > 1 ? "on $count wallets" : 'on a single wallet';
$miner_count = $worker > 1 ? "$worker miners" : "$worker miner";
ViewHelper::renderBoxHeader("Mining $coin_count $total_rate_d, $miner_count");

Yii::$app->ViewUtils->showTableSorter('maintable3', "{
    tableClass: 'dataGrid2',
    textExtraction: {
        3: function(node, table, n) { return $(node).attr('data'); },
        6: function(node, table, n) { return $(node).attr('data'); },
        7: function(node, table, n) { return $(node).attr('data'); }
    }
}");

echo <<<END
<thead>
<tr>
<th data-sorter=""></th>
<th data-sorter="text">Name</th>
<th align="right">Amount</th>
<th data-sorter="numeric" align="right">Diff</th>
<th align="right">Block</th>
<th align="right">TTF***</th>
<th data-sorter="numeric" align="right">Hash**</th>
<th data-sorter="currency" align="right">Profit*</th>
</tr>
</thead>
END;

$separate_aux = 0;
foreach ($list as $coin) {
    if($coin->auxpow && !$separate_aux) {
        $separate_aux = 1;
        echo "<tr class='ssrow'><td></td><td>merged mined coins</td></tr>";
    }

    $name       = substr($coin->name, 0, 20);
    $difficulty = Yii::$app->ConversionUtils->Itoa2($coin->difficulty, 3);
    $price      = Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price);
    $height     = number_format($coin->block_height, 0, '.', ' ');

	$blocktime = $coin->block_time? $coin->block_time : max(min($coin->actual_ttf, 60), 30);
    $network_hash = Yii::$app->YiimpUtils->coin_nethash($coin);
    $network_hash_string = $network_hash? 'network hash '.Yii::$app->ConversionUtils->Itoa2($network_hash).'h/s': '';

	$total_pool_rate   = Yii::$app->YiimpUtils->pool_rate($coin->algo);
	$pool_total_rate = $total_pool_rate ? Yii::$app->ConversionUtils->Itoa2($total_pool_rate) . 'h/s' : '';

    $pool_ttf   = $total_rate? $network_hash / $total_rate * $blocktime: 0;
    $reward     = round($coin->reward, 3);

    $btcmhd    = Yii::$app->YiimpUtils->yiimp_profitability($coin);
    $pool_hash = Yii::$app->YiimpUtils->coin_rate($coin->id);
    $real_ttf  = $pool_hash? $network_hash / $pool_hash * $blocktime: 0;

    $pool_shared_hash = Yii::$app->YiimpUtils->coin_shared_rate($coin->id);
    $shared_real_ttf  = $pool_shared_hash? $network_hash / $pool_shared_hash * $blocktime: 0;

    $pool_solo_hash = Yii::$app->YiimpUtils->coin_solo_rate($coin->id);
    $solo_real_ttf  = $pool_solo_hash? $network_hash / $pool_solo_hash * $blocktime: 0;

    $pool_hash_sfx = $pool_hash ? Yii::$app->ConversionUtils->Itoa2($pool_hash) . 'h/s' : '';
    $pool_shared_hash_sfx = $pool_shared_hash ? Yii::$app->ConversionUtils->Itoa2($pool_shared_hash) . 'h/s' : '';
    $pool_solo_hash_sfx = $pool_solo_hash ? Yii::$app->ConversionUtils->Itoa2($pool_solo_hash) . 'h/s' : '';

    $real_ttf      = $real_ttf ? Yii::$app->ConversionUtils->sectoa2($real_ttf) : '';
    $shared_real_ttf	= $shared_real_ttf ? Yii::$app->ConversionUtils->sectoa2($shared_real_ttf) : '';
    $solo_real_ttf = $solo_real_ttf ? Yii::$app->ConversionUtils->sectoa2($solo_real_ttf) : '';
    $pool_ttf      = $pool_ttf ? Yii::$app->ConversionUtils->sectoa2($pool_ttf) : '';

    $pool_hash_pow     = Yii::$app->YiimpUtils->pool_rate_pow($coin->algo);
    $pool_hash_pow_sfx = $pool_hash_pow ? Yii::$app->ConversionUtils->Itoa2($pool_hash_pow) . 'h/s' : '';

    $min_ttf      = $coin->network_ttf > 0 ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;

    if (!$coin->auto_ready)
        echo "<tr class='ssrow row-not-ready'>";
    else
        echo "<tr class='ssrow'>";

    echo '<td width="18">';
    echo $coin->createExplorerLink('<img width="16" src="' . $coin->image . '">');
    echo '</td>';

    $owed = (new \yii\db\Query())
                ->select(['sum(balance)'])
                ->from('accounts')
                ->Where(['coinid' => $coin->id])
                ->scalar();
    // $owed = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
    if (\YAAMP_ALLOW_EXCHANGE && $coin->balance + $coin->mint < $owed * 0.9) {
        $owed2  = Yii::$app->ConversionUtils->bitcoinvaluetoa($owed - $coin->balance);
        $symbol = $coin->getOfficialSymbol();
        $title  = "We are short of this currency ($owed2 $symbol). Please switch to another currency until we find more $symbol blocks.";
        echo "<td><b><a href=\"/site/block?id={$coin->id}\" title=\"$title\" class=\"coin-short\">$name</a></b><span class=\"text-small\"> ({$coin->algo})</span></td>";
    } else {
		echo "<td><b><a href='/site/block?id=$coin->id'>$name</a></b><span class='text-small'> ($coin->algo)</span>".
			(($coin->auto_exchange)?"":"<span class='text-small text-danger fw-bold'>(no autotrade)</span>").
			"</td>";
    }
    echo "<td align=right class='text-small'><b>$reward $coin->symbol_show</b></td>";

    $title = "POW $coin->difficulty";
    if ($coin->rpcencoding == 'POS')
        $title .= "\nPOS $coin->difficulty_pos";

    echo '<td align="right" class="text-small" data="' . $coin->difficulty . '" title="' . $title . '">' . $difficulty . '</td>';

    if (!empty($coin->errors))
        echo "<td align=right class='text-small text-danger' title='$coin->errors'>$height</td>";
    else
        echo "<td align=right class='text-small'>$height</td>";

    if (!YAAMP_ALLOW_EXCHANGE && !empty($real_ttf) && !empty($shared_real_ttf) && !empty($solo_real_ttf))
        echo '<td align="right" class="text-small" title="Shared: '.$shared_real_ttf.' at '.$pool_shared_hash_sfx.'
Solo: '.$solo_real_ttf.' at '.$pool_solo_hash_sfx.'
Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    elseif (!empty($real_ttf) && !empty($shared_real_ttf) && !empty($solo_real_ttf))
        echo '<td align="right" class="text-small" title="Shared: '.$shared_real_ttf.' at '.$pool_shared_hash_sfx.'
Solo: '.$solo_real_ttf.' at '.$pool_solo_hash_sfx.'
Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    elseif (!empty($real_ttf) && !empty($shared_real_ttf) && !empty($solo_real_ttf))
        echo '<td align="right" class="text-small" title="Shared: '.$shared_real_ttf.' at '.$pool_shared_hash_sfx.'
Solo: '.$solo_real_ttf.' at '.$pool_solo_hash_sfx.'
Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    elseif (!empty($real_ttf) && !empty($shared_real_ttf))
        echo '<td align="right" class="text-small" title="Shared: '.$shared_real_ttf.' at '.$pool_shared_hash_sfx.'
Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    elseif (!empty($real_ttf) && !empty($solo_real_ttf))
        echo '<td align="right" class="text-small" title="Solo: '.$solo_real_ttf.' at '.$pool_solo_hash_sfx.'
Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    elseif (!empty($real_ttf))
        echo '<td align="right" class="text-small" title="Full pool speed: '.$pool_ttf.' at '.$pool_total_rate.'">'.$real_ttf.'</td>';
    else
        echo '<td align="right" class="text-small" title="At current pool speed">' . $pool_ttf . '</td>';

    if ($coin->auxpow && $coin->auto_ready)
        echo "<td align=right class='text-small row-merge-mined' title='merge mined\n$network_hash_string' data='$pool_hash'>$pool_hash_sfx</td>";
    else
        echo "<td align=right class='text-small' title='Network: $network_hash_string' data='$pool_hash'>$pool_hash_sfx</td>";

    $btcmhd = Yii::$app->ConversionUtils->mbitcoinvaluetoa($btcmhd);
    echo "<td align=right class='text-small' data='$btcmhd'><b>$btcmhd</b></td>";
    echo "</tr>";
}

echo "</table>";

echo '<p class="text-small">
    &nbsp;*** estimated average time to find a block at full pool speed<br/>
    &nbsp;** approximate from the last 5 minutes submitted shares<br/>
    &nbsp;* 24h estimation from net difficulty in mBTC/MH/day (GH/day for sha & blake algos)<br>
</p>';

echo "</div></div><br>";