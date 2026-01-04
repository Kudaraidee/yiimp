<?php

namespace app\components;

use Yii;
use yii\web\ErrorHandler;
use yii\web\BadRequestHttpException;

/**
 * Enhanced error handler with comprehensive logging and CSRF-specific handling
 * 
 * Implements Requirements 1.5, 5.5:
 * - Provides user-friendly error messages for CSRF failures
 * - Adds debug mode detailed error information
 * - Implements session expiration redirect to login
 * - Adds error remediation suggestions
 */
class EnhancedErrorHandler extends ErrorHandler
{
    /**
     * @inheritdoc
     */
    public function logException($exception)
    {
        // Call parent to do standard logging
        parent::logException($exception);
        
        // Add enhanced logging with more context
        $category = get_class($exception);
        
        $logData = [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        
        // Add request information if available (not in console/test mode)
        try {
            if (Yii::$app->has('request') && !Yii::$app->request instanceof \yii\console\Request) {
                // Check if REQUEST_URI is available before trying to get URL
                if (isset($_SERVER['REQUEST_URI'])) {
                    $logData['url'] = Yii::$app->request->getUrl();
                    $logData['method'] = Yii::$app->request->getMethod();
                    $logData['ip'] = Yii::$app->request->getUserIP();
                    $logData['user_agent'] = Yii::$app->request->getUserAgent();
                } else {
                    $logData['url'] = 'N/A (REQUEST_URI not set)';
                }
            }
        } catch (\Exception $e) {
            // Ignore request errors in test/console mode
            $logData['request_error'] = $e->getMessage();
        }
        
        // Add stack trace in debug mode
        if (YII_DEBUG) {
            $logData['trace'] = $exception->getTraceAsString();
        }
        
        // Add previous exception if exists
        if ($exception->getPrevious()) {
            $prev = $exception->getPrevious();
            $logData['previous_exception'] = [
                'class' => get_class($prev),
                'message' => $prev->getMessage(),
                'code' => $prev->getCode(),
                'file' => $prev->getFile(),
                'line' => $prev->getLine(),
            ];
        }
        
        // Add database error details if it's a database exception
        if ($exception instanceof \yii\db\Exception) {
            $logData['error_info'] = $exception->errorInfo ?? null;
            
            // Try to extract SQL query if available
            if (method_exists($exception, 'getSql')) {
                $logData['sql'] = $exception->getSql();
            }
        }
        
        // Add CSRF-specific error details
        if ($this->isCsrfException($exception)) {
            $logData['csrf_error'] = true;
            $logData['session_active'] = Yii::$app->session->getIsActive();
            $logData['session_id'] = Yii::$app->session->getId();
            $logData['has_session_cookie'] = isset($_COOKIE[Yii::$app->session->getName()]);
            $logData['cookies_count'] = count($_COOKIE);
        }
        
        // Log with enhanced details
        Yii::error($logData, $category);
    }
    
    /**
     * Renders the exception with enhanced CSRF handling
     * 
     * @param \Exception|\Error $exception the exception to be rendered
     */
    protected function renderException($exception)
    {
        // Check if this is a CSRF validation error
        if ($this->isCsrfException($exception)) {
            $this->handleCsrfException($exception);
            return;
        }
        
        // Check if this is a session expiration scenario
        // BUT ONLY redirect to login if we're on an admin page (Requirements 1.1, 1.2, 4.1, 4.2, 4.5)
        // Public pages should not require sessions or redirect to login
        if ($this->isSessionExpired() && $this->isAdminPage()) {
            $this->handleSessionExpiration();
            return;
        }
        
        // Default exception rendering
        parent::renderException($exception);
    }
    
    /**
     * Check if exception is CSRF-related
     * 
     * @param \Exception|\Error $exception
     * @return bool
     */
    protected function isCsrfException($exception)
    {
        if (!($exception instanceof BadRequestHttpException)) {
            return false;
        }
        
        $message = $exception->getMessage();
        return stripos($message, 'csrf') !== false || 
               stripos($message, 'verify your data submission') !== false ||
               stripos($message, 'token') !== false;
    }
    
    /**
     * Check if session has expired
     * 
     * @return bool
     */
    protected function isSessionExpired()
    {
        try {
            if (!Yii::$app->has('session')) {
                return false;
            }
            
            $session = Yii::$app->session;
            
            // Check if session is not active
            if (!$session->getIsActive()) {
                return true;
            }
            
            // Check if session ID is empty
            if (empty($session->getId())) {
                return true;
            }
            
            // Check if session cookie is missing
            $sessionCookieName = $session->getName();
            if (!isset($_COOKIE[$sessionCookieName])) {
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if current page is an admin page that requires authentication
     * 
     * Requirements 1.1, 1.2, 4.1, 4.2, 4.5: Public pages should not redirect to login
     * 
     * @return bool
     */
    protected function isAdminPage()
    {
        try {
            if (!Yii::$app->has('request')) {
                return false;
            }
            
            $url = Yii::$app->request->getUrl();
            
            // Check if URL starts with /admin (but not /admin/login which is public)
            if (strpos($url, '/admin') === 0) {
                // /admin/login is public, don't redirect
                if (strpos($url, '/admin/login') === 0) {
                    return false;
                }
                return true;
            }
            
            // All other pages are public
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Handle CSRF validation exceptions with user-friendly messages
     * Implements Requirement 1.5: Clear error messages for CSRF failures
     * 
     * @param \Exception $exception
     */
    protected function handleCsrfException($exception)
    {
        // Log the CSRF error with details
        Yii::error([
            'message' => 'CSRF validation exception caught by error handler',
            'exception_message' => $exception->getMessage(),
            'session_active' => Yii::$app->session->getIsActive(),
            'session_id' => Yii::$app->session->getId(),
            'has_session_cookie' => isset($_COOKIE[Yii::$app->session->getName()]),
            'url' => Yii::$app->request->getUrl(),
        ], __METHOD__);
        
        // Set user-friendly flash message
        Yii::$app->session->setFlash('error', 
            'Unable to verify your data submission. Please refresh the page and try again.'
        );
        
        // In debug mode, provide detailed error information
        if (YII_DEBUG) {
            $debugInfo = $this->getCsrfDebugInfo();
            Yii::$app->session->setFlash('csrf_debug', $debugInfo);
        }
        
        // Redirect back to the referring page or form
        $referrer = Yii::$app->request->getReferrer();
        if ($referrer) {
            Yii::$app->response->redirect($referrer)->send();
        } else {
            // Fallback to homepage
            Yii::$app->response->redirect(['site/index'])->send();
        }
    }
    
    /**
     * Handle session expiration by redirecting to login
     * Implements Requirement 5.5: Session expiration redirect
     * 
     */
    protected function handleSessionExpiration()
    {
        Yii::info([
            'message' => 'Session expiration detected, redirecting to login',
            'url' => Yii::$app->request->getUrl(),
        ], __METHOD__);
        
        // Set user-friendly flash message
        Yii::$app->session->setFlash('warning', 
            'Your session has expired. Please log in again.'
        );
        
        // Redirect to login page
        Yii::$app->response->redirect(['admin/login'])->send();
    }
    
    /**
     * Get CSRF debug information for troubleshooting
     * 
     * @return array
     */
    protected function getCsrfDebugInfo()
    {
        $info = [
            'session_status' => [
                'active' => Yii::$app->session->getIsActive(),
                'id' => Yii::$app->session->getId(),
                'name' => Yii::$app->session->getName(),
            ],
            'cookies' => [
                'count' => count($_COOKIE),
                'has_session_cookie' => isset($_COOKIE[Yii::$app->session->getName()]),
                'cookie_names' => array_keys($_COOKIE),
            ],
            'csrf_config' => [
                'enabled' => Yii::$app->request->enableCsrfValidation,
                'param' => Yii::$app->request->csrfParam,
            ],
            'remediation_steps' => $this->getCsrfRemediationSteps(),
        ];
        
        return $info;
    }
    
    /**
     * Get remediation steps for CSRF errors
     * Implements Requirement 1.5: Error remediation suggestions
     * 
     * @return array
     */
    protected function getCsrfRemediationSteps()
    {
        $steps = [];
        
        // Check for common issues and provide specific remediation
        if (!Yii::$app->session->getIsActive()) {
            $steps[] = 'Session is not active. Check session configuration in config/web.php';
            $steps[] = 'Verify session storage directory has write permissions';
        }
        
        if (empty(Yii::$app->session->getId())) {
            $steps[] = 'Session ID is empty. Session may not be starting correctly';
            $steps[] = 'Check PHP session configuration (session.save_path)';
        }
        
        $sessionCookieName = Yii::$app->session->getName();
        if (!isset($_COOKIE[$sessionCookieName])) {
            $steps[] = "Session cookie '{$sessionCookieName}' is not set";
            $steps[] = 'Check if cookies are enabled in your browser';
            $steps[] = 'Verify cookie security settings match your protocol (HTTP/HTTPS)';
            $steps[] = 'Check if cookie domain/path settings are correct';
        }
        
        if (empty($steps)) {
            $steps[] = 'Refresh the page to get a new CSRF token';
            $steps[] = 'Clear your browser cookies and try again';
            $steps[] = 'Check browser console for JavaScript errors';
        }
        
        return $steps;
    }
}
