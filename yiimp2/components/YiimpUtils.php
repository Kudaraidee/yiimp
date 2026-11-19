<?php

namespace app\components;

use Yii;
use yii\base\Component;

use app\models\Coins;
use app\models\Accounts;

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

	$cached_algolist = Yii::$app->cache->get("yaamp_algo_list");
	if($cached_algolist) return $cached_algolist;

	$algoslist = (new \yii\db\Query())
				->select(['name','color','speedfactor','port','visible','powlimit_bits'])
				->from('algos')->all();

	if($algoslist) {
		Yii::$app->cache->set("yaamp_algo_list", $algoslist);
		return $algoslist;
	}

	/* Default Array for Algos */
	$algoslist = [ ['name' => 'sha256',	'color' => '#d0d0a0', 'speedfactor' => 1 , 'port' => 3333, 'visible' => 0], ];

	Yii::$app->cache->set("yaamp_algo_list", $algoslist);
	return $algoslist;
	}

	public function get_algos( $only_visible = false) {
		
		if ($only_visible) $storage_name = "yaamp_visible_algos";
		else $storage_name = "yaamp_unvisible_algos";
		
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
    
		$algo_colors = Yii::$app->cache->get("yaamp_algo_colors");
		if(!$algo_colors) {
			$algoslist = $this->get_algo_list();
			if ($algoslist) {
				foreach ($algoslist AS $algorow) {
					if (isset($algorow['name'])) $algo_colors[$algorow['name']] = $algorow['color'];
				}
			}
			
			if($algo_colors) {
				Yii::$app->cache->set("yaamp_algo_colors", $algo_colors);
			}
		}
		
		if (isset($algo_colors[$algo]))
			$algo_color = $algo_colors[$algo];
		else
			$algo_color = '#ffffff';
			
		return $algo_color;
}

	public function getAlgoPort($algo) {
		
		$algo_ports = Yii::$app->cache->get("yaamp_algo_ports");
		if(!$algo_ports) {
			$algoslist = $this->get_algo_list();
			if ($algoslist) {
				foreach ($algoslist AS $algorow) {
					if (isset($algorow['name'])) $algo_ports[$algorow['name']] = $algorow['port'];
				}
			}
			
			if($algo_ports) {
				Yii::$app->cache->set("yaamp_algo_ports", $algo_ports);
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
	$fee = Yii::$app->session->get("yaamp_fee-$algo");
	if($fee && is_numeric($fee)) return (float) $fee;

	$fee = \YAAMP_FEES_MINING;

	// local fees config
	global $configFixedPoolFees;
	if (isset($configFixedPoolFees[$algo])) {
		$fee = (float) $configFixedPoolFees[$algo];
	}
	Yii::$app->session->set("yaamp_fee-$algo", $fee);
	return $fee;
	}

	public function yiimp_fee_solo($algo)
	{
	$fee_solo = Yii::$app->session->get("yaamp_fee_solo-$algo");
	if($fee_solo && is_numeric($fee_solo)) return (float) $fee_solo;

	$fee_solo = \YAAMP_FEES_SOLO;

	// local solo fees config
	global $configFixedPoolFeesSolo;
	if (isset($configFixedPoolFeesSolo[$algo])) {
		$fee_solo = (float) $configFixedPoolFeesSolo[$algo];
	}

	Yii::$app->session->set("yaamp_fee_solo-$algo", $fee_solo);
	return $fee_solo;
	}

	public function take_yiimp_fee($v, $algo, $percent=-1)
	{
	if ($percent == -1) $percent = $this->yiimp_fee($algo);

	return $v - ($v * $percent / 100.0);
	}

	////////////////////////////////////////////////////////////////////////
	// Market prices for pairs such as LRS/USDT are stored in the quote
	// currency. Convert them to BTC using the rate cached by the backend in
	// settings (usdt_btc, usdc_btc, ...).
	public function quoteBtcPrice($symbol, $default = 0)
	{
		$symbol = strtoupper(trim((string) $symbol));
		if ($symbol === '' || $symbol === 'BTC') return 1.0;

		$row = (new \yii\db\Query())
			->select(['value'])
			->from('settings')
			->where(['param' => strtolower($symbol).'_btc'])
			->one();
		if ($row && (float) $row['value'] > 0) return (float) $row['value'];

		$coin = Coins::find()->where(['symbol' => $symbol])->one();
		if ($coin && (float) $coin->price > 0) return (float) $coin->price;

		return $default;
	}

	public function marketBtcPrice($market, $field = 'price')
	{
		$value = (float) (isset($market->$field) ? $market->$field : 0);
		$base = isset($market->base_coin) ? $market->base_coin : '';
		return $value * $this->quoteBtcPrice($base, 1);
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
			if (YAAMP_ALLOW_EXCHANGE) {
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
		} else if (YAAMP_ALLOW_EXCHANGE) {
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

	//    $speed = $coin->difficulty * $maxtarget_powlimit / yaamp_algo_mBTC_factor($coin->algo) / max(min($coin->actual_ttf, 60), 30);
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
		$rate = Yii::$app->cache->get("yaamp_pool_rate_pow-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo])
					->andWhere(['>', 'time', $delay])
					->andWhere(['in', 'coinid', $subquery])
					->scalar();
			Yii::$app->cache->set("yaamp_pool_rate_pow-$algo", $rate);
		}
		return $rate;
	}

	public function pool_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("yaamp_pool_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_pool_rate-$algo", $rate);
		}
		return $rate;
	}

	public function pool_shared_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("yaamp_pool_shared_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'solo' => 0])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_pool_shared_rate-$algo", $rate);
		}

		return $rate;
	}

	public function pool_solo_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = controller()->memcache->get_database_scalar("yaamp_pool_solo_rate-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND algo=:algo AND solo=1", array(':algo'=>$algo));
		return $rate;
	}

	public function pool_rate_bad($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = controller()->memcache->get_database_scalar("yaamp_pool_rate_bad-$algo",
			"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE not valid AND time>$delay AND algo=:algo", array(':algo'=>$algo));

		return $rate;
	}

	public function pool_rate_rentable($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("yaamp_pool_rate_rentable-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'extranonce1' => 1])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_pool_rate_rentable-$algo", $rate);
		}

		return $rate;
	}

	public function user_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->cache->get("yaamp_user_rate-$userid-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_user_rate-$userid-$algo", $rate);
		}

		return $rate;
	}

	public function user_shared_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = controller()->memcache->get_database_scalar("yaamp_user_shared_rate-$userid-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo AND solo=0", array(':algo'=>$algo));
		return $rate;
	}

	public function user_solo_rate($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = controller()->memcache->get_database_scalar("yaamp_user_solo_rate-$userid-$algo","SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=$userid AND algo=:algo AND solo=1", array(':algo'=>$algo));
		return $rate;
	}

	public function user_rate_bad($userid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');
		if(!$algo) return 0;

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$diff = Yii::$app->cache->get("yaamp_user_diff_avg-$userid-$algo");
		if (!$diff) {
			$diff = (new \yii\db\Query())
					->select(["avg(difficulty)"])
					->from('shares')
					->where(['valid' => true, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_user_diff_avg-$userid-$algo", $diff);
		}
		if (!$diff) { $diff = 0; }
		$rate = Yii::$app->cache->get("yaamp_user_rate_bad-$userid-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["((count(id) * $diff) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 0, 'algo' => $algo, 'userid' => $userid])
					->andWhere(['>', 'time', $delay])
					->scalar();
			Yii::$app->cache->set("yaamp_user_rate_bad-$userid-$algo", $rate);
		}

		return $rate;
	}

	public function worker_rate($workerid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = controller()->memcache->get_database_scalar("yaamp_worker_rate-$workerid-$algo",
			"SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);

		return $rate;
	}

	public function worker_rate_bad($workerid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$diff = (double) controller()->memcache->get_database_scalar("yaamp_worker_diff_avg-$workerid-$algo",
			"SELECT avg(difficulty) FROM shares WHERE valid AND time>$delay AND workerid=".$workerid);

		$rate = controller()->memcache->get_database_scalar("yaamp_worker_rate_bad-$workerid-$algo",
			"SELECT ((count(id) * $diff) * $target / $interval / 1000) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

		return empty($rate)? 0: $rate;
	}

	public function worker_shares_bad($workerid, $algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = (int) controller()->memcache->get_database_scalar("yaamp_worker_shares_bad-$workerid-$algo",
			"SELECT count(id) FROM shares WHERE valid!=1 AND time>$delay AND workerid=".$workerid);

		return $rate;
	}

	public function coin_rate($coinid)
	{
		$coin = Coins::find()->where(['id' => $coinid])->one();
		if(!$coin || !$coin->enable) return 0;

		$target = $this->hashrate_constant($coin->algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

	$rate = Yii::$app->session->get("yaamp_coin_rate-$coinid");
	if (!$rate) {
		$rate = (new \yii\db\Query())
				->select(["(sum(difficulty) * $target / $interval / 1000)"])
				->from('shares')
				->where(['valid' => 1, 'coinid' => $coinid])
				->andwhere(['>' , 'time' , $delay])
				->scalar();
		Yii::$app->session->set("yaamp_coin_rate-$coinid", $rate);
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

	$rate = Yii::$app->session->get("yaamp_coin_shared_rate-$coinid");
	if (!$rate) {
		$rate = (new \yii\db\Query())
				->select(["(sum(difficulty) * $target / $interval / 1000)"])
				->from('shares')
				->where(['valid' => 1, 'coinid' => $coinid, 'solo' => 0])
				->andwhere(['>' , 'time' , $delay])
				->scalar();
		Yii::$app->session->set("yaamp_coin_shared_rate-$coinid", $rate);
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

		$rate = Yii::$app->session->get("yaamp_coin_solo_rate-$coinid");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 1, 'coinid' => $coinid, 'solo' => 1])
					->andwhere(['>' , 'time' , $delay])
					->scalar();
			Yii::$app->session->set("yaamp_coin_solo_rate-$coinid", $rate);
		}

		return $rate;
	}

	public function rented_rate($algo=null)
	{
		if(!$algo) $algo = Yii::$app->session->get('yaamp-algo');

		$target = $this->hashrate_constant($algo);
		$interval = $this->hashrate_step();
		$delay = time()-$interval;

		$rate = Yii::$app->session->get("yaamp_rented_rate-$algo");
		if (!$rate) {
			$rate = (new \yii\db\Query())
					->select(["(sum(difficulty) * $target / $interval / 1000)"])
					->from('shares')
					->where(['valid' => 1, 'algo' => $algo])
					->andwhere(['!=' , 'jobid' , 0])
					->andwhere(['>' , 'time' , $delay])
					->scalar();
			Yii::$app->session->set("yaamp_rented_rate-$algo", $rate);
		}

		return $rate;
	}

	public function job_rate($jobid)
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

	public function job_rate_bad($jobid)
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
	public function getMarketUrl($coin, $marketName)
	{
		return '';
	}
}