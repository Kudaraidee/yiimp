<?php

// GETH-family daemons (Larissa, Parallax/XHash, ...) have no validateaddress
// RPC. Their payout addresses are plain 20-byte EVM hex addresses; validate
// locally so an explicit c=<symbol> can bind the account to that coin.
function yaamp_geth_address_valid($coin, $address)
{
	return $coin && isset($coin->rpcencoding) && $coin->rpcencoding === 'GETH' &&
		preg_match('/^0x[0-9a-fA-F]{40}$/', (string) $address) === 1;
}

function BackendUsersUpdate()
{
	$t1 = microtime(true);

	$list = getdbolist('db_accounts', "coinid IS NULL OR IFNULL(coinsymbol,'') != ''");
	foreach($list as $user)
	{
		$old_usercoinid = $user->coinid;
	//	debuglog("testing user $user->username, $user->coinsymbol");
		if(!empty($user->coinsymbol))
		{
			$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$user->coinsymbol));
			$user->coinsymbol = '';

			if($coin)
			{
				if($user->coinid == $coin->id)
				{
					$user->save();
					continue;
				}

				$remote = new WalletRPC($coin);

				$b = yaamp_geth_address_valid($coin, $user->username)
					? array('isvalid' => true)
					: $remote->validateaddress($user->username);
				if(arraySafeVal($b,'isvalid'))
				{
					$old_balance = $user->balance;
					if($user->balance > 0)
					{
						$coinref = getdbo('db_coins', $user->coinid);
						if(!$coinref) {
							if (YAAMP_ALLOW_EXCHANGE)
								$coinref = getdbosql('db_coins', "symbol=:sym", array(':sym'=>yaamp_payout_quote_symbol()));
							else
								continue;
						}

						$oldPrice = yaamp_payout_price($coinref);
						$newPrice = yaamp_payout_price($coin);
						if ($oldPrice <= 0 || $newPrice <= 0) continue;
						$user->balance = $user->balance * $oldPrice / $newPrice;
					}

					$user->coinid = $coin->id;
					$user->save();

					debuglog("{$user->username} converted to {$user->balance} {$coin->symbol} (old: $old_balance)");
					continue;
				}
			}
		}

		$user->coinid = 0;

		$order = YAAMP_ALLOW_EXCHANGE ? "difficulty" : "id";
		$coins = getdbolist('db_coins', "enable ORDER BY $order DESC");
		foreach($coins as $coin)
		{
			$remote = new WalletRPC($coin);

			$b = yaamp_geth_address_valid($coin, $user->username)
				? array('isvalid' => true)
				: $remote->validateaddress($user->username);
			if(!arraySafeVal($b,'isvalid')) continue;

			if ($old_usercoinid && $old_usercoinid != $coin->id) {
				debuglog("{$user->username} set to {$coin->symbol}, balance {$user->balance} reset to 0");
				$user->balance = 0;
			}
			$user->coinid = $coin->id;
			break;
		}

		if (empty($user->coinid)) {
			debuglog("{$user->hostaddr} - {$user->username} is an unknown address!");
		}

		$user->save();
	}

//	$delay=time()-60*60;
//	$list = dborun("UPDATE coins SET dontsell=1 WHERE id in (SELECT coinid FROM accounts WHERE balance>0 OR last_earning>$delay GROUP BY coinid)");
//	$list = dborun("UPDATE coins SET dontsell=0 WHERE id not in (SELECT coinid FROM accounts WHERE balance>0 OR last_earning>$delay GROUP BY coinid)");


//	$list = getdbolist('db_workers', "dns is null");
//	foreach($list as $worker)
//	{
//		$worker->dns = $worker->ip;
//		$res = system("resolveip $worker->ip");

//		if($res)
//		{
//			$a = explode(' ', $res);
//			if($a && isset($a[5]))
//				$worker->dns = $a[5];
//		}

//		$worker->save();
//	}

	$d1 = microtime(true) - $t1;
	controller()->memcache->add_monitoring_function(__METHOD__, $d1);
}


