<?php

function doKuCoinCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	$res = kucoin_api_user('/api/v1/orders/'.rawurlencode($OrderID), null, 'DELETE');
	if (kucoin_result_valid($res)) {
		dborun("DELETE FROM orders WHERE market='kucoin' AND uuid='".addslashes($OrderID)."'");
	}
}

function doKuCoinTrading($quick=false)
{
	$exchange = 'kucoin';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	// v2: GET /api/v1/accounts returns all currency accounts.
	$data = kucoin_api_user('/api/v1/accounts');
	if (!kucoin_result_valid($data)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	if (is_array($data->data))
	foreach($data->data as $account)
	{
		$currency = isset($account->currency) ? $account->currency : '';
		if ($currency === '') continue;

		$total = isset($account->balance)
			? (double) $account->balance
			: (double) $account->available + (double) $account->holds;
		$held = isset($account->holds) ? (double) $account->holds : 0;

		if ($currency == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $total;
				$savebalance->onsell = $held;
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
				$market = getdbosql('db_markets',
					"coinid=:coinid AND name='$exchange' ORDER BY balance"
					, array(':coinid'=>$coin->id)
				);
				if (!$market) continue;
				$market->balance = $total;
				$market->ontrade = $held;
				$market->balancetime = time();

				$checked_today = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
				if ($coin->installed && !$checked_today) {
					sleep(1);
					$obj = kucoin_api_user('/api/v1/deposit-addresses', array('currency' => $coin->symbol));
					if (!kucoin_result_valid($obj) || empty($obj->data)) {
						$market->save();
						continue;
					}
					$addr = isset($obj->data[0]->address) ? $obj->data[0]->address : '';
					if (!empty($addr) && $addr != $market->deposit_address) {
						debuglog("$exchange: updated {$coin->symbol} deposit address $addr");
						$market->deposit_address = $addr;
					}
					cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 24*3600);
				}
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
