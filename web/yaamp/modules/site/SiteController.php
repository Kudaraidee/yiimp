<?php

class SiteController extends CommonController
{
	public $defaultAction='index';

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		if(isset($_GET['address']))
			$this->render('wallet');
		else
			$this->render('index');
	}

	public function actionApi()
	{
		$this->render('api');
	}

	public function actionBenchmarks()
	{
		$this->render('benchmarks');
	}

	public function actionDiff()
	{
		$this->render('diff');
	}

	public function actionMultialgo()
	{
		$this->render('multialgo');
	}

	public function actionMining()
	{
		$this->render('mining');
	}

	public function actionMiners()
	{
		$this->render('miners');
	}

	/////////////////////////////////

	protected function renderPartialAlgoMemcached($partial, $cachetime=15)
	{
		$algo = user()->getState('yaamp-algo');
		$memcache = controller()->memcache->memcache;
		$memkey = $algo.'_'.str_replace('/','_',$partial);
		$html = controller()->memcache->get($memkey);

		if (!empty($html)) {
			echo $html;
			return;
		}

		ob_start();
		ob_implicit_flush(false);
		$this->renderPartial($partial);
		$html = ob_get_clean();
		echo $html;

		controller()->memcache->set($memkey, $html, $cachetime, MEMCACHE_COMPRESSED);
	}

	// Pool Status : public right panel with all algos and live stats
	public function actionCurrent_results()
	{
		$this->renderPartialAlgoMemcached('results/current_results', 30);
	}

	// Home Tab : Pool Stats (algo) on the bottom right
	public function actionHistory_results()
	{
		$this->renderPartialAlgoMemcached('results/history_results');
	}

	// Home Tab : Coin Information (algo) on the bottom right
	public function actionCoins_info()
	{
		$this->renderPartialAlgoMemcached('results/coins_info');
	}

	// Pool Tab : Top left panel with estimated profit per coin
	public function actionMining_results()
	{
		if ($this->admin)
			$this->renderPartial('results/mining_results');
		else
			$this->renderPartialAlgoMemcached('results/mining_results');
	}

	public function actionMiners_results()
	{
		if ($this->admin)
			$this->renderPartial('results/miners_results');
		else
			$this->renderPartialAlgoMemcached('results/miners_results');
	}

	// Pool tab: graph algo pool hashrate (json data)
	public function actionGraph_hashrate_results()
	{
		$this->renderPartialAlgoMemcached('results/graph_hashrate_results');
	}

	// Pool tab: graph algo estimate history (json data)
	public function actionGraph_price_results()
	{
		$this->renderPartialAlgoMemcached('results/graph_price_results');
	}

	// Pool tab: last 50 blocks
	public function actionFound_results()
	{
		$this->renderPartialAlgoMemcached('results/found_results');
	}

	public function actionWallet_results()
	{
		$this->renderPartial('results/wallet_results');
	}

	public function actionWallet_miners_results()
	{
		$this->renderPartial('results/wallet_miners_results');
	}

	public function actionWallet_graphs_results()
	{
		$this->renderPartial('results/wallet_graphs_results');
	}

	public function actionGraph_earnings_results()
	{
		$this->renderPartial('results/graph_earnings_results');
	}

	public function actionUser_earning_results()
	{
		$this->renderPartial('results/user_earning_results');
	}

	public function actionWallet_found_results()
	{
		$this->renderPartial('results/wallet_found_results');
	}

	public function actionGraph_user_results()
	{
		$this->renderPartial('results/graph_user_results');
	}

	public function actionTitle_results()
	{
		$user = getuserparam(getparam('address'));
		if($user)
		{
			$balance = bitcoinvaluetoa($user->balance);
			$coin = getdbo('db_coins', $user->coinid);

			if($coin)
				echo "$balance $coin->symbol - ".YAAMP_SITE_NAME;
			else
				echo "$balance - ".YAAMP_SITE_NAME;
		}
		else
			echo YAAMP_SITE_URL;
	}

	/////////////////////////////////////////////////

	public function actionAbout()
	{
		$this->render('about');
	}

	public function actionTerms()
	{
		$this->render('terms');
	}

        public function actionPrivacypolicy()
        {
                $this->render('privacypolicy');
        }

	/////////////////////////////////////////////////

	public function actionBlock()
	{
		$this->render('block');

	}

	public function actionBlock_results()
	{
		$this->renderPartial('block_results');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionTx()
	{
		$this->renderPartial('tx');
	}

	////////////////////////////////////////////////////////////////////////////////////////

	public function actionAlgo()
	{
		$algo = getalgoparam();
		$a = getdbosql('db_algos', "name=:name", array(':name'=>$algo));

		if($a)
			user()->setState('yaamp-algo', $a->name);
		else
			user()->setState('yaamp-algo', 'all');

		$route = getparam('r');
		if (!empty($route))
			$this->redirect($route);
		else
			$this->goback();
	}

	public function actionGomining()
	{
		$algo = getalgoparam();
		if ($algo == 'all') {
			return;
		}
		user()->setState('yaamp-algo', $algo);
		$this->redirect("/site/mining");
	}

	public function actionMainbtc()
	{
		debuglog(__METHOD__);
		setcookie('mainbtc', '1', time()+60*60*24, '/');
	}

}
