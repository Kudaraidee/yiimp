<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\models\Accounts;
use app\models\Algos;
use app\models\Blocks;
use app\models\Coins;

class SiteController extends BaseController
{
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
        
        if (!is_null($address))
            return $this->render('wallet');
        else
            return $this->render('index');
    }

    /**
     * Mining page action.
     *
     * @return string
     */
    public function actionMining()
    {
        return $this->render('mining');
    }

    /**
     * API documentation page (Swagger UI).
     */
    public function actionApi(): string
    {
        return $this->render('api');
    }

    /**
     * OpenAPI 3.0 specification served as JSON — consumed by Swagger UI.
     * Dynamically includes rental endpoints when YIIMP_RENTAL is enabled.
     */
    public function actionApiSpec(): Response
    {
        $baseUrl       = 'http://' . (defined('YIIMP_SITE_URL') ? YIIMP_SITE_URL : Yii::$app->request->hostName);
        $hasRental     = defined('YIIMP_RENTAL')             && YIIMP_RENTAL;
        $hasPayouts    = defined('YIIMP_API_PAYOUTS')        && YIIMP_API_PAYOUTS;
        $payoutHours   = defined('YIIMP_API_PAYOUTS_PERIOD') ? (int) (YIIMP_API_PAYOUTS_PERIOD / 3600) : 24;
        $siteName      = defined('YIIMP_SITE_NAME')          ? YIIMP_SITE_NAME : 'Yiimp';

        // ── Reusable response schemas ─────────────────────────────────────────
        $schemas = [
            'WalletStatus' => [
                'type' => 'object',
                'properties' => [
                    'unsold'   => ['type' => 'number', 'format' => 'double', 'example' => 0.00050362],
                    'balance'  => ['type' => 'number', 'format' => 'double', 'example' => 0.00000000],
                    'unpaid'   => ['type' => 'number', 'format' => 'double', 'example' => 0.00050362],
                    'paid24h'  => ['type' => 'number', 'format' => 'double', 'example' => 0.00000000],
                    'total'    => ['type' => 'number', 'format' => 'double', 'example' => 0.00050362],
                ],
            ],
            'MinerEntry' => [
                'type' => 'object',
                'properties' => [
                    'version'    => ['type' => 'string',  'example' => 'ccminer/1.8.2'],
                    'password'   => ['type' => 'string',  'example' => 'd=96'],
                    'ID'         => ['type' => 'string',  'example' => ''],
                    'algo'       => ['type' => 'string',  'example' => 'x11'],
                    'difficulty' => ['type' => 'number',  'example' => 96],
                    'subscribe'  => ['type' => 'integer', 'example' => 1],
                    'accepted'   => ['type' => 'number',  'example' => 82463372.083],
                    'rejected'   => ['type' => 'number',  'example' => 0],
                ],
            ],
            'AlgoStatus' => [
                'type' => 'object',
                'properties' => [
                    'name'              => ['type' => 'string',  'example' => 'x11'],
                    'port'              => ['type' => 'integer', 'example' => 3533],
                    'coins'             => ['type' => 'integer', 'example' => 10],
                    'fees'              => ['type' => 'number',  'example' => 1],
                    'hashrate'          => ['type' => 'integer', 'example' => 269473938],
                    'workers'           => ['type' => 'integer', 'example' => 5],
                    'estimate_current'  => ['type' => 'string',  'example' => '0.00053653'],
                    'estimate_last24h'  => ['type' => 'string',  'example' => '0.00036408'],
                    'actual_last24h'    => ['type' => 'string',  'example' => '0.00035620'],
                    'hashrate_last24h'  => ['type' => 'integer', 'example' => 269473000],
                    'rental_current'    => ['type' => 'string',  'example' => '3.61922463'],
                ],
            ],
            'CurrencyStatus' => [
                'type' => 'object',
                'properties' => [
                    'algo'          => ['type' => 'string',  'example' => 'bitcore'],
                    'port'          => ['type' => 'integer', 'example' => 3556],
                    'name'          => ['type' => 'string',  'example' => 'BitCore'],
                    'height'        => ['type' => 'integer', 'example' => 18944],
                    'workers'       => ['type' => 'integer', 'example' => 181],
                    'shares'        => ['type' => 'integer', 'example' => 392],
                    'hashrate'      => ['type' => 'integer', 'example' => 7267227499],
                    '24h_blocks'    => ['type' => 'integer', 'example' => 329],
                    '24h_btc'       => ['type' => 'number',  'example' => 0.54471295],
                    'lastblock'     => ['type' => 'integer', 'example' => 18945],
                    'timesincelast' => ['type' => 'integer', 'example' => 67],
                ],
            ],
        ];

        if ($hasRental) {
            $schemas['RentalJob'] = [
                'type' => 'object',
                'properties' => [
                    'jobid'     => ['type' => 'string'],
                    'algo'      => ['type' => 'string'],
                    'price'     => ['type' => 'string'],
                    'hashrate'  => ['type' => 'string'],
                    'server'    => ['type' => 'string'],
                    'port'      => ['type' => 'string'],
                    'username'  => ['type' => 'string'],
                    'password'  => ['type' => 'string'],
                    'started'   => ['type' => 'string'],
                    'active'    => ['type' => 'string'],
                    'accepted'  => ['type' => 'string'],
                    'rejected'  => ['type' => 'string'],
                    'diff'      => ['type' => 'string'],
                ],
            ];
            $schemas['RentalStatus'] = [
                'type' => 'object',
                'properties' => [
                    'balance'     => ['type' => 'number'],
                    'unconfirmed' => ['type' => 'number'],
                    'jobs'        => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/RentalJob']],
                ],
            ];
        }

        // ── Wallet extended (payouts optional) ────────────────────────────────
        $walletExProps = [
            'unsold'   => ['type' => 'number', 'format' => 'double'],
            'balance'  => ['type' => 'number', 'format' => 'double'],
            'unpaid'   => ['type' => 'number', 'format' => 'double'],
            'paid24h'  => ['type' => 'number', 'format' => 'double'],
            'total'    => ['type' => 'number', 'format' => 'double'],
            'miners'   => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/MinerEntry']],
        ];
        if ($hasPayouts) {
            $walletExProps['payouts'] = [
                'type'        => 'array',
                'description' => "Payouts from the last {$payoutHours} hours",
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'time'   => ['type' => 'integer', 'example' => 1529860641],
                        'amount' => ['type' => 'string',  'example' => '0.001'],
                        'tx'     => ['type' => 'string',  'example' => 'txid...'],
                    ],
                ],
            ];
        }
        $schemas['WalletExtended'] = ['type' => 'object', 'properties' => $walletExProps];

        // ── API key security scheme ───────────────────────────────────────────
        $apiKeyParam = [
            'name'        => 'key',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Your personal API key',
            'schema'      => ['type' => 'string'],
        ];

        // ── Paths ─────────────────────────────────────────────────────────────
        $paths = [
            '/api/wallet' => ['get' => [
                'tags'        => ['Wallet'],
                'summary'     => 'Wallet balance summary',
                'operationId' => 'getWallet',
                'parameters'  => [[
                    'name' => 'address', 'in' => 'query', 'required' => true,
                    'description' => 'Wallet address (payout address)',
                    'schema' => ['type' => 'string'],
                ]],
                'responses' => ['200' => [
                    'description' => 'Wallet status',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WalletStatus']]],
                ]],
            ]],

            '/api/walletEx' => ['get' => [
                'tags'        => ['Wallet'],
                'summary'     => 'Extended wallet status including connected miners' . ($hasPayouts ? " and last {$payoutHours}h payouts" : ''),
                'operationId' => 'getWalletEx',
                'parameters'  => [[
                    'name' => 'address', 'in' => 'query', 'required' => true,
                    'schema' => ['type' => 'string'],
                ]],
                'responses' => ['200' => [
                    'description' => 'Extended wallet status',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WalletExtended']]],
                ]],
            ]],

            '/api/status' => ['get' => [
                'tags'        => ['Pool'],
                'summary'     => 'Pool hashrate and profitability per algorithm',
                'operationId' => 'getStatus',
                'responses'   => ['200' => [
                    'description' => 'Keyed by algo name',
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'additionalProperties' => ['$ref' => '#/components/schemas/AlgoStatus'],
                    ]]],
                ]],
            ]],

            '/api/currencies' => ['get' => [
                'tags'        => ['Pool'],
                'summary'     => 'Per-coin mining statistics',
                'operationId' => 'getCurrencies',
                'responses'   => ['200' => [
                    'description' => 'Keyed by coin symbol',
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'additionalProperties' => ['$ref' => '#/components/schemas/CurrencyStatus'],
                    ]]],
                ]],
            ]],
        ];

        if ($hasRental) {
            $paths['/api/rental']          = ['get' => [
                'tags' => ['Rental'], 'summary' => 'Rental balance and active jobs',
                'operationId' => 'getRental',
                'parameters'  => [$apiKeyParam],
                'responses'   => ['200' => ['description' => 'Rental status',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RentalStatus']]]]],
            ]];
            $paths['/api/rental_price']    = ['get' => [
                'tags' => ['Rental'], 'summary' => 'Set the price for a rental job',
                'operationId' => 'setRentalPrice',
                'parameters'  => [$apiKeyParam,
                    ['name' => 'jobid', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'price', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'number']],
                ],
                'responses' => ['200' => ['description' => 'OK']],
            ]];
            $paths['/api/rental_hashrate'] = ['get' => [
                'tags' => ['Rental'], 'summary' => 'Set the maximum hashrate for a rental job',
                'operationId' => 'setRentalHashrate',
                'parameters'  => [$apiKeyParam,
                    ['name' => 'jobid',    'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'hashrate', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => ['200' => ['description' => 'OK']],
            ]];
            $paths['/api/rental_start']    = ['get' => [
                'tags' => ['Rental'], 'summary' => 'Start a rental job',
                'operationId' => 'startRentalJob',
                'parameters'  => [$apiKeyParam,
                    ['name' => 'jobid', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => ['200' => ['description' => 'OK']],
            ]];
            $paths['/api/rental_stop']     = ['get' => [
                'tags' => ['Rental'], 'summary' => 'Stop a rental job',
                'operationId' => 'stopRentalJob',
                'parameters'  => [$apiKeyParam,
                    ['name' => 'jobid', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => ['200' => ['description' => 'OK']],
            ]];
        }

        $tags = [
            ['name' => 'Wallet', 'description' => 'Per-address balance and miner data'],
            ['name' => 'Pool',   'description' => 'Pool-wide statistics'],
        ];
        if ($hasRental) {
            $tags[] = ['name' => 'Rental', 'description' => 'Hash-power rental management'];
        }

        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => "{$siteName} Pool API",
                'description' => 'Public REST API for the ' . $siteName . ' mining pool.',
                'version'     => '1.0.0',
            ],
            'servers'    => [['url' => $baseUrl, 'description' => 'Pool server']],
            'paths'      => $paths,
            'components' => ['schemas' => $schemas],
            'tags'       => $tags,
        ];

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->data   = $spec;
        return $response;
    }

    /**
     * Benchmarks page action.
     *
     * @return string
     */
	public function actionMaintenance(): string
	{
		$this->layout = false;
		Yii::$app->response->statusCode = 503;
		return $this->render('maintenance');
	}

	public function actionBenchmarks()
	{
		return $this->render('benchmarks');
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
    public function actionMiners(): string
	{
		return $this->render('miners');
	}

	public function actionMiners_results(): string
	{
		$algo     = Yii::$app->session->get('yaamp-algo', '');
		$isAdmin  = !is_null(Yii::$app->user->identity) && Yii::$app->user->identity->is_admin;
		$util     = Yii::$app->YiimpUtils;
		$conv     = Yii::$app->ConversionUtils;
		$cache    = Yii::$app->cache;
		$db       = Yii::$app->db;

		$algoFactor = $util->algo_mBTC_factor($algo);
		$algoUnit   = match (true) {
			$algoFactor == 0.001      => 'Kh',
			$algoFactor == 1000       => 'Gh',
			$algoFactor == 1000000    => 'Th',
			$algoFactor == 1000000000 => 'Ph',
			default                   => 'Mh',
		};

		$target   = $util->hashrate_constant($algo);
		$interval = $util->hashrate_step();
		$delay    = time() - $interval;

		// ── Totals ────────────────────────────────────────────────────────────
		$totalWorkers    = (int) (new \yii\db\Query())->from('workers')->where(['algo' => $algo])->count();
		$totalExtranonce = (int) (new \yii\db\Query())->from('workers')->where(['algo' => $algo, 'subscribe' => 1])->count();

		$totalHashrate = (float) $cache->getOrSet("current_hashrate-{$algo}", function () use ($db, $algo) {
			return $db->createCommand(
				"SELECT hashrate FROM hashrate WHERE algo=:a ORDER BY time DESC LIMIT 1",
				[':a' => $algo]
			)->queryScalar() ?: 0.0;
		}, 60);

		$totalInvalid = 0.0;
		if ($isAdmin) {
			$totalInvalid = (float) $cache->getOrSet("current_hashrate_bad-{$algo}", function () use ($db, $algo) {
				return $db->createCommand(
					"SELECT hashrate_bad FROM hashrate WHERE algo=:a ORDER BY time DESC LIMIT 1",
					[':a' => $algo]
				)->queryScalar() ?: 0.0;
			}, 60);
		}

		// ── Per-version worker counts ─────────────────────────────────────────
		$versionCounts = $db->createCommand(
			"SELECT version, COUNT(*) AS c, SUM(subscribe) AS s FROM workers WHERE algo=:a GROUP BY version ORDER BY c DESC",
			[':a' => $algo]
		)->queryAll();

		// ── Batch hashrate + invalid from shares (1 query instead of 2×N) ─────
		$versionRates = $cache->getOrSet("miners-rates-{$algo}", function () use ($db, $target, $interval, $delay, $algo) {
			$rows = $db->createCommand(
				"SELECT w.version,
				        SUM(CASE WHEN s.valid=1 THEN s.difficulty ELSE 0 END) * :t / :iv / 1000 AS hashrate,
				        SUM(CASE WHEN s.valid=0 THEN s.difficulty ELSE 0 END) * :t / :iv / 1000 AS invalid
				 FROM shares s JOIN workers w ON w.id=s.workerid
				 WHERE s.time>:delay AND w.algo=:algo
				 GROUP BY w.version",
				[':t' => $target, ':iv' => $interval, ':delay' => $delay, ':algo' => $algo]
			)->queryAll();
			$map = [];
			foreach ($rows as $r) {
				$map[$r['version']] = ['hashrate' => (float)$r['hashrate'], 'invalid' => (float)$r['invalid']];
			}
			return $map;
		}, 60);

		// ── Batch donators per version (1 query instead of N) ────────────────
		$versionDonators = [];
		foreach ($db->createCommand(
			"SELECT w.version, COUNT(*) AS d FROM workers w
			 LEFT JOIN accounts a ON a.id=w.userid
			 WHERE w.algo=:a AND a.donation>0 GROUP BY w.version",
			[':a' => $algo]
		)->queryAll() as $r) {
			$versionDonators[$r['version']] = (int) $r['d'];
		}

		// ── Batch error breakdown per version (admin only, 1 query instead of 8×N)
		$errorTab = [
			20 => 'Invalid nonce size',   21 => 'Invalid job id',
			22 => 'Duplicate share',      23 => 'Invalid time rolling',
			24 => 'Invalid extranonce2 size', 25 => 'Invalid share',
			26 => 'Low difficulty share', 27 => 'Invalid extranonce',
		];
		$versionErrors = [];
		if ($isAdmin) {
			foreach ($db->createCommand(
				"SELECT w.version, s.error, SUM(s.difficulty) * :t / :iv / 1000 AS bad
				 FROM shares s JOIN workers w ON w.id=s.workerid
				 WHERE s.time>:delay AND w.algo=:algo AND s.valid=0 AND s.error>0
				 GROUP BY w.version, s.error",
				[':t' => $target, ':iv' => $interval, ':delay' => $delay, ':algo' => $algo]
			)->queryAll() as $r) {
				$versionErrors[$r['version']][(int)$r['error']] = (float)$r['bad'];
			}
		}

		// ── Assemble per-version display data ────────────────────────────────
		$totalDonators = 0;
		$versionData   = [];

		foreach ($versionCounts as $item) {
			$version    = $item['version'];
			$count      = (int) $item['c'];
			$extranonce = (int) $item['s'];
			$rates      = $versionRates[$version] ?? ['hashrate' => 0.0, 'invalid' => 0.0];
			$hashrate   = $rates['hashrate'];
			$invalid    = $rates['invalid'];
			$donators   = $versionDonators[$version] ?? 0;

			if (!$hashrate && !$isAdmin) continue;

			$totalDonators += $donators;

			$errorTitle = '';
			if ($isAdmin && isset($versionErrors[$version])) {
				foreach ($versionErrors[$version] as $code => $bad) {
					if ($bad && ($hashrate + $invalid)) {
						$pct         = round($bad * 100 / ($hashrate + $invalid), 2);
						$errorTitle .= "{$pct}% — " . ($errorTab[$code] ?? "Error {$code}") . "\n";
					}
				}
			}

			$percent = ($totalHashrate && $hashrate) ? round($hashrate * 100 / $totalHashrate, 2) . '%' : '-';
			if ($percent === '0%') $percent = '-';
			$bad = ($hashrate + $invalid) ? round($invalid * 100 / ($hashrate + $invalid), 1) . '%' : '-';
			$avg = $count ? $conv->Itoa2($hashrate / $count) . 'H/s' : '';

			$versionData[] = [
				'version'    => substr($version, 0, 30),
				'count'      => $count,
				'extranonce' => $extranonce,
				'hashrate'   => $hashrate ? $conv->Itoa2($hashrate) . 'H/s' : '',
				'donators'   => $donators,
				'percent'    => $percent,
				'bad'        => $bad,
				'avg'        => $avg,
				'errorTitle' => $errorTitle,
			];
		}

		// ── Total row ─────────────────────────────────────────────────────────
		$totalErrorTitle = '';
		if ($isAdmin) {
			$aggErrors = [];
			foreach ($versionErrors as $verErrors) {
				foreach ($verErrors as $code => $bad) {
					$aggErrors[$code] = ($aggErrors[$code] ?? 0.0) + $bad;
				}
			}
			foreach ($aggErrors as $code => $bad) {
				if ($bad && ($totalHashrate + $totalInvalid)) {
					$pct              = round($bad * 100 / ($totalHashrate + $totalInvalid), 2);
					$totalErrorTitle .= "{$pct}% — " . ($errorTab[$code] ?? "Error {$code}") . "\n";
				}
			}
		}

		$totalBad = ($totalHashrate + $totalInvalid) && $totalInvalid
			? round($totalInvalid * 100 / ($totalHashrate + $totalInvalid), 1) . '%' : '';
		$totalAvg = $totalWorkers ? $conv->Itoa2($totalHashrate / $totalWorkers) . 'H/s' : '';

		return $this->renderPartial('results/miners_results', [
			'algo'             => $algo,
			'algoUnit'         => $algoUnit,
			'isAdmin'          => $isAdmin,
			'totalWorkers'     => $totalWorkers,
			'totalExtranonce'  => $totalExtranonce,
			'totalHashrateFmt' => $conv->Itoa2($totalHashrate) . 'H/s',
			'totalDonators'    => $totalDonators,
			'totalBad'         => $totalBad,
			'totalAvg'         => $totalAvg,
			'totalErrorTitle'  => $totalErrorTitle,
			'versionData'      => $versionData,
		]);
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
				return "$balance $coin->symbol - ".YIIMP_SITE_NAME;
			else
				return "$balance - ".YIIMP_SITE_NAME;
		}
		else
			return YIIMP_SITE_URL;
	}

    /////////////////////////////////////////////////

	public function actionBlock()
	{
		$id   = (int) Yii::$app->request->get('id');
		$coin = $id ? Coins::findOne($id) : null;
		return $this->render('block', ['coin' => $coin, 'id' => $id]);
	}

	public function actionBlock_results()
	{
		$id     = (int) Yii::$app->request->get('id');
		$coin   = Coins::findOne($id);
		$blocks = [];
		$accounts = [];

		if ($coin) {
			$isAdmin = !Yii::$app->user->isGuest && (Yii::$app->user->identity->is_admin ?? false);

			$query = Blocks::find()->where(['coin_id' => $id])->orderBy('time DESC')->limit(250);
			if (!$isAdmin) {
				$query->andWhere(['not in', 'category', ['stake', 'generated']]);
			}
			$blocks = $query->all();

			$userIds  = array_values(array_unique(array_filter(array_map(fn($b) => $b->userid, $blocks))));
			$accounts = $userIds
				? Accounts::find()->where(['id' => $userIds])->indexBy('id')->all()
				: [];
		}

		return $this->renderPartial('block_results', [
			'coin'     => $coin,
			'blocks'   => $blocks,
			'accounts' => $accounts,
		]);
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionTx()
	{
		return $this->renderPartial('tx');
	}

	////////////////////////////////////////////////////////////////////////////////////////

    public function actionAlgo(): Response
	{
		$algo  = Yii::$app->YiimpUtils->get_algo_param();
		$valid = Algos::find()->select('name')->where(['name' => $algo])->scalar();
		Yii::$app->session->set('yaamp-algo', $valid ?: 'all');

		$route = Yii::$app->getRequest()->getQueryParam('r');
		return !empty($route) ? $this->redirect($route) : $this->goBack();
	}

	public function actionGomining(): Response
	{
		$algo  = Yii::$app->YiimpUtils->get_algo_param();
		$valid = Algos::find()->select('name')->where(['name' => $algo])->scalar();
		Yii::$app->session->set('yaamp-algo', $valid ?: 'all');

		return $this->redirect(['/site/mining']);
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
