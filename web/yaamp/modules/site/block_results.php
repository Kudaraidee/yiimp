<?php

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

$id = (int) getiparam('id');
$db_blocks = getdbolist('db_blocks', "coin_id=:id order by time desc limit 250", array(':id'=>$id));
$coin = getdbo('db_coins', $id);

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	headers: {
		0:{sorter:false},
		1:{sorter:false},
		2:{sorter:'metadata'},
		3:{sorter:'numeric'},
		4:{sorter:'currency'},
		5:{sorter:'text'},
		6:{sorter:'numeric'},
		7:{sorter:'numeric'},
		8:{sorter:'text'}
	},
	widgets: ['zebra','filter'],
	widgetOptions: {
		filter_columnFilters: false,
		filter_ignoreCase: true
	}
}");

echo <<<end
<style type="text/css">
span.block { padding: 2px; display: inline-block; text-align: center; min-width: 15px; border-radius: 3px; }
span.block.new       { color: white; background-color: #ad4ef0; }
span.block.orphan    { color: white; background-color: #d9534f; }
span.block.immature  { color: white; background-color: #f0ad4e; }
span.block.confirmed { color: white; background-color: #5cb85c; }
span.block2 { padding: 2px; display: inline-block; text-align: center; min-width: 35px; border-radius: 3px; margin-right: 5px; }
span.block2.solo { color: white;  background-color: #4BB2C5 !important; }

</style>

<thead>
<tr>
<th width="20"></th>
<th>Name</th>
<th>Time</th>
<th>Height</th>
<th>Amount</th>
<th>Type</th>
<th>Effort</th>
<th>Status</th>
<th>Difficulty</th>
<th>Share Diff</th>
<th>Finder</th>
<th>Blockhash</th>
</tr>
</thead><tbody>
end;

foreach($db_blocks as $db_block)
{
	if(!$db_block->coin_id) continue;

	if(!$coin) continue;

	if($db_block->category == 'stake' && !$this->admin) continue;
	if($db_block->category == 'generated' && !$this->admin) continue; // mature stake income

//	$remote = new WalletRPC($coin);

// 	$blockext = $remote->getblock($db_block->blockhash);
// 	$tx = $remote->gettransaction($blockext['tx'][0]);

// 	$db_block->category = $tx['details'][0]['category'];

	if($db_block->category == 'immature')
		echo "<tr style='background-color: #e0d3e8;'>";
	else
		echo "<tr class='ssrow'>";

	echo '<td><img width="16" src="'.$coin->image.'"></td>';

	$flags = $db_block->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" valign="center" title="segwit"/>' : '';

	echo '<td>';
	if ($this->admin)
		echo '<a href="/site/coin?id='.$coin->id.'"><b>'.$coin->name.'</b></a>';
	else
		echo '<b>'.$coin->name.'</b>';
	echo '&nbsp;('.$coin->symbol.')'.$flags.'</td>';

//	$db_block->confirmations = $blockext['confirmations'];
//	$db_block->save();

	$d = datetoa2($db_block->time);
	echo '<td data="'.$db_block->time.'"><b>'.$d.' ago</b></td>';
	echo '<td>'.$coin->createExplorerLink($db_block->height, array('height'=>$db_block->height)).'</td>';
	echo '<td>'.$db_block->amount.'</td>';
	
	echo '<td>';
	if($db_block->solo == '1') 
		echo '<span class="block2 solo" title="Block was found by solo miner">solo</span>';
	else echo '<span></span>'; 
	echo "</td>";

	if ($db_block->effort)	
		echo '<td>'.$db_block->effort.'%</td>';
	else
		echo '<td>N/A</td>';
	
	echo '<td class="'.strtolower($db_block->category).'">';

	if($db_block->category == 'orphan')
		echo '<span class="block orphan">Orphan</span>';

	else if($db_block->category == 'immature') {
		$eta = '';
		if ($coin->block_time && $coin->mature_blocks) {
			$t = (int) ($coin->mature_blocks - $db_block->confirmations) * $coin->block_time;
			$eta = "ETA: ".sprintf('%dh %02dmn', ($t/3600), ($t/60)%60);
		}
		echo '<span class="block immature" title="'.$eta.'">Immature ('.$db_block->confirmations.'/'.$coin->mature_blocks.')</span>';
	}

	else if($db_block->category == 'generate')
		echo '<span class="block confirmed">Confirmed</span>';

	else if($db_block->category == 'stake')
		echo "Stake ({$db_block->confirmations})";

	else if($db_block->category == 'generated')
		echo '<span class="block stake">Stake</span>';

	echo "</td>";

	echo '<td>'.round_difficulty($db_block->difficulty).'</td>';
	$diff_user = $db_block->difficulty_user;
	if (!$diff_user && $coin->algo != 'homescrypt' && substr($db_block->blockhash,0,4) == '0000')
		$diff_user = hash_to_difficulty($coin, $db_block->blockhash);
	echo '<td>'.round_difficulty($diff_user).'</td>';

	$finderFull = '';
	$finder = '';
	if (!empty($db_block->userid)) {
		$user = getdbo('db_accounts', $db_block->userid);
		if ($user) {
			$finderFull = $user->username;
			$finder = substr($finderFull, 0, 7).'…'.substr($finderFull, -6);
		}
	}

	echo '<td'.($finderFull !== '' ? ' title="'.htmlspecialchars($finderFull, ENT_QUOTES, 'UTF-8').'"' : '').'>'.htmlspecialchars($finder, ENT_QUOTES, 'UTF-8').'</td>';
	echo '<td style="font-size: .8em; font-family: monospace;">';
	echo $coin->createExplorerLink($db_block->blockhash, array('hash'=>$db_block->blockhash));
	echo "</td>";
	echo "</tr>";
}

echo "</tbody></table>";

