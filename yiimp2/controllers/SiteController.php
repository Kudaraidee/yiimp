<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\models\Coins;

class SiteController extends Controller
{
    /**
     * Before action handler - log public page access for debugging
     * 
     * Requirements 1.1, 1.2, 4.1, 4.2, 4.5: Ensure public pages are accessible
     * 
     * @param \yii\base\Action $action
     * @return bool
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        
        // Log public page access for debugging (Requirements 1.1, 1.2, 4.1, 4.2, 4.5)
        try {
            Yii::info([
                'message' => 'SiteController action accessed',
                'controller' => $this->id,
                'action' => $action->id,
                'url' => Yii::$app->request->url ?? 'N/A',
                'is_guest' => Yii::$app->user->isGuest,
                'session_id' => Yii::$app->session->getId(),
                'session_active' => Yii::$app->session->getIsActive(),
                'has_session_cookie' => isset($_COOKIE[Yii::$app->session->getName()]),
                'user_agent' => Yii::$app->request->getUserAgent(),
            ], __METHOD__);
        } catch (\Exception $e) {
            // Ignore logging errors during error handling
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $address = Yii::$app->getRequest()->getQueryParam('address');
        
        if (!is_null($address)) {
            // Pass the address as username to the wallet view
            return $this->render('wallet', [
                'username' => $address,
                'address' => $address,
            ]);
        } else {
            return $this->render('index');
        }
    }

    /**
     * Mining page action.
     *
     * @return string
     */
    public function actionMining()
    {
        $algo = Yii::$app->request->get('algo', '');
        $homeUrl = Yii::$app->homeUrl;
        
        return $this->render('mining', [
            'algo' => $algo,
            'homeUrl' => $homeUrl,
        ]);
    }

    /**
     * Api page action.
     *
     * @return string
     */
    public function actionApi()
	{
		return $this->render('api');
	}

    /**
     * About page action.
     *
     * @return string
     */
    public function actionAbout()
	{
		return $this->render('about');
	}

    /**
     * Terms page action.
     *
     * @return string
     */
    public function actionTerms()
	{
		return $this->render('terms');
	}

    /**
     * Privacy page action.
     *
     * @return string
     */
    public function actionPrivacy()
	{
		return $this->render('privacy');
	}

    /**
     * Benchmarks page action.
     *
     * @return string
     */
	public function actionBenchmarks()
	{
		return $this->render('benchmarks');
	}

    /**
     * Bookmarks page action.
     *
     * @return string
     */
	public function actionBookmarks()
	{
		return $this->render('bookmarks');
	}

    /**
     * Diff page action.
     *
     * @return string
     */
	public function actionDiff()
	{
		return $this->render('diff');
	}

    /**
     * Multialgo page action.
     *
     * @return string
     */
	public function actionMultialgo()
	{
		return $this->render('multialgo');
	}

    /**
     * Miners page action.
     *
     * @return string
     */
    public function actionMiners()
	{
		return $this->render('miners');
	}

    public function actionMiners_results()
	{
		return $this->renderPartial('results/miners_results');
	}

    // Home Tab : Pool Stats (algo) on the bottom right
	public function actionHistory_results()
	{
		return $this->renderPartialAlgoMemcached('results/history_results');
	}

    // Home Tab : Coin Information (algo) on the bottom right
	public function actionCoins_info()
	{
		return $this->renderPartialAlgoMemcached('results/coins_info');
	}

    // Pool Status : public right panel with all algos and live stats
	public function actionCurrent_results()
	{
		return $this->renderPartialAlgoMemcached('results/current_results', 30);
	}
    public function actionFound_results()
	{
		return $this->renderPartialAlgoMemcached('results/found_results');
	}
    // Pool Tab : Top left panel with estimated profit per coin
	public function actionMining_results()
	{
		if ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin))
			return $this->renderPartial('results/mining_results');
		else
			return $this->renderPartialAlgoMemcached('results/mining_results');
	}

    // Pool tab: graph algo pool hashrate (json data)
	public function actionGraph_hashrate_results()
	{
		return $this->renderPartialAlgoMemcached('results/graph_hashrate_results');
	}

    // Pool tab: graph algo estimate history (json data)
	public function actionGraph_price_results()
	{
		return $this->renderPartialAlgoMemcached('results/graph_price_results');
	}

   	public function actionWallet_results()
	{
		return $this->renderPartial('results/wallet_results');
	}

	public function actionWallet_miners_results()
	{
		return $this->renderPartial('results/wallet_miners_results');
	}

	public function actionWallet_graphs_results()
	{
		return $this->renderPartial('results/wallet_graphs_results');
	}
	public function actionGraph_earnings_results()
	{
		return $this->renderPartial('results/graph_earnings_results');
	}

	public function actionUser_earning_results()
	{
		return $this->renderPartial('results/user_earning_results');
	}

	public function actionWallet_found_results()
	{
		return $this->renderPartial('results/wallet_found_results');
	}
    public function actionGraph_user_results()
	{
		return $this->renderPartial('results/graph_user_results');
	}
	public function actionTitle_results()
	{
        $user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
		if($user)
		{
			$balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance);
			$coin = Coins::find()->where(['id'=>$user->coinid])->one();

            if($coin)
				return "$balance $coin->symbol - ".YAAMP_SITE_NAME;
			else
				return "$balance - ".YAAMP_SITE_NAME;
		}
		else
			return YAAMP_SITE_URL;
	}

    /////////////////////////////////////////////////

	public function actionBlock()
	{
		return $this->render('block');
		
	}

	public function actionBlock_results()
	{
		return $this->renderPartial('block_results');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionTx()
	{
		return $this->renderPartial('tx');
	}

	////////////////////////////////////////////////////////////////////////////////////////

    public function actionAlgo()
	{
		$algo = Yii::$app->YiimpUtils->get_algo_param();
        $a = (new \yii\db\Query())
                ->select(['name'])
                ->from('algos')
                ->where(['name' => $algo])->scalar();

        if($a)
			Yii::$app->session->set('yaamp-algo', $a);
		else
			Yii::$app->session->set('yaamp-algo', 'all');

		$route = Yii::$app->getRequest()->getQueryParam('r');
		if (!empty($route))
			return $this->redirect($route);
		else
			return $this->goback();
	}

    protected function renderPartialAlgoMemcached($partial, $cachetime=15)
	{
		$algo = Yii::$app->session->get('yaamp-algo')?:'all';
		$memcache = Yii::$app->cache;
		$memkey = $algo.'_'.str_replace('/','_',$partial);
		$html = $memcache->get($memkey);

		if (!empty($html)) {
			return $html;
		}

		$html = $this->renderPartial($partial);
		$memcache->set($memkey, $html, $cachetime);

        return $html;
	}

}
