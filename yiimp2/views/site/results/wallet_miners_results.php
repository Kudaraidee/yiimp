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

$utils = Yii::$app->YiimpUtils;
$conv = Yii::$app->ConversionUtils;
$now = time();

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Miners: {$user->username}</div>";
echo "<div class='main-left-inner'>";

echo <<<'CSS'
<style>
.wallet-miners-table .coin-cell { white-space: nowrap; }
.wallet-miners-table .coin-cell img { width:16px; height:16px; margin-right:6px; vertical-align:middle; }
.wallet-miners-table .coin-algo { color:#888; font-size:.85em; margin-left:4px; }
.wallet-miners-table .mode { display:inline-block; padding:2px 7px; border-radius:3px;
	color:#fff; font-size:.85em; font-weight:bold; }
.wallet-miners-table .mode.shared { background:#87d547; }
.wallet-miners-table .mode.solo { background:#48d8d8; }
.wallet-miners-table .ttf { font-variant-numeric:tabular-nums; }
</style>
CSS;

echo '<table class="dataGrid2">';
echo "<thead>";
echo "<tr>";
echo "<th align=left>Summary</th>";
echo "<th align=right width=80>Workers</th>";
echo "<th align=right width=80>Hashrate*</th>";
echo "<th align=right width=60>TTF</th>";
echo "</tr>";
echo "</thead>";

foreach($utils->get_algos() as $algo)
{
	if (!YIIMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;

	$minercount = Workers::find()
			->where(['userid' => $userid , 'algo' => $algo])
			->count();
	if (!$minercount) continue;

	$user_pool_rate = $utils->user_rate($userid, $algo);
	$user_pool_rate = $user_pool_rate? $conv->Itoa2($user_pool_rate).'h/s': '-';

	echo '<tr class="ssrow">';
	echo '<td><b>'.$algo.'</b></td>';
	echo '<td align="right" >'.$minercount.'</td>';
	echo '<td align="right" width="100"><b>'.$user_pool_rate.'</b></td>';
	// algo-level TTF combines different coin difficulties, so point to the per-coin table
	echo '<td align="right" width="100" title="See TTF per coin below">-</td>';
	echo '</tr>';
}

echo "</table>";
////////////////////////////////////////////////////////////////////////////////

// Coins this user has mined recently. db_workers only knows the algo, so coin
// attribution for the hashrate/TTF comes from the shares themselves.
$recent = (new \yii\db\Query())
		->select(['coinid', 'solo', 'COUNT(DISTINCT workerid) AS workers'])
		->from('shares')
		->where(['valid' => 1, 'userid' => $userid])
		->andWhere(['>', 'time', $now - 3600])
		->groupBy(['coinid', 'solo'])
		->all();

$recentByCoin = array();
foreach($recent as $row)
{
	$rowCoinid = (int) $row['coinid'];
	if (!YIIMP_ALLOW_EXCHANGE && $coinid && $rowCoinid != $coinid) continue;
	$recentByCoin[$rowCoinid][(int) $row['solo']] = (int) $row['workers'];
}

$workers = Workers::find()
		->where(['userid' => $user->id])
		->orderBy('password')
		->all();

// One rig can open several stratum connections, creating duplicate worker rows
// with the same name. Collapse them to one row per name and add up their rates.
$sharesPerMinute = (new \yii\db\Query())
		->select(['workerid', 'COUNT(*) AS c'])
		->from('shares')
		->where(['userid' => $userid])
		->andWhere(['>', 'time', $now - 60])
		->groupBy(['workerid'])
		->all();
$sharesByWorker = array();
foreach($sharesPerMinute as $row)
	$sharesByWorker[(int) $row['workerid']] = (int) $row['c'];

$workerStats = array();
foreach($workers as $worker)
{
	$name = trim((string) $worker->worker);
	if ($name === '') continue; // unnamed duplicate connections
	if (!isset($workerStats[$name]))
		$workerStats[$name] = array('worker' => $worker, 'hashrate' => 0.0, 'shares' => 0);
	$workerStats[$name]['hashrate'] += (float) $utils->worker_rate($worker->id, $worker->algo);
	$workerStats[$name]['shares'] += isset($sharesByWorker[(int) $worker->id]) ? $sharesByWorker[(int) $worker->id] : 0;
}
$uniqueWorkers = array();
foreach($workerStats as $stat)
	$uniqueWorkers[] = $stat['worker'];

// Worker rows are deleted on disconnect and recreated with a new id, so the
// workerid values left in old shares cannot be used as a worker count. Count
// the currently connected workers instead and map them to their coin through
// the c=SYMBOL password token (falling back to the account's payout coin).
$symbols = array();
foreach($uniqueWorkers as $worker) {
	if (preg_match('/(?:^|,)c=([A-Za-z0-9_.-]+)/i', $worker->password, $matches))
		$symbols[] = strtoupper($matches[1]);
}
$symbols = array_unique($symbols);
$coinsBySymbol = array();
if (count($symbols)) {
	foreach(Coins::find()->where(['symbol' => $symbols])->all() as $symbolCoin)
		$coinsBySymbol[strtoupper($symbolCoin->symbol)] = (int) $symbolCoin->id;
}

$workerCount = array();
foreach($uniqueWorkers as $worker)
{
	$solo = (stripos($worker->password, 'm=solo') !== false) ? 1 : 0;
	$workerCoinid = 0;
	if (preg_match('/(?:^|,)c=([A-Za-z0-9_.-]+)/i', $worker->password, $matches)) {
		$symbol = strtoupper($matches[1]);
		if (isset($coinsBySymbol[$symbol])) $workerCoinid = $coinsBySymbol[$symbol];
	}
	if (!$workerCoinid && $coinid) $workerCoinid = $coinid;
	if (!$workerCoinid && count($recentByCoin) == 1) $workerCoinid = key($recentByCoin);
	if (!$workerCoinid) continue;
	if (!YIIMP_ALLOW_EXCHANGE && $coinid && $workerCoinid != $coinid) continue;

	if (!isset($workerCount[$workerCoinid][$solo])) $workerCount[$workerCoinid][$solo] = 0;
	$workerCount[$workerCoinid][$solo]++;
}

// Show rows for recent shares and for currently connected workers.
$rowKeys = array();
foreach($recentByCoin as $rowCoinid => $modes)
	foreach($modes as $solo => $_) $rowKeys[$rowCoinid][$solo] = true;
foreach($workerCount as $rowCoinid => $modes)
	foreach($modes as $solo => $_) $rowKeys[$rowCoinid][$solo] = true;

$coinIds = array_keys($rowKeys);
$coins = array();
if (count($coinIds))
	$coins = Coins::find()->where(['id' => $coinIds])->indexBy('id')->all();
uasort($coins, function($a, $b) { return strcasecmp($a->symbol, $b->symbol); });

// All-time share difficulty per coin/mode, used for the contribution percentage.
$shareKey = md5($userid.'-'.implode('-', $coinIds));
$userShareKey = "wallet_user_coin_shares-$shareKey";
$totalShareKey = "wallet_total_coin_shares-$shareKey";

$userShares = Yii::$app->cache->get($userShareKey);
if ($userShares === false) {
	$userShares = count($coinIds) ? (new \yii\db\Query())
			->select(['coinid', 'solo', 'SUM(difficulty) AS diff'])
			->from('shares')
			->where(['valid' => 1, 'userid' => $userid])
			->andWhere(['in', 'coinid', $coinIds])
			->groupBy(['coinid', 'solo'])
			->all() : array();
	Yii::$app->cache->set($userShareKey, $userShares, 60);
}

$totalShares = Yii::$app->cache->get($totalShareKey);
if ($totalShares === false) {
	$totalShares = count($coinIds) ? (new \yii\db\Query())
			->select(['coinid', 'solo', 'SUM(difficulty) AS diff'])
			->from('shares')
			->where(['valid' => 1])
			->andWhere(['in', 'coinid', $coinIds])
			->groupBy(['coinid', 'solo'])
			->all() : array();
	Yii::$app->cache->set($totalShareKey, $totalShares, 60);
}

$userShareMap = array();
foreach($userShares as $row)
	$userShareMap[(int) $row['coinid']][(int) $row['solo']] = (float) $row['diff'];

$totalShareMap = array();
foreach($totalShares as $row)
	$totalShareMap[(int) $row['coinid']][(int) $row['solo']] = (float) $row['diff'];

if(count($uniqueWorkers))
{
	echo "<br>";
	echo '<table class="dataGrid2 wallet-miners-table">';
	echo "<thead>";
	echo "<tr>";
	echo "<th align=left>Coin</th>";
	echo "<th align=left>Mode</th>";
	echo "<th align=right width=80>Workers</th>";
	echo "<th align=right>Shares</th>";
	echo "<th align=right width=80>Hashrate*</th>";
	echo "<th align=right width=60>TTF</th>";
	echo "</tr>";
	echo "</thead>";

	$shown = false;
	foreach($coins as $rowCoinid => $rowCoin)
	{
		foreach(array(0, 1) as $solo)
		{
			if (!isset($rowKeys[$rowCoinid][$solo])) continue;

			$workerTotal = isset($workerCount[$rowCoinid][$solo]) ? $workerCount[$rowCoinid][$solo] : 0;
			if (!$workerTotal && !isset($workerCount[$rowCoinid]) && isset($recentByCoin[$rowCoinid][$solo]))
				$workerTotal = $recentByCoin[$rowCoinid][$solo]; // no c= token available, keep the old estimate

			$userRate = $solo
				? $utils->user_coin_solo_rate($userid, $rowCoinid)
				: $utils->user_coin_shared_rate($userid, $rowCoinid);
			$poolRate = $solo
				? $utils->coin_solo_rate($rowCoinid)
				: $utils->coin_shared_rate($rowCoinid);

			// same formula as the Pool page: hashes needed for a block / pool rate
			// coin_nethash() honours each coin's powlimit_bits (LRS uses 0), so the
			// legacy difficulty * 2^32 shortcut gives wildly wrong values there.
			$blocktime = $rowCoin->block_time ?: max(min($rowCoin->actual_ttf, 60), 30);
			$networkHash = $utils->coin_nethash($rowCoin);
			$ttf = ($userRate && $poolRate && $networkHash)
				? $networkHash / $poolRate * $blocktime : 0;
			$ttfText = $ttf ? $conv->sectoa2($ttf) : '-';

			$userDiff = isset($userShareMap[$rowCoinid][$solo]) ? $userShareMap[$rowCoinid][$solo] : 0;
			$totalDiff = isset($totalShareMap[$rowCoinid][$solo]) ? $totalShareMap[$rowCoinid][$solo] : 0;
			$percent = ($userDiff && $totalDiff) ? round($userDiff * 100 / $totalDiff, 2) : 0;
			$sharesText = (!$solo && $percent) ? $conv->Itoa2($percent).'%' : '-';

			$rateText = $userRate ? $conv->Itoa2($userRate).'h/s' : '-';
			$tip = 'diff '.number_format((float) $rowCoin->difficulty, 2)
				.' · network '.($networkHash ? $conv->Itoa2($networkHash).'h/s' : '-')
				.' · block '.$blocktime.'s'
				.' · pool '.($poolRate ? $conv->Itoa2($poolRate).'h/s' : '-')
				.' · you '.($userRate ? $conv->Itoa2($userRate).'h/s' : '-');

			$symbol = htmlspecialchars($rowCoin->symbol, ENT_QUOTES, 'UTF-8');
			$name = htmlspecialchars($rowCoin->name, ENT_QUOTES, 'UTF-8');

			echo '<tr class="ssrow">';
			echo '<td class="coin-cell" title="'.$name.'">'
				. (($rowCoin->image) ? '<img src="'.htmlspecialchars($rowCoin->image, ENT_QUOTES, 'UTF-8').'" alt="">' : '')
				. $symbol.'<span class="coin-algo">'.$rowCoin->algo.'</span></td>';
			echo '<td><span class="mode '.($solo ? 'solo' : 'shared').'">'.($solo ? 'Solo' : 'Shared').'</span></td>';
			echo '<td align="right">'.$workerTotal.'</td>';
			echo '<td align="right" width="100">'.$sharesText.'</td>';
			echo '<td align="right" width="100"><b>'.$rateText.'</b></td>';
			echo '<td align="right" width="100" class="ttf" title="'.$tip.'">'.$ttfText.'</td>';
			echo '</tr>';
			$shown = true;
		}
	}

	if (!$shown) {
		echo '<tr class="ssrow"><td colspan="6" align="center">No shares in the last hour and no connected workers</td></tr>';
	}
	echo "</table>";
	echo "<p style='font-size: .8em'>TTF is estimated per coin from that coin's difficulty and pool hashrate.<br>"
		."Workers are connected miners with the same name merged; unnamed connections are hidden."
		." Coin from c=SYMBOL in the password (or the account coin).</p>";
}
////////////////////////////////////////////////////////////////////////////////

if(count($uniqueWorkers))
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

	foreach($workerStats as $name => $stat)
	{
		$worker = $stat['worker'];

		$user_rate1 = $stat['hashrate'] ? $conv->Itoa2($stat['hashrate']).'h/s' : '';

		$version = substr($worker->version, 0, 20);
		$password = substr($worker->password, 0, 32);

		$subscribe = $conv->Booltoa($worker->subscribe);

		$shares_per_minute = $stat['shares'];

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
