<?php

namespace app\components;

use Yii;
use yii\web\CacheSession;

/**
 * Logging Cache Session Component
 * 
 * Extends CacheSession to add comprehensive logging for session lifecycle events.
 * 
 * This component logs:
 * - Session initialization (Requirement 6.4)
 * - Session initialization failures (Requirement 6.4)
 * - Session configuration parameters (Requirement 6.5)
 * 
 * Requirements:
 * - 6.4: Log session initialization failures with error context
 * - 6.5: Log CSRF configuration parameters
 */
class LoggingCacheSession extends CacheSession
{
    /**
     * Opens the session
     * 
     * Overrides parent to add logging for session initialization
     * and handle initialization failures (Requirement 6.4)
     */
    public function open()
    {
        try {
            // Log session initialization attempt
            Yii::info([
                'event' => 'session_initialization_attempt',
                'session_name' => $this->getName(),
                'timeout' => $this->getTimeout(),
                'use_cookies' => $this->getUseCookies(),
                'cookie_params' => $this->getCookieParams(),
                'cache_component' => is_object($this->cache) ? get_class($this->cache) : $this->cache,
            ], 'session.init');
            
            // Call parent open method
            parent::open();
            
            // Log successful session initialization (Requirement 6.4)
            Yii::info([
                'event' => 'session_initialization_success',
                'session_id' => substr($this->getId(), 0, 20) . '...',
                'session_name' => $this->getName(),
                'is_active' => $this->getIsActive(),
            ], 'session.init');
            
        } catch (\Exception $e) {
            // Log session initialization failure (Requirement 6.4)
            Yii::error([
                'event' => 'session_initialization_failure',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'session_name' => $this->getName(),
                'cache_component' => is_object($this->cache) ? get_class($this->cache) : $this->cache,
                'cookie_params' => $this->getCookieParams(),
            ], 'session.init');
            
            // Re-throw the exception
            throw $e;
        }
    }
    
    /**
     * Regenerates session ID
     * 
     * Overrides parent to add logging for session ID regeneration
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldSessionId = $this->getId();
        
        try {
            // Call parent regenerate method
            parent::regenerateID($deleteOldSession);
            
            $newSessionId = $this->getId();
            
            // Log session ID regeneration
            Yii::info([
                'event' => 'session_id_regenerated',
                'old_session_id' => substr($oldSessionId, 0, 20) . '...',
                'new_session_id' => substr($newSessionId, 0, 20) . '...',
                'delete_old_session' => $deleteOldSession,
            ], 'session.lifecycle');
            
        } catch (\Exception $e) {
            // Log regeneration failure
            Yii::error([
                'event' => 'session_regeneration_failure',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'old_session_id' => substr($oldSessionId, 0, 20) . '...',
            ], 'session.lifecycle');
            
            throw $e;
        }
    }
    
    /**
     * Destroys the session
     * 
     * Overrides parent to add logging for session destruction
     */
    public function destroy()
    {
        $sessionId = $this->getId();
        
        try {
            // Call parent destroy method
            parent::destroy();
            
            // Log session destruction
            Yii::info([
                'event' => 'session_destroyed',
                'session_id' => substr($sessionId, 0, 20) . '...',
            ], 'session.lifecycle');
            
        } catch (\Exception $e) {
            // Log destruction failure
            Yii::error([
                'event' => 'session_destruction_failure',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'session_id' => substr($sessionId, 0, 20) . '...',
            ], 'session.lifecycle');
            
            throw $e;
        }
    }
}
