<?php

use app\components\rpc\WalletRPC;

$series = array();
$n = 0; $maxdiff = 0.0;

$json = Yii::$app->cache->get("yiimp-explorer-diff-".$coin->symbol);

if (empty($json)) {

	// version is used in multi algo coins
	$multiAlgos = Yii::$app->ExplorerUtils->versionToAlgo($coin, 0) !== false;

	$series['diff'] = array();
	$tm = 0;

	$remote = new WalletRPC($coin);
	// Ethereum RPC has no batched block calls; NEB answers one RPC per block
	// too. Keep both graphs responsive.
	if ($coin->rpcencoding == 'GETH') $graphBlocks = 40;
	elseif ($coin->algo === 'nebhash' || $coin->symbol === 'NEB') $graphBlocks = 60;
	else $graphBlocks = 500;
	for($i = $coin->block_height; $i > max(0, $coin->block_height-$graphBlocks); $i--)
	{
		$hash = $remote->getblockhash($i);
		if(!$hash) continue;

		$block = $remote->getblock($hash);
		if(!$block) continue;

		// only graph PoW blocks; firopow reports nonce=0 in valid PoW blocks
		$isPos = isset($block['mint']) ||
			strstr((string) Yii::$app->ConversionUtils->arraySafeVal($block, 'flags', ''), 'proof-of-stake');
		if ($isPos) continue;
		if (!array_key_exists('nonce', $block) &&
			!Yii::$app->ConversionUtils->arraySafeval($block,'auxpow',0)) continue;

		$n++;

		$tm = $block['time'];
		$dt = date('Y-m-d H:i:s', $tm);
		$diff = floatval($block['difficulty']);
		$vers = $block['version'];
		$algo = Yii::$app->ExplorerUtils->versionToAlgo($coin, $vers);

		if (!$multiAlgos) {
			$maxdiff = max($diff, $maxdiff);
			$series['diff'][$n] = array($dt, $diff, $block['height']);
		} else if ($coin->algo == $algo) {
			$maxdiff = max($diff, $maxdiff);
			$series[$algo][$n] = array($dt, $diff, $block['height']);
		}
	}

	// User blocks
	if ($tm) {
		$sql = "SELECT id, height, IFNULL(difficulty_user, difficulty) AS diff, time FROM blocks".
			" WHERE coin_id=:coin AND algo = :algo AND time >= :tm ORDER BY id";
		$rows = (new \yii\db\Query())
				->select(['id', 'height', 'IFNULL(difficulty_user, difficulty) AS diff', 'time'])
				->from('blocks')
				->where(['coin_id' => $coin->id, 'algo' => $coin->algo])
				->andWhere(['>', 'time' , intval($tm)])

				->orderBy('id')
				->all();

		$n = 0;
		foreach ($rows as $row) {
			$diff = floatval($row['diff']);
			if ($maxdiff && $diff > ($maxdiff*1.5)) $diff = $maxdiff*1.5;
			$tm = $row['time'];
			$dt = date('Y-m-d H:i:s', $tm);
			$series['user'][$n] = array($dt, $diff, $row['diff'], $row['height']);
			$n++;
		}
	}

	$json = '';
	foreach ($series as $algo => $data) {
		if (empty($data)) continue;
		$values = array_values($data);
		$json .= json_encode($values).',';
	}
	$json = rtrim($json, ',');

	// memcache the data
	Yii::$app->cache->set("yiimp-explorer-diff-".$coin->symbol, $json, 120);
}

echo "[$json]";
