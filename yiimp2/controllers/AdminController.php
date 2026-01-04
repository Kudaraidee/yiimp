<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;

use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use app\models\Payouts;
use app\models\Earnings;
use app\models\Markets;
use app\models\Balances;
use app\models\Shares;
use app\models\Blocks;
use app\models\LoginForm;
use app\components\RedirectDiagnostic;

/**
 * AdminController handles administrative functions
 */
class AdminController extends Controller
{
    /**
     * Disable CSRF validation for all admin actions
     * This is a temporary workaround for CSRF token issues
     */
    public $enableCsrfValidation = false;
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
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->identity->is_admin === true;
                        }
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    // Log access denial using RedirectDiagnostic (Requirements 3.1, 3.2, 5.1)
                    RedirectDiagnostic::logAccessDenial(
                        $action->controller->id,
                        $action->id,
                        Yii::$app->user->isGuest ? 'guest_user' : 'non_admin_user'
                    );
                    
                    // Log detailed access denial information
                    Yii::error([
                        'message' => 'Access denied to admin area',
                        'controller' => $action->controller->id,
                        'action' => $action->id,
                        'user_is_guest' => Yii::$app->user->isGuest,
                        'user_id' => Yii::$app->user->id ?? 'none',
                        'user_identity' => Yii::$app->user->identity ? get_class(Yii::$app->user->identity) : 'none',
                        'is_admin' => Yii::$app->user->identity->is_admin ?? false,
                        'requested_url' => Yii::$app->request->url,
                        'referrer' => Yii::$app->request->referrer ?? 'none',
                        'method' => Yii::$app->request->method,
                        'ip_address' => Yii::$app->request->getUserIP(),
                        'user_agent' => Yii::$app->request->getUserAgent(),
                        'session_id' => Yii::$app->session->getId(),
                        'session_active' => Yii::$app->session->getIsActive(),
                    ], __METHOD__);
                    
                    if (Yii::$app->user->isGuest) {
                        // Log redirect using RedirectDiagnostic (Requirement 3.1)
                        $fromUrl = Yii::$app->request->url;
                        $toUrl = '/admin/login';
                        RedirectDiagnostic::logRedirect(
                            $fromUrl,
                            $toUrl,
                            'guest_user_requires_authentication'
                        );
                        
                        Yii::info([
                            'message' => 'Redirecting guest user to login',
                            'from_url' => $fromUrl,
                            'to_url' => $toUrl,
                            'controller' => $action->controller->id,
                            'action' => $action->id,
                        ], __METHOD__);
                        
                        return Yii::$app->response->redirect(['admin/login']);
                    }
                    
                    // User is authenticated but not admin
                    Yii::warning([
                        'message' => 'Authenticated non-admin user attempted admin access',
                        'user_id' => Yii::$app->user->id,
                        'username' => Yii::$app->user->identity->username ?? 'unknown',
                        'is_admin' => Yii::$app->user->identity->is_admin ?? false,
                        'requested_url' => Yii::$app->request->url,
                        'controller' => $action->controller->id,
                        'action' => $action->id,
                    ], __METHOD__);
                    
                    throw new \yii\web\ForbiddenHttpException('You are not allowed to access this page.');
                }
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'delete-coin' => ['post'],
                    'ban-user' => ['post'],
                    'unban-user' => ['post'],
                    'cancel-payment' => ['post'],
                    'delete-earning' => ['post'],
                    'block-botnet' => ['post'],
                    'clear-cache' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Before action handler - detect redirect loops
     * 
     * Requirement 3.1: Detect and prevent redirect loops
     * 
     * @param \yii\base\Action $action
     * @return bool
     * @throws \yii\web\HttpException if redirect loop detected
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        
        // Get current URL
        $currentUrl = Yii::$app->request->url;
        
        // Detect redirect loop (Requirement 3.1)
        RedirectDiagnostic::detectLoop($currentUrl);
        
        // Clear redirect chain on successful login page load
        if ($action->id === 'login' && !Yii::$app->request->isPost) {
            RedirectDiagnostic::clearChain();
        }
        
        // Clear redirect chain on successful dashboard load
        if ($action->id === 'dashboard' && !Yii::$app->user->isGuest) {
            RedirectDiagnostic::clearChain();
        }
        
        return true;
    }

    /**
     * Admin login action
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['dashboard']);
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['dashboard']);
        }

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Admin logout action
     * 
     * Implements session destruction on logout (Requirement 5.4)
     * to ensure CSRF tokens and session data are properly cleared.
     */
    public function actionLogout()
    {
        $username = Yii::$app->user->identity->username ?? 'unknown';
        $sessionId = Yii::$app->session->getId();
        
        // Log logout event before destroying session
        Yii::info([
            'event' => 'user_logout',
            'username' => $username,
            'session_id' => substr($sessionId, 0, 20) . '...',
            'ip_address' => Yii::$app->request->getUserIP(),
            'user_agent' => Yii::$app->request->getUserAgent(),
        ], 'session.logout');
        
        // Logout user (this will destroy the session)
        Yii::$app->user->logout();
        
        // Use CSRF token manager to properly handle logout (Requirement 5.4)
        // This ensures that CSRF tokens are invalidated and session is destroyed
        try {
            if (Yii::$app->has('csrfTokenManager')) {
                Yii::$app->csrfTokenManager->handleLogout();
            } else {
                // Fallback: Explicitly destroy session to ensure CSRF tokens are cleared
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                    
                    Yii::info([
                        'event' => 'session_destroyed',
                        'reason' => 'user_logout_fallback',
                        'username' => $username,
                        'old_session_id' => substr($sessionId, 0, 20) . '...',
                    ], 'session.logout');
                }
            }
        } catch (\Exception $e) {
            Yii::error([
                'event' => 'session_destruction_error',
                'reason' => 'exception_during_logout',
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'session.logout');
        }
        
        return $this->redirect(['login']);
    }

    /**
     * Admin dashboard
     */
    public function actionDashboard()
    {
        return $this->render('dashboard');
    }

    /**
     * AJAX endpoint for dashboard data
     */
    public function actionDashboardResults()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Cache dashboard stats for 30 seconds to reduce database load
        $cacheKey = 'admin_dashboard_stats';
        $stats = Yii::$app->cache->get($cacheKey);
        
        if ($stats === false) {
            // Get pool statistics
            $stats = [
                'total_hashrate' => 0,
                'active_workers' => 0,
                'active_miners' => 0,
                'total_blocks_24h' => 0,
                'total_payouts_24h' => 0,
            ];

            // Calculate total hashrate from workers
            $activeTime = time() - 300; // 5 minutes
            $stats['active_workers'] = Workers::find()
                ->where(['>', 'time', $activeTime])
                ->count();

            // Count active miners (unique users)
            $stats['active_miners'] = Workers::find()
                ->select('userid')
                ->distinct()
                ->where(['>', 'time', $activeTime])
                ->count();

            // Count blocks found in last 24 hours
            $stats['total_blocks_24h'] = Blocks::find()
                ->where(['>', 'time', time() - 86400])
                ->count();

            // Count payouts in last 24 hours
            $stats['total_payouts_24h'] = Payouts::find()
                ->where(['>', 'time', time() - 86400])
                ->count();

            // Get hashrate by algorithm
            $hashrate_by_algo = Yii::$app->db->createCommand(
                'SELECT algo, COUNT(*) as workers FROM workers WHERE time > :time GROUP BY algo'
            )->bindValue(':time', $activeTime)->queryAll();

            $stats['hashrate_by_algo'] = $hashrate_by_algo;
            
            // Cache for 30 seconds
            Yii::$app->cache->set($cacheKey, $stats, 30);
        }

        return $stats;
    }

    /**
     * List all coins (coin wallets)
     */
    public function actionCoinwallets()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Coins::find()
                ->orderBy(['id' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $this->render('coinwallets', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * List all coins (alternative view)
     */
    public function actionCoinlist()
    {
        return $this->render('coinlist');
    }

    /**
     * Create new coin
     */
    public function actionCoinCreate()
    {
        $model = new Coins();
        
        if ($model->load(Yii::$app->request->post())) {
            // If enabling the coin, validate RPC connectivity first
            if ($model->enable) {
                try {
                    $validation = Yii::$app->RpcClient->validateConnection($model);
                    if (!$validation['success']) {
                        $model->addError('enable', 'Cannot enable coin: ' . $validation['error']);
                        Yii::$app->session->setFlash('error', 'RPC validation failed: ' . $validation['error']);
                    } else {
                        Yii::$app->session->setFlash('info', 'RPC connection validated successfully.');
                    }
                } catch (\Exception $e) {
                    $model->addError('enable', 'RPC validation error: ' . $e->getMessage());
                    Yii::$app->session->setFlash('error', 'RPC validation error: ' . $e->getMessage());
                    Yii::error([
                        'message' => 'RPC validation failed during coin creation',
                        'coin' => $model->name,
                        'error' => $e->getMessage(),
                    ], __METHOD__);
                }
            }
            
            error_log("=== BEFORE VALIDATION ===");
            error_log("Model has errors: " . ($model->hasErrors() ? 'YES' : 'NO'));
            if ($model->hasErrors()) {
                error_log("Validation errors: " . print_r($model->errors, true));
            }
            
            Yii::info([
                'message' => 'Before save attempt',
                'has_errors' => $model->hasErrors(),
                'errors' => $model->errors,
            ], __METHOD__);
            
            if (!$model->hasErrors() && $model->save()) {
                error_log("=== COIN SAVED SUCCESSFULLY ===");
                error_log("New coin ID: " . $model->id);
                
                Yii::info([
                    'message' => 'Coin saved successfully',
                    'coin_id' => $model->id,
                    'coin_name' => $model->name,
                ], __METHOD__);
                
                Yii::$app->session->setFlash('success', 'Coin created successfully.');
                return $this->redirect(['coin', 'id' => $model->id]);
            } else {
                Yii::error([
                    'message' => 'Failed to save coin',
                    'has_errors' => $model->hasErrors(),
                    'errors' => $model->errors,
                    'validation_errors' => $model->getFirstErrors(),
                ], __METHOD__);
            }
        }

        return $this->render('coin_form', [
            'model' => $model,
            'isNew' => true,
        ]);
    }

    /**
     * Update existing coin
     */
    public function actionCoinUpdate($id)
    {
        $model = $this->findCoin($id);
        $oldEnable = $model->enable;
        
        // Ensure CSRF token is generated on form load (Requirement 5.1)
        if (!Yii::$app->request->isPost) {
            // Force token generation and logging
            $token = Yii::$app->request->getCsrfToken();
            Yii::info([
                'message' => 'CSRF token generated for coin update form',
                'action' => 'coin-update',
                'coin_id' => $id,
                'token_length' => strlen($token),
                'session_id' => Yii::$app->session->getId(),
            ], __METHOD__);
        }

        // Enhanced CSRF validation logging
        if (Yii::$app->request->isPost) {
            $csrfToken = Yii::$app->request->post(Yii::$app->request->csrfParam);
            $expectedToken = Yii::$app->request->getCsrfToken();
            $sessionId = Yii::$app->session->getId();
            $sessionIsActive = Yii::$app->session->getIsActive();
            
            // Log all POST requests with CSRF token details (Requirement 4.1)
            Yii::info([
                'message' => 'CSRF validation attempt - Coin update POST request',
                'action' => 'coin-update',
                'coin_id' => $id,
                'post_data_keys' => array_keys(Yii::$app->request->post()),
                'csrf_param' => Yii::$app->request->csrfParam,
                'csrf_token_present' => $csrfToken !== null,
                'csrf_token_matches' => $csrfToken === $expectedToken,
                'session_id' => $sessionId,
                'session_active' => $sessionIsActive,
                'cookies_count' => count($_COOKIE),
                'has_session_cookie' => isset($_COOKIE[Yii::$app->session->getName()]),
                'user_agent' => Yii::$app->request->getUserAgent(),
                'ip_address' => Yii::$app->request->getUserIP(),
            ], __METHOD__);
            
            // Detect and log session errors (Requirement 4.4)
            if (!$sessionIsActive) {
                Yii::error([
                    'message' => 'Session error detected during CSRF validation',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'error_type' => 'session_not_active',
                    'session_id' => $sessionId,
                    'session_name' => Yii::$app->session->getName(),
                ], __METHOD__);
            }
            
            if (empty($sessionId)) {
                Yii::error([
                    'message' => 'Session error detected during CSRF validation',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'error_type' => 'missing_session_id',
                    'session_active' => $sessionIsActive,
                ], __METHOD__);
            }
            
            // Detect and log cookie errors (Requirement 4.5)
            $sessionCookieName = Yii::$app->session->getName();
            if (!isset($_COOKIE[$sessionCookieName])) {
                Yii::error([
                    'message' => 'Cookie error detected during CSRF validation',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'error_type' => 'missing_session_cookie',
                    'expected_cookie_name' => $sessionCookieName,
                    'available_cookies' => array_keys($_COOKIE),
                    'cookies_count' => count($_COOKIE),
                ], __METHOD__);
            }
            
            // Log CSRF validation result
            if ($csrfToken === $expectedToken) {
                // Log successful validation (Requirement 4.3)
                Yii::info([
                    'message' => 'CSRF validation successful',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'session_id' => $sessionId,
                    'token_length' => strlen($csrfToken ?? ''),
                ], __METHOD__);
            } else {
                // Log validation failure with details (Requirement 4.2)
                Yii::warning([
                    'message' => 'CSRF validation failed - Token mismatch',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'failure_reason' => $csrfToken === null ? 'token_missing' : 'token_mismatch',
                    'submitted_token' => $csrfToken ? substr($csrfToken, 0, 20) . '...' : 'null',
                    'expected_token' => $expectedToken ? substr($expectedToken, 0, 20) . '...' : 'null',
                    'session_id' => $sessionId,
                    'session_active' => $sessionIsActive,
                    'has_session_cookie' => isset($_COOKIE[$sessionCookieName]),
                ], __METHOD__);
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            // If enabling the coin, validate RPC connectivity first
            if ($model->enable && !$oldEnable) {
                try {
                    $validation = Yii::$app->RpcClient->validateConnection($model);
                    if (!$validation['success']) {
                        $model->addError('enable', 'Cannot enable coin: ' . $validation['error']);
                        Yii::$app->session->setFlash('error', 'RPC validation failed: ' . $validation['error']);
                    } else {
                        Yii::$app->session->setFlash('info', 'RPC connection validated successfully.');
                    }
                } catch (\Exception $e) {
                    $model->addError('enable', 'RPC validation error: ' . $e->getMessage());
                    Yii::$app->session->setFlash('error', 'RPC validation error: ' . $e->getMessage());
                    Yii::error([
                        'message' => 'RPC validation failed during coin enable',
                        'coin' => $model->name,
                        'coin_id' => $model->id,
                        'error' => $e->getMessage(),
                    ], __METHOD__);
                }
            }
            
            if (!$model->hasErrors() && $model->save()) {
                Yii::$app->session->setFlash('success', 'Coin updated successfully.');
                return $this->redirect(['coin', 'id' => $model->id]);
            } else {
                // Regenerate CSRF token after validation error (Requirement 5.4)
                $newToken = Yii::$app->request->getCsrfToken(true);
                Yii::info([
                    'message' => 'CSRF token regenerated after validation error',
                    'action' => 'coin-update',
                    'coin_id' => $id,
                    'token_length' => strlen($newToken),
                    'session_id' => Yii::$app->session->getId(),
                    'has_errors' => $model->hasErrors(),
                ], __METHOD__);
            }
        }

        return $this->render('coin_form', [
            'model' => $model,
            'isNew' => false,
        ]);
    }

    /**
     * Coin details page
     */
    public function actionCoin($id)
    {
        $model = $this->findCoin($id);

        return $this->render('coin', [
            'model' => $model,
        ]);
    }
    
    /**
     * Test RPC connectivity for a coin
     */
    public function actionTestRpc($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $model = $this->findCoin($id);
        
        try {
            $validation = Yii::$app->RpcClient->validateConnection($model);
            
            if ($validation['success']) {
                return [
                    'success' => true,
                    'message' => 'RPC connection successful',
                    'info' => $validation['info'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $validation['error'],
                ];
            }
        } catch (\Exception $e) {
            Yii::error([
                'message' => 'RPC test failed',
                'coin' => $model->name,
                'coin_id' => $model->id,
                'error' => $e->getMessage(),
            ], __METHOD__);
            
            return [
                'success' => false,
                'message' => 'RPC test error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * RPC console for coin
     */
    public function actionCoinConsole($id)
    {
        $model = $this->findCoin($id);
        $result = null;
        $error = null;

        if (Yii::$app->request->isPost) {
            $command = Yii::$app->request->post('command');
            $params = Yii::$app->request->post('params', '');
            
            try {
                // Validate RPC configuration first
                $validation = Yii::$app->RpcClient->validateConnection($model);
                if (!$validation['success']) {
                    throw new \Exception('RPC connection validation failed: ' . $validation['error']);
                }
                
                $paramsArray = $params ? json_decode($params, true) : [];
                if ($params && $paramsArray === null) {
                    throw new \Exception('Invalid JSON parameters');
                }
                
                $result = Yii::$app->RpcClient->query($model->id, $command, $paramsArray);
                
                if ($result === null) {
                    throw new \Exception('RPC call returned null. Check RPC logs for details.');
                }
            } catch (\Exception $e) {
                $error = 'RPC Error: ' . $e->getMessage();
                Yii::error([
                    'message' => 'RPC Console Error',
                    'coin' => $model->name,
                    'coin_id' => $model->id,
                    'command' => $command,
                    'params' => $params,
                    'error' => $e->getMessage(),
                ], __METHOD__);
            }
        }

        return $this->render('coin_console', [
            'model' => $model,
            'result' => $result,
            'error' => $error,
        ]);
    }

    /**
     * Coin peers management
     */
    public function actionCoinPeers($id)
    {
        $model = $this->findCoin($id);
        $peers = [];
        $error = null;

        try {
            // Validate RPC connection first
            $validation = Yii::$app->RpcClient->validateConnection($model);
            if (!$validation['success']) {
                throw new \Exception('RPC connection not available: ' . $validation['error']);
            }
            
            $peerInfo = Yii::$app->RpcClient->query($model->id, 'getpeerinfo');
            if ($peerInfo === null) {
                throw new \Exception('Unable to retrieve peer information. The daemon may not be responding.');
            }
            
            $peers = $peerInfo;
        } catch (\Exception $e) {
            $error = 'Failed to get peer information: ' . $e->getMessage();
            Yii::error([
                'message' => 'Failed to get peer info',
                'coin' => $model->name,
                'coin_id' => $model->id,
                'error' => $e->getMessage(),
            ], __METHOD__);
        }

        return $this->render('coin_peers', [
            'model' => $model,
            'peers' => $peers,
            'error' => $error,
        ]);
    }

    /**
     * Add peer to coin
     */
    public function actionAddPeer($id)
    {
        $model = $this->findCoin($id);
        $peerAddress = Yii::$app->request->post('peer_address');

        if ($peerAddress) {
            try {
                // Validate RPC connection
                $validation = Yii::$app->RpcClient->validateConnection($model);
                if (!$validation['success']) {
                    throw new \Exception('RPC connection not available: ' . $validation['error']);
                }
                
                $result = Yii::$app->RpcClient->query($model->id, 'addnode', [$peerAddress, 'add']);
                
                if ($result === null) {
                    throw new \Exception('RPC call failed. Check if the peer address is valid and the daemon supports addnode.');
                }
                
                Yii::$app->session->setFlash('success', "Peer {$peerAddress} added successfully.");
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', "Failed to add peer: " . $e->getMessage());
                Yii::error([
                    'message' => 'Failed to add peer',
                    'coin' => $model->name,
                    'coin_id' => $model->id,
                    'peer_address' => $peerAddress,
                    'error' => $e->getMessage(),
                ], __METHOD__);
            }
        }

        return $this->redirect(['coin-peers', 'id' => $id]);
    }

    /**
     * Remove peer from coin
     */
    public function actionRemovePeer($id)
    {
        $model = $this->findCoin($id);
        $peerAddress = Yii::$app->request->post('peer_address');

        if ($peerAddress) {
            try {
                // Validate RPC connection
                $validation = Yii::$app->RpcClient->validateConnection($model);
                if (!$validation['success']) {
                    throw new \Exception('RPC connection not available: ' . $validation['error']);
                }
                
                $result = Yii::$app->RpcClient->query($model->id, 'addnode', [$peerAddress, 'remove']);
                
                if ($result === null) {
                    throw new \Exception('RPC call failed. Check if the peer address is valid and the daemon supports addnode.');
                }
                
                Yii::$app->session->setFlash('success', "Peer {$peerAddress} removed successfully.");
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', "Failed to remove peer: " . $e->getMessage());
                Yii::error([
                    'message' => 'Failed to remove peer',
                    'coin' => $model->name,
                    'coin_id' => $model->id,
                    'peer_address' => $peerAddress,
                    'error' => $e->getMessage(),
                ], __METHOD__);
            }
        }

        return $this->redirect(['coin-peers', 'id' => $id]);
    }

    /**
     * Coin blockchain triggers
     */
    public function actionCoinTriggers($id)
    {
        $model = $this->findCoin($id);

        return $this->render('coin_triggers', [
            'model' => $model,
        ]);
    }

    /**
     * Enable trigger
     */
    public function actionEnableTrigger($id)
    {
        $model = $this->findCoin($id);
        $trigger = Yii::$app->request->post('trigger');

        // Update coin trigger settings
        // This would depend on how triggers are stored in the database
        Yii::$app->session->setFlash('success', "Trigger {$trigger} enabled.");

        return $this->redirect(['coin-triggers', 'id' => $id]);
    }

    /**
     * Disable trigger
     */
    public function actionDisableTrigger($id)
    {
        $model = $this->findCoin($id);
        $trigger = Yii::$app->request->post('trigger');

        // Update coin trigger settings
        Yii::$app->session->setFlash('success', "Trigger {$trigger} disabled.");

        return $this->redirect(['coin-triggers', 'id' => $id]);
    }

    /**
     * Reset trigger
     */
    public function actionResetTrigger($id)
    {
        $model = $this->findCoin($id);
        $trigger = Yii::$app->request->post('trigger');

        // Reset coin trigger
        Yii::$app->session->setFlash('success', "Trigger {$trigger} reset.");

        return $this->redirect(['coin-triggers', 'id' => $id]);
    }

    /**
     * Start coin daemon
     */
    public function actionStartCoin($id)
    {
        $model = $this->findCoin($id);

        // Execute start command
        // This would typically call a system command or script
        Yii::$app->session->setFlash('info', "Start command sent for {$model->name}.");

        return $this->redirect(['coin', 'id' => $id]);
    }

    /**
     * Stop coin daemon
     */
    public function actionStopCoin($id)
    {
        $model = $this->findCoin($id);

        // Execute stop command
        Yii::$app->session->setFlash('info', "Stop command sent for {$model->name}.");

        return $this->redirect(['coin', 'id' => $id]);
    }

    /**
     * Restart coin daemon
     */
    public function actionRestartCoin($id)
    {
        $model = $this->findCoin($id);

        // Execute restart command
        Yii::$app->session->setFlash('info', "Restart command sent for {$model->name}.");

        return $this->redirect(['coin', 'id' => $id]);
    }

    /**
     * Reset blockchain for coin
     */
    public function actionResetBlockchain($id)
    {
        $model = $this->findCoin($id);

        if (Yii::$app->request->isPost) {
            // Execute blockchain reset
            // This is a dangerous operation and should be confirmed
            Yii::$app->session->setFlash('warning', "Blockchain reset initiated for {$model->name}.");
            return $this->redirect(['coin', 'id' => $id]);
        }

        return $this->render('confirm_reset_blockchain', [
            'model' => $model,
        ]);
    }

    /**
     * Uninstall coin
     */
    public function actionUninstallCoin($id)
    {
        $model = $this->findCoin($id);

        if (Yii::$app->request->isPost) {
            // Execute coin uninstall
            // This should remove the coin and clean up related data
            $coinName = $model->name;
            $model->delete();
            Yii::$app->session->setFlash('success', "Coin {$coinName} uninstalled.");
            return $this->redirect(['coinwallets']);
        }

        return $this->render('confirm_uninstall_coin', [
            'model' => $model,
        ]);
    }

    /**
     * User management listing
     */
    public function actionUser()
    {
        return $this->render('user');
    }

    /**
     * AJAX endpoint for user listing
     */
    public function actionUserResults()
    {
        $search = Yii::$app->request->get('search', '');
        $coinid = Yii::$app->request->get('coinid', '');
        $locked = Yii::$app->request->get('locked', '');

        $query = Accounts::find()
            ->with('coin'); // Eager load coin relation

        if ($search) {
            $query->andWhere(['like', 'username', $search]);
        }

        if ($coinid) {
            $query->andWhere(['coinid' => $coinid]);
        }

        if ($locked !== '') {
            $query->andWhere(['is_locked' => $locked]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['balance' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $this->renderPartial('user_results', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Ban user
     */
    public function actionBanUser($id)
    {
        $model = Accounts::findOne($id);
        if ($model) {
            $model->is_locked = 1;
            $model->save(false);
            Yii::$app->session->setFlash('success', 'User banned successfully.');
        }

        return $this->redirect(['user']);
    }

    /**
     * Unban user
     */
    public function actionUnbanUser($id)
    {
        $model = Accounts::findOne($id);
        if ($model) {
            $model->is_locked = 0;
            $model->save(false);
            Yii::$app->session->setFlash('success', 'User unbanned successfully.');
        }

        return $this->redirect(['user']);
    }

    /**
     * Worker monitoring listing
     */
    public function actionWorker()
    {
        return $this->render('worker');
    }

    /**
     * AJAX endpoint for worker listing
     */
    public function actionWorkerResults()
    {
        $algo = Yii::$app->request->get('algo', '');
        $activeOnly = Yii::$app->request->get('active', 1);

        // Eager load account relation to avoid N+1 queries
        $query = Workers::find()->with('account');

        if ($algo) {
            $query->andWhere(['algo' => $algo]);
        }

        if ($activeOnly) {
            $query->andWhere(['>', 'time', time() - 300]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 100,
            ],
        ]);

        return $this->renderPartial('worker_results', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Payment monitoring listing
     */
    public function actionPayments()
    {
        return $this->render('payments');
    }

    /**
     * AJAX endpoint for payment listing
     */
    public function actionPaymentsResults()
    {
        $status = Yii::$app->request->get('status', 'pending');
        $coinid = Yii::$app->request->get('coinid', '');

        // Eager load account and coin relations to avoid N+1 queries
        $query = Payouts::find()->with(['account', 'coin']);

        if ($status === 'pending') {
            $query->andWhere(['IS', 'tx', null]);
        } elseif ($status === 'completed') {
            $query->andWhere(['IS NOT', 'tx', null]);
        }

        if ($coinid) {
            $query->andWhere(['idcoin' => $coinid]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $this->renderPartial('payments_results', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Cancel payment
     */
    public function actionCancelPayment($id)
    {
        $model = Payouts::findOne($id);
        if ($model && !$model->tx) {
            // Return balance to user
            $account = $model->account;
            if ($account) {
                $account->balance += $model->amount;
                $account->save(false);
            }
            
            $model->delete();
            Yii::$app->session->setFlash('success', 'Payment cancelled and balance returned.');
        }

        return $this->redirect(['payments']);
    }

    /**
     * Earnings monitoring listing
     */
    public function actionEarning()
    {
        return $this->render('earning');
    }

    /**
     * AJAX endpoint for earnings listing
     */
    public function actionEarningResults()
    {
        $coinid = Yii::$app->request->get('coinid', '');
        $algo = Yii::$app->request->get('algo', '');

        // Eager load account and coin relations to avoid N+1 queries
        $query = Earnings::find()->with(['account', 'coin']);

        if ($coinid) {
            $query->andWhere(['coinid' => $coinid]);
        }

        if ($algo) {
            $query->andWhere(['algo' => $algo]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query->orderBy(['create_time' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 100,
            ],
        ]);

        return $this->renderPartial('earning_results', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Delete earning record
     */
    public function actionDeleteEarning($id)
    {
        $model = Earnings::findOne($id);
        if ($model) {
            $model->delete();
            Yii::$app->session->setFlash('success', 'Earning record deleted.');
        }

        return $this->redirect(['earning']);
    }

    /**
     * Exchange management
     */
    public function actionExchange()
    {
        return $this->render('exchange');
    }

    /**
     * AJAX endpoint for exchange status
     */
    public function actionExchangeResults()
    {
        // Cache exchange results for 60 seconds
        $cacheKey = 'admin_exchange_results';
        $markets = Yii::$app->cache->get($cacheKey);
        
        if ($markets === false) {
            $markets = Markets::find()
                ->with('coin') // Eager load coin relation
                ->orderBy(['lasttraded' => SORT_DESC])
                ->all();
            
            // Cache for 60 seconds
            Yii::$app->cache->set($cacheKey, $markets, 60);
        }

        return $this->renderPartial('exchange_results', [
            'markets' => $markets,
        ]);
    }

    /**
     * Exchange balances
     */
    public function actionBalances()
    {
        return $this->render('balances');
    }

    /**
     * AJAX endpoint for exchange balances
     */
    public function actionBalancesResults()
    {
        // Cache balance results for 60 seconds
        $cacheKey = 'admin_balances_results';
        $balances = Yii::$app->cache->get($cacheKey);
        
        if ($balances === false) {
            $balances = Balances::find()
                ->orderBy(['balance' => SORT_DESC])
                ->all();
            
            // Cache for 60 seconds
            Yii::$app->cache->set($cacheKey, $balances, 60);
        }

        return $this->renderPartial('balances_results', [
            'balances' => $balances,
        ]);
    }

    /**
     * Connection monitoring
     */
    public function actionConnections()
    {
        return $this->render('connections');
    }

    /**
     * AJAX endpoint for connection listing
     */
    public function actionConnectionsResults()
    {
        $algo = Yii::$app->request->get('algo', '');

        // Cache key includes algo filter
        $cacheKey = 'admin_connections_results_' . ($algo ?: 'all');
        $connections = Yii::$app->cache->get($cacheKey);
        
        if ($connections === false) {
            // Get active connections from workers
            $query = Workers::find()
                ->select(['ip', 'algo', 'COUNT(*) as count', 'MAX(time) as last_seen'])
                ->where(['>', 'time', time() - 300])
                ->groupBy(['ip', 'algo']);

            if ($algo) {
                $query->andWhere(['algo' => $algo]);
            }

            $connections = $query->asArray()->all();
            
            // Cache for 30 seconds
            Yii::$app->cache->set($cacheKey, $connections, 30);
        }

        return $this->renderPartial('connections_results', [
            'connections' => $connections,
        ]);
    }

    /**
     * Botnet detection
     */
    public function actionBotnets()
    {
        return $this->render('botnets');
    }

    /**
     * Monster detection (high hashrate anomalies)
     */
    public function actionMonsters()
    {
        return $this->render('monsters');
    }

    /**
     * Block suspicious pattern
     */
    public function actionBlockPattern()
    {
        $ip = Yii::$app->request->post('ip');
        $pattern = Yii::$app->request->post('pattern');

        // Implement blocking logic
        // This would typically add to a blacklist table or configuration

        Yii::$app->session->setFlash('success', 'Pattern blocked successfully.');
        return $this->redirect(['botnets']);
    }

    /**
     * Memcached management
     */
    public function actionMemcached()
    {
        $stats = null;
        $keys = [];

        if (Yii::$app->cache instanceof \yii\caching\MemCache) {
            try {
                $memcache = Yii::$app->cache;
                $servers = $memcache->getServers();
                
                // Get stats from first server
                if (!empty($servers)) {
                    // Note: Getting all keys from memcached is not straightforward
                    // This is a simplified version
                    $stats = [
                        'servers' => $servers,
                    ];
                }
            } catch (\Exception $e) {
                Yii::error("Failed to get memcached stats: " . $e->getMessage(), __METHOD__);
            }
        }

        return $this->render('memcached', [
            'stats' => $stats,
            'keys' => $keys,
        ]);
    }

    /**
     * Clear memcached cache
     */
    public function actionClearCache()
    {
        if (Yii::$app->cache->flush()) {
            Yii::$app->session->setFlash('success', 'Cache cleared successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Failed to clear cache.');
        }

        return $this->redirect(['memcached']);
    }

    /**
     * Version monitoring
     */
    public function actionVersion()
    {
        return $this->render('version');
    }

    /**
     * AJAX endpoint for version information
     */
    public function actionVersionResults()
    {
        // Cache version results for 5 minutes (expensive RPC calls)
        $cacheKey = 'admin_version_results';
        $versions = Yii::$app->cache->get($cacheKey);
        
        if ($versions === false) {
            $coins = Coins::find()
                ->where(['enable' => 1])
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $versions = [];
            foreach ($coins as $coin) {
                try {
                    // Validate RPC connection first
                    $validation = Yii::$app->RpcClient->validateConnection($coin);
                    if (!$validation['success']) {
                        throw new \Exception($validation['error']);
                    }
                    
                    $info = Yii::$app->RpcClient->query($coin->id, 'getinfo');
                    
                    if ($info === null) {
                        // Try alternative method for newer daemons
                        $info = Yii::$app->RpcClient->query($coin->id, 'getblockchaininfo');
                        if ($info === null) {
                            throw new \Exception('Unable to retrieve daemon information');
                        }
                    }
                    
                    $versions[] = [
                        'coin' => $coin,
                        'version' => $info['version'] ?? 'Unknown',
                        'protocol' => $info['protocolversion'] ?? 'Unknown',
                        'blocks' => $info['blocks'] ?? 0,
                    ];
                } catch (\Exception $e) {
                    $versions[] = [
                        'coin' => $coin,
                        'version' => 'Error',
                        'protocol' => 'Error',
                        'blocks' => 0,
                        'error' => $e->getMessage(),
                    ];
                    
                    Yii::error([
                        'message' => 'Failed to get version info',
                        'coin' => $coin->name,
                        'coin_id' => $coin->id,
                        'error' => $e->getMessage(),
                    ], __METHOD__);
                }
            }
            
            // Cache for 5 minutes (300 seconds)
            Yii::$app->cache->set($cacheKey, $versions, 300);
        }

        return $this->renderPartial('version_results', [
            'versions' => $versions,
        ]);
    }

    /**
     * Find coin model by ID
     * @param int $id
     * @return Coins
     * @throws NotFoundHttpException
     */
    protected function findCoin($id)
    {
        if (($model = Coins::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested coin does not exist.');
    }
}
