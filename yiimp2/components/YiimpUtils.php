<?php

namespace app\components;

use Yii;
use yii\base\Component;

use app\models\Coins;
use app\models\Accounts;
use app\exchanges\ExchangeFactory;

class YiimpUtils extends Component
{
	/* algo related util functions */

	// Used for graphs and 24h profit
	// GH/s for fast algos like sha256
	public function algo_mBTC_factor($algo) {
		$algofactor = 1;
		$algoslist = $this->get_algo_list();
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

  public function get_algo_param() {
    $algo = strip_tags(substr(Yii::$app->getRequest()->getQueryParam('algo'), 0, 32));
    return $algo;
  }

	// mBTC coef per algo
	public function get_algo_norm($algo) {
		$a = array(
			'gr'		=> 0.001,
			'yespowerARWN'		=> 0.001,
			'mike'		=> 0.001,
			'power2b'	=> 0.001,
		);

		if(!isset($a[$algo])) {
			return 1.0;
		}

		return $a[$algo];
	}
	public function get_algo_list() {

	$cached_algolist = Yii::$app->cache->get("YIIMP_algo_list");
	if($cached_algolist) return $cached_algolist;

	$algoslist = (new \yii\db\Query())
				->select(['name','color','speedfactor','port','visible','powlimit_bits'])
				->from('algos')->all();

	if($algoslist) {
		Yii::$app->cache->set("YIIMP_algo_list", $algoslist);
		return $algoslist;
	}

	/* Default Array for Algos */
	$algoslist = [ ['name' => 'sha256',	'color' => '#d0d0a0', 'speedfactor' => 1 , 'port' => 3333, 'visible' => 0], ];

	Yii::$app->cache->set("YIIMP_algo_list", $algoslist);
	return $algoslist;
	}

	public function get_algos( $only_visible = false) {
		
		if ($only_visible) $storage_name = "YIIMP_visible_algos";
		else $storage_name = "YIIMP_unvisible_algos";
		
		$algos = Yii::$app->cache->get($storage_name);
		if($algos) return $algos;
		
		$algoslist = $this->get_algo_list();
		if ($algoslist) {
			foreach ($algoslist AS $algorow) {
				if (isset($algorow['name'])) {
					if (($only_visible) && ($algorow['visible'] == 0)) continue;
					$algos[] = $algorow['name'];
				}
			}
		}
		
		if($algos) {
			Yii::$app->cache->set($storage_name, $algos);
		}
		
		return $algos;
	}

	public function getAlgoColors($algo) {
    
		$algo_colors = Yii::$app->cache->get("YIIMP_algo_colors");
		if(!$algo_colors) {
			$algoslist = $this->get_algo_list();
			if ($algoslist) {
				foreach ($algoslist AS $algorow) {
					if (isset($algorow['name'])) $algo_colors[$algorow['name']] = $algorow['color'];
				}
			}
			
			if($algo_colors) {
				Yii::$app->cache->set("YIIMP_algo_colors", $algo_colors);
			}
		}
		
		if (isset($algo_colors[$algo]))
			$algo_color = $algo_colors[$algo];
		else
			$algo_color = '#ffffff';
			
		return $algo_color;
}

	public function getAlgoPort($algo) {
		
		$algo_ports = Yii::$app->cache->get("YIIMP_algo_ports");
		if(!$algo_ports) {
			$algoslist = $this->get_algo_list();
			if ($algoslist) {
				foreach ($algoslist AS $algorow) {
					if (isset($algorow['name'])) $algo_ports[$algorow['name']] = $algorow['port'];
				}
			}
			
			if($algo_ports) {
				Yii::$app->cache->set("YIIMP_algo_ports", $algo_ports);
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
	// fee related functions

	public function yiimp_fee($algo)
	{
	$fee = Yii::$app->session->get("YIIMP_fee-$algo");
	if($fee && is_numeric($fee)) return (float) $fee;

	$fee = \YIIMP_FEES_MINING;

	// local fees config
	global $configFixedPoolFees;
	if (isset($configFixedPoolFees[$algo])) {
		$fee = (float) $configFixedPoolFees[$algo];
	}
	Yii::$app->session->set("YIIMP_fee-$algo", $fee);
	return $fee;
	}

	public function yiimp_fee_solo($algo)
	{
	$fee_solo = Yii::$app->session->get("YIIMP_fee_solo-$algo");
	if($fee_solo && is_numeric($fee_solo)) return (float) $fee_solo;

	$fee_solo = \YIIMP_FEES_SOLO;

	// local solo fees config
	global $configFixedPoolFeesSolo;
	if (isset($configFixedPoolFeesSolo[$algo])) {
		$fee_solo = (float) $configFixedPoolFeesSolo[$algo];
	}

	Yii::$app->session->set("YIIMP_fee_solo-$algo", $fee_solo);
	return $fee_solo;
	}

	public function take_yiimp_fee($v, $algo, $percent=-1)
	{
	if ($percent == -1) $percent = $this->yiimp_fee($algo);

	return $v - ($v * $percent / 100.0);
	}

	////////////////////////////////////////////////////////////////////////
	// profit / amount related functions
	public function yiimp_profitability($coin , $coin_difficulty = null, $coin_reward = null, $coin_price = null)
	{
		if (is_null($coin_difficulty)) $coin_difficulty = $coin->difficulty;
		if(!$coin_difficulty) return 0;

		if (is_null($coin_reward)) $coin_reward = $coin->reward;
		if (is_null($coin_price)) $coin_price = $coin->price;

		$btcmhd = 20116.56761169 / $coin->difficulty * $coin->reward * $coin->price;

		$speed = $this->coin_nethash($coin);
		$blocktime = $coin->block_time? $coin->block_time : max(min($coin->actual_ttf, 60), 30);
		$reward_per_second = ($coin->reward * $coin_price) / $blocktime;
		$btcmhd = 24*60*60 * $reward_per_second / $speed * 1000000;

		if(!$coin->auxpow && $coin->rpcencoding == 'POW')
		{
			$listaux = Coins::find()
						->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1, 'auxpow' => 1, 'algo' => $coin->algo])
						->all();
			foreach($listaux as $aux)
			{
				if(!$aux->difficulty) continue;

				// $btcmhdaux = 20116.56761169 / $aux->difficulty * $aux->reward * $aux->price;
				$aux_speed = $this->coin_nethash($aux);
				$aux_blocktime = $aux->block_time? $aux->block_time : max(min($aux->actual_ttf, 60), 30);
				$aux_reward_per_second = ($aux->reward * $aux->price) / $aux_blocktime;
				$btcmhdaux = 24*60*60 * $aux_reward_per_second / $aux_speed * 1000000;

				$btcmhd += $btcmhdaux;
			}
		}

		$algo_unit_factor = $this->algo_mBTC_factor($coin->algo);
		return $btcmhd * $algo_unit_factor;
	}

	public function convert_amount_user($coin, $amount, $user)
	{
		$refcoin = Coins::find()->where(['id' => $user->coinid])->one();
		$value = 0.;
		if ($coin->id == $user->coinid) {
			$value = $amount;
		} else {
			if (YIIMP_ALLOW_EXCHANGE) {
				if(!$refcoin) $refcoin = Coins::find()->where(['symbol' => 'BTC'])->one();
				if(!$refcoin || $refcoin->price <= 0) return 0;
				$value = $amount * (($coin->auto_exchange)?$coin->price : 0.) / $refcoin->price;
			} else if ($coin->price && $refcoin && $refcoin->price > 0.) {
				$value = $amount * (($coin->auto_exchange)?$coin->price : 0.) / $refcoin->price;
			}
		}
		
		return $value;
	}

	public function convert_earnings_user($user, $status)
	{
		$refcoin = Coins::find()->where(['id' => $user->coinid])->one();
		$value = 0.;
		if ($refcoin && !$refcoin->auto_exchange) {
			$value = (new \yii\db\Query())
							->select(['sum(amount)'])
							->from('earnings')
							->where(['status' => $status, 'userid' => $user->id, 'coinid' => $user->coinid])
							->scalar();
		} else if (YIIMP_ALLOW_EXCHANGE) {
			if(!$refcoin) $refcoin = Coins::find()->where(['symbol' => 'BTC'])->one();
			if(!$refcoin || $refcoin->price <= 0) return 0;
			$value = (new \yii\db\Query())
							->select(['sum(amount*price)'])
							->from('earnings')
							->where(['status' => $status, 'userid' => $user->id])
							->scalar();
			$value = $value / $refcoin->price;
		} else if ($refcoin && $refcoin->price > 0.) {
			$value = (new \yii\db\Query())
							->select(['sum(amount*price)'])
							->from('earnings')
							->where(['status' => $status, 'userid' => $user->id])
							->scalar();
			$value = $value / $refcoin->price;
		} else if ($user->coinid) {
			$value = (new \yii\db\Query())
							->select(['sum(amount)'])
							->from('earnings')
							->where(['status' => $status, 'userid' => $user->id, 'coinid' => $user->coinid])
							->scalar();
		}
		return $value;
	}


	////////////////////////////////////////////////////////////////////////
	// hash rate related functions

	public function hashrate_constant($algo=null)
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

	public function hashrate_constant_coin($algo=null, $coinid=null)
	{
		$coin_powlimit_bits = null;
		
		if (!is_null($coinid)) {
			$coin = getdbo('db_coins', $coinid);
			if (($coin) && (!is_null($coin->powlimit_bits))) {
				$coin_powlimit_bits = $coin->powlimit_bits;
			}
		}
		
		if (is_null($coin_powlimit_bits)) {
			$algo_list = $this->get_algo_list();
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

	public function hashrate_step()
	{
		return 300;
	}

	public function coin_nethash($coin , $coin_powlimit_bits = null , $coin_difficulty = null, $coin_reward = null, $coin_price = null) {

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
				$algo_list = $this->get_algo_list(false);
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

	//    $speed = $coin->difficulty * $maxtarget_powlimit / YIIMP_algo_mBTC_factor($coin->algo) / max(min($coin->actual_ttf, 60), 30);
		$blocktime = $coin->block_time? $coin->block_time : max(min($coin->actual_ttf, 60), 30);
		$speed = $coin->difficulty * $maxtarget_powlimit / $blocktime;

		return $speed;
	}

	public function pool_rate_pow($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$subquery = (new \yii\db\Query())->select(['id'])->from('coins')->where(['rpcencoding' => 'POW']);
		$rate = Yii::$app->cache->get("YIIMP_pool_rate_pow-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo])
					->andWhere(['>', 'time', $delay])
					->andWhere(['in', 'coinid', $subquery])
					->scalar();
			Yii::$app->cache->set("YIIMP_pool_rate_pow-$algo", $rate);
		}
		return $rate;
	}

	public function pool_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_pool_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_pool_rate-$algo", $rate);
		}
		return $rate;
	}

	public function pool_shared_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_pool_shared_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'solo' => 0])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_pool_shared_rate-$algo", $rate);
		}

		return $rate;
	}

	public function pool_solo_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_pool_solo_rate-$algo");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo AND solo=1",
				[':algo' => $algo]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_pool_solo_rate-$algo", $rate);
		}
		return $rate;
	}

	public function pool_rate_bad($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_pool_rate_bad-$algo");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE NOT valid AND time>$delay AND algo=:algo",
				[':algo' => $algo]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_pool_rate_bad-$algo", $rate);
		}
		return $rate;
	}

	public function pool_rate_rentable($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_pool_rate_rentable-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'extranonce1' => 1])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_pool_rate_rentable-$algo", $rate);
		}

		return $rate;
	}

	public function user_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_user_rate-$userid-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_user_rate-$userid-$algo", $rate);
		}

		return $rate;
	}

	public function user_shared_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_user_shared_rate-$userid-$algo");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=:uid AND algo=:algo AND solo=0",
				[':uid' => $userid, ':algo' => $algo]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_user_shared_rate-$userid-$algo", $rate);
		}
		return $rate;
	}

	public function user_solo_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("YIIMP_user_solo_rate-$userid-$algo");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=:uid AND algo=:algo AND solo=1",
				[':uid' => $userid, ':algo' => $algo]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_user_solo_rate-$userid-$algo", $rate);
		}
		return $rate;
	}

	public function user_rate_bad($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');
		if(!$algo) return 0;

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$diff = Yii::$app->cache->get("YIIMP_user_diff_avg-$userid-$algo");
		if (!$diff) {
			$diff = (new \yii\db\Query())
					->select(["avg(difficulty)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_user_diff_avg-$userid-$algo", $diff);
		}
		if (!$diff) { $diff = 0; }
		$rate = Yii::$app->cache->get("YIIMP_user_rate_bad-$userid-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["((count(id) * $diff) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 0, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("YIIMP_user_rate_bad-$userid-$algo", $rate);
		}

		return $rate;
	}

	public function worker_rate($workerid, $algo=null)
	{
		if (!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target   = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay    = time() - $interval;

		$cacheKey = "YIIMP_worker_rate-{$workerid}-{$algo}";
		$rate = Yii::$app->cache->get($cacheKey);
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT SUM(difficulty) * :target / :interval / 1000
				 FROM shares WHERE valid=1 AND time > :delay AND workerid = :wid",
				[':target' => $target, ':interval' => $interval, ':delay' => $delay, ':wid' => $workerid]
			)->queryScalar();
			Yii::$app->cache->set($cacheKey, $rate, 60);
		}
		return (float) $rate;
	}

	public function worker_rate_bad($workerid, $algo=null)
	{
		if (!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target   = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay    = time() - $interval;

		// Average difficulty of valid shares — used to weight the invalid-share count
		$diff = (float) Yii::$app->db->createCommand(
			"SELECT AVG(difficulty) FROM shares WHERE valid=1 AND time > :delay AND workerid = :wid",
			[':delay' => $delay, ':wid' => $workerid]
		)->queryScalar();

		if (!$diff) return 0.0;

		$rate = (float) Yii::$app->db->createCommand(
			"SELECT COUNT(id) * :diff * :target / :interval / 1000
			 FROM shares WHERE valid != 1 AND time > :delay AND workerid = :wid",
			[':diff' => $diff, ':target' => $target, ':interval' => $interval,
			 ':delay' => $delay, ':wid' => $workerid]
		)->queryScalar();

		return $rate ?: 0.0;
	}

	public function worker_shares_bad($workerid, $algo=null)
	{
		if (!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$interval = $this->hashrate_step();
		$delay    = time() - $interval;

		return (int) Yii::$app->db->createCommand(
			"SELECT COUNT(id) FROM shares WHERE valid != 1 AND time > :delay AND workerid = :wid",
			[':delay' => $delay, ':wid' => $workerid]
		)->queryScalar();
	}

	public function coin_rate($coinid)
	{
		$coin = Coins::find()->where(['id' => $coinid])->one();
		if(!$coin || !$coin->enable) return 0;

		$target = $this->hashrate_constant($coin->algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

	$rate = Yii::$app->session->get("YIIMP_coin_rate-$coinid");
	if (!$rate) {
		$rate = (new \yii\db\Query())
				->select(["(sum(difficulty) * $target / $interval / 1000)"])
				->from('shares')
				->where(['valid' => 1, 'coinid' => $coinid])
				->andwhere(['>' , 'time' , $delay])
				->scalar();
		Yii::$app->session->set("YIIMP_coin_rate-$coinid", $rate);
	}

		return $rate;
	}

	public function coin_shared_rate($coinid)
	{
		$coin = Coins::find()->where(['id' => $coinid])->one();
		if(!$coin || !$coin->enable) return 0;

		$target = $this->hashrate_constant($coin->algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

	$rate = Yii::$app->session->get("YIIMP_coin_shared_rate-$coinid");
	if (!$rate) {
		$rate = (new \yii\db\Query())
				->select(["(sum(difficulty) * $target / $interval / 1000)"])
				->from('shares')
				->where(['valid' => 1, 'coinid' => $coinid, 'solo' => 0])
				->andwhere(['>' , 'time' , $delay])
				->scalar();
		Yii::$app->session->set("YIIMP_coin_shared_rate-$coinid", $rate);
	}

		return $rate;
	}

	public function coin_solo_rate($coinid)
	{
		$coin = Coins::find()->where(['id' => $coinid])->one();
		if(!$coin || !$coin->enable) return 0;

		$target = $this->hashrate_constant($coin->algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->session->get("YIIMP_coin_solo_rate-$coinid");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 1, 'coinid' => $coinid, 'solo' => 1])
					->andwhere(['>' , 'time' , $delay])
					->scalar();
			Yii::$app->session->set("YIIMP_coin_solo_rate-$coinid", $rate);
		}

		return $rate;
	}

	public function rented_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->session->get("YIIMP_rented_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 1, 'algo' => $algo])
					->andwhere(['!=' , 'jobid' , 0])
					->andwhere(['>' , 'time' , $delay])
					->scalar();
			Yii::$app->session->set("YIIMP_rented_rate-$algo", $rate);
		}

		return $rate;
	}

	public function job_rate($jobid)
	{
		$job = \app\models\Jobs::findOne((int) $jobid);
		if (!$job) return 0;

		$target   = $this->hashrate_constant($job->algo);
		$interval = $this->hashrate_step();
		$delay    = time() - $interval;

		$rate = Yii::$app->cache->get("YIIMP_job_rate-$jobid");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT (sum(difficulty) * $target / $interval / 1000) FROM jobsubmits WHERE valid AND time>$delay AND jobid=:jid",
				[':jid' => $jobid]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_job_rate-$jobid", $rate);
		}
		return $rate;
	}

	public function job_rate_bad($jobid)
	{
		$job = \app\models\Jobs::findOne((int) $jobid);
		if (!$job) return 0;

		$target   = $this->hashrate_constant($job->algo);
		$interval = $this->hashrate_step();
		$delay    = time() - $interval;

		$diff = Yii::$app->cache->get("YIIMP_job_diff_avg-$jobid");
		if ($diff === false) {
			$diff = (float) Yii::$app->db->createCommand(
				"SELECT avg(difficulty) FROM jobsubmits WHERE valid AND time>$delay AND jobid=:jid",
				[':jid' => $jobid]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_job_diff_avg-$jobid", $diff);
		}

		$rate = Yii::$app->cache->get("YIIMP_job_rate_bad-$jobid");
		if ($rate === false) {
			$rate = Yii::$app->db->createCommand(
				"SELECT ((count(id) * $diff) * $target / $interval / 1000) FROM jobsubmits WHERE valid!=1 AND time>$delay AND jobid=:jid",
				[':jid' => $jobid]
			)->queryScalar();
			Yii::$app->cache->set("YIIMP_job_rate_bad-$jobid", $rate);
		}
		return $rate;
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////
	// http utils
	public function getuserbyaddress($address)
	{
		if(empty($address)) return null;

		$address = trim(substr($address, 0, 52));
		$user = Accounts::find()->where(['username' => $address])->one();

		return $user;
	}

	public function gethexparam($p,$default='')
	{
		$str = Yii::$app->getRequest()->getQueryParam($p, NULL);
		$hex = (is_string($str) && ctype_xdigit($str)) ? $str : $default;
		return $hex;
	}

	/* Format an exchange coin Url */
	public function getMarketUrl($coin, $marketName): string
	{
		$symbol = method_exists($coin, 'getOfficialSymbol') ? $coin->getOfficialSymbol() : $coin->symbol;
		$base   = 'BTC';
		$market = trim($marketName);

		if (str_contains($marketName, ' ')) {
			[$market, $basePart] = explode(' ', $marketName, 2);
			if ($basePart !== '') {
				$base = $basePart;
			}
		}

		return ExchangeFactory::make($market)->marketUrl($symbol, $base);
	}
}