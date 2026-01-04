<?php

use Yii;
use app\models\Blocks;
use app\models\Coins;
use app\components\ViewHelper;

$algo = Yii::$app->session->get('yaamp-algo');

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user || $user->is_locked) return;

$count = Yii::$app->getRequest()->getQueryParam('count');
$count = $count? $count: 20;


ViewHelper::renderBoxHeader("Last $count Blocks found by $user->username");
$db_blocks = Blocks::find()
				->where(['userid' => $user->id])
				->orderBy('time desc')
				->limit($count)
				->all();

echo <<<EOT
<style type="text/css">
span.block { padding: 2px; display: inline-block; text-align: center; min-width: 75px; border-radius: 3px; }
span.block.invalid  { color: white; background-color: #d9534f; }
span.block.immature { color: white; background-color: #f0ad4e; }
span.block.exchange { color: white; background-color: #5cb85c; }
span.block.cleared  { color: white; background-color: gray; }
span.block2 { padding: 2px; display: inline-block; text-align: center; min-width: 35px; border-radius: 3px; margin-right: 5px; }
span.shared 
{
	padding: 2px;
 	display: inline-block;
	text-align: center;
	min-width: 15px;
	border-radius: 3px;
	color: white;
	background-color: #87d547;
}

span.solo
{
	padding: 2px; 
	display: inline-block; 
	text-align: center; 
	min-width: 15px; 
	border-radius: 3px; 
	color: white; 
	background-color: #48D8D8; 
}
</style>

<table class="dataGrid2">
<thead>
<tr>
<th class='coin-icon-column'></th>
<th data-sorter="text">Name</th>
<th data-sorter="numeric" >Block</th>
<th data-sorter="numeric" >Amount</th>
<th data-sorter="numeric" >Difficulty</th>
<th data-sorter="numeric" >Time</th>
<th data-sorter="numeric">Effort</th>
<th data-sorter="text" >Type</th>
<th data-sorter="text" >Status</th>
</tr>
</thead>
EOT;

foreach($db_blocks as $db_block)
{
	//if(!$db_block->coin_id) continue;
	
	$coin = Coins::find()->where(['id' => $db_block->coin_id])->one();

	if(!$coin) continue;

	$isAdmin = (!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin);
	if($db_block->category == 'stake' && !$isAdmin) continue;
	if($db_block->category == 'generated' && !$isAdmin) continue; // mature stake income

	if($db_block->category == 'immature')
		echo "<tr class='ssrow row-immature'>";
	else
		echo "<tr class='ssrow'>";

	echo '<td><img width="16" src="'.$coin->image.'"></td>';

	$flags = $db_block->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" valign="center" title="segwit"/>' : '';

	echo '<td>';
	if ($isAdmin)
		echo '<a href="/site/coin?id='.$coin->id.'"><b>'.$coin->name.'</b></a>';
	else
		echo '<b>'.$coin->name.'</b>';
	echo '&nbsp;('.$coin->algo.')'.$flags.'</td>';


	$d = Yii::$app->ConversionUtils->datetoa2($db_block->time);
	echo '<td>'.$coin->createExplorerLink($db_block->height, array('height'=>$db_block->height)).'</td>';
	echo '<td>'.$db_block->amount.'</td>';
	echo '<td>'.Yii::$app->ConversionUtils->Itoa2($db_block->difficulty, 3).'</td>';
	echo '<td data="'.$db_block->time.'"><b>'.$d.' ago</b></td>';

	if ($db_block->effort)	
		echo '<td class="row right">'.$db_block->effort.'%</td>';
	else
		echo '<td class="row right">N/A</td>';
	
	echo '<td>';
	if($db_block->solo == '1') 
		echo '<span class="solo" title="Block was found by solo miner">Solo</span>';
	else if($db_block->solo == '0')
		echo '<span class="shared" title="Block found was Shared">Shared</span>';
	else 
		echo '<span></span>';
	echo "</td>";
	
	echo '<td class="'.strtolower($db_block->category).'">';

	if($db_block->category == 'orphan')
		echo '<span class="block orphan">Orphan</span>';

	else if($db_block->category == 'immature') 
	{
		$eta = '';
		if ($coin->block_time && $coin->mature_blocks) 
		{
			$t = (int) ($coin->mature_blocks - $db_block->confirmations) * $coin->block_time;
			$eta = "ETA: ".sprintf('%dh %02dmn', ($t/3600), ($t/60)%60);
		}
		echo '<span class="block immature" title="'.$eta.'">Immature ('.$db_block->confirmations.'/'.$coin->mature_blocks.')</span>';
	}

	else if($db_block->category == 'generate')
		echo '<span class="block confirmed">Confirmed</span>';

	echo "</td>";

	echo "</tr>";
}

echo "</table>";

echo "<br></div></div><br>";
