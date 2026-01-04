<?php

use app\models\Workers;
use app\components\ViewHelper;
use app\components\CspHelper;

$algo = Yii::$app->session->get('yaamp-algo');
if (!$algo) $algo = 'all';

$target = Yii::$app->YiimpUtils->hashrate_constant($algo);
$interval = Yii::$app->YiimpUtils->hashrate_step();
$delay = time()-$interval;

$total_workers = Workers::find()->where(['algo' => $algo])->count();
$total_extranonce = Workers::find()->where(['algo' => $algo])->andWhere(['subscribe' => 1])->count();

$total_hashrate = Yii::$app->cache->get("current_hashrate-$algo");
if (!$total_hashrate) {
	$total_hashrate = (new \yii\db\Query())
		->select(['hashrate'])
		->from('hashrate')
		->where(['algo' => $algo])
		->orderBy(['time' => SORT_DESC])
		->limit(1)
		->scalar();
	Yii::$app->cache->set("current_hashrate-$algo", $total_hashrate);
}

// Determine if user is admin and if rejects column should be shown
$isAdmin = false;
if (!Yii::$app->user->isGuest && Yii::$app->user->identity !== null) {
    $isAdmin = (bool) Yii::$app->user->identity->is_admin;
}
$showRejects = $isAdmin;
$total_invalid = !$isAdmin ? 0 : Yii::$app->cache->get("current_hashrate_bad-$algo");
if (!$total_invalid && $isAdmin) {
	$total_invalid = (new \yii\db\Query())
		->select(['hashrate_bad'])
		->from('hashrate')
		->where(['algo' => $algo])
		->orderBy(['time' => SORT_DESC])
		->limit(1)
		->scalar();
	Yii::$app->cache->set("current_hashrate_bad-$algo", $total_invalid);
}

try {
	ViewHelper::renderBoxHeader("Miners Version ($algo)");
} catch (\Error $e) {
	Yii::error("ViewHelper not found: " . $e->getMessage(), __METHOD__);
	echo '<div class="box"><div class="box-header"><h3>Miners Version (' . htmlspecialchars($algo) . ')</h3></div><div class="box-body">';
}

//showTableSorter('maintable2');
$tableClass = $showRejects ? 'dataGrid2 show-rejects' : 'dataGrid2';
$rejectsStyle = $showRejects ? '' : ' style="display:none"';
echo '<br/>';
echo '<table id="maintable2" class="' . $tableClass . '">';
echo '<thead>';
echo '<tr>';
echo '<th>Version</th>';
echo '<th align="right">Count</th>';
echo '<th align="right">Donators</th>';
echo '<th align="right" title="* Extranonce Subscribe">ES</th>';
echo '<th align="right">Percent</th>';
echo '<th align="right">Hashrate*</th>';
echo '<th align="right" title="Rate per miner">Avg</th>';
echo '<th align="right" class="rejects"' . $rejectsStyle . '>Reject</th>';
echo '</tr>';
echo '</thead><tbody>';

$error_tab = array(
	20=>'Invalid nonce size',
	21=>'Invalid job id',
	22=>'Duplicate share',
	23=>'Invalid time rolling',
	24=>'Invalid extranonce2 size',
	25=>'Invalid share',
	26=>'Low difficulty share',
	27=>'Invalid extranonce',
);

$total_donators = 0;

$versions = (new \yii\db\Query())
	->select(['version', 'count(*) as c', 'sum(subscribe) as s'])
	->from('workers')
	->where(['algo' => $algo])
	->groupBy('version')
	->orderBy(['c' => SORT_DESC])
	->all();

foreach($versions as $item)
{
	$version = $item['version'];
	$count = $item['c'];
	$extranonce = $item['s'];

	$hashrate = Yii::$app->cache->get("miners-valid-$algo-v$version");
	if (!$hashrate) {
		$subquery = (new \yii\db\Query())
			->select(['id'])
			->from('workers')
			->where(['algo' => $algo, 'version' => $version]);
		
		$hashrate = (new \yii\db\Query())
			->select(["sum(difficulty) * $target / $interval / 1000"])
			->from('shares')
			->where(['valid' => 1])
			->andWhere(['>', 'time', $delay])
			->andWhere(['in', 'workerid', $subquery])
			->scalar();
		Yii::$app->cache->set("miners-valid-$algo-v$version", $hashrate);
	}

	if (!$hashrate && !$isAdmin) continue;

	$invalid = !$total_invalid ? 0 : Yii::$app->cache->get("miners-invalid-$algo-v$version");
	if (!$invalid && $total_invalid) {
		$subquery = (new \yii\db\Query())
			->select(['id'])
			->from('workers')
			->where(['algo' => $algo, 'version' => $version]);
		
		$invalid = (new \yii\db\Query())
			->select(["sum(difficulty) * $target / $interval / 1000"])
			->from('shares')
			->where(['valid' => 0])
			->andWhere(['>', 'time', $delay])
			->andWhere(['in', 'workerid', $subquery])
			->scalar();
		Yii::$app->cache->set("miners-invalid-$algo-v$version", $invalid);
	}

	$title = '';
	foreach($error_tab as $i=>$s)
	{
		$invalid2 = !$total_invalid ? 0 : Yii::$app->cache->get("miners-invalid-$algo-v$version-err$i");
		if (!$invalid2 && $total_invalid) {
			$subquery = (new \yii\db\Query())
				->select(['id'])
				->from('workers')
				->where(['algo' => $algo, 'version' => $version]);
			
			$invalid2 = (new \yii\db\Query())
				->select(["sum(difficulty) * $target / $interval / 1000"])
				->from('shares')
				->where(['error' => $i])
				->andWhere(['>', 'time', $delay])
				->andWhere(['in', 'workerid', $subquery])
				->scalar();
			Yii::$app->cache->set("miners-invalid-$algo-v$version-err$i", $invalid2);
		}

		if($invalid2) {
			$bad2 = round($invalid2*100/($hashrate+$invalid2), 2).'%';
			$title .= "$bad2 - $s\n";
		}
	}

	$donators = (new \yii\db\Query())
		->select(['COUNT(*) AS donators'])
		->from('workers W')
		->leftJoin('accounts A', 'A.id = W.userid')
		->where(['W.algo' => $algo, 'W.version' => $version])
		->andWhere(['>', 'A.donation', 0])
		->scalar();
	$total_donators += $donators;

	$percent = $total_hashrate && $hashrate ? round($hashrate * 100 / $total_hashrate, 2).'%': '';
	if (!$percent || $percent == '0%') $percent = '-';
	$bad = ($hashrate+$invalid)? round($invalid*100/($hashrate+$invalid), 1).'%': '';
	if (!$bad || $bad == '0%') $bad = '-';
	$avg = intval($count) ? $hashrate / intval($count) : '';
	$avg = $avg? Yii::$app->ConversionUtils->Itoa2($avg).'H/s': '';
	$hashrate = $hashrate? Yii::$app->ConversionUtils->Itoa2($hashrate).'H/s': '';
	$version = substr($version, 0, 30);

	echo '<tr class="ssrow">';
	echo '<td><b>'.$version.'</b></td>';
	echo '<td align="right">'.$count.'</td>';
	echo '<td align="right">'.($donators ? $donators : '-').'</td>';
	echo '<td align="right">'.($extranonce ? $extranonce : '-').'</td>';
	if (floatval($percent) > 50)
		echo '<td align="right"><b>'.$percent.'</b></td>';
	else
		echo '<td align="right">'.$percent.'</td>';
	echo '<td align="right">'.$hashrate.'</td>';
	echo '<td align="right">'.$avg.'</td>';
	echo '<td align="right" class="rejects" title="'.$title.'"'.$rejectsStyle.'>'.$bad.'</td>';
	echo '</tr>';
}

echo "</tbody>";

$title = '';
foreach($error_tab as $i=>$s)
{
	$invalid2 = !$total_invalid ? 0 : Yii::$app->cache->get("miners-invalid-$algo-err$i");
	if (!$invalid2 && $total_invalid) {
		$subquery = (new \yii\db\Query())
			->select(['id'])
			->from('workers')
			->where(['algo' => $algo]);
		
		$invalid2 = (new \yii\db\Query())
			->select(["SUM(difficulty) * $target / $interval / 1000"])
			->from('shares')
			->where(['error' => $i])
			->andWhere(['>', 'time', $delay])
			->andWhere(['in', 'workerid', $subquery])
			->scalar();
		Yii::$app->cache->set("miners-invalid-$algo-err$i", $invalid2);
	}

	if($invalid2) {
		$bad2 = round($invalid2*100/($total_hashrate+$invalid2), 2);
		$title .= "$bad2 - $s\n";
	}
}

$bad = ($total_hashrate+$total_invalid) && $total_invalid ? round($total_invalid*100/($total_hashrate+$total_invalid), 1).'%': '';
$avg = intval($total_workers) ? Yii::$app->ConversionUtils->Itoa2($total_hashrate / intval($total_workers)).'H/s' : '';
$total_hashrate = Yii::$app->ConversionUtils->Itoa2($total_hashrate).'H/s';

echo '<tr class="ssrow">';
echo '<th><b>Total</b></th>';
echo '<th align="right">'.$total_workers.'</th>';
echo '<th align="right">'.$total_donators.'</th>';
echo '<th align="right">'.$total_extranonce.'</th>';
echo '<th align="right"></th>';
echo '<th align="right">'.$total_hashrate.'</th>';
echo '<th align="right">'.$avg.'</th>';
echo '<th align="right" title="'.$title.'" class="rejects"'.$rejectsStyle.'>'.$bad.'</th>';
echo '</tr>';

echo "</table>";

echo "<p class='text-small'>
		&nbsp;* approximate from the last 5 minutes submitted shares<br>
		</p>";

try {
	ViewHelper::renderBoxFooter();
} catch (\Error $e) {
	echo "</div></div><br>";
}

// No inline script needed - rejects column visibility is controlled by CSS class on table

