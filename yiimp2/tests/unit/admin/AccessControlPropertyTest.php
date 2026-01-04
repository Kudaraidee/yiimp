<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use Yii;
use yii\web\ForbiddenHttpException;
use app\controllers\AdminController;
use app\models\User;

/**
 * Property-based tests for Access Control Enforcement
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 45: Access control enforcement
 */
class AccessControlPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        
        // Create controller instance
        $this->controller = new AdminController('admin', Yii::$app);
    }
    
    protected function _after()
    {
        // Logout after each test
        Yii::$app->user->logout();
        parent::_after();
    }
    
    /**
     * Property 45: Access Control Enforcement
     * 
     * For any protected controller action (admin functions), the system should
     * enforce access control using Yii2 filters and deny access to unauthorized users.
     * 
     * Validates: Requirements 13.6
     * 
     * @test
     */
    public function testAccessControlEnforcement()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 45: Access control enforcement
        
        $iterations = 100;
        $failures = [];
        
        // Get all protected admin actions (excluding login)
        $protectedActions = $this->getProtectedActions();
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select an action to test
            $action = $protectedActions[array_rand($protectedActions)];
            
            // Test 1: Guest user should be denied access
            Yii::$app->user->logout();
            $guestResult = $this->testActionAccess($action, 'guest');
            
            if ($guestResult['allowed']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $action,
                    'user_type' => 'guest',
                    'reason' => 'Guest user was allowed access to protected action',
                    'expected' => 'denied',
                    'actual' => 'allowed'
                ];
            }
            
            // Test 2: Non-admin authenticated user should be denied access
            $this->loginAsNonAdmin();
            $nonAdminResult = $this->testActionAccess($action, 'non_admin');
            
            if ($nonAdminResult['allowed']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $action,
                    'user_type' => 'non_admin',
                    'reason' => 'Non-admin user was allowed access to protected action',
                    'expected' => 'denied',
                    'actual' => 'allowed'
                ];
            }
            
            Yii::$app->user->logout();
            
            // Test 3: Admin user should be allowed access
            $this->loginAsAdmin();
            $adminResult = $this->testActionAccess($action, 'admin');
            
            if (!$adminResult['allowed']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $action,
                    'user_type' => 'admin',
                    'reason' => 'Admin user was denied access to protected action',
                    'expected' => 'allowed',
                    'actual' => 'denied',
                    'error' => $adminResult['error'] ?? null
                ];
            }
            
            Yii::$app->user->logout();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Get list of protected admin actions
     * 
     * @return array
     */
    protected function getProtectedActions()
    {
        return [
            'dashboard',
            'dashboard_results',
            'coinwallets',
            'coin_create',
            'coin_update',
            'coin',
            'coin_console',
            'coin_peers',
            'add_peer',
            'remove_peer',
            'coin_triggers',
            'enable_trigger',
            'disable_trigger',
            'reset_trigger',
            'start_coin',
            'stop_coin',
            'restart_coin',
            'reset_blockchain',
            'uninstall_coin',
            'user',
            'user_results',
            'ban_user',
            'unban_user',
            'worker',
            'worker_results',
            'payments',
            'payments_results',
            'cancel_payment',
            'earning',
            'earning_results',
            'delete_earning',
            'exchange',
            'exchange_results',
            'balances',
            'balances_results',
            'connections',
            'connections_results',
            'botnets',
            'monsters',
            'block_pattern',
            'memcached',
            'clear_cache',
            'version',
            'version_results',
        ];
    }
    
    /**
     * Test access to an action
     * 
     * @param string $actionId
     * @param string $userType
     * @return array ['allowed' => bool, 'error' => string|null]
     */
    protected function testActionAccess($actionId, $userType)
    {
        try {
            // Create a mock request
            Yii::$app->request->setUrl('/admin/' . str_replace('_', '-', $actionId));
            
            // Get the action
            $action = $this->controller->createAction($actionId);
            
            if ($action === null) {
                return ['allowed' => false, 'error' => 'Action not found'];
            }
            
            // Run beforeAction to trigger access control
            $allowed = $this->controller->beforeAction($action);
            
            return ['allowed' => $allowed, 'error' => null];
            
        } catch (ForbiddenHttpException $e) {
            // Access denied - this is expected for unauthorized users
            return ['allowed' => false, 'error' => $e->getMessage()];
        } catch (\yii\web\UnauthorizedHttpException $e) {
            // Unauthorized - this is expected for guests
            return ['allowed' => false, 'error' => $e->getMessage()];
        } catch (\Exception $e) {
            // Other exceptions might indicate access was attempted
            // Check if it's a redirect (which means access control worked)
            if (strpos($e->getMessage(), 'redirect') !== false) {
                return ['allowed' => false, 'error' => 'Redirected'];
            }
            
            // For other exceptions, we need to check the response
            $response = Yii::$app->response;
            if ($response->statusCode === 302 || $response->statusCode === 403) {
                return ['allowed' => false, 'error' => 'HTTP ' . $response->statusCode];
            }
            
            // Unknown exception - treat as error
            return ['allowed' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Login as admin user
     */
    protected function loginAsAdmin()
    {
        $user = User::findByUsername(YAAMP_ADMIN_USER);
        if ($user) {
            Yii::$app->user->login($user);
        }
    }
    
    /**
     * Login as non-admin user
     */
    protected function loginAsNonAdmin()
    {
        // Create a mock non-admin user
        $user = new User([
            'id' => '999',
            'username' => 'testuser',
            'password' => 'testpass',
            'is_admin' => false,
            'authKey' => 'testkey',
            'accessToken' => 'testtoken',
        ]);
        
        Yii::$app->user->login($user);
    }
}
