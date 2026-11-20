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
            return $this->render('index', ['tlsPorts' => $this->buildTlsPortMap()]);
    }

    /**
     * Dedicated TLS port per algo, read from the live stratum configs so the
     * miner command generator never shows a port that is not actually enabled.
     *
     * @return array<string,int>
     */
    private function buildTlsPortMap(): array
    {
        $configDir = dirname(Yii::$app->basePath) . '/config/stratum';
        $ports = [];

        foreach (glob($configDir . '/*.conf') ?: [] as $file) {
            $ini = parse_ini_file($file, true) ?: [];
            $algo = (string) ($ini['STRATUM']['algo'] ?? basename($file, '.conf'));
            $tlsPort = (int) ($ini['TCP']['tls_port'] ?? 0);
            if ($tlsPort > 0) $ports[$algo] = $tlsPort;
        }

        return $ports;
    }

    /**
     * Mining page action.
     *
     * @return string
     */
    public function actionMining()
    {
        return $this->render('mining', ['algoNav' => $this->buildMiningAlgoNav()]);
    }

    /**
     * JSON version of the mining algo scope bar, refreshed with the page.
     */
    public function actionMining_summary(): Response
    {
        return $this->asJson($this->buildMiningAlgoNav());
    }

    /**
     * Small summary used by the algo scope bar on /site/mining.
     *
     * @return array{active:string,items:array<string,array<string,mixed>>}
     */
    private function buildMiningAlgoNav(): array
    {
        $util   = Yii::$app->YiimpUtils;
        $conv   = Yii::$app->ConversionUtils;
        $active = (string) Yii::$app->session->get('yaamp-algo', 'all');

        $items        = [];
        $totalWorkers = 0;
        $totalPoolHash = 0.0;

        foreach ($util->get_algos(true) ?: [] as $algo) {
            // Bar membership follows the coin flags (enable + visible).
            // auto_ready is about auto-exchange and must not hide an algo here.
            $coins = Coins::find()
                ->where(['enable' => 1, 'visible' => 1, 'algo' => $algo])
                ->all();
            if (!$coins) continue;

            $workers = (int) \app\models\Workers::find()->where(['algo' => $algo])->count();
            $stratum = \app\models\Stratums::find()->where(['algo' => $algo])->one();

            $poolHash   = 0.0;
            $networkHash = 0.0;
            foreach ($coins as $coin) {
                $poolHash += (float) $util->coin_rate($coin->id);
                $hash = $util->display_network_hashrate($coin);
                if ($hash !== null && (float) $hash > $networkHash) {
                    $networkHash = (float) $hash;
                }
            }

            $items[$algo] = [
                'algo'         => $algo,
                'label'        => $algo,
                'color'        => $util->getAlgoColors($algo),
                'port'         => $stratum ? (int) $stratum->port : 0,
                'workers'      => $workers,
                'pool_hash'    => $poolHash > 0 ? $conv->Itoa2($poolHash) . 'h/s' : '0 h/s',
                'network_hash' => $networkHash > 0 ? strtoupper($conv->Itoa2($networkHash)) . 'H/s' : '—',
            ];

            $totalWorkers  += $workers;
            $totalPoolHash += $poolHash;
        }

        if ($active !== 'all' && !isset($items[$active])) {
            $active = 'all';
        }

        $all = [
            'algo'         => 'all',
            'label'        => 'All',
            'color'        => '#6c757d',
            'port'         => 0,
            'workers'      => $totalWorkers,
            'pool_hash'    => $totalPoolHash > 0 ? $conv->Itoa2($totalPoolHash) . 'h/s' : '0 h/s',
            'network_hash' => '—',
        ];

        return [
            'active' => $active,
            'items'  => array_merge(['all' => $all], $items),
        ];
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
        $baseUrl       = defined('YIIMP_SITE_URL') ? trim(YIIMP_SITE_URL) : Yii::$app->request->hostInfo;
        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
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
	public function actionDiff(): string
	{
		return $this->render('diff', $this->buildDiffData());
	}

	/**
	 * AJAX refresh endpoint for the live diff page.
	 */
	public function actionDiff_data(): Response
	{
		$data = $this->buildDiffData();
		Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

		return $this->asJson([
			'generated' => gmdate('Y-m-d H:i:s', $data['now']),
			'html'      => $this->renderPartial('diff_live', $data),
		]);
	}

	private function buildDiffData(): array
	{
		$now       = time();
		$configDir = dirname(Yii::$app->basePath) . '/config/stratum';
		$rows      = [];

		// Live stratum configuration from the runtime .conf files.
		foreach (glob($configDir . '/*.conf') ?: [] as $file) {
			$ini     = parse_ini_file($file, true) ?: [];
			$section = $ini['STRATUM'] ?? [];
			$algo    = (string) ($section['algo'] ?? basename($file, '.conf'));
			$default = (float) ($section['difficulty'] ?? 0);

			$rows[$algo] = [
				'algo'         => $algo,
				'port'         => (int) ($ini['TCP']['port'] ?? 0),
				'tls_port'     => (int) ($ini['TCP']['tls_port'] ?? 0),
				'config_file'  => basename($file),
				'default'      => $default,
				'min'          => isset($section['diff_min']) ? (float) $section['diff_min'] : $default / 2,
				'max'          => isset($section['diff_max']) ? (float) $section['diff_max'] : $default * 8192,
				'symbol'       => '',
				'coin_name'    => '',
				'network_diff' => null,
				'workers'      => 0,
				'fds'          => 0,
				'pid'          => null,
				'started'      => null,
				'seen'         => null,
				'hashrate'     => null,
				'hr_time'      => null,
			];
		}

		// Live process/connection stats written by each stratum instance.
		$stratums = (new \yii\db\Query())->from('stratums')->all();
		foreach ($stratums as $st) {
			$algo = (string) ($st['algo'] ?? '');
			if ($algo === '') continue;

			if (!isset($rows[$algo])) {
				$rows[$algo] = [
					'algo' => $algo, 'port' => 0, 'config_file' => '',
					'default' => 0, 'min' => 0, 'max' => 0,
					'symbol' => '', 'coin_name' => '', 'network_diff' => null,
					'workers' => 0, 'fds' => 0, 'pid' => null,
					'started' => null, 'seen' => null, 'hashrate' => null, 'hr_time' => null,
				];
			}

			$rows[$algo]['port']    = (int) ($st['port'] ?? 0) ?: $rows[$algo]['port'];
			$rows[$algo]['symbol']  = (string) ($st['symbol'] ?? '') ?: $rows[$algo]['symbol'];
			$rows[$algo]['workers'] = (int) ($st['workers'] ?? 0);
			$rows[$algo]['fds']     = (int) ($st['fds'] ?? 0);
			$rows[$algo]['pid']     = !empty($st['pid']) ? (int) $st['pid'] : null;
			$rows[$algo]['started'] = !empty($st['started']) ? (int) $st['started'] : null;
			$rows[$algo]['seen']    = !empty($st['time']) ? (int) $st['time'] : null;
		}

		// Live coin/network difficulty refreshed by the backend.
		$coins = (new \yii\db\Query())
			->select(['symbol', 'name', 'algo', 'difficulty'])
			->from('coins')
			->where(['enable' => 1, 'visible' => 1])
			->all();
		foreach ($coins as $coin) {
			$algo = (string) ($coin['algo'] ?? '');
			if (!isset($rows[$algo])) continue;
			if ($rows[$algo]['symbol'] === '') {
				$rows[$algo]['symbol']   = (string) $coin['symbol'];
				$rows[$algo]['coin_name'] = (string) $coin['name'];
			}
			$rows[$algo]['network_diff'] = (float) $coin['difficulty'];
		}

		foreach ($rows as &$row) {
			$hr = (new \yii\db\Query())
				->select(['hashrate', 'time'])
				->from('hashrate')
				->where(['algo' => $row['algo']])
				->orderBy(['time' => SORT_DESC])
				->one();
			if ($hr) {
				$row['hashrate'] = (float) $hr['hashrate'];
				$row['hr_time']  = (int) $hr['time'];
			}
		}
		unset($row);

		usort($rows, fn($a, $b) => ($a['port'] ?: 99999) <=> ($b['port'] ?: 99999));

		$workers = (new \yii\db\Query())
			->select(['worker', 'name', 'algo', 'difficulty', 'password', 'version', 'time'])
			->from('workers')
			->where(['>', 'time', $now - 3600])
			->orderBy(['time' => SORT_DESC])
			->limit(20)
			->all();

		$stratumUrl = defined('YAAMP_STRATUM_URL')
			? YAAMP_STRATUM_URL
			: (defined('YIIMP_STRATUM_URL') ? YIIMP_STRATUM_URL : 'localhost');

		return [
			'rows'       => $rows,
			'workers'    => $workers,
			'now'        => $now,
			'stratumUrl' => $stratumUrl,
		];
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
    public function actionMiners(): Response
	{
		$base = defined('YIIMP_SITE_URL') ? trim(YIIMP_SITE_URL) : Yii::$app->request->hostInfo;
		if ($base !== '' && !preg_match('#^https?://#i', $base)) $base = 'https://' . $base;
		return $this->redirect(rtrim($base, '/') . '/site/mining');
	}

	public function actionMiners_results(): string
	{
		$algo     = (string) Yii::$app->session->get('yaamp-algo', '');
		$isAll    = ($algo === 'all');
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

		$interval = $util->hashrate_step();
		$delay    = time() - $interval;

		// "All" scope must match the Algorithm bar: only algos backed by
		// enabled + visible coins. Recent hashrate history alone must not add
		// dormant algos here.
		if ($isAll) {
			$algos = [];
			foreach ($util->get_algos(true) ?: [] as $candidate) {
				if (Coins::find()->where(['enable' => 1, 'visible' => 1, 'algo' => $candidate])->exists()) {
					$algos[] = $candidate;
				}
			}
		} else {
			$algos = $algo !== '' ? [$algo] : [];
		}

		$totalWorkers    = 0;
		$totalExtranonce = 0;
		$totalHashrate   = 0.0;
		$totalInvalid    = 0.0;

		$versionCounts   = [];
		$versionRatesAll = [];
		$versionDonators = [];
		$versionErrors   = [];
		$aggErrors       = [];

		$errorTab = [
			20 => 'Invalid nonce size',   21 => 'Invalid job id',
			22 => 'Duplicate share',      23 => 'Invalid time rolling',
			24 => 'Invalid extranonce2 size', 25 => 'Invalid share',
			26 => 'Low difficulty share', 27 => 'Invalid extranonce',
		];

		// ── Collect per-algo data (one set of cached queries per algo) ────────
		foreach ($algos as $itemAlgo) {
			$target = $util->hashrate_constant($itemAlgo);

			$wQuery = (new \yii\db\Query())->from('workers')->where(['algo' => $itemAlgo]);
			$totalWorkers    += (int) (clone $wQuery)->count();
			$totalExtranonce += (int) (clone $wQuery)->andWhere(['subscribe' => 1])->count();

			$totalHashrate += (float) $cache->getOrSet("current_hashrate-{$itemAlgo}", function () use ($db, $itemAlgo) {
				return $db->createCommand(
					"SELECT hashrate FROM hashrate WHERE algo=:a ORDER BY time DESC LIMIT 1",
					[':a' => $itemAlgo]
				)->queryScalar() ?: 0.0;
			}, 60);

			if ($isAdmin) {
				$totalInvalid += (float) $cache->getOrSet("current_hashrate_bad-{$itemAlgo}", function () use ($db, $itemAlgo) {
					return $db->createCommand(
						"SELECT hashrate_bad FROM hashrate WHERE algo=:a ORDER BY time DESC LIMIT 1",
						[':a' => $itemAlgo]
					)->queryScalar() ?: 0.0;
				}, 60);
			}

			foreach ($db->createCommand(
				"SELECT version, COUNT(*) AS c, SUM(subscribe) AS s FROM workers WHERE algo=:a GROUP BY version ORDER BY c DESC",
				[':a' => $itemAlgo]
			)->queryAll() as $row) {
				$row['algo'] = $itemAlgo;
				$versionCounts[] = $row;
			}

			$versionRatesAll[$itemAlgo] = $cache->getOrSet("miners-rates-{$itemAlgo}", function () use ($db, $target, $interval, $delay, $itemAlgo) {
				$rows = $db->createCommand(
					"SELECT w.version,
					        SUM(CASE WHEN s.valid=1 THEN s.difficulty ELSE 0 END) * :t / :iv / 1000 AS hashrate,
					        SUM(CASE WHEN s.valid=0 THEN s.difficulty ELSE 0 END) * :t / :iv / 1000 AS invalid
					 FROM shares s JOIN workers w ON w.id=s.workerid
					 WHERE s.time>:delay AND w.algo=:algo
					 GROUP BY w.version",
					[':t' => $target, ':iv' => $interval, ':delay' => $delay, ':algo' => $itemAlgo]
				)->queryAll();
				$map = [];
				foreach ($rows as $r) {
					$map[$r['version']] = ['hashrate' => (float)$r['hashrate'], 'invalid' => (float)$r['invalid']];
				}
				return $map;
			}, 60);

			foreach ($db->createCommand(
				"SELECT w.version, COUNT(*) AS d FROM workers w
				 LEFT JOIN accounts a ON a.id=w.userid
				 WHERE w.algo=:a AND a.donation>0 GROUP BY w.version",
				[':a' => $itemAlgo]
			)->queryAll() as $r) {
				$versionDonators[$itemAlgo][$r['version']] = (int) $r['d'];
			}

			if ($isAdmin) {
				foreach ($db->createCommand(
					"SELECT w.version, s.error, SUM(s.difficulty) * :t / :iv / 1000 AS bad
					 FROM shares s JOIN workers w ON w.id=s.workerid
					 WHERE s.time>:delay AND w.algo=:algo AND s.valid=0 AND s.error>0
					 GROUP BY w.version, s.error",
					[':t' => $target, ':iv' => $interval, ':delay' => $delay, ':algo' => $itemAlgo]
				)->queryAll() as $r) {
					$code = (int) $r['error'];
					$bad  = (float) $r['bad'];
					$versionErrors[$itemAlgo][$r['version']][$code] = $bad;
					$aggErrors[$code] = ($aggErrors[$code] ?? 0.0) + $bad;
				}
			}
		}

		usort($versionCounts, fn($a, $b) => (int)$b['c'] <=> (int)$a['c']);

		// ── Assemble per-version display data ────────────────────────────────
		$totalDonators = 0;
		$versionData   = [];

		foreach ($versionCounts as $item) {
			$itemAlgo   = (string) $item['algo'];
			$version    = (string) $item['version'];
			$count      = (int) $item['c'];
			$extranonce = (int) $item['s'];
			$rates      = $versionRatesAll[$itemAlgo][$version] ?? ['hashrate' => 0.0, 'invalid' => 0.0];
			$hashrate   = (float) $rates['hashrate'];
			$invalid    = (float) $rates['invalid'];
			$donators   = $versionDonators[$itemAlgo][$version] ?? 0;

			if (!$hashrate && !$isAdmin) continue;

			$totalDonators += $donators;

			$errorTitle = '';
			if ($isAdmin && isset($versionErrors[$itemAlgo][$version])) {
				foreach ($versionErrors[$itemAlgo][$version] as $code => $bad) {
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
				'algo'       => $itemAlgo,
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
			'algoCount'        => count($algos),
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
		$algo  = (string) Yii::$app->session->get('yaamp-algo', 'all');
		$algos = $algo === 'all' ? $this->activeMiningAlgos() : [$algo];
		return $this->renderPartialAlgoMemcached('results/graph_hashrate_results', 15, ['algos' => $algos]);
	}

    // Pool tab: graph algo estimate history (json data)
	public function actionGraph_price_results()
	{
		$algo  = (string) Yii::$app->session->get('yaamp-algo', 'all');
		$algos = $algo === 'all' ? $this->activeMiningAlgos() : [$algo];
		return $this->renderPartialAlgoMemcached('results/graph_price_results', 15, ['algos' => $algos]);
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

    protected function renderPartialAlgoMemcached($partial, $cachetime=15, $params=[])
	{
		$algo = Yii::$app->session->get('yaamp-algo')?:'all';
		$memcache = Yii::$app->cache;
		$memkey = $algo.'_'.str_replace('/','_',$partial);
		$html = $memcache->get($memkey);

		if (!empty($html)) {
			return $html;
		}

		$html = $this->renderPartial($partial, $params);
		$memcache->set($memkey, $html, $cachetime);

        return $html;
	}

	/** Algos backed by enabled + visible coins, matching the Algorithm bar. */
	private function activeMiningAlgos(): array
	{
		$algos = [];
		foreach (Yii::$app->YiimpUtils->get_algos(true) ?: [] as $algo) {
			if (Coins::find()->where(['enable' => 1, 'visible' => 1, 'algo' => $algo])->exists()) {
				$algos[] = $algo;
			}
		}
		return $algos;
	}

}
