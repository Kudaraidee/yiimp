<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * BenchController handles benchmark display and submission
 */
class BenchController extends Controller
{
    /**
     * Displays benchmark listing organized by algorithm
     * 
     * @return string
     */
    public function actionIndex()
    {
        // Get algorithm filter from query params
        $algo = Yii::$app->request->get('algo', 'all');
        $chipId = Yii::$app->request->get('chip');
        
        // Get list of algorithms with benchmark counts
        $algos = Benchmarks::find()
            ->select(['algo', new Expression('COUNT(id) as count')])
            ->groupBy('algo')
            ->orderBy(['algo' => SORT_ASC])
            ->asArray()
            ->all();
        
        // Build algorithm array
        $algoList = [];
        foreach ($algos as $row) {
            $algoList[$row['algo']] = $row['count'];
        }
        
        // Get list of chips with benchmark data
        $chips = BenchChips::find()
            ->select(['bench_chips.id', 'bench_chips.devicetype', 'bench_chips.chip'])
            ->innerJoinWith('benchmarks')
            ->groupBy(['bench_chips.id', 'bench_chips.devicetype', 'bench_chips.chip'])
            ->orderBy(['bench_chips.devicetype' => SORT_DESC, 'bench_chips.chip' => SORT_ASC])
            ->asArray()
            ->all();
        
        // Build query for benchmarks
        $query = Benchmarks::find()
            ->with('benchChip')
            ->orderBy(['time' => SORT_DESC]);
        
        // Apply algorithm filter
        if ($algo !== 'all') {
            $query->where(['algo' => $algo]);
        }
        
        // Apply chip filter
        if ($chipId) {
            $query->andWhere(['idchip' => $chipId]);
        }
        
        // Limit results
        if ($algo === 'all') {
            $query->limit(50);
        } else {
            $query->limit(150);
        }
        
        // Create data provider
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'algoList' => $algoList,
            'chipList' => $chips,
            'selectedAlgo' => $algo,
            'selectedChip' => $chipId,
        ]);
    }

    /**
     * Displays algorithm-specific benchmarks with device performance
     * 
     * @param string $algo Algorithm name
     * @return string
     */
    public function actionAlgo($algo)
    {
        // Get list of all algorithms for navigation
        $algos = Benchmarks::find()
            ->select(['algo', new Expression('COUNT(id) as count')])
            ->groupBy('algo')
            ->orderBy(['algo' => SORT_ASC])
            ->asArray()
            ->all();
        
        $algoList = [];
        foreach ($algos as $row) {
            $algoList[$row['algo']] = $row['count'];
        }
        
        // Verify algorithm exists
        if (!isset($algoList[$algo])) {
            throw new NotFoundHttpException('Algorithm not found.');
        }
        
        // Get aggregated benchmark data by chip for this algorithm
        $chipBenchmarks = Benchmarks::find()
            ->select([
                'bench_chips.id',
                'bench_chips.devicetype',
                'bench_chips.chip',
                new Expression('AVG(benchmarks.khps) as avg_khps'),
                new Expression('AVG(benchmarks.power) as avg_power'),
                new Expression('AVG(benchmarks.intensity) as avg_intensity'),
                new Expression('AVG(benchmarks.freq) as avg_freq'),
                new Expression('COUNT(benchmarks.id) as record_count'),
            ])
            ->innerJoin('bench_chips', 'bench_chips.id = benchmarks.idchip')
            ->where(['benchmarks.algo' => $algo])
            ->andWhere(['>', 'benchmarks.idchip', 0])
            ->groupBy(['bench_chips.id', 'bench_chips.devicetype', 'bench_chips.chip'])
            ->orderBy(['bench_chips.devicetype' => SORT_DESC, 'bench_chips.chip' => SORT_ASC])
            ->asArray()
            ->all();
        
        // Get overall algorithm statistics
        $algoStats = Benchmarks::find()
            ->select([
                new Expression('AVG(khps) as avg_khps'),
                new Expression('MIN(khps) as min_khps'),
                new Expression('MAX(khps) as max_khps'),
                new Expression('AVG(power) as avg_power'),
                new Expression('COUNT(id) as total_records'),
            ])
            ->where(['algo' => $algo])
            ->asArray()
            ->one();
        
        return $this->render('algo', [
            'algo' => $algo,
            'algoList' => $algoList,
            'chipBenchmarks' => $chipBenchmarks,
            'algoStats' => $algoStats,
        ]);
    }

    /**
     * Displays device-specific benchmarks
     * Shows all devices in the database with their supported algorithms
     * 
     * @return string
     */
    public function actionDevices()
    {
        // Get distinct devices from benchmarks with chip information
        $devices = Benchmarks::find()
            ->select([
                'benchmarks.device',
                'benchmarks.type',
                'benchmarks.vendorid',
                'bench_chips.chip',
                'bench_chips.id as idchip',
            ])
            ->leftJoin('bench_chips', 'bench_chips.id = benchmarks.idchip')
            ->where(['>', 'benchmarks.idchip', 0])
            ->groupBy(['benchmarks.device', 'benchmarks.type', 'benchmarks.vendorid', 'bench_chips.chip', 'bench_chips.id'])
            ->orderBy(['benchmarks.type' => SORT_DESC, 'benchmarks.device' => SORT_ASC, 'benchmarks.vendorid' => SORT_ASC])
            ->asArray()
            ->all();
        
        // Get list of recent algorithms (last 30 days)
        $monthAgo = time() - (30 * 24 * 3600);
        $recentAlgos = Benchmarks::find()
            ->select('algo')
            ->where(['>', 'time', $monthAgo])
            ->groupBy('algo')
            ->orderBy(['algo' => SORT_ASC])
            ->limit(20)
            ->column();
        
        // For each device, get the algorithms it has benchmarks for
        $deviceAlgos = [];
        foreach ($devices as $device) {
            $key = $device['device'] . '_' . $device['vendorid'];
            
            if (!empty($device['vendorid'])) {
                $algos = Benchmarks::find()
                    ->select('algo')
                    ->where(['vendorid' => $device['vendorid']])
                    ->groupBy('algo')
                    ->column();
            } else {
                $algos = Benchmarks::find()
                    ->select('algo')
                    ->where(['device' => $device['device']])
                    ->groupBy('algo')
                    ->column();
            }
            
            $deviceAlgos[$key] = $algos;
        }
        
        return $this->render('devices', [
            'devices' => $devices,
            'recentAlgos' => $recentAlgos,
            'deviceAlgos' => $deviceAlgos,
        ]);
    }

    /**
     * Displays benchmark submission form and handles submission
     * 
     * @return string|Response
     */
    public function actionSubmit()
    {
        $model = new Benchmarks();
        $model->time = time();
        
        if ($model->load(Yii::$app->request->post())) {
            // Set timestamp
            $model->time = time();
            
            // Try to find matching chip if chip name is provided
            if ($model->chip && !$model->idchip) {
                $chip = BenchChips::find()
                    ->where(['chip' => $model->chip])
                    ->one();
                
                if ($chip) {
                    $model->idchip = $chip->id;
                }
            }
            
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Benchmark submitted successfully!');
                return $this->redirect(['index', 'algo' => $model->algo]);
            } else {
                Yii::$app->session->setFlash('error', 'Failed to submit benchmark. Please check the form for errors.');
            }
        }
        
        // Get list of algorithms for dropdown
        $algos = Benchmarks::find()
            ->select('algo')
            ->groupBy('algo')
            ->orderBy(['algo' => SORT_ASC])
            ->column();
        
        // Get list of chips for dropdown
        $chips = BenchChips::find()
            ->select(['id', 'chip', 'devicetype'])
            ->orderBy(['devicetype' => SORT_DESC, 'chip' => SORT_ASC])
            ->all();
        
        return $this->render('submit', [
            'model' => $model,
            'algos' => $algos,
            'chips' => $chips,
        ]);
    }

}
