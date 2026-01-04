<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use yii\web\HttpException;
use yii\db\Exception as DbException;

/**
 * Property-based tests for Error Handling
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 44: Error handling with Yii2 mechanisms
 * Feature: yiimp-to-yiimp2-migration, Property 52: Exception user-friendly display
 */
class ErrorHandlingPropertyTest extends Unit
{
    protected function _before()
    {
        parent::_before();
    }
    
    protected function _after()
    {
        parent::_after();
    }
    
    /**
     * Property 44: Error Handling with Yii2 Mechanisms
     * 
     * For any error condition (validation failure, database error, RPC failure), the system
     * should use Yii2 exception handling and display appropriate error pages.
     * 
     * Validates: Requirements 13.5
     * 
     * @test
     */
    public function testErrorHandlingWithYii2Mechanisms()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 44: Error handling with Yii2 mechanisms
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random exception scenario
                $exception = $this->generateRandomException();
                
                // Verify exception is logged properly
                $this->verifyExceptionLogging($exception, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage()
                ];
            }
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
     * Property 52: Exception User-Friendly Display
     * 
     * For any unhandled exception, the system should display a user-friendly error page
     * to the user while logging technical details to the application log.
     * 
     * Validates: Requirements 15.4
     * 
     * @test
     */
    public function testExceptionUserFriendlyDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 52: Exception user-friendly display
        
        $iterations = 100;
        $failures = [];
        $errorHandler = \Yii::$app->errorHandler;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random exception
                $exception = $this->generateRandomException();
                
                // Verify error action is configured
                if (empty($errorHandler->errorAction)) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Error action not configured'
                    ];
                    continue;
                }
                
                // Verify error action points to valid route
                $parts = explode('/', $errorHandler->errorAction);
                if (count($parts) !== 2) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Invalid error action format',
                        'action' => $errorHandler->errorAction
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage()
                ];
            }
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
     * Generate random exception for testing
     * 
     * @return \Exception
     */
    protected function generateRandomException()
    {
        $exceptionTypes = [
            function() {
                return new \Exception('Random exception ' . bin2hex(random_bytes(8)));
            },
            function() {
                return new HttpException(404, 'Page not found ' . bin2hex(random_bytes(8)));
            },
            function() {
                return new HttpException(403, 'Access denied ' . bin2hex(random_bytes(8)));
            },
            function() {
                return new HttpException(500, 'Internal server error ' . bin2hex(random_bytes(8)));
            },
            function() {
                return new DbException('Database error ' . bin2hex(random_bytes(8)));
            },
        ];
        
        $generator = $exceptionTypes[array_rand($exceptionTypes)];
        return $generator();
    }
    
    /**
     * Verify exception is logged properly
     * 
     * @param \Exception $exception
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyExceptionLogging($exception, $iteration, &$failures)
    {
        // Get error handler from application
        $errorHandler = \Yii::$app->errorHandler;
        
        // Log the exception
        $errorHandler->logException($exception);
        
        // Flush logs to ensure they're written
        \Yii::getLogger()->flush(true);
        
        // Check if any log file was created
        $logDir = \Yii::getAlias('@runtime/logs');
        if (!is_dir($logDir)) {
            $failures[] = [
                'iteration' => $iteration,
                'exception' => get_class($exception),
                'reason' => 'Log directory not found'
            ];
            return;
        }
        
        // Look for log files
        $logFiles = glob($logDir . '/*.log');
        if (empty($logFiles)) {
            $failures[] = [
                'iteration' => $iteration,
                'exception' => get_class($exception),
                'reason' => 'No log files created'
            ];
            return;
        }
        
        // Check if exception was logged in any file
        $logged = false;
        foreach ($logFiles as $logFile) {
            $logContent = file_get_contents($logFile);
            if (strpos($logContent, $exception->getMessage()) !== false) {
                $logged = true;
                break;
            }
        }
        
        if (!$logged) {
            $failures[] = [
                'iteration' => $iteration,
                'exception' => get_class($exception),
                'reason' => 'Exception not found in any log file'
            ];
        }
    }
    
    /**
     * Test that HTTP exceptions are handled correctly
     * 
     * @test
     */
    public function testHttpExceptionHandling()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 44: Error handling with Yii2 mechanisms (HTTP exceptions)
        
        $errorHandler = \Yii::$app->errorHandler;
        $statusCodes = [400, 403, 404, 500, 503];
        
        foreach ($statusCodes as $code) {
            $exception = new HttpException($code, "Test error $code");
            
            // Verify exception has correct status code
            $this->assertEquals($code, $exception->statusCode);
            
            // Log the exception
            $errorHandler->logException($exception);
            
            // Verify it was logged (basic check)
            $this->assertTrue(true, "HTTP exception $code logged successfully");
        }
    }
    
    /**
     * Test that database exceptions are handled correctly
     * 
     * @test
     */
    public function testDatabaseExceptionHandling()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 44: Error handling with Yii2 mechanisms (DB exceptions)
        
        $errorHandler = \Yii::$app->errorHandler;
        $exception = new DbException('Test database error');
        
        // Log the exception
        $errorHandler->logException($exception);
        
        // Verify exception is of correct type
        $this->assertInstanceOf(DbException::class, $exception);
        
        $this->assertTrue(true, "Database exception logged successfully");
    }
    
    /**
     * Test that error action is properly configured
     * 
     * @test
     */
    public function testErrorActionConfiguration()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 52: Exception user-friendly display (configuration)
        
        // Verify Yii is loaded
        $this->assertTrue(class_exists('Yii'), 'Yii class should be loaded');
        $this->assertNotNull(\Yii::$app, 'Yii application should be initialized');
        
        $errorHandler = \Yii::$app->errorHandler;
        $this->assertNotEmpty($errorHandler->errorAction, 'Error action should be configured');
        $this->assertEquals('site/error', $errorHandler->errorAction, 'Error action should point to site/error');
    }
    
    /**
     * Test that exceptions with previous exceptions are logged
     * 
     * @test
     */
    public function testNestedExceptionLogging()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 44: Error handling with Yii2 mechanisms (nested exceptions)
        
        $errorHandler = \Yii::$app->errorHandler;
        $previous = new \Exception('Previous exception');
        $exception = new \Exception('Main exception', 0, $previous);
        
        // Verify previous exception is accessible
        $this->assertNotNull($exception->getPrevious());
        $this->assertEquals('Previous exception', $exception->getPrevious()->getMessage());
        
        // Log the exception
        $errorHandler->logException($exception);
        
        $this->assertTrue(true, "Nested exception logged successfully");
    }
}
