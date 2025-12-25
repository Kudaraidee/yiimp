<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;

use app\models\Jobs;
use app\models\Renters;
use app\models\Rentertxs;
use app\models\Hashrenter;

/**
 * RentingController handles rental marketplace functionality
 */
class RentingController extends Controller
{
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
                        'actions' => ['index', 'login'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['create', 'orders', 'balance', 'settings', 'deposit', 'deposit-process', 'complete-order', 'activate-order', 'deactivate-order', 'logout'],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            return $this->isRenterLoggedIn();
                        }
                    ],
                    [
                        'actions' => ['admin'],
                        'allow' => true,
                        'roles' => ['@'], // Requires admin authentication
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
     * Displays rental marketplace with active orders
     *
     * @return string
     */
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Jobs::find()
                ->where(['active' => 1])
                ->orderBy(['price' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Renter login page
     *
     * @return string|Response
     */
    public function actionLogin()
    {
        if ($this->isRenterLoggedIn()) {
            return $this->redirect(['orders']);
        }

        $model = new Renters();
        $model->scenario = 'login';

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            
            // Check if this is a login or registration
            if (isset($post['action']) && $post['action'] === 'register') {
                return $this->handleRegistration($post);
            } else {
                return $this->handleLogin($post);
            }
        }

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Handle renter login
     */
    protected function handleLogin($post)
    {
        $address = $post['address'] ?? null;
        $password = $post['password'] ?? null;

        if (!$address || !$password) {
            Yii::$app->session->setFlash('error', 'Please provide address and password.');
            return $this->redirect(['login']);
        }

        $renter = Renters::findOne(['address' => $address]);
        
        if (!$renter || !$renter->validatePassword($password)) {
            Yii::$app->session->setFlash('error', 'Invalid address or password.');
            return $this->redirect(['login']);
        }

        // Login successful
        Yii::$app->session->set('renter_id', $renter->id);
        Yii::$app->session->setFlash('success', 'Login successful!');
        
        return $this->redirect(['orders']);
    }

    /**
     * Handle renter registration
     */
    protected function handleRegistration($post)
    {
        $address = $post['address'] ?? null;
        $email = $post['email'] ?? null;
        $password = $post['password'] ?? null;
        $confirmPassword = $post['confirm_password'] ?? null;

        if (!$address || !$password || !$confirmPassword) {
            Yii::$app->session->setFlash('error', 'Please fill all required fields.');
            return $this->redirect(['login']);
        }

        if ($password !== $confirmPassword) {
            Yii::$app->session->setFlash('error', 'Passwords do not match.');
            return $this->redirect(['login']);
        }

        // Check if address already exists
        if (Renters::findOne(['address' => $address])) {
            Yii::$app->session->setFlash('error', 'Address already registered.');
            return $this->redirect(['login']);
        }

        // Create new renter
        $renter = new Renters();
        $renter->address = $address;
        $renter->email = $email;
        $renter->setPassword($password);
        $renter->generateApiKey();
        $renter->balance = 0;
        $renter->received = 0;
        $renter->spent = 0;
        $renter->unconfirmed = 0;

        if ($renter->save()) {
            // Auto-login after registration
            Yii::$app->session->set('renter_id', $renter->id);
            Yii::$app->session->setFlash('success', 'Account created successfully! Your API key: ' . $renter->apikey);
            return $this->redirect(['orders']);
        } else {
            Yii::$app->session->setFlash('error', 'Failed to create account. Please try again.');
            return $this->redirect(['login']);
        }
    }

    /**
     * View renter's orders
     *
     * @return string
     */
    public function actionOrders()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => Jobs::find()
                ->where(['renterid' => $renter->id])
                ->orderBy(['time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('orders', [
            'renter' => $renter,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Complete an order (deactivate and deduct balance)
     *
     * @param int $id
     * @return Response
     */
    public function actionCompleteOrder($id)
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $job = Jobs::findOne(['id' => $id, 'renterid' => $renter->id]);
        
        if (!$job) {
            Yii::$app->session->setFlash('error', 'Order not found.');
            return $this->redirect(['orders']);
        }

        // Calculate cost based on hashrate delivered
        $hashrateRecords = Hashrenter::find()
            ->where(['jobid' => $job->id])
            ->all();
        
        $totalCost = 0;
        foreach ($hashrateRecords as $record) {
            // Calculate cost: hashrate * price * time (in hours)
            $hours = 1; // Assuming records are per hour
            $totalCost += $record->hashrate * $job->price * $hours / 24;
        }

        // Start transaction
        $transaction = Yii::$app->db->beginTransaction();
        
        try {
            // Deduct balance
            if ($renter->deductBalance($totalCost)) {
                // Create transaction record
                if (Rentertxs::createOrder($renter->id, $totalCost, $job->id)) {
                    // Deactivate job
                    $job->deactivate();
                    
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', 'Order completed. Cost: ' . number_format($totalCost, 8) . ' BTC');
                    return $this->redirect(['orders']);
                }
            } else {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'Insufficient balance to complete order.');
            }
            
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Error completing order: ' . $e->getMessage());
        }

        return $this->redirect(['orders']);
    }

    /**
     * Activate an order
     *
     * @param int $id
     * @return Response
     */
    public function actionActivateOrder($id)
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $job = Jobs::findOne(['id' => $id, 'renterid' => $renter->id]);
        
        if (!$job) {
            Yii::$app->session->setFlash('error', 'Order not found.');
            return $this->redirect(['orders']);
        }

        if ($job->activate()) {
            Yii::$app->session->setFlash('success', 'Order activated successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Failed to activate order.');
        }

        return $this->redirect(['orders']);
    }

    /**
     * Deactivate an order
     *
     * @param int $id
     * @return Response
     */
    public function actionDeactivateOrder($id)
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $job = Jobs::findOne(['id' => $id, 'renterid' => $renter->id]);
        
        if (!$job) {
            Yii::$app->session->setFlash('error', 'Order not found.');
            return $this->redirect(['orders']);
        }

        if ($job->deactivate()) {
            Yii::$app->session->setFlash('success', 'Order deactivated successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Failed to deactivate order.');
        }

        return $this->redirect(['orders']);
    }

    /**
     * Create rental order page
     *
     * @return string|Response
     */
    public function actionCreate()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $model = new Jobs();
        $model->renterid = $renter->id;

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            // Validate that renter has sufficient balance
            $estimatedCost = $model->price * $model->speed * 24; // Cost for 24 hours
            
            if ($renter->getAvailableBalance() < $estimatedCost) {
                Yii::$app->session->setFlash('error', 'Insufficient balance. Estimated cost: ' . number_format($estimatedCost, 8) . ' BTC');
                return $this->render('create', ['model' => $model, 'renter' => $renter]);
            }

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Rental order created successfully!');
                return $this->redirect(['orders']);
            } else {
                Yii::$app->session->setFlash('error', 'Failed to create order. Please check the form for errors.');
            }
        }

        return $this->render('create', [
            'model' => $model,
            'renter' => $renter,
        ]);
    }

    /**
     * Admin view of all rental orders and statistics
     *
     * @return string
     */
    public function actionAdmin()
    {
        // Get all renters
        $rentersDataProvider = new ActiveDataProvider([
            'query' => Renters::find()->orderBy(['created' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        // Get all jobs
        $jobsDataProvider = new ActiveDataProvider([
            'query' => Jobs::find()->orderBy(['time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        // Calculate statistics
        $stats = [
            'total_renters' => Renters::find()->count(),
            'active_orders' => Jobs::find()->where(['active' => 1])->count(),
            'total_orders' => Jobs::find()->count(),
            'total_balance' => Renters::find()->sum('balance'),
            'total_received' => Renters::find()->sum('received'),
            'total_spent' => Renters::find()->sum('spent'),
        ];

        return $this->render('admin', [
            'rentersDataProvider' => $rentersDataProvider,
            'jobsDataProvider' => $jobsDataProvider,
            'stats' => $stats,
        ]);
    }

    /**
     * Rental settings page
     *
     * @return string|Response
     */
    public function actionSettings()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            
            // Update custom settings
            $renter->custom_server = $post['custom_server'] ?? null;
            $renter->custom_address = $post['custom_address'] ?? null;
            $renter->email = $post['email'] ?? null;
            
            // Update password if provided
            if (!empty($post['new_password'])) {
                if (!empty($post['current_password']) && $renter->validatePassword($post['current_password'])) {
                    if ($post['new_password'] === $post['confirm_password']) {
                        $renter->setPassword($post['new_password']);
                    } else {
                        Yii::$app->session->setFlash('error', 'New passwords do not match.');
                        return $this->render('settings', ['renter' => $renter]);
                    }
                } else {
                    Yii::$app->session->setFlash('error', 'Current password is incorrect.');
                    return $this->render('settings', ['renter' => $renter]);
                }
            }
            
            if ($renter->save()) {
                Yii::$app->session->setFlash('success', 'Settings updated successfully.');
                return $this->redirect(['settings']);
            } else {
                Yii::$app->session->setFlash('error', 'Failed to update settings.');
            }
        }

        return $this->render('settings', [
            'renter' => $renter,
        ]);
    }

    /**
     * Balance history page
     *
     * @return string
     */
    public function actionBalance()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => Rentertxs::find()
                ->where(['renterid' => $renter->id])
                ->orderBy(['time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $this->render('balance', [
            'renter' => $renter,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Deposit page
     *
     * @return string
     */
    public function actionDeposit()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        return $this->render('deposit', [
            'renter' => $renter,
        ]);
    }

    /**
     * Process deposit
     *
     * @return Response
     */
    public function actionDepositProcess()
    {
        $renter = $this->getCurrentRenter();
        
        if (!$renter) {
            return $this->redirect(['login']);
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $amount = floatval($post['amount'] ?? 0);
            $txHash = $post['tx_hash'] ?? '';
            $address = $post['address'] ?? '';

            if ($amount <= 0) {
                Yii::$app->session->setFlash('error', 'Invalid deposit amount.');
                return $this->redirect(['deposit']);
            }

            // Start transaction
            $transaction = Yii::$app->db->beginTransaction();
            
            try {
                // Update renter balance
                if ($renter->addBalance($amount)) {
                    // Create transaction record
                    if (Rentertxs::createDeposit($renter->id, $amount, $address, $txHash)) {
                        $transaction->commit();
                        Yii::$app->session->setFlash('success', 'Deposit of ' . number_format($amount, 8) . ' BTC recorded successfully!');
                        return $this->redirect(['balance']);
                    }
                }
                
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'Failed to process deposit. Please try again.');
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'Error processing deposit: ' . $e->getMessage());
            }
        }

        return $this->redirect(['deposit']);
    }

    /**
     * Renter logout
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->session->remove('renter_id');
        Yii::$app->session->setFlash('success', 'Logged out successfully.');
        return $this->redirect(['index']);
    }

    /**
     * Check if renter is logged in
     */
    protected function isRenterLoggedIn()
    {
        $session = Yii::$app->session;
        return $session->has('renter_id');
    }

    /**
     * Get current renter
     */
    protected function getCurrentRenter()
    {
        $session = Yii::$app->session;
        if ($session->has('renter_id')) {
            return Renters::findOne($session->get('renter_id'));
        }
        return null;
    }
}
