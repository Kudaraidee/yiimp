<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;

use app\models\Nicehash;
use app\models\Services;
use app\models\Hashrate;

/**
 * NicehashController handles NiceHash integration and statistics
 */
class NicehashController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Only authenticated users (admin)
                    ],
                ],
            ],
        ];
    }

    /**
     * Displays NiceHash statistics page
     * Shows active orders, hashrate, and earnings
     * 
     * @return string
     */
    public function actionIndex()
    {
        // Check if NiceHash API is enabled
        if (!defined('YAAMP_USE_NICEHASH_API') || !YAAMP_USE_NICEHASH_API) {
            Yii::$app->session->setFlash('warning', 'NiceHash API integration is not enabled.');
        }
        
        // Get NiceHash balance if API is enabled
        $balance = null;
        $balancePending = null;
        
        if (defined('YAAMP_USE_NICEHASH_API') && YAAMP_USE_NICEHASH_API) {
            $balanceData = $this->getNicehashBalance();
            if ($balanceData) {
                $balance = $balanceData['confirmed'];
                $balancePending = $balanceData['pending'];
            }
        }
        
        // Get all NiceHash orders
        $dataProvider = new ActiveDataProvider([
            'query' => Nicehash::find()->orderBy(['algo' => SORT_ASC]),
            'pagination' => false,
        ]);
        
        // Get statistics
        $stats = Nicehash::getStatistics();
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'balance' => $balance,
            'balancePending' => $balancePending,
            'stats' => $stats,
        ]);
    }

    /**
     * AJAX endpoint for NiceHash statistics
     * Returns updated order data
     * 
     * @return string
     */
    public function actionIndex_results()
    {
        // Check if NiceHash API is enabled
        if (!defined('YAAMP_USE_NICEHASH_API') || !YAAMP_USE_NICEHASH_API) {
            return $this->renderPartial('index_results', [
                'orders' => [],
                'balance' => null,
                'balancePending' => null,
                'error' => 'NiceHash API integration is not enabled.',
            ]);
        }
        
        // Get NiceHash balance
        $balanceData = $this->getNicehashBalance();
        $balance = $balanceData ? $balanceData['confirmed'] : null;
        $balancePending = $balanceData ? $balanceData['pending'] : null;
        
        // Get all NiceHash orders with related data
        $orders = Nicehash::find()
            ->orderBy(['algo' => SORT_ASC])
            ->all();
        
        // Enrich orders with additional data
        foreach ($orders as $order) {
            // Get service price for comparison
            $order->servicePrice = $this->getServicePrice($order->algo);
            
            // Get Yaamp hashrate price for comparison
            $order->yaampPrice = $this->getYaampPrice($order->algo);
        }
        
        return $this->renderPartial('index_results', [
            'orders' => $orders,
            'balance' => $balance,
            'balancePending' => $balancePending,
            'error' => null,
        ]);
    }

    /**
     * Start a NiceHash order
     * 
     * @param int $id Order ID
     * @return Response
     */
    public function actionStart($id)
    {
        $order = $this->findModel($id);
        
        $order->active = 1;
        if ($order->save()) {
            Yii::$app->session->setFlash('success', "NiceHash order for {$order->algo} started.");
        } else {
            Yii::$app->session->setFlash('error', 'Failed to start NiceHash order.');
        }
        
        return $this->redirect(['index']);
    }

    /**
     * Stop a NiceHash order
     * 
     * @param int $id Order ID
     * @return Response
     */
    public function actionStop($id)
    {
        $order = $this->findModel($id);
        
        $order->active = 0;
        if ($order->save()) {
            Yii::$app->session->setFlash('success', "NiceHash order for {$order->algo} stopped.");
        } else {
            Yii::$app->session->setFlash('error', 'Failed to stop NiceHash order.');
        }
        
        return $this->redirect(['index']);
    }

    /**
     * Get NiceHash account balance from API
     * 
     * @return array|null Balance data or null on error
     */
    protected function getNicehashBalance()
    {
        if (!defined('NICEHASH_API_KEY') || !defined('NICEHASH_API_ID')) {
            return null;
        }
        
        $apiKey = NICEHASH_API_KEY;
        $apiId = NICEHASH_API_ID;
        
        if (empty($apiKey) || empty($apiId)) {
            return null;
        }
        
        try {
            $url = "https://api.nicehash.com/api?method=balance&id={$apiId}&key={$apiKey}";
            $response = @file_get_contents($url);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['result'])) {
                return null;
            }
            
            return [
                'confirmed' => $data['result']['balance_confirmed'] ?? 0,
                'pending' => $data['result']['balance_pending'] ?? 0,
            ];
        } catch (\Exception $e) {
            Yii::error("Failed to fetch NiceHash balance: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get service price for an algorithm
     * 
     * @param string $algo Algorithm name
     * @return float|null Service price or null
     */
    protected function getServicePrice($algo)
    {
        try {
            // Check if Services model exists
            if (!class_exists('app\models\Services')) {
                return null;
            }
            
            $service = Services::find()
                ->select('price')
                ->where(['algo' => $algo])
                ->scalar();
            
            if ($service !== false) {
                return $service * 1000; // Convert to mBTC
            }
        } catch (\Exception $e) {
            Yii::error("Failed to fetch service price: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get Yaamp hashrate price for an algorithm
     * 
     * @param string $algo Algorithm name
     * @return float|null Yaamp price or null
     */
    protected function getYaampPrice($algo)
    {
        try {
            // Check if Hashrate model exists
            if (!class_exists('app\models\Hashrate')) {
                return null;
            }
            
            $price = Hashrate::find()
                ->select('price')
                ->where(['algo' => $algo])
                ->orderBy(['time' => SORT_DESC])
                ->limit(1)
                ->scalar();
            
            return $price !== false ? $price : null;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch Yaamp price: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Finds the Nicehash model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * 
     * @param integer $id
     * @return Nicehash the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Nicehash::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested NiceHash order does not exist.');
    }
}
