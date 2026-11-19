<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;

use app\components\rpc\WalletRPC;
use app\models\Accounts;
use app\models\Blocks;
use app\models\Coins;
use app\models\Connections;
use app\models\Earnings;
use app\models\Markets;
use app\models\Mining;
use app\models\Algos;
use app\models\Bookmarks;
use app\models\Notifications;
use app\models\Orders;
use app\models\Workers;
use app\services\CoinService;
use app\services\BlockService;
use app\services\MarketService;
use yii\helpers\ArrayHelper;

class AdminController extends BaseController
{
    public $defaultAction='dashboard';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function(){
                    return $this->goHome();
                }
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
     * Displays Main Dashboard.
     *
     * @return Response|string
     */
    public function actionDashboard()
	{
		return $this->render('dashboard');
	}


    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin)) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack(['/admin/dashboard']);
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /* Dashboard sub-parts */
    public function actionCommon_results()
	{  
        return $this->renderPartial('common_results');
	}

    /////////////////////////////////////////////////
    /* generating data for graphs */

	public function actionGraph_assets_results()
	{
		return $this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		return $this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		return $this->renderPartial('results/graph_profit_results');
	}

    /////////////////////////////////////////////////

    public function actionGraph_market_balance()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_balance', ['id' => $coinid]);
	}

    public function actionGraph_market_prices()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_prices', ['id' => $coinid]);
	}

    /////////////////////////////////////////////////
    /* version breakdown */

    public function actionConfig(): string
    {
        $this->requireAdmin();
        return $this->render('config');
    }

    public function actionVersion(): string
    {
        $this->requireAdmin();
        $util        = Yii::$app->YiimpUtils;
        $currentAlgo = Yii::$app->session->get('yaamp-algo', '');
        $algos       = $util->get_algos() ?? [];

        return $this->render('version', [
            'currentAlgo' => $currentAlgo,
            'algos'       => $algos,
        ]);
    }

    public function actionVersion_results(): string
    {
        $this->requireAdmin();
        $algo = Yii::$app->request->get('algo', '');
        if ($algo !== '') {
            Yii::$app->session->set('yaamp-algo', $algo);
        } else {
            $algo = Yii::$app->session->get('yaamp-algo', '');
        }

        if ($algo === '') {
            return $this->renderPartial('version_results', ['algo' => '', 'rows' => []]);
        }

        $util     = Yii::$app->YiimpUtils;
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        $delay    = time() - $interval;

        // Single query: worker count + valid/invalid hashrate per version
        $rows = Yii::$app->db->createCommand(
            "SELECT
                 w.version,
                 COUNT(DISTINCT w.id)                                                              AS workers,
                 COALESCE(SUM(CASE WHEN s.valid  = 1 THEN s.difficulty ELSE 0 END), 0)
                     * :target / :interval / 1000                                                 AS hashrate,
                 COALESCE(SUM(CASE WHEN s.valid != 1 THEN s.difficulty ELSE 0 END), 0)
                     * :target / :interval / 1000                                                 AS invalid
             FROM workers w
             LEFT JOIN shares s ON s.workerid = w.id AND s.time > :delay
             WHERE w.algo = :algo
             GROUP BY w.version
             ORDER BY workers DESC",
            [':target' => $target, ':interval' => $interval, ':delay' => $delay, ':algo' => $algo]
        )->queryAll();

        return $this->renderPartial('version_results', [
            'algo' => $algo,
            'rows' => $rows,
        ]);
    }

    /////////////////////////////////////////////////
    /* worker list */

    public function actionWorker(): string
    {
        $this->requireAdmin();
        $util        = Yii::$app->YiimpUtils;
        $currentAlgo = Yii::$app->session->get('yaamp-algo', '');
        $algos       = $util->get_algos() ?? [];

        return $this->render('worker', [
            'currentAlgo' => $currentAlgo,
            'algos'       => $algos,
        ]);
    }

    public function actionWorker_results(): string
    {
        $this->requireAdmin();
        $algo = Yii::$app->request->get('algo', '');
        if ($algo !== '') {
            Yii::$app->session->set('yaamp-algo', $algo);
        } else {
            $algo = Yii::$app->session->get('yaamp-algo', '');
        }

        if ($algo === '') {
            return $this->renderPartial('worker_results', [
                'algo' => '', 'workers' => [], 'accounts' => [], 'coins' => [],
                'shareStatsMap' => [], 'shareCountMap' => [],
                'workerBlockMap' => [], 'userBlockMap' => [], 'totalRate' => 0.0,
            ]);
        }

        $util     = Yii::$app->YiimpUtils;
        $db       = Yii::$app->db;
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        $delay    = time() - $interval;

        // ── Workers ───────────────────────────────────────────────────────────
        $workers = Workers::find()->where(['algo' => $algo])->orderBy('name')->all();
        $workerIds = array_map(fn($w) => $w->id, $workers);
        $userIds   = array_values(array_unique(array_filter(array_map(fn($w) => $w->userid, $workers))));

        // ── Batch: accounts + coins ───────────────────────────────────────────
        $accounts = $userIds
            ? Accounts::find()->where(['id' => $userIds])->indexBy('id')->all()
            : [];

        $coinIds = array_values(array_unique(array_filter(array_map(fn($a) => $a->coinid, $accounts))));
        $coins   = $coinIds
            ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()
            : [];

        // ── Batch: share stats (rate + bad-rate) — time-windowed ─────────────
        // Combines the two per-worker queries from worker_rate / worker_rate_bad
        // into a single scan of the shares table.
        $shareStatsMap = [];
        if ($workerIds) {
            $inList = implode(',', array_map('intval', $workerIds));
            foreach ($db->createCommand(
                "SELECT workerid,
                        SUM(CASE WHEN valid=1 THEN difficulty ELSE 0 END)
                            * :target / :interval / 1000                       AS rate,
                        AVG(CASE WHEN valid=1 THEN difficulty ELSE NULL END)   AS avg_valid_diff,
                        SUM(CASE WHEN valid!=1 THEN 1 ELSE 0 END)              AS bad_count
                 FROM shares
                 WHERE time > :delay AND workerid IN ($inList)
                 GROUP BY workerid",
                [':target' => $target, ':interval' => $interval, ':delay' => $delay]
            )->queryAll() as $r) {
                $rate    = (float) $r['rate'];
                $avgDiff = (float) $r['avg_valid_diff'];
                $bad     = (int)   $r['bad_count'];
                $shareStatsMap[(int) $r['workerid']] = [
                    'rate' => $rate,
                    'bad'  => $avgDiff > 0 ? $bad * $avgDiff * $target / $interval / 1000 : 0.0,
                ];
            }
        }
        $totalRate = array_sum(array_column($shareStatsMap, 'rate'));

        // ── Batch: all-time share count per worker ────────────────────────────
        $shareCountMap = [];
        if ($workerIds) {
            foreach ((new \yii\db\Query)->select(['workerid', 'COUNT(id) AS cnt'])
                ->from('shares')->where(['workerid' => $workerIds, 'algo' => $algo])
                ->groupBy('workerid')->all() as $r
            ) {
                $shareCountMap[(int) $r['workerid']] = (int) $r['cnt'];
            }
        }

        // ── Batch: block counts ───────────────────────────────────────────────
        $workerBlockMap = [];
        if ($workerIds) {
            foreach ((new \yii\db\Query)->select(['workerid', 'COUNT(id) AS cnt'])
                ->from('blocks')->where(['workerid' => $workerIds, 'algo' => $algo])
                ->groupBy('workerid')->all() as $r
            ) {
                $workerBlockMap[(int) $r['workerid']] = (int) $r['cnt'];
            }
        }

        $userBlockMap = [];
        if ($userIds) {
            $minTime = (int) $db->createCommand(
                "SELECT MIN(time) FROM workers WHERE algo = :algo", [':algo' => $algo]
            )->queryScalar();

            foreach ((new \yii\db\Query)->select(['userid', 'COUNT(id) AS cnt'])
                ->from('blocks')
                ->where(['userid' => $userIds, 'algo' => $algo])
                ->andWhere(['>', 'time', $minTime])
                ->groupBy('userid')->all() as $r
            ) {
                $userBlockMap[(int) $r['userid']] = (int) $r['cnt'];
            }
        }

        return $this->renderPartial('worker_results', [
            'algo'           => $algo,
            'workers'        => $workers,
            'accounts'       => $accounts,
            'coins'          => $coins,
            'shareStatsMap'  => $shareStatsMap,
            'shareCountMap'  => $shareCountMap,
            'workerBlockMap' => $workerBlockMap,
            'userBlockMap'   => $userBlockMap,
            'totalRate'      => $totalRate,
        ]);
    }

    /////////////////////////////////////////////////
    /* user list */

    public function actionUser(): string
    {
        $this->requireAdmin();
        $symbol = Yii::$app->request->get('symbol', 'all');

        $activeCoins = Coins::find()
            ->where(['enable' => 1])
            ->andWhere(['or',
                ['in', 'id', (new \yii\db\Query)->select('coinid')->from('accounts')->where(['>', 'balance', 0.0001])->distinct()],
                ['in', 'id', (new \yii\db\Query)->select('coinid')->from('earnings')->distinct()],
            ])
            ->orderBy('symbol')
            ->all();

        return $this->render('user', [
            'symbol'      => $symbol,
            'activeCoins' => $activeCoins,
        ]);
    }

    public function actionUser_results(): string
    {
        $this->requireAdmin();
        $symbol = Yii::$app->request->get('symbol', 'all');
        $util   = Yii::$app->YiimpUtils;

        // ── Resolve coin and user list ────────────────────────────────────────
        $coin = null;
        if ($symbol === 'all') {
            $users = Accounts::find()
                ->where(['>', 'balance', 0.001])
                ->orWhere(['in', 'id',
                    (new \yii\db\Query)->select('userid')->from('workers')->distinct(),
                ])
                ->orderBy(['balance' => SORT_DESC])
                ->all();
        } else {
            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                return $this->renderPartial('user_results', [
                    'symbol' => $symbol, 'users' => [], 'coin' => null,
                    'coins' => [], 'rateMap' => [], 'badRateMap' => [],
                    'minerCountMap' => [], 'blockDataMap' => [], 'paidMap' => [],
                ]);
            }
            $users = Accounts::find()
                ->where(['coinid' => $coin->id])
                ->andWhere(['or',
                    ['>', 'balance', 0.001],
                    ['in', 'id', (new \yii\db\Query)->select('userid')->from('workers')->distinct()],
                ])
                ->orderBy(['balance' => SORT_DESC])
                ->all();
        }

        $userIds = array_map(fn($u) => $u->id, $users);

        // ── Batch-load coins ──────────────────────────────────────────────────
        $coinIds = array_values(array_unique(array_filter(array_map(fn($u) => $u->coinid, $users))));
        $coins   = $coinIds ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all() : [];

        // ── Batch aggregate queries ───────────────────────────────────────────
        $rateMap       = [];
        $badRateMap    = [];
        $minerCountMap = [];
        $blockDataMap  = [];
        $paidMap       = [];

        if ($userIds) {
            $db       = Yii::$app->db;
            $target   = $util->hashrate_constant();
            $interval = $util->hashrate_step();
            $delay    = time() - $interval;
            $inList   = implode(',', array_map('intval', $userIds));

            // Hashrate per user
            foreach ($db->createCommand(
                "SELECT userid, SUM(difficulty) * :target / :interval / 1000 AS rate
                 FROM shares WHERE valid=1 AND time > :delay AND userid IN ($inList)
                 GROUP BY userid",
                [':target' => $target, ':interval' => $interval, ':delay' => $delay]
            )->queryAll() as $r) {
                $rateMap[(int) $r['userid']] = (float) $r['rate'];
            }

            // Worker count per user
            foreach ((new \yii\db\Query)->select(['userid', 'COUNT(*) AS cnt'])
                ->from('workers')->where(['userid' => $userIds])->groupBy('userid')->all()
                as $r
            ) {
                $minerCountMap[(int) $r['userid']] = (int) $r['cnt'];
            }

            // Block count + difficulty sum per user (combined single query)
            foreach ((new \yii\db\Query)
                ->select(['userid', 'COUNT(*) AS cnt', 'SUM(difficulty) AS diff_sum'])
                ->from('blocks')->where(['userid' => $userIds])->groupBy('userid')->all()
                as $r
            ) {
                $blockDataMap[(int) $r['userid']] = [
                    'cnt'      => (int)   $r['cnt'],
                    'diff_sum' => (float) $r['diff_sum'],
                ];
            }

            // Paid per user
            foreach ((new \yii\db\Query)->select(['account_id', 'SUM(amount) AS paid'])
                ->from('payouts')->where(['account_id' => $userIds])->groupBy('account_id')->all()
                as $r
            ) {
                $paidMap[(int) $r['account_id']] = (float) $r['paid'];
            }

            // Bad-rate per user (cached in YiimpUtils; no raw DB hit on warm cache)
            foreach ($userIds as $uid) {
                $badRateMap[$uid] = (float) $util->user_rate_bad($uid);
            }
        }

        return $this->renderPartial('user_results', [
            'symbol'        => $symbol,
            'users'         => $users,
            'coin'          => $coin,
            'coins'         => $coins,
            'rateMap'       => $rateMap,
            'badRateMap'    => $badRateMap,
            'minerCountMap' => $minerCountMap,
            'blockDataMap'  => $blockDataMap,
            'paidMap'       => $paidMap,
        ]);
    }

    /////////////////////////////////////////////////
    /* coin list and information */

    public function actionCoinlist()
	{
		$search   = trim(Yii::$app->request->get('q', ''));
		$pageSize = max(10, min(500, (int) Yii::$app->request->get('pageSize', 50)));

		$query = Coins::find()->orderBy(['created' => SORT_DESC]);

		if ($search !== '') {
			$query->andWhere(['or',
				['like', 'name',   $search],
				['like', 'symbol', $search],
				['like', 'algo',   $search],
			]);
		}

		// Compute totals over the full filtered set before the provider adds LIMIT
		$totalInstalled = (clone $query)->andWhere(['installed' => 1])->count();
		$totalActive    = (clone $query)->andWhere(['enable'    => 1])->count();

		$provider = new \yii\data\ActiveDataProvider([
			'query'      => $query,
			'pagination' => [
				'pageSize'      => $pageSize,
				'pageSizeParam' => 'pageSize',
			],
			'sort' => false,
		]);

		// Batch-load market names for all coins on this page
		$coins   = $provider->models;
		$coinIds = array_map(fn($c) => $c->id, $coins);
		$marketsMap = [];
		if ($coinIds) {
			foreach (Markets::find()->select(['coinid', 'name'])
				->where(['coinid' => $coinIds])->asArray()->all() as $row
			) {
				$marketsMap[$row['coinid']][] = $row['name'];
			}
		}

		return $this->render('coinlist', [
			'provider'       => $provider,
			'totalInstalled' => (int) $totalInstalled,
			'totalActive'    => (int) $totalActive,
			'searchQuery'    => $search,
			'pageSize'       => $pageSize,
			'marketsMap'     => $marketsMap,
		]);
	}

	public function actionCoin_create()
	{
		$coin = new Coins;
		$coin->txmessage = true;
		$coin->created = time();

		if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>false));
	}

	public function actionCoin_update()
	{
        $coinid = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne($coinid);

        if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>true));
	}

	/////////////////////////////////////////////////

	public function actionCoinwallets()
	{
		$serverList = Coins::find()
			->select('rpchost')
			->distinct()
			->where(['installed' => 1])
			->orderBy('rpchost')
			->column();

		return $this->render('coinwallets', ['serverList' => $serverList]);
	}

	public function actionCoinwallet_results()
	{
		$server      = Yii::$app->getRequest()->getQueryParam('server');
		$coins       = CoinService::getCoinWalletList($server);
		$mining      = Mining::find()->one();
		$coinIds     = array_map(fn($c) => $c->id, $coins);
		$blockCounts = CoinService::getBlockCountsByCoin($coinIds);

		return $this->renderPartial('coinwallet_results', [
			'coins'       => $coins,
			'mining'      => $mining,
			'blockCounts' => $blockCounts,
		]);
	}

    /////////////////////////////////////////////////

    public function actionCoinwallet()
	{
		return $this->render('coinwallet');
	}

    /**
     * Uninstall a coin: wipe related data, disable the coin record.
     * GET → confirmation page. POST → execute and redirect to coinwallets list.
     * Ports: actionUninstallCoin (legacy).
     */
    public function actionUninstallcoin(): string|\yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if (!$coin) {
            Yii::$app->session->setFlash('error', 'Coin not found.');
            return $this->redirectBack(['/admin/coinwallets']);
        }

        if (Yii::$app->request->isPost) {
            $db = Yii::$app->db;
            $db->createCommand('DELETE FROM exchange_deposit WHERE coinid = :id', [':id' => $id])->execute();
            $db->createCommand('DELETE FROM earnings        WHERE coinid = :id', [':id' => $id])->execute();
            $db->createCommand('DELETE FROM orders          WHERE coinid = :id', [':id' => $id])->execute();
            $db->createCommand('DELETE FROM shares          WHERE coinid = :id', [':id' => $id])->execute();

            $coin->enable        = false;
            $coin->installed     = false;
            $coin->auto_ready    = false;
            $coin->master_wallet = null;
            $coin->mint          = 0;
            $coin->balance       = 0;
            $coin->save();

            Yii::$app->session->setFlash('success', "{$coin->symbol} has been uninstalled.");
            return $this->redirect(['/admin/coinwallets']);
        }

        return $this->render('uninstallcoin_confirm', ['coin' => $coin]);
    }

    public function actionCoinwallet_details()
	{
		$id   = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne($id);
		if (!$coin) {
			return $this->goHome();
		}

		return $this->renderPartial('coinwallet_details', array_merge(
			['coin' => $coin],
			CoinService::getCoinWalletDetails($coin)
		));
	}

	/////////////////////////////////////////////////

	public function actionCoinwallet_create()
	{
		$coin             = new Coins();
		$coin->txmessage  = true;
		$coin->created    = time();
		$coin->index_avg  = 1;
		$coin->difficulty = 1;
		$coin->installed  = 1;
		$coin->visible    = 1;
		$coin->lastblock  = '';

		if (Yii::$app->request->isPost) {
			$coin->setAttributes(Yii::$app->request->post('Coins', []), false);
			if ($coin->validate() && $coin->save()) {
				return $this->redirect(['/admin/coinwallets']);
			}
		}

		$algos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
		return $this->render('coinwallet_form', ['update' => false, 'coin' => $coin, 'algos' => $algos]);
	}

	public function actionCoinwallet_update()
	{
		$coin = Coins::findOne(['id' => (int) Yii::$app->getRequest()->getQueryParam('id')]);
		$txfee = $coin->txfee;

		if($coin && isset($_POST['Coins']))
		{
			$coin->setAttributes($_POST['Coins'], false);

			if($coin->validate() && $coin->save())
			{
				if($txfee != $coin->txfee)
				{
					$remote = new WalletRPC($coin);
					$remote->settxfee($coin->txfee);
				}
				return $this->redirect(array('coinwallet', 'id'=>$coin->id));
			}
		}

		$algos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
		return $this->render('coinwallet_form', ['update' => true, 'coin' => $coin, 'algos' => $algos]);
	}

    /////////////////////////////////////////////////

	public function actionEarning(): string
	{
		$coinId = (int) Yii::$app->request->get('id');
		$coin   = $coinId ? Coins::findOne($coinId) : null;
		return $this->render('earning', ['coinId' => $coinId, 'coin' => $coin]);
	}

	public function actionEarning_results(): string
	{
		$coinId = (int) Yii::$app->request->get('id');

		$query = Earnings::find()
			->where(['!=', 'status', 2])
			->orderBy('create_time DESC')
			->limit(1500);
		if ($coinId) {
			$query->andWhere(['coinid' => $coinId]);
		}
		$earnings = $query->all();

		$coinIds  = array_values(array_unique(array_filter(array_column($earnings, 'coinid'),  fn($v) => $v !== null)));
		$userIds  = array_values(array_unique(array_filter(array_column($earnings, 'userid'),  fn($v) => $v !== null)));
		$blockIds = array_values(array_unique(array_filter(array_column($earnings, 'blockid'), fn($v) => $v !== null)));

		$coins    = $coinIds  ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()    : [];
		$accounts = $userIds  ? Accounts::find()->where(['id' => $userIds])->indexBy('id')->all() : [];
		$blocks   = $blockIds ? Blocks::find()->where(['id' => $blockIds])->indexBy('id')->all()  : [];

		$coin    = $coinId ? ($coins[$coinId] ?? null) : null;
		$cleared = $coinId ? (float) Accounts::find()->where(['coinid' => $coinId])->sum('balance') : 0.0;

		return $this->renderPartial('earning_results', [
			'coinId'   => $coinId,
			'earnings' => $earnings,
			'coins'    => $coins,
			'accounts' => $accounts,
			'blocks'   => $blocks,
			'coin'     => $coin,
			'cleared'  => $cleared,
		]);
	}

	/**
	 * Markets for active coins that have no deposit address or carry an error message.
	 * These need manual attention — either configure the deposit address or clear the error.
	 */
	public function actionEmptymarkets(): string
	{
		$this->requireAdmin();

		$rows = (new \yii\db\Query())
			->select(['markets.id AS marketid', 'markets.coinid'])
			->from('markets')
			->leftJoin('coins', 'coins.id = markets.coinid')
			->where(['coins.installed' => 1, 'coins.enable' => 1])
			->andWhere(['or',
				['markets.deposit_address' => null],
				['markets.deposit_address' => ''],
				['and',
					['not', ['markets.message' => null]],
					['<>', 'markets.message', ''],
				],
			])
			->orderBy(['coins.id' => SORT_DESC, 'markets.id' => SORT_DESC])
			->all();

		$coinIds   = array_values(array_unique(array_column($rows, 'coinid')));
		$marketIds = array_values(array_unique(array_column($rows, 'marketid')));

		$coins   = $coinIds   ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()     : [];
		$markets = $marketIds ? Markets::find()->where(['id' => $marketIds])->indexBy('id')->all() : [];

		return $this->render('emptymarkets', [
			'rows'    => $rows,
			'coins'   => $coins,
			'markets' => $markets,
		]);
	}

    /** Move matured earnings into user balances for a single coin. Ports: actionClearearnings (legacy). */
    public function actionClearearnings(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            try {
                (new \app\services\PaymentService())->clearEarnings($coin->id);
                Yii::$app->session->setFlash('success', "Earnings cleared for {$coin->symbol}.");
            } catch (\Throwable $e) {
                Yii::$app->session->setFlash('error', "Clear earnings failed for {$coin->symbol}: " . $e->getMessage());
            }
        } else {
            Yii::$app->session->setFlash('error', 'Coin not found.');
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    /////////////////////////////////////////////////
    /* monsters — anomaly / high-activity user list */

    public function actionMonsters(): string
    {
        $this->requireAdmin();

        $db   = Yii::$app->db;
        $t24h = time() - 86400;

        // ── Five discovery queries → [userId, reason] pairs ──────────────────
        $rows = [];

        // 1. Workers whose PID is not in any active stratum (stale / ghost)
        foreach ($db->createCommand(
            "SELECT userid FROM shares
             WHERE pid IS NULL OR pid NOT IN (SELECT pid FROM stratums)
             GROUP BY userid"
        )->queryAll() as $r) {
            $rows[] = [(int) $r['userid'], 'pid'];
        }

        // 2. Accounts with balance but no blocks in the last 24 h
        foreach ($db->createCommand(
            "SELECT id FROM accounts
             WHERE balance > 0.001
               AND id NOT IN (
                   SELECT DISTINCT userid FROM blocks
                   WHERE userid IS NOT NULL AND time > :t
               )",
            [':t' => $t24h]
        )->queryAll() as $r) {
            $rows[] = [(int) $r['id'], 'blocks'];
        }

        // 3. Top 5 accounts by total worker count
        foreach ($db->createCommand(
            "SELECT userid, COUNT(*) AS total FROM workers
             GROUP BY userid ORDER BY total DESC LIMIT 5"
        )->queryAll() as $r) {
            $rows[] = [(int) $r['userid'], 'miners'];
        }

        // 4. Top 5 accounts by share count (direct join avoids per-row worker lookup)
        foreach ($db->createCommand(
            "SELECT w.userid, COUNT(s.id) AS total
             FROM shares s JOIN workers w ON w.id = s.workerid
             GROUP BY w.userid ORDER BY total DESC LIMIT 5"
        )->queryAll() as $r) {
            $rows[] = [(int) $r['userid'], 'shares'];
        }

        // 5. All currently locked accounts
        foreach (Accounts::find()->select('id')->where(['is_locked' => 1])->asArray()->all() as $r) {
            $rows[] = [(int) $r['id'], 'locked'];
        }

        // ── Batch-load all referenced data ────────────────────────────────────
        $userIds  = array_values(array_unique(array_column($rows, 0)));
        $accounts = $userIds
            ? Accounts::find()->where(['id' => $userIds])->indexBy('id')->all()
            : [];

        $coinIds = array_values(array_unique(array_filter(
            array_map(fn($a) => $a->coinid, $accounts)
        )));
        $coins = $coinIds
            ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()
            : [];

        // Aggregate: total paid per user
        $paidMap = [];
        if ($userIds) {
            foreach ((new \yii\db\Query)->select(['account_id', 'SUM(amount) AS paid'])
                ->from('payouts')->where(['account_id' => $userIds])->groupBy('account_id')->all()
                as $r
            ) {
                $paidMap[(int) $r['account_id']] = (float) $r['paid'];
            }
        }

        // Aggregate: worker count per user
        $workerCountMap = [];
        if ($userIds) {
            foreach ((new \yii\db\Query)->select(['userid', 'COUNT(*) AS cnt'])
                ->from('workers')->where(['userid' => $userIds])->groupBy('userid')->all()
                as $r
            ) {
                $workerCountMap[(int) $r['userid']] = (int) $r['cnt'];
            }
        }

        // Aggregate: share count per user (via workers join)
        $shareCountMap = [];
        if ($userIds) {
            $inList = implode(',', array_map('intval', $userIds));
            foreach ($db->createCommand(
                "SELECT w.userid, COUNT(s.id) AS cnt
                 FROM shares s JOIN workers w ON w.id = s.workerid
                 WHERE w.userid IN ($inList)
                 GROUP BY w.userid"
            )->queryAll() as $r) {
                $shareCountMap[(int) $r['userid']] = (int) $r['cnt'];
            }
        }

        // Aggregate: block count per user in the last 24 h
        $blockCountMap = [];
        if ($userIds) {
            foreach ((new \yii\db\Query)->select(['userid', 'COUNT(*) AS cnt'])
                ->from('blocks')->where(['userid' => $userIds])
                ->andWhere(['>', 'time', $t24h])->groupBy('userid')->all()
                as $r
            ) {
                $blockCountMap[(int) $r['userid']] = (int) $r['cnt'];
            }
        }

        return $this->render('monsters', [
            'rows'           => $rows,
            'accounts'       => $accounts,
            'coins'          => $coins,
            'paidMap'        => $paidMap,
            'workerCountMap' => $workerCountMap,
            'shareCountMap'  => $shareCountMap,
            'blockCountMap'  => $blockCountMap,
        ]);
    }

    /////////////////////////////////////////////////
    /* payments list */

    public function actionPayments(): string
    {
        $this->requireAdmin();
        $coinId = (int) Yii::$app->request->get('id', 0);
        $coin   = $coinId ? Coins::findOne($coinId) : null;
        return $this->render('payments', ['coinId' => $coinId, 'coin' => $coin]);
    }

    public function actionPayments_results(): string
    {
        $this->requireAdmin();
        $coinId = (int) Yii::$app->request->get('id', 0);

        // Immature earnings per (coin, user) — one aggregate query
        $immatureRows = Yii::$app->db->createCommand(
            'SELECT coinid, userid, SUM(amount) AS immature FROM earnings WHERE status = 0'
            . ($coinId ? ' AND coinid = :cid' : '')
            . ' GROUP BY coinid, userid',
            $coinId ? [':cid' => $coinId] : []
        )->queryAll();
        $immatureMap = [];
        foreach ($immatureRows as $row) {
            $immatureMap["{$row['coinid']}-{$row['userid']}"] = (float) $row['immature'];
        }

        // Failed payouts (no tx) per account — one aggregate query
        $failedRows = Yii::$app->db->createCommand(
            "SELECT account_id, SUM(amount) AS failed
             FROM payouts WHERE (tx IS NULL OR tx = '') AND completed = 0
             GROUP BY account_id"
        )->queryAll();
        $failedMap = [];
        foreach ($failedRows as $row) {
            $failedMap[(int) $row['account_id']] = (float) $row['failed'];
        }

        // Active user list
        $query = Accounts::find()
            ->where(['!=', 'is_locked', 1])
            ->andWhere(['or',
                ['>', 'balance', 0],
                ['>', 'last_earning', time() - 3600],
                ['in', 'id',
                    (new \yii\db\Query)->select('account_id')->from('payouts')
                        ->where(['or', ['tx' => null], ['tx' => '']])->distinct(),
                ],
            ])
            ->orderBy(['last_earning' => SORT_DESC]);

        if ($coinId) {
            $query->andWhere(['coinid' => $coinId]);
        } else {
            $query->limit(100);
        }
        $list = $query->all();

        // Batch-load all referenced coins in one query
        $coinIds = array_values(array_unique(array_filter(array_map(fn($u) => $u->coinid, $list))));
        $coins   = $coinIds ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all() : [];

        $coin = $coinId ? ($coins[$coinId] ?? null) : null;

        return $this->renderPartial('payments_results', [
            'coinId'      => $coinId,
            'list'        => $list,
            'coins'       => $coins,
            'coin'        => $coin,
            'immatureMap' => $immatureMap,
            'failedMap'   => $failedMap,
        ]);
    }

    /** Restore a single user's failed payouts (no tx) to their balance. */
    public function actionCancelUserPayment(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $user = \app\models\Accounts::findOne($id);
        if ($user) {
            (new \app\services\PaymentService())->cancelFailedPayment($user->id);
        }
        return $this->redirectBack();
    }

    /** Restore all failed payouts within 48 h for a coin back to user balances. */
    public function actionCancelUsersPayment(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = \app\models\Coins::findOne($id);

        if (!$coin) {
            Yii::$app->session->setFlash('error', 'Invalid coin id!');
            return $this->redirectBack();
        }

        $since   = time() - 48 * 3600;
        $failed  = \app\models\Payouts::find()
            ->where(['idcoin' => $coin->id])
            ->andWhere(['or', ['tx' => null], ['tx' => '']])
            ->andWhere(['>', 'time', $since])
            ->all();

        $totalAmount = 0.0;
        $count       = 0;

        foreach ($failed as $payout) {
            $user = \app\models\Accounts::findOne((int) $payout->account_id);
            if ($user) {
                $user->balance += (float) $payout->amount;
                if ($user->save()) {
                    $totalAmount += (float) $payout->amount;
                    $count++;
                }
            }
            $payout->delete();
        }

        $msg = $count
            ? "Restored {$count} failed tx(s) to user balances: {$totalAmount} {$coin->symbol}"
            : 'No failed txs found';
        Yii::$app->session->setFlash('success', $msg);
        return $this->redirectBack();
    }

    /////////////////////////////////////////////////

    public function actionBalances(): string
    {
        $this->requireAdmin();
        $exch = Yii::$app->request->get('exch', '');
        return $this->render('balances', ['exch' => $exch]);
    }

    public function actionBalances_results(): string
    {
        $this->requireAdmin();
        $exch   = Yii::$app->request->get('exch', '');
        $mining = Mining::find()->one() ?? new Mining(['usdbtc' => 0]);
        $usdbtc = (float) $mining->usdbtc;

        // Match 'nonkyc' (BTC) and 'nonkyc USDT', 'nonkyc BTC', etc. (alt-base markets)
        $markets = Markets::find()
            ->where('name = :exch OR name LIKE :prefix', [
                ':exch'   => $exch,
                ':prefix' => $exch . ' %',
            ])
            ->orderBy(new \yii\db\Expression('(balance + ontrade) * price DESC'))
            ->all();

        // Batch-load all referenced coins in one query
        $coinIds = array_values(array_unique(array_filter(array_map(fn($m) => $m->coinid, $markets))));
        $coins   = $coinIds ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all() : [];

        // Precompute BTC-per-base-unit rates for all unique base currencies
        $bases = array_values(array_unique(array_map(fn($m) => $m->base_coin ?: 'BTC', $markets)));
        $otherBases = array_values(array_filter($bases, fn($b) => !in_array($b, ['BTC', 'USDT', 'USDC', 'USD'], true)));

        $baseCoinPrices = $otherBases
            ? Coins::find()->select(['symbol', 'price'])->where(['symbol' => $otherBases])->indexBy('symbol')->all()
            : [];

        $btcRateMap = ['BTC' => 1.0];
        foreach (['USDT', 'USDC', 'USD'] as $stable) {
            $btcRateMap[$stable] = $usdbtc > 0 ? 1.0 / $usdbtc : 0.0;
        }
        foreach ($otherBases as $base) {
            $btcRateMap[$base] = isset($baseCoinPrices[$base]) ? (float) $baseCoinPrices[$base]->price : 0.0;
        }

        return $this->renderPartial('balances_results', [
            'exch'       => $exch,
            'markets'    => $markets,
            'mining'     => $mining,
            'coins'      => $coins,
            'btcRateMap' => $btcRateMap,
        ]);
    }

    public function actionBalanceUpdate(): \yii\web\Response
    {
        $this->requireAdmin();
        $id     = (int) Yii::$app->request->get('market');
        $market = \app\models\Markets::findOne($id);
        if ($market) {
            \app\exchanges\ExchangeFactory::make($market->name)->updateMarkets();
            return $this->redirect(['/admin/balances', 'exch' => $market->name]);
        }
        return $this->redirectBack();
    }

    /////////////////////////////////////////////////

	public function actionExchange()
	{
		return $this->render('exchange');
	}

	public function actionExchange_results()
	{
		$minsent = time() - 2 * 3600;

		$stuckMarkets = Markets::find()
			->where('lastsent < :minsent AND lastsent > lasttraded', [':minsent' => $minsent])
			->orderBy('lastsent')
			->all();

		$orders = Orders::find()->orderBy('(amount*bid) desc')->all();

		$deposits = \app\models\Exchange_deposit::find()
			->orderBy('send_time desc')
			->limit(150)
			->all();

		// Collect all referenced coinids across all three sections, batch in one query
		$coinIds = array_values(array_unique(array_filter(array_merge(
			array_map(fn($m) => $m->coinid,  $stuckMarkets),
			array_map(fn($o) => $o->coinid,  $orders),
			array_map(fn($d) => $d->coinid,  $deposits),
		))));
		$coins = $coinIds
			? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()
			: [];

		return $this->renderPartial('exchange_results', [
			'stuckMarkets' => $stuckMarkets,
			'orders'       => $orders,
			'deposits'     => $deposits,
			'coins'        => $coins,
		]);
	}

    /** Soft-delete an exchange deposit: mark as deleted, zero price, set receive time. */
    public function actionDeleteexchangedeposit(): \yii\web\Response
    {
        $this->requireAdmin();
        $deposit = \app\models\Exchange_deposit::findOne((int) Yii::$app->request->get('id'));
        if ($deposit) {
            $deposit->status       = 'deleted';
            $deposit->price        = 0;
            $deposit->receive_time = time();
            $deposit->save();
        }
        return $this->redirectBack(['/admin/exchange']);
    }

	public function actionClearmarket()
	{
		$this->requireAdmin();
		$market = Markets::findOne((int) Yii::$app->request->get('id'));
		if ($market) {
			$market->lastsent = null;
			$market->save(false);
		}
		return $this->redirectBack(['/admin/exchange']);
	}

	public function actionClearorder()
	{
		$this->requireAdmin();
		$order = Orders::findOne((int) Yii::$app->request->get('id'));
		if ($order) {
			$order->delete();
		}
		return $this->redirectBack(['/admin/exchange']);
	}

    /////////////////////////////////////////////////

    public function actionUpdateprice(): \yii\web\Response
    {
        $this->requireAdmin();
        try {
            $svc = new MarketService();
            $svc->updatePrices();
            Yii::$app->session->setFlash('success', 'Market prices updated successfully.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', 'Price update failed: ' . $e->getMessage());
        }
        return $this->redirectBack(['/admin/dashboard']);
    }

    /** Delete all earnings for a coin with a server-side confirmation step. Ports: actionDeleteEarnings (legacy). */
    public function actionDeleteearnings(): string|\yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if (!$coin) {
            Yii::$app->session->setFlash('error', 'Coin not found.');
            return $this->redirectBack(['/admin/coinwallets']);
        }

        if (Yii::$app->request->isPost) {
            Yii::$app->db->createCommand(
                'DELETE FROM earnings WHERE coinid = :id', [':id' => $coin->id]
            )->execute();
            Yii::$app->session->setFlash('success', "All earnings for {$coin->symbol} have been deleted.");
            return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
        }

        return $this->render('deleteearnings_confirm', ['coin' => $coin]);
    }

    /** Run full block update cycle for a single coin. Ports: actionCheckblocks (legacy). */
    public function actionCheckblocks(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            try {
                $svc = new \app\services\BlockService();
                $svc->processNewBlocks($id);
                $svc->updateBlockConfirmations($id);
                $svc->scanTransactions($id);
                $svc->updatePoolBalances($id);
                Yii::$app->session->setFlash('success', "Blocks updated for {$coin->symbol}.");
            } catch (\Throwable $e) {
                Yii::$app->session->setFlash('error', "Block update failed for {$coin->symbol}: " . $e->getMessage());
            }
        } else {
            Yii::$app->session->setFlash('error', 'Coin not found.');
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    /** Trigger on-demand payout for a single coin. Ports: actionPayuserscoin (legacy). */
    public function actionPayuserscoin(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            try {
                (new \app\services\PaymentService())->payCoin($coin);
                Yii::$app->session->setFlash('success', "Payments processed for {$coin->symbol}.");
            } catch (\Throwable $e) {
                Yii::$app->session->setFlash('error', "Payment failed for {$coin->symbol}: " . $e->getMessage());
            }
        } else {
            Yii::$app->session->setFlash('error', 'Coin not found.');
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    /////////////////////////////////////////////////
   	public function actionMemcached()
	{
		return $this->render('memcached');
	}

    /////////////////////////////////////////////////

	public function actionConnections()
	{
		return $this->render('connections');
	}

	public function actionConnections_results()
	{
		$list     = Connections::find()->orderBy('id desc')->all();
		$lastTime = Connections::find()->max('last');

		return $this->renderPartial('connections_results', [
			'list'     => $list,
			'lastTime' => $lastTime,
		]);
	}

	/////////////////////////////////////////////////
	/* coin peer management */

	public function actionCoinpeers(): string|\yii\web\Response
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$coin = Coins::findOne($id);
		if (!$coin) {
			return $this->redirectBack();
		}

		$remote = new WalletRPC($coin);
		$info   = $remote->error === null ? $remote->getinfo() : false;
		$list   = ($remote->error === null) ? ($remote->getpeerinfo() ?: []) : [];

		return $this->render('coin_peers', [
			'coin'  => $coin,
			'info'  => $info,
			'list'  => $list,
		]);
	}

	public function actionCoinpeerRemove()
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$node = Yii::$app->request->get('node', '');
		$coin = Coins::findOne($id);

		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding === 'DCR') {
				$res = $remote->node('disconnect', $node);
				if (!$res) {
					$remote->node('remove', $node);
				}
			} else {
				$res = $remote->addnode($node, 'remove');
				if (!$res && $remote->error) {
					Yii::$app->session->setFlash('error', "{$node} {$remote->error}");
				}
			}
		}

		return $this->redirectBack(['coinpeers', 'id' => $id]);
	}

	public function actionCoinpeerAdd()
	{
		$this->requireAdmin();
		if (!Yii::$app->request->isPost) {
			return $this->redirectBack();
		}
		$id   = (int) Yii::$app->request->get('id');
		$node = trim(Yii::$app->request->post('node', ''));
		$coin = Coins::findOne($id);

		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding === 'DCR') {
				$remote->addnode($node, 'add');
				usleep(500_000);
				$remote->node('connect', $node);
				sleep(1);
			} else {
				$res = $remote->addnode($node, 'add');
				if (!$res && $remote->error) {
					Yii::$app->session->setFlash('error', "{$node} {$remote->error}");
				} else {
					sleep(1);
				}
			}
		}

		return $this->redirect(['coinpeers', 'id' => $id]);
	}

	/////////////////////////////////////////////////
	/* botnets — accounts mining from an abnormally high number of distinct IPs */

	public function actionBotnets(): string
	{
		$this->requireAdmin();

		$rows = Yii::$app->db->createCommand(
			'SELECT userid, algo, pid,
			        MAX(time)          AS time,
			        COUNT(userid)      AS workers,
			        COUNT(DISTINCT ip) AS ips,
			        MAX(version)       AS version
			 FROM workers
			 GROUP BY userid, algo, pid
			 HAVING ips > 10
			 ORDER BY ips DESC'
		)->queryAll();

		$userIds  = array_values(array_unique(array_filter(array_column($rows, 'userid'))));
		$accounts = $userIds
			? Accounts::find()->where(['id' => $userIds])->indexBy('id')->all()
			: [];

		$coinIds = array_values(array_unique(array_filter(array_map(fn($a) => $a->coinid, $accounts))));
		$coins   = $coinIds
			? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()
			: [];

		return $this->render('botnets', [
			'rows'     => $rows,
			'accounts' => $accounts,
			'coins'    => $coins,
		]);
	}

	public function actionLoguser(): \yii\web\Response
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$en   = (int) Yii::$app->request->get('en', 0);
		$user = \app\models\Accounts::findOne($id);
		if ($user) {
			$user->logtraffic = $en;
			$user->save();
		}
		return $this->redirectBack();
	}

	public function actionBlockuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$wallet = trim((string) Yii::$app->request->get('wallet', ''));
		$user   = \app\models\Accounts::find()->where(['username' => $wallet])->one();
		if ($user) {
			$user->is_locked = true;
			$user->save();
		}
		return $this->redirectBack();
	}

	public function actionUnblockuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$wallet = trim((string) Yii::$app->request->get('wallet', ''));
		$user   = \app\models\Accounts::find()->where(['username' => $wallet])->one();
		if ($user) {
			$user->is_locked = false;
			$user->save();
		}
		return $this->redirectBack();
	}

	public function actionBanuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$user = \app\models\Accounts::findOne($id);
		if ($user) {
			$user->is_locked = true;
			$user->balance   = 0;
			$user->save();
		}
		return $this->redirectBack();
	}

    /** Interactive RPC console for a coin wallet. Ports: actionCoinConsole (legacy). */
    public function actionCoinwalletConsole(): string|\yii\web\Response
    {
        $this->requireAdmin();
        if (!defined('YIIMP_ADMIN_WEBCONSOLE') || !YIIMP_ADMIN_WEBCONSOLE) {
            throw new \yii\web\ForbiddenHttpException('Web console is disabled.');
        }

        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if (!$coin) {
            return $this->redirectBack(['/admin/coinwallets']);
        }

        $remote = new \app\components\rpc\WalletRPC($coin);
        $info   = $remote->error === null ? $remote->getinfo() : false;

        $query  = '';
        $result = null;
        $rpcErr = null;

        if (Yii::$app->request->isPost) {
            $query = trim(Yii::$app->request->post('query', ''));
            if ($query !== '') {
                $result = $remote->execute($query);
                if ($result === false) {
                    $rpcErr = $remote->error;
                    $result = null;
                }
                Yii::info("{$coin->symbol} CONSOLE {$query}", __CLASS__);
            }
        }

        return $this->render('coinwallet_console', [
            'coin'   => $coin,
            'info'   => $info,
            'query'  => $query,
            'result' => $result,
            'rpcErr' => $rpcErr,
        ]);
    }

    /** Signal the daemon to stop: disable coin, clear connections. Ports: actionStopCoin (legacy). */
    public function actionStopcoin(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            $coin->action      = 2;
            $coin->enable      = false;
            $coin->auto_ready  = false;
            $coin->connections = 0;
            $coin->save();
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    /////////////////////////////////////////////////
    /* coinwallet auto_ready toggle */

    public function actionCoinwalletSetauto(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            $coin->auto_ready = true;
            $coin->save();
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    public function actionCoinwalletUnsetauto(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if ($coin) {
            $coin->auto_ready = false;
            $coin->save();
        }
        return $this->redirectBack(['/admin/coinwallet', 'id' => $id]);
    }

    /////////////////////////////////////////////////
    /* coin triggers (notification rules) */

    public function actionCointriggers(): string|\yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if (!$coin) {
            return $this->redirectBack(['/admin/coinwallets']);
        }

        $remote        = new \app\components\rpc\WalletRPC($coin);
        $info          = $remote->error === null ? $remote->getinfo() : false;
        $notifications = Notifications::find()->where(['idcoin' => $coin->id])->all();

        return $this->render('cointriggers', [
            'coin'          => $coin,
            'info'          => $info,
            'notifications' => $notifications,
        ]);
    }

    public function actionCointriggerEnable(): \yii\web\Response
    {
        $this->requireAdmin();
        $rule = Notifications::findOne((int) Yii::$app->request->get('id'));
        if ($rule) {
            $rule->enabled = (int) Yii::$app->request->get('en', 0);
            $rule->save();
        }
        return $this->redirectBack(['/admin/cointriggers']);
    }

    public function actionCointriggerReset(): \yii\web\Response
    {
        $this->requireAdmin();
        $rule = Notifications::findOne((int) Yii::$app->request->get('id'));
        if ($rule) {
            $rule->lasttriggered = 0;
            $rule->save();
        }
        return $this->redirectBack(['/admin/cointriggers']);
    }

    public function actionCointriggerDel(): \yii\web\Response
    {
        $this->requireAdmin();
        $rule = Notifications::findOne((int) Yii::$app->request->get('id'));
        if ($rule) {
            $rule->delete();
        }
        return $this->redirectBack(['/admin/cointriggers']);
    }

    public function actionCointriggerAdd(): \yii\web\Response
    {
        $this->requireAdmin();
        if (!Yii::$app->request->isPost) {
            return $this->redirectBack();
        }

        $id   = (int) Yii::$app->request->get('id');
        $coin = Coins::findOne($id);
        if (!$coin) {
            return $this->redirectBack(['/admin/coinwallets']);
        }

        $post          = Yii::$app->request->post();
        $conditionType = trim($post['conditiontype'] ?? '');

        if (count(explode(' ', $conditionType)) < 2) {
            Yii::$app->session->setFlash('error', "Missing space in condition — example: 'balance <' 5");
            return $this->redirect(['/admin/cointriggers', 'id' => $id]);
        }

        $rule                 = new Notifications();
        $rule->idcoin         = $coin->id;
        $rule->notifytype     = $post['notifytype']     ?? 'email';
        $rule->conditiontype  = $conditionType;
        $rule->conditionvalue = (float) ($post['conditionvalue'] ?? 0);
        $rule->notifycmd      = trim($post['notifycmd']    ?? '');
        $rule->description    = trim($post['description'] ?? '');
        $rule->enabled        = 1;
        $rule->lastchecked    = 0;
        $rule->lasttriggered  = 0;
        $rule->save();

        return $this->redirect(['/admin/cointriggers', 'id' => $id]);
    }

	/////////////////////////////////////////////////

	public function actionBookmarkAdd(): mixed
	{
		$this->requireAdmin();
		$coin = Coins::findOne((int) Yii::$app->request->get('id'));
		if (!$coin) {
			return $this->redirectBack();
		}

		$bookmark          = new Bookmarks();
		$bookmark->idcoin  = $coin->id;

		if (Yii::$app->request->isPost) {
			$bookmark->setAttributes(Yii::$app->request->post('Bookmarks', []), false);
			if ($bookmark->save()) {
				return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
			}
		}

		return $this->render('bookmark', ['bookmark' => $bookmark, 'coin' => $coin]);
	}

	public function actionBookmarkEdit(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if (!$bookmark) {
			Yii::$app->session->setFlash('error', 'invalid bookmark');
			return $this->redirectBack();
		}

		$coin = Coins::findOne($bookmark->idcoin);

		if (Yii::$app->request->isPost) {
			$bookmark->setAttributes(Yii::$app->request->post('Bookmarks', []), false);
			if ($bookmark->save()) {
				return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
			}
		}

		return $this->render('bookmark', ['bookmark' => $bookmark, 'coin' => $coin]);
	}

	public function actionBookmarkDel(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if ($bookmark) {
			$bookmark->delete();
		}
		return $this->redirectBack();
	}

	public function actionBookmarkSend(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if (!$bookmark) {
			return $this->redirectBack();
		}

		$coin   = Coins::findOne($bookmark->idcoin);
		$amount = (float) Yii::$app->request->get('amount', 0);

		$remote = new WalletRPC($coin);
		$info   = $remote->getinfo();

		if (!$info || empty($info['balance'])) {
			Yii::$app->session->setFlash('error', "not enough balance {$coin->name}");
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$depositInfo = $remote->validateaddress($bookmark->address);
		if (!$depositInfo || empty($depositInfo['isvalid'])) {
			Yii::$app->session->setFlash('error', "invalid address for {$coin->name}, {$bookmark->address}");
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$amount = round(min($amount, $info['balance'] - ($info['paytxfee'] ?? 0)), 8);

		$tx = $remote->sendtoaddress($bookmark->address, $amount);
		if (!$tx) {
			Yii::$app->session->setFlash('error', $remote->error);
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$bookmark->lastused = time();
		$bookmark->save();

		(new BlockService())->updatePoolBalances($coin->id);

		return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
	}

	/////////////////////////////////////////////////

	public function actionBenchdel(): \yii\web\Response
	{
		$this->requireAdmin();
		$id = (int) Yii::$app->request->get('id');
		if ($id > 0) {
			Yii::$app->db->createCommand('DELETE FROM benchmarks WHERE id = :id', [':id' => $id])->execute();
		}
		return $this->redirectBack(['/bench']);
	}

	/** Redirect to the HTTP Referer, falling back to $fallback (default: home). */
	private function redirectBack(array|string|null $fallback = null): \yii\web\Response
	{
		return $this->redirect(Yii::$app->request->referrer ?: ($fallback ?? Yii::$app->homeUrl));
	}

	/** Abort with 403 if the current user is not an admin. */
	private function requireAdmin(): void
	{
		$identity = Yii::$app->user->identity;
		if (!$identity || !$identity->is_admin) {
			throw new \yii\web\ForbiddenHttpException();
		}
	}

}