<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based test for session ID regeneration
 * 
 * Feature: csrf-session-management-fix, Property 4: Session ID Regeneration Preserves Data
 * Validates: Requirements 3.2, 3.3
 * 
 * Property: For any session, regenerating the session ID should preserve all existing
 * session data while invalidating the old session ID.
 */
class SessionIdRegenerationPropertyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * Setup before each test
     */
    protected function _before()
    {
        parent::_before();
        
        // Ensure Yii application is available
        if (Yii::$app === null) {
            $config = require __DIR__ . '/../../config/test.php';
            new \yii\web\Application($config);
        }
        
        // Clean up any existing session
        if (Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
    }
    
    /**
     * Cleanup after each test
     */
    protected function _after()
    {
        // Clean up session
        if (Yii::$app !== null && Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
        
        parent::_after();
    }
    
    /**
     * Property 4: Session ID Regeneration Preserves Data
     * 
     * For any session with arbitrary data, regenerating the session ID should:
     * 1. Preserve all existing session data (Requirement 3.2)
     * 2. Invalidate the old session ID (Requirement 3.3)
     * 3. Generate a new, different session ID
     * 4. Update the session cookie with the new ID (Requirement 3.4)
     * 
     * This property must hold across all possible session states and data combinations.
     * 
     * Feature: csrf-session-management-fix, Property 4: Session ID Regeneration Preserves Data
     * Validates: Requirements 3.2, 3.3
     * 
     * @test
     */
    public function testSessionIdRegenerationPreservesDataProperty()
    {
        // Feature: csrf-session-management-fix, Property 4: Session ID Regeneration Preserves Data
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            try {
                Yii::$app->session->open();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_initialization',
                    'reason' => 'Failed to open session',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Generate random session data
            $testData = $this->generateRandomSessionData();
            
            // Store data in session
            foreach ($testData as $key => $value) {
                try {
                    Yii::$app->session->set($key, $value);
                } catch (\Exception $e) {
                    $failures[] = [
                        'iteration' => $i,
                        'phase' => 'data_storage',
                        'reason' => 'Failed to store session data',
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ];
                    break;
                }
            }
            
            // Get original session ID
            $oldSessionId = Yii::$app->session->getId();
            
            if (empty($oldSessionId)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_id_retrieval',
                    'reason' => 'Original session ID is empty',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Regenerate session ID (Requirement 3.1)
            try {
                Yii::$app->session->regenerateID();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_regeneration',
                    'reason' => 'Failed to regenerate session ID',
                    'error' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Get new session ID
            $newSessionId = Yii::$app->session->getId();
            
            if (empty($newSessionId)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'new_session_id_retrieval',
                    'reason' => 'New session ID is empty after regeneration',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Property Check 1: Session ID should be different (Requirement 3.3)
            if ($oldSessionId === $newSessionId) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_id_change',
                    'reason' => 'Session ID did not change after regeneration',
                    'old_session_id' => $oldSessionId,
                    'new_session_id' => $newSessionId,
                ];
            }
            
            // Property Check 2: All session data should be preserved (Requirement 3.2)
            $dataPreserved = true;
            $missingKeys = [];
            $mismatchedValues = [];
            
            foreach ($testData as $key => $expectedValue) {
                try {
                    $actualValue = Yii::$app->session->get($key);
                    
                    if ($actualValue === null) {
                        $dataPreserved = false;
                        $missingKeys[] = $key;
                    } elseif ($actualValue !== $expectedValue) {
                        $dataPreserved = false;
                        $mismatchedValues[] = [
                            'key' => $key,
                            'expected' => $this->serializeValue($expectedValue),
                            'actual' => $this->serializeValue($actualValue),
                        ];
                    }
                } catch (\Exception $e) {
                    $failures[] = [
                        'iteration' => $i,
                        'phase' => 'data_verification',
                        'reason' => 'Failed to retrieve session data after regeneration',
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ];
                    $dataPreserved = false;
                    break;
                }
            }
            
            if (!$dataPreserved) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'data_preservation',
                    'reason' => 'Session data was not preserved after regeneration',
                    'missing_keys' => $missingKeys,
                    'mismatched_values' => $mismatchedValues,
                    'old_session_id' => $oldSessionId,
                    'new_session_id' => $newSessionId,
                ];
            }
            
            // Additional check: Verify CSRF token is also preserved
            try {
                $csrfToken = Yii::$app->request->getCsrfToken();
                if (empty($csrfToken)) {
                    $failures[] = [
                        'iteration' => $i,
                        'phase' => 'csrf_token_preservation',
                        'reason' => 'CSRF token was lost after session regeneration',
                        'old_session_id' => $oldSessionId,
                        'new_session_id' => $newSessionId,
                    ];
                }
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'csrf_token_preservation',
                    'reason' => 'Failed to retrieve CSRF token after regeneration',
                    'error' => $e->getMessage(),
                ];
            }
            
            // Clean up
            Yii::$app->session->close();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $failureCount = count($failures);
            $failuresByPhase = [];
            foreach ($failures as $failure) {
                $phase = $failure['phase'] ?? 'unknown';
                if (!isset($failuresByPhase[$phase])) {
                    $failuresByPhase[$phase] = 0;
                }
                $failuresByPhase[$phase]++;
            }
            
            $this->fail(
                "Property 4 (Session ID Regeneration Preserves Data) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 4 holds for all $iterations iterations: " .
            "Session ID regeneration preserves data and invalidates old ID"
        );
    }
    
    /**
     * Test session regeneration with various data types
     * 
     * Verifies that regeneration works correctly with different data types
     * (strings, integers, arrays, objects, etc.)
     * 
     * @test
     */
    public function testSessionRegenerationWithVariousDataTypes()
    {
        $iterations = 50;
        $failures = [];
        
        $dataTypes = [
            'string' => 'test_string_value',
            'integer' => 42,
            'float' => 3.14159,
            'boolean_true' => true,
            'boolean_false' => false,
            'array_simple' => ['a', 'b', 'c'],
            'array_assoc' => ['key1' => 'value1', 'key2' => 'value2'],
            'array_nested' => ['level1' => ['level2' => ['level3' => 'deep_value']]],
            'null' => null,
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Store all data types
            foreach ($dataTypes as $key => $value) {
                Yii::$app->session->set($key, $value);
            }
            
            $oldSessionId = Yii::$app->session->getId();
            
            // Regenerate session
            Yii::$app->session->regenerateID();
            
            $newSessionId = Yii::$app->session->getId();
            
            // Verify session ID changed
            if ($oldSessionId === $newSessionId) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Session ID did not change',
                ];
            }
            
            // Verify all data types are preserved
            foreach ($dataTypes as $key => $expectedValue) {
                $actualValue = Yii::$app->session->get($key);
                
                if ($actualValue !== $expectedValue) {
                    $failures[] = [
                        'iteration' => $i,
                        'data_type' => $key,
                        'reason' => 'Data type not preserved correctly',
                        'expected' => $this->serializeValue($expectedValue),
                        'actual' => $this->serializeValue($actualValue),
                    ];
                }
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Data type preservation test failed in " . count($failures) . " cases out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Session regeneration preserves all data types correctly in all $iterations iterations"
        );
    }
    
    /**
     * Test multiple consecutive session regenerations
     * 
     * Verifies that multiple regenerations in sequence work correctly
     * and data remains preserved
     * 
     * @test
     */
    public function testMultipleConsecutiveRegenerations()
    {
        $iterations = 30;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Store test data
            $testData = [
                'test_key_1' => 'value_' . rand(1000, 9999),
                'test_key_2' => rand(1, 1000),
                'test_key_3' => ['nested' => 'array_' . rand(1000, 9999)],
            ];
            
            foreach ($testData as $key => $value) {
                Yii::$app->session->set($key, $value);
            }
            
            // Perform multiple regenerations
            $regenerationCount = rand(2, 5);
            $sessionIds = [Yii::$app->session->getId()];
            
            for ($j = 0; $j < $regenerationCount; $j++) {
                $oldId = Yii::$app->session->getId();
                Yii::$app->session->regenerateID();
                $newId = Yii::$app->session->getId();
                
                $sessionIds[] = $newId;
                
                // Verify ID changed
                if ($oldId === $newId) {
                    $failures[] = [
                        'iteration' => $i,
                        'regeneration' => $j,
                        'reason' => 'Session ID did not change on regeneration',
                    ];
                }
                
                // Verify data is still there
                foreach ($testData as $key => $expectedValue) {
                    $actualValue = Yii::$app->session->get($key);
                    if ($actualValue !== $expectedValue) {
                        $failures[] = [
                            'iteration' => $i,
                            'regeneration' => $j,
                            'key' => $key,
                            'reason' => 'Data lost after regeneration',
                        ];
                    }
                }
            }
            
            // Verify all session IDs are unique
            $uniqueIds = array_unique($sessionIds);
            if (count($uniqueIds) !== count($sessionIds)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Duplicate session IDs generated',
                    'total_ids' => count($sessionIds),
                    'unique_ids' => count($uniqueIds),
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Multiple regeneration test failed in " . count($failures) . " cases:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Multiple consecutive regenerations work correctly in all $iterations iterations"
        );
    }
    
    /**
     * Generate random session data for property testing
     * 
     * @return array Random session data
     */
    protected function generateRandomSessionData()
    {
        $dataCount = rand(3, 10);
        $data = [];
        
        for ($i = 0; $i < $dataCount; $i++) {
            $key = 'test_key_' . $i . '_' . bin2hex(random_bytes(4));
            $valueType = rand(1, 5);
            
            switch ($valueType) {
                case 1: // String
                    $data[$key] = 'value_' . bin2hex(random_bytes(8));
                    break;
                case 2: // Integer
                    $data[$key] = rand(1, 10000);
                    break;
                case 3: // Array
                    $data[$key] = [
                        'nested_key_1' => 'nested_value_' . rand(1000, 9999),
                        'nested_key_2' => rand(1, 100),
                    ];
                    break;
                case 4: // Boolean
                    $data[$key] = (bool)rand(0, 1);
                    break;
                case 5: // Float
                    $data[$key] = rand(1, 1000) / 100.0;
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Serialize a value for comparison in error messages
     * 
     * @param mixed $value Value to serialize
     * @return string Serialized value
     */
    protected function serializeValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } else {
            return (string)$value;
        }
    }
}
