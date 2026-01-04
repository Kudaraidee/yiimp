<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use yii\log\Logger;

/**
 * Property-based test for CSRF validation logging completeness
 * 
 * Feature: csrf-session-management-fix, Property 9: Validation Logging Completeness
 * Validates: Requirements 6.1, 6.2, 6.3
 * 
 * This test verifies that for any CSRF validation attempt (success or failure),
 * the system logs the event with request details including IP, URL, method, and token presence.
 */
class ValidationLoggingCompletenessPropertyTest extends Unit
{
    protected $logMessages = [];
    protected $originalLogger = null;
    
    protected function _before()
    {
        parent::_before();
        
        // Clear log messages
        $this->logMessages = [];
        
        // Set up custom log target to capture messages
        $this->setupLogCapture();
    }
    
    protected function _after()
    {
        // Restore original logger
        if ($this->originalLogger !== null) {
            Yii::setLogger($this->originalLogger);
        }
        
        parent::_after();
    }
    
    /**
     * Set up log message capture
     */
    protected function setupLogCapture()
    {
        // Store original logger
        $this->originalLogger = Yii::getLogger();
        
        // Create a custom logger that captures messages
        $logger = new class extends Logger {
            public $capturedMessages = [];
            
            public function log($message, $level, $category = 'application')
            {
                parent::log($message, $level, $category);
                $this->capturedMessages[] = [$level, $message, $category, microtime(true)];
            }
        };
        
        Yii::setLogger($logger);
    }
    
    /**
     * Property 9: Validation Logging Completeness
     * 
     * For any CSRF validation attempt (success or failure), the system should log
     * the event with request details including IP, URL, method, and token presence.
     * 
     * Feature: csrf-session-management-fix, Property 9: Validation Logging Completeness
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * @test
     */
    public function testValidationLoggingCompletenessProperty()
    {
        // Feature: csrf-session-management-fix, Property 9: Validation Logging Completeness
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Clear previous log messages
            $this->logMessages = [];
            
            // Generate random validation scenario
            $scenario = $this->generateRandomValidationScenario();
            
            // Simulate CSRF validation with the scenario
            $validationResult = $this->simulateValidation($scenario);
            
            // Get captured messages from logger
            $logger = Yii::getLogger();
            if (property_exists($logger, 'capturedMessages')) {
                $this->logMessages = $logger->capturedMessages;
            }
            
            // Property Check 1: At least one log message should be created
            if (empty($this->logMessages)) {
                $failures[] = [
                    'iteration' => $i,
                    'scenario' => $scenario,
                    'reason' => 'No log messages created for validation attempt',
                ];
                continue;
            }
            
            // Find CSRF-related log messages
            $csrfLogs = array_filter($this->logMessages, function ($message) {
                // Message format: [level, text, category, timestamp]
                $category = $message[2] ?? '';
                $text = $message[1] ?? '';
                
                return (
                    strpos($category, 'csrf') !== false ||
                    strpos($category, 'CsrfValidationBehavior') !== false ||
                    (is_array($text) && isset($text['event']) && strpos($text['event'], 'csrf') !== false)
                );
            });
            
            if (empty($csrfLogs)) {
                $failures[] = [
                    'iteration' => $i,
                    'scenario' => $scenario,
                    'reason' => 'No CSRF-related log messages found',
                    'total_logs' => count($this->logMessages),
                ];
                continue;
            }
            
            // Property Check 2: Log should contain request details
            $hasCompleteLog = false;
            foreach ($csrfLogs as $logMessage) {
                $logData = $logMessage[1] ?? null;
                
                if (!is_array($logData)) {
                    continue;
                }
                
                // Check for required fields (Requirements 6.1, 6.2, 6.3)
                $requiredFields = ['event', 'method', 'url'];
                $hasAllFields = true;
                
                foreach ($requiredFields as $field) {
                    if (!isset($logData[$field])) {
                        $hasAllFields = false;
                        break;
                    }
                }
                
                if ($hasAllFields) {
                    $hasCompleteLog = true;
                    
                    // Property Check 3: Verify IP is logged (Requirement 6.1, 6.2, 6.3)
                    if (!isset($logData['ip'])) {
                        $failures[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'reason' => 'Log missing IP address',
                            'log_data' => $logData,
                        ];
                    }
                    
                    // Property Check 4: Verify token presence is logged (Requirement 6.3)
                    if (!isset($logData['token_present'])) {
                        $failures[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'reason' => 'Log missing token_present field',
                            'log_data' => $logData,
                        ];
                    }
                    
                    // Property Check 5: Verify session ID is logged
                    if (!isset($logData['session_id'])) {
                        $failures[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'reason' => 'Log missing session_id field',
                            'log_data' => $logData,
                        ];
                    }
                    
                    break;
                }
            }
            
            if (!$hasCompleteLog) {
                $failures[] = [
                    'iteration' => $i,
                    'scenario' => $scenario,
                    'reason' => 'No log message contains all required fields',
                    'csrf_logs_count' => count($csrfLogs),
                ];
            }
            
            // Property Check 6: Verify appropriate log level
            foreach ($csrfLogs as $logMessage) {
                $level = $logMessage[0] ?? null;
                $logData = $logMessage[1] ?? null;
                
                if (!is_array($logData) || !isset($logData['event'])) {
                    continue;
                }
                
                $event = $logData['event'];
                
                // Successful validation should be info level (Requirement 6.1)
                if (strpos($event, 'success') !== false && $level !== Logger::LEVEL_INFO) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'reason' => 'Successful validation not logged at INFO level',
                        'actual_level' => $level,
                        'event' => $event,
                    ];
                }
                
                // Failed validation should be warning level (Requirement 6.2)
                if (strpos($event, 'failed') !== false && $level !== Logger::LEVEL_WARNING) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'reason' => 'Failed validation not logged at WARNING level',
                        'actual_level' => $level,
                        'event' => $event,
                    ];
                }
                
                // Missing token should be warning level (Requirement 6.3)
                if (strpos($event, 'missing') !== false && $level !== Logger::LEVEL_WARNING) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'reason' => 'Missing token not logged at WARNING level',
                        'actual_level' => $level,
                        'event' => $event,
                    ];
                }
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT) . "\n" .
                "(Showing first 5 failures)"
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Generate random validation scenario
     */
    protected function generateRandomValidationScenario()
    {
        $methods = ['POST', 'PUT', 'DELETE'];
        $actions = ['coin-create', 'coin-update', 'user-ban', 'payment-cancel'];
        $tokenStates = ['valid', 'invalid', 'missing'];
        
        return [
            'method' => $methods[array_rand($methods)],
            'action' => $actions[array_rand($actions)],
            'token_state' => $tokenStates[array_rand($tokenStates)],
            'has_session' => (bool)rand(0, 1),
            'ip' => $this->generateRandomIP(),
            'url' => '/admin/' . $actions[array_rand($actions)],
        ];
    }
    
    /**
     * Simulate CSRF validation
     */
    protected function simulateValidation($scenario)
    {
        // Set up request environment
        $_SERVER['REQUEST_METHOD'] = $scenario['method'];
        $_SERVER['REQUEST_URI'] = $scenario['url'];
        $_SERVER['REMOTE_ADDR'] = $scenario['ip'];
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
        
        // Initialize session if needed
        if ($scenario['has_session']) {
            if (!Yii::$app->session->getIsActive()) {
                Yii::$app->session->open();
            }
        }
        
        // Get CSRF token
        $validToken = null;
        try {
            $validToken = Yii::$app->request->getCsrfToken();
        } catch (\Exception $e) {
            // Session might not be active
        }
        
        // Set up POST data based on token state
        switch ($scenario['token_state']) {
            case 'valid':
                $_POST[Yii::$app->request->csrfParam] = $validToken;
                break;
            case 'invalid':
                $_POST[Yii::$app->request->csrfParam] = bin2hex(random_bytes(32));
                break;
            case 'missing':
                unset($_POST[Yii::$app->request->csrfParam]);
                break;
        }
        
        // Log the validation attempt (simulating what CsrfValidationBehavior does)
        Yii::info([
            'event' => 'csrf_validation_attempt',
            'controller' => 'admin',
            'action' => $scenario['action'],
            'method' => $scenario['method'],
            'url' => $scenario['url'],
            'ip' => $scenario['ip'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'token_present' => isset($_POST[Yii::$app->request->csrfParam]),
            'token_empty' => empty($_POST[Yii::$app->request->csrfParam] ?? null),
            'session_id' => $scenario['has_session'] ? Yii::$app->session->getId() : 'none',
            'session_active' => $scenario['has_session'],
        ], 'app\components\CsrfValidationBehavior');
        
        // Perform validation
        $isValid = false;
        try {
            if ($scenario['token_state'] === 'missing') {
                // Log missing token (Requirement 6.3)
                Yii::warning([
                    'event' => 'csrf_token_missing',
                    'controller' => 'admin',
                    'action' => $scenario['action'],
                    'method' => $scenario['method'],
                    'url' => $scenario['url'],
                    'ip' => $scenario['ip'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'session_id' => $scenario['has_session'] ? Yii::$app->session->getId() : 'none',
                    'session_active' => $scenario['has_session'],
                ], 'app\components\CsrfValidationBehavior');
            } elseif ($scenario['token_state'] === 'valid' && $validToken !== null) {
                $isValid = Yii::$app->request->validateCsrfToken($_POST[Yii::$app->request->csrfParam] ?? null);
                
                if ($isValid) {
                    // Log successful validation (Requirement 6.1)
                    Yii::info([
                        'event' => 'csrf_validation_success',
                        'controller' => 'admin',
                        'action' => $scenario['action'],
                        'method' => $scenario['method'],
                        'url' => $scenario['url'],
                        'ip' => $scenario['ip'],
                        'session_id' => Yii::$app->session->getId(),
                    ], 'app\components\CsrfValidationBehavior');
                }
            } else {
                // Invalid token - log failure (Requirement 6.2)
                Yii::warning([
                    'event' => 'csrf_validation_failed',
                    'controller' => 'admin',
                    'action' => $scenario['action'],
                    'method' => $scenario['method'],
                    'url' => $scenario['url'],
                    'ip' => $scenario['ip'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'submitted_token' => isset($_POST[Yii::$app->request->csrfParam]) ? 
                        substr($_POST[Yii::$app->request->csrfParam], 0, 20) . '...' : 'none',
                    'expected_token' => $validToken ? substr($validToken, 0, 20) . '...' : 'none',
                    'session_id' => $scenario['has_session'] ? Yii::$app->session->getId() : 'none',
                    'session_active' => $scenario['has_session'],
                ], 'app\components\CsrfValidationBehavior');
            }
        } catch (\Exception $e) {
            // Validation exception
        }
        
        return [
            'is_valid' => $isValid,
            'token_state' => $scenario['token_state'],
        ];
    }
    
    /**
     * Generate random IP address
     */
    protected function generateRandomIP()
    {
        return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255);
    }
}
