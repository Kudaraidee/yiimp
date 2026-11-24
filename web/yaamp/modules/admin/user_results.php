<?php

/////////////////////////////////////////////////////////////////////////////////////////////////

$symbol = getparam('symbol');
$coin = null;
$is_all = ($symbol == 'all');

if($symbol == 'all')
	$users = getdbolist('db_accounts', "balance>.001 OR id IN (SELECT DISTINCT userid FROM workers) ORDER BY balance DESC");
else
{
	$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
	if(!$coin) return;

	$users = getdbolist('db_accounts', "coinid={$coin->id} AND (balance>.001 OR id IN (SELECT DISTINCT userid FROM workers)) ORDER BY balance DESC");
}

// Per-row amounts remain in their own coin. Only the "-all-" footer needs
// prices to convert otherwise meaningless mixed units into BTC equivalents.
$price_by_coin = array();
if ($is_all) {
	foreach ($users as $u) {
		$cid = (int) $u->coinid;
		if ($cid > 0 && !isset($price_by_coin[$cid])) {
			$u_coin = getdbosql('db_coins', "id=:id", array(':id'=>$cid));
			$price_by_coin[$cid] = $u_coin ? (float) $u_coin->price : 0.0;
		}
	}
}

echo <<<end
<div align="right" style="margin-top: -20px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
.red { color: darkred; }
tr.ssrow.filtered { display: none; }
</style>
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		4: function(node, table, cellIndex) { return $(node).attr('data'); },
		6: function(node, table, cellIndex) { return $(node).attr('data'); },
	},
	widgets: ['zebra','filter','Storage','saveSort'],
	widgetOptions: {
		saveSort: true,
		filter_saveFilters: false,
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="numeric">UID</th>
<th data-sorter="false">&nbsp;</th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="numeric">Last</th>
<th data-sorter="numeric" align="right">Workers</th>
<th data-sorter="numeric" align="right">Hashrate</th>
<th data-sorter="numeric" align="right">Bad</th>
<th data-sorter="numeric" align="right">Blocks</th>
<th data-sorter="numeric" align="right">Diff/Paid</th>
<th data-sorter="currency" align="right">Balance</th>
<th data-sorter="currency" align="right">Total Paid</th>
<th data-sorter="false" align="right" class="actions" width="150">Actions</th>
</tr>
</thead><tbody>
end;

$total_balance = 0.0;
$total_paid = 0.0;
$raw_by_coin = array();

foreach($users as $user)
{
	$target = yaamp_hashrate_constant();
	$interval = yaamp_hashrate_step(); // 300 seconds
	$delay = time()-$interval;

	$user_rate = dboscalar("SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=".$user->id);
	$user_bad = yaamp_user_rate_bad($user->id);// dboscalar("SELECT (count(id) * $target / $interval / 1000) FROM shares WHERE valid=0 AND time>$delay AND userid=".$user->id);
	$pct_bad = $user_rate? round($user_bad*100/$user_rate, 3): 0;

	$balance = bitcoinvaluetoa($user->balance);
	$paid_raw = (float) dboscalar("SELECT sum(amount) FROM payouts WHERE account_id=".$user->id);
	$d = datetoa2($user->last_earning);

	$miner_count = getdbocount('db_workers', "userid=".$user->id);
	$block_count = getdbocount('db_blocks', "userid=".$user->id);
	$block_diff = ($paid_raw && $block_count) ? round(dboscalar("SELECT sum(difficulty) FROM blocks WHERE userid=".$user->id)/$paid_raw, 3): '?';

	$paid = bitcoinvaluetoa($paid_raw);

	$user_bad = Itoa2($user_bad);

	$coinimg = ''; $coinlink = '';
	$imgopt = array('width'=>'16');
	if ($coin && $user->coinid == $coin->id) {
		$coinimg = CHtml::image($coin->image, $coin->symbol, $imgopt);
		$coinlink = CHtml::link($coin->symbol, '/admin/coin?id='.$coin->id);
	} else if ($user->coinid > 0) {
		$user_coin = getdbosql('db_coins', "id=:id", array(':id'=>$user->coinid));
		if ($user_coin) {
			$coinimg = CHtml::image($user_coin->image, $user_coin->symbol, $imgopt);
			$coinlink = CHtml::link($user_coin->symbol, '/admin/coin?id='.$user_coin->id);
		}
	}

	echo '<tr class="ssrow">';
	echo '<td width="24">'.$user->id.'</td>';
	echo '<td width="16">'.$coinimg.'</td>';
	echo '<td width="48"><b>'.$coinlink.'</b></td>';
	echo '<td><a href="/?address='.$user->username.'"><b>'.$user->username.'</b></a></td>';
	echo '<td data="'.$user->last_earning.'">'.$d.'</td>';
	echo '<td align=right>'.$miner_count.'</td>';

	echo '<td width="32" data="'.(0+$user_rate).'" align="right">'.($user_rate ? Itoa2($user_rate) : '').'</td>';
	echo '<td width="32" align="right">';
	if ($pct_bad) echo round($pct_bad,1)."&nbsp;%";
	echo '</td>';

	echo '<td align="right">'.$block_count.'</td>';
	echo '<td align="right">'.($user_rate ? $block_diff : '').'</td>';
	echo '<td align="right">'.$balance.'</td>';
	echo '<td align="right">'.$paid.'</td>';

	echo '<td class="actions" align="right">';

	if ($user->logtraffic)
		echo '<a href="/admin/loguser?id='.$user->id.'&en=0">unwatch</a> ';
	else
		echo '<a href="/admin/loguser?id='.$user->id.'&en=1">watch</a> ';

	if ($user->is_locked)
		echo '<a href="/admin/unblockuser?wallet='.$user->username.'">unblock</a> ';
	else
		echo '<a href="/admin/blockuser?wallet='.$user->username.'">block</a> ';

	echo '<a href="/admin/banuser?id='.$user->id.'"><span class="red">BAN</span></a>';

	echo '</td>';

	echo '</tr>';

	$user_balance = (float) $user->balance;
	$cid = (int) $user->coinid;
	if (!isset($raw_by_coin[$cid])) $raw_by_coin[$cid] = array('balance'=>0.0, 'paid'=>0.0);

	$total_balance += $user_balance;
	$total_paid += $paid_raw;
	$raw_by_coin[$cid]['balance'] += $user_balance;
	$raw_by_coin[$cid]['paid'] += $paid_raw;
}

echo "</tbody>";

// ── Totals ────────────────────────────────────────────────────────────────────
// Single-coin views sum in that coin's own unit. "-all-" mixes units, so show
// current-price BTC equivalents there instead of adding unrelated numbers.
$colspan = 7;
$user_count = count($users);

$balance_btc = 0.0;
$paid_btc = 0.0;
$unpriced = 0;
if ($is_all) {
	foreach ($raw_by_coin as $cid => $amounts) {
		$price = (float) ($price_by_coin[$cid] ?? 0);
		if ($price > 0) {
			$balance_btc += $amounts['balance'] * $price;
			$paid_btc += $amounts['paid'] * $price;
		} elseif ($amounts['balance'] > 0 || $amounts['paid'] > 0) {
			$unpriced++;
		}
	}
}

$unit_label = $is_all ? '' : (string) ($coin ? $coin->symbol : $symbol);
$totals_note = '';
if ($is_all) {
	$totals_note = 'est. BTC @ current price';
	if ($unpriced > 0) $totals_note .= ' · '.$unpriced.' coin(s) without price excluded';
}

$balance_display = $is_all
	? '₿ '.bitcoinvaluetoa($balance_btc)
	: bitcoinvaluetoa($total_balance).' '.$unit_label;
$paid_display = $is_all
	? '₿ '.bitcoinvaluetoa($paid_btc)
	: bitcoinvaluetoa($total_paid).' '.$unit_label;
$note_html = ($is_all && $totals_note !== '')
	? ' <small style="font-weight:normal;color:#888;">'.CHtml::encode($totals_note).'</small>'
	: '';

echo '<tr class="ssfoot" style="border-top: 2px solid #eee;">';
echo '<th colspan=3><b>Users Total ('.$user_count.')</b>'.$note_html.'</th>';
for ($c=0; $c<$colspan; $c++) echo '<th></th>';
echo '<th align="right"><b>'.CHtml::encode($balance_display).'</b></th>';
echo '<th align="right"><b>'.CHtml::encode($paid_display).'</b></th>';
echo '<th></th>';
echo '</tr>';

if($coin)
{
	$unit_suffix = ' '.CHtml::encode($coin->symbol);
	$balance = bitcoinvaluetoa($coin->balance);
	$profit = bitcoinvaluetoa($coin->balance - $total_balance);

	echo '<tr class="ssfoot" style="border-top: 2px solid #eee;">';
	echo '<th colspan="3"><b>Wallet Balance</b></th>';
	for ($c=0; $c<$colspan; $c++) echo '<th></th>';
	echo '<th align="right"><b>'.$balance.$unit_suffix.'</b></th>';
	echo '<th colspan="2"></th>';
	echo '</tr>';

	echo '<tr class="ssfoot" style="border-top: 2px solid #eee;">';
	echo '<th colspan="3"><b>Wallet Profit</b></th>';
	for ($c=0; $c<$colspan; $c++) echo '<th></th>';
	echo '<th align="right"><b>'.$profit.$unit_suffix.'</b></th>';
	echo '<th colspan="2"></th>';
	echo '</tr>';
}

echo "</table>";
