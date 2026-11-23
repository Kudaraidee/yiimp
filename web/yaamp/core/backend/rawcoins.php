<?php
/**
 * This function adds the new markets
 * It also create new coins in the database (if present on the most common exchanges)
 */
function updateRawcoins()
{
	debuglog(__FUNCTION__);
	// exit();
	exchange_set_default('binance', 'disabled', true);
	exchange_set_default('nestex', 'disabled', false);
	exchange_set_default('hitbtc', 'disabled', true);
	exchange_set_default('kraken', 'disabled', true);
	exchange_set_default('kucoin', 'disabled', true);
	exchange_set_default('poloniex', 'disabled', true);
	exchange_set_default('yobit', 'disabled', false);

	settings_prefetch_all();

	$exchanges = getdbolist('db_balances');
	foreach ($exchanges as $exchange) {
		updateRawCoinExchange($exchange->name);
	}

	//////////////////////////////////////////////////////////

	$markets = dbocolumn("SELECT DISTINCT name FROM markets");
	foreach ($markets as $exchange) {
		if (exchange_get($exchange, 'disabled')) {
			$res = dborun("UPDATE markets SET disabled=8 WHERE name='$exchange'");
			if(!$res) continue;
			$coins = getdbolist('db_coins', "id IN (SELECT coinid FROM markets WHERE name='$exchange')");
			foreach($coins as $coin) {
				// allow to track a single market on a disabled exchange (dev test)
				if (market_get($exchange, $coin->getOfficialSymbol(), 'disabled', 1) == 0) {
					$res -= dborun("UPDATE markets SET disabled=0 WHERE name='$exchange' AND coinid={$coin->id}");
				}
			}
			debuglog("$exchange: $res markets disabled from db settings");
		} else {
			$res = dborun("UPDATE markets SET disabled=0 WHERE name='$exchange' AND disabled=8");
			if($res) debuglog("$exchange: $res markets re-enabled from db settings");
		}
	}

	dborun("DELETE FROM markets WHERE deleted");

	$list = getdbolist('db_coins', "not enable and not installed and id not in (select distinct coinid from markets)");
	foreach($list as $coin)
	{
		if ($coin->visible)
			debuglog("{$coin->symbol} is no longer active");
	// todo: proper cleanup in all tables (like "yiimp coin SYM delete")
	//	if ($coin->symbol != 'BTC')
	//		$coin->delete();
	}
}

function updateRawCoinExchange($marketname)
{
	debuglog(__FUNCTION__);
	debuglog("==== Exchange $marketname ====");
	switch ($marketname) {
		case 'nestex':
			if (!exchange_get($marketname, 'disabled'))
			{
				$list = nestex_api_query(); // fetch all tickers
				if (is_array($list) && !empty($list))
				{
					// Optional debug
					// debuglog(json_encode($list));

					dborun("UPDATE markets SET deleted=true WHERE name='$marketname'");

					foreach ($list as $data)
					{
						if (empty($data['base_currency']) || empty($data['target_currency']))
							continue;

						$symbol = strtoupper($data['base_currency']); // e.g., BTC
						$base   = strtoupper($data['target_currency']); // e.g., USDT

						// NestEX is USDT-only, skip anything else just in case
						if ($base !== 'USDT')
							continue;

						updateRawCoin($marketname, $symbol, $symbol, $base);
					}
				}
			}
			break;


		/* P2PB2B code not working as api-access functions missing 
		case 'p2pb2b':
			debuglog("Start P2PB2B");
			if (!exchange_get('p2pb2b', 'disabled')) 
			{
				debuglog("Ok");
				$list = p2pb2b_api_query('tickers');
				#debuglog(json_encode($list));
				if(is_object($list) && !empty($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='p2pb2b'");
					foreach($list->result as $name=>$ticker) {
						#debuglog("==== " .$name. " ====");
						#debuglog(json_encode($ticker));
						$e = explode('_', $name);
						if (strtoupper($e[1]) !== 'BTC')
							continue;
						$symbol = strtoupper($e[0]);
						updateRawCoin('p2pb2b', $symbol);
					}
				}
			}
			break; */
		/* btc-alpha code not working as api-access functions missing
		case 'btc-alpha':
			if (!exchange_get('btc-alpha', 'disabled')) {
				$list = btcalpha_api_query('ticker');
				if(is_array($list) && !empty($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='btcalpha'");
					foreach($list as $ticker) {
						$e = explode('_', $ticker->pair);
						if (strtoupper($e[1]) !== 'BTC')
							continue;
						$symbol = strtoupper($e[0]);
						updateRawCoin('btc-alpha', $symbol);
					}
				}
			}
			break; */

		case 'nonkyc':
			if (!exchange_get('nonkyc', 'disabled')) {
				$list = nonkyc_api_query('tickers','','array');
				if(is_array($list) && !empty($list)) {
					dborun("UPDATE markets SET deleted=true WHERE name='nonkyc'");
					foreach ($list as $tickers) {
						$base = strtoupper($tickers['target_currency']);
						$symbol = strtoupper($tickers['base_currency']);
						if (strtoupper($symbol) == 'BTC') {
							if ((strtoupper($base) === 'USDT') ||
							    (strtoupper($base) === 'USDC')) {
									$symbol = strtoupper($tickers['target_currency']);
									$base = strtoupper($tickers['base_currency']);
							}
							else {
								continue;
							}
						}

						updateRawCoin('nonkyc', $symbol, $symbol, ($base == 'BTC')?null:$base);
					}
				}
			}
			break;
	
		case 'safetrade':
			if (!exchange_get('safetrade', 'disabled')) {
				$list = safetrade_markets(true);

				if (!empty($list)) {
					// Retire both the bare BTC row and the qualified quote rows.
					dborun("UPDATE markets SET deleted=true WHERE name='safetrade' OR name LIKE 'safetrade %'");
					foreach ($list as $meta) {
						if (isset($meta['state']) && $meta['state'] !== 'enabled') continue;

						$base = strtoupper(isset($meta['quote_unit']) ? $meta['quote_unit'] : '');
						if (!safetrade_supported_quote($base)) continue;

						$symbol = strtoupper(isset($meta['base_unit']) ? $meta['base_unit'] : '');
						if ($symbol === '' || $symbol === 'BTC') continue;

						updateRawCoin('safetrade', $symbol, $symbol, ($base === 'BTC') ? null : $base);
					}
				}
			}
			break;

		case 'poloniex':
			if (!exchange_get('poloniex', 'disabled')) {
				$poloniex = new poloniex;
				$tickers = $poloniex->get_currencies();
				if (!$tickers)
					$tickers = array();
				else
					dborun("UPDATE markets SET deleted=true WHERE name='poloniex'");
				foreach($tickers as $symbol=>$ticker)
				{
					if(arraySafeVal($ticker,'disabled')) continue;
					if(arraySafeVal($ticker,'delisted')) continue;
					updateRawCoin('poloniex', $symbol);
				}
			}
		break;
		case 'yobit':
			if (!exchange_get('yobit', 'disabled')) {
				$res = yobit_api_query('info');
				if($res)
				{
					dborun("UPDATE markets SET deleted=true WHERE name='yobit'");
					foreach($res->pairs as $i=>$item)
					{
						$e = explode('_', $i);
						$symbol = strtoupper($e[0]);
						updateRawCoin('yobit', $symbol);
					}
				}
			}
		break;
		case 'hitbtc':
			if (!exchange_get('hitbtc', 'disabled')) {
				// v3 public/symbol is a map keyed by symbol, e.g. LTCBTC.
				$list = hitbtc_api_query('symbols');
				if(is_object($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='hitbtc'");
					foreach($list as $data) {
						$base = strtoupper(isset($data->quote_currency) ? $data->quote_currency : '');
						if ($base != 'BTC') continue;
						$symbol = strtoupper(isset($data->base_currency) ? $data->base_currency : '');
						if ($symbol === '' || $symbol === 'BTC') continue;
						updateRawCoin('hitbtc', $symbol);
					}
				}
			}
		break;
		case 'kraken':
			if (!exchange_get('kraken', 'disabled')) {
				$list = kraken_api_query('AssetPairs');
				if(is_array($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='kraken'");
					foreach($list as $pair => $item) {
						$pairs = explode('-', $pair);
						$base = reset($pairs); $symbol = end($pairs);
						if($symbol == 'BTC' || $base != 'BTC') continue;
						if(in_array($symbol, array('GBP','CAD','EUR','USD','JPY'))) continue;
						if(strpos($symbol,'.d') !== false) continue;
						$symbol = strtoupper($symbol);
						updateRawCoin('kraken', $symbol);
					}
				}
			}
		break;
		case 'binance':
			if (!exchange_get('binance', 'disabled')) {
				$list = binance_api_query('ticker/allBookTickers');
				if(is_array($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='binance'");
					foreach($list as $ticker) {
						$base = substr($ticker->symbol, -3, 3);
						// XXXBTC XXXETH BTCUSDT (no separator!)
						if ($base != 'BTC') continue;
						$symbol = substr($ticker->symbol, 0, strlen($ticker->symbol)-3);
						updateRawCoin('binance', $symbol);
					}
				}
			}
		break;
		case 'gateio':
			if (!exchange_get('gateio', 'disabled')) {
				$json = gateio_api_query('marketlist');
				$list = arraySafeVal($json,'data');
				if(!empty($list))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='gateio'");
					foreach($list as $item) {
						if ($item['curr_b'] != 'BTC')
							continue;
						$symbol = trim(strtoupper($item['symbol']));
						$name = trim($item['name']);
						updateRawCoin('gateio', $symbol, $name);
					}
				}
			}
		break;
		case 'kucoin':
			if (!exchange_get('kucoin', 'disabled')) {
				$list = kucoin_api_query('currencies');
				if(kucoin_result_valid($list) && !empty($list->data))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='kucoin'");
					foreach($list->data as $item) {
						// v2 exposes the ticker as "currency" (old v1 field: "name").
						$symbol = isset($item->currency) ? $item->currency : $item->name;
						$name = isset($item->fullName) ? $item->fullName : $symbol;
						updateRawCoin('kucoin', $symbol, $name);
					}
				}
			}
		break;
		case 'bibox':
			if (!exchange_get('bibox', 'disabled')) {
				$list = bibox_api_query('marketAll');
				if(isset($list["result"]) && !empty($list["result"]))
				{
					dborun("UPDATE markets SET deleted=true WHERE name='bibox'");
					foreach($list["result"] as $currency) {
						if ($currency["currency_symbol"] == 'BTC') continue;
						updateRawCoin('bibox', $currency["coin_symbol"]);
					}
				}
			}
		break;
	}
	debuglog("==== END Exchange ====");
}

function updateRawCoin($marketname, $symbol, $name='unknown', $reference_symbol = null)
{
	if($symbol == 'BTC') return;

	$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
	if(!$coin && YAAMP_CREATE_NEW_COINS)
	{
		$algo = '';

		if (in_array($marketname, array('askcoin','binance','hitbtc'))) {
			// don't polute too much the db with new coins, its better from exchanges with labels
			return;
		}

		// some other to ignore...
		if (in_array($marketname, array('yobit','kucoin')))
			return;

		if (market_get($marketname, $symbol, "disabled")) {
			return;
		}

		debuglog("new coin $marketname $symbol $name");

		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->hassubmitblock = true;
		$coin->name = $name;
		$coin->algo = $algo;
		$coin->symbol = $symbol;
		$coin->created = time();
		$coin->save();

		$url = getMarketUrl($coin, $marketname);
		// if (YAAMP_NOTIFY_NEW_COINS)
		// 	mail(YAAMP_ADMIN_EMAIL, "New coin $symbol", "new coin $symbol ($name) on $marketname\r\n\r\n$url");
		sleep(1);
	}

	else if($coin && $coin->name == 'unknown' && $name != 'unknown')
	{
		$coin->name = $name;
		$coin->save();
	}

	$list = getdbolist('db_coins', "symbol=:symbol or symbol2=:symbol", array(':symbol'=>$symbol));
	foreach($list as $coin)
	{
		if (is_null($reference_symbol)) {
			$sql_filter = "coinid=$coin->id and name LIKE '".$marketname."%' and base_coin is NULL";
		}
		else {
			$sql_filter = "coinid=$coin->id and name LIKE '".$marketname."%' and base_coin ='".$reference_symbol."'";
		}
		$market = getdbosql('db_markets', $sql_filter);
		if(!$market)
		{
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $marketname;
			$market->base_coin = $reference_symbol;
		}

		$market->deleted = false;
		$market->save();
	}

}

