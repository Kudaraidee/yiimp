<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Session Configuration Verifier
 * 
 * Verifies session configuration on application start and provides
 * detailed logging for troubleshooting CSRF validation issues.
 * 
 * Requirements: 6.1, 7.1, 7.2, 7.3, 7.5
 */
class SessionConfigVerifier extends Component
{
    /**
     * Verify session configuration on application initialization
     * 
     * This method should be called during application bootstrap to ensure
     * all session-related configuration is properly loaded and valid.
     * 
     * @throws InvalidConfigException if critical configuration is missing
     */
    public static function verify()
    {
        $logger = Yii::getLogger();
        
        // Log protocol detection (Requirement 3.5)
        self::logProtocolDetection();
        
        // Verify cookie validation key is loaded (Requirement 7.1)
        $cookieKey = self::verifyCookieValidationKey();
        
        // Verify session component configuration (Requirement 6.1)
        self::verifySessionComponent();
        
        // Log browser user agent (Requirement 3.4)
        self::logBrowserInfo();
        
        // Log successful verification
        $logger->log(
            'Session configuration verified successfully',
            \yii\log\Logger::LEVEL_INFO,
            'app\components\SessionConfigVerifier'
        );
    }
    
    /**
     * Verify cookie validation key is loaded correctly
     * 
     * Requirement 7.1: Load cookie validation key from environment or config
     * Requirement 7.2: Generate fallback key with warning if missing
     * Requirement 7.3: Verify key is set when CSRF validation is enabled
     * 
     * @return string The cookie validation key
     * @throws InvalidConfigException if key cannot be loaded or generated
     */
    protected static function verifyCookieValidationKey()
    {
        $logger = Yii::getLogger();
        $request = Yii::$app->request;
        
        // Check if cookie validation key is set
        $cookieKey = $request->cookieValidationKey;
        
        if (empty($cookieKey)) {
            // Requirement 7.2: Generate fallback key with warning
            $logger->log(
                'WARNING: Cookie validation key not set. Generating temporary key. ' .
                'Set YIIMP_COOKIE_VALIDATION_KEY environment variable for production.',
                \yii\log\Logger::LEVEL_WARNING,
                'app\components\SessionConfigVerifier'
            );
            
            // Generate a temporary key
            $cookieKey = bin2hex(random_bytes(32));
            $request->cookieValidationKey = $cookieKey;
            
            $logger->log(
                'Temporary cookie validation key generated (length: ' . strlen($cookieKey) . ')',
                \yii\log\Logger::LEVEL_INFO,
                'app\components\SessionConfigVerifier'
            );
        } else {
            // Log successful key load
            $logger->log(
                'Cookie validation key loaded successfully (length: ' . strlen($cookieKey) . ')',
                \yii\log\Logger::LEVEL_INFO,
                'app\components\SessionConfigVerifier'
            );
        }
        
        // Requirement 7.3: Verify key is set when CSRF validation is enabled
        if ($request->enableCsrfValidation && empty($cookieKey)) {
            $errorMsg = 'CSRF validation is enabled but cookie validation key is not set';
            $logger->log(
                $errorMsg,
                \yii\log\Logger::LEVEL_ERROR,
                'app\components\SessionConfigVerifier'
            );
            throw new InvalidConfigException($errorMsg);
        }
        
        return $cookieKey;
    }
    
    /**
     * Verify session component configuration
     * 
     * Requirement 6.1: Initialize session component with proper configuration
     * Requirement 7.5: Log configuration validation errors
     * 
     * @throws InvalidConfigException if session configuration is invalid
     */
    protected static function verifySessionComponent()
    {
        $logger = Yii::getLogger();
        
        try {
            $session = Yii::$app->session;
            
            // Verify session component exists
            if (!$session) {
                $errorMsg = 'Session component not configured';
                $logger->log(
                    $errorMsg,
                    \yii\log\Logger::LEVEL_ERROR,
                    'app\components\SessionConfigVerifier'
                );
                throw new InvalidConfigException($errorMsg);
            }
            
            // Get session configuration
            $sessionName = $session->getName();
            $timeout = $session->getTimeout();
            $useCookies = $session->getUseCookies();
            $cookieParams = $session->getCookieParams();
            
            // Log session configuration with detailed cookie parameters
            $logger->log(
                'Session configuration: ' . json_encode([
                    'name' => $sessionName,
                    'timeout' => $timeout,
                    'useCookies' => $useCookies,
                    'cookieParams' => [
                        'httponly' => $cookieParams['httponly'] ?? false,
                        'secure' => $cookieParams['secure'] ?? false,
                        'sameSite' => $cookieParams['sameSite'] ?? null,
                        'path' => $cookieParams['path'] ?? '/',
                        'domain' => $cookieParams['domain'] ?? '',
                    ],
                ]),
                \yii\log\Logger::LEVEL_INFO,
                'app\components\SessionConfigVerifier'
            );
            
            // Log detailed cookie parameter analysis
            $logger->log(
                sprintf(
                    'Cookie parameters: httponly=%s, secure=%s, sameSite=%s, path=%s, domain=%s',
                    ($cookieParams['httponly'] ?? false) ? 'true' : 'false',
                    ($cookieParams['secure'] ?? false) ? 'true' : 'false',
                    $cookieParams['sameSite'] ?? 'null',
                    $cookieParams['path'] ?? '/',
                    $cookieParams['domain'] ?? 'empty'
                ),
                \yii\log\Logger::LEVEL_INFO,
                'app\components\SessionConfigVerifier'
            );
            
            // Verify critical session settings
            if (empty($sessionName)) {
                $errorMsg = 'Session name is not configured';
                $logger->log(
                    $errorMsg,
                    \yii\log\Logger::LEVEL_ERROR,
                    'app\components\SessionConfigVerifier'
                );
                throw new InvalidConfigException($errorMsg);
            }
            
            if ($timeout <= 0) {
                $errorMsg = 'Session timeout must be greater than 0';
                $logger->log(
                    $errorMsg,
                    \yii\log\Logger::LEVEL_ERROR,
                    'app\components\SessionConfigVerifier'
                );
                throw new InvalidConfigException($errorMsg);
            }
            
            // Verify cookie security settings
            if (!isset($cookieParams['httponly']) || !$cookieParams['httponly']) {
                $logger->log(
                    'WARNING: Session cookie httponly flag is not set. This is a security risk.',
                    \yii\log\Logger::LEVEL_WARNING,
                    'app\components\SessionConfigVerifier'
                );
            }
            
            // Check if running on HTTPS and secure flag is set
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            if ($isHttps && (!isset($cookieParams['secure']) || !$cookieParams['secure'])) {
                $logger->log(
                    'WARNING: Running on HTTPS but session cookie secure flag is not set.',
                    \yii\log\Logger::LEVEL_WARNING,
                    'app\components\SessionConfigVerifier'
                );
            }
            
        } catch (\Exception $e) {
            // Requirement 7.5: Log configuration validation errors
            $logger->log(
                'Session configuration validation error: ' . $e->getMessage(),
                \yii\log\Logger::LEVEL_ERROR,
                'app\components\SessionConfigVerifier'
            );
            throw $e;
        }
    }
    
    /**
     * Get current session configuration as array
     * 
     * Useful for debugging and diagnostics
     * 
     * @return array Session configuration details
     */
    public static function getSessionConfig()
    {
        $session = Yii::$app->session;
        $request = Yii::$app->request;
        
        return [
            'session_name' => $session->getName(),
            'session_timeout' => $session->getTimeout(),
            'session_use_cookies' => $session->getUseCookies(),
            'session_cookie_params' => $session->getCookieParams(),
            'csrf_enabled' => $request->enableCsrfValidation,
            'csrf_param' => $request->csrfParam,
            'cookie_validation_key_set' => !empty($request->cookieValidationKey),
            'cookie_validation_key_length' => strlen($request->cookieValidationKey ?? ''),
        ];
    }
    
    /**
     * Log protocol detection information
     * 
     * Requirement 3.5: Log HTTPS detection method and result
     * 
     * @return void
     */
    protected static function logProtocolDetection()
    {
        $logger = Yii::getLogger();
        
        // Detect protocol using same logic as web.php
        $isHttps = false;
        $httpsDetectionMethod = 'none';
        
        // Check for HTTPS indicators in order of reliability
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isHttps = true;
            $httpsDetectionMethod = 'direct HTTPS ($_SERVER[HTTPS]=' . $_SERVER['HTTPS'] . ')';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isHttps = true;
            $httpsDetectionMethod = 'X-Forwarded-Proto header';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $isHttps = true;
            $httpsDetectionMethod = 'X-Forwarded-SSL header';
        } elseif (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') {
            $isHttps = true;
            $httpsDetectionMethod = 'YIIMP_FORCE_HTTPS environment variable';
        }
        
        // Explicit HTTP detection for localhost
        if (!$isHttps) {
            $serverName = $_SERVER['SERVER_NAME'] ?? '';
            $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            
            if ($serverName === 'localhost' || 
                $serverAddr === '127.0.0.1' || 
                strpos($httpHost, 'localhost') === 0 ||
                strpos($httpHost, '127.0.0.1') === 0) {
                $httpsDetectionMethod = 'HTTP localhost detected';
            } else {
                $httpsDetectionMethod = 'HTTP (no HTTPS indicators)';
            }
        }
        
        // Log protocol detection result
        $logger->log(
            sprintf(
                'Protocol detection: %s | Method: %s | Host: %s | Port: %s',
                $isHttps ? 'HTTPS' : 'HTTP',
                $httpsDetectionMethod,
                $_SERVER['HTTP_HOST'] ?? 'unknown',
                $_SERVER['SERVER_PORT'] ?? 'unknown'
            ),
            \yii\log\Logger::LEVEL_INFO,
            'app\components\SessionConfigVerifier'
        );
        
        // Log relevant server variables
        $logger->log(
            sprintf(
                'Server variables: HTTPS=%s, X-Forwarded-Proto=%s, X-Forwarded-SSL=%s, SERVER_NAME=%s',
                $_SERVER['HTTPS'] ?? 'not set',
                $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
                $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set',
                $_SERVER['SERVER_NAME'] ?? 'not set'
            ),
            \yii\log\Logger::LEVEL_INFO,
            'app\components\SessionConfigVerifier'
        );
    }
    
    /**
     * Log browser information
     * 
     * Requirement 3.4: Add browser user agent to logs
     * 
     * @return void
     */
    protected static function logBrowserInfo()
    {
        $logger = Yii::getLogger();
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logger->log(
            sprintf(
                'Browser info: User-Agent=%s | IP=%s | Method=%s | URI=%s',
                $userAgent,
                $remoteAddr,
                $requestMethod,
                $requestUri
            ),
            \yii\log\Logger::LEVEL_INFO,
            'app\components\SessionConfigVerifier'
        );
    }
    
    /**
     * Log session initialization success
     * 
     * Requirement 3.3: Log session initialization success/failure
     * 
     * @return void
     */
    public static function logSessionInitSuccess()
    {
        $logger = Yii::getLogger();
        
        try {
            $session = Yii::$app->session;
            
            if ($session->getIsActive()) {
                $logger->log(
                    sprintf(
                        'Session initialized successfully: ID=%s | Name=%s | Timeout=%d',
                        $session->getId(),
                        $session->getName(),
                        $session->getTimeout()
                    ),
                    \yii\log\Logger::LEVEL_INFO,
                    'app\components\SessionConfigVerifier'
                );
            } else {
                $logger->log(
                    'Session component exists but is not active',
                    \yii\log\Logger::LEVEL_WARNING,
                    'app\components\SessionConfigVerifier'
                );
            }
        } catch (\Exception $e) {
            self::logSessionInitFailure($e);
        }
    }
    
    /**
     * Log session initialization failure
     * 
     * Requirement 3.3: Log session initialization failure with detailed error information
     * 
     * @param \Exception $exception The exception that caused the failure
     * @return void
     */
    public static function logSessionInitFailure(\Exception $exception)
    {
        $logger = Yii::getLogger();
        
        $logger->log(
            sprintf(
                'Session initialization failed: %s | File: %s | Line: %d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ),
            \yii\log\Logger::LEVEL_ERROR,
            'app\components\SessionConfigVerifier'
        );
        
        // Log stack trace for debugging
        $logger->log(
            'Stack trace: ' . $exception->getTraceAsString(),
            \yii\log\Logger::LEVEL_ERROR,
            'app\components\SessionConfigVerifier'
        );
    }
    
    /**
     * Log cookie setting failure
     * 
     * Requirement 3.4: Log cookie parameters and browser state when cookie setting fails
     * 
     * @param array $cookieParams The cookie parameters that failed to set
     * @return void
     */
    public static function logCookieSettingFailure(array $cookieParams = [])
    {
        $logger = Yii::getLogger();
        
        // Get session cookie params if not provided
        if (empty($cookieParams)) {
            try {
                $session = Yii::$app->session;
                $cookieParams = $session->getCookieParams();
            } catch (\Exception $e) {
                $cookieParams = ['error' => 'Could not retrieve cookie params: ' . $e->getMessage()];
            }
        }
        
        $logger->log(
            sprintf(
                'Cookie setting failed | Cookie params: %s',
                json_encode($cookieParams)
            ),
            \yii\log\Logger::LEVEL_ERROR,
            'app\components\SessionConfigVerifier'
        );
        
        // Log browser state
        $logger->log(
            sprintf(
                'Browser state: User-Agent=%s | Accept-Language=%s | Accept-Encoding=%s | Connection=%s',
                $_SERVER['HTTP_USER_AGENT'] ?? 'not set',
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'not set',
                $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'not set',
                $_SERVER['HTTP_CONNECTION'] ?? 'not set'
            ),
            \yii\log\Logger::LEVEL_ERROR,
            'app\components\SessionConfigVerifier'
        );
        
        // Check if headers were already sent
        if (headers_sent($file, $line)) {
            $logger->log(
                sprintf(
                    'Headers already sent (output started at %s:%d) - cannot set cookie',
                    $file,
                    $line
                ),
                \yii\log\Logger::LEVEL_ERROR,
                'app\components\SessionConfigVerifier'
            );
        }
    }
}
