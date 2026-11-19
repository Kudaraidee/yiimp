<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Coins;
use app\models\Workers;

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if(!$user) return;

$userid = intval($user->id);
$coinid = intval($user->coinid);
$coin = null;
if ($coinid) {
	$coin = Coins::find()
				->where([ 'id' => $coinid ])
				->one();
}

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Miners: {$user->username}</div>";
echo "<div class='main-left-inner'>";

echo '<table class="dataGrid2">';
echo "<thead>";
echo "<tr>";
echo "<th align=left>Summary</th>";
echo "<th align=right width=80>Workers</th>";
echo "<th align=right width=80>Hashrate*</th>";
echo "<th align=right width=60>TTF</th>";
echo "</tr>";
echo "</thead>";

foreach(Yii::$app->YiimpUtils->get_algos() as $algo)
{
	if (!YIIMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;

	$minercount = Workers::find()
			->where(['userid' => $userid , 'algo' => $algo])
			->count();
	if (!$minercount) continue;

	$user_pool_rate = Yii::$app->YiimpUtils->user_rate($userid, $algo);
	$pool_hash = Yii::$app->YiimpUtils->pool_rate_pow($algo);
	$user_ttf = 0;

	if ($pool_hash != 0) {
		$user_ttf  = ($user_pool_rate && $coin) ? $coin->difficulty * 0x100000000 / $pool_hash : 0;
	}
	$user_ttf  = $user_ttf ? Yii::$app->ConversionUtils->sectoa2($user_ttf) : '-';

	$user_pool_rate = $user_pool_rate? Yii::$app->ConversionUtils->Itoa2($user_pool_rate).'h/s': '-';
	
	echo '<tr class="ssrow">';
	echo '<td><b>'.$algo.'</b></td>';
	echo '<td align="right" >'.$minercount.'</td>';
	echo '<td align="right" width="100"><b>'.$user_pool_rate.'</b></td>';
	echo '<td align="right" width="100">'.$user_ttf.'</td>';
	echo '</tr>';
}

echo "</table>";
////////////////////////////////////////////////////////////////////////////////

$minercount = Workers::find()
			->where(['userid' => $user->id])
			->orderBy('password')
			->all();
if(count($minercount))
{
	echo "<br>";
	echo '<table class="dataGrid2">';
	echo "<thead>";
	echo "<tr>";
	echo "<th align=left>Mode</th>";
	echo "<th align=right width=80>Workers</th>";
	echo "<th align=right>Shares</th>";
	echo "<th align=right width=80>Hashrate*</th>";
	echo "<th align=right width=60>TTF</th>";
	echo "</tr>";
	echo "</thead>";

	foreach(Yii::$app->YiimpUtils->get_algos() as $algo)
	{
		if (!YIIMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;

		$user_shared_rate = Yii::$app->YiimpUtils->user_shared_rate($userid, $algo);
		$user_solo_rate = Yii::$app->YiimpUtils->user_solo_rate($userid, $algo);
		
		$pool_shared_hash = Yii::$app->YiimpUtils->pool_shared_rate($algo);
		$user_shared_ttf  = $user_shared_rate ? $coin->difficulty * 0x100000000 / $pool_shared_hash : 0;
		$user_shared_ttf  = $user_shared_ttf ? Yii::$app->ConversionUtils->sectoa2($user_shared_ttf) : '';

		$pool_solo_hash = Yii::$app->YiimpUtils->pool_solo_rate($algo);
		$user_solo_ttf  = $user_solo_rate ? $coin->difficulty * 0x100000000 / $pool_solo_hash : 0;
		$user_solo_ttf  = $user_solo_ttf ? Yii::$app->ConversionUtils->sectoa2($user_solo_ttf) : '';


		$user_shared_rate = $user_shared_rate? Yii::$app->ConversionUtils->Itoa2($user_shared_rate).'h/s': '-';
		$user_solo_rate = $user_solo_rate? Yii::$app->ConversionUtils->Itoa2($user_solo_rate).'h/s': '-';
	
		$shared_minercount = Workers::find()
								->where(['userid'=>$userid,'algo'=>$algo])
								->andWhere(['not like','password','m=solo'])
								->count();
		$solo_minercount = Workers::find()
								->where(['userid'=>$userid,'algo'=>$algo])
								->andWhere(['like','password','m=solo'])
								->count();

		if ($shared_minercount)
		{
			if (YIIMP_ALLOW_EXCHANGE || !$user->coinid) 
			{

				$user_shared_shares = Yii::$app->cache->get("wallet_user_shared_shares-$userid-$algo");
				if (!$user_shared_shares) {
					$user_shared_shares = (new \yii\db\Query())
							->select(['SUM(difficulty)'])
							->from('shares')
							->where([ 'valid' => 1 , 'userid' => $userid , 'algo'=> $algo , 'solo' => 0])
							->scalar();
					Yii::$app->cache->set("wallet_user_shared_shares-$userid-$algo", $user_shared_shares);
				}

				if(!$user_shared_shares && !$shared_minercount) continue;

				$total_shared_shares = Yii::$app->cache->get("wallet_total_shared_shares-$algo");
				if (!$total_shared_shares) {
					$total_shared_shares = (new \yii\db\Query())
							->select(['SUM(difficulty)'])
							->from('shares')
							->where([ 'valid' => 1 , 'algo'=> $algo , 'solo' => 0])
							->scalar();
					Yii::$app->cache->set("wallet_total_shared_shares-$algo", $total_shared_shares);
				}

			} 
			else 
			{
				$user_shared_shares = Yii::$app->cache->get("wallet_user_shared_shares-$algo-$coinid-$userid");
				if (!$user_shared_shares) {
					$user_shared_shares = (new \yii\db\Query())
							->select(['SUM(difficulty)'])
							->from('shares')
							->where([ 'valid' => 1 , 'userid' => $userid , 'coinid' => $coinid , 'algo'=> $algo , 'solo' => 0])
							->scalar();
					Yii::$app->cache->set("wallet_user_shared_shares-$algo-$coinid-$userid", $user_shared_shares);
				}
				if(!$user_shared_shares) continue;

				$total_shared_shares = Yii::$app->cache->get("wallet_coin_shared_shares-$coinid");
				if (!$total_shared_shares) {
					$total_shared_shares = (new \yii\db\Query())
							->select(['SUM(difficulty)'])
							->from('shares')
							->where([ 'valid' => 1 , 'coinid' => $coinid , 'algo'=> $algo , 'solo' => 0])
							->scalar();
					Yii::$app->cache->set("wallet_coin_shared_shares-$coinid", $total_shared_shares);
				}
			}
	
			if(!$total_shared_shares) continue;
			$percent_shared_shares = round($user_shared_shares * 100 / $total_shared_shares, 4);
			$percent_shared_shares = $percent_shared_shares? Yii::$app->ConversionUtils->Itoa2($percent_shared_shares).'%': '-';
		}

		if ($shared_minercount)
		{
			echo '<tr class="ssrow">';
			echo '<td><b>Shared</b></td>';
			echo '<td align="right" >'.$shared_minercount.'</td>';
			echo '<td align="right" width="100">'.$percent_shared_shares.'</td>';
			echo '<td align="right" width="100"><b>'.$user_shared_rate.'</b></td>';
			echo '<td align="right" width="100">'.$user_shared_ttf.'</td>';
			echo '</tr>';
		}
		
		if ($solo_minercount)
		{
			echo '<tr class="ssrow">';
			echo '<td><b>Solo</b></td>';
			echo '<td align="right" >'.$solo_minercount.'</td>';
		 	echo '<td align="right" width="100">-</td>';
			echo '<td align="right" width="100"><b>'.$user_solo_rate.'</b></td>';
			echo '<td align="right" width="100">'.$user_solo_ttf.'</td>';
			echo '</tr>';
		}
	}
	echo "</table>"; 
}


////////////////////////////////////////////////////////////////////////////////

$workers = Workers::find()->where(['userid'=>$user->id])->orderBy('password')->all();
if(count($workers))
{
	echo "<br>";
	echo "<table  class='dataGrid2'>";
	echo "<thead>";
	echo "<tr>";
	echo "<th align=left>Details</th>";
	if (!is_null(Yii::$app->user->identity)) echo "<th>IP</th>";
	echo "<th align=left>Password</th>";
	echo "<th align=left>Algo</th>";
	echo "<th align=right>Diff</th>";
	echo "<th align=right title='extranonce.subscribe'>ES**</th>";
	echo "<th align=right width=80>Hashrate*</th>";
	echo "<th align=right width=60>Shares/Min*</th>";
	echo "</tr>";
	echo "</thead>";

	foreach($workers as $worker)
	{
		$user_rate1 = Yii::$app->YiimpUtils->worker_rate($worker->id, $worker->algo);
		
		$user_rate1 = $user_rate1? Yii::$app->ConversionUtils->Itoa2($user_rate1).'h/s': '';

		$version = substr($worker->version, 0, 20);
		$password = substr($worker->password, 0, 32);
		
		$name = $worker->worker;

		$subscribe = Yii::$app->ConversionUtils->Booltoa($worker->subscribe);

		$t = time() - 60;
		$shares_per_minute = Shares::find()
						->where(['algo'=>$algo,'userid'=> $user->id, 'workerid' => $worker->id])
						->andWhere(['>','time',$t])
						->count();

		echo '<tr class="ssrow">';
		echo '<td title="'.$worker->version.'"><b>Version:</b> '.$version.' <br> <b>Worker Name:</b> '.$name.' </td>';
		if (!is_null(Yii::$app->user->identity)) echo "<td>{$worker->ip}</td>";
		echo '<td title="'.$worker->password.'">'.$password.'</td>';
		echo '<td>'.$worker->algo.'</td>';
		echo '<td align="right">'.$worker->difficulty.'</td>';
		echo '<td align="right">'.$subscribe.'</td>';
		echo '<td align="right">'.$user_rate1.'</td>';
		echo '<td align="center" title="">'.$shares_per_minute.'</td>';
		echo '</tr>';
	}

	echo "</table>";
}

echo "</div>";

echo "<p style='font-size: .8em'>
		&nbsp;* approximate from the last 5 minutes submitted shares<br>
		&nbsp;** extranonce.subscribe<br>
		</p>";

echo "</div><br>";








