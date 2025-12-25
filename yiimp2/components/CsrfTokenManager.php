<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * CSRF Token Lifecycle Manager
 * 
 * Manages CSRF token lifecycle including:
 * - Token generation with timestamp (Requirement 5.1)
 * - Token expiration validation (Requirement 5.2)
 * - Token invalidation on session expiration (Requirement 5.3)
 * - Token destruction on logout (Requirement 5.4)
 * - Fresh token generation for new sessions (Requirement 5.5)
 * 
 * This component ensures CSRF tokens are properly managed throughout
 * the session lifecycle and are invalidated when appropriate.
 */
class CsrfTokenManager extends Component
{
    /**
     * @var int Token expiration time in seconds (default: 1 hour)
     */
    public $tokenExpiration = 3600;
    
    /**
     * @var string Session key for storing CSRF token timestamp
     */
    public $timestampKey = '_csrf_timestamp';
    
    /**
     * @var string Session key for storing session creation time
     */
    public $sessionCreationKey = '_session_created_at';
    
    /**
     * Initialize the component
     */
    public function init()
    {
        parent::init();
        
        // Note: Yii2's Session component doesn't have built-in events
        // Token lifecycle management is handled through CsrfAwareRequest component
    }
    
    /**
     * Generate a fresh CSRF token
     * 
     * Implements Requirements 5.1, 5.5:
     * - Generate new token
     * - Store with current timestamp (5.1)
     * - Ensure token is fresh for new session (5.5)
     */
    public function generateFreshToken()
    {
        $session = Yii::$app->session;
        $request = Yii::$app->request;
        
        // Clear any existing token data (but don't call invalidateToken to avoid recursion)
        $session->remove($this->timestampKey);
        $session->remove($this->sessionCreationKey);
        $session->remove('_last_session_id');
        $session->remove($request->csrfParam);
        
        // Generate a new CSRF token using Yii2's built-in mechanism
        // We need to call the parent Request class method, not our custom one
        // To do this, we'll use reflection to call the parent method directly
        $reflection = new \ReflectionClass($request);
        $parentClass = $reflection->getParentClass();
        $method = $parentClass->getMethod('getCsrfToken');
        $token = $method->invoke($request, true);
        
        // Store token generation timestamp (Requirement 5.1)
        $timestamp = time();
        $session->set($this->timestampKey, $timestamp);
        
        // Store session creation time
        $session->set($this->sessionCreationKey, $timestamp);
        
        // Store current session ID to detect future session changes
        $session->set('_last_session_id', $session->getId());
        
        Yii::info([
            'event' => 'csrf_token_generated',
            'timestamp' => $timestamp,
            'token_length' => strlen($token),
            'session_id' => substr($session->getId(), 0, 20) . '...',
        ], 'csrf.lifecycle');
        
        return $token;
    }
    
    /**
     * Validate token expiration
     * 
     * Implements Requirement 5.2:
     * - Check if token has expired
     * - Regenerate if expired
     * 
     * @return bool True if token is valid, false if expired
     */
    public function validateTokenExpiration()
    {
        $session = Yii::$app->session;
        $tokenTimestamp = $session->get($this->timestampKey);
        
        if ($tokenTimestamp === null) {
            // No timestamp - generate fresh token
            $this->generateFreshToken();
            return false;
        }
        
        $tokenAge = time() - $tokenTimestamp;
        
        if ($tokenAge > $this->tokenExpiration) {
            // Token expired - regenerate (Requirement 5.2)
            Yii::warning([
                'event' => 'csrf_token_expired',
                'token_age' => $tokenAge,
                'token_expiration' => $this->tokenExpiration,
                'session_id' => substr($session->getId(), 0, 20) . '...',
            ], 'csrf.lifecycle');
            
            $this->generateFreshToken();
            return false;
        }
        
        return true;
    }
    
    /**
     * Invalidate the current CSRF token
     * 
     * Implements Requirements 5.3, 5.4:
     * - Invalidate token on session expiration (5.3)
     * - Destroy token on logout (5.4)
     */
    public function invalidateToken()
    {
        $session = Yii::$app->session;
        $request = Yii::$app->request;
        
        // Remove token timestamp
        $session->remove($this->timestampKey);
        
        // Remove session creation tracking
        $session->remove($this->sessionCreationKey);
        $session->remove('_last_session_id');
        
        // Remove CSRF token from session
        $csrfParam = $request->csrfParam;
        $session->remove($csrfParam);
        
        // If using cookie storage, remove the cookie
        if ($request->enableCsrfCookie) {
            $cookies = Yii::$app->response->cookies;
            $cookies->remove($csrfParam);
        }
        
        Yii::info([
            'event' => 'csrf_token_invalidated',
            'session_id' => substr($session->getId(), 0, 20) . '...',
        ], 'csrf.lifecycle');
    }
    
    /**
     * Handle user logout
     * 
     * Implements Requirement 5.4:
     * - Destroy session and associated CSRF tokens on logout
     * 
     * This should be called from the logout action.
     */
    public function handleLogout()
    {
        $session = Yii::$app->session;
        $sessionId = $session->getId();
        
        Yii::info([
            'event' => 'logout_token_destruction',
            'session_id' => substr($sessionId, 0, 20) . '...',
        ], 'csrf.lifecycle');
        
        // Invalidate token before destroying session
        $this->invalidateToken();
        
        // Destroy session (Requirement 5.4)
        if ($session->getIsActive()) {
            $session->destroy();
        }
        
        Yii::info([
            'event' => 'logout_session_destroyed',
            'old_session_id' => substr($sessionId, 0, 20) . '...',
        ], 'csrf.lifecycle');
    }
    
    /**
     * Get token generation timestamp
     * 
     * @return int|null Timestamp when token was generated, or null if not set
     */
    public function getTokenTimestamp()
    {
        return Yii::$app->session->get($this->timestampKey);
    }
    
    /**
     * Get token age in seconds
     * 
     * @return int|null Age of token in seconds, or null if no timestamp
     */
    public function getTokenAge()
    {
        $timestamp = $this->getTokenTimestamp();
        
        if ($timestamp === null) {
            return null;
        }
        
        return time() - $timestamp;
    }
    
    /**
     * Check if token is expired
     * 
     * @return bool True if token is expired, false otherwise
     */
    public function isTokenExpired()
    {
        $age = $this->getTokenAge();
        
        if ($age === null) {
            return true; // No timestamp = expired
        }
        
        return $age > $this->tokenExpiration;
    }
}
