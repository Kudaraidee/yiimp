<?php

function doSafeTradeCancelOrder($OrderID=false)
{
	if (!$OrderID) return false;

	$res = safetrade_api_user('trade/market/orders/'.rawurlencode((string) $OrderID).'/cancel',
		array(), 'POST', 'array');
	if (!is_array($res) || !empty($res['errors'])) return false;

	$uuid = addslashes((string) $OrderID);
	$list = getdbolist('db_orders', "market LIKE 'safetrade%' AND uuid='$uuid'");
	foreach ($list as $db_order) $db_order->delete();
	return true;
}

/** Auto-withdraw accumulated BTC. Controlled by withdraw_min_btc, not YIIMP_ALLOW_EXCHANGE. */
function safetrade_auto_withdraw($saveBalance)
{
	if (!is_object($saveBalance)) return;

	$withdrawMin = (float) exchange_get('safetrade', 'withdraw_min_btc',
		defined('EXCH_AUTO_WITHDRAW') ? EXCH_AUTO_WITHDRAW : 0);
	$withdrawFee = (float) exchange_get('safetrade', 'withdraw_fee_btc', 0.0001);
	$btcaddr = defined('YIIMP_BTCADDRESS') ? YIIMP_BTCADDRESS : '';

	if ($withdrawMin <= 0 || $btcaddr === '') return;
	$balance = (float) $saveBalance->balance;
	if ($balance < $withdrawMin + $withdrawFee) return;

	$amount = safetrade_round_down($balance - $withdrawFee, 8);
	if ($amount <= 0) return;

	$body = array(
		'currency' => 'btc',
		'amount' => (float) $amount,
	);

	$beneficiary = (int) exchange_get('safetrade', 'withdraw_beneficiary_id', 0);
	if ($beneficiary > 0) {
		$body['beneficiary_id'] = $beneficiary;
	} else {
		$emailCode = trim((string) exchange_get('safetrade', 'withdraw_email_code', ''));
		$otpCode = trim((string) exchange_get('safetrade', 'withdraw_otp_code', ''));
		if ($emailCode === '' && $otpCode === '') {
			debuglog('safetrade: withdraw_min_btc is set but no withdraw_beneficiary_id or withdraw code is configured');
			return;
		}
		$body['address'] = $btcaddr;
		if ($emailCode !== '') $body['email_code'] = $emailCode;
		if ($otpCode !== '') $body['otp_code'] = $otpCode;
	}

	$blockchain = trim((string) exchange_get('safetrade', 'withdraw_blockchain_key', 'bitcoin-key'));
	if ($blockchain !== '') $body['blockchain_key'] = $blockchain;

	$password = trim((string) exchange_get('safetrade', 'withdraw_password', ''));
	if ($password !== '') $body['withdraw_password'] = $password;

	debuglog("safetrade: withdraw $amount BTC to $btcaddr");
	$res = safetrade_api_user('trade/account/withdraws', $body, 'POST', 'array');
	if (!is_array($res) || !empty($res['errors']) || !isset($res['id'])) {
		debuglog('safetrade: withdraw failed '.strip_data(json_encode($res)));
		return;
	}

	$withdraw = new db_withdraws;
	$withdraw->market = 'safetrade';
	$withdraw->address = $btcaddr;
	$withdraw->amount = $amount;
	$withdraw->time = time();
	$withdraw->save();

	$saveBalance->balance = 0;
	$saveBalance->save();
}

/** Sync spot balances, market balances and deposit addresses. */
function safetrade_sync_balances()
{
	$accounts = safetrade_api_user('trade/account/balances/spot', '', 'GET', 'array');
	if (!is_array($accounts)) return false;

	$saveBalance = getdbosql('db_balances', "name='safetrade'");

	foreach ($accounts as $account) {
		if (!isset($account['currency'])) continue;
		$symbol = strtoupper((string) $account['currency']);
		$available = isset($account['balance']) ? (float) $account['balance'] : 0.0;
		$locked = isset($account['locked']) ? (float) $account['locked'] : 0.0;

		if ($symbol === 'BTC') {
			if (is_object($saveBalance)) {
				$saveBalance->balance = $available;
				$saveBalance->onsell = $locked;
				$saveBalance->save();
			}
			continue;
		}

		$coins = getdbolist('db_coins', 'symbol=:symbol OR symbol2=:symbol',
			array(':symbol' => $symbol));
		foreach ($coins as $coin) {
			$markets = safetrade_markets_for_coin($coin);
			if (empty($markets)) continue;

			foreach ($markets as $market) {
				$market->balance = $available;
				$market->ontrade = $locked;
				$market->balancetime = time();
				$market->save();
			}

			foreach (array('BTC', 'USDT') as $base) {
				if (safetrade_find_market($coin, $base)) {
					safetrade_sync_deposit_address($coin, $base, $accounts);
				}
			}
		}
	}

	safetrade_auto_withdraw($saveBalance);
	return $accounts;
}

function doSafeTradeTrading($quick=false)
{
	$exchange = 'safetrade';
	if (exchange_get($exchange, 'disabled')) return;

	$accounts = safetrade_sync_balances();
	if (!is_array($accounts)) return;
	if (!YAAMP_ALLOW_EXCHANGE) return;

	$markets = safetrade_markets(true);
	if (empty($markets)) return;

	$flushAll = (rand(0, 8) === 0);
	if ($quick) $flushAll = false;

	$minBtcTrade = (float) exchange_get($exchange, 'trade_min_btc', 0.000001);
	$cancelAskPct = (float) exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);
	if ($cancelAskPct <= 1) $cancelAskPct = 1.20;

	$openResult = safetrade_api_user('trade/market/orders', array(
		'state' => 'wait,pending',
		'side' => 'sell',
		'limit' => 100,
	), 'GET', 'array');

	$openOrders = array();
	if (is_array($openResult)) {
		foreach ($openResult as $order) {
			if (!isset($order['market'])) continue;
			$id = strtolower((string) $order['market']);
			if (!isset($openOrders[$id])) $openOrders[$id] = array();
			$openOrders[$id][] = $order;
		}
	}

	foreach ($accounts as $account) {
		if (!isset($account['currency'])) continue;
		$symbol = strtoupper((string) $account['currency']);
		if (in_array($symbol, array('BTC', 'USDT'), true)) continue;

		$available = isset($account['balance']) ? (float) $account['balance'] : 0.0;
		$locked = isset($account['locked']) ? (float) $account['locked'] : 0.0;
		if ($available <= 0 && $locked <= 0) continue;

		$coin = getdbosql('db_coins', 'symbol=:symbol AND dontsell=0', array(':symbol' => $symbol));
		if (!$coin) continue;

		$pair = safetrade_choose_pair($coin, $markets);
		if (!$pair) continue;

		$market = $pair['market'];
		$marketName = $market->name;
		$marketId = $pair['id'];
		$meta = $pair['meta'];

		$depth = safetrade_depth($marketId, 10);
		if (!$depth) continue;

		$bid = $depth['bid'];
		$ask = $depth['ask'];
		$pricePrecision = isset($meta['price_precision']) ? (int) $meta['price_precision'] : 8;
		$amountPrecision = isset($meta['amount_precision']) ? (int) $meta['amount_precision'] : 8;

		$btcValue = (float) $coin->price;
		if ($btcValue <= 0) {
			if ($pair['base'] === 'BTC') $btcValue = $bid;
			else $btcValue = $bid * yiimp_quote_btc_price('USDT', 0);
		}

		$existing = isset($openOrders[$marketId]) ? $openOrders[$marketId] : array();
		$liveIds = array();

		foreach ($existing as $order) {
			$orderId = isset($order['id']) ? (string) $order['id'] : '';
			if ($orderId === '') continue;
			if (isset($order['side']) && stripos($order['side'], 'sell') === false) continue;

			$orderPrice = isset($order['price']) ? (float) $order['price'] : 0.0;
			if ($orderPrice > $ask * $cancelAskPct || $flushAll) {
				if (doSafeTradeCancelOrder($orderId)) continue;
			}

			$liveIds[$orderId] = true;
			$orderAmount = isset($order['origin_amount']) ? (float) $order['origin_amount']
				: (isset($order['amount']) ? (float) $order['amount'] : 0.0);
			safetrade_store_db_order($marketName, $coin->id, $orderAmount, $orderPrice, $bid, $ask, $orderId);
		}

		$dbOrders = getdbolist('db_orders', "coinid={$coin->id} AND market='".addslashes($marketName)."'");
		foreach ($dbOrders as $dbOrder) {
			if (!isset($liveIds[(string) $dbOrder->uuid])) $dbOrder->delete();
		}

		$market->lasttraded = time();
		$market->save();

		$amount = safetrade_round_down($available, $amountPrecision);
		if ($amount <= 0 || $btcValue <= 0 || $amount * $btcValue < $minBtcTrade) continue;

		if ($coin->sellonbid) {
			for ($i = 0; $i < 5 && $amount > 0; $i++) {
				if (!isset($depth['bids'][$i])) break;
				$level = $depth['bids'][$i];
				$levelAmount = isset($level[1]) ? (float) $level[1] : 0.0;
				if ($amount * 1.1 < $levelAmount) break;

				$sellPrice = round((float) $level[0], $pricePrecision);
				$sellAmount = safetrade_round_down(min($amount, $levelAmount), $amountPrecision);
				if ($sellAmount <= 0 || $sellAmount * $btcValue < $minBtcTrade) continue;

				$res = safetrade_create_limit_sell($marketId, $sellPrice, $sellAmount, $meta);
				if (!$res) continue;

				safetrade_store_db_order($marketName, $coin->id, $res['_submitted_amount'],
					$res['_submitted_price'], $bid, $ask, $res['id']);
				$amount = safetrade_round_down($amount - $res['_submitted_amount'], $amountPrecision);
			}
		}

		if ($amount <= 0) continue;

		$sellPrice = $coin->sellonbid ? (float) $bid
			: (float) ($ask - pow(10, -$pricePrecision));
		if ($ask > 0) $sellPrice = min($sellPrice, $ask - pow(10, -$pricePrecision));
		$sellPrice = round($sellPrice, $pricePrecision);
		if ($sellPrice <= 0 || $amount * $btcValue < $minBtcTrade) continue;

		$res = safetrade_create_limit_sell($marketId, $sellPrice, $amount, $meta);
		if (!$res) continue;

		safetrade_store_db_order($marketName, $coin->id, $res['_submitted_amount'],
			$res['_submitted_price'], $bid, $ask, $res['id']);
	}

	return;
}
