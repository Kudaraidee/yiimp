<?php

function yaamp_get_algo_list() {
    
    $algo_list = controller()->memcache->get("yaamp_algo_list");
    if($algo_list) return $algo_list;
    
    $algoslist = dbolist("select name,color,speedfactor,port,visible,powlimit_bits from algos", [] );
    
    if($algoslist) {
        controller()->memcache->set("yaamp_algo_list", $algoslist);
        return $algoslist;
    }

    /* Default Array for Algos */
    $algoslist = [ ['name' => 'sha256',	'color' => '#d0d0a0', 'speedfactor' => 1 , 'port' => 3333, 'visible' => 0], ];

    controller()->memcache->set("yaamp_algo_list", $algoslist);
    return $algoslist;
}

function yaamp_get_algos( $only_visible = false) {
    
    if ($only_visible) $storage_name = "yaamp_visible_algos";
    else $storage_name = "yaamp_unvisible_algos";
    
    $algos = controller()->memcache->get($storage_name);
    if($algos) return $algos;
    
    $algoslist = yaamp_get_algo_list();
    if ($algoslist) {
        foreach ($algoslist AS $algorow) {
            if (isset($algorow['name'])) {
                if (($only_visible) && ($algorow['visible'] == 0)) continue;
                $algos[] = $algorow['name'];
            }
        }
    }
    
    if($algos) {
        controller()->memcache->set($storage_name, $algos);
    }
    
    return $algos;
}

// Used for graphs and 24h profit
// GH/s for fast algos like sha256
function yaamp_algo_mBTC_factor($algo)
{
    $algofactor = 1;
    $algoslist = yaamp_get_algo_list();
    if ($algoslist) {
        foreach ($algoslist AS $algorow) {
            if ((isset($algorow['name'])) && ($algorow['name'] == $algo)) {
                $algofactor = $algorow['speedfactor'];
                break;
            }
        }
    }
    
    return $algofactor;
}

// mBTC coef per algo
function yaamp_get_algo_norm($algo)
{
	global $configAlgoNormCoef;
	if (isset($configAlgoNormCoef[$algo]))
		return (float) $configAlgoNormCoef[$algo];

	$a = array(
		'sha256'	=> 1.0,
		'curvehash'	=> 1.0,
		'scrypt'	=> 1.0,
		'scryptn'	=> 1.0,
		'x11'		=> 1.0,
		'x13'		=> 1.0,
		'argon2'	=> 1.0,
		'argon2d250'	=> 1.0,
		'argon2d-dyn'	=> 1.0,
		'argon2d4096'	=> 1.0,
		'lyra2'		=> 1.0,
		'lyra2v2'	=> 1.0,
		'lyra2v3'	=> 1.0,
		'gr'		=> 0.001,
		'yespowerARWN'		=> 0.001,
		'myr-gr'	=> 1.0,
		'mike'		=> 0.001,
		'nist5'		=> 1.0,
		'neoscrypt'	=> 1.0,
		'quark'		=> 1.0,
		'qubit'		=> 1.0,
		'skein'		=> 1.0,
		'blake'		=> 1.0,
		'keccak'	=> 1.0,
		'skein2'	=> 1.0,
		'velvet'	=> 1.0,
		'whirlpool'	=> 1.0,
		'power2b'	=> 0.001,
		'yescrypt'	=> 1.0,
		'yescryptR8'	=> 1.0,
		'yescryptR16'	=> 0.001,
		'yescryptR32'	=> 1.0,
		'zr5'		=> 1.0,
	);

	if(!isset($a[$algo]))
		return 1.0;

	return $a[$algo];
}

function getAlgoColors($algo) {
    
    $algo_colors = controller()->memcache->get("yaamp_algo_colors");
    if(!$algo_colors) {
        $algoslist = yaamp_get_algo_list();
        if ($algoslist) {
            foreach ($algoslist AS $algorow) {
                if (isset($algorow['name'])) $algo_colors[$algorow['name']] = $algorow['color'];
            }
        }
        
        if($algo_colors) {
            controller()->memcache->set("yaamp_algo_colors", $algo_colors);
        }
    }
    
    if (isset($algo_colors[$algo]))
        $algo_color = $algo_colors[$algo];
        else
            $algo_color = '#ffffff';
            
            return $algo_color;
}

function getAlgoPort($algo) {
    
    $algo_ports = controller()->memcache->get("yaamp_algo_ports");
    if(!$algo_ports) {
        $algoslist = yaamp_get_algo_list();
        if ($algoslist) {
            foreach ($algoslist AS $algorow) {
                if (isset($algorow['name'])) $algo_ports[$algorow['name']] = $algorow['port'];
            }
        }
        
        if($algo_ports) {
            controller()->memcache->set("yaamp_algo_ports", $algo_ports);
        }
    }
    
    if (isset($algo_ports[$algo])) {
        $algo_port = $algo_ports[$algo];
    }
    else {
        $algo_port = '3033';
    }
    
	global $configCustomPorts;
	if(isset($configCustomPorts[$algo]))
		return $configCustomPorts[$algo];

	return $algo_port;
}

////////////////////////////////////////////////////////////////////////

function yaamp_fee($algo)
{
	$fee = controller()->memcache->get("yaamp_fee-$algo");
	if($fee && is_numeric($fee)) return (float) $fee;

	$fee = YAAMP_FEES_MINING;

	// local fees config
	global $configFixedPoolFees;
	if (isset($configFixedPoolFees[$algo])) {
		$fee = (float) $configFixedPoolFees[$algo];
	}

	controller()->memcache->set("yaamp_fee-$algo", $fee);
	return $fee;
}

function yaamp_fee_solo($algo)
{
	$fee_solo = controller()->memcache->get("yaamp_fee_solo-$algo");
	if($fee_solo && is_numeric($fee_solo)) return (float) $fee_solo;

	$fee_solo = YAAMP_FEES_SOLO;

	// local solo fees config
	global $configFixedPoolFeesSolo;
	if (isset($configFixedPoolFeesSolo[$algo])) {
		$fee_solo = (float) $configFixedPoolFeesSolo[$algo];
	}

	controller()->memcache->set("yaamp_fee_solo-$algo", $fee_solo);
	return $fee_solo;
}

function take_yaamp_fee($v, $algo, $percent=-1)
{
	if ($percent == -1) $percent = yaamp_fee($algo);

	return $v - ($v * $percent / 100.0);
}

function yaamp_hashrate_constant($algo=null)
{
	switch ($algo) {
		case 'equihash96':
		case 'equihash125':
		case 'equihash144':
		case 'equihash192':
		case 'equihash':
			$target = 0x0000000004000000;
			break;
		default:
			$target = 0x0000040000000000; // pow(2, 42);
			break;
	}
	return $target;
}

function yaamp_hashrate_constant_coin($algo=null, $coinid=null)
{
    $coin_powlimit_bits = null;
    
    if (!is_null($coinid)) {
        $coin = getdbo('db_coins', $coinid);
        if (($coin) && (!is_null($coin->powlimit_bits))) {
            $coin_powlimit_bits = $coin->powlimit_bits;
        }
    }
    
    if (is_null($coin_powlimit_bits)) {
        $algo_list = yaamp_get_algo_list(false);
        foreach($algo_list as $current_algo) {
            if ($current_algo['name'] != $algo) continue;
            $coin_powlimit_bits = $current_algo['powlimit_bits'];
        }
    }
    
    if (is_null($coin_powlimit_bits)) {
        $coin_powlimit_bits = 32;
    }
    
    return pow(2, $coin_powlimit_bits);
}

function yaamp_hashrate_step()
{
	return 300;
}

function yaamp_coin_nethash($coin , $coin_powlimit_bits = null , $coin_difficulty = null, $coin_reward = null, $coin_price = null) {

	/*
	$network_hash = controller()
	->memcache
	->get("yiimp-nethashrate-{$coin->symbol}");
	if (!$network_hash)
	{
		$remote = new WalletRPC($coin);
		if ($remote) $info = $remote->getmininginfo();
		if (isset($info['networkhashps']))
		{
			$network_hash = $info['networkhashps'];
			controller()
				->memcache
				->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
		}
		else if (isset($info['netmhashps']))
		{
			$network_hash = floatval($info['netmhashps']) * 1e6;
			controller()
				->memcache
				->set("yiimp-nethashrate-{$coin->symbol}", $network_hash, 60);
		}
		if ($network_hash) return $network_hash;
	}
	*/

    if (is_null($coin_powlimit_bits)) {
        if (!is_null($coin->powlimit_bits)) {
            $coin_powlimit_bits = $coin->powlimit_bits;
        }
        else {
            $algo_list = yaamp_get_algo_list(false);
            foreach($algo_list as $current_algo) {
                 if ($current_algo['name'] != $coin->algo) continue;
                 $coin_powlimit_bits = $current_algo['powlimit_bits'];
            }
        }
    }
    
    if (is_null($coin_powlimit_bits)) {
        $coin_powlimit_bits = 32;
    }

    $maxtarget_powlimit = pow(2, $coin_powlimit_bits);

//    $speed = $coin->difficulty * $maxtarget_powlimit / yaamp_algo_mBTC_factor($coin->algo) / max(min($coin->actual_ttf, 60), 30);
    $blocktime = $coin->block_time? $coin->block_time : max(min($coin->actual_ttf, 60), 30);
    $speed = $coin->difficulty * $maxtarget_powlimit / $blocktime;

    return $speed;
}

function yaamp_profitability($coin)
{
	if(!$coin->difficulty) return 0;

	$btcmhd = 20116.56761169 / $coin->difficulty * $coin->reward * $coin->price;
	if(!$coin->auxpow && $coin->rpcencoding == 'POW')
	{
		$listaux = getdbolist('db_coins', "enable and visible and auto_ready and auxpow and algo='$coin->algo'");
		foreach($listaux as $aux)
		{
			if(!$aux->difficulty) continue;

			$btcmhdaux = 20116.56761169 / $aux->difficulty * $aux->reward * $aux->price;
			$btcmhd += $btcmhdaux;
		}
	}

	$algo_unit_factor = yaamp_algo_mBTC_factor($coin->algo);
	return $btcmhd * $algo_unit_factor;
}

function yaamp_convert_amount_user($coin, $amount, $user)
{
	$refcoin = getdbo('db_coins', $user->coinid);
	$value = 0.;
	if ($coin->id == $user->coinid) {
		$value = $amount;
	} else {
		if (YAAMP_ALLOW_EXCHANGE) {
			if(!$refcoin) $refcoin = getdbosql('db_coins', "symbol='BTC'");
			if(!$refcoin || $refcoin->price <= 0) return 0;
			$value = $amount * (($coin->auto_exchange)?$coin->price : 0.) / $refcoin->price;
		} else if ($coin->price && $refcoin && $refcoin->price > 0.) {
			$value = $amount * (($coin->auto_exchange)?$coin->price : 0.) / $refcoin->price;
		}
	}
	
	return $value;
}

function yaamp_convert_earnings_user($user, $status)
{
	$refcoin = getdbo('db_coins', $user->coinid);
	$value = 0.;
	if ($refcoin && !$refcoin->auto_exchange) {
		$value = dboscalar("SELECT sum(amount) FROM earnings WHERE $status AND userid={$user->id} and coinid={$user->coinid}");
	} else if (YAAMP_ALLOW_EXCHANGE) {
		if(!$refcoin) $refcoin = getdbosql('db_coins', "symbol='BTC'");
		if(!$refcoin || $refcoin->price <= 0) return 0;
		$value = dboscalar("SELECT sum(amount*price) FROM earnings WHERE $status AND userid={$user->id}");
		$value = $value / $refcoin->price;
	} else if ($refcoin && $refcoin->price > 0.) {
		$value = dboscalar("SELECT sum(amount*price) FROM earnings WHERE $status AND userid={$user->id}");
		$value = $value / $refcoin->price;
	} else if ($user->coinid) {
		$value = dboscalar("SELECT sum(amount) FROM earnings WHERE $status AND userid={$user->id} AND coinid=".$user->coinid);
	}
	return $value;
}

////////////////////////////////////////////////////////////////////////////////////////////

function yaamp_pool_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_pool_shared_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_shared_rate-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo AND solo=0", array(':algo'=>$algo));
	return $rate;
}

function yaamp_pool_solo_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_solo_rate-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo AND solo=1", array(':algo'=>$algo));
	return $rate;
}

function yaamp_pool_rate_bad($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_bad-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE not valid AND time>$delay AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_pool_rate_rentable($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_rentable-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND extranonce1 AND time>$delay AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_user_coin_rate($userid, $coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_rate-$userid-$coinid",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND coinid=$coinid");

	return $rate;
}

function yaamp_user_coin_shared_rate($userid, $coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_shared_rate-$userid-$coinid","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND coinid=$coinid AND solo=0");
	return $rate;
}

function yaamp_user_coin_solo_rate($userid, $coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_solo_rate-$userid-$coinid","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND coinid=$coinid AND solo=1");
	return $rate;
}

function yaamp_user_rate($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_rate-$userid-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_user_shared_rate($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_shared_rate-$userid-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo AND solo=0", array(':algo'=>$algo));
	return $rate;
}

function yaamp_user_solo_rate($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_user_solo_rate-$userid-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo AND solo=1", array(':algo'=>$algo));
	return $rate;
}

function yaamp_user_rate_bad($userid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$diff = (double) controller()->memcache->get_database_scalar("yaamp_user_diff_avg-$userid-$algo",
		"SELECT avg(difficulty) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));

	$rate = controller()->memcache->get_database_scalar("yaamp_user_rate_bad-$userid-$algo",
		"SELECT ((count(id) * $diff) * $target / $interval / 1000) FROM shares WHERE valid!=1 AND time>$delay AND userid=$userid AND algo=:algo", array(':algo'=>$algo));

	return $rate;
}

function yaamp_worker_rate($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_worker_rate-$workerid-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);

	return $rate;
}

function yaamp_worker_rate_bad($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$diff = (double) controller()->memcache->get_database_scalar("yaamp_worker_diff_avg-$workerid-$algo",
		"SELECT avg(difficulty) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);

	$rate = controller()->memcache->get_database_scalar("yaamp_worker_rate_bad-$workerid-$algo",
		"SELECT ((count(id) * $diff) * $target / $interval / 1000) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

	return empty($rate)? 0: $rate;
}

function yaamp_worker_shares_bad($workerid, $algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = (int) controller()->memcache->get_database_scalar("yaamp_worker_shares_bad-$workerid-$algo",
		"SELECT count(id) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

	return $rate;
}

function yaamp_coin_rate($coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_coin_rate-$coinid",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND coinid=$coinid");

	return $rate;
}

function yaamp_coin_shared_rate($coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_coin_shared_rate-$coinid",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND solo=0 AND time>$delay AND coinid=$coinid");

	return $rate;
}

function yaamp_coin_solo_rate($coinid)
{
	$coin = getdbo('db_coins', $coinid);
	if(!$coin || !$coin->enable) return 0;

	$target = yaamp_hashrate_constant($coin->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_coin_solo_rate-$coinid",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND solo=1 AND time>$delay AND coinid=$coinid");

	return $rate;
}

function yaamp_rented_rate($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_rented_rate-$algo",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE time>$delay AND algo=:algo AND jobid!=0 AND valid", array(':algo'=>$algo));

	return $rate;
}

function yaamp_job_rate($jobid)
{
	$job = getdbo('db_jobs', $jobid);
	if(!$job) return 0;

	$target = yaamp_hashrate_constant($job->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_job_rate-$jobid",
		"SELECT (sum(difficulty) * $target / $interval / 1000) FROM jobsubmits WHERE valid AND time>$delay AND jobid=".$jobid);
	return $rate;
}

function yaamp_job_rate_bad($jobid)
{
	$job = getdbo('db_jobs', $jobid);
	if(!$job) return 0;

	$target = yaamp_hashrate_constant($job->algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$diff = (double) controller()->memcache->get_database_scalar("yaamp_job_diff_avg-$jobid",
		"SELECT avg(difficulty) FROM jobsubmits WHERE valid AND time>$delay AND jobid=".$jobid);

	$rate = controller()->memcache->get_database_scalar("yaamp_job_rate_bad-$jobid",
		"SELECT ((count(id) * $diff) * $target / $interval / 1000) FROM jobsubmits WHERE valid!=1 AND time>$delay AND jobid=".$jobid);

	return $rate;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////

function yaamp_pool_rate_pow($algo=null)
{
	if(!$algo) $algo = user()->getState('yaamp-algo');

	$target = yaamp_hashrate_constant($algo);
	$interval = yaamp_hashrate_step();
	$delay = time()-$interval;

	$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_pow-$algo",
		"SELECT sum(shares.difficulty) * $target / $interval / 1000 FROM shares, coins
			WHERE shares.valid AND shares.time>$delay AND shares.algo=:algo AND
			shares.coinid=coins.id AND coins.rpcencoding='POW'", array(':algo'=>$algo));

	return $rate;
}

/////////////////////////////////////////////////////////////////////////////////////////////

function yaamp_renter_account($renter)
{
	if(YAAMP_PRODUCTION)
		return "renter-prod-$renter->id";
	else
		return "renter-dev-$renter->id";
}

/////////////////////////////////////////////////////////////////////////////////////////////
