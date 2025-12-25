<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\web\HttpException;

/**
 * Redirect Diagnostic Component
 * 
 * Tracks redirect chains and detects redirect loops to prevent infinite redirects.
 * Provides detailed logging for troubleshooting redirect issues.
 * 
 * Requirements: 3.1
 */
class RedirectDiagnostic extends Component
{
    /**
     * Maximum number of redirects to the same URL before considering it a loop
     */
    const MAX_REDIRECTS_TO_SAME_URL = 3;
    
    /**
     * Maximum total redirects to track in the chain
     */
    const MAX_CHAIN_LENGTH = 10;
    
    /**
     * Session key for storing redirect chain
     */
    const SESSION_KEY = 'redirect_chain';
    
    /**
     * Log a redirect from one URL to another
     * 
     * Requirement 3.1: Log redirect chain with URLs and reasons
     * 
     * @param string $from The URL being redirected from
     * @param string $to The URL being redirected to
     * @param string $reason The reason for the redirect
     */
    public static function logRedirect($from, $to, $reason)
    {
        $logger = Yii::getLogger();
        
        $logger->log(
            sprintf(
                'Redirect: %s -> %s (Reason: %s)',
                $from,
                $to,
                $reason
            ),
            \yii\log\Logger::LEVEL_INFO,
            'app\components\RedirectDiagnostic'
        );
        
        // Track in session for loop detection
        self::trackRedirect($to, $reason);
    }
    
    /**
     * Detect if accessing a URL would create a redirect loop
     * 
     * Requirement 3.1: Detect redirect loops and log the chain
     * 
     * @param string $url The URL being accessed
     * @throws HttpException if a redirect loop is detected
     */
    public static function detectLoop($url)
    {
        $session = Yii::$app->session;
        $logger = Yii::getLogger();
        
        // Get current redirect chain from session
        $redirectChain = $session->get(self::SESSION_KEY, []);
        
        // Count how many times this URL appears in the chain
        $urlCount = 0;
        foreach ($redirectChain as $entry) {
            if ($entry['url'] === $url) {
                $urlCount++;
            }
        }
        
        // If URL appears too many times, we have a loop
        if ($urlCount >= self::MAX_REDIRECTS_TO_SAME_URL) {
            // Log the full redirect chain
            $logger->log(
                sprintf(
                    'Redirect loop detected! URL "%s" appeared %d times in chain: %s',
                    $url,
                    $urlCount,
                    json_encode($redirectChain, JSON_PRETTY_PRINT)
                ),
                \yii\log\Logger::LEVEL_ERROR,
                'app\components\RedirectDiagnostic'
            );
            
            // Clear the redirect chain to prevent further issues
            $session->remove(self::SESSION_KEY);
            
            // Throw exception to break the loop
            throw new HttpException(
                500,
                sprintf(
                    'Redirect loop detected. The URL "%s" was redirected to %d times. ' .
                    'This usually indicates a configuration issue with authentication or access control.',
                    $url,
                    $urlCount
                )
            );
        }
    }
    
    /**
     * Log an access control denial
     * 
     * Requirement 3.1: Log access denial details for diagnostics
     * 
     * @param string $controller The controller being accessed
     * @param string $action The action being accessed
     * @param string $reason The reason for denial
     */
    public static function logAccessDenial($controller, $action, $reason)
    {
        $logger = Yii::getLogger();
        
        $logger->log(
            sprintf(
                'Access denied: %s/%s (Reason: %s, User: %s, Guest: %s)',
                $controller,
                $action,
                $reason,
                Yii::$app->user->id ?? 'none',
                Yii::$app->user->isGuest ? 'yes' : 'no'
            ),
            \yii\log\Logger::LEVEL_WARNING,
            'app\components\RedirectDiagnostic'
        );
    }
    
    /**
     * Track a redirect in the session
     * 
     * @param string $url The URL being redirected to
     * @param string $reason The reason for the redirect
     */
    protected static function trackRedirect($url, $reason)
    {
        $session = Yii::$app->session;
        
        // Get current redirect chain
        $redirectChain = $session->get(self::SESSION_KEY, []);
        
        // Add new redirect to chain
        $redirectChain[] = [
            'url' => $url,
            'reason' => $reason,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        
        // Keep only the last N redirects to prevent memory issues
        if (count($redirectChain) > self::MAX_CHAIN_LENGTH) {
            $redirectChain = array_slice($redirectChain, -self::MAX_CHAIN_LENGTH);
        }
        
        // Store back in session
        $session->set(self::SESSION_KEY, $redirectChain);
    }
    
    /**
     * Clear the redirect chain from session
     * 
     * Useful for resetting after successful page load or login
     */
    public static function clearChain()
    {
        $session = Yii::$app->session;
        $session->remove(self::SESSION_KEY);
    }
    
    /**
     * Get the current redirect chain
     * 
     * Useful for debugging and diagnostics
     * 
     * @return array The current redirect chain
     */
    public static function getChain()
    {
        $session = Yii::$app->session;
        return $session->get(self::SESSION_KEY, []);
    }
    
    /**
     * Get redirect chain as formatted string
     * 
     * @return string Formatted redirect chain for logging
     */
    public static function getChainAsString()
    {
        $chain = self::getChain();
        
        if (empty($chain)) {
            return 'No redirects in chain';
        }
        
        $lines = [];
        foreach ($chain as $index => $entry) {
            $lines[] = sprintf(
                '%d. %s (Reason: %s, Time: %s)',
                $index + 1,
                $entry['url'],
                $entry['reason'],
                date('Y-m-d H:i:s', $entry['timestamp'])
            );
        }
        
        return implode("\n", $lines);
    }
}
