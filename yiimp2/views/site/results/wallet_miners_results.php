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
		. "<span class='wl-title'>Miners</span></span>"
		. "<span class='wl-chips'><span class='wl-pill blue'>{$workerConnections} connections</span>"
		. "<span class='wl-pill'>auto · 60s</span></span></div>";
	echo "<div class='main-left-inner'><div style='overflow-x:auto'>";
} else {
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>Miners</div>";
	echo "<div class='main-left-inner'>";
}

// Algo summary as a compact pill strip instead of a 4-column table.
$summaryShown = false;
$algoPills = array();
foreach($utils->get_algos() as $algo)
{
	if (!YIIMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;

	$minercount = Workers::find()
			->where(['userid' => $userid , 'algo' => $algo])
			->count();
	if (!$minercount) continue;

	$user_pool_rate = $utils->user_rate($userid, $algo);
	$user_pool_rate = $user_pool_rate ? $conv->Itoa2($user_pool_rate).'h/s' : '-';

	$algoPills[] = '<span class="wm-algo-pill"><span class="wm-algo-bolt">&#9889;</span>'
		. htmlspecialchars($algo, ENT_QUOTES, 'UTF-8')
		. '<b>' . $minercount . ' ' . ($minercount == 1 ? 'worker' : 'workers') . '</b>'
		. '<span>' . htmlspecialchars($user_pool_rate, ENT_QUOTES, 'UTF-8') . '</span>'
		. '</span>';
	$summaryShown = true;
}
if (!$summaryShown) {
	echo "<div class='wl-empty'>No Miners Found</div>";
} else {
	echo "<div class='wm-section-label'>Algos</div>";
	echo "<div class='wm-algo-strip'>" . implode('', $algoPills) . "</div>";
}
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

// TLS status is written by the rebuilt stratum binary. Keep the page working
// on older schemas: no column means no tag rather than an AR attribute error.
$hasTlsColumn = count($workers) && $workers[0]->hasAttribute('tls');

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
	// Some miners connect with an empty worker name. Keep them visible instead of
	// dropping them, otherwise the per-coin and per-worker detail tables disappear.
	if ($name === '') $name = 'worker-' . (int) $worker->id;
	if (!isset($workerStats[$name]))
		$workerStats[$name] = array(
			'worker' => $worker, 'hashrate' => 0.0, 'shares' => 0,
			'rate_1m' => 0.0, 'rate_1h' => 0.0, 'rate_24h' => 0.0,
		);
	$workerStats[$name]['hashrate'] += (float) $utils->worker_rate($worker->id, $worker->algo);
	$workerStats[$name]['rate_1m']  += (float) $utils->worker_rate_window($worker->id, $worker->algo, 60, 10);
	$workerStats[$name]['rate_1h']  += (float) $utils->worker_rate_window($worker->id, $worker->algo, 3600);
	$workerStats[$name]['rate_24h'] += (float) $utils->worker_rate_window($worker->id, $worker->algo, 86400);
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
	echo "<div class='wm-section-label'>By coin</div>";
	echo '<table class="dataGrid2 wm-table">';
	echo "<thead><tr><th class='wl-left'>Coin</th><th>Mode</th><th>Workers</th><th>Shares</th><th>Hashrate</th><th>Next block</th></tr></thead>";

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
	echo "<div class='wm-section-label'>Connected workers</div>";
	echo '<table class="dataGrid2 wm-table">';
	echo "<thead><tr>";
	echo "<th class='wl-left'>Worker</th>";
	if (!is_null(Yii::$app->user->identity)) echo "<th>IP</th>";
	echo "<th class='wl-left'>Algo</th>";
	echo "<th>Diff</th>";
	echo "<th>Hashrate</th>";
	echo "<th>Shares/min</th>";
	echo "<th>Coin</th>";
	echo "</tr></thead>";

	foreach($workerStats as $name => $stat)
	{
		$worker = $stat['worker'];

		// Show the same 1m/1h/24m windows as ForgeMiner. NEB uses a fixed 10^10
		// expected-hashes-per-share scale, so Mh/s matches the miner panel.
		$fmtRate = function ($v) use ($conv, $worker): string {
			if (!$v || $v <= 0) return '—';
			if ($worker->algo === 'nebhash')
				return number_format((float) $v / 1000000, 2, '.', '') . 'Mh/s';
			return $conv->Itoa2($v, 1) . 'h/s';
		};
		$user_rate1 = '1m ' . $fmtRate($stat['rate_1m'])
			. ' · 1h ' . $fmtRate($stat['rate_1h'])
			. ' · 24h ' . $fmtRate($stat['rate_24h']);

		// NEB stores a 256-bit target difficulty; show the same scaled value the
		// miner prints (d=0.1 -> 0.1 elsewhere). Raw value stays in the tooltip.
		$diffRaw = htmlspecialchars((string) $worker->difficulty, ENT_QUOTES, 'UTF-8');
		$diffText = $worker->algo === 'nebhash'
			? number_format($utils->display_difficulty($worker->algo, $worker->difficulty), 4, '.', ',')
			: $diffRaw;

		$version = trim((string) $worker->version);
		$version = $version !== '' ? substr($version, 0, 24) : '';
		$password = trim((string) $worker->password);

		$shares_per_minute = $stat['shares'];
		$active = $shares_per_minute > 0;

		$workerCoinSymbol = '—';
		if (preg_match('/(?:^|,)c=([A-Za-z0-9_.-]+)/i', $password, $matches))
			$workerCoinSymbol = strtoupper($matches[1]);
		elseif ($coin)
			$workerCoinSymbol = strtoupper((string) $coin->symbol);

		echo '<tr class="ssrow">';
		echo '<td class="wl-left"><span class="wm-worker">'
			. '<span class="wm-worker-dot' . ($active ? ' active' : '') . '"></span>'
			. '<span class="wm-worker-main">'
			. '<span class="wm-worker-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
			. ($worker->subscribe ? ' <span class="wm-tag-es" title="extranonce.subscribe">ES</span>' : '')
			. ($hasTlsColumn && $worker->tls ? ' <span class="wm-tag-tls" title="TLS connection">TLS</span>' : '')
			. '</span>';
		if ($version !== '' || $password !== '') {
			echo '<span class="wm-worker-sub">';
			if ($version !== '') echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
			if ($version !== '' && $password !== '') echo ' · ';
			if ($password !== '')
				echo '<span title="' . htmlspecialchars((string) $worker->password, ENT_QUOTES, 'UTF-8') . '">&#9881; '
					. htmlspecialchars(substr($password, 0, 24), ENT_QUOTES, 'UTF-8') . '</span>';
			echo '</span>';
		}
		echo '</span></span></td>';
		if (!is_null(Yii::$app->user->identity))
			echo "<td>" . htmlspecialchars((string) $worker->ip, ENT_QUOTES, 'UTF-8') . "</td>";
		echo '<td><span class="wl-pill blue">' . htmlspecialchars((string) $worker->algo, ENT_QUOTES, 'UTF-8') . '</span></td>';
		echo '<td title="raw target difficulty: ' . $diffRaw . '">' . $diffText . '</td>';
		echo '<td><span class="wl-val">' . htmlspecialchars($user_rate1, ENT_QUOTES, 'UTF-8') . '</span></td>';
		echo '<td><span class="wl-val">' . htmlspecialchars((string) $shares_per_minute, ENT_QUOTES, 'UTF-8') . '/min</span></td>';
		echo '<td>' . htmlspecialchars($workerCoinSymbol, ENT_QUOTES, 'UTF-8') . '</td>';
		echo '</tr>';
	}

	echo "</table>";
}

if ($isLegacy) {
	echo "</div>";
	echo "<div class='wl-foot'><span>* shares/min from the last 60 seconds</span>"
		. "<span>ES = extranonce.subscribe · TLS = encrypted stratum</span><span>Refresh 60s</span></div>";
	echo "</div></div>";
} else {
	echo "</div>";

	echo "<p style='font-size: .8em'>
		&nbsp;* shares/min from the last 60 seconds<br>
		&nbsp;ES = extranonce.subscribe · TLS = encrypted stratum<br>
		</p>";

	echo "</div><br>";
}
