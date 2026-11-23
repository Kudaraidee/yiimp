<?php

function doHitBTCCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	$res = hitbtc_api_user('spot/order/'.rawurlencode($OrderID), NULL, 'DELETE');
	if (is_object($res) && in_array($res->status ?? '', array('canceled', 'new'), true)) {
		dborun("DELETE FROM orders WHERE market='hitbtc' AND uuid='".addslashes($OrderID)."'");
	}
}

function doHitBTCTrading($quick=false)
{
	$exchange = 'hitbtc';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	// v3: GET /api/3/spot/balance returns a list of {currency,available,reserved}.
	$data = hitbtc_api_user('spot/balance');
	if (!is_array($data) && !is_object($data)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	foreach($data as $balance)
	{
		$currency = isset($balance->currency) ? $balance->currency : '';
		if ($currency === '') continue;

		$cash = isset($balance->available) ? (double) $balance->available : 0;
		$reserved = isset($balance->reserved) ? (double) $balance->reserved : 0;

		if ($currency == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $cash;
				$savebalance->onsell = $reserved;
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$currency)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $cash;
				$market->ontrade = $reserved;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
