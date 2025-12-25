<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;

use app\components\rpc\WalletRPC;
use app\models\Coins;
use app\models\Markets;
use app\models\Bookmarks;

$id = (int) Yii::$app->getRequest()->getQueryParam('id');
$coin = Coins::findOne($id);

if (!$coin) {
	return Yii::$app->controller->goHome();
}

$PoS = ($coin->algo == 'PoS'); // or if 'stake' key is present in 'getinfo' method
$DCR = ($coin->rpcencoding == 'DCR' || $coin->getOfficialSymbol() == 'DCR');
$DGB = ($coin->rpcencoding == 'DGB' || $coin->getOfficialSymbol() == 'DGB');
$ETH = ($coin->rpcencoding == 'GETH');

$remote = new WalletRPC($coin);

$reserved1 = (new \yii\db\Query())
				->select(['SUM(balance)'])
				->from('accounts')
				->where(['coinid' => $coin->id])
				->scalar();
$reserved1 = Yii::$app->ConversionUtils->altcoinvaluetoa($reserved1);
$balance   = Yii::$app->ConversionUtils->altcoinvaluetoa($coin->balance);

$owed     = (new \yii\db\Query())
				->select(['SUM(earnings.amount) AS owed'])
				->from('earnings')
                ->leftJoin('blocks', 'earnings.blockid = blocks.id')
				->where(['coinid' => $coin->id])
				->andWhere(['!=', 'earnings.status' , 2])
				->scalar();
$owed_btc = Yii::$app->ConversionUtils->bitcoinvaluetoa($owed * $coin->price);
$owed     = Yii::$app->ConversionUtils->altcoinvaluetoa($owed);

$symbol = $coin->symbol;
if (!empty($coin->symbol2))
    $symbol = $coin->symbol2;

echo "<br/>";
if (YAAMP_ALLOW_EXCHANGE) {
    $subquery = (new \yii\db\Query())
				->select(['id'])
				->from('accounts')
				->where(['coinid' => $coin->id]);
    $tmp_dbvalue = (new \yii\db\Query())
				->select(['SUM(amount*price)'])
				->from('earnings')
                ->Where(['in', 'id', $subquery])
				->andWhere(['!=', 'status' , 2])
				->scalar();
    $reserved2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($tmp_dbvalue);
    echo "Earnings $reserved2 BTC, ";
}
echo "Balance (db) $balance $symbol";
echo ", Owned " . Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->available) . " $symbol";
echo ", Owed " . Html::a($owed, ['earning', 'coinid' => $coin->id]) . " $symbol ($owed_btc BTC)";
echo ", " . Html::a($reserved1, ['payments', 'coinid' => $coin->id]) . " $symbol cleared<br/><br/>";

//////////////////////////////////////////////////////////////////////////////////////

$bookmarkAdd = Html::a('+', ['bookmark-add', 'id' => $coin->id], array(
    'title' => 'Add a bookmark'
));

echo <<<end
<div id="markets">
<table class="dataGrid">
<thead><tr>
<th width="100">Market</th>
<th width="100">Bid</th>
<th width="100">Ask</th>
<th width="500">Deposit</th>
<th width="100">Balance</th>
<th width="100">Locked</th>
<th width="100">Sent</th>
<th width="100">Traded</th>
<th width="40">Late</th>
<th align="center" width="500">Message</th>
<th align="right" width="100">{$bookmarkAdd} Actions</th>
</tr></thead><tbody>
end;

$list = Markets::find()
            ->where(['coinid' => $coin->id, 'deleted' => 0])
            ->orderBy('disabled, priority DESC, price DESC')
            ->all();
//$bestmarket = getBestMarket($coin);
foreach ($list as $market) {
    $marketurl = '#';
    $price     = Yii::$app->ConversionUtils->bitcoinvaluetoa($market->price);
    $price2    = Yii::$app->ConversionUtils->bitcoinvaluetoa($market->price2);

    $marketurl = Yii::$app->YiimpUtils->getMarketUrl($coin, $market->name);

    $rowclass = 'ssrow';
//    if ($bestmarket && $market->id == $bestmarket->id)
//        $rowclass .= ' bestmarket';
    if ($market->disabled)
        $rowclass .= ' disabled';

    echo '<tr class="' . $rowclass . '">';

    echo '<td><b><a href="' . $marketurl . '" target=_blank>';
    echo $market->name;
    echo '</a></b></td>';

    $updated = "last updated: " . strip_tags(Yii::$app->ConversionUtils->datetoa2($market->pricetime));
    echo '<td title="' . $updated . '">' . $price . '</td>';
    echo '<td title="' . $updated . '">' . $price2 . '</td>';

    echo '<td class="text-ellipsis">';
    if (!empty($market->deposit_address)) {
        $name = \yii\helpers\Json::encode($market->name);
        $addr = \yii\helpers\Json::encode($market->deposit_address);
        echo Html::a(YAAMP_ALLOW_EXCHANGE ? "sell" : "send", "javascript:;", array(
            'onclick' => "return showSellAmountDialog($name, $addr, {$market->id});"
        ));
        echo ' ' . $market->deposit_address;
    }
    echo ' <a href="/market/update?id=' . $market->id . '">edit</a>';
    echo '</td>';

    $updated = "last updated: " . strip_tags(Yii::$app->ConversionUtils->datetoa2($market->balancetime));
    $balance = $market->balance > 0 ? Yii::$app->ConversionUtils->bitcoinvaluetoa($market->balance) : '';
    echo '<td title="' . $updated . '">' . $balance . '</td>';

    $ontrade = $market->ontrade > 0 ? Yii::$app->ConversionUtils->bitcoinvaluetoa($market->ontrade) : '';
    echo '<td title="' . $updated . '">' . $ontrade . '</td>';

    $sent   = Yii::$app->ConversionUtils->datetoa2($market->lastsent);
    $traded = Yii::$app->ConversionUtils->datetoa2($market->lasttraded);
    $late   = $market->lastsent > $market->lasttraded && $market->lasttraded ? 'late' : '';

    echo '<td>' . (empty($sent) ? "" : "$sent ago") . '</td>';
    echo '<td>' . (empty($traded) ? "" : "$traded ago") . '</td>';
    echo '<td>' . $late . '</td>';

    echo '<td align="center">' . $market->message . '</td>';

    echo '<td align="right">';
    if ($market->disabled)
        echo '<a title="Enable this market" href="/market/enable?id=' . $market->id . '&en=1">enable</a>';
    else
        echo '<a title="Disable this market" href="/market/enable?id=' . $market->id . '&en=0">disable</a>';
    echo '&nbsp;<a class="red" title="Remove this market" href="/market/delete?id=' . $market->id . '">delete</a>';
    echo '</td>';

    echo "</tr>";
}

// in the list after the markets, made for quick send between wallets
$list = Bookmarks::find()->where(['idcoin' => $coin->id])->orderBy('lastused DESC')->all();

foreach ($list as $bookmark) {
    echo '<tr class="ssrow bookmark">';

    echo '<td><b>' . $bookmark->label . '<b></td>';
    echo '<td></td>';
    echo '<td></td>';

    echo '<td>';
    if (!empty($bookmark->address)) {
        $name = \yii\helpers\Json::encode($bookmark->label);
        $addr = \yii\helpers\Json::encode($bookmark->address);
        echo Html::a("send", "javascript:;", array(
            'onclick' => "return showSellAmountDialog($name, $addr, 0, {$bookmark->id});"
        ));
        echo ' ' . $bookmark->address;
    }
    echo ' ' . Html::a('edit', ['bookmark-edit', 'id' => $bookmark->id]);
    echo '</td>';

    echo '<td></td>';
    echo '<td></td>';

    $sent = Yii::$app->ConversionUtils->datetoa2($bookmark->lastused);
    echo '<td>' . (empty($sent) ? "" : "$sent ago") . '</td>';
    echo '<td></td>';
    echo '<td></td>';

    echo '<td align="center"></td>';

    echo '<td align="right">';
    echo Html::a('delete', ['bookmark-delete', 'id' => $bookmark->id], ['class' => 'red']);
    echo '</td>';

    echo "</tr>";
}

echo "</tbody></table></div>";

//////////////////////////////////////////////////////////////////////////////////////

$info = $remote->getinfo();
if (!empty($info)) {
    $stake = Yii::$app->ConversionUtils->arraySafeVal($info, 'stake', '');
    if ($stake !== '')
        $PoS = true;
}

if ($DCR) {
    // Decred Tickets
    $stake    = 0;
    $balances = $remote->getbalance('*', 0);
    if (isset($balances["balances"])) {
        foreach ($balances["balances"] as $accb) {
            $stake += Yii::$app->ConversionUtils->arraySafeVal($accb, 'lockedbytickets', 0);
        }
    }
    $stakeinfo   = $remote->getstakeinfo();
    $ticketprice = Yii::$app->ConversionUtils->arraySafeVal($stakeinfo, 'difficulty');
    $tickets     = Yii::$app->ConversionUtils->arraySafeVal($stakeinfo, 'live', 0);
    $tickets += Yii::$app->ConversionUtils->arraySafeVal($stakeinfo, 'immature', 0);
}

echo '<table id="infos" class="dataGrid">';
echo '<thead><tr>';
echo '<th width="30"></th>';
echo '<th width="30"></th>';
echo "<th>Name</th>";
echo "<th>Symbol</th>";
echo "<th>Algo</th>";
echo "<th>Difficulty</th>";
echo "<th>Blocks</th>";
echo "<th>Balance</th>";
echo "<th>BTC</th>";
if ($PoS || $DCR)
    echo "<th>Stake</th>";
if ($DCR)
    echo "<th>Ticket price</th>";
echo "<th>Conns</th>";

echo "<th>Price</th>";
echo "<th>Reward</th>";
echo "<th>Index *</th>";

echo "</tr>";
echo "</thead><tbody>";

echo "<tr class='ssrow'>";
echo "<td><img src='$coin->image' width=24></td>";

if ($coin->enable)
    echo "<td>[ + ]</td>";
else
    echo "<td>[&nbsp;&nbsp;&nbsp;&nbsp;]</td>";

echo '<td><b>' . Html::a($coin->name, ['/site/block', 'id' => $coin->id]) . '</b></td>';
echo '<td width="60"><b>' . $coin->symbol . '</b></td>';
echo '<td>' . Html::a($coin->algo, ['/site/gomining', 'algo' => $coin->algo]) . '</td>';

if (!$info || !isset($info['balance']) ) {
    // echo '<td colspan="5">ERROR ' . $remote->error . '</td>';
    echo '<td>' . Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price) . '</td>';
    echo '<td colspan="2">';
    echo "</tr></tbody></table><br/>";
    return;
}

$errors      = isset($info['errors']) ? $info['errors'] : '';
$balance     = isset($info['balance']) ? $info['balance'] : '';
$txfee       = isset($info['paytxfee']) ? $info['paytxfee'] : '';
$connections = isset($info['connections']) ? Html::a($info['connections'], ['coin-peers', 'id' => $coin->id]) : '';
$blocks      = isset($info['blocks']) ? $info['blocks'] : '';
$zbalance    = null;

if ((!is_null($coin->wallet_zaddress)) && (trim($coin->wallet_zaddress) != '')) {
    $zbalance = $remote->z_getbalance(trim($coin->wallet_zaddress));
}

echo '<td>' . Yii::$app->ConversionUtils->round_difficulty($coin->difficulty) . '</td>';
if (!empty($errors))
    echo '<td class="red" title="' . $errors . '">' . $blocks . '</td>';
else
    echo "<td>$blocks</td>";

echo '<td>' . Yii::$app->ConversionUtils->altcoinvaluetoa($balance);

$btc = Yii::$app->ConversionUtils->bitcoinvaluetoa($balance * $coin->price);
if ($zbalance) {
    echo '<br>('.Yii::$app->ConversionUtils->bitcoinvaluetoa($zbalance).')';
}
echo '</td>';

echo "<td>$btc</td>";
if ($PoS)
    echo '<td>' . $stake . '</td>';
else if ($DCR) {
    echo '<td>' . Html::a("$stake ($tickets)", ['coin-tickets', 'id' => $coin->id]) . '</td>';
    echo '<td>' . Html::a($ticketprice, "https://dcrstats.com/", array(
        'target' => '_blank'
    )) . '</td>';
}
echo "<td>$connections</td>";

echo '<td>' . Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price) . '</td>';
echo '<td>' . $coin->reward . '</td>';

$index = '';
if ($coin->difficulty)
    $index = round($coin->reward * $coin->price / $coin->difficulty * 10000, 3);
echo '<td>' . $index . '</td>';

echo '</tr>';
echo '</tbody></table>';

//////////////////////////////////////////////////////////////////////////////////////

// last week
$list_since = Yii::$app->ConversionUtils->arraySafeVal($_GET, 'since', time() - (7 * 24 * 3600));

$maxrows = (int) Yii::$app->ConversionUtils->arraySafeVal($_GET, 'rows', 500);
$maxrows = max($maxrows, 250);
$maxrows = min($maxrows, 2500);

echo <<<end
<div id="transactions">
<table class="dataGrid">
<thead class="">
<tr>
<th>Time</th>
<th>Category</th>
<th>Amount</th>
<th>Height</th>
<th>Difficulty</th>
<th>Confirm</th>
<th>Address</th>
<th>Tx</th>
</tr>
</thead><tbody>
end;

$account = '';
if ($DCR || $DGB)
    $account = '*';
else if ($ETH)
    $account = $coin->master_wallet;
	
else if ($coin->symbol == "BTC") $account = '*'; // should work for all coins now
	
	
$txs = $remote->listtransactions($account, $maxrows);


// Coins with disabled accounting will not show any tx. $account should be "*"

if (empty($txs)) {
        $account = '*';
        $txs = $remote->listtransactions($account, $maxrows);
}
	
	
$txs = $remote->listtransactions($account, $maxrows);

if (empty($txs)) {
    if (!empty($remote->error)) {
        echo "<b>RPC Error: {$remote->error}</b><p/>";
    }
    // retry...
    $txs = $remote->listtransactions($account, 200);
}

$txs_array = array();
$lastday   = '';

if (!empty($txs)) {
    // to hide truncated days sums
    $tx = reset($txs);

    if (count($txs) == $maxrows && isset($tx['time']))
        $lastday = strftime('%F', $tx['time']);

    if (!empty($txs))
        foreach ($txs as $tx) {
            if (Yii::$app->ConversionUtils->arraySafeVal($tx, 'time', $list_since + 1) > $list_since)
                $txs_array[] = $tx;
        }

    krsort($txs_array);
}

// filter useless decred spent transactions
if ($DCR) {

    // normal value since 0.1.5
    $amountin_mul = $info['version'] >= 10500 ? 1.0 : 0.00000001;

    $prev_tx = array();
    $lastday = '';
    foreach ($txs_array as $key => $tx) {
        // required after a wallet resynch/import
        $txs_array[$key]['time'] = min($tx['timereceived'], Yii::$app->ConversionUtils->arraySafeVal($tx, 'blocktime', $tx['time']));

        $prev_txid = Yii::$app->ConversionUtils->arraySafeVal($prev_tx, "txid");
        $category  = $tx['category'];
        if (Yii::$app->ConversionUtils->arraySafeVal($tx, 'txtype') == 'ticket') {
            $txs_array[$key]['category'] = 'ticket';
            if ($category != 'receive' || $prev_txid === Yii::$app->ConversionUtils->arraySafeVal($tx, "txid"))
                unset($txs_array[$key]);
            else
                $txs_array[$key]['amount'] = 0 - $tx['amount'];
            continue;
        }
        if ($category == 'send' && Yii::$app->ConversionUtils->arraySafeVal($tx, 'generated')) {
            $txs_array[$key]['category'] = 'spent';
        } else if ($category == 'send' && $tx['amount'] == -0) {
            // vote accepted (listed twice ? in listtransactions)
            if ($tx['vout'] > 0)
                $category = 'spent';
            else if (Yii::$app->ConversionUtils->arraySafeVal($tx, "confirmations") >= 256)
                $category = 'receive';
            else
                $category = 'immature';

            if ($category == 'spent' && Yii::$app->ConversionUtils->arraySafeVal($tx, 'txtype') == 'vote') {
                // todo: ticket unlocked amount
                $category = 'unlock';
            }

            $txs_array[$key]['category'] = $category;

            if ($tx['vout'] == 0) {
                // won ticket value
                $t = $remote->getrawtransaction($tx['txid'], 1);
                if ($t && isset($t['vin'][0])) {
                    $txs_array[$key]['amount'] = $t['vin'][0]['amountin'] * $amountin_mul;
                }
            }
            if ($category == 'unlock') {
                // unlocked amount
                $t = $remote->getrawtransaction($tx['txid'], 1);
                if ($t && isset($t['vin'][1])) {
                    $txs_array[$key]['amount'] = $t['vin'][1]['amountin'] * $amountin_mul;
                }
            }
        } else if ($category == 'send' && $prev_txid === Yii::$app->ConversionUtils->arraySafeVal($tx, "txid")) {
            // if txid is the same as the last income... it's not a real "send"
            if ($prev_tx['amount'] == 0 - $tx['amount'])
                $txs_array[$key]['category'] = 'spent';
        } else if ($category == 'receive') {
            $prev_tx = $tx;
        }
        // for truncated day sums
        if ($lastday == '' && count($txs) == $maxrows)
            $lastday = strftime('%F', $tx['time']);
    }
    if ($info['version'] < 1010200)
        ksort($txs_array); // was in reversed order
}

$rows = 0;
foreach ($txs_array as $tx) {
    if (!isset($tx['amount'])) {
        if (!isset($tx['reward'])) continue;

        $tx['amount'] = $tx['reward'];
    }
    
    $category = Yii::$app->ConversionUtils->arraySafeVal($tx, 'category');
    if ($category == 'spent')
        continue;

    $block = null;
    if (isset($tx['blockhash']))
        $block = $remote->getblock($tx['blockhash']);

    echo '<tr class="ssrow ' . $category . '">';

    if (!isset($tx['time'])) {
        // martian wallets
        echo '<td colspan="8">' . json_encode($tx) . '</td>';
        continue;
    }

    $d = datetoa2($tx['time']);
    echo '<td><b>' . $d . '</b></td>';

    $eta = '';
    if ($category == 'immature') {
        if ($coin->block_time && $coin->mature_blocks) {
            $t   = (int) ($coin->mature_blocks - Yii::$app->ConversionUtils->arraySafeVal($tx, 'confirmations', 0)) * $coin->block_time;
            $eta = "ETA: " . sprintf('%dh %02dmn', ($t / 3600), ($t / 60) % 60);
        }
    }
    echo '<td title="' . $eta . '">' . $category . '</td>';
    echo '<td>' . $tx['amount'] . '</td>';

    if ($block) {
        echo '<td>' . $block['height'] . '</td><td>' . Yii::$app->ConversionUtils->round_difficulty($block['difficulty']) . '</td>';
    } else
        echo '<td></td><td></td>';

    echo '<td>' . Yii::$app->ConversionUtils->arraySafeVal($tx, 'confirmations') . '</td>';

    echo '<td width="280">';
    if (isset($tx['address'])) {
        $address = $tx['address'];
        $exists  = dboscalar("SELECT count(*) AS nb FROM accounts WHERE username=:address", array(
            ':address' => $address
        ));
        if ($exists)
            echo Html::a($address, ['/site/index', 'address' => $address]);
        else
            echo $address . '<br>';
    }
    echo '</td>';

    echo '<td>';
    if (!empty($block)) {
        $txid  = Yii::$app->ConversionUtils->arraySafeVal($tx, 'txid');
        $label = substr($txid, 0, 7);
        echo $coin->createExplorerLink($label, array(
            'txid' => $txid
        ), array(
            'target' => '_blank'
        ));
    }
    echo '</td>';

    echo '</tr>';

    $rows++;
    if ($rows >= $maxrows)
        break;
}

echo '</tbody></table>';

$url     = \yii\helpers\Url::to(['coinwallet', 'id' => $coin->id, 'since' => (time() - 31 * 24 * 3600), 'rows' => ($maxrows * 2)]);
$moreurl = Html::a('Click here to show more transactions...', $url);

echo '<div class="loadfooter" class="mt-4px">' . $moreurl . '</div>';
echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////

echo <<<end
<div id="sums">
<table class="dataGrid">
<thead class="">
<tr>
<th>Day</th>
<th>Category</th>
<th>Sum</th>
<th>BTC</th>
</tr>
</thead><tbody>
end;

$sums = array();
foreach ($txs_array as $tx) {
    if (!isset($tx['time'])) continue;
    if (!isset($tx['amount'])) continue;
    
    $day = strftime('%F', $tx['time']); // YYYY-MM-DD
    if ($day == $lastday)
        break; // do not show truncated days

    $category = $tx['category'];
    if ($category == 'spent')
        continue;

    $key        = $day . ' ' . $category;
    $sums[$key] = Yii::$app->ConversionUtils->arraySafeVal($sums, $key) + $tx['amount'];
}

foreach ($sums as $key => $amount) {
    $keys     = explode(' ', $key);
    $day      = substr($keys[0], 5); // remove year
    $category = $keys[1];
    echo '<tr class="ssrow ' . $category . '">';
    echo '<td><b>' . $day . '</b></td>';

    echo '<td>' . $category . '</td>';
    echo '<td>' . $amount . '</td>';
    echo '<td>' . Yii::$app->ConversionUtils->bitcoinvaluetoa($coin->price * $amount) . '</td>';

    echo '</tr>';
}

if (empty($sums)) {
    echo '<tr class="ssrow">';
    echo '<td colspan="4"><i>No activity during the last 7 days</i></td>';
    echo '</tr>';
}

echo '</tbody></table></div>';
