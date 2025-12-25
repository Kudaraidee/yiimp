<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use app\models\Coins;
use app\models\Markets;
use app\models\Algos;
use app\models\MarketHistory;

/**
 * TradingController handles trading and market information features
 */
class TradingController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'mining_results', 'history', 'history_data'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Display market prices for all pool coins
     * Requirements: 6.1
     */
    public function actionIndex()
    {
        // Get all enabled coins with their market data
        $coins = Coins::find()
            ->where(['enable' => 1, 'visible' => 1])
            ->orderBy(['index_avg' => SORT_DESC])
            ->all();

        // Get market data for each coin
        $marketData = [];
        foreach ($coins as $coin) {
            $markets = Markets::find()
                ->where(['coinid' => $coin->id, 'disabled' => 0])
                ->orderBy(['price' => SORT_DESC])
                ->all();
            
            if (!empty($markets)) {
                $marketData[$coin->id] = [
                    'coin' => $coin,
                    'markets' => $markets,
                    'best_price' => $markets[0]->price ?? 0,
                ];
            }
        }

        return $this->render('index', [
            'marketData' => $marketData,
        ]);
    }

    /**
     * AJAX endpoint for mining profitability calculations
     * Requirements: 6.2
     */
    public function actionMining_results()
    {
        // Get current algorithm from session or default
        $algo = Yii::$app->session->get('yaamp-algo', 'scrypt');
        
        // Get all enabled coins for this algorithm
        $coins = Coins::find()
            ->where(['enable' => 1, 'algo' => $algo])
            ->orderBy(['index_avg' => SORT_DESC])
            ->all();

        // Calculate profitability for each coin
        $profitabilityData = [];
        foreach ($coins as $coin) {
            $profitability = $this->calculateProfitability($coin);
            $poolHashrate = $this->getPoolHashrate($coin->id);
            
            $profitabilityData[] = [
                'coin' => $coin,
                'profitability' => $profitability,
                'pool_hashrate' => $poolHashrate,
                'pool_ttf' => $coin->pool_ttf,
            ];
        }

        // Get algorithm info
        $algoModel = Algos::findOne(['name' => $algo]);
        $totalRate = $this->getTotalPoolRate($algo);
        $workerCount = $this->getWorkerCount($algo);

        return $this->renderPartial('mining_results', [
            'coins' => $profitabilityData,
            'algo' => $algo,
            'algoModel' => $algoModel,
            'totalRate' => $totalRate,
            'workerCount' => $workerCount,
        ]);
    }

    /**
     * Calculate profitability for a coin (mBTC/MH/day)
     * Based on yaamp_profitability() from legacy code
     * Requirements: 6.2
     */
    protected function calculateProfitability($coin)
    {
        if (!$coin->difficulty || $coin->difficulty == 0) {
            return 0;
        }

        // Base profitability calculation
        // Formula: 20116.56761169 / difficulty * reward * price
        $btcmhd = 20116.56761169 / $coin->difficulty * $coin->reward * $coin->price;

        // Add auxiliary proof-of-work coins if applicable
        if (!$coin->auxpow && $coin->rpcencoding == 'POW') {
            $auxCoins = Coins::find()
                ->where([
                    'enable' => 1,
                    'visible' => 1,
                    'auto_ready' => 1,
                    'auxpow' => 1,
                    'algo' => $coin->algo,
                ])
                ->all();

            foreach ($auxCoins as $aux) {
                if ($aux->difficulty && $aux->difficulty > 0) {
                    $btcmhdaux = 20116.56761169 / $aux->difficulty * $aux->reward * $aux->price;
                    $btcmhd += $btcmhdaux;
                }
            }
        }

        // Apply algorithm unit factor
        $algoUnitFactor = $this->getAlgoUnitFactor($coin->algo);
        return $btcmhd * $algoUnitFactor;
    }

    /**
     * Get algorithm unit factor for normalization
     */
    protected function getAlgoUnitFactor($algo)
    {
        // Default factors for common algorithms
        $factors = [
            'scrypt' => 1,
            'sha256' => 1000,
            'x11' => 1,
            'x13' => 1,
            'x15' => 1,
            'nist5' => 1,
            'neoscrypt' => 1,
            'lyra2' => 1,
            'lyra2v2' => 1,
        ];

        return $factors[$algo] ?? 1;
    }

    /**
     * Get pool hashrate for a specific coin
     */
    protected function getPoolHashrate($coinId)
    {
        $sql = "SELECT SUM(difficulty) * :target / :interval / 1000 as hashrate
                FROM shares 
                WHERE valid = 1 
                AND time > :delay 
                AND coinid = :coinid";

        $target = $this->getHashrateConstant();
        $interval = $this->getHashrateStep();
        $delay = time() - $interval;

        $result = Yii::$app->db->createCommand($sql, [
            ':target' => $target,
            ':interval' => $interval,
            ':delay' => $delay,
            ':coinid' => $coinId,
        ])->queryScalar();

        return $result ?: 0;
    }

    /**
     * Get total pool rate for an algorithm
     */
    protected function getTotalPoolRate($algo)
    {
        $sql = "SELECT SUM(difficulty) * :target / :interval / 1000 as hashrate
                FROM shares 
                WHERE valid = 1 
                AND time > :delay 
                AND workerid IN (SELECT id FROM workers WHERE algo = :algo)";

        $target = $this->getHashrateConstant();
        $interval = $this->getHashrateStep();
        $delay = time() - $interval;

        $result = Yii::$app->db->createCommand($sql, [
            ':target' => $target,
            ':interval' => $interval,
            ':delay' => $delay,
            ':algo' => $algo,
        ])->queryScalar();

        return $result ?: 0;
    }

    /**
     * Get worker count for an algorithm
     */
    protected function getWorkerCount($algo)
    {
        return (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM workers WHERE algo = :algo",
            [':algo' => $algo]
        )->queryScalar();
    }

    /**
     * Get hashrate constant (target)
     */
    protected function getHashrateConstant()
    {
        // Default constant for hashrate calculation
        return 0x00000000ffff0000;
    }

    /**
     * Get hashrate step (time interval in seconds)
     */
    protected function getHashrateStep()
    {
        // Default to 5 minutes
        return 300;
    }

    /**
     * Display market history with price charts
     * Requirements: 6.4
     */
    public function actionHistory($id = null)
    {
        $coin = null;
        $marketHistory = [];
        $chartData = [];

        if ($id) {
            // Get specific coin
            $coin = Coins::findOne($id);
            
            if ($coin) {
                // Get market history for the last 7 days
                $timeLimit = time() - (7 * 24 * 60 * 60);
                
                $history = MarketHistory::find()
                    ->where(['idcoin' => $coin->id])
                    ->andWhere(['>', 'time', $timeLimit])
                    ->orderBy(['time' => SORT_ASC])
                    ->all();

                // Group by market for chart display
                $marketGroups = [];
                foreach ($history as $record) {
                    if ($record->idmarket) {
                        if (!isset($marketGroups[$record->idmarket])) {
                            $marketGroups[$record->idmarket] = [
                                'market' => $record->market,
                                'data' => [],
                            ];
                        }
                        $marketGroups[$record->idmarket]['data'][] = [
                            'time' => $record->time,
                            'price' => $record->price,
                            'price2' => $record->price2,
                        ];
                    }
                }

                $chartData = $marketGroups;
                $marketHistory = $history;
            }
        }

        // Get list of coins with market history for selection
        $coinsWithHistory = Coins::find()
            ->innerJoinWith('markets')
            ->where(['coins.enable' => 1, 'coins.visible' => 1])
            ->groupBy('coins.id')
            ->orderBy(['coins.name' => SORT_ASC])
            ->all();

        return $this->render('history', [
            'coin' => $coin,
            'marketHistory' => $marketHistory,
            'chartData' => $chartData,
            'coinsWithHistory' => $coinsWithHistory,
        ]);
    }

    /**
     * AJAX endpoint for market history chart data
     * Requirements: 6.4
     */
    public function actionHistory_data($id, $days = 7)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $coin = Coins::findOne($id);
        if (!$coin) {
            return ['error' => 'Coin not found'];
        }

        $timeLimit = time() - ($days * 24 * 60 * 60);
        
        $history = MarketHistory::find()
            ->where(['idcoin' => $coin->id])
            ->andWhere(['>', 'time', $timeLimit])
            ->orderBy(['time' => SORT_ASC])
            ->all();

        // Group by market
        $marketGroups = [];
        foreach ($history as $record) {
            if ($record->idmarket) {
                $marketId = $record->idmarket;
                if (!isset($marketGroups[$marketId])) {
                    $market = Markets::findOne($marketId);
                    $marketGroups[$marketId] = [
                        'name' => $market ? $market->name : 'Unknown',
                        'data' => [],
                    ];
                }
                $marketGroups[$marketId]['data'][] = [
                    date('Y-m-d H:i', $record->time),
                    (float) $record->price,
                ];
            }
        }

        return array_values($marketGroups);
    }
}
