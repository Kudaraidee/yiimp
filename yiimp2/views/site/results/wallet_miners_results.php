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
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$workerConnections = Workers::find()->where(['userid' => $userid])->count();

if ($isLegacy) {
	echo "<div id='wallet-miners-card' class='main-left-box'>";
	echo "<div class='main-left-title'><span class='wl-head-main'>"
		. "<span class='wl-title'>Miners</span><span class='wl-sub'>" . htmlspecialchars($user->username) . "</span></span>"
		. "<span class='wl-chips'><span class='wl-pill blue'>{$workerConnections} connections</span>"
		. "<span class='wl-pill'>auto · 60s</span></span></div>";
	echo "<div class='main-left-inner'><div style='overflow-x:auto'>";
} else {
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>Miners: {$user->username}</div>";
	echo "<div class='main-left-inner'>";
}

echo '<table class="dataGrid2 wm-table">';
echo "<thead><tr>";
echo "<th class='wl-left'>Algo</th><th>Workers</th><th>Hashrate</th><th>TTF</th>";
echo "</tr></thead>";

$summaryShown = false;
foreach($utils->get_algos() as $algo)
{
	if (!YIIMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;

	$minercount = Workers::find()
			->where(['userid' => $userid , 'algo' => $algo])
			->count();
	if (!$minercount) continue;

	$user_pool_rate = $utils->user_rate($userid, $algo);
	$user_pool_rate = $user_pool_rate? $conv->Itoa2($user_pool_rate).'h/s': '-';

	echo '<tr>';
	echo '<td class="wl-left"><span class="wl-pill blue">'.htmlspecialchars($algo).'</span></td>';
	echo '<td><span class="wl-val">'.$minercount.'</span></td>';
	echo '<td><span class="wl-val">'.$user_pool_rate.'</span></td>';
	// algo-level TTF combines different coin difficulties, so point to the per-coin table
	echo '<td title="See TTF per coin below"><span class="wl-val">—</span></td>';
	echo '</tr>';
	$summaryShown = true;
}
if (!$summaryShown) echo "<tr><td colspan='4' class='wl-empty'>No Miners Found</td></tr>";

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
	echo "<div class='wl-summary'><span><i></i><b>" . count($uniqueWorkers) . " workers</b> connected</span>"
		. "<span>Coin from c=SYMBOL in the password</span><span>Refresh <b>60s</b></span></div>";
	echo '<table class="dataGrid2 wm-table">';
	echo "<thead><tr><th class='wl-left'>Coin</th><th>Mode</th><th>Workers</th><th>Shares</th><th>Hashrate</th><th>TTF</th></tr></thead>";

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
			$ttfText = $ttf ? $conv->sectoa2($ttf) : '—';

			$userDiff = isset($userShareMap[$rowCoinid][$solo]) ? $userShareMap[$rowCoinid][$solo] : 0;
			$totalDiff = isset($totalShareMap[$rowCoinid][$solo]) ? $totalShareMap[$rowCoinid][$solo] : 0;
			$percent = ($userDiff && $totalDiff) ? round($userDiff * 100 / $totalDiff, 2) : 0;
			$sharesText = (!$solo && $percent) ? $conv->Itoa2($percent).'%' : '—';

			$rateText = $userRate ? $conv->Itoa2($userRate).'h/s' : '—';
			$tip = 'diff '.number_format((float) $rowCoin->difficulty, 2)
				.' · network '.($networkHash ? $conv->Itoa2($networkHash).'h/s' : '—')
				.' · block '.$blocktime.'s'
				.' · pool '.($poolRate ? $conv->Itoa2($poolRate).'h/s' : '—')
				.' · you '.($userRate ? $conv->Itoa2($userRate).'h/s' : '—');

			$symbol = htmlspecialchars($rowCoin->symbol, ENT_QUOTES, 'UTF-8');
			$name = htmlspecialchars($rowCoin->name, ENT_QUOTES, 'UTF-8');
			$initials = strtoupper(substr((string) $rowCoin->symbol, 0, 3));
			$coinImg = !empty($rowCoin->image)
				? '<img src="' . htmlspecialchars($rowCoin->image, ENT_QUOTES, 'UTF-8') . '" alt="" onerror="this.remove()">'
				: '';

			echo '<tr>';
			echo '<td class="wl-left"><span class="wl-coin"><span class="wl-ico">' . htmlspecialchars($initials) . $coinImg . '</span>'
				. '<span class="wl-coin-main"><span class="wl-link" title="' . $name . '">' . $symbol . '</span>'
				. '<span class="wl-cap">' . htmlspecialchars((string) $rowCoin->algo) . '</span></span></span></td>';
			echo '<td><span class="wl-pill ' . ($solo ? 'teal' : 'blue') . '">' . ($solo ? 'Solo' : 'Shared') . '</span></td>';
			echo '<td><span class="wl-val">' . $workerTotal . '</span></td>';
			echo '<td><span class="wl-val">' . $sharesText . '</span></td>';
			echo '<td><span class="wl-val">' . $rateText . '</span></td>';
			echo '<td title="' . htmlspecialchars($tip) . '"><span class="wl-val">' . $ttfText . '</span></td>';
			echo '</tr>';
			$shown = true;
		}
	}

	if (!$shown) {
		echo "<tr><td colspan='6' class='wl-empty'>No Miners Found</td></tr>";
	}
	echo "</table>";
}
////////////////////////////////////////////////////////////////////////////////

if(count($uniqueWorkers))
{
	echo '<table class="dataGrid2 wm-table">';
	echo "<thead><tr>";
	echo "<th class='wl-left'>Worker</th>";
	if (!is_null(Yii::$app->user->identity)) echo "<th>IP</th>";
	echo "<th class='wl-left'>Password</th>";
	echo "<th class='wl-left'>Algo</th>";
	echo "<th>Diff</th>";
	echo "<th title='extranonce.subscribe'>ES</th>";
	echo "<th>Hashrate</th>";
	echo "<th>Shares/Min</th>";
	echo "</tr></thead>";

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

if ($isLegacy) {
	echo "</div>";
	echo "<div class='wl-foot'><span>* approximate from the last 5 minutes submitted shares</span>"
		. "<span>** extranonce.subscribe</span><span>Refresh 60s</span></div>";
	echo "</div></div>";
} else {
	echo "</div>";

	echo "<p style='font-size: .8em'>
		&nbsp;* approximate from the last 5 minutes submitted shares<br>
		&nbsp;** extranonce.subscribe<br>
		</p>";

	echo "</div><br>";
}
